<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages\Concerns;

use App\Models\FormaPagamento;
use App\Models\Person;
use App\Models\TabelaPrazo;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use Carbon\Carbon;
use Filament\Notifications\Notification;

/**
 * Fluxo Contas Receber | Parcelas no faturamento da OS — espelho do PDV
 * (crediário / cheque / boleto).
 */
trait ManagesOrdemServicoFaturamentoParcelas
{
    public ?int $osTabelaPrazoId = null;

    public ?string $osTabelaPrazoDias = null;

    public bool $osTabelaPrazoConsulta = false;

    /** @var array<int, array{documento: string, vencimento: string, valor: string, dias: int, numero_cheque?: string}> */
    public array $osParcelasRows = [];

    public string $osParcelasQtd = '1';

    public string $osParcelasIntervalo = '30';

    public ?int $osSelectedParcelaIndex = null;

    public bool $osTabelasPrazoListaAberta = false;

    /** @var array<int, array{tabela_prazo_id: int, dias: string, label: string}> */
    public array $osTabelasPrazoPredefinidas = [];

    public ?int $osSelectedTabelaPredefinidaIndex = null;

    public function getOsTabelaPrazoEmConsultaProperty(): bool
    {
        return $this->osTabelaPrazoConsulta;
    }

    public function getOsTabelaPrazoLabelProperty(): string
    {
        return filled($this->osTabelaPrazoDias) ? (string) $this->osTabelaPrazoDias : '';
    }

    public function getOsParcelasTotalLabelProperty(): string
    {
        if ($this->osParcelasRows === []) {
            return '';
        }

        $total = collect($this->osParcelasRows)->sum(
            fn (array $row): float => ErpMoney::parseBr($row['valor'] ?? '0'),
        );

        return ErpMoney::formatBr($total);
    }

    public function getOsCrediarioTotalValorProperty(): float
    {
        foreach ($this->osMeiosPagamento as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::precisaParcelasCarne($this->osPagamentoComoPdv($pagamento))) {
                continue;
            }

            $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

            if ($valor > 0) {
                return $valor;
            }
        }

