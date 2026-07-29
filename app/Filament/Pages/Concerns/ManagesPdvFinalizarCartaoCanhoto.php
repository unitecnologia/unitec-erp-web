<?php

namespace App\Filament\Pages\Concerns;

use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use Carbon\Carbon;

/**
 * Cartão com "Aparece no Contas à Receber": captura canhoto da POS
 * (NSU, autorização, bandeira, parcelas) e gera títulos para conciliação.
 */
trait ManagesPdvFinalizarCartaoCanhoto
{
    public bool $finalizarCartaoCanhotoAberta = false;

    public bool $finalizarCartaoCanhotoConfirmado = false;

    public string $finalizarCartaoNsu = '';

    public string $finalizarCartaoAutorizacao = '';

    public string $finalizarCartaoMaquininha = '';

    public string $finalizarCartaoBandeira = '';

    public string $finalizarCartaoParcelasQtd = '1';

    public string $finalizarCartaoIntervalo = '30';

    /** @var array<int, array{documento: string, vencimento: string, valor: string, dias: int}> */
    public array $finalizarCartaoParcelasRows = [];

    public ?int $selectedFinalizarCartaoParcelaIndex = null;

    public function getFinalizarCartaoParcelasTotalLabelProperty(): string
    {
        $total = collect($this->finalizarCartaoParcelasRows)->sum(
            fn (array $row): float => ErpMoney::parseBr($row['valor'] ?? '0'),
        );

        return $this->finalizarCartaoParcelasRows === []
            ? ''
            : ErpMoney::formatBr($total);
    }

    public function getFinalizarCartaoTotalValorProperty(): float
    {
        foreach ($this->finalizarPagamentos as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::isFormaCartaoContasReceber($pagamento)) {
                continue;
            }

            $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

            if ($valor > 0) {
                return $valor;
            }
        }

