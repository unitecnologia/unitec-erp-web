<?php

namespace App\Filament\Pages\Concerns;

use App\Models\FormaPagamento;
use App\Models\Person;
use App\Models\TabelaPrazo;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use Carbon\Carbon;

trait ManagesPdvFinalizarTabelaPrazo
{
    public ?int $finalizarTabelaPrazoId = null;

    public ?string $finalizarTabelaPrazoDias = null;

    /** Modal Contas Receber | Parcelas aberto. */
    public bool $finalizarTabelaPrazoConsulta = false;

    /** @var array<int, array{documento: string, vencimento: string, valor: string, dias: int}> */
    public array $finalizarParcelasRows = [];

    public string $finalizarParcelasQtd = '1';

    public string $finalizarParcelasIntervalo = '30';

    public ?int $selectedFinalizarParcelaIndex = null;

    /** Lista de prazos pré-definidos (cadastro Formas de Pagamento). */
    public bool $finalizarTabelasPrazoListaAberta = false;

    /** @var array<int, array{tabela_prazo_id: int, dias: string, label: string}> */
    public array $finalizarTabelasPrazoPredefinidas = [];

    public ?int $selectedFinalizarTabelaPredefinidaIndex = null;

    /** Modal Impressão do Carnê (A4 / bobina 80). */
    public bool $finalizarCarneImpressaoAberta = false;

    public function getFinalizarTabelaPrazoEmConsultaProperty(): bool
    {
        return $this->finalizarTabelaPrazoConsulta;
    }

    public function getFinalizarCarneImpressaoAbertaProperty(): bool
    {
        return $this->finalizarCarneImpressaoAberta;
    }

    public function getFinalizarTabelaPrazoLabelProperty(): string
    {
        if (blank($this->finalizarTabelaPrazoDias)) {
            return '';
        }

        return (string) $this->finalizarTabelaPrazoDias;
    }

    public function getFinalizarParcelasTotalLabelProperty(): string
    {
        $total = collect($this->finalizarParcelasRows)->sum(
            fn (array $row): float => ErpMoney::parseBr($row['valor'] ?? '0'),
        );

        return $this->finalizarParcelasRows === []
            ? ''
            : ErpMoney::formatBr($total);
    }

    public function getFinalizarCrediarioTotalValorProperty(): float
    {
        foreach ($this->finalizarPagamentos as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::precisaParcelasCarne($pagamento)) {
                continue;
            }

            $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

            if ($valor > 0) {
                return $valor;
            }
        }

