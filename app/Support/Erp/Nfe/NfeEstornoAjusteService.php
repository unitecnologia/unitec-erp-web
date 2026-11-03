<?php

namespace App\Support\Erp\Nfe;

use App\Models\Nfe;
use App\Models\NfeItem;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use Illuminate\Database\Eloquent\Builder;

/**
 * Prepara lançamento de estorno fiscal (finNFe=3) a partir de NF-e de saída autorizada.
 * Não grava, não transmite e não movimenta estoque/financeiro.
 */
final class NfeEstornoAjusteService
{
    public const NATUREZA = 'ESTORNO DE NF-E NÃO CANCELADA NO PRAZO LEGAL';

    /**
     * Busca paginada de NF-e de saída autorizadas.
     * Número/chave direta ignora o filtro de período (permite achar fora do mês padrão).
     *
     * @return array{
     *     rows: list<array{id:int,numero:string,serie:string,data:string,cliente:string,chave:string,total:string}>,
     *     page: int,
     *     per_page: int,
     *     total: int,
     *     last_page: int
     * }
     */
    public function buscarSaidasAutorizadas(
        int $empresaId,
        string $termo = '',
        ?string $dataDe = null,
        ?string $dataAte = null,
        string $cliente = '',
        int $page = 1,
        int $perPage = 25,
    ): array {
        $termo = trim($termo);
        $cliente = trim($cliente);
        $digits = preg_replace('/\D/', '', $termo) ?? '';
        $buscaDiretaNumeroOuChave = $this->isBuscaDiretaNumeroOuChave($termo, $digits);
        $perPage = max(10, min($perPage, 50));
        $page = max(1, $page);

        $query = Nfe::query()
            ->with(['cliente:id,nome_razao'])
            ->where('empresa_id', $empresaId)
            ->where('status', Nfe::STATUS_TRANSMITIDA)
            ->whereNotNull('chave')
            ->where('chave', '!=', '')
            ->where(function (Builder $q): void {
                // Saída: padrão '1', legado 'S'. Entrada '0' fica de fora.
                $q->where('movimento', '1')
                    ->orWhere('movimento', 'S')
                    ->orWhereNull('movimento')
                    ->orWhere('movimento', '');
            });

        if ($buscaDiretaNumeroOuChave) {
            $query->where(function (Builder $q) use ($digits): void {
                if (strlen($digits) >= 44) {
                    $q->where('chave', substr($digits, 0, 44));

                    return;
                }

                $q->where('numero', $digits)
                    ->orWhere('numero', ltrim($digits, '0') ?: '0');
            });
        } elseif ($termo !== '') {
            $like = '%'.$termo.'%';
            $query->where(function (Builder $q) use ($like, $digits): void {
                $q->where('chave', 'like', $like)
                    ->orWhere('numero', 'like', $like);

                if ($digits !== '') {
                    $q->orWhere('numero', $digits)
                        ->orWhere('numero', ltrim($digits, '0') ?: '0');
                }
            });
        }

        // Período só quando não há busca direta por número/chave.
        if (! $buscaDiretaNumeroOuChave) {
            $de = $this->normalizeDate($dataDe);
            $ate = $this->normalizeDate($dataAte);

            if ($de !== null) {
                $query->whereDate('data_emissao', '>=', $de);
            }

            if ($ate !== null) {
                $query->whereDate('data_emissao', '<=', $ate);
            }
        }

        if ($cliente !== '') {
            $likeCliente = '%'.$cliente.'%';
            $query->whereHas('cliente', function (Builder $q) use ($likeCliente): void {
                $q->where('nome_razao', 'like', $likeCliente);
            });
        }

        $paginator = $query
            ->orderByDesc('data_emissao')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginator->items())->map(static function (Nfe $nfe): array {
            return [
                'id' => (int) $nfe->id,
                'numero' => (string) $nfe->numero,
                'serie' => (string) ($nfe->serie ?: '1'),
                'data' => $nfe->data_emissao?->format('d/m/Y') ?? '—',
                'cliente' => trim((string) ($nfe->cliente?->nome_razao ?: '—')),
                'chave' => (string) $nfe->chave,
                'total' => ErpMoney::formatBr((float) $nfe->total, 2),
            ];
        })->values()->all();

