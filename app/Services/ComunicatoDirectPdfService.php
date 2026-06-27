<?php

declare(strict_types=1);

namespace App\Services;

final class ComunicatoDirectPdfService
{
    public function __construct(
        private readonly PdfLayoutService $layout,
        private readonly PdfWriterService $writer,
        private readonly PdfQrService $qr
    ) {
    }

    public function write(
        string $targetPath,
        string $title,
        string $body,
        string $protocolNumber,
        string $createdAt,
        ?string $documentNumber = null,
        ?string $verifyUrl = null,
        ?string $signature = null
    ): void {
        $pages = [];
        $y = PdfLayoutService::BODY_TOP;
        $content = $this->title($title, $y);
        $y -= 44;

        foreach ($this->lines($body) as $line) {
            if ($y < 92) {
                $pages[] = $this->page($content, $protocolNumber, $createdAt, $documentNumber, $verifyUrl);
                $content = '';
                $y = PdfLayoutService::BODY_TOP;
            }

            $content .= $line === ''
                ? ''
                : $this->layout->text(PdfLayoutService::MARGIN_LEFT, $y, 11, PdfLayoutService::FONT_REGULAR, $line);
            $y -= $line === '' ? 12 : 16;
        }

        $y = max(62, $y - 20);
        $content .= $this->layout->text(PdfLayoutService::MARGIN_LEFT, $y, 11, PdfLayoutService::FONT_BOLD, 'Cordiali saluti.');
        $y -= 22;
        $content .= $this->layout->text(PdfLayoutService::MARGIN_LEFT, $y, 11, PdfLayoutService::FONT_REGULAR, 'RSU');
        $pages[] = $this->page($content, $protocolNumber, $createdAt, $documentNumber, $verifyUrl);
        if ($signature !== null && $signature !== '') {
            $pages[] = $this->verificationPage($protocolNumber, $createdAt, $documentNumber, $verifyUrl, $signature);
        }
        $this->writer->write($targetPath, $pages);
    }

    public function textOriginal(string $title, string $body, string $protocolNumber, string $createdAt): string
    {
        return "Titolo: {$title}\nProtocollo: {$protocolNumber}\nData e ora: {$createdAt}\n\n{$body}";
    }

    private function page(string $content, string $protocolNumber, string $createdAt, ?string $documentNumber, ?string $verifyUrl): array
    {
        $page = $this->layout->page($content, [
            'number' => $documentNumber ?? '-',
            'protocol' => $protocolNumber,
            'date' => $createdAt,
            'verify_text' => $verifyUrl ? 'Verifica autenticita copia digitale' : '-',
        ]);

        if ($verifyUrl !== null && $verifyUrl !== '') {
            $page['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
            $page['links'][] = ['rect' => [260, 723, 430, 738], 'url' => $verifyUrl];
            $page['links'][] = ['rect' => [502, 728, 554, 780], 'url' => $verifyUrl];
        }

        return $page;
    }

    private function verificationPage(string $protocolNumber, string $createdAt, ?string $documentNumber, ?string $verifyUrl, string $signature): array
    {
        $content = $this->layout->text(42, PdfLayoutService::BODY_TOP, 18, PdfLayoutService::FONT_BOLD, 'Verifica documento');
        $verification = $this->layout->verificationBlock(PdfLayoutService::BODY_TOP - 48, $signature, $verifyUrl);
        $page = $this->page($content . $verification['content'], $protocolNumber, $createdAt, $documentNumber, $verifyUrl);
        $page['links'] = array_merge($page['links'], $verification['links']);

        return $page;
    }

    private function title(string $title, float $y): string
    {
        return $this->layout->text(PdfLayoutService::MARGIN_LEFT, $y, 18, PdfLayoutService::FONT_BOLD, $title);
    }

    private function lines(string $body): array
    {
        $lines = [];
        foreach (preg_split("/\R/", trim($body)) ?: [] as $paragraph) {
            if (trim($paragraph) === '') {
                $lines[] = '';
                continue;
            }
            foreach (explode("\n", wordwrap($paragraph, 88, "\n", true)) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
