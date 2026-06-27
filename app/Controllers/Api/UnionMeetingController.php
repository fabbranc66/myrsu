<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class UnionMeetingController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->requireManager($request);

        return Response::json(['data' => array_map([$this, 'withParticipants'], $this->app->unionMeetings->all())]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireManager($request);
        $data = $request->all();
        Validator::required($data, ['title', 'description', 'participants', 'agenda', 'meeting_date', 'location']);

        $status = (string)($data['status'] ?? 'scheduled');
        $visibility = (string)($data['visibility'] ?? 'rsu');
        if (!in_array($status, ['scheduled', 'done', 'cancelled'], true)) {
            throw new HttpException(422, 'Stato non valido.');
        }
        if (!in_array($visibility, ['public', 'members', 'rsu'], true)) {
            throw new HttpException(422, 'Visibilita non valida.');
        }

        $meeting = $this->app->unionMeetings->create([
            'title' => trim((string)$data['title']),
            'description' => trim((string)$data['description']),
            'participants' => trim((string)$data['participants']),
            'agenda' => trim((string)$data['agenda']),
            'meeting_date' => str_replace('T', ' ', trim((string)$data['meeting_date'])),
            'location' => trim((string)$data['location']),
            'status' => $status,
            'visibility' => $visibility,
            'created_by' => (int)$user['id'],
        ]);
        $this->app->unionMeetingParticipants->replace((int)$meeting['id'], $data['selected_participants'] ?? []);
        $meeting = $this->withParticipants($meeting);

        $this->app->activityLogs->write((int)$user['id'], 'meetings.create', [
            'section' => 'meetings',
            'meeting_id' => $meeting['id'],
            'title' => $meeting['title'],
        ]);

        return Response::json(['data' => $meeting], 201);
    }

    public function show(Request $request, array $params): Response
    {
        $this->requireManager($request);
        $meeting = $this->findMeeting((int)$params['id']);
        $meeting['notes'] = $this->app->unionMeetingNotes->forMeeting((int)$meeting['id']);
        $meeting = $this->withParticipants($meeting);

        return Response::json(['data' => $meeting]);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $meeting = $this->findMeeting((int)$params['id']);
        $data = $request->all();
        Validator::required($data, ['title', 'description', 'participants', 'agenda', 'meeting_date', 'location']);

        $updated = $this->app->unionMeetings->update((int)$meeting['id'], [
            'title' => trim((string)$data['title']),
            'description' => trim((string)$data['description']),
            'participants' => trim((string)$data['participants']),
            'agenda' => trim((string)$data['agenda']),
            'meeting_date' => str_replace('T', ' ', trim((string)$data['meeting_date'])),
            'location' => trim((string)$data['location']),
            'status' => (string)($data['status'] ?? $meeting['status']),
            'visibility' => (string)($data['visibility'] ?? $meeting['visibility']),
        ]);
        $this->app->unionMeetingParticipants->replace((int)$meeting['id'], $data['selected_participants'] ?? []);
        $this->app->activityLogs->write((int)$user['id'], 'meetings.update', [
            'section' => 'meetings',
            'meeting_id' => $updated['id'],
        ]);

        return Response::json(['data' => $this->withParticipants($updated)]);
    }

    public function publicComunicato(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $meeting = $this->findMeeting((int)$params['id']);
        if ($meeting['public_document_id'] !== null) {
            return Response::json(['data' => [
                'meeting' => $meeting,
                'document' => $this->app->documents->findById((int)$meeting['public_document_id']),
            ]]);
        }

        $title = 'Incontro sindacale - ' . $meeting['title'];
        $body = $this->publicComunicatoBody($meeting);
        $protocol = $this->app->protocols->create('OUT', 'COM', $title, (int)$user['id']);
        $original = $this->app->comunicatoDirectPdf->textOriginal($title, $body, (string)$protocol['protocol_number'], (string)$protocol['created_at']);
        $stored = $this->app->documentStorage->storeGeneratedPdf(
            $original,
            'incontro-sindacale.html',
            'comunicati',
            fn (string $pdfPath) => $this->app->comunicatoDirectPdf->write(
                $pdfPath,
                $title,
                $body,
                (string)$protocol['protocol_number'],
                (string)$protocol['created_at']
            )
        );
        $document = $this->app->documents->create($stored + ['visibility' => 'public', 'uploaded_by' => (int)$user['id']]);
        $protocol = $this->app->protocols->update((int)$protocol['id'], $title, (int)$document['id']);
        $document = $this->app->documents->updateSignature((int)$document['id'], $this->app->documentSignature->sign($document));
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $verifyUrl = $this->appBaseUrl() . '/ui/document-verify.html?id=' . (int)$document['id'] . '&sig=' . urlencode((string)$document['signature']);
        $this->app->comunicatoDirectPdf->write($pdfPath, $title, $body, (string)$protocol['protocol_number'], (string)$protocol['created_at'], (string)$document['id'], $verifyUrl, (string)$document['signature']);
        $document = $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
        $this->app->documentStorage->uploadPdfToHosting($document);
        $meeting = $this->app->unionMeetings->attachPublicDocument((int)$meeting['id'], (int)$document['id']);

        $this->app->activityLogs->write((int)$user['id'], 'meetings.public_comunicato', [
            'section' => 'meetings',
            'meeting_id' => $meeting['id'],
            'document_id' => $document['id'],
            'protocol_number' => $protocol['protocol_number'],
        ]);

        return Response::json(['data' => ['meeting' => $meeting, 'document' => $document, 'protocol' => $protocol]], 201);
    }

    public function notes(Request $request, array $params): Response
    {
        $this->requireManager($request);
        $meeting = $this->findMeeting((int)$params['id']);

        return Response::json(['data' => $this->app->unionMeetingNotes->forMeeting((int)$meeting['id'])]);
    }

    public function storeNote(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $meeting = $this->findMeeting((int)$params['id']);
        $data = $request->all();
        Validator::required($data, ['note_type', 'body']);
        $type = (string)$data['note_type'];
        if (!in_array($type, ['content', 'answer', 'idea', 'proposal'], true)) {
            throw new HttpException(422, 'Tipo nota non valido.');
        }

        $note = $this->app->unionMeetingNotes->create(
            (int)$meeting['id'],
            $type,
            trim((string)$data['body']),
            (int)$user['id']
        );
        $this->app->activityLogs->write((int)$user['id'], 'meetings.note_create', [
            'section' => 'meetings',
            'meeting_id' => $meeting['id'],
            'note_type' => $type,
        ]);

        return Response::json(['data' => $note], 201);
    }

    private function requireManager(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        if (!array_intersect($roles, ['admin', 'delegato'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

        return $user;
    }

    private function findMeeting(int $id): array
    {
        $meeting = $this->app->unionMeetings->findById($id);
        if ($meeting === null) {
            throw new HttpException(404, 'Incontro non trovato.');
        }

        return $meeting;
    }

    private function publicComunicatoBody(array $meeting): string
    {
        $participants = $this->participantLabels((int)$meeting['id']);
        if ($participants === '') {
            $participants = (string)$meeting['participants'];
        }

        return "Partecipanti:\n{$participants}\n\nOrdine del giorno:\n{$meeting['agenda']}\n\nLuogo:\n{$meeting['location']}\n\nData e ora:\n{$meeting['meeting_date']}\n\n{$meeting['description']}";
    }

    private function withParticipants(array $meeting): array
    {
        $meeting['selected_participants'] = $this->app->unionMeetingParticipants->forMeeting((int)$meeting['id']);

        return $meeting;
    }

    private function participantLabels(int $meetingId): string
    {
        return implode("\n", array_map(
            static fn (array $participant): string => (string)$participant['label'],
            $this->app->unionMeetingParticipants->forMeeting($meetingId)
        ));
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
