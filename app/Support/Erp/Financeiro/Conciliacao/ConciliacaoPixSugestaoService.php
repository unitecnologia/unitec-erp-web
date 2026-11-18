<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

use App\Models\CaixaLancamento;
use App\Models\ExtratoLancamento;
use App\Models\PixCobranca;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C4B — Motor de sugestões PIX (somente identificação).
 * Não concilia, não baixa, não altera extrato/financeiro/webhook.
 * Sob demanda: nunca chamar no mount da listagem.
 */
final class ConciliacaoPixSugestaoService
{
    public function __construct(
        private readonly ConciliacaoCandidatosQuery $caixaQuery = new ConciliacaoCandidatosQuery,
    ) {
    }

    public function janelaDias(): int
    {
        return (int) config('unitec.conciliacao.c4b_date_window_days', config('unitec.conciliacao.c3_date_window_days', 3));
    }

    public function maxCandidatos(): int
    {
        return (int) config('unitec.conciliacao.c4b_candidates_max', 20);
    }

    public function sugerir(ExtratoLancamento $extrato): PixSugestaoResultado
    {
        $extratoId = (int) $extrato->id;
        $parecePix = PixIdentificadorOfx::historicoParecePix($extrato->historico, $extrato->memo);
        $avisos = [];

        if (! in_array((string) $extrato->tipo, [ExtratoLancamento::TIPO_CREDITO, ExtratoLancamento::TIPO_DEBITO], true)) {
            return new PixSugestaoResultado($extratoId, PixSugestaoResultado::CLASS_SEM, [], [
                'Tipo de movimento do extrato não suportado para sugestão PIX.',
            ], $parecePix);
        }

        if (! $parecePix) {
            $avisos[] = 'Histórico do extrato não indica PIX; busca limitada ao Livro Caixa elegível.';
        }

        /** @var list<PixSugestaoCandidato> $fortes */
        $fortes = [];
        /** @var list<PixSugestaoCandidato> $possiveis */
        $possiveis = [];
        /** @var array<int, true> $pixIdsUsados */
        $pixIdsUsados = [];

        $txidsNoTexto = PixIdentificadorOfx::extrairTxidsDoTexto(
            $extrato->historico,
            $extrato->memo,
            $extrato->payee,
        );

        // FITID nunca vira txId automaticamente.
        $fitid = (string) ($extrato->fitid ?? '');
        if ($fitid !== '' && PixIdentificadorOfx::looksLikeOfxFitidHex32($fitid)) {
            $avisos[] = 'FITID OFX ignorado como txId/endToEndId (formato não equivalente).';
        }

        $caixaRows = $this->carregarCaixaElegivel($extrato);
        $pixRows = $this->carregarPixCobrancas($extrato);
        $crRows = $this->carregarCrPagamentos($extrato);
        $cpRows = $this->carregarCpPagamentos($extrato);
        $pdvRows = $this->carregarPdvPix($extrato);

        // —— Fortes: txId no texto + pix_cobranca + caixa na mesma conta/valor/sentido ——
        if ($txidsNoTexto !== [] && $pixRows->isNotEmpty()) {
            foreach ($txidsNoTexto as $txid) {
                $pixMatches = $pixRows->filter(function ($p) use ($txid): bool {
                    $a = strtoupper(trim((string) ($p->txid ?? '')));
                    $b = strtoupper(trim((string) ($p->provider_ref ?? '')));

                    return $a === $txid || $b === $txid;
                });

                foreach ($pixMatches as $pix) {
                    $caixaProva = $this->caixaCompativelComPix($caixaRows, $extrato, $pix);
                    if ($caixaProva !== null) {
                        $fortes[] = new PixSugestaoCandidato(
                            origem: PixSugestaoCandidato::ORIGEM_CAIXA,
                            origemId: (int) $caixaProva->id,
                            classificacao: PixSugestaoResultado::CLASS_FORTE,
                            valor: $this->valorCaixa($caixaProva),
                            data: $this->dataCaixa($caixaProva),
                            caixaContaId: (int) $caixaProva->caixa_conta_id,
                            resumo: 'TxId no OFX + Livro Caixa na mesma conta',
                            evidencias: [
                                'txid' => $txid,
                                'pix_cobranca_id' => (int) $pix->id,
                                'caixa_lancamento_id' => (int) $caixaProva->id,
                                'pix_status' => (string) $pix->status,
                            ],
                        );
                        $pixIdsUsados[(int) $pix->id] = true;
                    } else {
                        // Cobrança identificada, mas sem prova bancária no Livro da conta → nunca forte.
                        $possiveis[] = new PixSugestaoCandidato(
                            origem: PixSugestaoCandidato::ORIGEM_PIX_COBRANCA,
                            origemId: (int) $pix->id,
                            classificacao: PixSugestaoResultado::CLASS_POSSIVEL,
                            valor: round((float) $pix->valor, 2),
                            data: $pix->pago_em?->toDateString(),
                            caixaContaId: null,
                            resumo: 'Cobrança PIX identificada por txId, sem lançamento no Livro Caixa da conta',
                            evidencias: [
                                'txid' => $txid,
                                'pix_status' => (string) $pix->status,
                                'motivo' => 'pix_pago_sem_caixa_bancario',
                            ],
                        );
                        $pixIdsUsados[(int) $pix->id] = true;
                        $avisos[] = 'Cobrança PIX identificada não prova conciliação bancária sem Livro Caixa na conta.';
                    }
                }
            }
        }

        // —— Possíveis: caixa mesma conta/valor/data (reuso C3) ——
        foreach ($caixaRows as $caixa) {
            if ($this->jaCitado($fortes, PixSugestaoCandidato::ORIGEM_CAIXA, (int) $caixa->id)) {
                continue;
            }
            $possiveis[] = new PixSugestaoCandidato(
                origem: PixSugestaoCandidato::ORIGEM_CAIXA,
                origemId: (int) $caixa->id,
                classificacao: PixSugestaoResultado::CLASS_POSSIVEL,
                valor: $this->valorCaixa($caixa),
                data: $this->dataCaixa($caixa),
                caixaContaId: (int) $caixa->caixa_conta_id,
                resumo: 'Livro Caixa: mesmo valor, sentido, conta e janela de datas',
                evidencias: [
                    'documento' => (string) ($caixa->documento ?? ''),
                    'historico' => mb_substr((string) ($caixa->historico ?? ''), 0, 120),
                ],
            );
        }

        // —— Possíveis: CR pagamento (entrada) com mesma conta ——
        if ((string) $extrato->tipo === ExtratoLancamento::TIPO_CREDITO) {
            foreach ($crRows as $pag) {
                $possiveis[] = new PixSugestaoCandidato(
                    origem: PixSugestaoCandidato::ORIGEM_CR_PAGAMENTO,
                    origemId: (int) $pag->id,
                    classificacao: PixSugestaoResultado::CLASS_POSSIVEL,
                    valor: round((float) $pag->valor_recebido, 2),
                    data: substr((string) $pag->data, 0, 10),
                    caixaContaId: $pag->caixa_conta_id !== null ? (int) $pag->caixa_conta_id : null,
                    resumo: 'Recebimento CR liquidado (não é título aberto)',
                    evidencias: [
                        'conta_receber_id' => (int) $pag->conta_receber_id,
                    ],
                );
            }
        }

        // —— Possíveis: CP pagamento (saída) ——
        if ((string) $extrato->tipo === ExtratoLancamento::TIPO_DEBITO) {
            foreach ($cpRows as $pag) {
                $possiveis[] = new PixSugestaoCandidato(
                    origem: PixSugestaoCandidato::ORIGEM_CP_PAGAMENTO,
                    origemId: (int) $pag->id,
                    classificacao: PixSugestaoResultado::CLASS_POSSIVEL,
                    valor: round((float) $pag->valor_pago, 2),
                    data: substr((string) $pag->data, 0, 10),
                    caixaContaId: $pag->caixa_conta_id !== null ? (int) $pag->caixa_conta_id : null,
                    resumo: 'Pagamento CP liquidado',
                    evidencias: [
                        'conta_pagar_id' => (int) $pag->conta_pagar_id,
                    ],
                );
            }
        }

        // —— PIX pago por valor/data (sem prova de conta bancária) → possível fraco / aviso ——
        foreach ($pixRows as $pix) {
            if (isset($pixIdsUsados[(int) $pix->id])
                || $this->jaCitado($possiveis, PixSugestaoCandidato::ORIGEM_PIX_COBRANCA, (int) $pix->id)
                || $this->jaCitado($fortes, PixSugestaoCandidato::ORIGEM_PIX_COBRANCA, (int) $pix->id)
            ) {
                continue;
            }
            // Sem txId no OFX: nunca forte; só possível se valor/data batem.
            $possiveis[] = new PixSugestaoCandidato(
                origem: PixSugestaoCandidato::ORIGEM_PIX_COBRANCA,
                origemId: (int) $pix->id,
                classificacao: PixSugestaoResultado::CLASS_POSSIVEL,
                valor: round((float) $pix->valor, 2),
                data: $pix->pago_em?->toDateString(),
                caixaContaId: null,
                resumo: 'Cobrança PIX paga por valor/data — sem vínculo bancário comprovado',
                evidencias: [
                    'txid' => (string) ($pix->txid ?? ''),
                    'pix_status' => (string) $pix->status,
                    'motivo' => 'pix_pago_sem_prova_bancaria',
                ],
            );
        }

        // —— PDV PIX: só possível (sem conta BANCO no pagamento) ——
        foreach ($pdvRows as $pag) {
            $possiveis[] = new PixSugestaoCandidato(
                origem: PixSugestaoCandidato::ORIGEM_PDV_PAGAMENTO,
                origemId: (int) $pag->id,
                classificacao: PixSugestaoResultado::CLASS_POSSIVEL,
                valor: round((float) $pag->valor, 2),
                data: $pag->venda_data ? substr((string) $pag->venda_data, 0, 10) : null,
                caixaContaId: null,
                resumo: 'Pagamento PIX de PDV (evidência fraca — sem conta BANCO)',
                evidencias: [
                    'pdv_venda_id' => (int) ($pag->pdv_venda_id ?? 0),
                ],
            );
        }

        $fortes = $this->limitar($fortes);
        $possiveis = $this->limitar($possiveis);

        if (count($fortes) === 1 && $possiveis === []) {
            return new PixSugestaoResultado($extratoId, PixSugestaoResultado::CLASS_FORTE, $fortes, $avisos, $parecePix);
        }

        if (count($fortes) > 1) {
            foreach ($fortes as $i => $c) {
                $fortes[$i] = $this->comClassificacao($c, PixSugestaoResultado::CLASS_AMBIGUA);
            }

            return new PixSugestaoResultado(
                $extratoId,
                PixSugestaoResultado::CLASS_AMBIGUA,
                $fortes,
                array_merge($avisos, ['Múltiplas correspondências fortes.']),
                $parecePix,
            );
        }

        if (count($fortes) === 1) {
            // Forte único + outros possíveis → ainda forte, mas avisa.
            $avisos[] = 'Há também candidatos fracos; a sugestão forte prevalece.';

            return new PixSugestaoResultado(
                $extratoId,
                PixSugestaoResultado::CLASS_FORTE,
                array_merge($fortes, $this->relabelPossiveis($possiveis)),
                $avisos,
                $parecePix,
            );
        }

        if (count($possiveis) === 1) {
            return new PixSugestaoResultado($extratoId, PixSugestaoResultado::CLASS_POSSIVEL, $possiveis, $avisos, $parecePix);
        }

        if (count($possiveis) > 1) {
            $amb = [];
            foreach ($possiveis as $c) {
                $amb[] = $this->comClassificacao($c, PixSugestaoResultado::CLASS_AMBIGUA);
            }

            return new PixSugestaoResultado(
                $extratoId,
                PixSugestaoResultado::CLASS_AMBIGUA,
                $amb,
                array_merge($avisos, ['Múltiplos candidatos possíveis.']),
                $parecePix,
            );
        }

        return new PixSugestaoResultado($extratoId, PixSugestaoResultado::CLASS_SEM, [], $avisos, $parecePix);
    }

