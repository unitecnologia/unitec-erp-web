<?php

namespace App\Filament\Pages\Concerns;

use App\Models\FormaPagamento;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use Carbon\Carbon;

/**
 * Cartão Contas a Receber: canhoto POS (NSU/auth/bandeira + parcelas).
 * SuperTEF Crédito: reusa o mesmo overlay de parcelas (Qtd/Intervalo/F2/F7 e F8 tabelas),
 * sem NSU manual e sem gerar títulos CR — installment_count vai para a adquirente.
 */
trait ManagesPdvFinalizarCartaoCanhoto
{
    public bool $finalizarCartaoCanhotoAberta = false;

    public bool $finalizarCartaoCanhotoConfirmado = false;

    /** true = overlay em modo SuperTEF Crédito (sem canhoto manual / sem CR). */
    public bool $finalizarCartaoModoSuperTef = false;

    public string $finalizarCartaoNsu = "";

    public string $finalizarCartaoAutorizacao = "";

    public string $finalizarCartaoMaquininha = "";

    public string $finalizarCartaoBandeira = "";

    public string $finalizarCartaoParcelasQtd = "1";

    public string $finalizarCartaoIntervalo = "30";

    /** @var array<int, array{documento: string, vencimento: string, valor: string, dias: int}> */
    public array $finalizarCartaoParcelasRows = [];

    public ?int $selectedFinalizarCartaoParcelaIndex = null;

    public bool $finalizarCartaoTabelasListaAberta = false;

    /** @var array<int, array{tabela_prazo_id: int, dias: string, label: string}> */
    public array $finalizarCartaoTabelasPredefinidas = [];

    public ?int $selectedFinalizarCartaoTabelaIndex = null;

    public ?int $finalizarCartaoTabelaId = null;

    public function getFinalizarCartaoParcelasTotalLabelProperty(): string
    {
        $total = collect($this->finalizarCartaoParcelasRows)->sum(
            fn (array $row): float => ErpMoney::parseBr($row["valor"] ?? "0"),
        );

        return $this->finalizarCartaoParcelasRows === []
            ? ""
            : ErpMoney::formatBr($total);
    }

    public function getFinalizarCartaoTotalValorProperty(): float
    {
        $pagamento = $this->resolvePagamentoCartaoParcelamentoAtivo();
        if ($pagamento === null) {
            return 0.0;
        }

        return ErpMoney::parseBr($pagamento["valor"] ?? "0");
    }

    public function getFinalizarCartaoModoPrazoProperty(): string
    {
        $pagamento = $this->resolvePagamentoCartaoParcelamentoAtivo();

        return FormaPagamento::normalizeModoPrazo($pagamento["modo_prazo"] ?? null);
    }

    /** POS canhoto: à vista / Financeiro 1x — esconde Qtd/Intervalo/F2 (NSU permanece). */
    public function getFinalizarCartaoOcultarEscolhaParcelasProperty(): bool
    {
        if ($this->finalizarCartaoModoSuperTef) {
            return false;
        }

        $pagamento = $this->resolvePagamentoCartaoParcelamentoAtivo();

        return $pagamento !== null
            && PdvFinalizarPagamentosHelper::deveAssumirParcelaUnicaSemSelecao($pagamento);
    }

    protected function resetFinalizarCartaoCanhoto(): void
    {
        $this->finalizarCartaoCanhotoAberta = false;
        $this->finalizarCartaoCanhotoConfirmado = false;
        $this->finalizarCartaoModoSuperTef = false;
        $this->finalizarCartaoNsu = "";
        $this->finalizarCartaoAutorizacao = "";
        $this->finalizarCartaoMaquininha = "";
        $this->finalizarCartaoBandeira = "";
        $this->finalizarCartaoParcelasQtd = "1";
        $this->finalizarCartaoIntervalo = "30";
        $this->finalizarCartaoParcelasRows = [];
        $this->selectedFinalizarCartaoParcelaIndex = null;
        $this->finalizarCartaoTabelasListaAberta = false;
        $this->finalizarCartaoTabelasPredefinidas = [];
        $this->selectedFinalizarCartaoTabelaIndex = null;
        $this->finalizarCartaoTabelaId = null;
    }

    protected function finalizarTemCartaoContasReceberComValor(): bool
    {
        foreach ($this->finalizarPagamentos as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::precisaCanhotoPosManual($pagamento)) {
                continue;
            }
            if (ErpMoney::parseBr($pagamento["valor"] ?? "0") > 0) {
                return true;
            }
        }

