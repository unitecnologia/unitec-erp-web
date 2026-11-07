<?php

namespace App\Support\Erp\Nfe;

use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeFatura;
use App\Models\NfeItem;
use App\Models\Person;
use App\Models\Venda;
use App\Models\VendasParametro;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Fiscal\NfeEmissionService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Monta NF-e a partir de venda faturada e transmite (fluxo lote do Monitor).
 */
final class NfeVendaLoteEmissionService
{
    public function __construct(
        private readonly NfeVendaMercadoriaService $vendaService = new NfeVendaMercadoriaService(),
        private readonly NfeCalculoService $calculo = new NfeCalculoService(),
    ) {}

    /**
     * @param  (callable(int, string): void)|null  $onProgress
     * @return array{ok: bool, venda_id: int, nfe_id: ?int, numero: ?string, protocolo: ?string, erro: ?string}
     */
    public function criarETransmitir(Venda $venda, ?callable $onProgress = null, ?int $empresaEmitenteId = null): array
    {
        $vendaId = (int) $venda->id;
        // Fatia 4B2: se o rascunho já nasceu e a transmissão falhar, devolver o id
        // para o resumo do lote abrir a mesma NF-e (sem lookup ambíguo por venda_id).
        $nfeIdCriada = null;

        try {
            $this->assertVendaDoMonitorEmpresaAtiva($venda);

            $emitente = $empresaEmitenteId && $empresaEmitenteId > 0
                ? Empresa::query()->find((int) $empresaEmitenteId)
                : null;

            if ($empresaEmitenteId && $empresaEmitenteId > 0 && ! $emitente) {
                throw new RuntimeException('Empresa emitente não encontrada.');
            }

            $payload = $this->vendaService->montarPayload($venda, $emitente);
            $nfe = $this->criarRascunho($venda, $payload, $empresaEmitenteId);
            $nfeIdCriada = (int) $nfe->id;
            $empresaId = (int) ($nfe->empresa_id ?? ErpContext::currentEmpresaId() ?? 0);
            $empresa = $empresaId > 0 ? Empresa::query()->find($empresaId) : null;

            if (! $empresa) {
                throw new RuntimeException('Empresa não identificada para transmissão.');
            }

            $nfe = app(NfeEmissionService::class)->transmitir(
                $nfe->fresh(['itens.product', 'faturas', 'cliente', 'referencias']) ?? $nfe,
                $empresa,
                $onProgress,
            );

            return [
                'ok' => true,
                'venda_id' => $vendaId,
                'nfe_id' => (int) $nfe->id,
                'numero' => (string) ($nfe->numero ?? ''),
                'protocolo' => (string) ($nfe->protocolo ?? ''),
                'erro' => null,
            ];
        } catch (FiscalEngineException|Throwable $e) {
            return [
                'ok' => false,
                'venda_id' => $vendaId,
                'nfe_id' => $nfeIdCriada,
                'numero' => null,
                'protocolo' => null,
                'erro' => $e->getMessage(),
            ];
        }
    }