    /**
     * @return Collection<int, CaixaLancamento>
     */
    private function carregarCaixaElegivel(ExtratoLancamento $extrato): Collection
    {
        $max = $this->maxCandidatos();

        return $this->caixaQuery->query($extrato)->limit($max)->get();
    }

    /**
     * @return Collection<int, PixCobranca>
     */
    private function carregarPixCobrancas(ExtratoLancamento $extrato): Collection
    {
        if (! Schema::hasTable('pix_cobrancas')) {
            return collect();
        }

        // Entradas: cobranças recebidas. Saídas: não há cobrança típica; ainda assim busca por valor se status pago.
        $empresaId = (int) $extrato->empresa_id;
        $valor = round((float) $extrato->valor, 2);
        $data = $this->dataExtrato($extrato);
        $janela = $this->janelaDias();
        $de = Carbon::parse($data)->subDays($janela)->startOfDay();
        $ate = Carbon::parse($data)->addDays($janela)->endOfDay();

        return PixCobranca::query()
            ->select(['id', 'empresa_id', 'txid', 'provider_ref', 'valor', 'status', 'pago_em', 'origem'])
            ->where('empresa_id', $empresaId)
            ->where('status', PixCobranca::STATUS_PAGO)
            ->where('valor', $valor)
            ->whereNotNull('pago_em')
            ->whereBetween('pago_em', [$de, $ate])
            ->orderBy('id')
            ->limit($this->maxCandidatos())
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function carregarCrPagamentos(ExtratoLancamento $extrato): Collection
    {
        if ((string) $extrato->tipo !== ExtratoLancamento::TIPO_CREDITO
            || ! Schema::hasTable('conta_receber_pagamentos')
        ) {
            return collect();
        }

        $empresaId = (int) $extrato->empresa_id;
        $contaId = (int) $extrato->caixa_conta_id;
        $valor = round((float) $extrato->valor, 2);
        [$de, $ate] = $this->janelaDatas($extrato);

        $q = DB::table('conta_receber_pagamentos as p')
            ->select(['p.id', 'p.data', 'p.valor_recebido', 'p.caixa_conta_id', 'p.conta_receber_id'])
            ->where('p.valor_recebido', $valor)
            ->where('p.caixa_conta_id', $contaId)
            ->whereDate('p.data', '>=', $de)
            ->whereDate('p.data', '<=', $ate)
            ->orderBy('p.id')
            ->limit($this->maxCandidatos());

        if (Schema::hasTable('contas_receber') && Schema::hasColumn('contas_receber', 'empresa_id')) {
            $q->join('contas_receber as c', 'c.id', '=', 'p.conta_receber_id')
                ->where('c.empresa_id', $empresaId);
        }

        return $q->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function carregarCpPagamentos(ExtratoLancamento $extrato): Collection
    {
        if ((string) $extrato->tipo !== ExtratoLancamento::TIPO_DEBITO
            || ! Schema::hasTable('conta_pagar_pagamentos')
        ) {
            return collect();
        }

        $empresaId = (int) $extrato->empresa_id;
        $contaId = (int) $extrato->caixa_conta_id;
        $valor = round((float) $extrato->valor, 2);
        [$de, $ate] = $this->janelaDatas($extrato);

        $q = DB::table('conta_pagar_pagamentos as p')
            ->select(['p.id', 'p.data', 'p.valor_pago', 'p.caixa_conta_id', 'p.conta_pagar_id'])
            ->where('p.valor_pago', $valor)
            ->where('p.caixa_conta_id', $contaId)
            ->whereDate('p.data', '>=', $de)
            ->whereDate('p.data', '<=', $ate)
            ->orderBy('p.id')
            ->limit($this->maxCandidatos());

        if (Schema::hasTable('contas_pagar') && Schema::hasColumn('contas_pagar', 'empresa_id')) {
            $q->join('contas_pagar as c', 'c.id', '=', 'p.conta_pagar_id')
                ->where('c.empresa_id', $empresaId);
        }

        return $q->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function carregarPdvPix(ExtratoLancamento $extrato): Collection
    {
        if ((string) $extrato->tipo !== ExtratoLancamento::TIPO_CREDITO
            || ! Schema::hasTable('pdv_venda_pagamentos')
            || ! Schema::hasTable('pdv_vendas')
        ) {
            return collect();
        }

        $valor = round((float) $extrato->valor, 2);
        [$de, $ate] = $this->janelaDatas($extrato);
        $colsV = Schema::getColumnListing('pdv_vendas');
        $dataCol = in_array('data_venda', $colsV, true) ? 'data_venda'
            : (in_array('emissao', $colsV, true) ? 'emissao' : 'created_at');

        return DB::table('pdv_venda_pagamentos as p')
            ->join('pdv_vendas as v', 'v.id', '=', 'p.pdv_venda_id')
            ->where('p.forma', 'like', '%PIX%')
            ->where('p.valor', $valor)
            ->whereDate('v.'.$dataCol, '>=', $de)
            ->whereDate('v.'.$dataCol, '<=', $ate)
            ->orderBy('p.id')
            ->limit($this->maxCandidatos())
            ->get([
                'p.id',
                'p.valor',
                'p.pdv_venda_id',
                DB::raw('v.'.$dataCol.' as venda_data'),
            ]);
    }

    /**
     * @param  Collection<int, CaixaLancamento>  $caixaRows
     */
    private function caixaCompativelComPix(Collection $caixaRows, ExtratoLancamento $extrato, PixCobranca $pix): ?CaixaLancamento
    {
        $valor = round((float) $extrato->valor, 2);
        $pixValor = round((float) $pix->valor, 2);
        if ($valor !== $pixValor) {
            return null;
        }

        foreach ($caixaRows as $caixa) {
            if (round($this->valorCaixa($caixa), 2) === $valor) {
                return $caixa;
            }
        }

        return null;
    }

    private function valorCaixa(CaixaLancamento $caixa): float
    {
        $entrada = round((float) $caixa->entrada, 2);
        $saida = round((float) $caixa->saida, 2);

        return $entrada > 0 ? $entrada : $saida;
    }

    private function dataCaixa(CaixaLancamento $caixa): ?string
    {
        return $caixa->emissao instanceof Carbon
            ? $caixa->emissao->toDateString()
            : ($caixa->emissao ? substr((string) $caixa->emissao, 0, 10) : null);
    }

    private function dataExtrato(ExtratoLancamento $extrato): string
    {
        return $extrato->data_movimento instanceof Carbon
            ? $extrato->data_movimento->toDateString()
            : substr((string) $extrato->data_movimento, 0, 10);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function janelaDatas(ExtratoLancamento $extrato): array
    {
        $data = $this->dataExtrato($extrato);
        $janela = $this->janelaDias();

        return [
            Carbon::parse($data)->subDays($janela)->toDateString(),
            Carbon::parse($data)->addDays($janela)->toDateString(),
        ];
    }

    /**
     * @param  list<PixSugestaoCandidato>  $lista
     */
    private function jaCitado(array $lista, string $origem, int $id): bool
    {
        foreach ($lista as $c) {
            if ($c->origem === $origem && $c->origemId === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<PixSugestaoCandidato>  $lista
     * @return list<PixSugestaoCandidato>
     */
    private function limitar(array $lista): array
    {
        return array_values(array_slice($lista, 0, $this->maxCandidatos()));
    }

    private function comClassificacao(PixSugestaoCandidato $c, string $classificacao): PixSugestaoCandidato
    {
        return new PixSugestaoCandidato(
            origem: $c->origem,
            origemId: $c->origemId,
            classificacao: $classificacao,
            valor: $c->valor,
            data: $c->data,
            caixaContaId: $c->caixaContaId,
            resumo: $c->resumo,
            evidencias: $c->evidencias,
        );
    }

    /**
     * @param  list<PixSugestaoCandidato>  $lista
     * @return list<PixSugestaoCandidato>
     */
    private function relabelPossiveis(array $lista): array
    {
        $out = [];
        foreach ($lista as $c) {
            $out[] = $this->comClassificacao($c, PixSugestaoResultado::CLASS_POSSIVEL);
        }

        return $out;
    }
}