        return false;
    }

    protected function finalizarTemSuperTefCreditoComValor(): bool
    {
        foreach ($this->finalizarPagamentos as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::isFormaSuperTefCredito($pagamento)) {
                continue;
            }
            if (ErpMoney::parseBr($pagamento["valor"] ?? "0") > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cartão POS manual (usa_pos ou legado Contas a Receber) exige canhoto confirmado (F7).
     */
    protected function ensureCartaoCanhoto(bool $abrirSeNecessario = true): bool
    {
        if (! $this->finalizarTemCartaoContasReceberComValor()) {
            return true;
        }

        if ($this->finalizarCartaoModoSuperTef) {
            // Overlay estava em SuperTEF; POS CR precisa reabrir em modo canhoto.
            $this->finalizarCartaoCanhotoConfirmado = false;
        }

        if ($this->finalizarCartaoCanhotoConfirmado && $this->finalizarCartaoParcelasRows !== [] && ! $this->finalizarCartaoModoSuperTef) {
            return true;
        }

        if (! $abrirSeNecessario) {
            return false;
        }

        return $this->abrirCartaoCanhoto(superTef: false);
    }

    /**
     * SuperTEF Crédito: exige confirmação das parcelas no mesmo overlay do cartão.
     */
    protected function ensureSuperTefCreditoParcelas(bool $abrirSeNecessario = true): bool
    {
        if (! $this->finalizarTemSuperTefCreditoComValor()) {
            return true;
        }

        if ($this->pagamentoCartaoAssumeParcelaUnica(superTef: true)) {
            $this->aplicarCartaoAVistaSemEscolhaParcelas(superTef: true);

            return true;
        }

        if (
            $this->finalizarCartaoCanhotoConfirmado
            && $this->finalizarCartaoModoSuperTef
            && $this->finalizarCartaoParcelasRows !== []
        ) {
            $this->sincronizarParcelasSuperTefNoPagamento();

            return true;
        }

        if (! $abrirSeNecessario) {
            return false;
        }

        return $this->abrirCartaoCanhoto(superTef: true);
    }

    protected function abrirCartaoCanhoto(bool $superTef = false): bool
    {
        $this->finalizarCartaoModoSuperTef = $superTef;
        $this->finalizarCartaoTabelasListaAberta = false;
        $this->finalizarCartaoTabelasPredefinidas = [];
        $this->selectedFinalizarCartaoTabelaIndex = null;
        $this->finalizarCartaoTabelaId = null;
        $this->prepararCartaoCanhotoForm();
        $this->finalizarCartaoCanhotoAberta = true;
        $this->finalizarCartaoCanhotoConfirmado = false;
        $this->selectedFinalizarCartaoParcelaIndex = $this->finalizarCartaoParcelasRows !== [] ? 0 : null;
        $this->dispatch("erp-pdv-focus-finalizar-cartao-canhoto");

        return false;
    }

    protected function prepararCartaoCanhotoForm(): void
    {
        $pagamento = $this->resolvePagamentoCartaoParcelamentoAtivo();
        $max = max(1, (int) ($pagamento["max_parcelas"] ?? 1));
        $prazo = max(0, (int) ($pagamento["prazo_cartao"] ?? 0));
        $intervalo = max(0, (int) ($pagamento["intervalo_parcelas"] ?? 0));
        $modo = FormaPagamento::normalizeModoPrazo($pagamento["modo_prazo"] ?? null);

        if ($prazo <= 0) {
            $prazo = $intervalo > 0 ? $intervalo : 30;
        }

        if ($intervalo <= 0) {
            $intervalo = $prazo;
        }

        $this->finalizarCartaoIntervalo = (string) $intervalo;

        // POS canhoto: flag à vista ou Financeiro max=1 → 1x sem pedir escolha (NSU permanece).
        if (! $this->finalizarCartaoModoSuperTef && PdvFinalizarPagamentosHelper::deveAssumirParcelaUnicaSemSelecao($pagamento ?? [])) {
            $this->finalizarCartaoParcelasQtd = "1";
            $this->finalizarCartaoTabelaId = null;
            $dias = PdvFinalizarPagamentosHelper::diasParcelaUnicaFinanceiro($pagamento ?? []);
            $this->finalizarCartaoIntervalo = (string) ($dias[0] ?? $intervalo);
            $this->gerarParcelasCartaoPorDias($dias);

            return;
        }

        if ($this->finalizarCartaoModoSuperTef && $modo === FormaPagamento::MODO_PRAZO_TABELA) {
            $this->refreshCartaoTabelasDaForma();
            // Não inventa 1..N: só condições cadastradas (F8).
            if ($this->finalizarCartaoParcelasRows === [] && count($this->finalizarCartaoTabelasPredefinidas) === 1) {
                $this->selectedFinalizarCartaoTabelaIndex = 0;
                $this->aplicarTabelaCartaoPredefinida();
            } elseif ($this->finalizarCartaoParcelasRows === []) {
                $this->finalizarCartaoParcelasQtd = "1";
                $this->finalizarCartaoTabelasListaAberta = $this->finalizarCartaoTabelasPredefinidas !== [];
            }

            return;
        }

        $this->finalizarCartaoParcelasQtd = (string) min(max(1, (int) $this->finalizarCartaoParcelasQtd), $max);

        if ($this->finalizarCartaoParcelasRows === []) {
            $this->gerarParcelasCartaoCanhoto();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolvePagamentoCartaoParcelamentoAtivo(): ?array
    {
        if ($this->finalizarCartaoModoSuperTef) {
            foreach ($this->finalizarPagamentos as $pagamento) {
                if (
                    PdvFinalizarPagamentosHelper::isFormaSuperTefCredito($pagamento)
                    && ErpMoney::parseBr($pagamento["valor"] ?? "0") > 0
                ) {
                    return $pagamento;
                }
            }

            return null;
        }

        return $this->resolvePagamentoCartaoContasReceber();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolvePagamentoCartaoContasReceber(): ?array
    {
        foreach ($this->finalizarPagamentos as $pagamento) {
            if (
                PdvFinalizarPagamentosHelper::precisaCanhotoPosManual($pagamento)
                && ErpMoney::parseBr($pagamento["valor"] ?? "0") > 0
            ) {
                return $pagamento;
            }
        }

        return null;
    }

    public function gerarParcelasCartaoCanhoto(): void
    {
        $pagamento = $this->resolvePagamentoCartaoParcelamentoAtivo();
        $modo = FormaPagamento::normalizeModoPrazo($pagamento["modo_prazo"] ?? null);

        if ($this->finalizarCartaoModoSuperTef && $modo === FormaPagamento::MODO_PRAZO_TABELA) {
            $this->notifyPdvError(
                "Selecione uma tabela de prazo.",
                "No modo Tabela de Prazo use F8 | Tabelas (condições cadastradas na forma).",
            );

            return;
        }

        if (PdvFinalizarPagamentosHelper::deveAssumirParcelaUnicaSemSelecao($pagamento ?? [])) {
            $this->finalizarCartaoParcelasQtd = "1";
        }

        $max = max(1, (int) ($pagamento["max_parcelas"] ?? 12));
        if ($this->finalizarCartaoModoSuperTef && method_exists($this, "finalizarSuperTefMaxParcelasUi")) {
            $max = min($max, max(1, (int) $this->finalizarSuperTefMaxParcelasUi()));
        }

        $qtd = max(1, min($max, (int) preg_replace("/\D/", "", $this->finalizarCartaoParcelasQtd) ?: 1));
        $intervalo = max(0, (int) preg_replace("/\D/", "", $this->finalizarCartaoIntervalo) ?: 30);

        $this->finalizarCartaoParcelasQtd = (string) $qtd;
        $this->finalizarCartaoIntervalo = (string) $intervalo;
        $this->finalizarCartaoCanhotoConfirmado = false;
        $this->finalizarCartaoTabelaId = null;

        $dias = [];
        for ($i = 1; $i <= $qtd; $i++) {
            $dias[] = $intervalo * $i;
        }

        $this->gerarParcelasCartaoPorDias($dias);
        $this->selectedFinalizarCartaoParcelaIndex = 0;
        $this->dispatch("erp-pdv-focus-finalizar-cartao-canhoto");
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
                "documento" => ($i + 1)."/".$n,
                "vencimento" => $venc->format("d/m/Y"),
                "valor" => ErpMoney::formatBr($valor),
                "dias" => $diaInt,
            ];
        }

        $this->finalizarCartaoParcelasRows = $rows;
        $this->finalizarCartaoParcelasQtd = (string) $n;
    }

    public function abrirTabelasCartaoPredefinidas(): void
    {
        if (! $this->finalizarCartaoModoSuperTef) {
            return;
        }

        $this->refreshCartaoTabelasDaForma();

        if ($this->finalizarCartaoTabelasPredefinidas === []) {
            $this->notifyPdvError(
                "Nenhuma tabela de prazo nesta forma.",
                "Cadastre condições em Formas de Pagamento (Tabela de Prazo).",
            );

            return;
        }

        $this->finalizarCartaoTabelasListaAberta = true;
        $this->selectedFinalizarCartaoTabelaIndex = 0;
        $this->dispatch("erp-pdv-focus-finalizar-cartao-tabelas");
    }

    public function fecharTabelasCartaoPredefinidas(): void
    {
        $this->finalizarCartaoTabelasListaAberta = false;
        $this->selectedFinalizarCartaoTabelaIndex = null;
        $this->dispatch("erp-pdv-focus-finalizar-cartao-canhoto");
    }

    protected function refreshCartaoTabelasDaForma(): void
    {
        $pagamento = $this->resolvePagamentoCartaoParcelamentoAtivo();
        $tabelas = $pagamento["tabelas_prazo"] ?? [];
        $maxTec = 18;
        if ($this->finalizarCartaoModoSuperTef && method_exists($this, "finalizarSuperTefMaxParcelasUi")) {
            $maxTec = max(1, (int) $this->finalizarSuperTefMaxParcelasUi());
        }

        $out = [];
        if (is_array($tabelas)) {
            foreach ($tabelas as $tab) {
                if (! is_array($tab)) {
                    continue;
                }
                $dias = PdvFinalizarPagamentosHelper::diasDeString((string) ($tab["dias"] ?? ""));
                $qtd = count($dias);
                if ($qtd < 1 || $qtd > $maxTec) {
                    continue;
                }
                $id = (int) ($tab["id"] ?? 0);
                $out[] = [
                    "tabela_prazo_id" => $id,
                    "dias" => implode(",", $dias),
                    "label" => implode("/", $dias).($qtd === 1 ? " (à vista)" : " (".$qtd."x)"),
                ];
            }
        }

        $this->finalizarCartaoTabelasPredefinidas = $out;
    }

    public function selectFinalizarCartaoTabelaPredefinida(int $index): void
    {
        if (isset($this->finalizarCartaoTabelasPredefinidas[$index])) {
            $this->selectedFinalizarCartaoTabelaIndex = $index;
        }
    }

    public function aplicarTabelaCartaoPredefinida(): void
    {
        $index = $this->selectedFinalizarCartaoTabelaIndex;
        if ($index === null || ! isset($this->finalizarCartaoTabelasPredefinidas[$index])) {
            $this->notifyPdvError("Selecione uma tabela de prazos.");

            return;
        }

        $row = $this->finalizarCartaoTabelasPredefinidas[$index];
        $dias = PdvFinalizarPagamentosHelper::diasDeString((string) $row["dias"]);
        if ($dias === []) {
            $this->notifyPdvError("Tabela de prazos inválida.");

            return;
        }

        $this->finalizarCartaoTabelaId = (int) ($row["tabela_prazo_id"] ?? 0) ?: null;
        $this->finalizarCartaoCanhotoConfirmado = false;
        $this->gerarParcelasCartaoPorDias($dias);
        $this->finalizarCartaoIntervalo = (string) $this->estimarIntervaloCartao($dias);
        $this->selectedFinalizarCartaoParcelaIndex = 0;
        $this->finalizarCartaoTabelasListaAberta = false;
        $this->selectedFinalizarCartaoTabelaIndex = null;
        $this->dispatch("erp-pdv-focus-finalizar-cartao-canhoto");
    }

    /**
     * @param  list<int>  $dias
     */
    protected function estimarIntervaloCartao(array $dias): int
    {
        if (count($dias) < 2) {
            return max(0, (int) ($dias[0] ?? 0));
        }

        return max(0, (int) $dias[1] - (int) $dias[0]);
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
        $this->finalizarCartaoTabelasListaAberta = false;
        $this->dispatch("erp-pdv-focus-finalizar-pagamento", index: $this->selectedPagamentoIndex ?? 0);
    }

    public function concluirCartaoCanhoto(): void
    {
        if ($this->finalizarCartaoParcelasRows === []) {
            $this->notifyPdvError(
                $this->finalizarCartaoModoSuperTef
                    ? "Confirme as parcelas do SuperTEF Crédito."
                    : "Gere as parcelas do cartão.",
                $this->finalizarCartaoModoPrazo === FormaPagamento::MODO_PRAZO_TABELA
                    ? "Use F8 | Tabelas e Enter | Usar tabela."
                    : "Informe Qtd. Parcelas + Intervalo e use F2 | Gerar.",
            );

            return;
        }

        if ($this->finalizarCartaoModoSuperTef) {
            $this->finalizarCartaoCanhotoConfirmado = true;
            $this->finalizarCartaoCanhotoAberta = false;
            $this->finalizarCartaoTabelasListaAberta = false;
            $this->sincronizarParcelasSuperTefNoPagamento();
            $this->dispatch("erp-pdv-focus-finalizar-ok");

            return;
        }

        $this->finalizarCartaoNsu = mb_strtoupper(trim($this->finalizarCartaoNsu), "UTF-8");
        $this->finalizarCartaoAutorizacao = mb_strtoupper(trim($this->finalizarCartaoAutorizacao), "UTF-8");
        $this->finalizarCartaoMaquininha = mb_strtoupper(trim($this->finalizarCartaoMaquininha), "UTF-8");
        $this->finalizarCartaoBandeira = mb_strtoupper(trim($this->finalizarCartaoBandeira), "UTF-8");

        $this->finalizarCartaoCanhotoConfirmado = true;
        $this->finalizarCartaoCanhotoAberta = false;
        $this->dispatch("erp-pdv-focus-finalizar-ok");
    }

    protected function pagamentoCartaoAssumeParcelaUnica(bool $superTef): bool
    {
        $prev = $this->finalizarCartaoModoSuperTef;
        $this->finalizarCartaoModoSuperTef = $superTef;
        $pagamento = $this->resolvePagamentoCartaoParcelamentoAtivo();
        $this->finalizarCartaoModoSuperTef = $prev;

        return $pagamento !== null
            && PdvFinalizarPagamentosHelper::deveAssumirParcelaUnicaSemSelecao($pagamento);
    }

    protected function aplicarCartaoAVistaSemEscolhaParcelas(bool $superTef): void
    {
        $this->finalizarCartaoModoSuperTef = $superTef;
        $this->finalizarCartaoTabelasListaAberta = false;
        $this->finalizarCartaoTabelaId = null;
        $this->finalizarCartaoParcelasQtd = "1";

        $pagamento = $this->resolvePagamentoCartaoParcelamentoAtivo() ?? [];
        $dias = PdvFinalizarPagamentosHelper::diasParcelaUnicaFinanceiro($pagamento);
        $this->finalizarCartaoIntervalo = (string) ($dias[0] ?? 30);
        $this->gerarParcelasCartaoPorDias($dias);
        $this->finalizarCartaoCanhotoConfirmado = true;
        $this->finalizarCartaoCanhotoAberta = false;

        if ($superTef) {
            $this->sincronizarParcelasSuperTefNoPagamento();
        }
    }

    protected function sincronizarParcelasSuperTefNoPagamento(): void
    {
        $qtd = max(1, count($this->finalizarCartaoParcelasRows));
        $tabId = (int) ($this->finalizarCartaoTabelaId ?? 0);
        $key = $tabId > 0 ? "tab:".$tabId : "fin:".$qtd;

        foreach ($this->finalizarPagamentos as $i => $pagamento) {
            if (! PdvFinalizarPagamentosHelper::isFormaSuperTefCredito($pagamento)) {
                continue;
            }
            if (ErpMoney::parseBr($pagamento["valor"] ?? "0") <= 0) {
                continue;
            }
            $this->finalizarPagamentos[$i]["supertef_parcelas"] = (string) $qtd;
            $this->finalizarPagamentos[$i]["supertef_condicao"] = $key;
            break;
        }
    }

    /**
     * @return list<int>|null
     */
    protected function finalizarCartaoDiasList(): ?array
    {
        // SuperTEF: preview de parcelas não vira CR.
        if ($this->finalizarCartaoModoSuperTef || ! $this->finalizarTemCartaoContasReceberComValor() || ! $this->finalizarCartaoCanhotoConfirmado) {
            return null;
        }

        $dias = collect($this->finalizarCartaoParcelasRows)
            ->map(fn (array $row): int => (int) ($row["dias"] ?? 0))
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
            "nsu" => trim($this->finalizarCartaoNsu),
            "autorizacao" => trim($this->finalizarCartaoAutorizacao),
            "maquininha" => trim($this->finalizarCartaoMaquininha),
            "bandeira" => trim($this->finalizarCartaoBandeira),
            "dias" => $dias,
        ];
    }

    protected function validaCartaoCanhotoFinalizar(): ?string
    {
        if (! $this->finalizarTemCartaoContasReceberComValor()) {
            return null;
        }

        if ($this->finalizarCartaoModoSuperTef) {
            return null;
        }

        if (! $this->finalizarCartaoCanhotoConfirmado || $this->finalizarCartaoParcelasRows === []) {
            return "Informe o canhoto da POS (parcelas do cartão) para Contas à Receber.";
        }

        return null;
    }
}
