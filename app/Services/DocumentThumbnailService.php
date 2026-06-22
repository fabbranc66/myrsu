<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use Imagick;
use Throwable;

final class DocumentThumbnailService
{
    private string $path;

    public function __construct(private readonly string $basePath)
    {
        $this->path = $this->basePath . '/public/documents/thumbnails';
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }
    }

    public function firstPage(array $document, string $pdfPath): string
    {
        if (!is_file($pdfPath)) {
            throw new HttpException(404, 'File non trovato.');
        }

        $thumbnailPath = $this->path . '/document-' . (int)$document['id'] . '.png';
        if (is_file($thumbnailPath) && filemtime($thumbnailPath) >= filemtime($pdfPath)) {
            return $thumbnailPath;
        }

        if (!class_exists(Imagick::class)) {
            throw new HttpException(500, 'Anteprima documento non disponibile.');
        }

        try {
            $image = new Imagick();
            $image->setResolution(120, 120);
            $image->readImage($pdfPath . '[0]');
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $image->setImageFormat('png');
            $image->thumbnailImage(520, 720, true);
            $image->writeImage($thumbnailPath);
            $image->clear();
            $image->destroy();
        } catch (Throwable) {
            throw new HttpException(500, 'Anteprima documento non disponibile.');
        }

        return $thumbnailPath;
    }
}
