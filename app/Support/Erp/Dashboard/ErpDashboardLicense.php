<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\License\LicencaRemotaService;
use App\Support\Erp\License\LicencaSnapshot;
use Carbon\Carbon;
use Throwable;

class ErpDashboardLicense
{
    /**
     * @return array<string, mixed>
     */
    public static function kpi(): array
    {
        $service = app(LicencaRemotaService::class);
        // Só sessão/cache — nunca HTTP no painel.
        $snapshot = $service->loginGateSnapshot();

        if ($snapshot === null) {
            $snapshot = $service->ensureLoginGateWithoutRemote();
        }

        $status = $snapshot->status;

        // Preferência: vencimento da mensalidade (pagamento). Fallback: valido_ate do contrato.
        $expiresAt = static::mensalidadeDueAt($service) ?? $snapshot->expiresAt() ?? static::localExpiresAt();
        $usingMensalidade = static::mensalidadeDueAt($service) !== null;
        $daysRemaining = static::daysRemaining($expiresAt);

        $tone = match (true) {
            $status === LicencaSnapshot::STATUS_BLOQUEADO,
            $status === LicencaSnapshot::STATUS_NAO_ENCONTRADO,
            $status === LicencaSnapshot::STATUS_SEM_CNPJ => 'red',
            $status === LicencaSnapshot::STATUS_INDISPONIVEL && $daysRemaining === null => 'slate',
            $daysRemaining === null => 'slate',
            $daysRemaining < 0 => 'red',
            $daysRemaining <= 7 => 'red',
            $daysRemaining <= 20 => 'orange',
            default => 'amber',
        };

        $value = match (true) {
            $status === LicencaSnapshot::STATUS_BLOQUEADO => 'Bloqueada',
            $status === LicencaSnapshot::STATUS_NAO_ENCONTRADO => 'Não encontrada',
            $status === LicencaSnapshot::STATUS_SEM_CNPJ => 'Sem CNPJ',
            $daysRemaining === null => '—',
            $daysRemaining < 0 => 'Vencida',
            $daysRemaining === 0 => 'Vence hoje',
            $daysRemaining === 1 => '1 dia',
            default => "{$daysRemaining} dias",
        };

        if ($expiresAt !== null && ! in_array($status, [
            LicencaSnapshot::STATUS_BLOQUEADO,
            LicencaSnapshot::STATUS_NAO_ENCONTRADO,
            LicencaSnapshot::STATUS_SEM_CNPJ,
        ], true)) {
            if ($daysRemaining !== null && $daysRemaining >= 0) {
                $value = ($daysRemaining === 0
                    ? 'Hoje'
                    : ($daysRemaining === 1 ? '1 dia' : "{$daysRemaining} dias"))
                    .' · '.$expiresAt->format('d/m/Y');
            } elseif ($daysRemaining !== null && $daysRemaining < 0) {
                $value = 'Vencida · '.$expiresAt->format('d/m/Y');
            } else {
                $value = $expiresAt->format('d/m/Y');
            }
        }

        $hint = match (true) {
            $status === LicencaSnapshot::STATUS_BLOQUEADO => 'Regularize no portal',
            $status === LicencaSnapshot::STATUS_NAO_ENCONTRADO => 'CNPJ não cadastrado',
            $status === LicencaSnapshot::STATUS_SEM_CNPJ => 'Cadastre o CNPJ',
            $usingMensalidade && $daysRemaining !== null && $daysRemaining < 0 => 'Mensalidade vencida',
            $usingMensalidade => 'Mensalidade',
            $status === LicencaSnapshot::STATUS_INDISPONIVEL => 'Portal offline',
            $daysRemaining === null => 'Sem data',
            $daysRemaining < 0 => 'Regularize',
            default => 'Licença',
        };

        return [
            'key' => 'licenca_sistema',
            'label' => 'Licença',
            'value' => $value,
            'hint' => $hint,
            'tone' => $tone,
            'icon' => 'heroicon-o-shield-exclamation',
            'action_url' => $service->pagamentoUrl(),
            'action_label' => 'Renovar',
        ];
    }

    private static function mensalidadeDueAt(LicencaRemotaService $service): ?Carbon
    {
        $raw = trim((string) ($service->loginGateMensalidadeDueDate() ?? ''));

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private static function localExpiresAt(): ?Carbon
    {
        $raw = trim((string) config('unitec.licenca', ''));

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $raw)->startOfDay();
        } catch (Throwable) {
            try {
                return Carbon::parse($raw)->startOfDay();
            } catch (Throwable) {
                return null;
            }
        }
    }

    private static function daysRemaining(?Carbon $expiresAt): ?int
    {
        if ($expiresAt === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($expiresAt, false);
    }
}
