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
            ['Alla cortese attenzione di', 11, PdfLayoutService::FONT_REGULAR],
            [(string)$request['company_recipient'], 12, PdfLayoutService::FONT_BOLD],
            ['', 8, PdfLayoutService::FONT_REGULAR],
            ['Oggetto: ' . (string)$request['subject'], 13, PdfLayoutService::FONT_BOLD],
            ['', 8, PdfLayoutService::FONT_REGULAR],
            'La scrivente ' . (string)$request['union_name'] . ' richiede la fruizione di permesso sindacale.',
            'Delegato/RLS: ' . (string)$delegate['name'],
            'Tipologia permesso: ' . $this->permitLabel((string)$request['permit_type']),
            'Data richiesta: ' . (string)$request['request_date'],
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

        $pages = [$this->page($content, $request, $documentNumber, 1, $verifyUrl)];
        if ($signature !== null && $signature !== '') {
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
            'Delegato/RLS: ' . (string)$delegate['name'],
            'Tipologia: ' . $this->permitLabel((string)$request['permit_type']),
            'Ore: ' . (string)$request['hours'],
            'Oggetto: ' . (string)$request['subject'],
        ]);
    }

    private function page(string $content, array $request, string $documentNumber, int $page, ?string $verifyUrl, array $links = []): array
    {
        $pdfPage = $this->layout->page($content, [
            'number' => $documentNumber . ' / ' . $page,
            'protocol' => '-',
            'date' => (string)($request['created_at'] ?? date('Y-m-d H:i:s')),
            'creator' => (string)($request['creator_name'] ?? ''),
            'revision' => '-',
            'verify_text' => $verifyUrl ? 'Verifica autenticita copia digitale' : '-',
        ]);
        if ($verifyUrl !== null && $verifyUrl !== '') {
            $pdfPage['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
            $pdfPage['links'][] = ['rect' => PdfLayoutService::VERIFY_LINK_RECT, 'url' => $verifyUrl];
            $pdfPage['links'][] = ['rect' => [502, 728, 554, 780], 'url' => $verifyUrl];
        }
        $pdfPage['links'] = array_merge($pdfPage['links'], $links);
        return $pdfPage;
    }

    private function permitLabel(string $type): string
    {
        return $type === 'rls' ? 'RLS - problematica sicurezza' : 'RSU - problematica sindacale';
    }
}
