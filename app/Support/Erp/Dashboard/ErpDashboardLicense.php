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
        $snapshot = $service->checkCurrentEmpresa();
        $expiresAt = $snapshot->expiresAt() ?? static::localExpiresAt();
        $daysRemaining = static::daysRemaining($expiresAt);
        $status = $snapshot->status;

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

        // Valor principal: dias + data quando existir.
        $value = match (true) {
            $status === LicencaSnapshot::STATUS_BLOQUEADO => 'Bloqueada',
            $status === LicencaSnapshot::STATUS_NAO_ENCONTRADO => 'Não encontrada',
            $status === LicencaSnapshot::STATUS_SEM_CNPJ => 'Sem CNPJ',
            $daysRemaining === null => '—',
            $daysRemaining < 0 => 'Vencida',
            $daysRemaining === 0 => 'Vence hoje',
            $daysRemaining === 1 => 'Falta 1 dia',
            default => "Faltam {$daysRemaining} dias",
        };

        if ($expiresAt !== null && ! in_array($status, [
            LicencaSnapshot::STATUS_BLOQUEADO,
            LicencaSnapshot::STATUS_NAO_ENCONTRADO,
            LicencaSnapshot::STATUS_SEM_CNPJ,
        ], true)) {
            // Ex.: "Faltam 155 dias · até 31/12/2026"
            if ($daysRemaining !== null && $daysRemaining >= 0) {
                $value = ($daysRemaining === 0
                    ? 'Vence hoje'
                    : ($daysRemaining === 1 ? 'Falta 1 dia' : "Faltam {$daysRemaining} dias"))
                    .' · '.$expiresAt->format('d/m/Y');
            } elseif ($daysRemaining !== null && $daysRemaining < 0) {
                $value = 'Vencida · '.$expiresAt->format('d/m/Y');
            } else {
                $value = 'Até '.$expiresAt->format('d/m/Y');
            }
        }

        $hint = match (true) {
            $status === LicencaSnapshot::STATUS_BLOQUEADO => 'Regularize no gerenciador e clique em verificar',
            $status === LicencaSnapshot::STATUS_NAO_ENCONTRADO => 'CNPJ não cadastrado no gerenciador',
            $status === LicencaSnapshot::STATUS_SEM_CNPJ => 'Cadastre o CNPJ da empresa',
            $status === LicencaSnapshot::STATUS_ATIVO && $expiresAt !== null => 'Licença válida até '.$expiresAt->format('d/m/Y'),
            $status === LicencaSnapshot::STATUS_INDISPONIVEL && $expiresAt !== null => 'Portal offline · última validade '.$expiresAt->format('d/m/Y'),
            $status === LicencaSnapshot::STATUS_INDISPONIVEL => 'Portal indisponível no momento',
            $expiresAt !== null => 'Válida até '.$expiresAt->format('d/m/Y'),
            $daysRemaining === null => 'Data de licença não configurada',
            $daysRemaining < 0 => 'Regularize para continuar usando',
            default => 'Para vencer o sistema',
        };

        return [
            'key' => 'licenca_sistema',
            'label' => 'Licença do sistema',
            'value' => $value,
            'hint' => $hint,
            'tone' => $tone,
            'icon' => 'heroicon-o-shield-exclamation',
            'action_url' => $service->pagamentoUrl(),
            'action_label' => 'Clique aqui para renovar',
        ];
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
