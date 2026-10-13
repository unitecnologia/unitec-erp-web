<?php

namespace App\Support\Erp\License;

use Carbon\Carbon;
use Throwable;

/**
 * Regras locais de liberação por data (tolerância pós-vencimento).
 *
 * Ex.: vencimento 10/10 → liberado até 12/10 (2 dias corridos, inclusive);
 * bloqueio a partir de 13/10.
 *
 * Com vencimento conhecido, só vale vencimento + grace_days da config — não
 * reaproveita valido_ate do portal/cache (pode trazer tolerância antiga).
 * valido_ate só entra como fallback quando não há vencimento.
 */
final class LicencaLiberacao
{
    public static function graceDays(): int
    {
        return max(0, (int) config('unitec.licenca_api.grace_days', 2));
    }

    /**
     * Último dia em que o ERP ainda libera com base nas datas.
     * Prioridade: vencimento + grace_days; senão valido_ate (fallback).
     */
    public static function liberadoAte(?string $vencimento, ?string $validoAte, ?int $graceDays = null): ?Carbon
    {
        $graceDays ??= self::graceDays();

        $venc = self::parseDate($vencimento);
        if ($venc !== null) {
            return $venc->copy()->addDays($graceDays)->startOfDay();
        }

        return self::parseDate($validoAte)?->copy()->startOfDay();
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
