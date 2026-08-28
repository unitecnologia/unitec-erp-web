<?php

namespace App\Filament\Resources\TerminalResource\Pages\Concerns;

use App\Models\ForcaVendasDevice;
use App\Models\Terminal;
use App\Models\VendasInternasDevice;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\License\DeviceLicenseLimitExceeded;
use App\Support\Erp\Pdv\TerminalResolver;
use App\Support\Gestor\GestorAprovacaoService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait ManagesTerminalAparelhos
{
    public string $aparelhoStatusFilter = 'pendentes';

    public ?string $selectedAparelhoKey = null;

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

    public function revogarAparelhoSelecionado(): void
    {
        $item = $this->selectedAparelhoItem();

        if ($item === null) {
            Notification::make()
                ->title('Selecione um aparelho para revogar.')
                ->warning()
                ->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'terminais.update')) {
            return;
        }

        try {
            app(GestorAprovacaoService::class)->rejeitarAparelho($item['origem'], (int) $item['id']);
            $this->desativarTerminalDoAparelho($item);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não foi possível revogar.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->selectedAparelhoKey = null;

        Notification::make()
            ->title('Aparelho revogado.')
            ->success()
            ->send();
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

        return $query->limit(200)->get()->map(function ($device) use ($origem, $origemLabel): array {
            return [
                'key' => $origem.':'.$device->id,
                'id' => $device->id,
                'origem' => $origem,
                'origem_label' => $origemLabel,
                'device_name' => $device->device_name ?: 'Aparelho sem nome',
                'pairing_code' => $device->pairing_code,
                'platform' => $device->platform,
                'vendedor' => $device->user?->name,
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
     * @param  array<string, mixed>  $item
     */
    private function desativarTerminalDoAparelho(array $item): void
    {
        if (! Schema::hasColumn('terminais', 'device_uuid')) {
            return;
        }

        $device = $item['origem'] === 'vi'
            ? VendasInternasDevice::query()->find($item['id'])
            : ForcaVendasDevice::query()->find($item['id']);

        $uuid = trim((string) ($device?->device_uuid ?? ''));
        $empresaId = (int) ($device?->empresa_id ?: ErpContext::currentEmpresaId() ?: 0);

        if ($uuid === '' || $empresaId < 1) {
            return;
        }

        Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('device_uuid', $uuid)
            ->update(['ativo' => false]);
    }
}
