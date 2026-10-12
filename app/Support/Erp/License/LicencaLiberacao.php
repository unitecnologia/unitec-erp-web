<?php

namespace App\Support\Erp\License;

use Carbon\Carbon;
use Throwable;

/**
 * Regras locais de liberação por data (tolerância pós-vencimento + valido_ate).
 *
 * Ex.: vencimento 10/10 → liberado até 15/10 (5 dias corridos, inclusive);
 * bloqueio a partir de 16/10. valido_ate pode estender essa janela.
 */
final class LicencaLiberacao
{
    public static function graceDays(): int
    {
        return max(0, (int) config('unitec.licenca_api.grace_days', 5));
    }

    /**
     * Último dia em que o ERP ainda libera com base nas datas
     * (max de vencimento+grace e valido_ate).
     */
    public static function liberadoAte(?string $vencimento, ?string $validoAte, ?int $graceDays = null): ?Carbon
    {
        $graceDays ??= self::graceDays();
        $candidates = [];

        $venc = self::parseDate($vencimento);
        if ($venc !== null) {
            $candidates[] = $venc->copy()->addDays($graceDays);
        }

        $valido = self::parseDate($validoAte);
        if ($valido !== null) {
            $candidates[] = $valido;
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (Carbon $a, Carbon $b): int => $b->timestamp <=> $a->timestamp);

        return $candidates[0]->copy()->startOfDay();
    }

    /**
     * True quando as datas locais já passaram da janela de liberação.
     * Sem datas → não bloqueia por este critério.
     */
    public static function bloqueadoPorData(?string $vencimento, ?string $validoAte, ?Carbon $hoje = null, ?int $graceDays = null): bool
    {
        $liberadoAte = self::liberadoAte($vencimento, $validoAte, $graceDays);

        if ($liberadoAte === null) {
            return false;
        }

        $hoje = ($hoje ?? now())->copy()->startOfDay();

        return $hoje->gt($liberadoAte);
    }

    public static function parseDate(?string $raw): ?Carbon
    {
        $raw = trim((string) $raw);

        if ($raw === '' || strtolower($raw) === 'null') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (Throwable) {
            try {
                return Carbon::createFromFormat('d/m/Y', $raw)->startOfDay();
            } catch (Throwable) {
                return null;
            }
        }
    }

    /**
     * Aviso amigável no início da sessão (não bloqueia).
     * Antes do vencimento: só em 2 dias, amanhã ou hoje.
     * Depois: durante a tolerância (venceu há X dias).
     *
     * @return array{title: string, body: ?string, level: string}|null
     */
    public static function avisoStartup(?string $vencimento, ?string $validoAte = null, ?Carbon $hoje = null, ?int $graceDays = null): ?array
    {
        $referencia = self::parseDate($vencimento) ?? self::parseDate($validoAte);

        if ($referencia === null) {
            return null;
        }

        $hoje = ($hoje ?? now())->copy()->startOfDay();
        $liberadoAte = self::liberadoAte($vencimento, $validoAte, $graceDays);

        // Já fora da tolerância: a tela de bloqueio cuida disso.
        if ($liberadoAte !== null && $hoje->gt($liberadoAte)) {
            return null;
        }

        $dias = (int) $hoje->diffInDays($referencia, false);

        // Antes do vencimento: só nos 2 últimos dias (2 dias / amanhã / hoje).
        if ($dias > 2) {
            return null;
        }

        $title = match (true) {
            $dias === 2 => 'Seu sistema vence em 2 dias.',
            $dias === 1 => 'Seu sistema vence amanhã.',
            $dias === 0 => 'Seu sistema vence hoje.',
            $dias === -1 => 'Seu sistema venceu há 1 dia.',
            default => 'Seu sistema venceu há '.abs($dias).' dias.',
        };

        $body = null;

        if ($dias < 0 && $liberadoAte !== null) {
            $restantes = (int) $hoje->diffInDays($liberadoAte, false);

            if ($restantes > 1) {
                $body = 'Ainda liberado até '.$liberadoAte->format('d/m/Y')
                    ." ({$restantes} dias de tolerância). Regularize o pagamento para evitar o bloqueio.";
            } elseif ($restantes === 1) {
                $body = 'Ainda liberado até '.$liberadoAte->format('d/m/Y')
                    .' (1 dia de tolerância). Regularize o pagamento para evitar o bloqueio.';
            } elseif ($restantes === 0) {
                $body = 'Último dia de tolerância (até '.$liberadoAte->format('d/m/Y')
                    .'). Regularize o pagamento para evitar o bloqueio.';
            }
        } else {
            $body = 'Regularize o pagamento para evitar interrupção do sistema.';
        }

        return [
            'title' => $title,
            'body' => $body,
            'level' => 'warning',
        ];
    }
}
