<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\FileResponse;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ArchiveSharingService;

final class ArchiveSharingController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function catalogue(Request $request): Response
    {
        $this->admin($request);
        $service = $this->service();
        $this->installed($service);
        $type = $this->type($request->query('type', 'practice'));
        $query = $request->query('q', '');
        if (!is_string($query) || mb_strlen($query) > 150) throw new HttpException(422, 'Ricerca non valida, massimo 150 caratteri.');
        $source = $request->query('source', '0');
        if (!is_string($source) || !ctype_digit($source)) throw new HttpException(422, 'Origine non valida.');
        $page = $this->id($request->query('page', '1'));
        return Response::json(['data' => $service->catalogue($type, trim($query), (int)$source, $page)]);
    }

    public function set(Request $request): Response
    {
        $user = $this->admin($request);
        $service = $this->service();
        $this->installed($service);
        $enabled = $request->input('enabled');
        if (!is_bool($enabled)) throw new HttpException(422, 'Flag di condivisione non valido.');
        $source = $this->id($request->input('source_configuration_id'));
        $type = $this->type($request->input('entity_type'));
        $id = $this->id($request->input('entity_id'));
        $service->set($source, $type, $id, $enabled, (int)$user['id']);
        $this->app->activityLogs->write((int)$user['id'], $enabled ? 'archive.share' : 'archive.unshare', ['source_configuration_id' => $source, 'target_configuration_id' => $this->app->configurationContext->current['id'], 'entity_type' => $type, 'entity_id' => $id]);
        return Response::json(['data' => ['enabled' => $enabled]]);
    }

    public function shared(Request $request): Response
    {
        $type = $this->type($request->query('type', 'practice'));
        $user = $this->reader($request, $type);
        return Response::json(['data' => ['items' => $this->service()->shared($type, $user)]]);
    }

    public function protocols(Request $request): Response
    {
        $user = $this->app->auth->requirePermission($request, 'protocol.view');
        return Response::json(['data' => ['items' => $this->service()->sharedProtocols($user)]]);
    }

    public function item(Request $request, array $params): Response
    {
        $type = $this->type($params['type']);
        $user = $this->reader($request, $type);
        return Response::json(['data' => $this->service()->item($this->id($params['source']), $type, $this->id($params['id']), $user)]);
    }

    public function file(Request $request, array $params): FileResponse
    {
        $user = $this->reader($request, 'document');
        $source = $this->id($params['source']);
        $id = $this->id($params['id']);
        $service = $this->service();
        $document = $service->document($source, $id, $user);
        if ($document['conversion_status'] !== 'ready') throw new HttpException(409, 'Documento originale ancora in elaborazione.');
        $path = $service->source($source)->pdfPath($document);
        $this->app->activityLogs->write((int)$user['id'], 'archive.file', ['source_configuration_id' => $source, 'document_id' => $id]);
        header('Cache-Control: private, no-store');
        return new FileResponse($path, basename($path), 'application/pdf', $request->query('download') !== '1');
    }

    public function verify(Request $request, array $params): Response
    {
        $user = $this->reader($request, 'document');
        $source = $this->id($params['source']);
        $id = $this->id($params['id']);
        $service = $this->service();
        $document = $service->document($source, $id, $user);
        $checksum = hash_file('sha256', $service->source($source)->pdfPath($document));
        $checksumOk = hash_equals((string)$document['pdf_checksum_sha256'], $checksum);
        $signature = $request->query('sig', '');
        if (!is_string($signature)) throw new HttpException(422, 'Firma non valida.');
        $signature = strtoupper(trim($signature));
        $valid = $checksumOk && $signature !== '' && hash_equals((string)$document['signature'], $signature);
        return Response::json(['data' => ['valid' => $valid, 'checksum_ok' => $checksumOk, 'source_configuration_id' => $source, 'document_id' => $id]]);
    }

    public function verifyFile(Request $request, array $params): Response
    {
        $user = $this->reader($request, 'document');
        $source = $this->id($params['source']);
        $id = $this->id($params['id']);
        $document = $this->service()->document($source, $id, $user);
        $signature = $request->input('sig', '');
        if (!is_string($signature)) throw new HttpException(422, 'Firma non valida.');
        $file = $_FILES['file'] ?? [];
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new HttpException(400, 'File non valido.');
        $checksum = hash_file('sha256', $file['tmp_name']);
        $checksumOk = $checksum !== false && hash_equals((string)$document['pdf_checksum_sha256'], $checksum);
        $signature = strtoupper(trim($signature));
        $valid = $checksumOk && $signature !== '' && hash_equals((string)$document['signature'], $signature);
        return Response::json(['data' => ['valid' => $valid, 'checksum_ok' => $checksumOk, 'source_configuration_id' => $source, 'document_id' => $id, 'mode' => 'uploaded_file']]);
    }

    public function attachment(Request $request, array $params): FileResponse
    {
        $user = $this->reader($request, 'practice');
        $sourceId = $this->id($params['source']);
        $id = $this->id($params['id']);
        $service = $this->service();
        $allowed = false;
        foreach ($service->dossier($user) as $item) {
            if ($item['archive_source_configuration_id'] !== $sourceId) continue;
            foreach ($item['archive_attachments'] as $attachment) if ((int)$attachment['id'] === $id) $allowed = true;
        }
        if (!$allowed) throw new HttpException(403, 'Allegato non collegato a una pratica disponibile.');
        $source = $service->source($sourceId);
        $statement = $source->pdo->prepare('SELECT * FROM report_attachments WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();
        if (!$row) throw new HttpException(404, 'Allegato originale non disponibile.');
        $base = realpath($source->root . '/storage/private/report-attachments');
        $path = realpath($source->root . '/storage/private/report-attachments/' . basename($row['stored_name']));
        if (!$base || !$path || !is_file($path) || !str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $base) . '/')) throw new HttpException(404, 'File allegato originale non disponibile.');
        header('Cache-Control: private, no-store');
        return new FileResponse($path, $row['original_name'], 'application/octet-stream');
    }

    private function reader(Request $request, string $type): array
    {
        $user = $this->app->auth->requireUser($request);
        if ($type === 'document') return $user;
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        if (!array_intersect($roles, ['admin', 'delegato', 'rls'])) throw new HttpException(403, 'Permesso insufficiente.');
        return $user;
    }

    private function admin(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        if (!in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true)) throw new HttpException(403, 'Condivisioni riservate agli amministratori.');
        return $user;
    }

    private function service(): ArchiveSharingService
    {
        return new ArchiveSharingService($this->app);
    }

    private function installed(ArchiveSharingService $service): void
    {
        if ($this->app->configurationContext->current === null || !$service->repository->installed()) throw new HttpException(409, 'Applicare prima la migration 20261024_archive_shares.sql.');
    }

    private function id(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || !ctype_digit((string)$value) || (int)$value <= 0) throw new HttpException(422, 'Identificativo archivio non valido.');
        return (int)$value;
    }

    private function type(mixed $value): string
    {
        if (!in_array($value, ['practice', 'document'], true)) throw new HttpException(422, 'Tipo contenuto non valido.');
        return $value;
    }
}
