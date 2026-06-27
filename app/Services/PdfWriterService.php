<?php

declare(strict_types=1);

namespace App\Services;

final class PdfWriterService
{
    public function write(string $targetPath, array $pages): void
    {
        $objects = ["1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"];
        $pageRefs = [];
        $next = 3;
        $fontRegular = $next++;
        $fontBold = $next++;
        $extState = $next++;

        foreach ($pages as $page) {
            $pageObject = $next++;
            $contentObject = $next++;
            $imageRefs = [];
            $annotRefs = [];
            foreach (($page['images'] ?? []) as $image) {
                $imageObject = $next++;
                $imageRefs[(string)$image['name']] = $imageObject;
                $objects[] = $this->imageObject($imageObject, $image);
                [$x, $y, $width, $height] = $image['rect'];
                $page['content'] = sprintf(
                    "q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
                    $width,
                    $height,
                    $x,
                    $y,
                    (string)$image['name']
                ) . (string)($page['content'] ?? '');
            }
            foreach (($page['links'] ?? []) as $link) {
                $annotObject = $next++;
                $annotRefs[] = $annotObject . ' 0 R';
                $objects[] = $this->linkObject($annotObject, $link);
            }
            $pageRefs[] = $pageObject . ' 0 R';
            $content = (string)($page['content'] ?? '');
            $objects[] = "{$contentObject} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream\nendobj\n";
            $xobjects = '';
            foreach ($imageRefs as $name => $objectId) {
                $xobjects .= '/' . $name . ' ' . $objectId . ' 0 R ';
            }
            $annots = $annotRefs === [] ? '' : ' /Annots [' . implode(' ', $annotRefs) . ']';
            $objects[] = "{$pageObject} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 "
                . PdfLayoutService::PAGE_WIDTH . ' ' . PdfLayoutService::PAGE_HEIGHT
                . "]{$annots} /Resources << /XObject << {$xobjects} >> /Font << /F1 {$fontRegular} 0 R /F2 {$fontBold} 0 R >> /ExtGState << /GS1 {$extState} 0 R >> >> /Contents {$contentObject} 0 R >>\nendobj\n";
        }

        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $pageRefs) . '] /Count ' . count($pageRefs) . " >>\nendobj\n";
        $objects[] = "{$fontRegular} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $objects[] = "{$fontBold} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
        $objects[] = "{$extState} 0 obj\n<< /Type /ExtGState /ca 0.45 /CA 0.45 >>\nendobj\n";
        $this->writeObjects($objects, $targetPath);
    }

    private function imageObject(int $id, array $image): string
    {
        return "{$id} 0 obj\n<< /Type /XObject /Subtype /Image /Width {$image['width']} /Height {$image['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length "
            . strlen($image['data']) . " >>\nstream\n{$image['data']}\nendstream\nendobj\n";
    }

    private function linkObject(int $id, array $link): string
    {
        [$x1, $y1, $x2, $y2] = $link['rect'];
        $url = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string)$link['url']);

        return "{$id} 0 obj\n<< /Type /Annot /Subtype /Link /Rect [{$x1} {$y1} {$x2} {$y2}] /Border [0 0 0] /A << /S /URI /URI ({$url}) >> >>\nendobj\n";
    }

    private function writeObjects(array $objects, string $targetPath): void
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $object) {
            preg_match('/^(\d+) 0 obj/', $object, $match);
            $offsets[(int)$match[1]] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $size = max(array_keys($offsets)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($index = 1; $index < $size; $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index] ?? 0);
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        file_put_contents($targetPath, $pdf);
    }
}
