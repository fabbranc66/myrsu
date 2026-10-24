<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\DocumentFolderRepository;
use App\Repositories\RolePermissionRepository;

final class DocumentFolderAccessService
{
    private ?array $snapshot = null;
    private array $userRoles = [];
    private ?array $roleDefinitions = null;

    public function __construct(
        private readonly DocumentFolderRepository $folders,
        private readonly RolePermissionRepository $roles,
        private readonly string $basePath
    ) {
    }

    public function refresh(): void
    {
        $this->snapshot = null;
    }

    public function data(): array
    {
        return $this->snapshot ??= $this->folders->snapshot();
    }

    public function allowsFolder(?int $folderId, ?array $user, bool $manage = false): bool
    {
        if ($folderId === null) return true;
        $data = $this->data();
        $folders = array_column($data['folders'], null, 'id');
        $roleNames = $user === null ? [] : $this->rolesForUser((int)$user['id']);
        $roleIds = [];
        foreach ($this->roleDefinitions ??= $this->roles->allRoles() as $role) {
            if (in_array($role['name'], $roleNames, true)) $roleIds[] = (int)$role['id'];
        }
        $visited = [];
        while ($folderId !== null) {
            if (isset($visited[$folderId]) || !isset($folders[$folderId])) return false;
            $visited[$folderId] = true;
            $folder = $folders[$folderId];
            if (!in_array('admin', $roleNames, true) && !(bool)$folder['inherit_permissions']) {
                $allowed = false;
                foreach ($data['permissions'] as $permission) {
                    if ((int)$permission['folder_id'] !== $folderId || !in_array((int)$permission['role_id'], $roleIds, true)) continue;
                    if (!$manage || $permission['access_level'] === 'manage') $allowed = true;
                }
                if (!$allowed) return false;
            }
            $folderId = $folder['parent_id'] === null ? null : (int)$folder['parent_id'];
        }
        return true;
    }

    public function folderForDocument(int $documentId): ?int
    {
        foreach ($this->data()['placements'] as $placement) {
            if ((int)$placement['document_id'] === $documentId) return (int)$placement['folder_id'];
        }
        return null;
    }

    public function allowsDocument(array $document, ?array $user, bool $manage = false): bool
    {
        $documentId = (int)$document['id'];
        if (!$this->allowsFolder($this->folderForDocument($documentId), $user, $manage)) return false;
        $systemFolders = array_column(array_filter($this->data()['folders'], fn (array $folder): bool => $folder['system_key'] !== null), 'id', 'system_key');
        foreach ($this->data()['practice_links'] as $link) {
            if ((int)$link['document_id'] !== $documentId) continue;
            $root = $systemFolders['practices'] ?? null;
            if ($root === null) return false;
            $folderId = (int)($systemFolders['practice:' . $link['practice_id']] ?? $root);
            if (!$this->allowsFolder($folderId, $user, $manage)) return false;
        }
        return true;
    }

    public function assertFolder(?int $folderId, ?array $user, bool $manage = false): void
    {
        if (!$this->allowsFolder($folderId, $user, $manage)) {
            throw new HttpException(403, 'Accesso alla cartella non autorizzato.');
        }
    }

    public function assertDocument(array $document, ?array $user, bool $manage = false): void
    {
        if (!$this->allowsDocument($document, $user, $manage)) {
            throw new HttpException(403, 'Accesso al documento non autorizzato dai permessi delle cartelle.');
        }
    }

    public function filterDocuments(array $documents, ?array $user): array
    {
        return array_values(array_filter($documents, fn (array $document): bool => $this->allowsDocument($document, $user)));
    }

    public function assertFile(string $path, ?array $user): void
    {
        $path = str_replace('\\', '/', realpath($path) ?: $path);
        $base = rtrim(str_replace('\\', '/', realpath($this->basePath) ?: $this->basePath), '/') . '/';
        if (!str_starts_with($path, $base)) return;
        foreach ($this->folders->documentsForFile(substr($path, strlen($base))) as $document) {
            $this->assertDocument($document, $user);
        }
    }

    private function rolesForUser(int $userId): array
    {
        return $this->userRoles[$userId] ??= $this->roles->rolesForUser($userId);
    }
}
