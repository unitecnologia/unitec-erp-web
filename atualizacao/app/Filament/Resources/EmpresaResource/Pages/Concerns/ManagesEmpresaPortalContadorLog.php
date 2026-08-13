<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Models\ContadorCloudSyncLog;
use App\Models\Empresa;
use App\Support\ContadorCloud\ContadorCloudSyncService;
use App\Support\Erp\EmpresaParametros;
use Filament\Notifications\Notification;

trait ManagesEmpresaPortalContadorLog
{
    public bool $portalContadorLogModalOpen = false;

    /** @var array<int, array<string, mixed>> */
    public array $portalContadorLogRows = [];

    /** @var array{total: int, sent: int, failed: int, pending: int, skipped: int} */
    public array $portalContadorLogSummary = [
        'total' => 0,
        'sent' => 0,
        'failed' => 0,
        'pending' => 0,
        'skipped' => 0,
    ];

    public function openPortalContadorLogModal(): void
    {
        $empresa = $this->resolveEmpresaRecordForPortalContador();

        if (! $empresa) {
            Notification::make()
                ->title('Portal do Contador')
                ->body('Salve a empresa antes de consultar o log.')
                ->warning()
                ->send();

            return;
        }

        $this->portalContadorLogRows = $this->loadPortalContadorLogRows($empresa);
        $this->portalContadorLogSummary = $this->buildPortalContadorLogSummary($empresa);
        $this->portalContadorLogModalOpen = true;
    }

    public function refreshPortalContadorLog(): void
    {
        $empresa = $this->resolveEmpresaRecordForPortalContador();

        if (! $empresa) {
            return;
        }

        $this->portalContadorLogRows = $this->loadPortalContadorLogRows($empresa);
        $this->portalContadorLogSummary = $this->buildPortalContadorLogSummary($empresa);
    }

    public function atualizarPortalContador(): void
    {
        $empresa = $this->resolveEmpresaRecordForPortalContador();

        if (! $empresa) {
            Notification::make()
                ->title('Portal do Contador')
                ->body('Salve a empresa antes de atualizar.')
                ->warning()
                ->send();

            return;
        }

        $empresa->refresh();
        $this->reloadPortalContadorFormData($empresa);

        $reenviados = 0;
        $falhas = 0;

        $logs = ContadorCloudSyncLog::query()
            ->where('empresa_id', $empresa->id)
            ->where('status', ContadorCloudSyncLog::STATUS_FAILED)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $syncService = app(ContadorCloudSyncService::class);

        foreach ($logs as $log) {
            $resultado = $syncService->retry($log);

            if ($resultado->status === ContadorCloudSyncLog::STATUS_SENT) {
                $reenviados++;
            } else {
                $falhas++;
            }
        }

        if ($this->portalContadorLogModalOpen) {
            $this->refreshPortalContadorLog();
        }

        $mensagem = 'Dados do portal recarregados.';

        if ($reenviados > 0 || $falhas > 0) {
            $mensagem .= sprintf(' Reenvio: %d enviado(s), %d falha(s).', $reenviados, $falhas);
        }

        Notification::make()
            ->title('Portal do Contador')
            ->body($mensagem)
            ->success()
            ->send();
    }

    public function closePortalContadorLogModal(): void
    {
        $this->portalContadorLogModalOpen = false;
        $this->portalContadorLogRows = [];
        $this->portalContadorLogSummary = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'pending' => 0,
            'skipped' => 0,
        ];
    }

    protected function resolveEmpresaRecordForPortalContador(): ?Empresa
    {
        if (! property_exists($this, 'record') || ! $this->record instanceof Empresa) {
            return null;
        }

        if (! filled($this->record->getKey())) {
            return null;
        }

        return $this->record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function loadPortalContadorLogRows(Empresa $empresa): array
    {
        return ContadorCloudSyncLog::query()
            ->where('empresa_id', $empresa->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (ContadorCloudSyncLog $log): array => [
                'id' => $log->id,
                'data_hora' => optional($log->created_at)?->format('d/m/Y H:i:s') ?? '—',
                'tipo' => ContadorCloudSyncLog::tipoLabel((string) $log->tipo_documento),
                'evento' => ContadorCloudSyncLog::eventoLabel((string) $log->evento),
                'chave' => filled($log->chave) ? (string) $log->chave : '—',
                'status' => ContadorCloudSyncLog::statusLabel((string) $log->status),
                'status_codigo' => (string) $log->status,
                'http_status' => filled($log->http_status) ? (string) $log->http_status : '—',
                'tentativas' => (int) $log->attempts,
                'erro' => filled($log->error_message) ? (string) $log->error_message : '—',
            ])
            ->all();
    }

    /**
     * @return array{total: int, sent: int, failed: int, pending: int, skipped: int}
     */
    protected function buildPortalContadorLogSummary(Empresa $empresa): array
    {
        $counts = ContadorCloudSyncLog::query()
            ->where('empresa_id', $empresa->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'sent' => (int) ($counts[ContadorCloudSyncLog::STATUS_SENT] ?? 0),
            'failed' => (int) ($counts[ContadorCloudSyncLog::STATUS_FAILED] ?? 0),
            'pending' => (int) ($counts[ContadorCloudSyncLog::STATUS_PENDING] ?? 0),
            'skipped' => (int) ($counts[ContadorCloudSyncLog::STATUS_SKIPPED] ?? 0),
        ];
    }

    protected function reloadPortalContadorFormData(Empresa $empresa): void
    {
        foreach (array_keys(EmpresaParametros::portalContadorFields()) as $field) {
            $this->data[$field] = $empresa->getAttribute($field);
        }

        foreach (array_keys(EmpresaParametros::portalContadorBooleanFields()) as $field) {
            $this->data[$field] = (bool) $empresa->getAttribute($field);
        }
    }
}