        return 0.0;
    }

    public function getOsParcelasEhCrediarioProperty(): bool
    {
        return $this->osParcelasEhTipo('crediario');
    }

    public function getOsParcelasEhChequeProperty(): bool
    {
        return $this->osParcelasEhTipo('cheque');
    }

    public function getOsParcelasEhBoletoProperty(): bool
    {
        return $this->osParcelasEhTipo('boleto');
    }

    protected function osParcelasEhTipo(string $esperado): bool
    {
        foreach ($this->osMeiosPagamento as $pagamento) {
            $pdv = $this->osPagamentoComoPdv($pagamento);

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
    protected function osPagamentoComoPdv(array $pagamento): array
    {
        return [
            'forma' => (string) ($pagamento['descricao'] ?? $pagamento['forma'] ?? ''),
            'tipo' => (string) ($pagamento['tipo'] ?? ''),
            'tipo_movimento' => (string) ($pagamento['tipo_movimento'] ?? ''),
            'valor' => (string) ($pagamento['valor'] ?? '0,00'),
        ];
    }

    protected function resetOsParcelasFaturamento(): void
    {
        $this->osTabelaPrazoId = null;
        $this->osTabelaPrazoDias = null;
        $this->osTabelaPrazoConsulta = false;
        $this->osParcelasRows = [];
        $this->osParcelasQtd = '1';
        $this->osParcelasIntervalo = '30';
        $this->osSelectedParcelaIndex = null;
        $this->osTabelasPrazoListaAberta = false;
        $this->osTabelasPrazoPredefinidas = [];
        $this->osSelectedTabelaPredefinidaIndex = null;
    }

    /**
     * Crediário / cheque / boleto: abre Contas Receber | Parcelas (igual PDV).
     */
    protected function ensureOsTabelaPrazoCrediario(bool $abrirSeNecessario = true): bool
    {
        if ($this->osCrediarioTotalValor <= 0) {
            return true;
        }

        if ($this->clienteId === null) {
            Notification::make()
                ->title('Informe o cliente para pagamento a prazo.')
                ->warning()
                ->send();

            return false;
        }

        if (filled($this->osTabelaPrazoDias) && $this->osParcelasRows !== []) {
            return true;
        }

        if (! $abrirSeNecessario) {
            return false;
        }

        return $this->abrirOsParcelasCrediario();
    }

    protected function abrirOsParcelasCrediario(): bool
    {
        $this->prepararOsParcelasCrediarioForm();
        $this->osTabelasPrazoListaAberta = false;
        $this->osTabelasPrazoPredefinidas = [];
        $this->osSelectedTabelaPredefinidaIndex = null;
        $this->osTabelaPrazoConsulta = true;
        $this->osSelectedParcelaIndex = $this->osParcelasRows !== [] ? 0 : null;
        $this->dispatch('erp-os-focus-finalizar-parcelas');

        return false;
    }

    protected function prepararOsParcelasCrediarioForm(): void
    {
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
                $this->osTabelaPrazoId = (int) $tabela->id;
                $diasCliente = $parsed;
            }
        }

        $forma = $this->resolveOsFormaPagamentoCarneAtiva();
        $dias = PdvFinalizarPagamentosHelper::resolverDiasCarnePrioridade(
            $diasCliente,
            (int) ($forma?->max_parcelas ?? 0),
            (int) ($forma?->intervalo_parcelas ?? 0),
            $forma?->modo_prazo,
        );

        if ($dias !== null && $dias !== []) {
            if ($diasCliente === null) {
                $this->osTabelaPrazoId = null;
            }

            $this->osParcelasQtd = (string) count($dias);
            $this->osParcelasIntervalo = (string) $this->estimarOsIntervaloParcelas($dias);
            $this->gerarOsParcelasCrediarioPorDias($dias);

            return;
        }

        if ($this->osParcelasRows === []) {
            $this->osParcelasQtd = '1';
            $this->osParcelasIntervalo = '30';
        }
    }

    protected function resolveOsFormaPagamentoCarneAtiva(): ?FormaPagamento
    {
        foreach ($this->osMeiosPagamento as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::precisaParcelasCarne($this->osPagamentoComoPdv($pagamento))) {
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
    protected function estimarOsIntervaloParcelas(array $dias): int
    {
        if (count($dias) >= 2) {
            return max(0, (int) $dias[1] - (int) $dias[0]);
        }

        return max(0, (int) ($dias[0] ?? 30));
    }

    public function gerarOsParcelasCrediario(): void
    {
        $this->osTabelaPrazoId = null;
        $this->osTabelasPrazoListaAberta = false;

        $qtd = max(1, (int) preg_replace('/\D/', '', $this->osParcelasQtd) ?: 1);
        $intervalo = max(0, (int) preg_replace('/\D/', '', $this->osParcelasIntervalo) ?: 0);
        $this->osParcelasQtd = (string) $qtd;
        $this->osParcelasIntervalo = (string) $intervalo;

        $dias = [];

        for ($i = 1; $i <= $qtd; $i++) {
            $dias[] = $intervalo * $i;
        }

        $this->gerarOsParcelasCrediarioPorDias($dias);
        $this->osSelectedParcelaIndex = 0;
        $this->dispatch('erp-os-focus-finalizar-parcelas');
    }

    public function abrirOsTabelasPrazoPredefinidas(): void
    {
        $this->refreshOsTabelasPrazoPredefinidas();

        if ($this->osTabelasPrazoPredefinidas === []) {
            Notification::make()
                ->title('Nenhuma tabela de prazos cadastrada para Crediário.')
                ->body('Cadastre em Formas de Pagamento.')
                ->warning()
                ->send();

            return;
        }

        $this->osTabelasPrazoListaAberta = true;
        $this->osSelectedTabelaPredefinidaIndex = 0;
        $this->dispatch('erp-os-focus-finalizar-tabelas-predefinidas');
    }

    public function fecharOsTabelasPrazoPredefinidas(): void
    {
        $this->osTabelasPrazoListaAberta = false;
        $this->osSelectedTabelaPredefinidaIndex = null;
        $this->dispatch('erp-os-focus-finalizar-parcelas');
    }

    public function refreshOsTabelasPrazoPredefinidas(): void
    {
        $formaIds = FormaPagamento::query()
            ->where('ativo', true)
            ->whereIn('tipo', ['crediario', 'cheque', 'boleto'])
            ->pluck('id');

        $this->osTabelasPrazoPredefinidas = TabelaPrazo::query()
            ->when(
                $formaIds->isNotEmpty(),
                fn ($q) => $q->whereIn('forma_pagamento_id', $formaIds),
            )
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

    public function selectOsTabelaPredefinida(int $index): void
    {
        if (isset($this->osTabelasPrazoPredefinidas[$index])) {
            $this->osSelectedTabelaPredefinidaIndex = $index;
        }
    }

    public function moveOsTabelaPredefinidaSelection(int $delta): void
    {
        if ($this->osTabelasPrazoPredefinidas === []) {
            return;
        }

        $count = count($this->osTabelasPrazoPredefinidas);
        $index = ($this->osSelectedTabelaPredefinidaIndex ?? 0) + $delta;
        $this->osSelectedTabelaPredefinidaIndex = max(0, min($count - 1, $index));
    }

    public function aplicarOsTabelaPrazoPredefinida(): void
    {
        $index = $this->osSelectedTabelaPredefinidaIndex;

        if ($index === null || ! isset($this->osTabelasPrazoPredefinidas[$index])) {
            Notification::make()->title('Selecione uma tabela de prazos.')->warning()->send();

            return;
        }

        $row = $this->osTabelasPrazoPredefinidas[$index];
        $dias = PdvFinalizarPagamentosHelper::diasDeString((string) $row['dias']);

        if ($dias === []) {
            Notification::make()->title('Tabela de prazos inválida.')->warning()->send();

            return;
        }

        $this->osTabelaPrazoId = (int) $row['tabela_prazo_id'];
        $this->osParcelasQtd = (string) count($dias);
        $this->osParcelasIntervalo = (string) $this->estimarOsIntervaloParcelas($dias);
        $this->gerarOsParcelasCrediarioPorDias($dias);
        $this->osSelectedParcelaIndex = 0;
        $this->osTabelasPrazoListaAberta = false;
        $this->osSelectedTabelaPredefinidaIndex = null;
        $this->dispatch('erp-os-focus-finalizar-parcelas');
    }

    /**
     * @param  list<int>  $dias
     */
    protected function gerarOsParcelasCrediarioPorDias(array $dias): void
    {
        $total = round($this->osCrediarioTotalValor, 2);

        if ($total <= 0 || $dias === []) {
            $this->osParcelasRows = [];

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

        $this->osParcelasRows = $rows;
        $this->osParcelasQtd = (string) $n;
    }

    public function selectOsParcelaRow(int $index): void
    {
        if (isset($this->osParcelasRows[$index])) {
            $this->osSelectedParcelaIndex = $index;
        }
    }

    public function moveOsParcelaSelection(int $delta): void
    {
        if ($this->osParcelasRows === []) {
            return;
        }

        $count = count($this->osParcelasRows);
        $index = ($this->osSelectedParcelaIndex ?? 0) + $delta;
        $this->osSelectedParcelaIndex = max(0, min($count - 1, $index));
    }

    public function excluirOsParcelaCrediario(): void
    {
        $index = $this->osSelectedParcelaIndex;

        if ($index === null || ! isset($this->osParcelasRows[$index])) {
            Notification::make()->title('Selecione a parcela para excluir.')->warning()->send();

            return;
        }

        $rows = $this->osParcelasRows;
        array_splice($rows, $index, 1);

        foreach ($rows as $i => $row) {
            $rows[$i]['documento'] = (string) ($i + 1);
        }

        $this->osParcelasRows = $rows;
        $this->osParcelasQtd = (string) max(1, count($rows));
        $this->osSelectedParcelaIndex = $rows === []
            ? null
            : min($index, count($rows) - 1);
    }

    public function cancelarOsTabelaPrazoConsulta(): void
    {
        if (! $this->osTabelaPrazoConsulta) {
            return;
        }

        if ($this->osTabelasPrazoListaAberta) {
            $this->fecharOsTabelasPrazoPredefinidas();

            return;
        }

        $this->osTabelaPrazoConsulta = false;
        $this->osTabelasPrazoListaAberta = false;
        $this->osSelectedParcelaIndex = null;
    }

    public function concluirOsParcelasCrediario(): void
    {
        if ($this->osParcelasRows === []) {
            Notification::make()
                ->title('Gere as parcelas antes de concluir.')
                ->body('Informe Parcelas/Intervalo e pressione F2 | Gerar.')
                ->warning()
                ->send();

            return;
        }

        $dias = [];

        foreach ($this->osParcelasRows as $row) {
            $dias[] = max(0, (int) ($row['dias'] ?? 0));
        }

        if ($dias === []) {
            Notification::make()->title('Parcelas inválidas.')->warning()->send();

            return;
        }

        $soma = round(collect($this->osParcelasRows)->sum(
            fn (array $row): float => ErpMoney::parseBr($row['valor'] ?? '0'),
        ), 2);
        $total = round($this->osCrediarioTotalValor, 2);

        if (abs($soma - $total) > 0.01) {
            Notification::make()
                ->title('Total das parcelas diverge do valor a prazo.')
                ->body('Parcelas: R$ '.ErpMoney::formatBr($soma).' / A prazo: R$ '.ErpMoney::formatBr($total))
                ->warning()
                ->send();

            return;
        }

        $this->osTabelaPrazoDias = implode(',', $dias);
        $this->osTabelaPrazoConsulta = false;
        $this->dispatch('erp-os-focus-finalizar-faturar');
    }

    /**
     * @return list<int>|null
     */
    protected function osTabelaPrazoDiasList(): ?array
    {
        if ($this->osParcelasRows !== []) {
            return collect($this->osParcelasRows)
                ->map(fn (array $row): int => max(0, (int) ($row['dias'] ?? 0)))
                ->values()
                ->all();
        }

        if (blank($this->osTabelaPrazoDias)) {
            return null;
        }

        $dias = PdvFinalizarPagamentosHelper::diasDeString((string) $this->osTabelaPrazoDias);

        return $dias !== [] ? $dias : null;
    }

    /**
     * @return list<string>|null
     */
    protected function osParcelasChequeNumerosList(): ?array
    {
        if (! $this->osParcelasEhCheque || $this->osParcelasRows === []) {
            return null;
        }

        return collect($this->osParcelasRows)
            ->map(fn (array $row): string => trim((string) ($row['numero_cheque'] ?? '')))
            ->values()
            ->all();
    }
}
