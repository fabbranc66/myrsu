<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class ContactController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->requireManager($request);

        return Response::json(['data' => [
            'users' => array_map([$this, 'systemContact'], $this->app->users->all()),
            'institutional' => array_map([$this, 'institutionalContact'], $this->app->institutionalContacts->all()),
        ]]);
    }

    public function storeInstitutional(Request $request): Response
    {
        $user = $this->requireManager($request);
        $data = $request->all();
        Validator::required($data, ['type', 'name']);
        if (!in_array((string)$data['type'], ['aziendale', 'sindacale', 'esterno'], true)) {
            throw new HttpException(422, 'Tipo contatto non valido.');
        }

        $contact = $this->app->institutionalContacts->create([
            'type' => (string)$data['type'],
            'name' => trim((string)$data['name']),
            'role' => trim((string)($data['role'] ?? '')),
            'organization' => trim((string)($data['organization'] ?? '')),
            'email' => trim((string)($data['email'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? '')),
        ], (int)$user['id']);

        $this->app->activityLogs->write((int)$user['id'], 'contacts.create', [
            'section' => 'contacts',
            'contact_id' => $contact['id'],
            'type' => $contact['type'],
        ]);

        return Response::json(['data' => $this->institutionalContact($contact)], 201);
    }

    public function updateInstitutional(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $data = $request->all();
        Validator::required($data, ['type', 'name']);
        if (!in_array((string)$data['type'], ['aziendale', 'sindacale', 'esterno'], true)) {
            throw new HttpException(422, 'Tipo contatto non valido.');
        }

        $contact = $this->app->institutionalContacts->update((int)$params['id'], [
            'type' => (string)$data['type'],
            'name' => trim((string)$data['name']),
            'role' => trim((string)($data['role'] ?? '')),
            'organization' => trim((string)($data['organization'] ?? '')),
            'email' => trim((string)($data['email'] ?? '')),
            'phone' => trim((string)($data['phone'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? '')),
        ]);
        if ($contact === null) {
            throw new HttpException(404, 'Contatto non trovato.');
        }

        $this->app->activityLogs->write((int)$user['id'], 'contacts.update', [
            'section' => 'contacts',
            'contact_id' => $contact['id'],
        ]);

        return Response::json(['data' => $this->institutionalContact($contact)]);
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

    private function systemContact(array $user): array
    {
        return [
            'type' => 'user',
            'id' => (int)$user['id'],
            'label' => (string)$user['name'],
            'role' => (string)($user['roles'] ?? ''),
            'organization' => 'MyRSU',
            'email' => (string)($user['email'] ?? ''),
            'phone' => (string)($user['phone'] ?? $user['mobile'] ?? ''),
        ];
    }

    private function institutionalContact(array $contact): array
    {
        return [
            'type' => 'institutional_contact',
            'id' => (int)$contact['id'],
            'label' => (string)$contact['name'],
            'contact_type' => (string)$contact['type'],
            'role' => (string)($contact['role'] ?? ''),
            'organization' => (string)($contact['organization'] ?? ''),
            'email' => (string)($contact['email'] ?? ''),
            'phone' => (string)($contact['phone'] ?? ''),
        ];
    }
}
