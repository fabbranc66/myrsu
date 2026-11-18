<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ConfigurationCatalogService;
use App\Services\ConfigurationFilesService;
use PDOException;

final class SavedConfigurationController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->admin($request);
        $rows = array_map($this->summary(...), $this->app->configurationContext->repository->all());
        return Response::json(['data' => ['configurations' => $rows, 'current' => $this->app->configurationContext->summary()]]);
    }

    public function create(Request $request): Response
    {
        $user = $this->admin($request);
        $mode = $request->input('mode');
        if (!in_array($mode, ['copy', 'empty'], true)) throw new HttpException(422, 'Scegli copia della situazione attuale oppure configurazione vuota.');
        $service = $this->service();
        try {
            $row = $service->create($this->app->configurationContext->current, $service->name($request->input('name')), $mode === 'copy', (int)$user['id']);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') throw new HttpException(409, 'Nome già utilizzato o dati collegati non coerenti.');
            throw $exception;
        }
        $this->log($user, 'configurations.create', (int)$row['id']);
        return Response::json(['data' => $this->summary($row)], 201);
    }

    public function save(Request $request, array $params): Response
    {
        $user = $this->admin($request);
        $row = $this->find((int)$params['id']);
        if ((int)$this->app->configurationContext->current['id'] !== (int)$row['id']) throw new HttpException(409, 'Apri la configurazione prima di salvarla.');
        if ($request->input('confirm') !== $row['name']) throw new HttpException(422, 'Conferma il nome della configurazione da aggiornare.');
        try {
            $row = $this->app->configurationContext->repository->save((int)$row['id'], $this->service()->name($request->input('name')), (int)$user['id']);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') throw new HttpException(409, 'Nome configurazione già utilizzato.');
            throw $exception;
        }
        $this->log($user, 'configurations.save', (int)$row['id']);
        return Response::json(['data' => $this->summary($row)]);
    }

    public function select(Request $request): Response
    {
        $user = $this->admin($request);
        $id = $request->input('configuration_id');
        if ($id !== null && (!is_int($id) || $id <= 0)) throw new HttpException(422, 'Configurazione non valida.');
        if ($id !== null) $this->find($id);
        $this->app->configurationContext->repository->select($request->bearerToken(), $id);
        $this->log($user, 'configurations.select', $id);
        return Response::json(['data' => ['selected' => $id]]);
    }

    public function operational(Request $request, array $params): Response
    {
        $user = $this->admin($request);
        $row = $this->find((int)$params['id']);
        if ($request->input('confirm') !== $row['name']) throw new HttpException(422, 'Conferma il nome della configurazione da rendere operativa.');
        $this->app->configurationContext->repository->makeOperational((int)$row['id']);
        $this->log($user, 'configurations.operational', (int)$row['id']);
        return Response::json(['data' => ['operational_id' => (int)$row['id']]]);
    }

    private function admin(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        if (!in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true)) throw new HttpException(403, 'Operazione riservata agli amministratori.');
        if ($this->app->configurationContext->current === null) throw new HttpException(409, 'Esegui prima la migration 20261024_saved_configurations.sql.');
        return $user;
    }

    private function find(int $id): array
    {
        return $this->app->configurationContext->repository->find($id) ?? throw new HttpException(404, 'Configurazione non trovata.');
    }

    private function summary(array $row): array
    {
        unset($row['table_prefix'], $row['tables_json'], $row['state']);
        $row['id'] = (int)$row['id'];
        $row['is_operational'] = (int)$row['operational_key'] === 1;
        unset($row['operational_key']);
        return $row;
    }

    private function service(): ConfigurationCatalogService
    {
        return new ConfigurationCatalogService($this->app->configurationContext->repository, new ConfigurationFilesService($this->app->basePath()));
    }

    private function log(array $user, string $action, ?int $id): void
    {
        $this->app->activityLogs->write((int)$user['id'], $action, ['configuration_id' => $id]);
    }
}
