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
        ?string $signature = null,
        ?string $revisionAt = null,
        ?string $creator = null
    ): void {
        $documentNumber = pathinfo($targetPath, PATHINFO_FILENAME);
        $pages = [];
        $y = PdfLayoutService::BODY_TOP;
        $content = '';
        foreach (explode("\n", wordwrap($title, 42, "\n", true)) as $titleLine) {
            $content .= $this->layout->text(PdfLayoutService::MARGIN_LEFT, $y, 18, PdfLayoutService::FONT_BOLD, $titleLine);
            $y -= 23;
        }
        $y -= 20;

        foreach ($this->lines($this->withoutClosing($body)) as $line) {
            if ($y < 92) {
                $pages[] = $this->page($content, $protocolNumber, $createdAt, $documentNumber, count($pages) + 1, $verifyUrl, $revisionAt, $creator);
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
        $pages[] = $this->page($content, $protocolNumber, $createdAt, $documentNumber, count($pages) + 1, $verifyUrl, $revisionAt, $creator);
        if ($signature !== null && $signature !== '') {
            $pages[] = $this->verificationPage($protocolNumber, $createdAt, $documentNumber, count($pages) + 1, $verifyUrl, $signature, $revisionAt, $creator);
        }
        $this->writer->write($targetPath, $pages);
    }

    public function textOriginal(string $title, string $body, string $protocolNumber, string $createdAt): string
    {
        return "Titolo: {$title}\nProtocollo: {$protocolNumber}\nData e ora: {$createdAt}\n\n{$body}";
    }

    public function draftOriginal(string $title, string $body): string
    {
        return "Titolo: {$title}\n\n{$body}";
    }

    private function page(string $content, string $protocolNumber, string $createdAt, string $documentNumber, int $pageNumber, ?string $verifyUrl, ?string $revisionAt, ?string $creator): array
    {
        $page = $this->layout->page($content, [
            'number' => $documentNumber . ' / ' . $pageNumber,
            'protocol' => $protocolNumber,
            'date' => $createdAt,
            'creator' => $creator,
            'revision' => $revisionAt,
            'verify_text' => $verifyUrl ? 'Verifica autenticita copia digitale' : '-',
        ]);

        if ($verifyUrl !== null && $verifyUrl !== '') {
            $page['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
            $page['links'][] = ['rect' => PdfLayoutService::VERIFY_LINK_RECT, 'url' => $verifyUrl];
            $page['links'][] = ['rect' => [502, 728, 554, 780], 'url' => $verifyUrl];
        }

        return $page;
    }

    private function verificationPage(string $protocolNumber, string $createdAt, string $documentNumber, int $pageNumber, ?string $verifyUrl, string $signature, ?string $revisionAt, ?string $creator): array
    {
        $content = $this->layout->text(42, PdfLayoutService::BODY_TOP, 18, PdfLayoutService::FONT_BOLD, 'Verifica documento');
        $verification = $this->layout->verificationBlock(PdfLayoutService::BODY_TOP - 48, $signature, $verifyUrl);
        $page = $this->page($content . $verification['content'], $protocolNumber, $createdAt, $documentNumber, $pageNumber, $verifyUrl, $revisionAt, $creator);
        $page['links'] = array_merge($page['links'], $verification['links']);

        return $page;
    }

    private function withoutClosing(string $body): string
    {
        return trim((string)preg_replace(
            '/\R*Cordiali saluti\.\R+RSU(?:\s+Canegrate)?\s*$/i',
            '',
            trim($body)
        ));
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
