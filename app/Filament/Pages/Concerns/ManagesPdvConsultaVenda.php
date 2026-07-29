<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PdvVenda;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use App\Support\Erp\Vendas\EstornarVendaService;
use DomainException;
use Filament\Notifications\Notification;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

trait ManagesPdvConsultaVenda
{
    public string $consultaVendaSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $consultaVendaResults = [];

    public ?int $selectedConsultaVendaIndex = null;

    /** @var array<string, mixed>|null */
    public ?array $consultaVendaDetalhe = null;

    public ?int $consultaVendaEstornoId = null;

    public ?string $consultaVendaEstornoNumero = null;

    public string $consultaVendaMotivoEstorno = '';

    public function openConsultaVendaModal(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        $this->consultaVendaSearch = '';
        $this->selectedConsultaVendaIndex = null;
        $this->consultaVendaDetalhe = null;
        $this->consultaVendaEstornoId = null;
        $this->consultaVendaEstornoNumero = null;
        $this->consultaVendaMotivoEstorno = '';
        $this->refreshConsultaVendaResults();
        $this->openPdvModal('consulta_venda');
        $this->dispatch('erp-pdv-focus-consulta-venda');
    }

    public function updatedConsultaVendaSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->consultaVendaSearch !== $upper) {
            $this->consultaVendaSearch = $upper;
        }

        $this->refreshConsultaVendaResults();
    }

    public function refreshConsultaVendaResults(): void
    {
        if (! $this->caixaSessaoId) {
            $this->consultaVendaResults = [];
            $this->selectedConsultaVendaIndex = null;
            $this->consultaVendaDetalhe = null;

            return;
        }

        $term = trim($this->consultaVendaSearch);
        $like = $term !== '' ? '%' . $term . '%' : null;

        $query = PdvVenda::query()
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('situacao', '!=', 'C')
            ->orderByDesc('numero');

        if ($like) {
            $query->where(function ($q) use ($like, $term): void {
                $q->where('numero', 'like', $like)
                    ->orWhere('vendedor_nome', 'like', $like);

                if (is_numeric($term)) {
                    $q->orWhere('numero', (int) $term);
                }
            });
        }

        $previousVendaId = $this->resolveConsultaVendaMarkedId();

        $this->consultaVendaResults = $query
            ->limit(50)
            ->get()
            ->map(fn (PdvVenda $venda): array => [
                'venda_id' => $venda->id,
                'numero' => str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT),
                'total' => ErpMoney::formatBr($venda->total),
                'forma' => $venda->forma_pagamento ?? '—',
                'situacao' => $venda->situacao ?? 'F',
            ])
            ->values()
            ->all();

        $this->selectedConsultaVendaIndex = null;

        if ($previousVendaId > 0) {
            foreach ($this->consultaVendaResults as $idx => $row) {
                if ((int) ($row['venda_id'] ?? 0) === $previousVendaId) {
                    $this->selectedConsultaVendaIndex = $idx;
                    break;
                }
            }
        }

        $this->loadConsultaVendaDetalhe();
    }

    public function selectConsultaVendaRow(int $index): void
    {
        $index = (int) $index;

        if (! isset($this->consultaVendaResults[$index])) {
            return;
        }

        $this->selectedConsultaVendaIndex = $index;
        $this->loadConsultaVendaDetalhe();
    }

    public function isConsultaVendaRowSelected(int $index): bool
    {
        return $this->selectedConsultaVendaIndex !== null
            && (int) $this->selectedConsultaVendaIndex === $index;
    }

    public function toggleMarkCurrentConsultaVendaRow(): void
    {
        if ($this->selectedConsultaVendaIndex === null) {
            return;
        }

        $this->selectConsultaVendaRow($this->selectedConsultaVendaIndex);
    }

    public function moveConsultaVendaSelection(int $delta): void
    {
        if ($this->consultaVendaResults === []) {
            return;
        }

        $count = count($this->consultaVendaResults);

        if ($this->selectedConsultaVendaIndex === null) {
            if ($delta > 0) {
                $this->selectConsultaVendaRow(0);
            } elseif ($delta < 0) {
                $this->selectConsultaVendaRow($count - 1);
            }

            return;
        }

        $index = $this->selectedConsultaVendaIndex + $delta;
        $this->selectConsultaVendaRow(max(0, min($count - 1, $index)));
    }

    protected function loadConsultaVendaDetalhe(): void
    {
        $index = $this->selectedConsultaVendaIndex;

        if ($index === null || ! isset($this->consultaVendaResults[$index])) {
            $this->consultaVendaDetalhe = null;

            return;
        }

        $vendaId = (int) ($this->consultaVendaResults[$index]['venda_id'] ?? 0);
        $venda = PdvVenda::query()->with(['itens', 'pagamentos', 'person'])->find($vendaId);

        if (! $venda) {
            $this->consultaVendaDetalhe = null;

            return;
        }

        $this->consultaVendaDetalhe = [
            'venda_id' => $venda->id,
            'numero' => str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT),
            'total' => ErpMoney::formatBr($venda->total),
            'subtotal' => ErpMoney::formatBr($venda->subtotal),
            'desconto' => ErpMoney::formatBr($venda->desconto),
            'acrescimo' => ErpMoney::formatBr($venda->acrescimo),
            'cliente' => $venda->person?->nome_razao ?? 'CONSUMIDOR FINAL',
            'forma' => $venda->forma_pagamento ?? '—',
            'itens' => $venda->itens->map(fn ($item): array => [
                'descricao' => $item->descricao,
                'quantidade' => (float) $item->quantidade,
                'total' => ErpMoney::formatBr($item->total),
            ])->all(),
            'pagamentos' => $venda->pagamentos->map(fn ($pag): array => [
                'forma' => $pag->forma,
                'valor' => ErpMoney::formatBr($pag->valor),
            ])->all(),
        ];
    }

    public function imprimirConsultaVenda(): void
    {
        $vendaId = (int) ($this->consultaVendaDetalhe['venda_id'] ?? 0);

        if ($vendaId <= 0) {
            $this->notifyPdvError('Selecione uma venda.');

            return;
        }

        $copias = $this->pdvConfig()->pedidoDuasVias() ? 2 : 1;
        $this->imprimirCupomPosVenda($vendaId, $copias);
    }

    public function requestEstornarConsultaVenda(): void
    {
        $vendaId = $this->resolveConsultaVendaMarkedId();

        if ($vendaId <= 0) {
            $this->notifyPdvError('Selecione uma venda no grid para estornar.');

            return;
        }

        if ($this->pdvConfig()->pedirAutorizacaoExcluir() && ! $this->pdvAutorizado()) {
            $this->consultaVendaEstornoId = $vendaId;
            $this->pdvAuthPendingAction = 'estornar_venda';
            $this->pdvAuthPassword = '';
            $this->openPdvModal('autorizacao');
            $this->dispatch('erp-pdv-focus-autorizacao');

            return;
        }

        $this->openEstornoVendaModal($vendaId);
    }

    public function openEstornoVendaModal(int $vendaId): void
    {
        $this->consultaVendaEstornoId = $vendaId;
        $this->consultaVendaMotivoEstorno = $this->pdvConfig()->motivoEstornoAutomatico()
            ? PdvEstornoMotivo::MOTIVO_AUTOMATICO
            : '';
        $this->consultaVendaEstornoNumero = null;

        foreach ($this->consultaVendaResults as $row) {
            if ((int) ($row['venda_id'] ?? 0) === $vendaId) {
                $this->consultaVendaEstornoNumero = (string) ($row['numero'] ?? '');
                break;
            }
        }

        $this->openPdvModal('estorno_venda');
        $this->dispatch('erp-pdv-focus-estorno-venda');
    }

    public function cancelEstornoVenda(): void
    {
        $this->consultaVendaMotivoEstorno = '';
        $this->consultaVendaEstornoId = null;
        $this->consultaVendaEstornoNumero = null;
        $this->openPdvModal('consulta_venda');
        $this->dispatch('erp-pdv-focus-consulta-venda');
    }

    public function confirmEstornarConsultaVenda(): void
    {
        $vendaId = (int) ($this->consultaVendaEstornoId ?? 0);
        $motivo = PdvEstornoMotivo::normalize($this->consultaVendaMotivoEstorno);
        $erro = PdvEstornoMotivo::validate($motivo);

        if ($erro !== null) {
            $this->notifyPdvError($erro);

            return;
        }

        if ($vendaId <= 0) {
            $this->notifyPdvError('Venda não selecionada para estorno.');

            return;
        }

        $this->estornarVenda($vendaId, $motivo);
    }

    public function estornarVenda(int $vendaId, string $motivo): void
    {
        $motivo = PdvEstornoMotivo::normalize($motivo);
        $erro = PdvEstornoMotivo::validate($motivo);

        if ($erro !== null) {
            $this->notifyPdvError($erro);

            return;
        }
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            return;
        }

        $venda = PdvVenda::query()
            ->with(['itens', 'pagamentos', 'nfce'])
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('situacao', '!=', 'C')
            ->find($vendaId);

        if (! $venda) {
            $this->notifyPdvError('Venda não encontrada ou já estornada.');

            return;
        }

        try {
            $result = (new EstornarVendaService())->fromPdvVenda(
                $venda,
                $motivo,
                EstornarVendaService::ORIGEM_PDV,
                $this->pdvConfig()->empresa(),
                (int) $this->caixaSessaoId,
                $this->pdvConfig()->bloquearCancelamentoDocFiscal(),
            );
        } catch (DomainException $exception) {
            $this->notifyPdvError($exception->getMessage());

            return;
        } catch (FiscalEngineException $exception) {
            $this->notifyPdvFiscalError($exception);

            return;
        }

        $this->clearPdvAutorizacao();
        $this->refreshConsultaVendaResults();

        $numeroVenda = str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT);

        if ($result->protocoloCancelamento !== null) {
            $this->showPdvFiscalOverlaySucessoCancelamento($vendaId, $numeroVenda, $result->protocoloCancelamento);

            return;
        }

        $this->consultaVendaEstornoId = null;
        $this->consultaVendaEstornoNumero = null;
        $this->consultaVendaMotivoEstorno = '';
        $this->openPdvModal('consulta_venda');
        $this->dispatch('erp-pdv-focus-consulta-venda');

        Notification::make()
            ->title('Venda estornada.')
            ->body('Venda #' . $numeroVenda)
            ->success()
            ->send();
    }

    public function cancelConsultaVenda(): void
    {
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function resolveConsultaVendaMarkedId(): int
    {
        $index = $this->selectedConsultaVendaIndex;

        if ($index === null || ! isset($this->consultaVendaResults[$index])) {
            return 0;
        }

        return (int) ($this->consultaVendaResults[$index]['venda_id'] ?? 0);
    }
}
