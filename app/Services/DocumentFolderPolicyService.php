<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class DocumentFolderPolicyService
{
    public function __construct(private readonly DocumentVisibilityService $visibility)
    {
    }

    public function folder(?int $folderId, array $data, array $visited = []): ?array
    {
        $folders = array_column($data['folders'], null, 'id');
        while ($folderId !== null) {
            if (isset($visited[$folderId]) || !isset($folders[$folderId])) throw new HttpException(403, 'Percorso cartella non autorizzato.');
            $visited[$folderId] = true;
            $folder = $folders[$folderId];
            if (!(bool)$folder['inherit_permissions']) {
                if (($folder['visibility'] ?? null) === 'document') return null;
                $policy = [
                    'folder_id' => $folderId, 'name' => $folder['name'] ?? '',
                    'visibility' => $folder['visibility'] ?? null,
                    'permissions' => array_values(array_filter($data['permissions'], static fn (array $row): bool => (int)$row['folder_id'] === $folderId)),
                ];
                if ($policy['visibility'] === null) {
                    $parent = $this->folder($folder['parent_id'] === null ? null : (int)$folder['parent_id'], $data, $visited);
                    if ($parent !== null) {
                        $read = $parent['visibility'] === null ? $this->visibility->roleAudience($parent['permissions']) : $this->visibility->audience($parent['visibility']);
                        $manage = $parent['visibility'] === null ? $this->visibility->roleAudience($parent['permissions'], true) : $read;
                        $names = array_column($this->visibility->roleDefinitions(), 'name', 'id');
                        $permissions = [];
                        foreach ($policy['permissions'] as $permission) {
                            $name = $names[(int)$permission['role_id']] ?? '';
                            if ($name === 'admin' || in_array($name, $read, true)) {
                                if ($name !== 'admin' && !in_array($name, $manage, true)) $permission['access_level'] = 'read';
                                $permissions[] = $permission;
                            }
                        }
                        $policy['permissions'] = $permissions;
                    }
                }
                return $policy;
            }
            $folderId = $folder['parent_id'] === null ? null : (int)$folder['parent_id'];
        }
        return null;
    }

    public function document(array $document, array $data, bool $override = false, ?int $destination = null): array
    {
        $folderIds = [];
        foreach ($data['placements'] as $placement) {
            if ((int)$placement['document_id'] === (int)$document['id']) $folderIds[] = (int)$placement['folder_id'];
        }
        if ($override) $folderIds = $destination === null ? [] : [$destination];
        $systemFolders = array_column(array_filter($data['folders'], static fn (array $folder): bool => $folder['system_key'] !== null), 'id', 'system_key');
        foreach ($data['practice_links'] as $link) {
            if ((int)$link['document_id'] !== (int)$document['id']) continue;
            $folderId = $systemFolders['practice:' . $link['practice_id']] ?? $systemFolders['practices'] ?? null;
            if ($folderId !== null) $folderIds[] = (int)$folderId;
        }
        $policies = [];
        foreach (array_unique($folderIds) as $folderId) {
            $policy = $this->folder($folderId, $data);
            if ($policy !== null) $policies[$policy['folder_id']] = $policy;
        }
        return array_values($policies);
    }

    public function allows(array $policy, ?array $user, bool $manage = false): bool
    {
        if ($policy['visibility'] !== null) {
            return $policy['visibility'] === 'creator' ? false : $this->visibility->allows($policy['visibility'], $user);
        }
        return (bool)array_intersect($this->visibility->roles($user), $this->visibility->roleAudience($policy['permissions'], $manage));
    }

    public function audience(array $document, array $data, bool $override = false, ?int $destination = null): array
    {
        $policies = $this->document($document, $data, $override, $destination);
        if ($policies === []) return $this->visibility->audience((string)($document['visibility'] ?? 'creator'));
        $audience = null;
        foreach ($policies as $policy) {
            $current = $policy['visibility'] === null ? $this->visibility->roleAudience($policy['permissions']) : $this->visibility->audience($policy['visibility']);
            $audience = $audience === null ? $current : array_values(array_intersect($audience, $current));
        }
        return $audience ?? [];
    }

    public function describe(array $document, array $data): array
    {
        $policies = $this->document($document, $data);
        $effective = (string)($document['visibility'] ?? 'creator');
        if ($policies !== []) {
            $effective = 'custom';
            $audience = $this->audience($document, $data);
            foreach (DocumentVisibilityService::VALUES as $value) {
                $expected = $this->visibility->audience($value);
                if (!array_diff($audience, $expected) && !array_diff($expected, $audience)) { $effective = $value; break; }
            }
        }
        return ['effective_visibility' => $effective, 'visibility_inherited' => $policies !== [], 'visibility_folders' => array_column($policies, 'name')];
    }
}
