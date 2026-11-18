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
    private readonly PracticeAccessService $practiceAccess;
    private readonly DocumentVisibilityService $visibility;
    private readonly DocumentFolderPolicyService $policies;

    public function __construct(
        private readonly DocumentFolderRepository $folders,
        private readonly RolePermissionRepository $roles,
        private readonly string $basePath
    ) {
        $this->practiceAccess = new PracticeAccessService($roles);
        $this->visibility = new DocumentVisibilityService($roles);
        $this->policies = new DocumentFolderPolicyService($this->visibility);
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
        $requestedFolder = $folderId;
        $data = $this->data();
        $folders = array_column($data['folders'], null, 'id');
        $roleNames = $user === null ? [] : $this->rolesForUser((int)$user['id']);
        $visited = [];
        while ($folderId !== null) {
            if (isset($visited[$folderId]) || !isset($folders[$folderId])) return false;
            $visited[$folderId] = true;
            $folder = $folders[$folderId];
            if (($folder['practice_id'] ?? null) !== null && !$this->practiceAccess->allows([
                'visibility' => $folder['practice_visibility'],
                'assigned_user_id' => $folder['practice_assigned_user_id'],
            ], $user)) return false;
            $folderId = $folder['parent_id'] === null ? null : (int)$folder['parent_id'];
        }
        if (in_array('admin', $roleNames, true)) return true;
        $policy = $this->policies->folder($requestedFolder, $data);
        return $policy === null || ($policy['visibility'] === 'creator' && $user !== null) || $this->policies->allows($policy, $user, $manage);
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
        if (!array_key_exists('visibility', $document) || !array_key_exists('uploaded_by', $document)) {
            $document = $this->folders->document((int)($document['id'] ?? 0));
            if ($document === null) return false;
        }
        try {
            $policies = $this->policies->document($document, $this->data());
        } catch (HttpException) {
            return false;
        }
        if ($this->visibility->privileged($document, $user)) return true;
        if ($policies === []) return $this->visibility->allows((string)($document['visibility'] ?? 'creator'), $user);
        foreach ($policies as $policy) if (!$this->policies->allows($policy, $user, $manage)) return false;
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
            throw new HttpException(403, 'Accesso al documento non autorizzato dalla visibilità o dai permessi della cartella.');
        }
    }

    public function filterDocuments(array $documents, ?array $user): array
    {
        return array_values(array_filter($documents, fn (array $document): bool => $this->allowsDocument($document, $user)));
    }

    public function describe(array $document, ?array $user = null): array
    {
        $details = $this->policies->describe($document, $this->data());
        $details['can_set_visibility'] = $this->canSetVisibility($document, $user);
        $details['visibility_folders'] = array_map(fn (array $policy): string => $this->allowsFolder($policy['folder_id'], $user) ? $policy['name'] : 'cartella riservata', $this->policies->document($document, $this->data()));
        return $details;
    }

    public function canSetVisibility(array $document, ?array $user): bool
    {
        return $this->visibility->privileged($document, $user);
    }

    public function moveExpandsAccess(array $document, ?int $destination): bool
    {
        return (bool)array_diff($this->policies->audience($document, $this->data(), true, $destination), $this->policies->audience($document, $this->data()));
    }

    public function permissionsExpandAccess(int $folderId, bool $inherit, ?string $visibility, array $permissions, array $documents): bool
    {
        $before = $this->data();
        $after = $before;
        foreach ($after['folders'] as &$folder) {
            if ((int)$folder['id'] === $folderId) { $folder['inherit_permissions'] = $inherit; $folder['visibility'] = $visibility; }
        }
        unset($folder);
        $after['permissions'] = array_values(array_filter($after['permissions'], static fn (array $row): bool => (int)$row['folder_id'] !== $folderId));
        foreach ($inherit ? [] : $permissions as $permission) $after['permissions'][] = $permission + ['folder_id' => $folderId];
        foreach ($documents as $document) {
            if (array_diff($this->policies->audience($document, $after), $this->policies->audience($document, $before))) return true;
        }
        return false;
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
