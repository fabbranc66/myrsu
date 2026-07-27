<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use Throwable;

final class FundController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->requireOperator($request);
        return Response::json(['data' => [
            'balance' => $this->app->funds->balance(),
            'contracts' => $this->app->funds->contracts(),
            'movements' => $this->app->funds->movements(),
        ]]);
    }

    public function storeContract(Request $request): Response
    {
        $user = $this->requireOperator($request);
        $contract = $this->app->funds->createContract($this->contractData($request->all(), (int)$user['id']));
        [$protocol, $protocolError] = $this->protocolContractSafe($contract, (int)$user['id']);
        $this->log((int)$user['id'], 'funds.contract_create', ['contract_id' => $contract['id']]);
        return Response::json(['data' => ['contract' => $contract, 'protocol' => $protocol, 'protocol_error' => $protocolError]], 201);
    }

    public function updateContract(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $this->findContract((int)$params['id']);
        $contract = $this->app->funds->updateContract((int)$params['id'], $this->contractData($request->all(), (int)$user['id']));
        [$protocol, $protocolError] = $this->protocolContractSafe($contract, (int)$user['id']);
        $this->log((int)$user['id'], 'funds.contract_update', ['contract_id' => (int)$params['id']]);
        return Response::json(['data' => ['contract' => $contract, 'protocol' => $protocol, 'protocol_error' => $protocolError]]);
    }

    public function destroyContract(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $this->findContract((int)$params['id']);
        $this->app->funds->deleteContract((int)$params['id']);
        $this->log((int)$user['id'], 'funds.contract_delete', ['contract_id' => (int)$params['id']]);
        return Response::json(['data' => ['deleted' => true]]);
    }

    public function protocolContract(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $contract = $this->findContract((int)$params['id']);
        $entry = $this->app->fundProtocol->contract($contract, (int)$user['id']);
        $this->log((int)$user['id'], 'funds.contract_protocol', [
            'contract_id' => (int)$contract['id'],
            'document_id' => (int)$contract['document_id'],
            'protocol_number' => $entry['protocol_number'],
        ]);

        return Response::json(['data' => ['protocol' => $entry]]);
    }

    public function storeMovement(Request $request): Response
    {
        $user = $this->requireOperator($request);
        $movement = $this->app->funds->createMovement($this->movementData($request->all(), (int)$user['id']));
        $protocol = $this->app->fundProtocol->movement($movement, (int)$user['id']);
        $this->log((int)$user['id'], 'funds.movement_create', ['movement_id' => $movement['id']]);
        return Response::json(['data' => ['movement' => $movement, 'protocol' => $protocol]], 201);
    }

    public function updateMovement(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $this->findMovement((int)$params['id']);
        $movement = $this->app->funds->updateMovement((int)$params['id'], $this->movementData($request->all(), (int)$user['id']));
        $protocol = $this->app->fundProtocol->movement($movement, (int)$user['id']);
        $this->log((int)$user['id'], 'funds.movement_update', ['movement_id' => (int)$params['id']]);
        return Response::json(['data' => ['movement' => $movement, 'protocol' => $protocol]]);
    }

    public function destroyMovement(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $this->findMovement((int)$params['id']);
        $this->app->funds->deleteMovement((int)$params['id']);
        $this->log((int)$user['id'], 'funds.movement_delete', ['movement_id' => (int)$params['id']]);
        return Response::json(['data' => ['deleted' => true]]);
    }

    private function contractData(array $data, int $userId): array
    {
        Validator::required($data, ['supplier_name', 'start_date', 'status']);
        $status = (string)$data['status'];
        if (!in_array($status, ['active', 'expired', 'closed'], true)) throw new HttpException(422, 'Stato contratto non valido.');
        return [
            'supplier_name' => trim((string)$data['supplier_name']),
            'contract_number' => trim((string)($data['contract_number'] ?? '')) ?: null,
            'start_date' => trim((string)$data['start_date']),
            'end_date' => trim((string)($data['end_date'] ?? '')) ?: null,
            'status' => $status,
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'document_id' => $this->nullableId($data['document_id'] ?? null),
            'created_by' => $userId,
        ];
    }

    private function movementData(array $data, int $userId): array
    {
        Validator::required($data, ['movement_date', 'movement_type', 'amount', 'reason']);
        $type = (string)$data['movement_type'];
        if (!in_array($type, ['income', 'expense'], true)) throw new HttpException(422, 'Tipo movimento non valido.');
        if ((float)$data['amount'] <= 0) throw new HttpException(422, 'Importo non valido.');
        return [
            'contract_id' => $this->nullableId($data['contract_id'] ?? null),
            'movement_date' => trim((string)$data['movement_date']),
            'movement_type' => $type,
            'amount' => (float)$data['amount'],
            'reason' => trim((string)$data['reason']),
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'document_id' => $this->nullableId($data['document_id'] ?? null),
            'created_by' => $userId,
        ];
    }

    private function requireOperator(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        if (!array_intersect($this->app->roles->rolesForUser((int)$user['id']), ['admin', 'delegato', 'rls'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }
        return $user;
    }

    private function findContract(int $id): array
    {
        $contract = $this->app->funds->findContract($id);
        if ($contract === null) throw new HttpException(404, 'Contratto non trovato.');
        return $contract;
    }

    private function findMovement(int $id): array
    {
        $movement = $this->app->funds->findMovement($id);
        if ($movement === null) throw new HttpException(404, 'Movimento non trovato.');
        return $movement;
    }

    private function nullableId(mixed $value): ?int
    {
        $id = (int)$value;
        return $id > 0 ? $id : null;
    }

    private function protocolContractSafe(array $contract, int $userId): array
    {
        if ((int)($contract['document_id'] ?? 0) === 0) {
            return [null, null];
        }

        try {
            return [$this->app->fundProtocol->contract($contract, $userId), null];
        } catch (Throwable $exception) {
            return [null, $exception->getMessage()];
        }
    }

    private function log(int $userId, string $action, array $details): void
    {
        $this->app->activityLogs->write($userId, $action, ['section' => 'fondi'] + $details);
    }
}
