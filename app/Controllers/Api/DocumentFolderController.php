<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class DocumentFolderController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $user = $this->app->auth->requireUser($request);
        $canManage = $this->app->roles->userHasPermission((int)$user['id'], 'documents.update');
        $canSetPermissions = in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true)
            && $canManage && $this->app->roles->userHasPermission((int)$user['id'], 'roles.manage');
        if ($this->app->documentFolders->ensurePracticeFolders()) $this->app->documentFolderAccess->refresh();
        $data = $this->app->documentFolderAccess->data();
        $folders = [];
        foreach ($data['folders'] as $folder) {
            if (!$this->app->documentFolderAccess->allowsFolder((int)$folder['id'], $user)) continue;
            $folder['can_manage'] = $canManage && $this->app->documentFolderAccess->allowsFolder((int)$folder['id'], $user, true);
            $folder['can_set_permissions'] = $canSetPermissions && $folder['can_manage'];
            if ($folder['practice_id'] !== null) {
                $folder['name'] = $folder['practice_code'] . ' · ' . $folder['practice_title'];
            }
            $folders[] = $folder;
        }
        $visibleIds = array_map('intval', array_column($folders, 'id'));
        $byId = array_column($data['folders'], null, 'id');
        foreach ($folders as &$folder) {
            while ($folder['parent_id'] !== null && !in_array((int)$folder['parent_id'], $visibleIds, true)) {
                $folder['parent_id'] = $byId[(int)$folder['parent_id']]['parent_id'] ?? null;
            }
        }
        unset($folder);
        return Response::json(['data' => [
            'folders' => $folders,
            'placements' => array_values(array_filter($data['placements'], fn (array $item): bool => in_array((int)$item['folder_id'], $visibleIds, true))),
            'permissions' => $canSetPermissions ? $data['permissions'] : [],
            'roles' => $canSetPermissions ? $this->app->roles->allRoles() : [],
            'can_manage' => $canManage,
            'can_set_permissions' => $canSetPermissions,
        ]]);
    }

    public function store(Request $request): Response
    {
        $user = $this->app->auth->requirePermission($request, 'documents.update');
        $folder = $this->app->documentFolderService->create($request->all(), $user);
        $this->app->activityLogs->write((int)$user['id'], 'document_folders.created', ['folder_id' => $folder['id']]);
        return Response::json(['data' => $folder], 201);
    }

    public function move(Request $request, array $params): Response
    {
        $user = $this->app->auth->requirePermission($request, 'documents.update');
        $placement = $this->app->documentFolderService->move((int)$params['id'], $request->all(), $user);
        if ($placement['confirmation_required'] ?? false) return Response::json(['error' => ['code' => 'document_visibility_expansion', 'message' => $placement['message']]], 409);
        $this->app->activityLogs->write((int)$user['id'], 'document_folders.document_moved', $placement);
        return Response::json(['data' => $placement]);
    }

    public function permissions(Request $request, array $params): Response
    {
        $user = $this->app->auth->requireUser($request);
        if (!in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true)) {
            throw new HttpException(403, 'Solo gli amministratori possono modificare i permessi delle cartelle.');
        }
        $user = $this->app->auth->requirePermission($request, 'roles.manage');
        $this->app->auth->requirePermission($request, 'documents.update');
        $this->app->documentFolderAccess->assertFolder((int)$params['id'], $user, true);
        $result = $this->app->documentFolderService->setPermissions((int)$params['id'], $request->all());
        if ($result['confirmation_required'] ?? false) return Response::json(['error' => ['code' => 'document_visibility_expansion', 'message' => $result['message']]], 409);
        $this->app->activityLogs->write((int)$user['id'], 'document_folders.permissions_updated', ['folder_id' => (int)$params['id']]);
        return Response::json(['data' => ['updated' => true]]);
    }
}
