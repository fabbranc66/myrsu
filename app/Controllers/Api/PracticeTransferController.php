<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\PracticeTransferService;

final class PracticeTransferController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function preview(Request $request, array $params): Response
    {
        $this->admin($request);
        return Response::json(['data' => (new PracticeTransferService($this->app))->preview($this->id($params['source']), $this->id($params['id']))]);
    }

    public function move(Request $request, array $params): Response
    {
        $user = $this->admin($request);
        $confirm = $request->input('confirm');
        if (!is_string($confirm)) throw new HttpException(422, 'Codice di conferma obbligatorio.');
        return Response::json(['data' => (new PracticeTransferService($this->app))->move($this->id($params['source']), $this->id($params['id']), trim($confirm), (int)$user['id'])]);
    }

    private function admin(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        if (!in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true)) throw new HttpException(403, 'Spostamento riservato agli amministratori.');
        return $user;
    }

    private function id(string $value): int
    {
        if (!ctype_digit($value) || (int)$value <= 0) throw new HttpException(422, 'Identificativo non valido.');
        return (int)$value;
    }
}
