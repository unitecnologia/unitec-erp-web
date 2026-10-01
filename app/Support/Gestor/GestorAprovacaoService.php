<?php

namespace App\Support\Gestor;

use App\Models\EntregasDevice;
use App\Models\ForcaVendasDevice;
use App\Models\ForcaVendasOrder;
use App\Models\Terminal;
use App\Models\UnitecOsDevice;
use App\Models\VendasInternasDevice;
use App\Support\Erp\ErpContext;
use App\Support\Erp\License\DeviceLicenseService;
use App\Support\ForcaVendas\ForcaVendasFaturamentoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Support\Erp\ErpSchema;
use Throwable;

/**
 * Central de aprovações do Painel Executivo.
 *
 * - Pedidos Força de Vendas em situação "financeiro" (liberar / negar)
 * - Aparelhos FV/VI aguardando autorização
 */
final class GestorAprovacaoService
{
    /**
     * @return array{
     *   total: int,
     *   pedidos: list<array<string, mixed>>,
     *   aparelhos: list<array<string, mixed>>
     * }
     */
    public function pendencias(?int $empresaId = null): array
    {
        $empresaId = $empresaId ?: app(GestorExecutivoService::class)->empresaId();
        $pedidos = $this->listarPedidosPendentes($empresaId);
        $aparelhos = $this->listarAparelhosPendentes($empresaId);

        return [
            'total' => count($pedidos) + count($aparelhos),
            'pedidos' => $pedidos,
            'aparelhos' => $aparelhos,
        ];
    }

    public function countPendencias(?int $empresaId = null): int
    {
        $empresaId = $empresaId ?: app(GestorExecutivoService::class)->empresaId();

        return $this->countPedidosPendentes($empresaId) + $this->countAparelhosPendentes($empresaId);
    }