        return 0.0;
    }

    protected function resetFinalizarCartaoCanhoto(): void
    {
        $this->finalizarCartaoCanhotoAberta = false;
        $this->finalizarCartaoCanhotoConfirmado = false;
        $this->finalizarCartaoNsu = '';
        $this->finalizarCartaoAutorizacao = '';
        $this->finalizarCartaoMaquininha = '';
        $this->finalizarCartaoBandeira = '';
        $this->finalizarCartaoParcelasQtd = '1';
        $this->finalizarCartaoIntervalo = '30';
        $this->finalizarCartaoParcelasRows = [];
        $this->selectedFinalizarCartaoParcelaIndex = null;
    }

    protected function finalizarTemCartaoContasReceberComValor(): bool
    {
        return $this->finalizarCartaoTotalValor > 0;
    }

    /**
     * Cartão → Contas a Receber exige canhoto/parcelas confirmados (F7).
     */
    protected function ensureCartaoCanhoto(bool $abrirSeNecessario = true): bool
    {
        if (! $this->finalizarTemCartaoContasReceberComValor()) {
            return true;
        }

        if ($this->finalizarCartaoCanhotoConfirmado && $this->finalizarCartaoParcelasRows !== []) {
            return true;
        }

        if (! $abrirSeNecessario) {
            return false;
        }

        return $this->abrirCartaoCanhoto();
    }

    protected function abrirCartaoCanhoto(): bool
    {
        $this->prepararCartaoCanhotoForm();
        $this->finalizarCartaoCanhotoAberta = true;
        $this->finalizarCartaoCanhotoConfirmado = false;
        $this->selectedFinalizarCartaoParcelaIndex = $this->finalizarCartaoParcelasRows !== [] ? 0 : null;
        $this->dispatch('erp-pdv-focus-finalizar-cartao-canhoto');

        return false;
    }

    protected function prepararCartaoCanhotoForm(): void
    {
        $pagamento = $this->resolvePagamentoCartaoContasReceber();
        $max = max(1, (int) ($pagamento['max_parcelas'] ?? 1));
        $prazo = max(0, (int) ($pagamento['prazo_cartao'] ?? 0));
        $intervalo = max(0, (int) ($pagamento['intervalo_parcelas'] ?? 30));

        if ($prazo <= 0) {
            $prazo = $intervalo > 0 ? $intervalo : 30;
        }

        if ($intervalo <= 0) {
            $intervalo = $prazo;
        }

        $this->finalizarCartaoParcelasQtd = (string) min(max(1, (int) $this->finalizarCartaoParcelasQtd), $max);
        $this->finalizarCartaoIntervalo = (string) $intervalo;

        if ($this->finalizarCartaoParcelasRows === []) {
            $this->gerarParcelasCartaoCanhoto();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolvePagamentoCartaoContasReceber(): ?array
    {
        foreach ($this->finalizarPagamentos as $pagamento) {
            if (
                PdvFinalizarPagamentosHelper::isFormaCartaoContasReceber($pagamento)
                && ErpMoney::parseBr($pagamento['valor'] ?? '0') > 0
            ) {
                return $pagamento;
            }
        }

        return null;
    }

    public function gerarParcelasCartaoCanhoto(): void
    {
        $pagamento = $this->resolvePagamentoCartaoContasReceber();
        $max = max(1, (int) ($pagamento['max_parcelas'] ?? 12));
        $qtd = max(1, min($max, (int) preg_replace('/\D/', '', $this->finalizarCartaoParcelasQtd) ?: 1));
        $intervalo = max(0, (int) preg_replace('/\D/', '', $this->finalizarCartaoIntervalo) ?: 30);

        $this->finalizarCartaoParcelasQtd = (string) $qtd;
        $this->finalizarCartaoIntervalo = (string) $intervalo;
        $this->finalizarCartaoCanhotoConfirmado = false;

        $dias = [];
        for ($i = 1; $i <= $qtd; $i++) {
            $dias[] = $intervalo * $i;
        }

        $this->gerarParcelasCartaoPorDias($dias);
        $this->selectedFinalizarCartaoParcelaIndex = 0;
        $this->dispatch('erp-pdv-focus-finalizar-cartao-canhoto');
    }

    /**
     * @param  list<int>  $dias
     */
    protected function gerarParcelasCartaoPorDias(array $dias): void
    {
        $total = round($this->finalizarCartaoTotalValor, 2);

        if ($total <= 0 || $dias === []) {
            $this->finalizarCartaoParcelasRows = [];

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
                'documento' => ($i + 1).'/'.$n,
                'vencimento' => $venc->format('d/m/Y'),
                'valor' => ErpMoney::formatBr($valor),
                'dias' => $diaInt,
            ];
        }

        $this->finalizarCartaoParcelasRows = $rows;
        $this->finalizarCartaoParcelasQtd = (string) $n;
    }

    public function selectFinalizarCartaoParcelaRow(int $index): void
    {
        if (isset($this->finalizarCartaoParcelasRows[$index])) {
            $this->selectedFinalizarCartaoParcelaIndex = $index;
        }
    }

    public function cancelFinalizarCartaoCanhoto(): void
    {
        if (! $this->finalizarCartaoCanhotoAberta) {
            return;
        }

        $this->finalizarCartaoCanhotoAberta = false;
        $this->dispatch('erp-pdv-focus-finalizar-pagamento', index: $this->selectedPagamentoIndex ?? 0);
    }

    public function concluirCartaoCanhoto(): void
    {
        if ($this->finalizarCartaoParcelasRows === []) {
            $this->notifyPdvError(
                'Gere as parcelas do cartão.',
                'Informe Qtd. Parcelas + Intervalo e use F2 | Gerar.',
            );

            return;
        }

        $this->finalizarCartaoNsu = mb_strtoupper(trim($this->finalizarCartaoNsu), 'UTF-8');
        $this->finalizarCartaoAutorizacao = mb_strtoupper(trim($this->finalizarCartaoAutorizacao), 'UTF-8');
        $this->finalizarCartaoMaquininha = mb_strtoupper(trim($this->finalizarCartaoMaquininha), 'UTF-8');
        $this->finalizarCartaoBandeira = mb_strtoupper(trim($this->finalizarCartaoBandeira), 'UTF-8');

        $this->finalizarCartaoCanhotoConfirmado = true;
        $this->finalizarCartaoCanhotoAberta = false;
        $this->dispatch('erp-pdv-focus-finalizar-ok');
    }

    /**
     * @return list<int>|null
     */
    protected function finalizarCartaoDiasList(): ?array
    {
        if (! $this->finalizarTemCartaoContasReceberComValor() || ! $this->finalizarCartaoCanhotoConfirmado) {
            return null;
        }

        $dias = collect($this->finalizarCartaoParcelasRows)
            ->map(fn (array $row): int => (int) ($row['dias'] ?? 0))
            ->filter(fn (int $d): bool => $d >= 0)
            ->values()
            ->all();

        return $dias !== [] ? $dias : null;
    }

    /**
     * @return array{nsu: string, autorizacao: string, maquininha: string, bandeira: string, dias: list<int>}|null
     */
    protected function finalizarCartaoCanhotoPayload(): ?array
    {
        $dias = $this->finalizarCartaoDiasList();

        if ($dias === null) {
            return null;
        }

        return [
            'nsu' => trim($this->finalizarCartaoNsu),
            'autorizacao' => trim($this->finalizarCartaoAutorizacao),
            'maquininha' => trim($this->finalizarCartaoMaquininha),
            'bandeira' => trim($this->finalizarCartaoBandeira),
            'dias' => $dias,
        ];
    }

    protected function validaCartaoCanhotoFinalizar(): ?string
    {
        if (! $this->finalizarTemCartaoContasReceberComValor()) {
            return null;
        }

        if (! $this->finalizarCartaoCanhotoConfirmado || $this->finalizarCartaoParcelasRows === []) {
            return 'Informe o canhoto da POS (parcelas do cartão) para Contas à Receber.';
        }

        return null;
    }
}
