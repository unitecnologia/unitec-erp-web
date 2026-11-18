<?php

namespace App\Filament\Resources\ContaPagarResource\Pages\Concerns;

use App\Models\CaixaConta;
use App\Models\ContaPagar;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaPagarBaixaJurosPadrao;
use App\Support\Erp\Financeiro\ContaPagarBaixaService;
use App\Support\Erp\Financeiro\ContaPagarBaixaValorPagoJuros;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use InvalidArgumentException;

trait ManagesContaPagarBaixaModal
{
    public bool $baixaModalOpen = false;

    /** @var list<int> */
    public array $baixaContaIds = [];

    public ?int $baixaFormaPagamentoId = null;

    public ?int $baixaPlanoContaId = null;

    public ?int $baixaCaixaContaId = null;

    public string $baixaContaDestino = '—';

    public bool $baixaMovimentaCaixa = false;

    /** @var list<array{id: int, label: string}> */
    public array $baixaCaixasOptions = [];

    public bool $baixaExigeCheque = false;

    public string $baixaCheque = '';

    public bool $baixaUnica = true;

    public bool $baixaValorPagoEditado = false;

    public bool $baixaJurosEditado = false;

    public bool $baixaJurosValorEditado = false;

    public bool $baixaMultaEditada = false;

    /** @var list<array{saldo: float, vencimento: string|null}> */
    public array $baixaItensSaldo = [];

    public bool $baixaDescontoEditado = false;

    public string $baixaResumoQtd = '0';

    public string $baixaResumoTotal = '0,00';

    /** @var array<string, string> */
    public array $baixaDados = [
        'fornecedor' => '',
        'documento' => '',
        'emissao' => '',
        'vencimento' => '',
        'valor' => '0,00',
        'juros_pago' => '0,00',
        'multa_paga' => '0,00',
        'desconto_recebido' => '0,00',
        'valor_pago_acumulado' => '0,00',
        'valor_a_pagar_titulo' => '0,00',
    ];

    public string $baixaSaldo = '0,00';

    public string $baixaPercJuros = '0,00';

    public string $baixaJuros = '0,00';

    public string $baixaPercMulta = '0,00';

    public string $baixaMulta = '0,00';

    public string $baixaSaldoComJuros = '0,00';

    public string $baixaPercDesconto = '0,00';

    public string $baixaDesconto = '0,00';

    public string $baixaValorAPagar = '0,00';

    public string $baixaValorPago = '0,00';

    public string $baixaPagoEm = '';

    public string $baixaPagoEmConfirmado = '';

    /** @var list<array{id: int, label: string, tipo: string|null, caixa_conta_id?: int|null, caixa_label?: string, movimenta_caixa?: bool, exige_cheque?: bool}> */
    public array $baixaFormasOptions = [];

    /** @var list<array{id: int, label: string}> */
    public array $baixaPlanosOptions = [];

