<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class WorkersAssemblyController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->requireManager($request);

        return Response::json(['data' => array_map([$this, 'withSessions'], $this->app->workersAssemblies->all())]);
    }

    public function show(Request $request, array $params): Response
    {
        $this->requireManager($request);

        return Response::json(['data' => $this->withSessions($this->findAssembly((int)$params['id']))]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireManager($request);
        $data = $this->validated($request->all());
        $assembly = $this->app->workersAssemblies->create($data + ['created_by' => (int)$user['id']]);
        $this->app->workersAssemblyParticipants->replace((int)$assembly['id'], $data['selected_participants']);
        $this->app->workersAssemblySessions->replace((int)$assembly['id'], $data['sessions']);
        $this->app->activityLogs->write((int)$user['id'], 'assemblies.create', [
            'section' => 'assemblies',
            'assembly_id' => $assembly['id'],
            'practice_id' => $assembly['practice_id'],
        ]);

        return Response::json(['data' => $this->withSessions($assembly)], 201);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $assembly = $this->findAssembly((int)$params['id']);
        $data = $this->validated($request->all());
        $updated = $this->app->workersAssemblies->update((int)$assembly['id'], $data);
        $this->app->workersAssemblyParticipants->replace((int)$assembly['id'], $data['selected_participants']);
        $this->app->workersAssemblySessions->replace((int)$assembly['id'], $data['sessions']);
        $this->app->activityLogs->write((int)$user['id'], 'assemblies.update', [
            'section' => 'assemblies',
            'assembly_id' => $updated['id'],
            'practice_id' => $updated['practice_id'],
        ]);

        return Response::json(['data' => $this->withSessions($updated)]);
    }

    public function destroy(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $assembly = $this->findAssembly((int)$params['id']);
        $this->app->workersAssemblies->delete((int)$assembly['id']);
        $this->app->activityLogs->write((int)$user['id'], 'assemblies.delete', [
            'section' => 'assemblies',
            'assembly_id' => $assembly['id'],
        ]);

        return Response::json(['data' => ['deleted' => true]]);
    }

    public function storeDocument(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $assembly = $this->findAssembly((int)$params['id']);
        $file = $_FILES['file'] ?? [];
        $pendingOffice = $this->app->officeFiles->isOffice($file) && !$this->app->pdfConversion->available();
        $stored = $pendingOffice
            ? $this->app->documentStorage->storePendingUpload($file, 'documenti')
            : $this->app->documentStorage->store($file, 'documenti');
        $document = $this->app->documents->create($stored + ['visibility' => 'rsu', 'uploaded_by' => (int)$user['id']]);
        if (!$pendingOffice) {
            $document = $this->app->documents->updateSignature((int)$document['id'], $this->app->documentSignature->sign($document));
            $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
            $verifyUrl = $this->appBaseUrl() . '/ui/document-verify.html?id=' . (int)$document['id'] . '&sig=' . urlencode((string)$document['signature']);
            $this->app->uploadedDocumentPdf->write(
                $this->app->documentStorage->originalPath((string)$document['original_stored_name']),
                $pdfPath,
                $document + ['creator_name' => (string)$user['name']],
                null,
                $verifyUrl,
                (string)$document['signature']
            );
            $document = $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
        }
        $this->app->workersAssemblyDocuments->create((int)$assembly['id'], (int)$document['id'], (int)$user['id']);
        $this->app->activityLogs->write((int)$user['id'], $pendingOffice ? 'assemblies.document_pending' : 'assemblies.document_upload', [
            'section' => 'assemblies',
            'assembly_id' => $assembly['id'],
            'document_id' => $document['id'],
        ]);

        return Response::json(['data' => [
            'document' => $document,
            'documents' => $this->app->workersAssemblyDocuments->forAssembly((int)$assembly['id']),
        ]], $pendingOffice ? 202 : 201);
    }

    public function linkDocument(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $assembly = $this->findAssembly((int)$params['id']);
        $data = $request->all();
        Validator::required($data, ['document_id']);
        $document = $this->app->documents->findById((int)$data['document_id']);
        if ($document === null || (string)$document['category'] !== 'documenti') {
            throw new HttpException(404, 'Documento archivio non trovato.');
        }

        $this->app->workersAssemblyDocuments->create((int)$assembly['id'], (int)$document['id'], (int)$user['id']);
        $this->app->activityLogs->write((int)$user['id'], 'assemblies.document_link', [
            'section' => 'assemblies',
            'assembly_id' => $assembly['id'],
            'document_id' => $document['id'],
        ]);

        return Response::json(['data' => [
            'document' => $document,
            'documents' => $this->app->workersAssemblyDocuments->forAssembly((int)$assembly['id']),
        ]]);
    }

    public function destroyDocument(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $assembly = $this->findAssembly((int)$params['id']);
        $this->app->workersAssemblyDocuments->delete((int)$assembly['id'], (int)$params['document_id']);
        $this->app->activityLogs->write((int)$user['id'], 'assemblies.document_unlink', [
            'section' => 'assemblies',
            'assembly_id' => $assembly['id'],
            'document_id' => (int)$params['document_id'],
        ]);

        return Response::json(['data' => ['documents' => $this->app->workersAssemblyDocuments->forAssembly((int)$assembly['id'])]]);
    }

    public function publicConvocation(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $assembly = $this->withSessions($this->findAssembly((int)$params['id']));
        if ($assembly['public_document_id'] !== null) {
            return Response::json(['data' => [
                'assembly' => $assembly,
                'document' => $this->app->documents->findById((int)$assembly['public_document_id']),
            ]]);
        }

        $title = 'Convocazione assemblea lavoratori - ' . $assembly['title'];
        $body = $this->convocationBody($assembly);
        $protocol = $this->app->protocols->create('OUT', 'COM', $title, (int)$user['id']);
        $original = $this->app->comunicatoDirectPdf->textOriginal($title, $body, (string)$protocol['protocol_number'], (string)$protocol['created_at']);
        $stored = $this->app->documentStorage->storeGeneratedPdf(
            $original,
            'convocazione-assemblea.html',
            'comunicati',
            fn (string $pdfPath) => $this->app->comunicatoDirectPdf->write(
                $pdfPath,
                $title,
                $body,
                (string)$protocol['protocol_number'],
                (string)$protocol['created_at'],
                null,
                null,
                null,
                null,
                (string)$user['name']
            )
        );
        $officialPublicPath = $this->app->protocolDocumentName->publicPath('comunicati', (string)$protocol['protocol_number']);
        $this->app->protocolDocumentName->move(
            $this->app->documentStorage->pdfPath((string)$stored['pdf_public_path']),
            $this->app->documentStorage->pdfPath($officialPublicPath)
        );
        $stored['pdf_public_path'] = $officialPublicPath;
        $document = $this->app->documents->create($stored + ['visibility' => 'public', 'uploaded_by' => (int)$user['id']]);
        $protocol = $this->app->protocols->update((int)$protocol['id'], $title, (int)$document['id']);
        $document = $this->app->documents->updateSignature((int)$document['id'], $this->app->documentSignature->sign($document));
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $verifyUrl = $this->appBaseUrl() . '/ui/document-verify.html?id=' . (int)$document['id'] . '&sig=' . urlencode((string)$document['signature']);
        $this->app->comunicatoDirectPdf->write($pdfPath, $title, $body, (string)$protocol['protocol_number'], (string)$protocol['created_at'], null, $verifyUrl, (string)$document['signature'], null, (string)$user['name']);
        $document = $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
        $this->app->documentStorage->uploadPdfToHosting($document);
        $assembly = $this->app->workersAssemblies->attachPublicDocument((int)$assembly['id'], (int)$document['id']);
        $this->app->activityLogs->write((int)$user['id'], 'assemblies.public_convocation', [
            'section' => 'assemblies',
            'assembly_id' => $assembly['id'],
            'document_id' => $document['id'],
            'protocol_number' => $protocol['protocol_number'],
        ]);

        return Response::json(['data' => ['assembly' => $assembly, 'document' => $document, 'protocol' => $protocol]], 201);
    }

    public function minutes(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $assembly = $this->withSessions($this->findAssembly((int)$params['id']));
        if ($assembly['minutes_document_id'] !== null) {
            return Response::json(['data' => $this->regenerateMinutes($assembly, $user)]);
        }

        $title = 'Verbale assemblea lavoratori - ' . $assembly['title'];
        $body = $this->minutesBody($assembly);
        $protocol = $this->app->protocols->create('OUT', 'VER', $title, (int)$user['id']);
        $original = $this->app->comunicatoDirectPdf->textOriginal($title, $body, (string)$protocol['protocol_number'], (string)$protocol['created_at']);
        $stored = $this->app->documentStorage->storeGeneratedPdf(
            $original,
            'verbale-assemblea.html',
            'documenti',
            fn (string $pdfPath) => $this->app->comunicatoDirectPdf->write($pdfPath, $title, $body, (string)$protocol['protocol_number'], (string)$protocol['created_at'], null, null, null, null, (string)$user['name'])
        );
        $officialPublicPath = $this->app->protocolDocumentName->publicPath('documenti', (string)$protocol['protocol_number']);
        $this->app->protocolDocumentName->move($this->app->documentStorage->pdfPath((string)$stored['pdf_public_path']), $this->app->documentStorage->pdfPath($officialPublicPath));
        $stored['pdf_public_path'] = $officialPublicPath;
        $document = $this->app->documents->create($stored + ['visibility' => 'rsu', 'uploaded_by' => (int)$user['id']]);
        $protocol = $this->app->protocols->update((int)$protocol['id'], $title, (int)$document['id']);
        $document = $this->app->documents->updateSignature((int)$document['id'], $this->app->documentSignature->sign($document));
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $verifyUrl = $this->appBaseUrl() . '/ui/document-verify.html?id=' . (int)$document['id'] . '&sig=' . urlencode((string)$document['signature']);
        $this->app->comunicatoDirectPdf->write($pdfPath, $title, $body, (string)$protocol['protocol_number'], (string)$protocol['created_at'], null, $verifyUrl, (string)$document['signature'], null, (string)$user['name']);
        $document = $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
        $this->app->documentStorage->uploadPdfToHosting($document);
        $assembly = $this->app->workersAssemblies->attachMinutesDocument((int)$assembly['id'], (int)$document['id']);
        $this->app->activityLogs->write((int)$user['id'], 'assemblies.minutes_generate', [
            'section' => 'assemblies',
            'assembly_id' => $assembly['id'],
            'document_id' => $document['id'],
            'protocol_number' => $protocol['protocol_number'],
        ]);

        return Response::json(['data' => ['assembly' => $assembly, 'document' => $document, 'protocol' => $protocol]], 201);
    }

    private function regenerateMinutes(array $assembly, array $user): array
    {
        $document = $this->app->documents->findById((int)$assembly['minutes_document_id']);
        if ($document === null) {
            throw new HttpException(404, 'Verbale non trovato.');
        }
        $protocol = $this->app->protocols->findActiveByDocumentId((int)$document['id']);
        if ($protocol === null) {
            throw new HttpException(404, 'Protocollo verbale non trovato.');
        }

        $title = 'Verbale assemblea lavoratori - ' . $assembly['title'];
        $body = $this->minutesBody($assembly);
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $signature = $this->app->documentSignature->sign($document);
        $document = $this->app->documents->updateSignature((int)$document['id'], $signature);
        $verifyUrl = $this->appBaseUrl() . '/ui/document-verify.html?id=' . (int)$document['id'] . '&sig=' . urlencode((string)$signature);
        $this->app->comunicatoDirectPdf->write($pdfPath, $title, $body, (string)$protocol['protocol_number'], (string)$protocol['created_at'], null, $verifyUrl, (string)$signature, date('Y-m-d H:i:s'), (string)$user['name']);
        $document = $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
        $this->app->documentStorage->uploadPdfToHosting($document);
        $this->app->activityLogs->write((int)$user['id'], 'assemblies.minutes_regenerate', [
            'section' => 'assemblies',
            'assembly_id' => $assembly['id'],
            'document_id' => $document['id'],
            'protocol_number' => $protocol['protocol_number'],
        ]);

        return ['assembly' => $assembly, 'document' => $document, 'protocol' => $protocol];
    }

    public function storeSessionNote(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $assembly = $this->findAssembly((int)$params['id']);
        $session = $this->app->workersAssemblySessions->findForAssembly((int)$assembly['id'], (int)$params['session_id']);
        if ($session === null) {
            throw new HttpException(404, 'Turno assemblea non trovato.');
        }
        $data = $request->all();
        Validator::required($data, ['note_type', 'body']);
        $type = (string)$data['note_type'];
        if (!in_array($type, ['discussion', 'question', 'answer', 'proposal', 'decision', 'note'], true)) {
            throw new HttpException(422, 'Tipo contenuto non valido.');
        }
        $body = trim((string)$data['body']);
        if ($body === '') {
            throw new HttpException(422, 'Contenuto obbligatorio.');
        }

        $note = $this->app->workersAssemblySessionNotes->create((int)$session['id'], $type, $body, (int)$user['id']);
        $this->app->activityLogs->write((int)$user['id'], 'assemblies.session_note_create', [
            'section' => 'assemblies',
            'assembly_id' => $assembly['id'],
            'session_id' => $session['id'],
            'note_type' => $type,
        ]);

        return Response::json(['data' => $note], 201);
    }

    private function validated(array $data): array
    {
        Validator::required($data, ['title', 'agenda', 'sessions']);
        $status = (string)($data['status'] ?? 'draft');
        $visibility = (string)($data['visibility'] ?? 'members');
        if (!in_array($status, ['draft', 'called', 'done', 'cancelled'], true)) {
            throw new HttpException(422, 'Stato non valido.');
        }
        if (!in_array($visibility, ['public', 'members', 'rsu'], true)) {
            throw new HttpException(422, 'Visibilita non valida.');
        }

        return [
            'practice_id' => isset($data['practice_id']) && $data['practice_id'] !== '' ? (int)$data['practice_id'] : null,
            'title' => trim((string)$data['title']),
            'agenda' => trim((string)$data['agenda']),
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'final_statement' => trim((string)($data['final_statement'] ?? '')) ?: null,
            'status' => $status,
            'visibility' => $visibility,
            'voting_enabled' => !empty($data['voting_enabled']) ? 1 : 0,
            'voting_subject' => trim((string)($data['voting_subject'] ?? '')) ?: null,
            'selected_participants' => is_array($data['selected_participants'] ?? null) ? $data['selected_participants'] : [],
            'sessions' => $this->validatedSessions($data['sessions']),
        ];
    }

    private function validatedSessions(mixed $sessions): array
    {
        if (!is_array($sessions) || count($sessions) === 0) {
            throw new HttpException(422, 'Inserire almeno un turno assemblea.');
        }

        return array_map(function (array $session): array {
            Validator::required($session, ['shift_label', 'assembly_date', 'time_start']);
            $mode = (string)($session['mode'] ?? 'in_person');
            $status = (string)($session['status'] ?? 'scheduled');
            if (!in_array($mode, ['in_person', 'online', 'mixed'], true)) {
                throw new HttpException(422, 'Modalita turno non valida.');
            }
            if (!in_array($status, ['scheduled', 'done', 'cancelled'], true)) {
                throw new HttpException(422, 'Stato turno non valido.');
            }

            return [
                'shift_label' => trim((string)$session['shift_label']),
                'assembly_date' => trim((string)$session['assembly_date']),
                'time_start' => trim((string)$session['time_start']),
                'time_end' => trim((string)($session['time_end'] ?? '')) ?: null,
                'mode' => $mode,
                'place' => trim((string)($session['place'] ?? '')) ?: null,
                'status' => $status,
            ];
        }, $sessions);
    }

    private function requireManager(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        if (!array_intersect($roles, ['admin', 'delegato', 'rls'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

        return $user;
    }

    private function findAssembly(int $id): array
    {
        $assembly = $this->app->workersAssemblies->findById($id);
        if ($assembly === null) {
            throw new HttpException(404, 'Assemblea non trovata.');
        }

        return $assembly;
    }

    private function withSessions(array $assembly): array
    {
        $assembly['sessions'] = $this->app->workersAssemblySessions->forAssembly((int)$assembly['id']);
        $assembly['selected_participants'] = $this->app->workersAssemblyParticipants->forAssembly((int)$assembly['id']);
        $assembly['documents'] = $this->app->workersAssemblyDocuments->forAssembly((int)$assembly['id']);
        $assembly['notes'] = $this->app->workersAssemblySessionNotes->forAssembly((int)$assembly['id']);

        return $assembly;
    }

    private function convocationBody(array $assembly): string
    {
        $sessions = implode("\n", array_map(
            static fn (array $session): string => sprintf(
                '- %s | %s | %s-%s | %s',
                (string)$session['shift_label'],
                (string)$session['assembly_date'],
                substr((string)$session['time_start'], 0, 5),
                substr((string)($session['time_end'] ?? ''), 0, 5) ?: '-',
                (string)($session['place'] ?? '-')
            ),
            $assembly['sessions'] ?? []
        ));
        $participants = implode("\n", array_map(
            static fn (array $participant): string => '- ' . (string)$participant['label'],
            $assembly['selected_participants'] ?? []
        ));

        return "Oggetto:\n{$assembly['title']}\n\nTurni assemblea:\n{$sessions}\n\nPartecipanti/convocati:\n{$participants}\n\nOrdine del giorno:\n{$assembly['agenda']}\n\nDescrizione:\n{$assembly['description']}\n\nChiosa finale:\n{$assembly['final_statement']}";
    }

    private function minutesBody(array $assembly): string
    {
        $sessions = [];
        foreach ($assembly['sessions'] ?? [] as $session) {
            $notes = array_filter($assembly['notes'] ?? [], static fn (array $note): bool => (int)$note['session_id'] === (int)$session['id']);
            $content = implode("\n", array_map(
                fn (array $note): string => $this->noteLabel((string)$note['note_type']) . ":\n" . (string)$note['body'],
                $notes
            ));
            $sessions[] = sprintf(
                "TURNO %s | Data: %s | Ora: %s-%s | Luogo: %s\n\n%s",
                (string)$session['shift_label'],
                (string)$session['assembly_date'],
                substr((string)$session['time_start'], 0, 5),
                substr((string)($session['time_end'] ?? ''), 0, 5) ?: '-',
                (string)($session['place'] ?? '-'),
                $content !== '' ? $content : 'Nessun contenuto inserito.'
            );
        }
        $participants = implode("\n", array_map(static fn (array $participant): string => '- ' . (string)$participant['label'], $assembly['selected_participants'] ?? []));
        $documents = implode("\n", array_map(static fn (array $document): string => '- ' . (string)$document['original_name'], $assembly['documents'] ?? []));
        $vote = (int)$assembly['voting_enabled'] === 1 ? 'Votazione predisposta: ' . (string)($assembly['voting_subject'] ?? '-') : 'Nessuna votazione predisposta.';

        return "Ordine del giorno:\n{$assembly['agenda']}\n\nDescrizione:\n{$assembly['description']}\n\nPartecipanti/convocati:\n{$participants}\n\nContenuti per turno:\n" . implode("\n\n", $sessions) . "\n\nChiosa finale:\n{$assembly['final_statement']}\n\nVotazione:\n{$vote}\n\nAllegati:\n{$documents}";
    }

    private function noteLabel(string $type): string
    {
        return [
            'discussion' => 'Discussione',
            'question' => 'Domanda/intervento',
            'answer' => 'Risposta',
            'proposal' => 'Proposta',
            'decision' => 'Decisione/mandato',
            'note' => 'Nota',
        ][$type] ?? $type;
    }

    public function updateFinalStatement(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $assembly = $this->findAssembly((int)$params['id']);
        $data = $request->all();
        $updated = $this->app->workersAssemblies->update((int)$assembly['id'], [
            'practice_id' => $assembly['practice_id'],
            'title' => $assembly['title'],
            'agenda' => $assembly['agenda'],
            'description' => $assembly['description'],
            'final_statement' => trim((string)($data['final_statement'] ?? '')) ?: null,
            'status' => $assembly['status'],
            'visibility' => $assembly['visibility'],
            'voting_enabled' => (int)$assembly['voting_enabled'],
            'voting_subject' => $assembly['voting_subject'],
        ]);
        $this->app->activityLogs->write((int)$user['id'], 'assemblies.final_statement_update', [
            'section' => 'assemblies',
            'assembly_id' => $updated['id'],
        ]);

        return Response::json(['data' => $this->withSessions($updated)]);
    }

    private function appBaseUrl(): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
            return $scheme . '://' . $host . ($dir === '' ? '' : $dir);
        }

        return rtrim((string)env_value('APP_URL', 'http://localhost/myrsu'), '/');
    }
}
