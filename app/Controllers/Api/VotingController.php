<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class VotingController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->requireManager($request);
        return Response::json(['data' => array_map([$this, 'withDetails'], $this->app->votings->all())]);
    }

    public function show(Request $request, array $params): Response
    {
        $this->requireManager($request);
        return Response::json(['data' => $this->withDetails($this->findVoting((int)$params['id']))]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireManager($request);
        $data = $this->validated($request->all());
        if ($data['assembly_id'] !== null && $data['session_id'] !== null) {
            $existing = $this->app->votings->findByAssemblySession((int)$data['assembly_id'], (int)$data['session_id']);
            if ($existing !== null) {
                $updated = $this->app->votings->update((int)$existing['id'], $data);
                $this->app->votingOptions->replace((int)$existing['id'], $data['options']);
                $this->app->activityLogs->write((int)$user['id'], 'votings.update', ['section' => 'votings', 'voting_id' => $existing['id']]);
                return Response::json(['data' => $this->withDetails($updated)]);
            }
        }
        $voting = $this->app->votings->create($data + ['created_by' => (int)$user['id']]);
        $this->app->votingOptions->replace((int)$voting['id'], $data['options']);
        $this->app->activityLogs->write((int)$user['id'], 'votings.create', ['section' => 'votings', 'voting_id' => $voting['id']]);
        return Response::json(['data' => $this->withDetails($voting)], 201);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $voting = $this->findVoting((int)$params['id']);
        $data = $this->validated($request->all());
        $updated = $this->app->votings->update((int)$voting['id'], $data);
        $this->app->votingOptions->replace((int)$voting['id'], $data['options']);
        $this->app->activityLogs->write((int)$user['id'], 'votings.update', ['section' => 'votings', 'voting_id' => $voting['id']]);
        return Response::json(['data' => $this->withDetails($updated)]);
    }

    public function destroy(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $voting = $this->findVoting((int)$params['id']);
        $this->app->votings->delete((int)$voting['id']);
        $this->app->activityLogs->write((int)$user['id'], 'votings.delete', ['section' => 'votings', 'voting_id' => $voting['id']]);
        return Response::json(['data' => ['deleted' => true]]);
    }

    public function generateTokens(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $voting = $this->findVoting((int)$params['id']);
        if ((string)($voting['vote_mode'] ?? 'online') !== 'online') throw new HttpException(403, 'Votazione non online.');
        $count = max(1, min(500, (int)($request->all()['count'] ?? 1)));
        $tokens = $this->app->votingTokens->generate((int)$voting['id'], $count);
        $this->app->activityLogs->write((int)$user['id'], 'votings.tokens_generate', ['section' => 'votings', 'voting_id' => $voting['id'], 'count' => $count]);
        return Response::json(['data' => $tokens], 201);
    }

    public function cancelToken(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $voting = $this->findVoting((int)$params['id']);
        $token = $this->app->votingTokens->findForVoting((int)$voting['id'], (int)$params['tokenId']);
        if ($token === null) throw new HttpException(404, 'Token non trovato.');
        if ((string)$token['status'] !== 'unused') throw new HttpException(403, 'Token non annullabile.');
        $this->app->votingTokens->cancel((int)$token['id']);
        $this->app->activityLogs->write((int)$user['id'], 'votings.token_cancel', ['section' => 'votings', 'voting_id' => $voting['id'], 'token_id' => $token['id']]);
        return Response::json(['data' => ['cancelled' => true]]);
    }

    public function manualVote(Request $request, array $params): Response
    {
        $user = $this->requireManager($request);
        $voting = $this->findVoting((int)$params['id']);
        if ((string)($voting['vote_mode'] ?? 'online') !== 'manual') throw new HttpException(403, 'Votazione non manuale.');
        $this->assertVotingWindow($voting);
        $data = $request->all();
        Validator::required($data, ['participants_count', 'votes']);
        if (!is_array($data['votes'])) throw new HttpException(422, 'Conteggio voti non valido.');
        $participants = max(0, min(5000, (int)$data['participants_count']));
        $options = $this->app->votingOptions->forVoting((int)$voting['id']);
        $optionIds = array_map(static fn (array $option): int => (int)$option['id'], $options);
        $total = 0;
        foreach ($data['votes'] as $optionId => $quantity) {
            if (!in_array((int)$optionId, $optionIds, true)) throw new HttpException(422, 'Opzione voto non valida.');
            $total += max(0, (int)$quantity);
        }
        if ($participants <= 0) throw new HttpException(422, 'Numero partecipanti non valido.');
        if ($total !== $participants) throw new HttpException(422, 'Riconciliazione non valida: voti diversi dai partecipanti.');
        foreach ($data['votes'] as $optionId => $quantity) {
            $quantity = max(0, (int)$quantity);
            if ($quantity > 0) $this->app->votingBallots->createManual((int)$voting['id'], (int)$optionId, $quantity, (int)$user['id']);
        }
        $this->app->activityLogs->write((int)$user['id'], 'votings.manual_reconcile', ['section' => 'votings', 'voting_id' => $voting['id'], 'participants' => $participants, 'votes' => $total]);
        return Response::json(['data' => $this->withDetails($this->findVoting((int)$voting['id']))], 201);
    }

    public function publicToken(Request $request, array $params): Response
    {
        if (trim((string)$params['token']) === '') throw new HttpException(400, 'Token voto assente.');
        $token = $this->findToken((string)$params['token']);
        $voting = $this->findVoting((int)$token['voting_id']);
        $this->assertOpen($voting, $token);
        return Response::json(['data' => $this->withDetails($voting)]);
    }

    public function publicOpen(Request $request): Response
    {
        foreach ($this->app->votings->all() as $voting) {
            if ((string)$voting['status'] !== 'open') continue;
            if ((string)($voting['vote_mode'] ?? 'online') !== 'online') continue;
            $tokens = $this->app->votingTokens->forVoting((int)$voting['id']);
            $token = array_values(array_filter($tokens, static fn (array $row): bool => (string)$row['status'] === 'unused'))[0] ?? null;
            if ($token === null) continue;
            try {
                $this->assertOpen($voting, $token);
            } catch (HttpException) {
                continue;
            }
            $voting = $this->withDetails($voting);
            $voting['vote_token'] = $token['token'];
            return Response::json(['data' => $voting]);
        }

        throw new HttpException(404, 'Nessuna votazione online presente.');
    }

    public function voteByToken(Request $request, array $params): Response
    {
        if (trim((string)$params['token']) === '') throw new HttpException(400, 'Token voto assente.');
        $token = $this->findToken((string)$params['token']);
        $voting = $this->findVoting((int)$token['voting_id']);
        if ((string)($voting['vote_mode'] ?? 'online') !== 'online') throw new HttpException(403, 'Votazione non online.');
        $this->assertOpen($voting, $token);
        $data = $request->all();
        Validator::required($data, ['option_id']);
        $optionIds = array_map(static fn (array $option): int => (int)$option['id'], $this->app->votingOptions->forVoting((int)$voting['id']));
        $optionId = (int)$data['option_id'];
        if (!in_array($optionId, $optionIds, true)) {
            throw new HttpException(422, 'Opzione voto non valida.');
        }

        $localIdentifierHash = null;
        if (!empty($data['local_identifier'])) {
            $localIdentifierHash = hash('sha256', trim((string)$data['local_identifier']));
            if ($this->app->votingBallots->existsForLocalIdentifier((int)$voting['id'], $localIdentifierHash)) {
                throw new HttpException(403, 'Hai già votato da questo dispositivo.');
            }
        }

        $this->app->votingBallots->create((int)$voting['id'], $optionId, (int)$token['id'], null, hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')), $localIdentifierHash);
        $this->app->votingTokens->markUsed((int)$token['id']);
        return Response::json(['data' => ['voted' => true]]);
    }

    private function validated(array $data): array
    {
        Validator::required($data, ['title', 'options']);
        $status = (string)($data['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'open', 'closed', 'cancelled'], true)) {
            throw new HttpException(422, 'Stato non valido.');
        }
        $voteMode = (string)($data['vote_mode'] ?? 'online');
        if (!in_array($voteMode, ['online', 'manual'], true)) {
            throw new HttpException(422, 'Tipo votazione non valido.');
        }
        return [
            'title' => trim((string)$data['title']),
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'status' => $status,
            'anonymous' => !empty($data['anonymous']) ? 1 : 0,
            'vote_mode' => $voteMode,
            'starts_at' => trim((string)($data['starts_at'] ?? '')) ?: null,
            'ends_at' => trim((string)($data['ends_at'] ?? '')) ?: null,
            'assembly_id' => isset($data['assembly_id']) && $data['assembly_id'] !== '' ? (int)$data['assembly_id'] : null,
            'session_id' => isset($data['session_id']) && $data['session_id'] !== '' ? (int)$data['session_id'] : null,
            'options' => is_array($data['options']) ? $data['options'] : [],
        ];
    }

    private function withDetails(array $voting): array
    {
        $voting['options'] = $this->app->votingOptions->forVoting((int)$voting['id']);
        $voting['tokens'] = $this->app->votingTokens->forVoting((int)$voting['id']);
        $voting['results'] = $this->app->votingBallots->results((int)$voting['id']);
        $voting['session'] = $this->votingSession($voting);
        return $voting;
    }

    private function assertOpen(array $voting, array $token): void
    {
        if ((string)$token['status'] !== 'unused') throw new HttpException(403, 'Token non utilizzabile.');
        $this->assertVotingWindow($voting);
    }

    private function assertVotingWindow(array $voting): void
    {
        if ((string)$voting['status'] !== 'open') throw new HttpException(403, 'Votazione non aperta.');
        $now = time();
        if ($voting['starts_at'] !== null && strtotime((string)$voting['starts_at']) > $now) throw new HttpException(403, 'Votazione non ancora aperta.');
        if ($voting['ends_at'] !== null && strtotime((string)$voting['ends_at']) < $now) throw new HttpException(403, 'Votazione chiusa.');
        $session = $this->votingSession($voting);
        if ($session === null) return;
        if ((string)$session['status'] === 'cancelled') throw new HttpException(403, 'Turno assemblea annullato.');
        $start = strtotime((string)$session['assembly_date'] . ' ' . (string)$session['time_start']);
        $end = $session['time_end'] !== null ? strtotime((string)$session['assembly_date'] . ' ' . (string)$session['time_end']) : null;
        if ($start !== false && $start > $now) throw new HttpException(403, 'Votazione non ancora aperta per questo turno.');
        if ($end !== null && $end !== false && $end < $now) throw new HttpException(403, 'Votazione chiusa per questo turno.');
    }

    private function votingSession(array $voting): ?array
    {
        if (empty($voting['assembly_id']) || empty($voting['session_id'])) return null;
        return $this->app->workersAssemblySessions->findForAssembly((int)$voting['assembly_id'], (int)$voting['session_id']);
    }

    private function findVoting(int $id): array
    {
        $voting = $this->app->votings->findById($id);
        if ($voting === null) throw new HttpException(404, 'Votazione non trovata.');
        return $voting;
    }

    private function findToken(string $token): array
    {
        $row = $this->app->votingTokens->findByToken(strtoupper(trim($token)));
        if ($row === null) throw new HttpException(404, 'Token non trovato.');
        return $row;
    }

    private function requireManager(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        if (!array_intersect($roles, ['admin', 'delegato', 'rls'])) throw new HttpException(403, 'Permesso insufficiente.');
        return $user;
    }
}