    private function countPedidosPendentes(int $empresaId): int
    {
        try {
            if (! ErpSchema::hasTable((new ForcaVendasOrder)->getTable())) {
                return 0;
            }

            $q = ForcaVendasOrder::query()
                ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO)
                ->where('situacao', ForcaVendasOrder::SITUACAO_FINANCEIRO);

            if ($empresaId > 0 && ErpSchema::hasColumn((new ForcaVendasOrder)->getTable(), 'empresa_id')) {
                $q->where(function ($builder) use ($empresaId): void {
                    $builder->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                });
            }

            return (int) $q->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countAparelhosPendentes(int $empresaId): int
    {
        $total = 0;

        try {
            if (ErpSchema::hasTable((new ForcaVendasDevice)->getTable())) {
                $q = ForcaVendasDevice::query()
                    ->whereNull('revoked_at')
                    ->where('status', '!=', ForcaVendasDevice::STATUS_APROVADO);

                if ($empresaId > 0 && ErpSchema::hasColumn((new ForcaVendasDevice)->getTable(), 'empresa_id')) {
                    $q->where(function ($builder) use ($empresaId): void {
                        $builder->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                    });
                }

                $total += (int) $q->count();
            }
        } catch (Throwable) {
            // ignore
        }

        try {
            if (ErpSchema::hasTable((new VendasInternasDevice)->getTable())) {
                $q = VendasInternasDevice::query()
                    ->whereNull('revoked_at')
                    ->where('status', '!=', VendasInternasDevice::STATUS_APROVADO);

                if ($empresaId > 0 && ErpSchema::hasColumn((new VendasInternasDevice)->getTable(), 'empresa_id')) {
                    $q->where(function ($builder) use ($empresaId): void {
                        $builder->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                    });
                }

                $total += (int) $q->count();
            }
        } catch (Throwable) {
            // ignore
        }

        try {
            if (ErpSchema::hasTable((new UnitecOsDevice)->getTable())) {
                $q = UnitecOsDevice::query()
                    ->whereNull('revoked_at')
                    ->where('status', '!=', UnitecOsDevice::STATUS_APROVADO);

                if ($empresaId > 0 && ErpSchema::hasColumn((new UnitecOsDevice)->getTable(), 'empresa_id')) {
                    $q->where(function ($builder) use ($empresaId): void {
                        $builder->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                    });
                }

                $total += (int) $q->count();
            }
        } catch (Throwable) {
            // ignore
        }

        try {
            if (ErpSchema::hasTable((new EntregasDevice)->getTable())) {
                $q = EntregasDevice::query()
                    ->whereNull('revoked_at')
                    ->where('status', '!=', EntregasDevice::STATUS_APROVADO);

                if ($empresaId > 0 && ErpSchema::hasColumn((new EntregasDevice)->getTable(), 'empresa_id')) {
                    $q->where(function ($builder) use ($empresaId): void {
                        $builder->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                    });
                }

                $total += (int) $q->count();
            }
        } catch (Throwable) {
            // ignore
        }

        return $total;
    }

    public function aprovarPedido(int $orderId): void
    {
        $order = ForcaVendasOrder::query()
            ->whereKey($orderId)
            ->firstOrFail();

        if ($order->tipo !== ForcaVendasOrder::TIPO_PEDIDO) {
            throw new \RuntimeException('Somente pedidos podem ser liberados aqui.');
        }

        if ($order->situacao !== ForcaVendasOrder::SITUACAO_FINANCEIRO) {
            throw new \RuntimeException('Pedido não está aguardando liberação financeira.');
        }

        (new ForcaVendasFaturamentoService())->liberarFinanceiro($order, Auth::user());
    }

    public function rejeitarPedido(int $orderId): void
    {
        $order = ForcaVendasOrder::query()->whereKey($orderId)->firstOrFail();

        if ($order->situacao !== ForcaVendasOrder::SITUACAO_FINANCEIRO) {
            throw new \RuntimeException('Pedido não está aguardando liberação financeira.');
        }

        (new ForcaVendasFaturamentoService())->cancelarPendente($order);
    }

    public function aprovarAparelho(string $origem, int $deviceId): void
    {
        $device = $this->findDevice($origem, $deviceId);

        if ($device instanceof ForcaVendasDevice && $device->isApproved()) {
            throw new \RuntimeException('Aparelho já autorizado.');
        }

        if ($device instanceof VendasInternasDevice && $device->isApproved()) {
            throw new \RuntimeException('Aparelho já autorizado.');
        }

        if ($device instanceof UnitecOsDevice && $device->isApproved()) {
            throw new \RuntimeException('Aparelho já autorizado.');
        }

        if ($device instanceof EntregasDevice && $device->isApproved()) {
            throw new \RuntimeException('Aparelho já autorizado.');
        }

        $empresaId = (int) ($device->empresa_id ?: ErpContext::currentEmpresaId() ?: 0);
        $devices = app(DeviceLicenseService::class);

        $origin = match (true) {
            $device instanceof EntregasDevice => 'entregas',
            $device instanceof UnitecOsDevice => 'unitec_os',
            $device instanceof VendasInternasDevice => 'vendas_internas',
            default => 'forca_vendas',
        };

        // Unitec OS / Entregas: autoriza só na tabela de devices (aba Aparelhos).
        // Não cria terminal "Telefone" na lista Dispositivo.
        if (! in_array($origin, ['unitec_os', 'entregas'], true) && $empresaId > 0 && $devices->isAvailable()) {
            $devices->register(
                empresaId: $empresaId,
                deviceUuid: (string) $device->device_uuid,
                category: DeviceLicenseService::CATEGORY_TELEFONE,
                origin: $origin,
                deviceName: $device->device_name,
                platform: $device->platform,
            );
        }

        $device->forceFill([
            'empresa_id' => $empresaId ?: $device->empresa_id,
            'status' => $device::STATUS_APROVADO,
            'revoked_at' => null,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ])->save();
    }

    public function rejeitarAparelho(string $origem, int $deviceId): void
    {
        $device = $this->findDevice($origem, $deviceId);

        if ($device->current_token_id) {
            DB::table('personal_access_tokens')->where('id', $device->current_token_id)->delete();
        }

        $device->forceFill([
            'status' => $device::STATUS_REVOGADO,
            'current_token_id' => null,
            'revoked_at' => now(),
        ])->save();
    }

    /**
     * Remove o aparelho de vez (libera vaga de telefone na licença).
     */
    public function excluirAparelho(string $origem, int $deviceId): void
    {
        $device = $this->findDevice($origem, $deviceId);

        if ($device->current_token_id) {
            DB::table('personal_access_tokens')->where('id', $device->current_token_id)->delete();
        }

        $uuid = trim((string) ($device->device_uuid ?? ''));
        $empresaId = (int) ($device->empresa_id ?: ErpContext::currentEmpresaId() ?: 0);

        $device->delete();

        if ($uuid === '' || $empresaId < 1 || ! ErpSchema::hasColumn('terminais', 'device_uuid')) {
            return;
        }

        $query = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('device_uuid', $uuid);

        if (ErpSchema::hasColumn('terminais', 'categoria_licenca')) {
            $query->where('categoria_licenca', DeviceLicenseService::CATEGORY_TELEFONE);
        }

        $query->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarPedidosPendentes(int $empresaId): array
    {
        try {
            if (! ErpSchema::hasTable((new ForcaVendasOrder)->getTable())) {
                return [];
            }

            $q = ForcaVendasOrder::query()
                ->with(['cliente:id,nome_razao', 'vendedor:id,nome', 'orcamento:id,numero,total'])
                ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO)
                ->where('situacao', ForcaVendasOrder::SITUACAO_FINANCEIRO)
                ->orderByDesc('id')
                ->limit(40);

            if ($empresaId > 0 && ErpSchema::hasColumn((new ForcaVendasOrder)->getTable(), 'empresa_id')) {
                $q->where(function ($builder) use ($empresaId): void {
                    $builder->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                });
            }

            return $q->get()->map(function (ForcaVendasOrder $order): array {
                $payload = is_array($order->payload) ? $order->payload : [];
                $descontoPct = $this->descontoPercentual($payload, (float) $order->total);
                $abertura = $order->dataAberturaAt();
                $resumo = $order->financeiroResumo();
                $dav = $order->pedido?->numero
                    ? (string) (int) preg_replace('/\D/', '', (string) $order->pedido->numero)
                    : '#'.$order->id;

                return [
                    'id' => (int) $order->id,
                    'titulo' => 'DAV '.$dav,
                    'cliente' => $order->clienteNome(),
                    'vendedor' => (string) ($order->vendedor?->nome ?? ($payload['vendedor_nome'] ?? '—')),
                    'total' => round((float) $order->total, 2),
                    'desconto_pct' => $descontoPct,
                    'forma' => (string) ($payload['forma_pagamento'] ?? '—'),
                    'quando' => $abertura?->timezone(config('app.timezone'))->format('d/m H:i') ?? '—',
                    'identificacao' => (string) ($order->identificacao ?: ''),
                    'status' => 'Financeiro',
                    'motivos' => $resumo['motivos'],
                    'situacao' => $resumo['situacao'],
                    'motivo' => $order->motivoFinanceiro(),
                ];
            })->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listarAparelhosPendentes(int $empresaId): array
    {
        $out = [];

        try {
            if (ErpSchema::hasTable((new ForcaVendasDevice)->getTable())) {
                $q = ForcaVendasDevice::query()
                    ->whereNull('revoked_at')
                    ->where('status', '!=', ForcaVendasDevice::STATUS_APROVADO)
                    ->orderByDesc('id')
                    ->limit(20);

                if ($empresaId > 0 && ErpSchema::hasColumn((new ForcaVendasDevice)->getTable(), 'empresa_id')) {
                    $q->where(function ($builder) use ($empresaId): void {
                        $builder->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                    });
                }

                foreach ($q->get() as $device) {
                    $out[] = $this->mapDevice('fv', $device);
                }
            }
        } catch (Throwable) {
            // ignore
        }

        try {
            if (ErpSchema::hasTable((new VendasInternasDevice)->getTable())) {
                $q = VendasInternasDevice::query()
                    ->whereNull('revoked_at')
                    ->where('status', '!=', VendasInternasDevice::STATUS_APROVADO)
                    ->orderByDesc('id')
                    ->limit(20);

                if ($empresaId > 0 && ErpSchema::hasColumn((new VendasInternasDevice)->getTable(), 'empresa_id')) {
                    $q->where(function ($builder) use ($empresaId): void {
                        $builder->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                    });
                }

                foreach ($q->get() as $device) {
                    $out[] = $this->mapDevice('vi', $device);
                }
            }
        } catch (Throwable) {
            // ignore
        }

        try {
            if (ErpSchema::hasTable((new UnitecOsDevice)->getTable())) {
                $q = UnitecOsDevice::query()
                    ->whereNull('revoked_at')
                    ->where('status', '!=', UnitecOsDevice::STATUS_APROVADO)
                    ->orderByDesc('id')
                    ->limit(20);

                if ($empresaId > 0 && ErpSchema::hasColumn((new UnitecOsDevice)->getTable(), 'empresa_id')) {
                    $q->where(function ($builder) use ($empresaId): void {
                        $builder->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                    });
                }

                foreach ($q->get() as $device) {
                    $out[] = $this->mapDevice('os', $device);
                }
            }
        } catch (Throwable) {
            // ignore
        }

        try {
            if (ErpSchema::hasTable((new EntregasDevice)->getTable())) {
                $q = EntregasDevice::query()
                    ->whereNull('revoked_at')
                    ->where('status', '!=', EntregasDevice::STATUS_APROVADO)
                    ->orderByDesc('id')
                    ->limit(20);

                if ($empresaId > 0 && ErpSchema::hasColumn((new EntregasDevice)->getTable(), 'empresa_id')) {
                    $q->where(function ($builder) use ($empresaId): void {
                        $builder->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                    });
                }

                foreach ($q->get() as $device) {
                    $out[] = $this->mapDevice('ent', $device);
                }
            }
        } catch (Throwable) {
            // ignore
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function descontoPercentual(array $payload, float $total): ?float
    {
        foreach (['desconto_pct', 'pct_desconto', 'desconto_percentual'] as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return round((float) $payload[$key], 1);
            }
        }

        $descontoValor = $payload['desconto'] ?? $payload['desconto_valor'] ?? null;
        $bruto = $payload['subtotal'] ?? $payload['total_bruto'] ?? null;

        if (is_numeric($descontoValor) && is_numeric($bruto) && (float) $bruto > 0) {
            return round(((float) $descontoValor / (float) $bruto) * 100, 1);
        }

        if (is_numeric($descontoValor) && $total > 0 && (float) $descontoValor > 0) {
            $brutoCalc = $total + (float) $descontoValor;

            return $brutoCalc > 0 ? round(((float) $descontoValor / $brutoCalc) * 100, 1) : null;
        }

        return null;
    }

    private function mapDevice(string $origem, ForcaVendasDevice|VendasInternasDevice|UnitecOsDevice|EntregasDevice $device): array
    {
        $label = match ($origem) {
            'vi' => 'Vendas Internas',
            'os' => 'Unitec OS',
            'ent' => 'Unitec Entregas',
            default => 'Força de Vendas',
        };

        return [
            'id' => (int) $device->id,
            'origem' => $origem,
            'titulo' => $device->device_name ?: ('Aparelho #'.$device->id),
            'app' => $label,
            'platform' => (string) ($device->platform ?? '—'),
            'versao' => (string) ($device->app_version ?? '—'),
            'quando' => $device->registered_at?->timezone(config('app.timezone'))->format('d/m H:i')
                ?? $device->created_at?->timezone(config('app.timezone'))->format('d/m H:i')
                ?? '—',
        ];
    }

    private function findDevice(string $origem, int $deviceId): ForcaVendasDevice|VendasInternasDevice|UnitecOsDevice|EntregasDevice
    {
        return match ($origem) {
            'vi' => VendasInternasDevice::query()->whereKey($deviceId)->firstOrFail(),
            'os' => UnitecOsDevice::query()->whereKey($deviceId)->firstOrFail(),
            'ent' => EntregasDevice::query()->whereKey($deviceId)->firstOrFail(),
            default => ForcaVendasDevice::query()->whereKey($deviceId)->firstOrFail(),
        };
    }
}
