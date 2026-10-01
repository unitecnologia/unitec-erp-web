<?php

namespace App\Filament\Pages\Concerns;

use App\Models\FormaPagamento;
use App\Models\Person;
use App\Models\TabelaPrazo;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use Carbon\Carbon;
use Filament\Notifications\Notification;

/**
 * Fluxo Contas Receber | Parcelas na Tela de Venda — espelho do PDV
 * (crediário / cheque / boleto).
 */
trait ManagesForcaVendasTelaVendaParcelas
{
    public ?int $fvTabelaPrazoId = null;

    public bool $fvTabelaPrazoConsulta = false;

    /** @var array<int, array{documento: string, vencimento: string, valor: string, dias: int, numero_cheque?: string}> */
    public array $fvParcelasRows = [];

    public string $fvParcelasQtd = '1';

    public string $fvParcelasIntervalo = '30';

    public ?int $fvSelectedParcelaIndex = null;

    public bool $fvTabelasPrazoListaAberta = false;

    /** @var array<int, array{tabela_prazo_id: int, dias: string, label: string}> */
    public array $fvTabelasPrazoPredefinidas = [];

    public ?int $fvSelectedTabelaPredefinidaIndex = null;

    public function getFvTabelaPrazoEmConsultaProperty(): bool
    {
        return $this->fvTabelaPrazoConsulta;
    }

    public function getFvTabelaPrazoLabelProperty(): string
    {
        return filled($this->tabelaPrazoDias) ? (string) $this->tabelaPrazoDias : '';
    }

    public function getFvParcelasTotalLabelProperty(): string
    {
        if ($this->fvParcelasRows === []) {
            return '';
        }

        $total = collect($this->fvParcelasRows)->sum(
            fn (array $row): float => ErpMoney::parseBr($row['valor'] ?? '0'),
        );

        return ErpMoney::formatBr($total);
    }

    public function getFvCrediarioTotalValorProperty(): float
    {
        foreach ($this->meiosPagamento as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::precisaParcelasCarne($this->fvPagamentoComoPdv($pagamento))) {
                continue;
            }

            $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

            if ($valor > 0) {
                return $valor;
            }
        }