        return 0.0;
    }

    /** Carnê (impressão) só para Crediário — não para cheque/boleto. */
    public function getFinalizarParcelasEhCrediarioProperty(): bool
    {
        foreach ($this->finalizarPagamentos as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::precisaParcelasCarne($pagamento)) {
                continue;
            }

            if (ErpMoney::parseBr($pagamento['valor'] ?? '0') <= 0) {
                continue;
            }

            $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');

            return $tipo === 'crediario'
                || PdvFinalizarPagamentosHelper::isFormaCrediario((string) ($pagamento['forma'] ?? ''));
        }

        return false;
    }

    /** Cheque: mostra coluna número do cheque (oculta F5 Boleto). */
    public function getFinalizarParcelasEhChequeProperty(): bool
    {
        foreach ($this->finalizarPagamentos as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::precisaParcelasCarne($pagamento)) {
                continue;
            }

            if (ErpMoney::parseBr($pagamento['valor'] ?? '0') <= 0) {
                continue;
            }

            $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');

            return $tipo === 'cheque'
                || PdvFinalizarPagamentosHelper::isFormaCheque((string) ($pagamento['forma'] ?? ''));
        }

        return false;
    }

    /** Boleto: botão F5 (quando houver emissão). */
    public function getFinalizarParcelasEhBoletoProperty(): bool
    {
        foreach ($this->finalizarPagamentos as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::precisaParcelasCarne($pagamento)) {
                continue;
            }

            if (ErpMoney::parseBr($pagamento['valor'] ?? '0') <= 0) {
                continue;
            }

            $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');

            return $tipo === 'boleto'
                || PdvFinalizarPagamentosHelper::isFormaBoleto((string) ($pagamento['forma'] ?? ''));
        }

        return false;
    }

    protected function resetFinalizarTabelaPrazo(): void
    {
        $this->finalizarTabelaPrazoId = null;
        $this->finalizarTabelaPrazoDias = null;
        $this->finalizarTabelaPrazoConsulta = false;
        $this->finalizarParcelasRows = [];
        $this->finalizarParcelasQtd = '1';
        $this->finalizarParcelasIntervalo = '30';
        $this->selectedFinalizarParcelaIndex = null;
        $this->finalizarTabelasPrazoListaAberta = false;
        $this->finalizarTabelasPrazoPredefinidas = [];
        $this->selectedFinalizarTabelaPredefinidaIndex = null;
        $this->finalizarCarneImpressaoAberta = false;
    }

    /**
     * Crediário / cheque / boleto passam pela tela Contas Receber | Parcelas.
     * Só libera o fechamento após F7 | Concluir.
     */
    protected function ensureTabelaPrazoCrediario(bool $abrirSeNecessario = true): bool
    {
        if (! $this->finalizarTemCrediarioComValor()) {
            return true;
        }

        if (! $this->finalizarClienteId) {
            $this->notifyPdvError('Informe o cliente para pagamento a prazo.');

            return false;
        }

        if (filled($this->finalizarTabelaPrazoDias) && $this->finalizarParcelasRows !== []) {
            return true;
        }

        if (! $abrirSeNecessario) {
            return false;
        }

        return $this->abrirParcelasCrediario();
    }

    protected function finalizarTemCrediarioComValor(): bool
    {
        return $this->finalizarCrediarioTotalValor > 0;
    }

    protected function abrirParcelasCrediario(): bool
    {
        $this->prepararParcelasCrediarioForm();
        $this->finalizarTabelasPrazoListaAberta = false;
        $this->finalizarTabelasPrazoPredefinidas = [];
        $this->selectedFinalizarTabelaPredefinidaIndex = null;
        $this->finalizarTabelaPrazoConsulta = true;
        $this->selectedFinalizarParcelaIndex = $this->finalizarParcelasRows !== [] ? 0 : null;
        $this->dispatch('erp-pdv-focus-finalizar-parcelas');

        return false;
    }

    protected function prepararParcelasCrediarioForm(): void
    {
        $cliente = $this->finalizarClienteId
            ? Person::query()->find($this->finalizarClienteId)
            : null;

        $tabela = $cliente?->tabela_prazo_id
            ? TabelaPrazo::query()->find($cliente->tabela_prazo_id)
            : null;

        if ($tabela && filled($tabela->dias)) {
            $this->finalizarTabelaPrazoId = (int) $tabela->id;
            $dias = PdvFinalizarPagamentosHelper::diasDeString((string) $tabela->dias);

            if ($dias !== []) {
                $this->finalizarParcelasQtd = (string) count($dias);
                $this->finalizarParcelasIntervalo = (string) $this->estimarIntervalo($dias);
                $this->gerarParcelasCrediarioPorDias($dias);

                return;
            }
        }

        if ($this->finalizarParcelasRows === []) {
            $this->finalizarParcelasQtd = '1';
            $this->finalizarParcelasIntervalo = '30';
        }
    }

    /**
     * @param  list<int>  $dias
     */
    protected function estimarIntervalo(array $dias): int
    {
        if (count($dias) >= 2) {
            return max(0, (int) $dias[1] - (int) $dias[0]);
        }

        return max(0, (int) ($dias[0] ?? 30));
    }

    public function gerarParcelasCrediario(): void
    {
        // Avulso: limpa vínculo com tabela pré-definida.
        $this->finalizarTabelaPrazoId = null;
        $this->finalizarTabelasPrazoListaAberta = false;

        $qtd = max(1, (int) preg_replace('/\D/', '', $this->finalizarParcelasQtd) ?: 1);
        $intervalo = max(0, (int) preg_replace('/\D/', '', $this->finalizarParcelasIntervalo) ?: 0);
        $this->finalizarParcelasQtd = (string) $qtd;
        $this->finalizarParcelasIntervalo = (string) $intervalo;

        $dias = [];

        for ($i = 1; $i <= $qtd; $i++) {
            $dias[] = $intervalo * $i;
        }

        $this->gerarParcelasCrediarioPorDias($dias);
        $this->selectedFinalizarParcelaIndex = 0;
        $this->dispatch('erp-pdv-focus-finalizar-parcelas');
    }

    public function abrirTabelasPrazoPredefinidas(): void
    {
        $this->refreshTabelasPrazoPredefinidas();

        if ($this->finalizarTabelasPrazoPredefinidas === []) {
            $this->notifyPdvError(
                'Nenhuma tabela de prazos cadastrada para Crediário.',
                'Cadastre em Formas de Pagamento.',
            );

            return;
        }

        $this->finalizarTabelasPrazoListaAberta = true;
        $this->selectedFinalizarTabelaPredefinidaIndex = 0;
        $this->dispatch('erp-pdv-focus-finalizar-tabelas-predefinidas');
    }

    public function fecharTabelasPrazoPredefinidas(): void
    {
        $this->finalizarTabelasPrazoListaAberta = false;
        $this->selectedFinalizarTabelaPredefinidaIndex = null;
        $this->dispatch('erp-pdv-focus-finalizar-parcelas');
    }

    public function refreshTabelasPrazoPredefinidas(): void
    {
        $formaIds = FormaPagamento::query()
            ->where('ativo', true)
            ->whereIn('tipo', ['crediario', 'cheque', 'boleto'])
            ->pluck('id');

        $this->finalizarTabelasPrazoPredefinidas = TabelaPrazo::query()
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

    public function selectFinalizarTabelaPredefinida(int $index): void
    {
        if (isset($this->finalizarTabelasPrazoPredefinidas[$index])) {
            $this->selectedFinalizarTabelaPredefinidaIndex = $index;
        }
    }

    public function moveFinalizarTabelaPredefinidaSelection(int $delta): void
    {
        if ($this->finalizarTabelasPrazoPredefinidas === []) {
            return;
        }

        $count = count($this->finalizarTabelasPrazoPredefinidas);
        $index = ($this->selectedFinalizarTabelaPredefinidaIndex ?? 0) + $delta;
        $this->selectedFinalizarTabelaPredefinidaIndex = max(0, min($count - 1, $index));
    }

    public function aplicarTabelaPrazoPredefinida(): void
    {
        $index = $this->selectedFinalizarTabelaPredefinidaIndex;

        if ($index === null || ! isset($this->finalizarTabelasPrazoPredefinidas[$index])) {
            $this->notifyPdvError('Selecione uma tabela de prazos.');

            return;
        }

        $row = $this->finalizarTabelasPrazoPredefinidas[$index];
        $dias = PdvFinalizarPagamentosHelper::diasDeString((string) $row['dias']);

        if ($dias === []) {
            $this->notifyPdvError('Tabela de prazos inválida.');

            return;
        }

        $this->finalizarTabelaPrazoId = (int) $row['tabela_prazo_id'];
        $this->finalizarParcelasQtd = (string) count($dias);
        $this->finalizarParcelasIntervalo = (string) $this->estimarIntervalo($dias);
        $this->gerarParcelasCrediarioPorDias($dias);
        $this->selectedFinalizarParcelaIndex = 0;
        $this->finalizarTabelasPrazoListaAberta = false;
        $this->selectedFinalizarTabelaPredefinidaIndex = null;
        $this->dispatch('erp-pdv-focus-finalizar-parcelas');
    }

    /**
     * @param  list<int>  $dias
     */
    protected function gerarParcelasCrediarioPorDias(array $dias): void
    {
        $total = round($this->finalizarCrediarioTotalValor, 2);

        if ($total <= 0 || $dias === []) {
            $this->finalizarParcelasRows = [];

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

        $this->finalizarParcelasRows = $rows;
        $this->finalizarParcelasQtd = (string) $n;
    }

    public function selectFinalizarParcelaRow(int $index): void
    {
        if (isset($this->finalizarParcelasRows[$index])) {
            $this->selectedFinalizarParcelaIndex = $index;
        }
    }

    public function moveFinalizarParcelaSelection(int $delta): void
    {
        if ($this->finalizarParcelasRows === []) {
            return;
        }

        $count = count($this->finalizarParcelasRows);
        $index = ($this->selectedFinalizarParcelaIndex ?? 0) + $delta;
        $this->selectedFinalizarParcelaIndex = max(0, min($count - 1, $index));
    }

    public function excluirParcelaCrediario(): void
    {
        $index = $this->selectedFinalizarParcelaIndex;

        if ($index === null || ! isset($this->finalizarParcelasRows[$index])) {
            $this->notifyPdvError('Selecione a parcela para excluir.');

            return;
        }

        $rows = $this->finalizarParcelasRows;
        array_splice($rows, $index, 1);

        foreach ($rows as $i => $row) {
            $rows[$i]['documento'] = (string) ($i + 1);
        }

        $this->finalizarParcelasRows = $rows;
        $this->finalizarParcelasQtd = (string) max(1, count($rows));
        $this->selectedFinalizarParcelaIndex = $rows === []
            ? null
            : min($index, count($rows) - 1);
    }

    public function cancelFinalizarTabelaPrazoConsulta(): void
    {
        if (! $this->finalizarTabelaPrazoConsulta) {
            return;
        }

        if ($this->finalizarCarneImpressaoAberta) {
            $this->fecharCarneImpressao();

            return;
        }

        if ($this->finalizarTabelasPrazoListaAberta) {
            $this->fecharTabelasPrazoPredefinidas();

            return;
        }

        $this->finalizarTabelaPrazoConsulta = false;
        $this->finalizarTabelasPrazoListaAberta = false;
        $this->finalizarCarneImpressaoAberta = false;
        $this->selectedFinalizarParcelaIndex = null;
    }

    public function abrirCarneImpressao(): void
    {
        if (! $this->finalizarParcelasEhCrediario) {
            return;
        }

        if ($this->finalizarParcelasRows === []) {
            $this->notifyPdvError(
                'Gere as parcelas antes de imprimir o carnê.',
                'Use F2 | Gerar ou F8 | Tabelas.',
            );

            return;
        }

        $this->finalizarCarneImpressaoAberta = true;
        $this->dispatch('erp-pdv-focus-carne-impressao');
    }

    public function fecharCarneImpressao(): void
    {
        if (! $this->finalizarCarneImpressaoAberta) {
            return;
        }

        $this->finalizarCarneImpressaoAberta = false;
        $this->dispatch('erp-pdv-focus-finalizar-parcelas');
    }

    public function escolherCarneImpressaoA4ComCapa(): void
    {
        $this->abrirRelatorioCarneImpressao('erp.reports.pdv-carne-a4', ['capa' => 1]);
    }

    public function escolherCarneImpressaoA4(): void
    {
        $this->abrirRelatorioCarneImpressao('erp.reports.pdv-carne-a4', ['capa' => 0]);
    }

    public function escolherCarneImpressaoBobina80(): void
    {
        $this->abrirRelatorioCarneImpressao('erp.reports.pdv-carne-bobina');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function montarPayloadCarneImpressao(): ?array
    {
        if ($this->finalizarParcelasRows === []) {
            $this->notifyPdvError('Gere as parcelas antes de imprimir o carnê.');

            return null;
        }

        $empresa = $this->pdvConfig()->empresa();
        $empresaNome = mb_strtoupper(trim((string) (
            $empresa?->fantasia
            ?: $empresa?->razao_social
            ?: $empresa?->nome
            ?: 'EMPRESA'
        )), 'UTF-8');

        $razao = mb_strtoupper(trim((string) (
            $empresa?->razao_social
            ?: $empresa?->nome
            ?: $empresaNome
        )), 'UTF-8');

        $obs = trim((string) ($empresa?->obs_carne ?? ''));
        if ($obs === '') {
            $obs = 'OBRIGADO PELA PREFERÊNCIA!';
        }

        $numeroBase = '0';
        if (! empty($this->caixaSessaoId)) {
            $numeroBase = (string) \App\Models\PdvVenda::nextNumero((int) $this->caixaSessaoId);
        }

        $totalParcelas = count($this->finalizarParcelasRows);
        $parcelas = [];
        $totalCentavos = 0;

        foreach ($this->finalizarParcelasRows as $index => $row) {
            $seq = $index + 1;
            $valor = (string) ($row['valor'] ?? '0,00');
            $totalCentavos += (int) round(\App\Support\Erp\ErpMoney::parseBr($valor) * 100);
            $parcelas[] = [
                'documento' => $numeroBase.'/'.$seq,
                'vencimento' => (string) ($row['vencimento'] ?? ''),
                'valor' => $valor,
                'dias' => (int) ($row['dias'] ?? 0),
                'parcela' => $seq,
                'total_parcelas' => $totalParcelas,
            ];
        }

        $logradouro = trim(implode(', ', array_filter([
            trim((string) ($empresa?->endereco ?? '')),
            trim((string) ($empresa?->numero ?? '')) !== '' ? 'nº '.trim((string) $empresa->numero) : null,
            filled($empresa?->complemento) ? trim((string) $empresa->complemento) : null,
        ])));

        $cidadeUf = trim(implode('/', array_filter([
            trim((string) ($empresa?->cidade ?? '')),
            trim((string) ($empresa?->uf ?? '')),
        ])));

        $enderecoLinhas = array_values(array_filter([
            $logradouro,
            filled($empresa?->bairro) ? 'Bairro: '.trim((string) $empresa->bairro) : null,
            $cidadeUf !== '' ? $cidadeUf : null,
            filled($empresa?->cep) ? 'CEP '.trim((string) $empresa->cep) : null,
        ]));

        return [
            'empresa_nome' => $empresaNome,
            'empresa_razao' => $razao,
            'empresa_cnpj' => $this->formatCnpjCpfCarne((string) ($empresa?->cnpj ?? '')),
            'empresa_ie' => trim((string) ($empresa?->ie ?? '')),
            'empresa_telefone' => trim((string) ($empresa?->telefone ?? '')),
            'empresa_email' => trim((string) ($empresa?->email ?? '')),
            'empresa_endereco_linhas' => $enderecoLinhas,
            'empresa_logo_path' => (string) ($empresa?->logo_path ?? ''),
            'cliente_nome' => mb_strtoupper(trim((string) ($this->finalizarForm['cliente'] ?? 'CONSUMIDOR FINAL')), 'UTF-8'),
            'vendedor_nome' => mb_strtoupper(trim((string) ($this->vendedor ?: 'SEM OPERADOR')), 'UTF-8'),
            'observacao' => mb_strtoupper($obs, 'UTF-8'),
            'emissao' => now()->format('d/m/Y'),
            'numero_base' => $numeroBase,
            'total_parcelas' => $totalParcelas,
            'total_valor' => \App\Support\Erp\ErpMoney::formatBr($totalCentavos / 100),
            'parcelas' => $parcelas,
        ];
    }

    protected function formatCnpjCpfCarne(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 14) {
            return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3).'/'
                .substr($digits, 8, 4).'-'.substr($digits, 12, 2);
        }

        if (strlen($digits) === 11) {
            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function abrirRelatorioCarneImpressao(string $routeName, array $query = []): void
    {
        $payload = $this->montarPayloadCarneImpressao();
        if ($payload === null) {
            return;
        }

        session([
            \App\Http\Controllers\Erp\PdvCarneBobinaReportController::SESSION_KEY => $payload,
        ]);

        $url = route($routeName, array_merge(['auto' => 1], $query));

        $this->fecharCarneImpressao();
        // Imprime em iframe oculto no PDV — não abre aba nova (evita acumular janelas).
        $this->js('window.ErpPdvPrint?.printUrl?.('. \Illuminate\Support\Js::from($url) .')');
    }

    public function concluirParcelasCrediario(): void
    {
        if ($this->finalizarParcelasRows === []) {
            $this->notifyPdvError(
                'Gere as parcelas antes de concluir.',
                'Informe Parcelas/Intervalo e pressione F2 | Gerar.',
            );

            return;
        }

        $dias = [];

        foreach ($this->finalizarParcelasRows as $row) {
            $dias[] = max(0, (int) ($row['dias'] ?? 0));
        }

        if ($dias === []) {
            $this->notifyPdvError('Parcelas inválidas.');

            return;
        }

        $soma = round(collect($this->finalizarParcelasRows)->sum(
            fn (array $row): float => ErpMoney::parseBr($row['valor'] ?? '0'),
        ), 2);
        $total = round($this->finalizarCrediarioTotalValor, 2);

        if (abs($soma - $total) > 0.01) {
            $this->notifyPdvError(
                'Total das parcelas diverge do crediário.',
                'Parcelas: R$ ' . ErpMoney::formatBr($soma) . ' / Crediário: R$ ' . ErpMoney::formatBr($total),
            );

            return;
        }

        $this->finalizarTabelaPrazoDias = implode(',', $dias);
        $this->finalizarTabelaPrazoConsulta = false;
        $this->dispatch('erp-pdv-focus-finalizar-ok');
    }

    /**
     * Ao trocar o cliente, limpa confirmação e reaproveita só a prévia da tabela fixa.
     */
    protected function sincronizarTabelaPrazoComCliente(): void
    {
        $this->finalizarTabelaPrazoId = null;
        $this->finalizarTabelaPrazoDias = null;
        $this->finalizarTabelaPrazoConsulta = false;
        $this->finalizarParcelasRows = [];
        $this->selectedFinalizarParcelaIndex = null;
        $this->finalizarParcelasQtd = '1';
        $this->finalizarParcelasIntervalo = '30';

        if (! $this->finalizarClienteId) {
            return;
        }

        $cliente = Person::query()->find($this->finalizarClienteId);
        $tabela = $cliente?->tabela_prazo_id
            ? TabelaPrazo::query()->find($cliente->tabela_prazo_id)
            : null;

        if ($tabela && filled($tabela->dias)) {
            $this->finalizarTabelaPrazoId = (int) $tabela->id;
            $dias = PdvFinalizarPagamentosHelper::diasDeString((string) $tabela->dias);

            if ($dias !== []) {
                $this->finalizarParcelasQtd = (string) count($dias);
                $this->finalizarParcelasIntervalo = (string) $this->estimarIntervalo($dias);
            }
        }
    }

    protected function validaTabelaPrazoFinalizar(): ?string
    {
        if (! $this->finalizarTemCrediarioComValor()) {
            return null;
        }

        if ($this->finalizarParcelasRows === [] || blank($this->finalizarTabelaPrazoDias)) {
            return 'Informe as parcelas (Contas Receber | Parcelas).';
        }

        $dias = PdvFinalizarPagamentosHelper::diasDeString((string) $this->finalizarTabelaPrazoDias);

        if ($dias === []) {
            return 'Parcelas inválidas.';
        }

        return null;
    }

    /**
     * @return list<int>|null
     */
    protected function finalizarTabelaPrazoDiasList(): ?array
    {
        if ($this->finalizarParcelasRows !== []) {
            return collect($this->finalizarParcelasRows)
                ->map(fn (array $row): int => max(0, (int) ($row['dias'] ?? 0)))
                ->values()
                ->all();
        }

        if (blank($this->finalizarTabelaPrazoDias)) {
            return null;
        }

        $dias = PdvFinalizarPagamentosHelper::diasDeString((string) $this->finalizarTabelaPrazoDias);

        return $dias !== [] ? $dias : null;
    }

    /**
     * @return list<string>|null
     */
    protected function finalizarParcelasChequeNumerosList(): ?array
    {
        if (! $this->finalizarParcelasEhCheque || $this->finalizarParcelasRows === []) {
            return null;
        }

        return collect($this->finalizarParcelasRows)
            ->map(fn (array $row): string => trim((string) ($row['numero_cheque'] ?? '')))
            ->values()
            ->all();
    }

    /** Compat: métodos antigos da lista de tabelas. */
    public function confirmFinalizarTabelaPrazo(): void
    {
        $this->concluirParcelasCrediario();
    }

    public function selectFinalizarTabelaPrazoResult(int $index): void
    {
        $this->selectFinalizarParcelaRow($index);
    }

    public function moveFinalizarTabelaPrazoSelection(int $delta): void
    {
        $this->moveFinalizarParcelaSelection($delta);
    }
}
