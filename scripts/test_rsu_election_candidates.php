<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Services\RsuElectionAnalysisService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require dirname(__DIR__) . '/app/Core/HttpException.php';
require dirname(__DIR__) . '/app/Services/RsuElectionAnalysisService.php';

function checkElectionCandidates(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$input = [
    'electors' => 154, 'voters' => 130, 'valid_votes' => 130,
    'blank_votes' => 0, 'null_votes' => 0, 'seats' => 3,
    'lists' => [
        ['name' => 'CGIL', 'votes' => 108, 'presentation_order' => 1, 'candidates' => []],
        ['name' => 'CISL', 'votes' => 22, 'presentation_order' => 2, 'candidates' => []],
    ],
];
$service = new RsuElectionAnalysisService();
foreach ([0, 1, 3, 4, 5] as $candidateCount) {
    foreach ($input['lists'] as $listIndex => $list) {
        $input['lists'][$listIndex]['candidates'] = [];
        for ($index = 1; $index <= $candidateCount; $index++) {
            $input['lists'][$listIndex]['candidates'][] = ['name' => 'Candidato ' . $index, 'preferences' => $index];
        }
    }
    $output = $service->analyze($input);
    foreach ($output['lists'] as $list) {
        checkElectionCandidates(count($list['candidates']) === $candidateCount, 'Numero candidati non conservato.');
        checkElectionCandidates($list['seats'] === ($list['name'] === 'CGIL' ? 2 : 1), 'Ripartizione seggi alterata.');
        if ($candidateCount > 0) {
            checkElectionCandidates($list['elected'][0]['name'] === 'Candidato ' . $candidateCount, 'Ordine delle preferenze alterato.');
        }
    }
}
foreach ([0, 1] as $listIndex) {
    $invalid = $input;
    $invalid['lists'][$listIndex]['candidates'][] = ['name' => 'Candidato 6', 'preferences' => 0];
    try {
        $service->analyze($invalid);
        throw new RuntimeException('Sei candidati non devono essere accettati.');
    } catch (HttpException $exception) {
        checkElectionCandidates($exception->status() === 422, 'Status non corretto.');
        checkElectionCandidates($exception->getMessage() === 'Massimo 5 candidati per lista.', 'Limite non indicato correttamente.');
    }
}
$input['lists'][1]['candidates'] = [['name' => 'Unico candidato CISL', 'preferences' => 22]];
$output = $service->analyze($input);
checkElectionCandidates(count($output['lists'][0]['candidates']) === 5, 'Cinque candidati CGIL non conservati.');
checkElectionCandidates($output['lists'][1]['elected'][0]['name'] === 'Unico candidato CISL', 'Candidatura unica non conservata.');
$input['lists'][0]['candidates'][] = ['name' => ' ', 'preferences' => 0];
$output = $service->analyze($input);
checkElectionCandidates(count($output['lists'][0]['candidates']) === 5, 'I campi vuoti non devono contare come candidati.');

echo "OK: fino a 5 candidati per lista, sesto rifiutato, campi vuoti e candidato unico verificati. Nessun database coinvolto.\n";