    /**
     * Garante que a venda pertence a um pedido do Monitor da empresa da sessão.
     * Emitente multi-empresa pode diferir; o pedido/venda da grade não.
     */
    private function assertVendaDoMonitorEmpresaAtiva(Venda $venda): void
    {
        $vendaId = (int) $venda->id;

        if ($vendaId <= 0) {
            throw new RuntimeException('Venda inválida para emissão de NF-e.');
        }

        $permitida = ForcaVendasMonitorResource::ordersQuery()
            ->where('venda_id', $vendaId)
            ->exists();

        if (! $permitida) {
            throw new RuntimeException(
                'A venda não pertence à empresa ativa do Monitor. A emissão foi bloqueada.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function criarRascunho(Venda $venda, array $payload, ?int $empresaEmitenteId = null): Nfe
    {
        $this->assertVendaDoMonitorEmpresaAtiva($venda);

        $empresaId = $empresaEmitenteId && $empresaEmitenteId > 0
            ? (int) $empresaEmitenteId
            : (int) (ErpContext::currentEmpresaId()
                ?? $venda->forcaVendasOrder?->empresa_id
                ?? 0);

        if ($empresaId <= 0) {
            throw new RuntimeException('Empresa não identificada para a NF-e.');
        }

        $empresa = Empresa::query()->find($empresaId);

        if (! $empresa) {
            throw new RuntimeException('Empresa não encontrada.');
        }

        $cliente = Person::query()->find((int) ($payload['cliente_id'] ?? 0));

        if (! $cliente) {
            throw new RuntimeException('Cliente da venda não encontrado.');
        }

        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];

        if ($rows === []) {
            throw new RuntimeException('Venda sem itens para NF-e.');
        }

        $calculated = $this->calculo->calcular($rows, $empresa, (string) ($cliente->uf ?? ''));
        $totais = $calculated['totais'];
        $transporte = is_array($payload['transporte'] ?? null) ? $payload['transporte'] : [];

        return DB::transaction(function () use (
            $empresaId,
            $payload,
            $calculated,
            $totais,
            $transporte,
            $cliente,
            $venda,
        ): Nfe {
            $params = VendasParametro::forEmpresa($empresaId);
            $numero = (string) ($params->peekNumeroNfe() ?? Nfe::nextNumero($empresaId));
            $serie = (string) ($params->serie_nfe ?? 1);

            $duplicada = Nfe::query()
                ->where('empresa_id', $empresaId)
                ->where('numero', $numero)
                ->where('serie', $serie)
                ->exists();

            if ($duplicada) {
                throw new RuntimeException('Número de NF-e já utilizado nesta série.');
            }

            $ibptTexto = trim((string) ($totais['ibpt_texto'] ?? ''));
            $obs = trim((string) ($payload['obs_contribuinte'] ?? ''));

            if ($ibptTexto !== '' && $obs !== '' && ! str_contains($obs, 'Lei 12.741')) {
                $obs = rtrim($obs, " .\n").'. '.$ibptTexto;
            } elseif ($ibptTexto !== '' && $obs === '') {
                $obs = $ibptTexto;
            }

            $nfe = Nfe::query()->create([
                'empresa_id' => $empresaId,
                'numero' => $numero,
                'serie' => $serie,
                'modelo' => '55',
                'data_emissao' => $payload['data_emissao'] ?? now()->toDateString(),
                'data_saida' => $payload['data_saida'] ?? ($payload['data_emissao'] ?? now()->toDateString()),
                'cliente_id' => (int) $cliente->id,
                'npedido' => $payload['numero_pedido'] ?? null,
                'venda_id' => (int) $venda->id,
                'cfop' => $calculated['cfop'],
                'finalidade' => '1',
                'movimento' => '1',
                'consumidor_final' => $cliente->isConsumidorFinalPadrao() ? '1' : '0',
                'forma_pgto' => $payload['forma_pgto'] ?? 'a_vista',
                'meio_pgto' => $payload['meio_pgto'] ?? 'dinheiro',
                'obs_contribuinte' => $obs !== '' ? $obs : null,
                'tipo_frete' => (string) ($transporte['tipo_frete'] ?? '9'),
                'transportadora_id' => filled($transporte['transportadora_id'] ?? null)
                    ? (int) $transporte['transportadora_id']
                    : null,
                'placa' => filled($transporte['placa'] ?? null)
                    ? mb_strtoupper(trim((string) $transporte['placa']), 'UTF-8')
                    : null,
                'uf_placa' => filled($transporte['uf_placa'] ?? null)
                    ? mb_strtoupper(trim((string) $transporte['uf_placa']), 'UTF-8')
                    : null,
                'especie' => filled($transporte['especie'] ?? null)
                    ? mb_strtoupper(trim((string) $transporte['especie']), 'UTF-8')
                    : null,
                'marca' => filled($transporte['marca'] ?? null)
                    ? mb_strtoupper(trim((string) $transporte['marca']), 'UTF-8')
                    : null,
                'nvol' => filled($transporte['nvol'] ?? null) ? trim((string) $transporte['nvol']) : null,
                'qvol' => max(0, (int) preg_replace('/\D/', '', (string) ($transporte['qvol'] ?? '0'))),
                'peso_l' => ErpMoney::parseBr((string) ($transporte['peso_l'] ?? '0'), 3),
                'peso_b' => ErpMoney::parseBr((string) ($transporte['peso_b'] ?? '0'), 3),
                'subtotal' => $totais['subtotal'],
                'desconto' => $totais['desconto'],
                'total' => $totais['total'],
                'total_itens' => count($calculated['rows']),
                'base_icms' => $totais['base_icms'],
                'total_icms' => $totais['valor_icms'],
                'base_ipi' => $totais['base_ipi'],
                'total_ipi' => $totais['valor_ipi'],
                'base_icms_pis' => $totais['base_pis'],
                'total_icms_pis' => $totais['valor_pis'],
                'base_icms_cofins' => $totais['base_cofins'],
                'total_icms_cofins' => $totais['valor_cofins'],
                'base_icms_st' => $totais['base_st'],
                'valor_icms_st' => $totais['valor_st'],
                'vfcp' => $totais['valor_fcp'] ?? 0,
                'vfcp_st' => $totais['valor_fcp_st'] ?? 0,
                'vfcp_st_ret' => $totais['valor_fcp_st_ret'] ?? 0,
                'vfcp_uf_dest' => $totais['vfcp_uf_dest'] ?? 0,
                'vicms_uf_dest' => $totais['vicms_uf_dest'] ?? 0,
                'vicms_uf_remet' => $totais['vicms_uf_remet'] ?? 0,
                'frete' => $totais['frete'],
                'seguro' => $totais['seguro'],
                'outros' => $totais['outras'],
                'total_desoneracao' => $totais['desoneracao'],
                'trib_fed' => $totais['trib_fed'] ?? 0,
                'trib_est' => $totais['trib_est'] ?? 0,
                'trib_mun' => $totais['trib_mun'] ?? 0,
                'trib_imp' => $totais['trib_imp'] ?? 0,
                'situacao' => Nfe::SITUACAO_ABERTA,
                'status' => Nfe::STATUS_ABERTA,
            ]);

            $params->consumeNumeroNfe();

            foreach ($calculated['rows'] as $row) {
                NfeItem::query()->create([
                    'nfe_id' => $nfe->id,
                    'item' => $row['item'],
                    'product_id' => $row['product_id'] ?? null,
                    'cod_barra' => $row['cod_barra'] ?? null,
                    'ncm' => $row['ncm'] ?? null,
                    'cfop' => $row['cfop'] ?? null,
                    'cst' => $row['cst'] ?? null,
                    'csosn' => $row['csosn'] ?? null,
                    'origem' => array_key_exists('origem', $row) ? (int) $row['origem'] : null,
                    'cest' => $row['cest'] ?? null,
                    'unidade' => $row['unidade'] ?? 'UN',
                    'descricao' => $row['descricao'] ?? '',
                    'info_adicionais' => $row['info_adicionais'] ?? null,
                    'quantidade' => $row['quantidade'],
                    'valor_unitario' => $row['valor_unitario'],
                    'desconto' => $row['desconto'] ?? 0,
                    'frete' => $row['frete'] ?? 0,
                    'seguro' => $row['seguro'] ?? 0,
                    'outros' => $row['outros'] ?? 0,
                    'total' => $row['total'],
                    'situacao' => Nfe::SITUACAO_ABERTA,
                    'base_icms' => $row['base_icms'] ?? 0,
                    'aliq_icms' => $row['aliq_icms'] ?? 0,
                    'p_red_bc_icms' => $row['p_red_bc_icms'] ?? 0,
                    'mod_bc_icms' => filled($row['mod_bc_icms'] ?? null) ? $row['mod_bc_icms'] : null,
                    'valor_icms' => $row['valor_icms'] ?? 0,
                    'base_icms_st' => $row['base_icms_st'] ?? 0,
                    'aliq_icms_st' => $row['aliq_icms_st'] ?? 0,
                    'valor_icms_st' => $row['valor_icms_st'] ?? 0,
                    'mod_bc_st' => filled($row['mod_bc_st'] ?? null) ? $row['mod_bc_st'] : null,
                    'p_mva_st' => $row['p_mva_st'] ?? 0,
                    'p_red_bc_st' => $row['p_red_bc_st'] ?? 0,
                    'vfcp' => $row['vfcp'] ?? 0,
                    'p_fcp' => $row['p_fcp'] ?? 0,
                    'v_bc_fcp' => $row['v_bc_fcp'] ?? 0,
                    'v_bc_fcp_st' => $row['v_bc_fcp_st'] ?? 0,
                    'p_fcp_st' => $row['p_fcp_st'] ?? 0,
                    'v_fcp_st' => $row['v_fcp_st'] ?? 0,
                    'base_icms_st_ret' => $row['base_icms_st_ret'] ?? 0,
                    'p_st_ret' => $row['p_st_ret'] ?? 0,
                    'valor_icms_st_ret' => $row['valor_icms_st_ret'] ?? 0,
                    'v_icms_substituto' => $row['v_icms_substituto'] ?? 0,
                    'v_bc_fcp_st_ret' => $row['v_bc_fcp_st_ret'] ?? 0,
                    'p_fcp_st_ret' => $row['p_fcp_st_ret'] ?? 0,
                    'v_fcp_st_ret' => $row['v_fcp_st_ret'] ?? 0,
                    'motivo_desoneracao' => filled($row['motivo_desoneracao'] ?? null) ? $row['motivo_desoneracao'] : null,
                    'base_desoneracao' => $row['base_desoneracao'] ?? 0,
                    'desc_desoneracao' => $row['desc_desoneracao'] ?? 0,
                    'valor_desoneracao' => $row['valor_desoneracao'] ?? 0,
                    'ind_deduz_deson' => array_key_exists('ind_deduz_deson', $row) && $row['ind_deduz_deson'] !== '' && $row['ind_deduz_deson'] !== null
                        ? (int) $row['ind_deduz_deson']
                        : null,
                    'base_ipi' => $row['base_ipi'] ?? 0,
                    'aliq_ipi' => $row['aliq_ipi'] ?? 0,
                    'valor_ipi' => $row['valor_ipi'] ?? 0,
                    'cst_ipi' => $row['cst_ipi'] ?? null,
                    'cst_pis' => $row['cst_pis'] ?? null,
                    'base_pis_icms' => $row['base_pis_icms'] ?? 0,
                    'aliq_pis_icms' => $row['aliq_pis_icms'] ?? 0,
                    'valor_pis_icms' => $row['valor_pis_icms'] ?? 0,
                    'cst_cofins' => $row['cst_cofins'] ?? null,
                    'base_cofins_icms' => $row['base_cofins_icms'] ?? 0,
                    'aliq_cofins_icms' => $row['aliq_cofins_icms'] ?? 0,
                    'valor_cofins_icms' => $row['valor_cofins_icms'] ?? 0,
                    'class_trib' => filled($row['class_trib'] ?? null) ? $row['class_trib'] : null,
                    'cst_ibs_cbs' => filled($row['cst_ibs_cbs'] ?? null) ? $row['cst_ibs_cbs'] : null,
                    'v_ibs_mun' => $row['v_ibs_mun'] ?? 0,
                    'v_ibs_uf' => $row['v_ibs_uf'] ?? 0,
                    'v_cbs' => $row['v_cbs'] ?? 0,
                    'bc_ibs' => $row['bc_ibs'] ?? 0,
                    'alq_cbs' => $row['alq_cbs'] ?? 0,
                    'alq_ibs_mun' => $row['alq_ibs_mun'] ?? 0,
                    'alq_ibs_uf' => $row['alq_ibs_uf'] ?? 0,
                    'p_red_ibs' => $row['p_red_ibs'] ?? 0,
                    'p_red_cbs' => $row['p_red_cbs'] ?? 0,
                    'trib_fed' => $row['trib_fed'] ?? 0,
                    'trib_est' => $row['trib_est'] ?? 0,
                    'trib_mun' => $row['trib_mun'] ?? 0,
                    'trib_imp' => $row['trib_imp'] ?? 0,
                    // DIFAL preenchido na transmissão (IcmsUfDest::persistir); rascunho inicia zerado.
                    'vbcufdest' => $row['vbcufdest'] ?? 0,
                    'vicmsufdest' => $row['vicmsufdest'] ?? 0,
                    'vicmsufremet' => $row['vicmsufremet'] ?? 0,
                ]);
            }

            $faturas = is_array($payload['faturas'] ?? null) ? $payload['faturas'] : [];

            foreach ($faturas as $fatura) {
                if (! is_array($fatura)) {
                    continue;
                }

                NfeFatura::query()->create([
                    'nfe_id' => $nfe->id,
                    'empresa_id' => $empresaId,
                    'numero' => (string) ($fatura['numero'] ?? ''),
                    'data_vencimento' => $fatura['data_vencimento'] ?? null,
                    'valor' => ErpMoney::parseBr((string) ($fatura['valor'] ?? '0')),
                ]);
            }

            return $nfe->fresh(['itens', 'faturas', 'cliente']) ?? $nfe;
        });
    }
}
