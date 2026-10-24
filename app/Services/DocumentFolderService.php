<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\DocumentFolderRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\RolePermissionRepository;
use PDOException;

final class DocumentFolderService
{
    public function __construct(
        private readonly DocumentFolderRepository $folders,
        private readonly DocumentRepository $documents,
        private readonly RolePermissionRepository $roles,
        private readonly DocumentFolderAccessService $access
    ) {
    }

    public function create(array $data, array $user): array
    {
        $name = is_string($data['name'] ?? null) ? trim($data['name']) : '';
        if ($name === '' || mb_strlen($name) > 150 || in_array($name, ['.', '..'], true)
            || preg_match('~[\\\\/:*?"<>|\x00-\x1F\x7F]~u', $name)) {
            throw new HttpException(422, 'Nome cartella non valido (massimo 150 caratteri, senza separatori di percorso).');
        }
        $parentId = $this->folderId($data['parent_id'] ?? null);
        if ($parentId === null && mb_strtolower($name) === 'documenti pratiche') {
            throw new HttpException(422, 'Questo nome è riservato alla cartella automatica delle pratiche.');
        }
        $this->requireFolder($parentId, $user);
        try {
            $folder = $this->folders->create($name, $parentId, (int)$user['id']);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new HttpException(409, 'Esiste già una cartella con questo nome nella stessa posizione.');
            }
            throw $exception;
        }
        $this->access->refresh();
        return $folder;
    }

    public function uploadDestination(mixed $value, array $user): ?int
    {
        $folderId = $this->folderId($value === '' ? null : $value);
        if ($folderId !== null && !$this->roles->userHasPermission((int)$user['id'], 'documents.update')) {
            throw new HttpException(403, 'Permesso insufficiente per caricare nella cartella.');
        }
        $this->requireFolder($folderId, $user);
        return $folderId;
    }

    public function move(int $documentId, array $data, array $user): array
    {
        if (!array_key_exists('folder_id', $data)) throw new HttpException(422, 'Indicare la cartella di destinazione.');
        $document = $this->documents->findById($documentId);
        if ($document === null) throw new HttpException(404, 'Documento non trovato.');
        $this->access->assertDocument($document, $user, true);
        $folderId = $this->folderId($data['folder_id']);
        $this->requireFolder($folderId, $user);
        $this->folders->place($documentId, $folderId, (int)$user['id']);
        $this->access->refresh();
        return ['document_id' => $documentId, 'folder_id' => $folderId];
    }

    public function setPermissions(int $folderId, array $data): void
    {
        if ($this->folders->find($folderId) === null) throw new HttpException(404, 'Cartella non trovata.');
        if (!is_bool($data['inherit_permissions'] ?? null) || !is_array($data['permissions'] ?? null)) {
            throw new HttpException(422, 'Specificare ereditarietà e permessi per ruolo.');
        }
        $roles = array_column($this->roles->allRoles(), 'id');
        $permissions = [];
        foreach ($data['permissions'] as $permission) {
            if (!is_array($permission)) throw new HttpException(422, 'Permesso non valido.');
            $roleId = $this->folderId($permission['role_id'] ?? null);
            if ($roleId === null || !in_array($roleId, array_map('intval', $roles), true)
                || !in_array($permission['access_level'] ?? null, ['read', 'manage'], true) || isset($permissions[$roleId])) {
                throw new HttpException(422, 'Ruolo o livello di accesso non valido.');
            }
            $permissions[$roleId] = ['role_id' => $roleId, 'access_level' => $permission['access_level']];
        }
        $this->folders->setPermissions($folderId, $data['inherit_permissions'], array_values($permissions));
        $this->access->refresh();
    }

    private function requireFolder(?int $folderId, array $user): void
    {
        $folder = $folderId === null ? null : $this->folders->find($folderId);
        if ($folderId !== null && $folder === null) throw new HttpException(404, 'Cartella non trovata.');
        if (($folder['system_key'] ?? null) !== null) {
            throw new HttpException(422, 'Cartella automatica: i documenti sono gestiti dai collegamenti alle pratiche.');
        }
        $this->access->assertFolder($folderId, $user, true);
    }

    private function folderId(mixed $value): ?int
    {
        if ($value === null) return null;
        if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new HttpException(422, 'Identificativo cartella o ruolo non valido.');
        }
        return (int)$value;
    }
}
