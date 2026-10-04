<?php

namespace App\Filament\Resources\ContaPagarResource\Pages\Concerns;

use App\Models\ContaPagar;
use App\Models\PlanoConta;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaPagarBaixaService;
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

    public bool $baixaExigeCheque = false;

    public string $baixaCheque = '';

    public bool $baixaUnica = true;

    public bool $baixaValorPagoEditado = false;

    public bool $baixaJurosEditado = false;

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
        'desconto_recebido' => '0,00',
        'valor_pago_acumulado' => '0,00',
        'valor_a_pagar_titulo' => '0,00',
    ];

    public string $baixaSaldo = '0,00';

    public string $baixaPercJuros = '0,00';

    public string $baixaJuros = '0,00';

    public string $baixaSaldoComJuros = '0,00';

    public string $baixaPercDesconto = '0,00';

    public string $baixaDesconto = '0,00';

    public string $baixaValorAPagar = '0,00';

    public string $baixaValorPago = '0,00';

    public string $baixaPagoEm = '';

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
        $this->baixaDescontoEditado = false;
        $this->baixaCheque = '';
        $this->preencherDadosBaixa($contas, $saldo);
        $this->syncFormaBaixa();

        $this->baixaSaldo = ErpMoney::formatBr($saldo);
        $this->baixaPercJuros = '0,00';
        $this->baixaJuros = '0,00';
        $this->baixaPercDesconto = '0,00';
        $this->baixaDesconto = '0,00';
        $this->baixaPagoEm = ErpTimezone::toLocal()->toDateString();
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
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $perc = ErpMoney::parseBr($value);
        $this->baixaPercJuros = ErpMoney::formatBr($perc);
        $this->baixaJuros = ErpMoney::formatBr(round($saldo * ($perc / 100), 2));
        $this->recalcularBaixaTotais();
    }

    public function updatingBaixaJuros(mixed $value): void
    {
        if ($this->dinheiroBaixaMudou($value, $this->baixaJuros)) {
            $this->baixaJurosEditado = true;
        }
    }

    public function updatedBaixaJuros(mixed $value): void
    {
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $juros = ErpMoney::parseBr($value);
        $this->baixaJuros = ErpMoney::formatBr($juros);
        $this->baixaPercJuros = $saldo > 0
            ? ErpMoney::formatBr(round(($juros / $saldo) * 100, 2))
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
        $base = ErpMoney::parseBr($this->baixaSaldo) + ErpMoney::parseBr($this->baixaJuros);
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
        $base = ErpMoney::parseBr($this->baixaSaldo) + ErpMoney::parseBr($this->baixaJuros);
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
        $this->baixaValorPago = ErpMoney::formatBr(ErpMoney::parseBr($value));
    }

    protected function recalcularBaixaTotais(): void
    {
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $juros = ErpMoney::parseBr($this->baixaJuros);
        $desconto = ErpMoney::parseBr($this->baixaDesconto);
        $saldoComJuros = round($saldo + $juros, 2);
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
        $this->baixaExigeCheque = false;
        $this->baixaCheque = '';
        $this->baixaUnica = true;
        $this->baixaValorPagoEditado = false;
        $this->baixaJurosEditado = false;
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
            'desconto_recebido' => '0,00',
            'valor_pago_acumulado' => '0,00',
            'valor_a_pagar_titulo' => '0,00',
        ];
        $this->baixaSaldo = '0,00';
        $this->baixaPercJuros = '0,00';
        $this->baixaJuros = '0,00';
        $this->baixaSaldoComJuros = '0,00';
        $this->baixaPercDesconto = '0,00';
        $this->baixaDesconto = '0,00';
        $this->baixaValorAPagar = '0,00';
        $this->baixaValorPago = '0,00';
        $this->baixaPagoEm = '';
    }

    protected function resolvePlanoContaPadraoFornecedores(): ?int
    {
        $ids = array_map(fn (array $plano): int => (int) $plano['id'], $this->baixaPlanosOptions);

        $porCodigo = (int) PlanoConta::query()
            ->where('codigo', 1)
            ->where('ativo', true)
            ->where('dc', 'D')
            ->value('id');

        if ($porCodigo > 0 && in_array($porCodigo, $ids, true)) {
            return $porCodigo;
        }

        foreach ($this->baixaPlanosOptions as $plano) {
            $label = mb_strtoupper((string) ($plano['label'] ?? ''), 'UTF-8');
            $id = (int) ($plano['id'] ?? 0);

            if ($id > 0 && str_contains($label, 'FORNECEDOR')) {
                return $id;
            }
        }

        return (int) ($this->baixaPlanosOptions[0]['id'] ?? 0) ?: null;
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

        if ($this->baixaMovimentaCaixa && (int) $this->baixaCaixaContaId <= 0) {
            Notification::make()
                ->title('A forma de pagamento não possui conta de destino.')
                ->body('Cadastre a conta de destino na forma para lançar no caixa.')
                ->warning()
                ->send();

            return;
        }

        $opcoes = [
            'plano_conta_id' => (int) $this->baixaPlanoContaId,
            'perc_juros' => ErpMoney::parseBr($this->baixaPercJuros),
            'juros' => ErpMoney::parseBr($this->baixaJuros),
            'perc_desconto' => ErpMoney::parseBr($this->baixaPercDesconto),
            'desconto' => ErpMoney::parseBr($this->baixaDesconto),
            'valor_pago' => ErpMoney::parseBr($this->baixaValorPago),
            'pago_em' => $this->baixaPagoEm,
            'numero_cheque' => $this->baixaExigeCheque ? $this->baixaCheque : '',
        ];

        if (! $this->baixaUnica) {
            $opcoes['grupo'] = [
                'juros_editado' => $this->baixaJurosEditado,
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

        $this->baixaDados = [
            'fornecedor' => $fornecedorNome !== '' ? $fornecedorNome : '—',
            'documento' => $documento,
            'emissao' => $emissao,
            'vencimento' => $vencimento,
            'valor' => ErpMoney::formatBr(round($contas->sum(fn (ContaPagar $conta): float => (float) $conta->valor), 2)),
            'juros_pago' => ErpMoney::formatBr(round($contas->sum(fn (ContaPagar $conta): float => (float) $conta->juros), 2)),
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
        $this->baixaCaixaContaId = $this->baixaMovimentaCaixa
            ? ((int) ($forma['caixa_conta_id'] ?? 0) ?: null)
            : null;
        $this->baixaContaDestino = (string) ($forma['caixa_label'] ?? '—');

        if (! $this->baixaExigeCheque) {
            $this->baixaCheque = '';
        }
    }

    protected function dinheiroBaixaMudou(mixed $novo, string $atual): bool
    {
        return abs(round(ErpMoney::parseBr($novo), 2) - round(ErpMoney::parseBr($atual), 2)) > 0.001;
    }
}