        return [
            'rows' => $rows,
            'page' => (int) $paginator->currentPage(),
            'per_page' => (int) $paginator->perPage(),
            'total' => (int) $paginator->total(),
            'last_page' => max(1, (int) $paginator->lastPage()),
        ];
    }

    /**
     * Monta payload apenas para o formulário do modal (sem persistir).
     *
     * @return array{
     *     cliente_id: int|null,
     *     natureza_operacao: string,
     *     finalidade: string,
     *     movimento: string,
     *     obs_fisco: string,
     *     forma_pgto: string,
     *     meio_pgto: string,
     *     data_emissao: string,
     *     data_saida: string,
     *     referencias: list<array{referencia: string}>,
     *     rows: list<array<string, mixed>>,
     *     origem_numero: string,
     *     origem_chave: string
     * }
     */
    public function prepararLancamento(Nfe $origem): array
    {
        $origem->loadMissing(['itens.product', 'cliente']);

        $chave = preg_replace('/\D/', '', (string) ($origem->chave ?? '')) ?? '';

        if (strlen($chave) !== 44) {
            throw new \InvalidArgumentException('NF-e original sem chave de acesso válida.');
        }

        if ((string) $origem->status !== Nfe::STATUS_TRANSMITIDA) {
            throw new \InvalidArgumentException('Somente NF-e de saída autorizada pode ser estornada neste fluxo.');
        }

        $movimentoOrigem = (string) ($origem->movimento ?? '1');
        if ($movimentoOrigem === '0') {
            throw new \InvalidArgumentException('A NF-e selecionada é de entrada e não serve para este estorno.');
        }

        $hoje = ErpTimezone::today();
        $numero = ltrim((string) $origem->numero, '0') ?: (string) $origem->numero;
        $serie = (string) ($origem->serie ?: '1');

        $obsFisco = sprintf(
            'ESTORNO DE NF-E NAO CANCELADA NO PRAZO LEGAL. REF. NF-E N. %s SERIE %s CHAVE %s.',
            $numero,
            $serie,
            $chave,
        );

        $rows = $origem->itens
            ->sortBy('item')
            ->values()
            ->map(fn (NfeItem $item, int $index): array => $this->mapItemRow($item, $index + 1, (string) ($origem->npedido ?? '')))
            ->all();

        return [
            'cliente_id' => $origem->cliente_id ? (int) $origem->cliente_id : null,
            'natureza_operacao' => self::NATUREZA,
            'finalidade' => 'ajuste',
            'movimento' => 'entrada',
            'obs_fisco' => $obsFisco,
            'forma_pgto' => 'outros',
            'meio_pgto' => 'sem_pagamento',
            'data_emissao' => $hoje,
            'data_saida' => $hoje,
            'referencias' => [['referencia' => $chave]],
            'rows' => $rows,
            'origem_numero' => (string) $origem->numero,
            'origem_chave' => $chave,
        ];
    }

    private function isBuscaDiretaNumeroOuChave(string $termo, string $digits): bool
    {
        if ($digits === '') {
            return false;
        }

        if (strlen($digits) >= 44) {
            return true;
        }

        return ctype_digit($termo) && strlen($digits) <= 9;
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapItemRow(NfeItem $item, int $seq, string $pedido): array
    {
        $cfopOrigem = preg_replace('/\D/', '', (string) ($item->cfop ?? '')) ?? '';
        $cfop = $cfopOrigem === '5102' ? '1102' : (string) ($item->cfop ?? '');

        return [
            'key' => 'estorno-'.$seq.'-'.((int) ($item->product_id ?? 0)).'-'.uniqid(),
            'product_id' => $item->product_id,
            'codigo' => $item->product?->codigo ?? '',
            'cod_barra' => (string) ($item->cod_barra ?: $item->product?->codigo_barras ?: ''),
            'referencia' => trim((string) ($item->product?->referencia ?? '')),
            'descricao' => $item->descricao,
            'info_adicionais' => $item->info_adicionais ?? '',
            'ncm' => $item->ncm ?? ($item->product?->ncm ?? null),
            'cest' => $item->cest ?? null,
            'cfop' => $cfop,
            'cst' => $item->cst,
            'csosn' => $item->csosn,
            'origem' => $item->origem,
            'pedido' => $pedido,
            'quantidade' => ErpMoney::formatBr((float) $item->quantidade, 3),
            'valor_unitario' => ErpMoney::formatBr((float) $item->valor_unitario, 3),
            'unidade' => $item->unidade ?? 'UN',
            'desconto' => ErpMoney::formatBr((float) $item->desconto, 2),
            'frete' => ErpMoney::formatBr((float) $item->frete, 2),
            'seguro' => ErpMoney::formatBr((float) $item->seguro, 2),
            'outros' => ErpMoney::formatBr((float) $item->outros, 2),
            'base_icms' => ErpMoney::formatBr((float) $item->base_icms, 2),
            'aliq_icms' => ErpMoney::formatBr((float) $item->aliq_icms, 2),
            'p_red_bc_icms' => (float) ($item->p_red_bc_icms ?? 0),
            'mod_bc_icms' => $item->mod_bc_icms ?? null,
            'valor_icms' => ErpMoney::formatBr((float) $item->valor_icms, 2),
            'base_icms_st' => ErpMoney::formatBr((float) ($item->base_icms_st ?? 0), 2),
            'aliq_icms_st' => ErpMoney::formatBr((float) ($item->aliq_icms_st ?? 0), 2),
            'valor_icms_st' => ErpMoney::formatBr((float) ($item->valor_icms_st ?? 0), 2),
            'mod_bc_st' => $item->mod_bc_st ?? null,
            'p_mva_st' => ErpMoney::formatBr((float) ($item->p_mva_st ?? 0), 4),
            'p_red_bc_st' => ErpMoney::formatBr((float) ($item->p_red_bc_st ?? 0), 4),
            'p_fcp' => ErpMoney::formatBr((float) ($item->p_fcp ?? 0), 4),
            'vfcp' => ErpMoney::formatBr((float) ($item->vfcp ?? 0), 2),
            'v_bc_fcp' => ErpMoney::formatBr((float) ($item->v_bc_fcp ?? 0), 2),
            'v_bc_fcp_st' => ErpMoney::formatBr((float) ($item->v_bc_fcp_st ?? 0), 2),
            'p_fcp_st' => ErpMoney::formatBr((float) ($item->p_fcp_st ?? 0), 4),
            'v_fcp_st' => ErpMoney::formatBr((float) ($item->v_fcp_st ?? 0), 2),
            'base_icms_st_ret' => ErpMoney::formatBr((float) ($item->base_icms_st_ret ?? 0), 2),
            'p_st_ret' => ErpMoney::formatBr((float) ($item->p_st_ret ?? 0), 4),
            'valor_icms_st_ret' => ErpMoney::formatBr((float) ($item->valor_icms_st_ret ?? 0), 2),
            'v_icms_substituto' => ErpMoney::formatBr((float) ($item->v_icms_substituto ?? 0), 2),
            'v_bc_fcp_st_ret' => ErpMoney::formatBr((float) ($item->v_bc_fcp_st_ret ?? 0), 2),
            'p_fcp_st_ret' => ErpMoney::formatBr((float) ($item->p_fcp_st_ret ?? 0), 4),
            'v_fcp_st_ret' => ErpMoney::formatBr((float) ($item->v_fcp_st_ret ?? 0), 2),
            'motivo_desoneracao' => $item->motivo_desoneracao ?? '',
            'base_desoneracao' => ErpMoney::formatBr((float) ($item->base_desoneracao ?? 0), 2),
            'desc_desoneracao' => ErpMoney::formatBr((float) ($item->desc_desoneracao ?? 0), 2),
            'valor_desoneracao' => ErpMoney::formatBr((float) ($item->valor_desoneracao ?? 0), 2),
            'ind_deduz_deson' => $item->ind_deduz_deson === null || $item->ind_deduz_deson === ''
                ? ''
                : (string) (int) $item->ind_deduz_deson,
            'cst_ipi' => $item->cst_ipi ?? null,
            'base_ipi' => ErpMoney::formatBr((float) ($item->base_ipi ?? 0), 2),
            'aliq_ipi' => ErpMoney::formatBr((float) $item->aliq_ipi, 2),
            'valor_ipi' => ErpMoney::formatBr((float) $item->valor_ipi, 2),
            'cst_pis' => $item->cst_pis ?? null,
            'base_pis_icms' => ErpMoney::formatBr((float) ($item->base_pis_icms ?? 0), 2),
            'aliq_pis_icms' => ErpMoney::formatBr((float) $item->aliq_pis_icms, 2),
            'valor_pis_icms' => ErpMoney::formatBr((float) $item->valor_pis_icms, 2),
            'cst_cofins' => $item->cst_cofins ?? null,
            'base_cofins_icms' => ErpMoney::formatBr((float) ($item->base_cofins_icms ?? 0), 2),
            'aliq_cofins_icms' => ErpMoney::formatBr((float) $item->aliq_cofins_icms, 2),
            'valor_cofins_icms' => ErpMoney::formatBr((float) $item->valor_cofins_icms, 2),
            'class_trib' => $item->class_trib ?? '',
            'cst_ibs_cbs' => $item->cst_ibs_cbs ?? '',
            'v_ibs_mun' => ErpMoney::formatBr((float) ($item->v_ibs_mun ?? 0), 2),
            'v_ibs_uf' => ErpMoney::formatBr((float) ($item->v_ibs_uf ?? 0), 2),
            'v_cbs' => ErpMoney::formatBr((float) ($item->v_cbs ?? 0), 2),
            'bc_ibs' => ErpMoney::formatBr((float) ($item->bc_ibs ?? 0), 2),
            'alq_cbs' => ErpMoney::formatBr((float) ($item->alq_cbs ?? 0), 4),
            'alq_ibs_mun' => ErpMoney::formatBr((float) ($item->alq_ibs_mun ?? 0), 4),
            'alq_ibs_uf' => ErpMoney::formatBr((float) ($item->alq_ibs_uf ?? 0), 4),
            'p_red_ibs' => (float) ($item->p_red_ibs ?? 0),
            'p_red_cbs' => (float) ($item->p_red_cbs ?? 0),
            'trib_fed' => (float) ($item->trib_fed ?? 0),
            'trib_est' => (float) ($item->trib_est ?? 0),
            'trib_mun' => (float) ($item->trib_mun ?? 0),
            'trib_imp' => (float) ($item->trib_imp ?? 0),
        ];
    }
}
