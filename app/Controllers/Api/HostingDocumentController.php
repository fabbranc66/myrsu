<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;

final class HostingDocumentController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function store(Request $request): Response
    {
        $this->app->hostingDocumentReceive->assertToken($request->bearerToken());

        $result = $this->app->hostingDocumentReceive->receive(
            $_FILES['file'] ?? [],
            (string)($_POST['category'] ?? ''),
            (string)($_POST['checksum_sha256'] ?? ''),
            json_decode((string)($_POST['metadata_json'] ?? '[]'), true) ?: []
        );

        return Response::json(['data' => $result], 201);
    }
}
