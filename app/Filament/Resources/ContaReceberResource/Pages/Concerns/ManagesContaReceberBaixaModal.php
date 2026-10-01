<?php

namespace App\Filament\Resources\ContaReceberResource\Pages\Concerns;

use App\Models\ContaReceber;
use App\Models\FormaPagamento;
use App\Models\PlanoConta;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaReceberBaixaService;
use App\Support\Erp\Financeiro\ContaReceberJurosCarteira;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesContaReceberBaixaModal
{
    public bool $baixaModalOpen = false;

    /** @var list<int> */
    public array $baixaContaIds = [];

    public ?int $baixaFormaPagamentoId = null;

    public string $baixaResumoQtd = '0';

    public string $baixaResumoTotal = '0,00';

    /** @var list<array{id: int, label: string, tipo: string|null}> */
    public array $baixaFormasOptions = [];

    public bool $baixaUnica = false;

    public string $baixaJuros = '0,00';

    public string $baixaMultaPct = '0,00';

    public string $baixaMulta = '0,00';

    public bool $baixaMultaEditada = false;

    public string $baixaDesconto = '0,00';

    public string $baixaValorRecebido = '0,00';

    public string $baixaData = '';

    public string $baixaCheque = '';

    public bool $baixaValorEditado = false;

    /** @var array<string, string> */
    public array $baixaDados = [];

    public string $baixaSaldo = '0,00';

    public string $baixaPercJuros = '0,00';

    public string $baixaSaldoComJuros = '0,00';

    public string $baixaPercDesconto = '0,00';

    public string $baixaValorAReceber = '0,00';

    public string $baixaDiasAtraso = '0';

    public string $baixaVencimentoIso = '';

    public string $baixaContaDestino = '—';

    public string $baixaPlanoContas = '—';

    public string $baixaPlanoContaId = '';

    /** @var list<array{id: int, label: string}> */
    public array $baixaPlanosOptions = [];

    /** @var array<int, string> */
    public array $baixaDestinosPorForma = [];

    public function baixarConta(): void
    {
        $ids = $this->resolverIdsParaBaixa();

        if ($ids === []) {
            return;
        }

        $contas = ContaReceber::query()
            ->whereIn('id', $ids)
            ->get();

        $pendentes = $contas->filter(fn (ContaReceber $c): bool => (float) $c->saldo > 0);

        if ($pendentes->isEmpty()) {
            Notification::make()
                ->title('Nenhuma conta com saldo para baixar.')
                ->warning()
                ->send();

            return;
        }

        $service = app(ContaReceberBaixaService::class);
        $this->baixaFormasOptions = $service->formasDisponiveis();

        if ($this->baixaFormasOptions === []) {
            Notification::make()
                ->title('Cadastre um meio de pagamento')
                ->body('Nenhuma forma de pagamento disponível para Contas a Receber.')
                ->warning()
                ->send();

            return;
        }

        $this->baixaContaIds = $pendentes->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        $this->baixaResumoQtd = (string) count($this->baixaContaIds);
        $this->baixaResumoTotal = ErpMoney::formatBr((float) $pendentes->sum(fn (ContaReceber $c): float => (float) $c->saldo));
        $this->baixaFormaPagamentoId = (int) ($this->baixaFormasOptions[0]['id'] ?? 0);
        $this->baixaUnica = count($this->baixaContaIds) === 1;
        $this->baixaValorEditado = false;
        $this->baixaCheque = '';
        $this->baixaData = ErpTimezone::toLocal()->toDateString();
        $this->baixaJuros = '0,00';
        $this->baixaMultaPct = '0,00';
        $this->baixaMulta = '0,00';
        $this->baixaMultaEditada = false;
        $this->baixaDesconto = '0,00';
        $this->baixaValorRecebido = $this->baixaResumoTotal;

        if ($this->baixaUnica) {
            $conta = $pendentes->first();
            $saldo = round((float) $conta->saldo, 2);
            $dataPagamento = \Carbon\Carbon::parse($this->baixaData);
            $jurosSugerido = round(max(0, ContaReceberJurosCarteira::calcularValor($conta, $dataPagamento) - (float) $conta->juros), 2);
            $this->baixaJuros = ErpMoney::formatBr($jurosSugerido);
            $this->baixaValorRecebido = ErpMoney::formatBr(round($saldo + $jurosSugerido, 2));
            $this->preencherBaixaUnica($conta);
        }

        $this->baixaModalOpen = true;
    }

    public function updatedBaixaFormaPagamentoId(): void
    {
        $this->syncContaDestinoBaixa();
    }

    public function updatedBaixaData(): void
    {
        $this->atualizarDiasAtraso($this->baixaVencimentoIso);

        if (! $this->baixaUnica || $this->baixaMultaEditada) {
            return;
        }

        $conta = ContaReceber::query()->find((int) ($this->baixaContaIds[0] ?? 0));
        if (! $conta) {
            return;
        }

        $data = $this->baixaData !== '' ? \Carbon\Carbon::parse($this->baixaData) : null;
        $this->baixaMulta = ErpMoney::formatBr(ContaReceberJurosCarteira::calcularMulta($conta, $data));
        $this->recalcularBaixaReceber();
    }

    public function updatedBaixaMulta(mixed $value): void
    {
        $this->baixaMultaEditada = true;
        $this->baixaMulta = ErpMoney::formatBr(ErpMoney::parseBr($value));
        $this->recalcularBaixaReceber();
    }

    public function updatedBaixaPercJuros(mixed $value): void
    {
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $perc = ErpMoney::parseBr($value);
        $this->baixaPercJuros = ErpMoney::formatBr($perc);
        $this->baixaJuros = ErpMoney::formatBr(round($saldo * ($perc / 100), 2));
        $this->recalcularBaixaReceber();
    }

    public function updatedBaixaJuros(mixed $value): void
    {
        $juros = ErpMoney::parseBr($value);
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $this->baixaJuros = ErpMoney::formatBr($juros);
        $this->baixaPercJuros = $this->percentualSobre($juros, $saldo);
        $this->recalcularBaixaReceber();
    }

    public function updatedBaixaPercDesconto(mixed $value): void
    {
        $base = round(ErpMoney::parseBr($this->baixaSaldo) + ErpMoney::parseBr($this->baixaJuros) + ErpMoney::parseBr($this->baixaMulta), 2);
        $perc = ErpMoney::parseBr($value);
        $this->baixaPercDesconto = ErpMoney::formatBr($perc);
        $this->baixaDesconto = ErpMoney::formatBr(round($base * ($perc / 100), 2));
        $this->recalcularBaixaReceber();
    }

    public function updatedBaixaDesconto(mixed $value): void
    {
        $desconto = ErpMoney::parseBr($value);
        $base = round(ErpMoney::parseBr($this->baixaSaldo) + ErpMoney::parseBr($this->baixaJuros) + ErpMoney::parseBr($this->baixaMulta), 2);
        $this->baixaDesconto = ErpMoney::formatBr($desconto);
        $this->baixaPercDesconto = $this->percentualSobre($desconto, $base);
        $this->recalcularBaixaReceber();
    }

    public function updatedBaixaValorRecebido(mixed $value): void
    {
        $this->baixaValorEditado = true;
        $this->baixaValorRecebido = ErpMoney::formatBr(ErpMoney::parseBr($value));
    }

    protected function recalcularBaixaReceber(): void
    {
        $saldo = ErpMoney::parseBr($this->baixaResumoTotal);
        $juros = ErpMoney::parseBr($this->baixaJuros);
        $multa = ErpMoney::parseBr($this->baixaMulta);
        $saldoComJuros = round($saldo + $juros, 2);
        $base = round($saldoComJuros + $multa, 2);
        $desconto = min(ErpMoney::parseBr($this->baixaDesconto), $base);
        $devido = round(max(0, $base - $desconto), 2);
        $this->baixaDesconto = ErpMoney::formatBr($desconto);
        $this->baixaSaldoComJuros = ErpMoney::formatBr($saldoComJuros);
        $this->baixaValorAReceber = ErpMoney::formatBr($devido);
        $this->baixaPercDesconto = $this->percentualSobre($desconto, $base);

        if (! $this->baixaValorEditado) {
            $this->baixaValorRecebido = ErpMoney::formatBr($devido);
        }
    }

    public function closeBaixaModal(): void
    {
        $this->baixaModalOpen = false;
        $this->baixaContaIds = [];
        $this->baixaFormaPagamentoId = null;
        $this->baixaResumoQtd = '0';
        $this->baixaResumoTotal = '0,00';
        $this->baixaFormasOptions = [];
        $this->baixaUnica = false;
        $this->baixaJuros = '0,00';
        $this->baixaMultaPct = '0,00';
        $this->baixaMulta = '0,00';
        $this->baixaMultaEditada = false;
        $this->baixaDesconto = '0,00';
        $this->baixaValorRecebido = '0,00';
        $this->baixaData = '';
        $this->baixaCheque = '';
        $this->baixaValorEditado = false;
        $this->baixaDados = [];
        $this->baixaSaldo = '0,00';
        $this->baixaPercJuros = '0,00';
        $this->baixaSaldoComJuros = '0,00';
        $this->baixaPercDesconto = '0,00';
        $this->baixaValorAReceber = '0,00';
        $this->baixaDiasAtraso = '0';
        $this->baixaVencimentoIso = '';
        $this->baixaContaDestino = '—';
        $this->baixaPlanoContas = '—';
        $this->baixaPlanoContaId = '';
        $this->baixaPlanosOptions = [];
        $this->baixaDestinosPorForma = [];
    }

    protected function preencherBaixaUnica(ContaReceber $conta): void
    {
        $conta->loadMissing('cliente:id,nome_razao');
        $this->carregarDestinosBaixa();

        $saldo = round((float) $conta->saldo, 2);
        $cliente = trim((string) ($conta->cliente?->nome_razao ?? ''));

        $this->baixaDados = [
            'cliente' => $cliente !== '' ? mb_strtoupper($cliente, 'UTF-8') : '—',
            'documento' => mb_strtoupper(trim((string) ($conta->documento ?: '—')), 'UTF-8'),
            'emissao' => $conta->emissao?->format('d/m/Y') ?? '—',
            'vencimento' => $conta->vencimento?->format('d/m/Y') ?? '—',
            'valor' => ErpMoney::formatBr((float) $conta->valor),
            'valor_recebido' => ErpMoney::formatBr((float) $conta->valor_recebido),
            'saldo' => ErpMoney::formatBr($saldo),
        ];
        $this->baixaSaldo = ErpMoney::formatBr($saldo);
        $data = $this->baixaData !== '' ? \Carbon\Carbon::parse($this->baixaData) : null;
        $this->baixaMultaPct = number_format(ContaReceberJurosCarteira::percentuaisEfetivos($conta)['multa_pct'], 2, ',', '.');
        $this->baixaMulta = ErpMoney::formatBr(ContaReceberJurosCarteira::calcularMulta($conta, $data));
        $this->baixaMultaEditada = false;
        $this->baixaPercJuros = $this->percentualSobre(ErpMoney::parseBr($this->baixaJuros), $saldo);
        $this->baixaPercDesconto = '0,00';
        $this->baixaVencimentoIso = $conta->vencimento?->toDateString() ?? '';
        $this->atualizarDiasAtraso($this->baixaVencimentoIso);
        $this->syncContaDestinoBaixa();
        $this->baixaPlanosOptions = $this->planosCreditoBaixaOptions();
        $this->baixaPlanoContaId = $this->sugerirPlanoBaixa($conta);
        $this->recalcularBaixaReceber();
    }

    protected function carregarDestinosBaixa(): void
    {
        $ids = collect($this->baixaFormasOptions)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->all();

        $this->baixaDestinosPorForma = FormaPagamento::query()
            ->with('contaDestino:id,codigo,nome')
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(function (FormaPagamento $forma): array {
                $caixa = $forma->contaDestino;
                $codigo = trim((string) ($caixa->codigo ?? ''));
                $nome = mb_strtoupper(trim((string) ($caixa->nome ?? '')), 'UTF-8');
                $label = $caixa
                    ? ($codigo !== '' && $nome !== '' ? $codigo.' — '.$nome : ($nome !== '' ? $nome : ($codigo !== '' ? $codigo : '—')))
                    : '—';

                return [(int) $forma->id => $label];
            })
            ->all();
    }

    protected function syncContaDestinoBaixa(): void
    {
        $id = (int) $this->baixaFormaPagamentoId;
        $this->baixaContaDestino = $this->baixaDestinosPorForma[$id] ?? '—';
    }

    protected function atualizarDiasAtraso(string $vencimentoIso): void
    {
        if ($vencimentoIso === '' || $this->baixaData === '') {
            $this->baixaDiasAtraso = '0';

            return;
        }

        $vencimento = ErpTimezone::toLocal($vencimentoIso)->startOfDay();
        $pagamento = ErpTimezone::toLocal($this->baixaData)->startOfDay();
        $this->baixaDiasAtraso = $pagamento->gt($vencimento)
            ? (string) $vencimento->diffInDays($pagamento)
            : '0';
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    protected function planosCreditoBaixaOptions(): array
    {
        return PlanoConta::query()
            ->where('ativo', true)
            ->where('dc', 'C')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descricao'])
            ->map(fn (PlanoConta $plano): array => [
                'id' => (int) $plano->id,
                'label' => trim((string) $plano->codigo).' — '.mb_strtoupper((string) $plano->descricao, 'UTF-8'),
            ])
            ->values()
            ->all();
    }

    protected function sugerirPlanoBaixa(ContaReceber $conta): string
    {
        $ids = array_map(fn (array $plano): int => (int) $plano['id'], $this->baixaPlanosOptions);
        $doTitulo = (int) ($conta->plano_conta_id ?? 0);

        if ($doTitulo > 0 && in_array($doTitulo, $ids, true)) {
            return (string) $doTitulo;
        }

        $sugerido = (int) PlanoConta::query()
            ->where('codigo', 103)
            ->where('dc', 'C')
            ->where('ativo', true)
            ->value('id');

        return $sugerido > 0 ? (string) $sugerido : '';
    }

    protected function percentualSobre(float $parte, float $base): string
    {
        if ($base <= 0) {
            return '0,00';
        }

        return ErpMoney::formatBr(round(($parte / $base) * 100, 2));
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

        $opcoes = [];

        if ($this->baixaUnica) {
            if ((int) $this->baixaPlanoContaId <= 0) {
                Notification::make()
                    ->title('Selecione o plano de contas.')
                    ->warning()
                    ->send();

                return;
            }

            $opcoes = [
                'juros' => ErpMoney::parseBr($this->baixaJuros),
                'multa' => ErpMoney::parseBr($this->baixaMulta),
                'desconto' => ErpMoney::parseBr($this->baixaDesconto),
                'valor_recebido' => ErpMoney::parseBr($this->baixaValorRecebido),
                'recebido_em' => $this->baixaData,
                'numero_cheque' => $this->baixaCheque,
                'plano_conta_id' => (int) $this->baixaPlanoContaId,
            ];
        }

        try {
            $resultado = app(ContaReceberBaixaService::class)
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
        $this->pushContaReceberListRefresh(skipPageRender: false);

        if ($resultado['ok'] < 1) {
            Notification::make()
                ->title('Nenhuma conta foi baixada.')
                ->warning()
                ->send();

            return;
        }

        $qtd = $resultado['ok'];
        Notification::make()
            ->title($qtd === 1 ? 'Conta baixada.' : "{$qtd} contas baixadas.")
            ->body('Total recebido: R$ '.ErpMoney::formatBr((float) $resultado['total']))
            ->success()
            ->send();
    }

    /**
     * @return list<int>
     */
    protected function resolverIdsParaBaixa(): array
    {
        $ids = collect($this->selecionadosParaBaixa)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($ids !== []) {
            if ($this->clienteFilter === 'todos' || ! is_numeric($this->clienteFilter)) {
                Notification::make()
                    ->title('Selecione um cliente antes de marcar contas para baixa.')
                    ->warning()
                    ->send();

                return [];
            }

            return $ids;
        }

        if (! $this->highlightedRecordIdOrNotify('baixar')) {
            return [];
        }

        return [(int) $this->highlightedRecordId];
    }
}
