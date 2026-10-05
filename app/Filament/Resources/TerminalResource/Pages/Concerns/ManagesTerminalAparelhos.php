<?php

namespace App\Filament\Resources\TerminalResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\EntregasDevice;
use App\Models\ForcaVendasDevice;
use App\Models\ForcaVendasDeviceReset;
use App\Models\UnitecOsDevice;
use App\Models\VendasInternasDevice;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\License\DeviceLicenseLimitExceeded;
use App\Support\Erp\Pdv\TerminalResolver;
use App\Support\ForcaVendas\ForcaVendasDeviceResetService;
use App\Support\Gestor\GestorAprovacaoService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait ManagesTerminalAparelhos
{
    public string $aparelhoStatusFilter = 'pendentes';

    public ?string $selectedAparelhoKey = null;

    /** Aparelho aguardando confirmação do reset da base (modal aberto). */
    public ?string $resetAparelhoKey = null;

    /**
     * @return list<array<string, mixed>>
     */
    public function getAparelhosPendentesProperty(): array
    {
        return $this->aparelhosLista();
    }

    public function selectAparelho(string $key): void
    {
        $this->selectedAparelhoKey = $key;
    }

    public function updatedAparelhoStatusFilter(): void
    {
        $this->selectedAparelhoKey = null;
    }

    public function autorizarAparelhoSelecionado(): void
    {
        $item = $this->selectedAparelhoItem();

        if ($item === null) {
            Notification::make()
                ->title('Selecione um aparelho para autorizar.')
                ->warning()
                ->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'terminais.update')) {
            return;
        }

        try {
            app(GestorAprovacaoService::class)->aprovarAparelho($item['origem'], (int) $item['id']);
        } catch (DeviceLicenseLimitExceeded $e) {
            Notification::make()
                ->title('Limite de telefones atingido.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não foi possível autorizar.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->selectedAparelhoKey = null;

        Notification::make()
            ->title('Aparelho autorizado.')
            ->body('Ele já aparece em Terminais e o app pode entrar.')
            ->success()
            ->send();
    }

    public function excluirAparelhoSelecionado(): void
    {
        $item = $this->selectedAparelhoItem();

        if ($item === null) {
            Notification::make()
                ->title('Selecione um aparelho para excluir.')
                ->warning()
                ->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'terminais.update')) {
            return;
        }

        try {
            app(GestorAprovacaoService::class)->excluirAparelho($item['origem'], (int) $item['id']);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não foi possível excluir.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->selectedAparelhoKey = null;

        Notification::make()
            ->title('Aparelho excluído.')
            ->body('A vaga de telefone foi liberada. O app pode solicitar autorização de novo.')
            ->success()
            ->send();
    }

    public function abrirResetAparelhoSelecionado(): void
    {
        $item = $this->selectedAparelhoItem();

        if ($item === null) {
            Notification::make()
                ->title('Selecione um aparelho para autorizar o reset da base.')
                ->warning()
                ->send();

            return;
        }

        if (($item['origem'] ?? null) !== 'fv') {
            Notification::make()
                ->title('Reset da base disponível só para aparelhos do Força de Vendas.')
                ->warning()
                ->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'terminais.update')) {
            return;
        }

        if (! app(ForcaVendasDeviceResetService::class)->tabelaDisponivel()) {
            Notification::make()
                ->title('Reset da base indisponível.')
                ->body('Execute as migrações do sistema (php artisan migrate).')
                ->danger()
                ->send();

            return;
        }

        if (($item['reset_status'] ?? null) === ForcaVendasDeviceReset::STATUS_PENDENTE) {
            Notification::make()
                ->title('Este aparelho já tem um reset pendente.')
                ->body('Ele será executado na próxima vez que o app conectar ao servidor.')
                ->warning()
                ->send();

            return;
        }

        $this->resetAparelhoKey = $item['key'];
    }

    public function cancelarResetAparelho(): void
    {
        $this->resetAparelhoKey = null;
    }

    public function confirmarResetAparelho(): void
    {
        $key = $this->resetAparelhoKey;
        $this->resetAparelhoKey = null;

        if ($key === null || ! str_starts_with($key, 'fv:')) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'terminais.update')) {
            return;
        }

        $device = ForcaVendasDevice::query()->find((int) substr($key, 3));

        if ($device === null || blank($device->device_uuid)) {
            Notification::make()
                ->title('Aparelho não encontrado.')
                ->danger()
                ->send();

            return;
        }

        try {
            $result = app(ForcaVendasDeviceResetService::class)->autorizar($device, Auth::user());
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não foi possível autorizar o reset.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($result['criado'] ? 'Reset da base autorizado.' : 'Este aparelho já tinha um reset pendente.')
            ->body('O app apaga a base local na próxima vez que conectar ao servidor e volta para o login.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResetAparelhoConfirmProperty(): ?array
    {
        if ($this->resetAparelhoKey === null) {
            return null;
        }

        foreach ($this->aparelhosLista() as $item) {
            if (($item['key'] ?? null) === $this->resetAparelhoKey) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aparelhosLista(): array
    {
        $empresaId = (int) (TerminalResolver::make()->resolveEmpresaId() ?: ErpContext::currentEmpresaId() ?: 0);
        $items = collect();

        if (Schema::hasTable((new ForcaVendasDevice)->getTable())) {
            $items = $items->merge($this->mapDevices('fv', 'Força de Vendas', ForcaVendasDevice::query(), $empresaId));
        }

        if (Schema::hasTable((new VendasInternasDevice)->getTable())) {
            $items = $items->merge($this->mapDevices('vi', 'Vendas Internas', VendasInternasDevice::query(), $empresaId));
        }

        if (Schema::hasTable((new UnitecOsDevice)->getTable())) {
            $items = $items->merge($this->mapDevices('os', 'Unitec OS', UnitecOsDevice::query(), $empresaId));
        }

        if (Schema::hasTable((new EntregasDevice)->getTable())) {
            $items = $items->merge($this->mapDevices('ent', 'Unitec Entregas', EntregasDevice::query(), $empresaId));
        }

        return $items
            ->sortByDesc(fn (array $row): string => (string) ($row['registered_at_sort'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Collection<int, array<string, mixed>>
     */
    private function mapDevices(string $origem, string $origemLabel, $query, int $empresaId): Collection
    {
        $query = $query->with('user')->orderByDesc('id');

        if ($empresaId > 0 && Schema::hasColumn($query->getModel()->getTable(), 'empresa_id')) {
            $query->where(function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
            });
        }

        $query = match ($this->aparelhoStatusFilter) {
            'pendentes' => $query->whereNull('revoked_at')->where('status', '!=', 'aprovado'),
            'ativos' => $query->whereNull('revoked_at')->where('status', 'aprovado'),
            'revogados' => $query->whereNotNull('revoked_at'),
            default => $query,
        };

        $devices = $query->limit(200)->get();
        $empresas = $this->empresasDosAparelhos($devices);
        $resets = $origem === 'fv'
            ? app(ForcaVendasDeviceResetService::class)->resumoPorAparelho(
                $devices->pluck('device_uuid')->filter()->map(fn ($u): string => (string) $u)->values()->all(),
            )
            : collect();

        return $devices->map(function ($device) use ($origem, $origemLabel, $empresas, $resets): array {
            $empresa = $empresas->get((int) ($device->empresa_id ?? 0));
            /** @var ForcaVendasDeviceReset|null $reset */
            $reset = $resets->get((string) $device->device_uuid);

            return [
                'device_uuid' => $device->device_uuid,
                'reset_status' => $reset?->status,
                'reset_label' => match ($reset?->status) {
                    ForcaVendasDeviceReset::STATUS_PENDENTE => 'Reset pendente',
                    ForcaVendasDeviceReset::STATUS_CONCLUIDO => 'Concluído '.$reset->completed_at?->format('d/m/Y H:i'),
                    default => null,
                },
                'reset_title' => $reset !== null
                    ? 'Autorizado em '.$reset->authorized_at?->format('d/m/Y H:i')
                        .($reset->authorizer?->name ? ' por '.$reset->authorizer->name : '')
                        .' — solicitação '.$reset->uuid
                    : null,
                'key' => $origem.':'.$device->id,
                'id' => $device->id,
                'origem' => $origem,
                'origem_label' => $origemLabel,
                'device_name' => $device->device_name ?: 'Aparelho sem nome',
                'pairing_code' => $device->pairing_code,
                'platform' => $device->platform,
                'app_version' => $device->app_version,
                'vendedor' => $device->user?->name,
                'empresa' => $this->nomeEmpresaAparelho($empresa),
                'situacao' => $device->situacaoLabel(),
                'registered_at' => $device->registered_at?->format('d/m/Y H:i'),
                'registered_at_sort' => $device->registered_at?->format('Y-m-d H:i:s') ?? '',
                'last_seen_at' => $device->last_seen_at?->format('d/m/Y H:i'),
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedAparelhoItem(): ?array
    {
        if ($this->selectedAparelhoKey === null || $this->selectedAparelhoKey === '') {
            return null;
        }

        foreach ($this->aparelhosLista() as $item) {
            if (($item['key'] ?? null) === $this->selectedAparelhoKey) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, \Illuminate\Database\Eloquent\Model>  $devices
     * @return Collection<int, Empresa>
     */
    private function empresasDosAparelhos(Collection $devices): Collection
    {
        $ids = $devices
            ->pluck('empresa_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty() || ! Schema::hasTable((new Empresa)->getTable())) {
            return collect();
        }

        return Empresa::query()
            ->whereIn('id', $ids)
            ->get(['id', 'nome', 'fantasia', 'razao_social'])
            ->keyBy('id');
    }

    private function nomeEmpresaAparelho(?Empresa $empresa): ?string
    {
        if ($empresa === null) {
            return null;
        }

        $nome = trim((string) ($empresa->fantasia ?: $empresa->nome ?: $empresa->razao_social));

        return $nome !== '' ? $nome : null;
    }
}
