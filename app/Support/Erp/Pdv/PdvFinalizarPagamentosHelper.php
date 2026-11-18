<?php

namespace App\Support\Erp\Pdv;

use App\Support\Erp\ErpMoney;
use App\Support\Erp\Financeiro\FormaPagamentoDestino;

final class PdvFinalizarPagamentosHelper
{
    /**
     * @param  array{forma?: string, tipo?: string, tipo_movimento?: string}  $pagamento
     */
    public static function isFormaAPrazoPagamento(array $pagamento): bool
    {
        // Cartão em CR usa canhoto, não zera as demais formas.
        if (self::isFormaCartao($pagamento)) {
            return false;
        }

        if (FormaPagamentoDestino::geraContasReceber($pagamento)) {
            return true;
        }

        return self::isFormaAPrazo((string) ($pagamento['forma'] ?? ''));
    }

    public static function isFormaAPrazo(string $forma): bool
    {
        $forma = mb_strtoupper(trim($forma), 'UTF-8');

        if (str_contains($forma, 'CHEQUE') || str_contains($forma, 'BOLETO')) {
            return true;
        }

        return self::isFormaCrediario($forma);
    }

    public static function isFormaCrediario(string $forma): bool
    {
        $forma = mb_strtoupper(trim($forma), 'UTF-8');

        return str_contains($forma, 'CREDIÁRIO') || str_contains($forma, 'CREDIARIO');
    }

    public static function isFormaCheque(string $forma): bool
    {
        return str_contains(mb_strtoupper(trim($forma), 'UTF-8'), 'CHEQUE');
    }

    public static function isFormaBoleto(string $forma): bool
    {
        return str_contains(mb_strtoupper(trim($forma), 'UTF-8'), 'BOLETO');
    }

    /**
     * Crediário / cheque / boleto: abre a tela Contas Receber | Parcelas (carnê).
     * Cartão usa canhoto e não entra aqui.
     *
     * @param  array{forma?: string, tipo?: string, tipo_movimento?: string}  $pagamento
     */
    public static function precisaParcelasCarne(array $pagamento): bool
    {
        if (self::isFormaCartao($pagamento)) {
            return false;
        }

        $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');
        $forma = (string) ($pagamento['forma'] ?? '');

        if (in_array($tipo, ['crediario', 'cheque', 'boleto'], true)) {
            return true;
        }

        return self::isFormaCrediario($forma)
            || self::isFormaCheque($forma)
            || self::isFormaBoleto($forma);
    }

    /**
     * Cartão (crédito/débito/POS) com tipo_movimento Contas à Receber.
     *
     * @param  array{forma?: string, tipo?: string, tipo_movimento?: string, aparece_contas_receber?: bool|int|string, usa_super_tef?: bool|int|string}  $pagamento
     */
    public static function isFormaCartaoContasReceber(array $pagamento): bool
    {
        // SuperTEF: NSU/parcelas vêm da API — nunca abrir canhoto manual.
        if (self::isFormaSuperTef($pagamento)) {
            return false;
        }

        if (! self::isFormaCartao($pagamento)) {
            return false;
        }

        return FormaPagamentoDestino::geraContasReceber($pagamento);
    }

