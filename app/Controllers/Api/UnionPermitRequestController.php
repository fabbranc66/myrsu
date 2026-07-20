<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class UnionPermitRequestController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function allocations(Request $request): Response
    {
        $user = $this->requireOperator($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        $userId = $this->visibleUserId($request, $user, $roles);

        return Response::json(['data' => $this->app->unionPermits->allocations($userId)]);
    }

    public function delegates(Request $request): Response
    {
        $user = $this->requireOperator($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        $delegates = $this->app->users->unionDelegates();
        if (!in_array('admin', $roles, true)) {
            $delegates = array_values(array_filter($delegates, fn (array $delegate): bool => (int)$delegate['id'] === (int)$user['id']));
        }

        return Response::json(['data' => $delegates]);
    }

    public function saveAllocation(Request $request): Response
    {
        $admin = $this->requireAdmin($request);
        $data = $request->all();
        Validator::required($data, ['user_id', 'year', 'permit_type', 'annual_hours']);
        $type = $this->permitType((string)$data['permit_type']);
        $hours = $this->positiveHours($data['annual_hours']);
        $target = $this->app->users->findById((int)$data['user_id']);
        if ($target === null) {
            throw new HttpException(404, 'Utente non trovato.');
        }
        $targetRoles = $this->app->roles->rolesForUser((int)$target['id']);
        if ($type === 'rls' && !in_array('rls', $targetRoles, true)) {
            throw new HttpException(422, 'Ore RLS assegnabili solo a utenti RLS.');
        }
        if ($type === 'rsu' && !array_intersect($targetRoles, ['admin', 'delegato', 'rls'])) {
            throw new HttpException(422, 'Ore RSU assegnabili solo a RSU/delegati/RLS.');
        }

        $allocation = $this->app->unionPermits->upsertAllocation([
            'user_id' => (int)$target['id'],
            'year' => (int)$data['year'],
            'permit_type' => $type,
            'annual_hours' => $hours,
            'created_by' => (int)$admin['id'],
        ]);
        $extraAllocation = null;
        if ($type === 'rsu' && in_array('rls', $targetRoles, true) && isset($data['rls_hours']) && (float)$data['rls_hours'] > 0) {
            $extraAllocation = $this->app->unionPermits->upsertAllocation([
                'user_id' => (int)$target['id'],
                'year' => (int)$data['year'],
                'permit_type' => 'rls',
                'annual_hours' => $this->positiveHours($data['rls_hours']),
                'created_by' => (int)$admin['id'],
            ]);
        }

        $this->app->activityLogs->write((int)$admin['id'], 'union_permits.allocation_save', [
            'section' => 'permessi sindacali',
            'where' => 'utente',
            'user_id' => $target['id'],
            'permit_type' => $type,
            'annual_hours' => $hours,
            'rls_extra_hours' => $extraAllocation['annual_hours'] ?? null,
        ]);

        return Response::json(['data' => [
            'allocation' => $allocation,
            'extra_allocation' => $extraAllocation,
        ]]);
    }

    public function requests(Request $request): Response
    {
        $user = $this->requireOperator($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        $userId = $this->visibleUserId($request, $user, $roles);

        return Response::json(['data' => $this->app->unionPermits->requests($userId)]);
    }

    public function issue(Request $request): Response
    {
        $actor = $this->requireOperator($request);
        $roles = $this->app->roles->rolesForUser((int)$actor['id']);
        $data = $this->validatedIssue($request->all(), $actor, $roles);
        $allocation = $this->app->unionPermits->findAllocation($data['user_id'], (int)date('Y', strtotime($data['request_date'])), $data['permit_type']);
        if ($allocation === null) {
            throw new HttpException(422, 'Monte ore non assegnato.');
        }

        $permit = $this->app->unionPermits->createRequest($data + ['allocation_id' => (int)$allocation['id']]);
        if ($permit === []) {
            throw new HttpException(422, 'Monte ore insufficiente.');
        }

        $delegate = $this->app->users->findById((int)$permit['user_id']) ?? $actor;
        $permit['creator_name'] = (string)$actor['name'];
        try {
            $document = $this->createDocument($permit, $delegate);
        } catch (\Throwable $exception) {
            $this->app->unionPermits->deleteRequestAndRestore($permit);
            throw $exception;
        }
        $permit = $this->app->unionPermits->attachDocument((int)$permit['id'], (int)$document['id']);
        $protocol = $this->app->protocols->findActiveByDocumentId((int)$document['id']);

        $this->app->activityLogs->write((int)$actor['id'], 'union_permits.request_issue', [
            'section' => 'permessi sindacali',
            'where' => 'documento',
            'permit_request_id' => $permit['id'],
            'document_id' => $document['id'],
            'protocol_number' => $protocol['protocol_number'] ?? null,
            'hours' => $permit['hours'],
            'permit_type' => $permit['permit_type'],
        ]);

        return Response::json(['data' => $permit + ['document' => $document]], 201);
    }

    public function update(Request $request, array $params): Response
    {
        $actor = $this->requireOperator($request);
        $roles = $this->app->roles->rolesForUser((int)$actor['id']);
        $current = $this->findPermit((int)$params['id']);
        if ((string)$current['status'] === 'canceled') {
            throw new HttpException(422, 'Permesso annullato: modifica non consentita.');
        }
        if ((int)$current['user_id'] !== (int)$actor['id'] && !in_array('admin', $roles, true)) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

        $data = $this->validatedIssue($request->all(), $actor, $roles, (int)$current['user_id']);
        $allocation = $this->app->unionPermits->findAllocation($data['user_id'], (int)date('Y', strtotime($data['request_date'])), $data['permit_type']);
        if ($allocation === null) {
            throw new HttpException(422, 'Monte ore non assegnato.');
        }
        $permit = $this->app->unionPermits->updateRequest($current, $data + ['allocation_id' => (int)$allocation['id']]);
        if ($permit === []) {
            throw new HttpException(422, 'Monte ore insufficiente.');
        }

        $document = $this->regenerateDocument($permit, $actor);
        $protocol = $this->app->protocols->findActiveByDocumentId((int)$document['id']);
        $this->app->activityLogs->write((int)$actor['id'], 'union_permits.request_update', [
            'section' => 'permessi sindacali',
            'where' => 'documento',
            'permit_request_id' => $permit['id'],
            'document_id' => $document['id'],
            'protocol_number' => $protocol['protocol_number'] ?? null,
            'hours' => $permit['hours'],
            'permit_type' => $permit['permit_type'],
        ]);

        return Response::json(['data' => $permit + ['document' => $document]]);
    }

    public function destroy(Request $request, array $params): Response
    {
        $actor = $this->requireOperator($request);
        $roles = $this->app->roles->rolesForUser((int)$actor['id']);
        $permit = $this->findPermit((int)$params['id']);
        if ((int)$permit['user_id'] !== (int)$actor['id'] && !in_array('admin', $roles, true)) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }
        if ((string)$permit['status'] === 'canceled') {
            throw new HttpException(422, 'Permesso gia annullato.');
        }

        $restoreHours = strtotime((string)$permit['start_at']) < time();
        $canceled = $this->app->unionPermits->cancelRequest($permit, (int)$actor['id'], $restoreHours);
        $protocol = null;
        if ((int)($permit['document_id'] ?? 0) > 0) {
            $protocol = $this->app->protocols->findActiveByDocumentId((int)$permit['document_id']);
            if ($protocol !== null) {
                $protocol = $this->app->protocols->cancel((int)$protocol['id'], (int)$actor['id']);
            }
        }

        $this->app->activityLogs->write((int)$actor['id'], 'union_permits.request_cancel', [
            'section' => 'permessi sindacali',
            'where' => 'documento',
            'permit_request_id' => $permit['id'],
            'document_id' => $permit['document_id'],
            'protocol_number' => $protocol['protocol_number'] ?? null,
            'hours' => $permit['hours'],
            'hours_restored' => $restoreHours,
            'permit_start_at' => $permit['start_at'],
        ]);

        return Response::json(['data' => [
            'request' => $canceled,
            'hours_restored' => $restoreHours,
            'protocol' => $protocol,
        ]]);
    }

    private function createDocument(array $permit, array $delegate): array
    {
        $original = $this->app->unionPermitPdf->original($permit, $delegate);
        $stored = $this->app->documentStorage->storeGeneratedPdf(
            $original,
            'permesso-sindacale-' . $permit['id'] . '.html',
            'permessi',
            fn (string $pdfPath) => $this->app->unionPermitPdf->write($pdfPath, $permit, $delegate)
        );
        $document = $this->app->documents->create($stored + [
            'visibility' => 'rsu',
            'uploaded_by' => (int)$permit['created_by'],
        ]);
        $protocol = $this->app->protocols->create('OUT', 'PRM', (string)$permit['subject'], (int)$permit['created_by']);
        $protocol = $this->app->protocols->update((int)$protocol['id'], (string)$permit['subject'], (int)$document['id']);
        $publicPath = $this->app->protocolDocumentName->publicPath('permessi', (string)$protocol['protocol_number']);
        $this->app->protocolDocumentName->move(
            $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']),
            $this->app->documentStorage->pdfPath($publicPath)
        );
        $document = $this->app->documents->updatePublicPath((int)$document['id'], $publicPath);
        $permit['protocol_number'] = (string)$protocol['protocol_number'];
        $permit['protocol_created_at'] = (string)$protocol['created_at'];
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        if ((string)$permit['request_scope'] === 'internal') {
            $signature = $this->app->documentSignature->sign($document);
            $document = $this->app->documents->updateSignature((int)$document['id'], $signature);
            $verifyUrl = $this->baseUrl() . '/ui/document-verify.html?id=' . (int)$document['id'] . '&sig=' . urlencode($signature);
            $this->app->unionPermitPdf->write($pdfPath, $permit, $delegate, $verifyUrl, $signature);
        } else {
            $document = $this->app->documents->clearSignature((int)$document['id']);
            $permit['union_logo_image'] = $this->unionLogoImage($delegate);
            $this->app->unionPermitPdf->write($pdfPath, $permit, $delegate);
        }
        $document = $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
        try {
            $this->app->documentStorage->uploadPdfToHosting($document);
        } catch (\Throwable $exception) {
            error_log('MyRSU permit hosting upload failed: ' . $exception->getMessage());
        }
        return $document;
    }

    private function regenerateDocument(array $permit, array $actor): array
    {
        $document = $this->app->documents->findById((int)$permit['document_id']);
        if ($document === null) {
            throw new HttpException(404, 'Documento non trovato.');
        }
        $delegate = $this->app->users->findById((int)$permit['user_id']) ?? $actor;
        $protocol = $this->app->protocols->findActiveByDocumentId((int)$document['id']);
        if ($protocol === null) {
            throw new HttpException(422, 'Protocollo permesso non trovato.');
        }
        $protocol = $this->app->protocols->update((int)$protocol['id'], (string)$permit['subject'], (int)$document['id']);
        $permit['creator_name'] = (string)$actor['name'];
        $permit['protocol_number'] = (string)$protocol['protocol_number'];
        $permit['protocol_created_at'] = (string)$protocol['created_at'];
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        if ((string)$permit['request_scope'] === 'internal') {
            $signature = $this->app->documentSignature->sign($document);
            $document = $this->app->documents->updateSignature((int)$document['id'], $signature);
            $verifyUrl = $this->baseUrl() . '/ui/document-verify.html?id=' . (int)$document['id'] . '&sig=' . urlencode($signature);
            $this->app->unionPermitPdf->write($pdfPath, $permit, $delegate, $verifyUrl, $signature);
        } else {
            $document = $this->app->documents->clearSignature((int)$document['id']);
            $permit['union_logo_image'] = $this->unionLogoImage($delegate);
            $this->app->unionPermitPdf->write($pdfPath, $permit, $delegate);
        }
        return $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
    }

    private function validatedIssue(array $data, array $actor, array $roles, ?int $forcedUserId = null): array
    {
        Validator::required($data, ['request_scope', 'permit_type', 'union_name', 'subject', 'request_date', 'start_at', 'end_at', 'hours']);
        $scope = (string)$data['request_scope'];
        if (!in_array($scope, ['internal', 'external'], true)) {
            throw new HttpException(422, 'Ambito permesso non valido.');
        }
        $type = $this->permitType((string)$data['permit_type']);
        if ($type === 'rls' && !array_intersect($roles, ['admin', 'rls'])) {
            throw new HttpException(403, 'Solo RLS/admin possono usare ore RLS sicurezza.');
        }
        $userId = $forcedUserId ?? (in_array('admin', $roles, true) && isset($data['user_id']) && $data['user_id'] !== '' ? (int)$data['user_id'] : (int)$actor['id']);
        if ($userId !== (int)$actor['id'] && !in_array('admin', $roles, true)) {
            throw new HttpException(403, 'Delegato non consentito.');
        }
        if ($this->app->users->findById($userId) === null) {
            throw new HttpException(404, 'Delegato non trovato.');
        }
        $start = str_replace('T', ' ', trim((string)$data['start_at']));
        $end = str_replace('T', ' ', trim((string)$data['end_at']));
        if (strtotime($start) === false || strtotime($end) === false || strtotime($end) <= strtotime($start)) {
            throw new HttpException(422, 'Periodo non valido.');
        }

        return [
            'user_id' => $userId,
            'request_scope' => $scope,
            'permit_type' => $type,
            'union_name' => trim((string)$data['union_name']),
            'company_recipient' => trim((string)($data['company_recipient'] ?? 'Azienda')),
            'subject' => trim((string)$data['subject']),
            'request_date' => trim((string)$data['request_date']),
            'start_at' => $start,
            'end_at' => $end,
            'hours' => $this->positiveHours($data['hours']),
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'created_by' => (int)$actor['id'],
        ];
    }

    private function unionLogoImage(array $delegate): ?array
    {
        $storedName = trim((string)($delegate['union_logo_stored_name'] ?? ''));
        return $storedName !== '' ? $this->app->unionLogoStorage->image($storedName, 'UnionLogo', 42, 726, 150, 70) : null;
    }

    private function findPermit(int $id): array
    {
        $permit = $this->app->unionPermits->findRequest($id);
        if ($permit === null) {
            throw new HttpException(404, 'Permesso non trovato.');
        }

        return $permit;
    }

    private function requireAdmin(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        if (!in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true)) {
            throw new HttpException(403, 'Solo admin.');
        }
        return $user;
    }

    private function requireOperator(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        if (!array_intersect($this->app->roles->rolesForUser((int)$user['id']), ['admin', 'delegato', 'rls'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }
        return $user;
    }

    private function visibleUserId(Request $request, array $user, array $roles): ?int
    {
        $requested = $request->query('user_id');
        if (in_array('admin', $roles, true)) {
            return $requested !== null && $requested !== '' ? (int)$requested : null;
        }

        return (int)$user['id'];
    }

    private function permitType(string $type): string
    {
        if (!in_array($type, ['rsu', 'rls'], true)) {
            throw new HttpException(422, 'Tipo permesso non valido.');
        }
        return $type;
    }

    private function positiveHours(mixed $value): float
    {
        $hours = round((float)$value, 2);
        if ($hours <= 0) {
            throw new HttpException(422, 'Ore non valide.');
        }
        return $hours;
    }

    private function baseUrl(): string
    {
        return rtrim((string)env_value('APP_URL', 'http://localhost/myrsu'), '/');
    }
}
