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
use Illuminate\Support\Collection;
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

    public bool $baixaJurosEditado = false;

    public bool $baixaDescontoEditado = false;

    public bool $baixaVencimentosMistos = false;

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
        $this->baixaJurosEditado = false;
        $this->baixaDescontoEditado = false;
        $this->baixaCheque = '';
        $this->baixaData = ErpTimezone::toLocal()->toDateString();
        $this->baixaJuros = '0,00';
        $this->baixaMultaPct = '0,00';
        $this->baixaMulta = '0,00';
        $this->baixaMultaEditada = false;
        $this->baixaDesconto = '0,00';
        $this->baixaValorRecebido = $this->baixaResumoTotal;
        $this->preencherBaixa($pendentes);

        $this->baixaModalOpen = true;
    }

    public function updatedBaixaFormaPagamentoId(): void
    {
        $this->syncContaDestinoBaixa();
    }

    public function updatedBaixaData(): void
    {
        if ($this->baixaVencimentosMistos) {
            $this->baixaDiasAtraso = '—';
        } else {
            $this->atualizarDiasAtraso($this->baixaVencimentoIso);
        }

        $atualizarMulta = ! $this->baixaMultaEditada;
        $atualizarJuros = ! $this->baixaUnica && ! $this->baixaJurosEditado;

        if (! $atualizarMulta && ! $atualizarJuros) {
            return;
        }

        $contas = ContaReceber::query()->whereIn('id', $this->baixaContaIds)->get();
        if ($contas->isEmpty()) {
            return;
        }

        $data = $this->baixaData !== '' ? \Carbon\Carbon::parse($this->baixaData) : null;

        if ($atualizarMulta) {
            $multa = round($contas->sum(fn (ContaReceber $conta): float => ContaReceberJurosCarteira::calcularMulta($conta, $data)), 2);
            $this->baixaMulta = ErpMoney::formatBr($multa);
        }

        if ($atualizarJuros) {
            $juros = round($contas->sum(fn (ContaReceber $conta): float => ContaReceberJurosCarteira::jurosAdicional($conta, $data)), 2);
            $this->baixaJuros = ErpMoney::formatBr($juros);
            $this->baixaPercJuros = $this->percentualSobre($juros, ErpMoney::parseBr($this->baixaSaldo));
        }

        $this->recalcularBaixaReceber();
    }

    public function updatingBaixaMulta(mixed $value): void
    {
        if ($this->baixaUnica || $this->dinheiroMudou($value, $this->baixaMulta)) {
            $this->baixaMultaEditada = true;
        }
    }

    public function updatedBaixaMulta(mixed $value): void
    {
        $this->baixaMulta = ErpMoney::formatBr(ErpMoney::parseBr($value));
        $this->recalcularBaixaReceber();
    }

    public function updatingBaixaPercJuros(mixed $value): void
    {
        if ($this->dinheiroMudou($value, $this->baixaPercJuros)) {
            $this->baixaJurosEditado = true;
        }
    }

    public function updatedBaixaPercJuros(mixed $value): void
    {
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $perc = ErpMoney::parseBr($value);
        $this->baixaPercJuros = ErpMoney::formatBr($perc);
        $this->baixaJuros = ErpMoney::formatBr(round($saldo * ($perc / 100), 2));
        $this->recalcularBaixaReceber();
    }

    public function updatingBaixaJuros(mixed $value): void
    {
        if ($this->dinheiroMudou($value, $this->baixaJuros)) {
            $this->baixaJurosEditado = true;
        }
    }

    public function updatedBaixaJuros(mixed $value): void
    {
        $juros = ErpMoney::parseBr($value);
        $saldo = ErpMoney::parseBr($this->baixaSaldo);
        $this->baixaJuros = ErpMoney::formatBr($juros);
        $this->baixaPercJuros = $this->percentualSobre($juros, $saldo);
        $this->recalcularBaixaReceber();
    }

    public function updatingBaixaPercDesconto(mixed $value): void
    {
        if ($this->dinheiroMudou($value, $this->baixaPercDesconto)) {
            $this->baixaDescontoEditado = true;
        }
    }

    public function updatedBaixaPercDesconto(mixed $value): void
    {
        $base = round(ErpMoney::parseBr($this->baixaSaldo) + ErpMoney::parseBr($this->baixaJuros) + ErpMoney::parseBr($this->baixaMulta), 2);
        $perc = ErpMoney::parseBr($value);
        $this->baixaPercDesconto = ErpMoney::formatBr($perc);
        $this->baixaDesconto = ErpMoney::formatBr(round($base * ($perc / 100), 2));
        $this->recalcularBaixaReceber();
    }

    public function updatingBaixaDesconto(mixed $value): void
    {
        if ($this->dinheiroMudou($value, $this->baixaDesconto)) {
            $this->baixaDescontoEditado = true;
        }
    }

    public function updatedBaixaDesconto(mixed $value): void
    {
        $desconto = ErpMoney::parseBr($value);
        $base = round(ErpMoney::parseBr($this->baixaSaldo) + ErpMoney::parseBr($this->baixaJuros) + ErpMoney::parseBr($this->baixaMulta), 2);
        $this->baixaDesconto = ErpMoney::formatBr($desconto);
        $this->baixaPercDesconto = $this->percentualSobre($desconto, $base);
        $this->recalcularBaixaReceber();
    }

    public function updatingBaixaValorRecebido(mixed $value): void
    {
        if ($this->baixaUnica || $this->dinheiroMudou($value, $this->baixaValorRecebido)) {
            $this->baixaValorEditado = true;
        }
    }

    public function updatedBaixaValorRecebido(mixed $value): void
    {
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
        $this->baixaJurosEditado = false;
        $this->baixaDescontoEditado = false;
        $this->baixaVencimentosMistos = false;
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

    /**
     * @param  Collection<int, ContaReceber>  $contas
     */
    protected function preencherBaixa(Collection $contas): void
    {
        $contas->loadMissing('cliente:id,nome_razao');
        $this->carregarDestinosBaixa();

        $saldo = round($contas->sum(fn (ContaReceber $conta): float => (float) $conta->saldo), 2);
        $nomes = $contas
            ->map(fn (ContaReceber $conta): string => mb_strtoupper(trim((string) ($conta->cliente?->nome_razao ?? '')), 'UTF-8'))
            ->filter()
            ->unique()
            ->values();
        $cliente = $nomes->count() === 1 ? (string) $nomes->first() : ($nomes->isEmpty() ? '—' : 'VÁRIOS');

        if ($contas->count() === 1) {
            $conta = $contas->first();
            $documento = mb_strtoupper(trim((string) ($conta->documento ?: '—')), 'UTF-8');
            $emissao = $conta->emissao?->format('d/m/Y') ?? '—';
            $vencimento = $conta->vencimento?->format('d/m/Y') ?? '—';
            $this->baixaVencimentosMistos = false;
            $this->baixaVencimentoIso = $conta->vencimento?->toDateString() ?? '';
            $this->atualizarDiasAtraso($this->baixaVencimentoIso);
        } else {
            $documento = $contas->count().' TÍTULOS';
            $emissoes = $contas->map(fn (ContaReceber $conta): string => $conta->emissao?->format('d/m/Y') ?? '')->unique();
            $vencimentos = $contas->map(fn (ContaReceber $conta): string => $conta->vencimento?->format('d/m/Y') ?? '')->unique();
            $emissao = $emissoes->count() === 1 && (string) $emissoes->first() !== '' ? (string) $emissoes->first() : '—';
            $vencimento = $vencimentos->count() === 1 && (string) $vencimentos->first() !== '' ? (string) $vencimentos->first() : '—';
            $isos = $contas->map(fn (ContaReceber $conta): string => $conta->vencimento?->toDateString() ?? '')->unique();
            $this->baixaVencimentosMistos = $isos->count() !== 1 || (string) $isos->first() === '';
            $this->baixaVencimentoIso = $this->baixaVencimentosMistos ? '' : (string) $isos->first();
            if ($this->baixaVencimentosMistos) {
                $this->baixaDiasAtraso = '—';
            } else {
                $this->atualizarDiasAtraso($this->baixaVencimentoIso);
            }
        }

        $this->baixaDados = [
            'cliente' => $cliente,
            'documento' => $documento,
            'emissao' => $emissao,
            'vencimento' => $vencimento,
            'valor' => ErpMoney::formatBr(round($contas->sum(fn (ContaReceber $conta): float => (float) $conta->valor), 2)),
            'valor_recebido' => ErpMoney::formatBr(round($contas->sum(fn (ContaReceber $conta): float => (float) $conta->valor_recebido), 2)),
            'saldo' => ErpMoney::formatBr($saldo),
        ];
        $this->baixaSaldo = ErpMoney::formatBr($saldo);
        $this->baixaResumoTotal = $this->baixaSaldo;

        $data = $this->baixaData !== '' ? \Carbon\Carbon::parse($this->baixaData) : null;
        $pcts = $contas
            ->map(fn (ContaReceber $conta): string => number_format(ContaReceberJurosCarteira::percentuaisEfetivos($conta)['multa_pct'], 2, ',', '.'))
            ->unique();
        $this->baixaMultaPct = $pcts->count() === 1 ? (string) $pcts->first() : '—';

        $juros = round($contas->sum(fn (ContaReceber $conta): float => ContaReceberJurosCarteira::jurosAdicional($conta, $data)), 2);
        $multa = round($contas->sum(fn (ContaReceber $conta): float => ContaReceberJurosCarteira::calcularMulta($conta, $data)), 2);
        $this->baixaJuros = ErpMoney::formatBr($juros);
        $this->baixaMulta = ErpMoney::formatBr($multa);
        $this->baixaMultaEditada = false;
        $this->baixaPercJuros = $this->percentualSobre($juros, $saldo);
        $this->baixaPercDesconto = '0,00';
        $this->baixaDesconto = '0,00';
        $this->syncContaDestinoBaixa();
        $this->baixaPlanosOptions = $this->planosCreditoBaixaOptions();
        $planosTitulo = $contas->map(fn (ContaReceber $conta): int => (int) ($conta->plano_conta_id ?? 0))->unique();
        $this->baixaPlanoContaId = $this->sugerirPlanoBaixa($planosTitulo->count() === 1 ? $contas->first() : null);
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

    protected function sugerirPlanoBaixa(?ContaReceber $conta): string
    {
        $ids = array_map(fn (array $plano): int => (int) $plano['id'], $this->baixaPlanosOptions);
        $doTitulo = (int) ($conta?->plano_conta_id ?? 0);

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

        if (! $this->baixaUnica) {
            $opcoes['grupo'] = [
                'juros_editado' => $this->baixaJurosEditado,
                'multa_editada' => $this->baixaMultaEditada,
                'desconto_editado' => $this->baixaDescontoEditado,
                'valor_editado' => $this->baixaValorEditado,
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

    protected function dinheiroMudou(mixed $novo, string $atual): bool
    {
        return abs(round(ErpMoney::parseBr($novo), 2) - round(ErpMoney::parseBr($atual), 2)) > 0.001;
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
