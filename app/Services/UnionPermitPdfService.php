<?php

declare(strict_types=1);

namespace App\Services;

final class UnionPermitPdfService
{
    public function __construct(
        private readonly PdfLayoutService $layout,
        private readonly PdfWriterService $writer,
        private readonly PdfQrService $qr
    ) {
    }

    public function write(
        string $targetPath,
        array $request,
        array $delegate,
        ?string $verifyUrl = null,
        ?string $signature = null
    ): void {
        $documentNumber = pathinfo($targetPath, PATHINFO_FILENAME);
        $content = '';
        $y = PdfLayoutService::BODY_TOP;
        $lines = [
            ['Oggetto: ' . (string)$request['subject'], 13, PdfLayoutService::FONT_BOLD],
            ['', 8, PdfLayoutService::FONT_REGULAR],
            'La scrivente ' . (string)$request['union_name'] . ' richiede la fruizione di permesso sindacale.',
            'Ambito richiesta: ' . $this->scopeLabel((string)$request['request_scope']),
            'Delegato/RLS: ' . (string)$delegate['name'],
            'Tipologia permesso: ' . $this->permitLabel((string)$request['permit_type']),
            'Oggetto: ' . (string)$request['subject'],
            'Giorno fruizione: ' . substr((string)$request['start_at'], 0, 10),
            'Dalle ore: ' . substr((string)$request['start_at'], 11, 5) . ' alle ore: ' . substr((string)$request['end_at'], 11, 5),
            'Ore richieste: ' . (string)$request['hours'],
        ];

        foreach ($lines as $line) {
            [$text, $size, $font] = is_array($line) ? $line : [$line, 11, PdfLayoutService::FONT_REGULAR];
            if ($text === '') {
                $y -= $size;
                continue;
            }
            $content .= $this->layout->text(42, $y, $size, $font, $text);
            $y -= $size + 9;
        }

        if (trim((string)($request['notes'] ?? '')) !== '') {
            $wrapped = $this->layout->wrappedText(42, $y - 6, 10, 96, 'Note: ' . (string)$request['notes']);
            $content .= $wrapped['content'];
            $y = $wrapped['y'];
        }

        $content .= $this->layout->text(42, $y - 20, 11, PdfLayoutService::FONT_REGULAR, 'Cordiali saluti.');
        $content .= $this->layout->text(42, $y - 42, 11, PdfLayoutService::FONT_BOLD, (string)$request['union_name']);

        $isExternal = (string)$request['request_scope'] === 'external';
        $pages = [$this->page($content, $request, $documentNumber, 1, $isExternal ? null : $verifyUrl)];
        if (!$isExternal && $signature !== null && $signature !== '') {
            $verification = $this->layout->verificationBlock(PdfLayoutService::BODY_TOP - 48, $signature, $verifyUrl);
            $pages[] = $this->page(
                $this->layout->text(42, PdfLayoutService::BODY_TOP, 18, PdfLayoutService::FONT_BOLD, 'Verifica documento') . $verification['content'],
                $request,
                $documentNumber,
                2,
                $verifyUrl,
                $verification['links']
            );
        }

        $this->writer->write($targetPath, $pages);
    }

    public function original(array $request, array $delegate): string
    {
        return implode("\n", [
            'Richiesta permesso sindacale',
            'Ambito: ' . $this->scopeLabel((string)$request['request_scope']),
            'Delegato/RLS: ' . (string)$delegate['name'],
            'Tipologia: ' . $this->permitLabel((string)$request['permit_type']),
            'Ore: ' . (string)$request['hours'],
            'Oggetto: ' . (string)$request['subject'],
        ]);
    }

    private function page(string $content, array $request, string $documentNumber, int $page, ?string $verifyUrl, array $links = []): array
    {
        $pdfPage = $this->layout->page($content, [
            'simple_brand' => (string)$request['request_scope'] === 'external',
            'brand' => (string)$request['request_scope'] === 'external' && isset($request['union_logo_image']) ? '' : ((string)$request['request_scope'] === 'external' ? (string)$request['union_name'] : 'RSU'),
            'brand_subtitle' => (string)$request['request_scope'] === 'external' && isset($request['union_logo_image']) ? '' : ((string)$request['request_scope'] === 'external' ? 'Sigla sindacale' : 'Canegrate'),
            'number' => $documentNumber . ' / ' . $page,
            'protocol' => (string)($request['protocol_number'] ?? '-'),
            'date' => (string)($request['protocol_created_at'] ?? $request['created_at'] ?? date('Y-m-d H:i:s')),
            'creator' => (string)($request['creator_name'] ?? ''),
            'revision' => '-',
            'verify_text' => $verifyUrl ? 'Verifica autenticita copia digitale' : '-',
        ]);
        if ($verifyUrl !== null && $verifyUrl !== '') {
            $pdfPage['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
            $pdfPage['links'][] = ['rect' => PdfLayoutService::VERIFY_LINK_RECT, 'url' => $verifyUrl];
            $pdfPage['links'][] = ['rect' => [502, 728, 554, 780], 'url' => $verifyUrl];
        }
        if ((string)$request['request_scope'] === 'external' && isset($request['union_logo_image'])) {
            $pdfPage['images'][] = $request['union_logo_image'];
        }
        $pdfPage['links'] = array_merge($pdfPage['links'], $links);
        return $pdfPage;
    }

    private function permitLabel(string $type): string
    {
        return $type === 'rls' ? 'RLS - problematica sicurezza' : 'RSU - problematica sindacale';
    }

    private function scopeLabel(string $scope): string
    {
        return $scope === 'external' ? 'esterno - sigla sindacale' : 'interno - RSU aziendale';
    }
}