    public function baixarConta(): void
    {
        $contas = $this->contasParaAbrirBaixa();

        if ($contas === null) {
            return;
        }

        $service = app(ContaPagarBaixaService::class);
        $this->baixaFormasOptions = $service->formasDisponiveis();
        $this->baixaPlanosOptions = $service->planosDisponiveis();
        $this->baixaCaixasOptions = $this->caixasAtivasBaixaOptions();

        if ($this->baixaFormasOptions === []) {
            Notification::make()
                ->title('Cadastre um meio de pagamento')
                ->body('Nenhuma forma de pagamento disponível para Contas a Pagar.')
                ->warning()
                ->send();

            return;
        }

        if ($this->baixaPlanosOptions === []) {
            Notification::make()
                ->title('Cadastre um plano de contas de débito')
                ->body('A baixa exige um plano de contas com DC = D.')
                ->warning()
                ->send();

            return;
        }

        $contas = ContaPagar::query()
            ->with('fornecedor:id,nome_razao,apelido_fantasia')
            ->whereIn('id', $contas->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->get();

        $saldo = round($contas->sum(fn (ContaPagar $conta): float => (float) $conta->saldo), 2);
        $this->baixaContaIds = $contas->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        $this->baixaUnica = $contas->count() === 1;
        $this->baixaResumoQtd = (string) $contas->count();
        $this->baixaResumoTotal = ErpMoney::formatBr($saldo);
        $this->baixaFormaPagamentoId = (int) ($this->baixaFormasOptions[0]['id'] ?? 0);
        $this->baixaPlanoContaId = $this->resolvePlanoContaPadraoFornecedores();
        $this->baixaValorPagoEditado = false;
        $this->baixaJurosEditado = false;
        $this->baixaJurosValorEditado = false;
        $this->baixaMultaEditada = false;
        $this->baixaDescontoEditado = false;
        $this->baixaCheque = '';
        $this->preencherDadosBaixa($contas, $saldo);
        $this->syncFormaBaixa();

        $this->baixaSaldo = ErpMoney::formatBr($saldo);
        $this->baixaPercDesconto = '0,00';
        $this->baixaDesconto = '0,00';
        $this->baixaPagoEm = ErpTimezone::toLocal()->toDateString();
        $this->baixaPagoEmConfirmado = $this->baixaPagoEm;
        $this->aplicarPadraoJurosMulta();
        $this->recalcularBaixaTotais();
        $this->baixaModalOpen = true;
    }

    public function updatedBaixaFormaPagamentoId(mixed $value = null): void
    {
        $this->syncFormaBaixa();
    }

    public function updatingBaixaPercJuros(mixed $value): void
    {
        if ($this->dinheiroBaixaMudou($value, $this->baixaPercJuros)) {
            $this->baixaJurosEditado = true;
        }
    }

    public function updatedBaixaPercJuros(mixed $value): void
    {
        $perc = ErpMoney::parseBr($value);
        $this->baixaPercJuros = ErpMoney::formatBr($perc);
        $this->baixaJurosValorEditado = false;
        $this->baixaJuros = ErpMoney::formatBr($this->totalJurosPeloPercAoDia($perc));
        $this->recalcularBaixaTotais();
    }

    public function updatingBaixaJuros(mixed $value): void
    {
        if ($this->dinheiroBaixaMudou($value, $this->baixaJuros)) {
            $this->baixaJurosEditado = true;
            $this->baixaJurosValorEditado = true;
        }
    }

    public function updatedBaixaJuros(mixed $value): void
    {
        $juros = ErpMoney::parseBr($value);
        $this->baixaJuros = ErpMoney::formatBr($juros);
        $this->baixaPercJuros = $this->percAoDiaPeloJuros($juros);
        $this->recalcularBaixaTotais();
    }

    public function updatedBaixaPagoEm(mixed $value): void
    {
        $pagoEm = trim((string) $value);
        if (! ContaPagarBaixaJurosPadrao::dataIsoValida($pagoEm)) {
            if (ContaPagarBaixaJurosPadrao::dataIsoValida($this->baixaPagoEmConfirmado)) {
                $this->baixaPagoEm = $this->baixaPagoEmConfirmado;
            }

            return;
        }

        $this->baixaPagoEm = $pagoEm;
        $this->baixaPagoEmConfirmado = $pagoEm;

        if (! $this->baixaJurosValorEditado) {
            $this->baixaJuros = ErpMoney::formatBr(
                $this->totalJurosPeloPercAoDia(ErpMoney::parseBr($this->baixaPercJuros))
            );
        } else {
            $this->baixaPercJuros = $this->percAoDiaPeloJuros(ErpMoney::parseBr($this->baixaJuros));
        }

        if (! $this->baixaMultaEditada) {
            $this->baixaMulta = ErpMoney::formatBr(
                $this->totalMultaPeloPerc(ErpMoney::parseBr($this->baixaPercMulta))
            );
        }

        $this->recalcularBaixaTotais();
    }

    public function updatingBaixaPercMulta(mixed $value): void
    {
        if ($this->dinheiroBaixaMudou($value, $this->baixaPercMulta)) {
            $this->baixaMultaEditada = true;
        }
    }

    public function updatedBaixaPercMulta(mixed $value): void
    {
        $perc = ErpMoney::parseBr($value);
        $this->baixaPercMulta = ErpMoney::formatBr($perc);
        $this->baixaMulta = ErpMoney::formatBr($this->totalMultaPeloPerc($perc));
        $this->recalcularBaixaTotais();
    }

    public function updatingBaixaMulta(mixed $value): void
    {
        if ($this->dinheiroBaixaMudou($value, $this->baixaMulta)) {
            $this->baixaMultaEditada = true;
        }
    }

    public function updatedBaixaMulta(mixed $value): void
    {
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $multa = ErpMoney::parseBr($value);
        $this->baixaMulta = ErpMoney::formatBr($multa);
        $this->baixaPercMulta = $saldo > 0
            ? ErpMoney::formatBr(round(($multa / $saldo) * 100, 2))
            : '0,00';
        $this->recalcularBaixaTotais();
    }

    public function updatingBaixaPercDesconto(mixed $value): void
    {
        if ($this->dinheiroBaixaMudou($value, $this->baixaPercDesconto)) {
            $this->baixaDescontoEditado = true;
        }
    }

    public function updatedBaixaPercDesconto(mixed $value): void
    {
        $base = ErpMoney::parseBr($this->baixaSaldo)
            + ErpMoney::parseBr($this->baixaJuros)
            + ErpMoney::parseBr($this->baixaMulta);
        $perc = ErpMoney::parseBr($value);
        $this->baixaPercDesconto = ErpMoney::formatBr($perc);
        $this->baixaDesconto = ErpMoney::formatBr(round($base * ($perc / 100), 2));
        $this->recalcularBaixaTotais();
    }

    public function updatingBaixaDesconto(mixed $value): void
    {
        if ($this->dinheiroBaixaMudou($value, $this->baixaDesconto)) {
            $this->baixaDescontoEditado = true;
        }
    }

    public function updatedBaixaDesconto(mixed $value): void
    {
        $base = ErpMoney::parseBr($this->baixaSaldo)
            + ErpMoney::parseBr($this->baixaJuros)
            + ErpMoney::parseBr($this->baixaMulta);
        $desconto = ErpMoney::parseBr($value);
        $this->baixaDesconto = ErpMoney::formatBr($desconto);
        $this->baixaPercDesconto = $base > 0
            ? ErpMoney::formatBr(round(($desconto / $base) * 100, 2))
            : '0,00';
        $this->recalcularBaixaTotais();
    }

    public function updatingBaixaValorPago(mixed $value): void
    {
        if ($this->dinheiroBaixaMudou($value, $this->baixaValorPago)) {
            $this->baixaValorPagoEditado = true;
        }
    }

    public function updatedBaixaValorPago(mixed $value): void
    {
        $valorPago = round(ErpMoney::parseBr($value), 2);
        $this->baixaValorPago = ErpMoney::formatBr($valorPago);

        $calc = ContaPagarBaixaValorPagoJuros::absorverExcesso(
            ErpMoney::parseBr($this->baixaSaldo),
            ErpMoney::parseBr($this->baixaJuros),
            ErpMoney::parseBr($this->baixaMulta),
            ErpMoney::parseBr($this->baixaDesconto),
            $valorPago,
        );

        if (! $calc['aplicar']) {
            return;
        }

        $this->baixaJuros = ErpMoney::formatBr($calc['juros']);
        $this->baixaPercJuros = $this->percAoDiaPeloJuros($calc['juros']);
        $this->baixaJurosEditado = true;
        $this->baixaJurosValorEditado = true;
        $this->recalcularBaixaTotais();
    }

    protected function recalcularBaixaTotais(): void
    {
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $juros = ErpMoney::parseBr($this->baixaJuros);
        $multa = ErpMoney::parseBr($this->baixaMulta);
        $desconto = ErpMoney::parseBr($this->baixaDesconto);
        $saldoComJuros = round($saldo + $juros + $multa, 2);
        $desconto = min($desconto, $saldoComJuros);
        $valorAPagar = round(max(0, $saldoComJuros - $desconto), 2);

        $this->baixaSaldoComJuros = ErpMoney::formatBr($saldoComJuros);
        $this->baixaDesconto = ErpMoney::formatBr($desconto);
        $this->baixaPercDesconto = $saldoComJuros > 0
            ? ErpMoney::formatBr(round(($desconto / $saldoComJuros) * 100, 2))
            : '0,00';
        $this->baixaValorAPagar = ErpMoney::formatBr($valorAPagar);

        if (! $this->baixaValorPagoEditado) {
            $this->baixaValorPago = ErpMoney::formatBr($valorAPagar);
        }
    }

    public function closeBaixaModal(): void
    {
        $this->baixaModalOpen = false;
        $this->baixaContaIds = [];
        $this->baixaFormaPagamentoId = null;
        $this->baixaPlanoContaId = null;
        $this->baixaCaixaContaId = null;
        $this->baixaContaDestino = '—';
        $this->baixaMovimentaCaixa = false;
        $this->baixaCaixasOptions = [];
        $this->baixaExigeCheque = false;
        $this->baixaCheque = '';
        $this->baixaUnica = true;
        $this->baixaValorPagoEditado = false;
        $this->baixaJurosEditado = false;
        $this->baixaJurosValorEditado = false;
        $this->baixaItensSaldo = [];
        $this->baixaMultaEditada = false;
        $this->baixaDescontoEditado = false;
        $this->baixaResumoQtd = '0';
        $this->baixaResumoTotal = '0,00';
        $this->baixaFormasOptions = [];
        $this->baixaPlanosOptions = [];
        $this->baixaDados = [
            'fornecedor' => '',
            'documento' => '',
            'emissao' => '',
            'vencimento' => '',
            'valor' => '0,00',
            'juros_pago' => '0,00',
            'multa_paga' => '0,00',
            'desconto_recebido' => '0,00',
            'valor_pago_acumulado' => '0,00',
            'valor_a_pagar_titulo' => '0,00',
        ];
        $this->baixaSaldo = '0,00';
        $this->baixaPercJuros = '0,00';
        $this->baixaJuros = '0,00';
        $this->baixaPercMulta = '0,00';
        $this->baixaMulta = '0,00';
        $this->baixaSaldoComJuros = '0,00';
        $this->baixaPercDesconto = '0,00';
        $this->baixaDesconto = '0,00';
        $this->baixaValorAPagar = '0,00';
        $this->baixaValorPago = '0,00';
        $this->baixaPagoEm = '';
        $this->baixaPagoEmConfirmado = '';
    }

    protected function resolvePlanoContaPadraoFornecedores(): ?int
    {
        $empresaId = null;

        if ($this->baixaContaIds !== []) {
            $empresaId = (int) (ContaPagar::query()
                ->whereKey($this->baixaContaIds[0])
                ->value('empresa_id') ?? 0) ?: null;
        }

        return app(ContaPagarBaixaService::class)
            ->sugerirPlanoPadrao($empresaId ?: ErpContext::currentEmpresaId());
    }

    public function confirmarBaixaConta(): void
    {
        if (! $this->baixaModalOpen || $this->baixaContaIds === []) {
            return;
        }

        if (! $this->baixaFormaPagamentoId) {
            Notification::make()
                ->title('Selecione o meio de pagamento.')
                ->warning()
                ->send();

            return;
        }

        if ((int) $this->baixaPlanoContaId <= 0) {
            Notification::make()
                ->title('Selecione o plano de contas.')
                ->warning()
                ->send();

            return;
        }

        if (! ContaPagarBaixaJurosPadrao::dataIsoValida($this->baixaPagoEm)) {
            Notification::make()
                ->title('Informe a data de pagamento.')
                ->warning()
                ->send();

            return;
        }

        $opcoes = [
            'plano_conta_id' => (int) $this->baixaPlanoContaId,
            'perc_juros' => ErpMoney::parseBr($this->baixaPercJuros),
            'juros' => ErpMoney::parseBr($this->baixaJuros),
            'perc_multa' => ErpMoney::parseBr($this->baixaPercMulta),
            'multa' => ErpMoney::parseBr($this->baixaMulta),
            'perc_desconto' => ErpMoney::parseBr($this->baixaPercDesconto),
            'desconto' => ErpMoney::parseBr($this->baixaDesconto),
            'valor_pago' => ErpMoney::parseBr($this->baixaValorPago),
            'pago_em' => $this->baixaPagoEm,
            'numero_cheque' => $this->baixaExigeCheque ? $this->baixaCheque : '',
            'caixa_conta_id' => $this->baixaMovimentaCaixa
                ? ((int) ($this->baixaCaixaContaId ?? 0) ?: null)
                : null,
        ];

        if (! $this->baixaUnica) {
            $opcoes['grupo'] = [
                'juros_editado' => $this->baixaJurosEditado || ErpMoney::parseBr($this->baixaJuros) > 0,
                'multa_editada' => $this->baixaMultaEditada || ErpMoney::parseBr($this->baixaMulta) > 0,
                'desconto_editado' => $this->baixaDescontoEditado,
                'valor_editado' => $this->baixaValorPagoEditado,
            ];
        }

        try {
            $resultado = app(ContaPagarBaixaService::class)
                ->baixarMuitas($this->baixaContaIds, (int) $this->baixaFormaPagamentoId, $opcoes);
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Não foi possível baixar a conta.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->closeBaixaModal();
        $this->selecionadosParaBaixa = [];
        $this->clearListSelection();

        if ($resultado['ok'] < 1) {
            $this->atualizarGradeAposBaixa();

            Notification::make()
                ->title('Nenhuma conta foi baixada.')
                ->warning()
                ->send();

            return;
        }

        $this->atualizarGradeAposBaixa();

        $qtd = $resultado['ok'];
        Notification::make()
            ->title($qtd === 1 ? 'Conta baixada.' : "{$qtd} contas baixadas.")
            ->body('Total pago: R$ '.ErpMoney::formatBr((float) $resultado['total']))
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, ContaPagar>|null
     */
    protected function contasParaAbrirBaixa(): ?Collection
    {
        $ids = collect($this->selecionadosParaBaixa ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids !== []) {
            if ($this->fornecedorFilter === 'todos' || ! is_numeric($this->fornecedorFilter)) {
                Notification::make()
                    ->title('Selecione um fornecedor antes de marcar contas para baixa.')
                    ->warning()
                    ->send();

                return null;
            }

            $contas = ContaPagar::query()
                ->whereIn('id', $ids)
                ->get();

            $pendentes = $contas
                ->filter(fn (ContaPagar $conta): bool => (float) $conta->saldo > 0)
                ->values();

            if ($pendentes->isEmpty()) {
                Notification::make()
                    ->title('Nenhuma conta com saldo para baixar.')
                    ->warning()
                    ->send();

                return null;
            }

            $fornecedores = $pendentes
                ->map(fn (ContaPagar $conta): int => (int) $conta->fornecedor_id)
                ->unique()
                ->values();

            if ($fornecedores->count() !== 1 || (int) $fornecedores->first() !== (int) $this->fornecedorFilter) {
                Notification::make()
                    ->title('A baixa múltipla só aceita títulos do mesmo fornecedor.')
                    ->warning()
                    ->send();

                return null;
            }

            return $pendentes;
        }

        if (! $this->highlightedRecordIdOrNotify('baixar')) {
            return null;
        }

        $conta = ContaPagar::query()
            ->whereKey((int) $this->highlightedRecordId)
            ->first();

        if (! $conta || (float) $conta->saldo <= 0) {
            Notification::make()
                ->title('Nenhuma conta com saldo para baixar.')
                ->warning()
                ->send();

            return null;
        }

        return collect([$conta]);
    }

    /**
     * @param  Collection<int, ContaPagar>  $contas
     */
    protected function preencherDadosBaixa(Collection $contas, float $saldo): void
    {
        $nomes = $contas
            ->map(function (ContaPagar $conta): string {
                $fornecedor = $conta->fornecedor;

                return mb_strtoupper(trim((string) (
                    $fornecedor?->apelido_fantasia
                    ?: $fornecedor?->nome_razao
                    ?: ''
                )), 'UTF-8');
            })
            ->filter()
            ->unique()
            ->values();

        $fornecedorNome = $nomes->count() === 1 ? (string) $nomes->first() : ($nomes->isEmpty() ? '—' : 'VÁRIOS');

        if ($contas->count() === 1) {
            $conta = $contas->first();
            $documento = mb_strtoupper(trim((string) ($conta->documento ?: '—')), 'UTF-8');
            $emissao = optional($conta->emissao)->format('d/m/Y') ?: '—';
            $vencimento = optional($conta->vencimento)->format('d/m/Y') ?: '—';
        } else {
            $documento = $contas->count().' TÍTULOS';
            $emissoes = $contas->map(fn (ContaPagar $conta): string => optional($conta->emissao)->format('d/m/Y') ?? '')->unique();
            $vencimentos = $contas->map(fn (ContaPagar $conta): string => optional($conta->vencimento)->format('d/m/Y') ?? '')->unique();
            $emissao = $emissoes->count() === 1 && (string) $emissoes->first() !== '' ? (string) $emissoes->first() : '—';
            $vencimento = $vencimentos->count() === 1 && (string) $vencimentos->first() !== '' ? (string) $vencimentos->first() : '—';
        }

        $this->baixaItensSaldo = $contas
            ->map(fn (ContaPagar $conta): array => [
                'saldo' => round((float) $conta->saldo, 2),
                'vencimento' => optional($conta->vencimento)?->toDateString(),
            ])
            ->values()
            ->all();

        $this->baixaDados = [
            'fornecedor' => $fornecedorNome !== '' ? $fornecedorNome : '—',
            'documento' => $documento,
            'emissao' => $emissao,
            'vencimento' => $vencimento,
            'valor' => ErpMoney::formatBr(round($contas->sum(fn (ContaPagar $conta): float => (float) $conta->valor), 2)),
            'juros_pago' => ErpMoney::formatBr(round($contas->sum(fn (ContaPagar $conta): float => (float) $conta->juros), 2)),
            'multa_paga' => ErpMoney::formatBr(round($contas->sum(fn (ContaPagar $conta): float => (float) ($conta->multa ?? 0)), 2)),
            'desconto_recebido' => ErpMoney::formatBr(round($contas->sum(fn (ContaPagar $conta): float => (float) $conta->desconto), 2)),
            'valor_pago_acumulado' => ErpMoney::formatBr(round($contas->sum(fn (ContaPagar $conta): float => (float) $conta->valor_pago), 2)),
            'valor_a_pagar_titulo' => ErpMoney::formatBr($saldo),
        ];
    }

    protected function syncFormaBaixa(): void
    {
        $formaId = (int) $this->baixaFormaPagamentoId;
        $forma = null;

        foreach ($this->baixaFormasOptions as $opcao) {
            if ((int) $opcao['id'] === $formaId) {
                $forma = $opcao;
                break;
            }
        }

        $this->baixaMovimentaCaixa = (bool) ($forma['movimenta_caixa'] ?? false);
        $this->baixaExigeCheque = (bool) ($forma['exige_cheque'] ?? false);

        if (! $this->baixaExigeCheque) {
            $this->baixaCheque = '';
        }

        if (! $this->baixaMovimentaCaixa) {
            $this->baixaCaixaContaId = null;
            $this->baixaContaDestino = 'Não movimenta o caixa';

            return;
        }

        $sugerida = (int) ($forma['caixa_conta_id'] ?? 0);
        if ($sugerida <= 0 || ! collect($this->baixaCaixasOptions)->contains(fn (array $c): bool => (int) $c['id'] === $sugerida)) {
            $sugerida = (int) CaixaConta::ensureCaixaGeral()->id;
        }

        $this->baixaCaixaContaId = $sugerida;
        $this->baixaContaDestino = (string) ($forma['caixa_label'] ?? CaixaConta::LABEL_CAIXA_GERAL_PADRAO);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    protected function caixasAtivasBaixaOptions(): array
    {
        CaixaConta::ensureCaixaGeral();

        return CaixaConta::query()
            ->visiveisNaEmpresa()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome'])
            ->map(function (CaixaConta $caixa): array {
                $codigo = trim((string) ($caixa->codigo ?? ''));
                $nome = mb_strtoupper(trim((string) ($caixa->nome ?? '')), 'UTF-8');
                $label = $codigo !== '' && $nome !== ''
                    ? $codigo.' — '.$nome
                    : ($nome !== '' ? $nome : ($codigo !== '' ? $codigo : 'Caixa #'.$caixa->id));

                return [
                    'id' => (int) $caixa->id,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    protected function aplicarPadraoJurosMulta(): void
    {
        $dias = $this->maiorDiasAtrasoBaixa();
        if ($dias <= 0) {
            $this->baixaPercJuros = '0,00';
            $this->baixaJuros = '0,00';
            $this->baixaPercMulta = '0,00';
            $this->baixaMulta = '0,00';

            return;
        }

        $this->baixaPercJuros = ErpMoney::formatBr(ContaPagarBaixaJurosPadrao::PERC_JUROS_MES);
        $this->baixaPercMulta = ErpMoney::formatBr(ContaPagarBaixaJurosPadrao::PERC_MULTA);
        $this->baixaJuros = ErpMoney::formatBr($this->totalJurosPeloPercAoDia(ContaPagarBaixaJurosPadrao::PERC_JUROS_MES));
        $this->baixaMulta = ErpMoney::formatBr($this->totalMultaPeloPerc(ContaPagarBaixaJurosPadrao::PERC_MULTA));
    }

    protected function totalJurosPeloPercAoDia(float $percAoDia): float
    {
        $total = 0.0;
        foreach ($this->baixaItensSaldo as $item) {
            $dias = ContaPagarBaixaJurosPadrao::diasAtraso($item['vencimento'] ?? null, $this->baixaPagoEm);
            $total = round($total + ContaPagarBaixaJurosPadrao::juros((float) ($item['saldo'] ?? 0), $dias, $percAoDia), 2);
        }

        return $total;
    }

    protected function totalMultaPeloPerc(float $perc): float
    {
        $total = 0.0;
        foreach ($this->baixaItensSaldo as $item) {
            $dias = ContaPagarBaixaJurosPadrao::diasAtraso($item['vencimento'] ?? null, $this->baixaPagoEm);
            $total = round($total + ContaPagarBaixaJurosPadrao::multa((float) ($item['saldo'] ?? 0), $dias, $perc), 2);
        }

        return $total;
    }

    protected function percAoDiaPeloJuros(float $juros): string
    {
        $baseDias = 0.0;
        foreach ($this->baixaItensSaldo as $item) {
            $dias = ContaPagarBaixaJurosPadrao::diasAtraso($item['vencimento'] ?? null, $this->baixaPagoEm);
            $baseDias = round($baseDias + round((float) ($item['saldo'] ?? 0) * $dias, 2), 2);
        }

        if ($baseDias <= 0) {
            return '0,00';
        }

        return ErpMoney::formatBr(round(($juros / $baseDias) * 100 * ContaPagarBaixaJurosPadrao::DIAS_MES, 2));
    }

    protected function maiorDiasAtrasoBaixa(): int
    {
        $maior = 0;
        foreach ($this->baixaItensSaldo as $item) {
            $dias = ContaPagarBaixaJurosPadrao::diasAtraso($item['vencimento'] ?? null, $this->baixaPagoEm);
            $maior = max($maior, $dias);
        }

        return $maior;
    }

    protected function dinheiroBaixaMudou(mixed $novo, string $atual): bool
    {
        return abs(round(ErpMoney::parseBr($novo), 2) - round(ErpMoney::parseBr($atual), 2)) > 0.001;
    }
}
