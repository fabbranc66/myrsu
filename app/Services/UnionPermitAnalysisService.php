<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class UnionPermitAnalysisService
{
    public function analyze(array $data): array
    {
        $employees = $this->positiveInt($data, 'employees');
        $rsuCount = $this->positiveInt($data, 'rsu_count');
        $rlsCount = $this->nonNegativeInt($data, 'rls_count');
        if ($rlsCount > $rsuCount) {
            throw new HttpException(422, 'Gli RLS non possono superare i componenti RSU.');
        }
        $rsuRule = (string)($data['rsu_rule'] ?? 'metal_industry');
        $rlsRule = (string)($data['rls_rule'] ?? 'metal_industry');
        $customRlsHours = (float)($data['custom_rls_hours'] ?? 0);
        $rsu = $this->rsuHours($employees, $rsuCount, $rsuRule);
        $rlsHoursEach = $this->rlsHoursEach($employees, $rlsRule, $customRlsHours);

        return [
            'input' => [
                'employees' => $employees,
                'rsu_count' => $rsuCount,
                'rls_count' => $rlsCount,
                'rsu_rule' => $rsuRule,
                'rls_rule' => $rlsRule,
            ],
            'rsu' => $rsu + [
                'members' => $rsuCount,
                'hours_average_each' => round($rsu['annual_hours'] / $rsuCount, 2),
            ],
            'rls' => [
                'hours_each' => $rlsHoursEach,
                'hours_total' => $rlsHoursEach * $rlsCount,
                'rule_note' => $this->rlsRuleNote($rlsRule),
            ],
            'total' => [
                'annual_hours' => $rsu['annual_hours'] + ($rlsHoursEach * $rlsCount),
                'monthly_average' => round(($rsu['annual_hours'] + ($rlsHoursEach * $rlsCount)) / 12, 2),
            ],
            'notes' => [
                'RSU: i componenti subentrano ai dirigenti RSA nei diritti di cui al Titolo III L. 300/1970, salvo miglior favore CCNL.',
                'RLS: il D.Lgs. 81/2008 rinvia alla contrattazione collettiva; usare la regola applicabile al proprio settore.',
            ],
        ];
    }

    private function rsuHours(int $employees, int $rsuCount, string $rule): array
    {
        if ($rule === 'metal_industry') {
            return [
                'basis' => 'CCNL Metalmeccanici Industria Federmeccanica-Assistal: fino a 200 dipendenti 1 ora e 30 minuti annui per dipendente; oltre 200 applicazione art. 23 L.300/1970 salvo miglior favore.',
                'permission_holders' => $rsuCount,
                'annual_hours' => $employees <= 200 ? round($employees * 1.5, 2) : $rsuCount * 8 * 12,
            ];
        }

        if ($employees <= 200) {
            return [
                'basis' => 'L. 300/1970 art. 23: 1 ora annua per dipendente fino a 200 dipendenti.',
                'permission_holders' => 1,
                'annual_hours' => $employees,
            ];
        }

        $holders = $employees <= 3000
            ? (int)ceil($employees / 300)
            : 10 + (int)ceil(($employees - 3000) / 500);

        return [
            'basis' => 'L. 300/1970 art. 23: almeno 8 ore mensili per avente diritto.',
            'permission_holders' => $holders,
            'annual_hours' => $holders * 8 * 12,
        ];
    }

    private function rlsHoursEach(int $employees, string $rule, float $customHours): float
    {
        if ($rule === 'custom') {
            if ($customHours <= 0) {
                throw new HttpException(422, 'Ore RLS personalizzate obbligatorie.');
            }
            return $customHours;
        }

        if ($rule === 'metal_industry') {
            if ($employees <= 5) return 12.0;
            if ($employees <= 15) return 30.0;
            if ($employees <= 49) return 40.0;
            if ($employees <= 100) return 50.0;
            if ($employees <= 300) return 70.0;
            if ($employees <= 1000) return 72.0;
            return 76.0;
        }

        if ($rule === 'ai_2018') {
            return $employees <= 5 ? 24.0 : ($employees <= 15 ? 48.0 : 72.0);
        }

        return $employees <= 5 ? 12.0 : ($employees <= 15 ? 30.0 : 40.0);
    }

    private function rlsRuleNote(string $rule): string
    {
        return [
            'metal_industry' => 'CCNL Metalmeccanici Industria: 12/30/40/50/70/72/76 ore annue per RLS secondo fascia addetti.',
            'ai_1996' => 'Schema frequente: 12/30/40 ore annue per RLS secondo fascia addetti.',
            'ai_2018' => 'Schema accordo interconfederale sicurezza: 24/48/72 ore annue per RLS secondo fascia addetti.',
            'custom' => 'Valore manuale da CCNL/accordo aziendale applicabile.',
        ][$rule] ?? 'Schema frequente: 12/30/40 ore annue per RLS secondo fascia addetti.';
    }

    private function positiveInt(array $data, string $key): int
    {
        $value = (int)($data[$key] ?? 0);
        if ($value < 1) {
            throw new HttpException(422, 'Valore non valido: ' . $key);
        }

        return $value;
    }

    private function nonNegativeInt(array $data, string $key): int
    {
        $value = (int)($data[$key] ?? 0);
        if ($value < 0) {
            throw new HttpException(422, 'Valore non valido: ' . $key);
        }

        return $value;
    }
}