        return 0.0;
    }

    public function getFvParcelasEhCrediarioProperty(): bool
    {
        return $this->fvParcelasEhTipo('crediario');
    }

    public function getFvParcelasEhChequeProperty(): bool
    {
        return $this->fvParcelasEhTipo('cheque');
    }

    public function getFvParcelasEhBoletoProperty(): bool
    {
        return $this->fvParcelasEhTipo('boleto');
    }

    protected function fvParcelasEhTipo(string $esperado): bool
    {
        foreach ($this->meiosPagamento as $pagamento) {
            $pdv = $this->fvPagamentoComoPdv($pagamento);

            if (! PdvFinalizarPagamentosHelper::precisaParcelasCarne($pdv)) {
                continue;
            }

            if (ErpMoney::parseBr($pagamento['valor'] ?? '0') <= 0) {
                continue;
            }

            $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');
            $forma = (string) ($pagamento['descricao'] ?? $pagamento['forma'] ?? '');

            return match ($esperado) {
                'crediario' => $tipo === 'crediario' || PdvFinalizarPagamentosHelper::isFormaCrediario($forma),
                'cheque' => $tipo === 'cheque' || PdvFinalizarPagamentosHelper::isFormaCheque($forma),
                'boleto' => $tipo === 'boleto' || PdvFinalizarPagamentosHelper::isFormaBoleto($forma),
                default => false,
            };
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $pagamento
     * @return array{forma: string, tipo: string, tipo_movimento: string, valor: string}
     */
    protected function fvPagamentoComoPdv(array $pagamento): array
    {
        return [
            'forma' => (string) ($pagamento['descricao'] ?? $pagamento['forma'] ?? ''),
            'tipo' => (string) ($pagamento['tipo'] ?? ''),
            'tipo_movimento' => (string) ($pagamento['tipo_movimento'] ?? ''),
            'valor' => (string) ($pagamento['valor'] ?? '0,00'),
        ];
    }

    protected function resetFvParcelasFaturamento(): void
    {
        $this->fvTabelaPrazoId = null;
        $this->fvTabelaPrazoConsulta = false;
        $this->fvParcelasRows = [];
        $this->fvParcelasQtd = '1';
        $this->fvParcelasIntervalo = '30';
        $this->fvSelectedParcelaIndex = null;
        $this->fvTabelasPrazoListaAberta = false;
        $this->fvTabelasPrazoPredefinidas = [];
        $this->fvSelectedTabelaPredefinidaIndex = null;
    }

    /**
     * Crediário / cheque / boleto: abre Contas Receber | Parcelas (igual PDV).
     */
    protected function ensureFvTabelaPrazoCrediario(bool $abrirSeNecessario = true): bool
    {
        if ($this->fvCrediarioTotalValor <= 0) {
            return true;
        }

        if ($this->clienteId === null) {
            Notification::make()
                ->title('Informe o cliente para pagamento a prazo.')
                ->warning()
                ->send();

            return false;
        }

        if (filled($this->tabelaPrazoDias) && $this->fvParcelasRows !== []) {
            return true;
        }

        if (! $abrirSeNecessario) {
            return false;
        }

        return $this->abrirFvParcelasCrediario();
    }

    protected function abrirFvParcelasCrediario(): bool
    {
        $this->prepararFvParcelasCrediarioForm();
        $this->fvTabelasPrazoListaAberta = false;
        $this->fvTabelasPrazoPredefinidas = [];
        $this->fvSelectedTabelaPredefinidaIndex = null;
        $this->fvTabelaPrazoConsulta = true;
        $this->fvSelectedParcelaIndex = $this->fvParcelasRows !== [] ? 0 : null;
        $this->dispatch('erp-fv-focus-finalizar-parcelas');

        return false;
    }

    protected function prepararFvParcelasCrediarioForm(): void
    {
        // 1º Prazo já negociado neste pedido (app / edição) — preserva decisão anterior.
        if (filled($this->tabelaPrazoDias)) {
            $diasPedido = PdvFinalizarPagamentosHelper::diasDeString((string) $this->tabelaPrazoDias);

            if ($diasPedido !== []) {
                $this->fvParcelasQtd = (string) count($diasPedido);
                $this->fvParcelasIntervalo = (string) $this->estimarFvIntervaloParcelas($diasPedido);
                $this->gerarFvParcelasCrediarioPorDias($diasPedido);

                return;
            }
        }

        // 2º Tabela fixa do cliente → 3º prazo financeiro da forma → 4º F2/F8.
        $cliente = $this->clienteId
            ? Person::query()->find($this->clienteId)
            : null;

        $tabela = $cliente?->tabela_prazo_id
            ? TabelaPrazo::query()->find($cliente->tabela_prazo_id)
            : null;

        $diasCliente = null;

        if ($tabela && filled($tabela->dias)) {
            $parsed = PdvFinalizarPagamentosHelper::diasDeString((string) $tabela->dias);

            if ($parsed !== []) {
                $this->fvTabelaPrazoId = (int) $tabela->id;
                $diasCliente = $parsed;
            }
        }

        $forma = $this->resolveFvFormaPagamentoCarneAtiva();
        $dias = PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade(
            $diasCliente,
            (int) ($forma?->max_parcelas ?? 0),
            (int) ($forma?->intervalo_parcelas ?? 0),
            $forma?->modo_prazo,
        );

        if ($dias !== null && $dias !== []) {
            if ($diasCliente === null) {
                $this->fvTabelaPrazoId = null;
            }

            $this->fvParcelasQtd = (string) count($dias);
            $this->fvParcelasIntervalo = (string) $this->estimarFvIntervaloParcelas($dias);
            $this->gerarFvParcelasCrediarioPorDias($dias);

            return;
        }

        if ($this->fvParcelasRows === []) {
            $this->fvParcelasQtd = '1';
            $this->fvParcelasIntervalo = '30';
        }
    }

    protected function resolveFvFormaPagamentoCarneAtiva(): ?FormaPagamento
    {
        foreach ($this->meiosPagamento as $pagamento) {
            $pdv = $this->fvPagamentoComoPdv($pagamento);

            if (! PdvFinalizarPagamentosHelper::precisaParcelasCarne($pdv)) {
                continue;
            }

            if (ErpMoney::parseBr($pagamento['valor'] ?? '0') <= 0) {
                continue;
            }

            $id = (int) ($pagamento['id'] ?? 0);

            if ($id > 0) {
                return FormaPagamento::query()->find($id);
            }

            $descricao = mb_strtoupper(trim((string) ($pagamento['descricao'] ?? $pagamento['forma'] ?? '')), 'UTF-8');

            if ($descricao === '') {
                return null;
            }

            return FormaPagamento::query()
                ->whereRaw('UPPER(descricao) = ?', [$descricao])
                ->orderByDesc('ativo')
                ->first();
        }

        return null;
    }

    /**
     * @param  list<int>  $dias
     */
    protected function estimarFvIntervaloParcelas(array $dias): int
    {
        if (count($dias) >= 2) {
            return max(0, (int) $dias[1] - (int) $dias[0]);
        }

        return max(0, (int) ($dias[0] ?? 30));
    }

    /**
     * Financeiro ou tabela fixa do cliente: o operador não altera o prazo.
     */
    public function fvPrazoCarneTravado(): bool
    {
        if ($this->fvClienteTemTabelaPrazoFixa()) {
            return true;
        }

        $forma = $this->resolveFvFormaPagamentoCarneAtiva();
        $modo = mb_strtolower(trim((string) ($forma?->modo_prazo ?? '')), 'UTF-8');

        return $modo === FormaPagamento::MODO_PRAZO_FINANCEIRO;
    }

    /**
     * Lista de tabelas só no modo tabela, sem tabela fixa no cliente.
     */
    public function fvPodeEscolherTabelaPrazo(): bool
    {
        if ($this->fvPrazoCarneTravado()) {
            return false;
        }

        $forma = $this->resolveFvFormaPagamentoCarneAtiva();
        $modo = mb_strtolower(trim((string) ($forma?->modo_prazo ?? '')), 'UTF-8');

        return $modo === FormaPagamento::MODO_PRAZO_TABELA;
    }

    protected function fvClienteTemTabelaPrazoFixa(): bool
    {
        $clienteId = (int) ($this->clienteId ?? 0);

        if ($clienteId <= 0) {
            return false;
        }

        return Person::query()
            ->whereKey($clienteId)
            ->whereNotNull('tabela_prazo_id')
            ->exists();
    }

    public function gerarFvParcelasCrediario(): void
    {
        if ($this->fvPrazoCarneTravado()) {
            return;
        }

        $this->fvTabelaPrazoId = null;
        $this->fvTabelasPrazoListaAberta = false;

        $qtd = max(1, (int) preg_replace('/\D/', '', $this->fvParcelasQtd) ?: 1);
        $intervalo = max(0, (int) preg_replace('/\D/', '', $this->fvParcelasIntervalo) ?: 0);
        $this->fvParcelasQtd = (string) $qtd;
        $this->fvParcelasIntervalo = (string) $intervalo;

        $dias = [];

        for ($i = 1; $i <= $qtd; $i++) {
            $dias[] = $intervalo * $i;
        }

        $this->gerarFvParcelasCrediarioPorDias($dias);
        $this->fvSelectedParcelaIndex = 0;
        $this->dispatch('erp-fv-focus-finalizar-parcelas');
    }

    public function abrirFvTabelasPrazoPredefinidas(): void
    {
        if (! $this->fvPodeEscolherTabelaPrazo()) {
            return;
        }

        $this->refreshFvTabelasPrazoPredefinidas();

        if ($this->fvTabelasPrazoPredefinidas === []) {
            Notification::make()
                ->title('Nenhuma tabela de prazos cadastrada para Crediário.')
                ->body('Cadastre em Formas de Pagamento.')
                ->warning()
                ->send();

            return;
        }

        $this->fvTabelasPrazoListaAberta = true;
        $this->fvSelectedTabelaPredefinidaIndex = 0;
        $this->dispatch('erp-fv-focus-finalizar-tabelas-predefinidas');
    }

    public function fecharFvTabelasPrazoPredefinidas(): void
    {
        $this->fvTabelasPrazoListaAberta = false;
        $this->fvSelectedTabelaPredefinidaIndex = null;
        $this->dispatch('erp-fv-focus-finalizar-parcelas');
    }

    public function refreshFvTabelasPrazoPredefinidas(): void
    {
        $formaId = (int) ($this->resolveFvFormaPagamentoCarneAtiva()?->id ?? 0);

        $this->fvTabelasPrazoPredefinidas = $formaId <= 0
            ? []
            : TabelaPrazo::query()
                ->where('forma_pagamento_id', $formaId)
                ->orderBy('ordem')
                ->orderBy('id')
                ->get(['id', 'dias', 'ordem'])
                ->map(fn (TabelaPrazo $tabela): array => [
                    'tabela_prazo_id' => (int) $tabela->id,
                    'dias' => (string) $tabela->dias,
                    'label' => (string) $tabela->dias,
                ])
                ->values()
                ->all();
    }

    public function selectFvTabelaPredefinida(int $index): void
    {
        if (isset($this->fvTabelasPrazoPredefinidas[$index])) {
            $this->fvSelectedTabelaPredefinidaIndex = $index;
        }
    }

    public function moveFvTabelaPredefinidaSelection(int $delta): void
    {
        if ($this->fvTabelasPrazoPredefinidas === []) {
            return;
        }

        $count = count($this->fvTabelasPrazoPredefinidas);
        $index = ($this->fvSelectedTabelaPredefinidaIndex ?? 0) + $delta;
        $this->fvSelectedTabelaPredefinidaIndex = max(0, min($count - 1, $index));
    }

    public function aplicarFvTabelaPrazoPredefinida(): void
    {
        if (! $this->fvPodeEscolherTabelaPrazo()) {
            return;
        }

        $index = $this->fvSelectedTabelaPredefinidaIndex;

        if ($index === null || ! isset($this->fvTabelasPrazoPredefinidas[$index])) {
            Notification::make()->title('Selecione uma tabela de prazos.')->warning()->send();

            return;
        }

        $row = $this->fvTabelasPrazoPredefinidas[$index];
        $dias = PdvFinalizarPagamentosHelper::diasDeString((string) $row['dias']);

        if ($dias === []) {
            Notification::make()->title('Tabela de prazos inválida.')->warning()->send();

            return;
        }

        $this->fvTabelaPrazoId = (int) $row['tabela_prazo_id'];
        $this->fvParcelasQtd = (string) count($dias);
        $this->fvParcelasIntervalo = (string) $this->estimarFvIntervaloParcelas($dias);
        $this->gerarFvParcelasCrediarioPorDias($dias);
        $this->fvSelectedParcelaIndex = 0;
        $this->fvTabelasPrazoListaAberta = false;
        $this->fvSelectedTabelaPredefinidaIndex = null;
        $this->dispatch('erp-fv-focus-finalizar-parcelas');
    }

    /**
     * @param  list<int>  $dias
     */
    protected function gerarFvParcelasCrediarioPorDias(array $dias): void
    {
        $total = round($this->fvCrediarioTotalValor, 2);

        if ($total <= 0 || $dias === []) {
            $this->fvParcelasRows = [];

            return;
        }

        $n = count($dias);
        $base = floor($total / $n * 100) / 100;
        $hoje = Carbon::today();
        $rows = [];

        foreach (array_values($dias) as $i => $dia) {
            $valor = $i === $n - 1
                ? round($total - $base * ($n - 1), 2)
                : $base;

            $diaInt = max(0, (int) $dia);
            $venc = $hoje->copy()->addDays($diaInt);

            $rows[] = [
                'documento' => (string) ($i + 1),
                'vencimento' => $venc->format('d/m/Y'),
                'valor' => ErpMoney::formatBr($valor),
                'dias' => $diaInt,
                'numero_cheque' => '',
            ];
        }

        $this->fvParcelasRows = $rows;
        $this->fvParcelasQtd = (string) $n;
    }

    public function selectFvParcelaRow(int $index): void
    {
        if (isset($this->fvParcelasRows[$index])) {
            $this->fvSelectedParcelaIndex = $index;
        }
    }

    public function moveFvParcelaSelection(int $delta): void
    {
        if ($this->fvParcelasRows === []) {
            return;
        }

        $count = count($this->fvParcelasRows);
        $index = ($this->fvSelectedParcelaIndex ?? 0) + $delta;
        $this->fvSelectedParcelaIndex = max(0, min($count - 1, $index));
    }

    public function excluirFvParcelaCrediario(): void
    {
        if ($this->fvPrazoCarneTravado()) {
            return;
        }

        $index = $this->fvSelectedParcelaIndex;

        if ($index === null || ! isset($this->fvParcelasRows[$index])) {
            Notification::make()->title('Selecione a parcela para excluir.')->warning()->send();

            return;
        }

        $rows = $this->fvParcelasRows;
        array_splice($rows, $index, 1);

        foreach ($rows as $i => $row) {
            $rows[$i]['documento'] = (string) ($i + 1);
        }

        $this->fvParcelasRows = $rows;
        $this->fvParcelasQtd = (string) max(1, count($rows));
        $this->fvSelectedParcelaIndex = $rows === []
            ? null
            : min($index, count($rows) - 1);
    }

    public function cancelarFvTabelaPrazoConsulta(): void
    {
        if (! $this->fvTabelaPrazoConsulta) {
            return;
        }

        if ($this->fvTabelasPrazoListaAberta) {
            $this->fecharFvTabelasPrazoPredefinidas();

            return;
        }

        $this->fvTabelaPrazoConsulta = false;
        $this->fvTabelasPrazoListaAberta = false;
        $this->fvSelectedParcelaIndex = null;
        $this->dispatch('erp-fv-focus-pagamento', index: $this->selectedPagamentoIndex ?? 0);
    }

    public function concluirFvParcelasCrediario(): void
    {
        if ($this->fvParcelasRows === []) {
            Notification::make()
                ->title('Gere as parcelas antes de concluir.')
                ->body('Informe Parcelas/Intervalo e pressione F2 | Gerar.')
                ->warning()
                ->send();

            return;
        }

        $dias = [];

        foreach ($this->fvParcelasRows as $row) {
            $dias[] = max(0, (int) ($row['dias'] ?? 0));
        }

        if ($dias === []) {
            Notification::make()->title('Parcelas inválidas.')->warning()->send();

            return;
        }

        $soma = round(collect($this->fvParcelasRows)->sum(
            fn (array $row): float => ErpMoney::parseBr($row['valor'] ?? '0'),
        ), 2);
        $total = round($this->fvCrediarioTotalValor, 2);

        if (abs($soma - $total) > 0.01) {
            Notification::make()
                ->title('Total das parcelas diverge do valor a prazo.')
                ->body('Parcelas: R$ '.ErpMoney::formatBr($soma).' / A prazo: R$ '.ErpMoney::formatBr($total))
                ->warning()
                ->send();

            return;
        }

        $this->tabelaPrazoDias = implode(',', $dias);
        $this->fvTabelaPrazoConsulta = false;
        $this->dispatch('erp-fv-focus-finalizar-ok');
    }
}