    /**
     * Forma marcada para canhoto POS manual (maquininha), independente do tipo_movimento.
     *
     * @param  array{usa_pos?: bool|int|string, usa_super_tef?: bool|int|string, usa_tef?: bool|int|string}  $pagamento
     */
    public static function isFormaUsaPos(array $pagamento): bool
    {
        if (! filter_var($pagamento['usa_pos'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        // Combinações incompatíveis: SuperTEF / TEF integrado vencem e não pedem canhoto manual.
        if (self::isFormaSuperTef($pagamento)) {
            return false;
        }

        if (filter_var($pagamento['usa_tef'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return self::isFormaCartao($pagamento);
    }

    /**
     * Abre o canhoto POS manual:
     * - legado: cartão → Contas a Receber (mesmo sem usa_pos), ou
     * - flag usa_pos (qualquer tipo_movimento financeiro).
     * SuperTEF nunca entra aqui.
     *
     * @param  array{forma?: string, tipo?: string, tipo_movimento?: string, usa_pos?: bool|int|string, usa_super_tef?: bool|int|string, usa_tef?: bool|int|string}  $pagamento
     */
    public static function precisaCanhotoPosManual(array $pagamento): bool
    {
        if (self::isFormaSuperTef($pagamento)) {
            return false;
        }

        if (self::isFormaCartaoContasReceber($pagamento)) {
            return true;
        }

        return self::isFormaUsaPos($pagamento);
    }

    /**
     * Decide se o cartão vai para Contas a Receber.
     * Fonte da verdade: tipo_movimento da forma (parametro empresa só se movimento vazio/legado).
     *
     * @param  array{forma?: string, tipo?: string, tipo_movimento?: string, aparece_contas_receber?: bool|int|string}  $pagamento
     */
    public static function cartaoVaiParaContasReceber(array $pagamento, bool $lancarCartaoNoCaixa = true): bool
    {
        if (self::isFormaSuperTef($pagamento)) {
            return false;
        }

        if (! self::isFormaCartao($pagamento)) {
            return false;
        }

        $movimento = FormaPagamentoDestino::from($pagamento);

        if ($movimento === 'contas_receber') {
            return true;
        }

        if ($movimento === 'caixa') {
            return false;
        }

        // Legado sem tipo_movimento: parâmetro empresa + flag aparece_contas_receber
        if ($movimento === 'nenhum') {
            $apareceCr = filter_var($pagamento['aparece_contas_receber'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($apareceCr) {
                return true;
            }

            return ! $lancarCartaoNoCaixa;
        }

        return false;
    }

    /**
     * @param  array{forma?: string, tipo?: string}  $pagamento
     */
    public static function isFormaCartao(array $pagamento): bool
    {
        $tipo = strtolower(trim((string) ($pagamento['tipo'] ?? '')));

        if (in_array($tipo, ['cartao_credito', 'cartao_debito', 'tef'], true)) {
            return true;
        }

        $forma = mb_strtoupper(trim((string) ($pagamento['forma'] ?? '')), 'UTF-8');

        return str_contains($forma, 'CARTÃO')
            || str_contains($forma, 'CARTAO')
            || str_contains($forma, 'POS ')
            || str_starts_with($forma, 'POS');
    }

    /**
     * TEF integrado: fluxo próprio (não concluir venda só porque o restante zerou).
     *
     * @param  array{forma?: string, tipo?: string, usa_tef?: bool|int|string}  $pagamento
     */
    public static function isFormaTef(array $pagamento): bool
    {
        $tipo = strtolower(trim((string) ($pagamento['tipo'] ?? '')));

        if ($tipo === 'tef') {
            return true;
        }

        if (filter_var($pagamento['usa_tef'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $forma = mb_strtoupper(trim((string) ($pagamento['forma'] ?? '')), 'UTF-8');

        return str_contains($forma, 'TEF');
    }

    /**
     * PIX com "Gerar QR Code na tela do PDV".
     *
     * @param  array{forma?: string, tipo?: string, gerar_qrcode_pdv?: bool|int|string}  $pagamento
     */
    public static function isFormaPixGerarQrcodePdv(array $pagamento): bool
    {
        if (! filter_var($pagamento['gerar_qrcode_pdv'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');

        if ($tipo === 'pix') {
            return true;
        }

        $forma = mb_strtoupper(trim((string) ($pagamento['forma'] ?? '')), 'UTF-8');

        return str_contains($forma, 'PIX');
    }

    /**
     * Cartão de crédito marcado como à vista: PDV não solicita parcelas (1x).
     *
     * @param  array{tipo?: string, considerar_a_vista?: bool|int|string}  $pagamento
     */
    public static function isFormaConsiderarAVista(array $pagamento): bool
    {
        if (! filter_var($pagamento['considerar_a_vista'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');

        return $tipo === 'cartao_credito';
    }

    /**
     * PDV pode seguir com 1 parcela sem overlay de escolha:
     * - flag "Considerar à vista" (cartão crédito), ou
     * - modo Financeiro com max_parcelas = 1
     * Tabela de Prazo: não usa max_parcelas para essa automação.
     *
     * @param  array{tipo?: string, considerar_a_vista?: bool|int|string, modo_prazo?: string, max_parcelas?: int|string}  $pagamento
     */
    public static function deveAssumirParcelaUnicaSemSelecao(array $pagamento): bool
    {
        if (self::isFormaConsiderarAVista($pagamento)) {
            return true;
        }

        $modo = \App\Models\FormaPagamento::normalizeModoPrazo($pagamento['modo_prazo'] ?? null);
        if ($modo === \App\Models\FormaPagamento::MODO_PRAZO_TABELA) {
            return false;
        }

        return max(0, (int) ($pagamento['max_parcelas'] ?? 0)) === 1;
    }

    /**
     * Vencimento único (dias) no padrão financeiro do canhoto/carnê: intervalo*1.
     * Usa intervalo_parcelas; se vazio, prazo_cartao; senão 30 (mesmo fallback do canhoto).
     *
     * @param  array{intervalo_parcelas?: int|string, prazo_cartao?: int|string}  $pagamento
     * @return list<int>
     */
    public static function diasParcelaUnicaFinanceiro(array $pagamento): array
    {
        $intervalo = max(0, (int) ($pagamento['intervalo_parcelas'] ?? 0));
        $prazo = max(0, (int) ($pagamento['prazo_cartao'] ?? 0));

        if ($intervalo <= 0) {
            $intervalo = $prazo > 0 ? $prazo : 30;
        }

        return self::diasDePrazoFinanceiro(1, $intervalo);
    }

    /**
     * Qualquer SuperTEF integrado (débito ou crédito) — separado do POS/canhoto.
     *
     * @param  array{forma?: string, tipo?: string, usa_super_tef?: bool|int|string}  $pagamento
     */
    public static function isFormaSuperTef(array $pagamento): bool
    {
        if (! filter_var($pagamento['usa_super_tef'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');
        if (in_array($tipo, ['cartao_debito', 'cartao_credito', 'tef'], true)) {
            return true;
        }

        $forma = mb_strtoupper(trim((string) ($pagamento['forma'] ?? '')), 'UTF-8');

        return str_contains($forma, 'SUPERTEF') || str_contains($forma, 'SUPER TEF');
    }

    /**
     * SuperTEF Débito (integrado).
     *
     * @param  array{forma?: string, tipo?: string, usa_super_tef?: bool|int|string}  $pagamento
     */
    public static function isFormaSuperTefDebito(array $pagamento): bool
    {
        if (! self::isFormaSuperTef($pagamento)) {
            return false;
        }

        $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');
        if ($tipo === 'cartao_credito') {
            return false;
        }
        if (in_array($tipo, ['cartao_debito', 'tef'], true)) {
            return true;
        }

        $forma = mb_strtoupper(trim((string) ($pagamento['forma'] ?? '')), 'UTF-8');

        return str_contains($forma, 'DEBITO') || str_contains($forma, 'DÉBITO');
    }

    /**
     * SuperTEF Crédito (à vista ou parcelado na mesma forma).
     *
     * @param  array{forma?: string, tipo?: string, usa_super_tef?: bool|int|string}  $pagamento
     */
    public static function isFormaSuperTefCredito(array $pagamento): bool
    {
        if (! self::isFormaSuperTef($pagamento)) {
            return false;
        }

        $tipo = mb_strtolower(trim((string) ($pagamento['tipo'] ?? '')), 'UTF-8');
        if ($tipo === 'cartao_credito') {
            return true;
        }
        if (in_array($tipo, ['cartao_debito', 'tef'], true)) {
            return false;
        }

        $forma = mb_strtoupper(trim((string) ($pagamento['forma'] ?? '')), 'UTF-8');

        return str_contains($forma, 'CREDITO') || str_contains($forma, 'CRÉDITO');
    }

    /**
     * Condições de parcelamento SuperTEF Crédito a partir do cadastro da forma.
     * - modo financeiro: 1..max_parcelas (mesma regra do canhoto/POS)
     * - modo tabela: cada tabela_prazo com N dias => N parcelas na adquirente
     *   (usa só a quantidade; não gera títulos CR a partir dos dias)
     * Teto técnico (POS/acquirer) limita as opções; não cria tabela paralela.
     *
     * @param  array{
     *   modo_prazo?: string,
     *   max_parcelas?: int|string,
     *   intervalo_parcelas?: int|string,
     *   supertef_condicoes?: list<array<string,mixed>>,
     *   tabelas_prazo?: list<array{id?:int,dias?:string,ordem?:int}>
     * }  $pagamento
     * @return list<array{key: string, parcelas: int, label: string, origem: string, tabela_id: int|null, dias: list<int>}>
     */
    public static function condicoesSuperTefCredito(array $pagamento, int $maxTecnico = 12): array
    {
        if (isset($pagamento['supertef_condicoes']) && is_array($pagamento['supertef_condicoes']) && $pagamento['supertef_condicoes'] !== []) {
            return array_values(array_filter(
                $pagamento['supertef_condicoes'],
                static fn ($c): bool => is_array($c) && (int) ($c['parcelas'] ?? 0) >= 1
            ));
        }

        $teto = max(1, min(18, $maxTecnico));
        $modo = \App\Models\FormaPagamento::normalizeModoPrazo($pagamento['modo_prazo'] ?? null);
        $out = [];

        if ($modo === \App\Models\FormaPagamento::MODO_PRAZO_TABELA) {
            $tabelas = $pagamento['tabelas_prazo'] ?? [];
            if (is_array($tabelas)) {
                foreach ($tabelas as $tab) {
                    if (! is_array($tab)) {
                        continue;
                    }
                    $dias = self::diasDeString((string) ($tab['dias'] ?? ''));
                    $qtd = count($dias);
                    if ($qtd < 1 || $qtd > $teto) {
                        continue;
                    }
                    $id = (int) ($tab['id'] ?? 0);
                    $rotuloDias = implode('/', $dias);
                    $out[] = [
                        'key' => $id > 0 ? 'tab:'.$id : 'tab:'.$qtd.':'.$rotuloDias,
                        'parcelas' => $qtd,
                        'label' => $qtd === 1 ? ('À vista ('.$rotuloDias.')') : ($qtd.'x ('.$rotuloDias.')'),
                        'origem' => 'tabela',
                        'tabela_id' => $id > 0 ? $id : null,
                        'dias' => $dias,
                    ];
                }
            }
        }

        if ($out === []) {
            // Financeiro (ou tabela sem condições válidas para TEF): 1..max_parcelas.
            $formaMax = max(1, (int) ($pagamento['max_parcelas'] ?? $teto));
            $limite = min($teto, $formaMax);
            $intervalo = max(0, (int) ($pagamento['intervalo_parcelas'] ?? 0));
            for ($p = 1; $p <= $limite; $p++) {
                $hint = '';
                if ($intervalo > 0 && $p > 1) {
                    $hint = ' / '.$intervalo.'d';
                }
                $out[] = [
                    'key' => 'fin:'.$p,
                    'parcelas' => $p,
                    'label' => $p === 1 ? 'À vista' : ($p.'x'.$hint),
                    'origem' => 'financeiro',
                    'tabela_id' => null,
                    'dias' => [],
                ];
            }
        }

        return $out;
    }

    /**
     * Condição escolhida (ou primeira) para cobrança SuperTEF Crédito.
     *
     * @param  array{supertef_condicao?: string, supertef_parcelas?: int|string}  $pagamento
     * @return array{key: string, parcelas: int, label: string, origem: string, tabela_id: int|null, dias: list<int>}|null
     */
    public static function resolverCondicaoSuperTefCredito(array $pagamento, int $maxTecnico = 12): ?array
    {
        $conds = self::condicoesSuperTefCredito($pagamento, $maxTecnico);
        if ($conds === []) {
            return null;
        }

        $key = trim((string) ($pagamento['supertef_condicao'] ?? ''));
        if ($key !== '') {
            foreach ($conds as $c) {
                if ((string) ($c['key'] ?? '') === $key) {
                    return $c;
                }
            }
        }

        // Legado: supertef_parcelas numérico.
        $qtdLegado = (int) preg_replace('/\D/', '', (string) ($pagamento['supertef_parcelas'] ?? '')) ?: 0;
        if ($qtdLegado > 0) {
            foreach ($conds as $c) {
                if ((int) $c['parcelas'] === $qtdLegado) {
                    return $c;
                }
            }
        }

        return $conds[0];
    }

    /**
     * Parcelas SuperTEF Crédito (1 = à vista) — derivado da condição da forma.
     *
     * @param  array{supertef_condicao?: string, supertef_parcelas?: int|string, max_parcelas?: int|string}  $pagamento
     */
    public static function superTefParcelas(array $pagamento, int $maxPermitido = 12): int
    {
        $cond = self::resolverCondicaoSuperTefCredito($pagamento, $maxPermitido);

        return max(1, (int) ($cond['parcelas'] ?? 1));
    }

    /**
     * Converte "30,60,90" numa lista de dias [30, 60, 90].
     *
     * @return list<int>
     */
    public static function diasDeString(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn ($d): string => trim((string) $d))
            ->filter(fn (string $d): bool => $d !== '' && is_numeric($d))
            ->map(fn (string $d): int => (int) $d)
            ->values()
            ->all();
    }

    /**
     * Prazo financeiro do carnê (max_parcelas + intervalo_parcelas).
     *
     * Com modo_prazo explícito:
     * - financeiro → max>=1 e intervalo>0 (inclui 1×30 intencional)
     * - tabela → nunca usa max/intervalo para auto-prazo
     *
     * Sem modo (legado / app antigo): mantém a heurística histórica
     * excluindo o default de instalação (1 × 30).
     */
    public static function isPrazoFinanceiroValido(
        int $maxParcelas,
        int $intervaloParcelas,
        ?string $modoPrazo = null,
    ): bool {
        $modo = mb_strtolower(trim((string) $modoPrazo), 'UTF-8');

        if ($modo === 'tabela') {
            return false;
        }

        if ($modo === 'financeiro') {
            return $maxParcelas >= 1 && $intervaloParcelas > 0;
        }

        // Legado sem modo_prazo (compatibilidade).
        if ($maxParcelas < 1 || $intervaloParcelas <= 0) {
            return false;
        }

        if ($maxParcelas === 1 && $intervaloParcelas === 30) {
            return false;
        }

        return true;
    }

    /**
     * Gera dias absolutos a partir do prazo financeiro: 1..max → intervalo*i.
     *
     * @return list<int>
     */
    public static function diasDePrazoFinanceiro(int $maxParcelas, int $intervaloParcelas): array
    {
        $max = max(1, $maxParcelas);
        $intervalo = max(0, $intervaloParcelas);
        $dias = [];

        for ($i = 1; $i <= $max; $i++) {
            $dias[] = $intervalo * $i;
        }

        return $dias;
    }

    /**
     * Prioridade do carnê (boleto/cheque/crediário):
     * 1) tabela fixa do cliente
     * 2) prazo financeiro da forma (se modo_prazo permitir)
     * 3) null → fallback UI atual (F2/F8/defaults)
     *
     * @param  list<int>|null  $diasTabelaCliente
     * @return list<int>|null
     */
    public static function resolverDiasCarnePrioridade(
        ?array $diasTabelaCliente,
        int $maxParcelas,
        int $intervaloParcelas,
        ?string $modoPrazo = null,
    ): ?array {
        $doCliente = collect($diasTabelaCliente ?? [])
            ->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 0)
            ->values()
            ->all();

        if ($doCliente !== []) {
            return $doCliente;
        }

        if (! self::isPrazoFinanceiroValido($maxParcelas, $intervaloParcelas, $modoPrazo)) {
            return null;
        }

        return self::diasDePrazoFinanceiro($maxParcelas, $intervaloParcelas);
    }

    /**
     * Ao escolher crediário/cheque/boleto, zera as demais formas e concentra o valor na linha escolhida.
     *
     * @param  array<int, array{forma: string, atalho: string, valor: string}>  $pagamentos
     * @return array<int, array{forma: string, atalho: string, valor: string}>
     */
    public static function aplicarFormaPrazoExclusiva(array $pagamentos, int $index, float $totalVenda): array
    {
        if (! isset($pagamentos[$index])) {
            return $pagamentos;
        }

        $total = max(0, round($totalVenda, 2));
        $valorLinha = ErpMoney::parseBr($pagamentos[$index]['valor'] ?? '0');
        $valorFinal = $valorLinha > 0 ? min($valorLinha, $total) : $total;

        foreach ($pagamentos as $i => $pagamento) {
            $pagamentos[$i]['valor'] = $i === $index
                ? ErpMoney::formatBr($valorFinal)
                : '0,00';
        }

        return $pagamentos;
    }

    /**
     * PDV online: se alguma forma com valor > 0 estiver marcada nfce, a venda transmite NFC-e
     * automaticamente (uma nota com o total e todos os pagamentos).
     *
     * @param  array<int, array{valor?: string|int|float|null, nfce?: bool|int|string|null}>  $pagamentos
     */
    public static function exigeNfceAutomatica(array $pagamentos): bool
    {
        foreach ($pagamentos as $pagamento) {
            if (! is_array($pagamento)) {
                continue;
            }

            if (ErpMoney::parseBr($pagamento['valor'] ?? '0') <= 0) {
                continue;
            }

            if (filter_var($pagamento['nfce'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        return false;
    }
}
