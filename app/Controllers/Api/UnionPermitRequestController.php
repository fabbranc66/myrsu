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
        $userId = in_array('admin', $roles, true) ? null : (int)$user['id'];

        return Response::json(['data' => $this->app->unionPermits->allocations($userId)]);
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
        $userId = in_array('admin', $roles, true) ? null : (int)$user['id'];

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

        $this->app->activityLogs->write((int)$actor['id'], 'union_permits.request_issue', [
            'section' => 'permessi sindacali',
            'where' => 'documento',
            'permit_request_id' => $permit['id'],
            'document_id' => $document['id'],
            'hours' => $permit['hours'],
            'permit_type' => $permit['permit_type'],
        ]);

        return Response::json(['data' => $permit + ['document' => $document]], 201);
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
        $signature = $this->app->documentSignature->sign($document);
        $document = $this->app->documents->updateSignature((int)$document['id'], $signature);
        $verifyUrl = $this->baseUrl() . '/ui/document-verify.html?id=' . (int)$document['id'] . '&sig=' . urlencode($signature);
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $this->app->unionPermitPdf->write($pdfPath, $permit, $delegate, $verifyUrl, $signature);
        $document = $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
        $this->app->documentStorage->uploadPdfToHosting($document);
        return $document;
    }

    private function validatedIssue(array $data, array $actor, array $roles): array
    {
        Validator::required($data, ['permit_type', 'union_name', 'company_recipient', 'subject', 'request_date', 'start_at', 'end_at', 'hours']);
        $type = $this->permitType((string)$data['permit_type']);
        if ($type === 'rls' && !array_intersect($roles, ['admin', 'rls'])) {
            throw new HttpException(403, 'Solo RLS/admin possono usare ore RLS sicurezza.');
        }
        $userId = in_array('admin', $roles, true) && isset($data['user_id']) && $data['user_id'] !== '' ? (int)$data['user_id'] : (int)$actor['id'];
        $start = str_replace('T', ' ', trim((string)$data['start_at']));
        $end = str_replace('T', ' ', trim((string)$data['end_at']));
        if (strtotime($start) === false || strtotime($end) === false || strtotime($end) <= strtotime($start)) {
            throw new HttpException(422, 'Periodo non valido.');
        }

        return [
            'user_id' => $userId,
            'permit_type' => $type,
            'union_name' => trim((string)$data['union_name']),
            'company_recipient' => trim((string)$data['company_recipient']),
            'subject' => trim((string)$data['subject']),
            'request_date' => trim((string)$data['request_date']),
            'start_at' => $start,
            'end_at' => $end,
            'hours' => $this->positiveHours($data['hours']),
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'created_by' => (int)$actor['id'],
        ];
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
