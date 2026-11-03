<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\IcmsAliquota;
use App\Models\Nfe;
use App\Models\NfeItem;
use App\Models\Person;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Motor DIFAL / ICMSUFDest (NF-e 55) — isolado de ICMS-ST, FCP-ST e W16-10.
 *
 * Não inventa alíquotas. Checkbox param_difal_usar NÃO desliga obrigação NA01-20.
 * Totais vFCPUFDest / vICMSUFDest / vICMSUFRemet NÃO entram em vNF.
 */
final class IcmsUfDest
{
    public const CLASS_NAO_APLICA = 'nao_aplica';

    /** SEFAZ NA01-20: CRT 1/4 dispensam o grupo no XML. */
    public const CLASS_EXCECAO_CRT_SN = 'excecao_crt_1_4';

    /** CRT 2: bloqueado — sem emissão incompleta. */
    public const CLASS_CRT2_PENDENTE = 'crt2_pendente';

    public const CLASS_EXIGE = 'exige_icmsufdest';

    private const CFOP_EXCECAO = [
        '6552', '6922', '6929',
    ];

    /**
     * Resolução SF 13/2012: alíquota interestadual de 4% quando legalmente aplicável.
     * Origens 1, 2, 3 e 8. Origens 6 e 7 (importadas sem similar nacional / lista CAMEX)
     * são exceção — usam a interestadual UF→UF (7%/12%), não 4%.
     *
     * @return list<int>
     */
    public static function origensAliquotaQuatroPorcento(): array
    {
        return [1, 2, 3, 8];
    }

    /**
     * @return array{
     *   class: string,
     *   motivo: string,
     *   id_dest: int,
     *   ind_final: int,
     *   ind_ie_dest: int,
     *   crt: int
     * }
     */
    public static function classificarOperacao(
        int $crt,
        int $idDest,
        int $indFinal,
        int $indIeDest,
        int $finNFe = 1,
        int $tpNf = 1,
        ?string $cfop = null,
        ?string $cstIcms = null,
        ?string $csosn = null,
    ): array {
        $base = [
            'id_dest' => $idDest,
            'ind_final' => $indFinal,
            'ind_ie_dest' => $indIeDest,
            'crt' => $crt,
        ];

        if ($idDest !== 2 || $indFinal !== 1 || $indIeDest !== 9) {
            return $base + [
                'class' => self::CLASS_NAO_APLICA,
                'motivo' => 'Operação fora do perfil interestadual + consumidor final + não contribuinte.',
            ];
        }

        if ($tpNf === 0) {
            return $base + ['class' => self::CLASS_NAO_APLICA, 'motivo' => 'NF-e de entrada (tpNF=0).'];
        }

        if (in_array($finNFe, [2, 3, 5, 6], true)) {
            return $base + ['class' => self::CLASS_NAO_APLICA, 'motivo' => 'Finalidade '.$finNFe.' dispensa ICMSUFDest (NA01-20).'];
        }

        $cfopN = preg_replace('/\D/', '', (string) $cfop) ?? '';
        if ($cfopN !== '' && in_array($cfopN, self::CFOP_EXCECAO, true)) {
            return $base + ['class' => self::CLASS_NAO_APLICA, 'motivo' => 'CFOP '.$cfopN.' na lista de exceção NA01-20.'];
        }

        $cst2 = ImpostoPadraoCompatibilidade::cstIcms2($cstIcms);
        $csosnN = preg_replace('/\D/', '', (string) $csosn) ?? '';
        $csosnN = $csosnN === '' ? '' : str_pad(substr($csosnN, 0, 3), 3, '0', STR_PAD_LEFT);
        if (in_array($cst2, ['40', '41'], true) || in_array($csosnN, ['103', '300', '400'], true)) {
            return $base + ['class' => self::CLASS_NAO_APLICA, 'motivo' => 'Operação isenta/imune/não tributada (CST/CSOSN).'];
        }

        if (in_array($crt, [1, 4], true)) {
            return $base + [
                'class' => self::CLASS_EXCECAO_CRT_SN,
                'motivo' => 'CRT '.$crt.': NA01-20 dispensa ICMSUFDest no XML.',
            ];
        }

        if ($crt === 2) {
            return $base + [
                'class' => self::CLASS_CRT2_PENDENTE,
                'motivo' => 'CRT 2 (excesso sublimite): DIFAL ainda não habilitado — sem emissão incompleta.',
            ];
        }

        return $base + [
            'class' => self::CLASS_EXIGE,
            'motivo' => 'CRT 3: ICMSUFDest exigível (interestadual, CF, não contribuinte).',
        ];
    }

    /**
     * Valida e calcula DIFAL por item (CRT 3). CRT 2 bloqueia; CRT 1/4 não calcula.
     *
     * @return array{
     *   exige: bool,
     *   por_item: array<int, array{
     *     v_bc_uf_dest: float,
     *     p_fcp_uf_dest: float,
     *     p_icms_uf_dest: float,
     *     p_icms_inter: float,
     *     p_icms_inter_part: float,
     *     v_fcp_uf_dest: float,
     *     v_icms_uf_dest: float,
     *     v_icms_uf_remet: float
     *   }>,
     *   totais: array{vfcp_uf_dest: float, vicms_uf_dest: float, vicms_uf_remet: float}
     * }
     */
    public static function calcularParaEmissao(
        Nfe $nfe,
        Empresa $empresa,
        Person $cliente,
        int $idDest,
        int $indFinal,
        int $indIeDest,
        int $crt,
        ?string $ufEmitente = null,
    ): array {
        $finNFe = self::mapFinNFe($nfe);
        $tpNf = ((string) ($nfe->movimento ?? '1') === '0') ? 0 : 1;
        $ufOrigem = strtoupper(trim((string) ($ufEmitente ?: $empresa->uf ?? '')));

        $nfe->loadMissing(['itens.product']);
        $vazio = [
            'exige' => false,
            'por_item' => [],
            'totais' => ['vfcp_uf_dest' => 0.0, 'vicms_uf_dest' => 0.0, 'vicms_uf_remet' => 0.0],
        ];

        $algumExige = false;
        $algumCrt2 = false;

        foreach ($nfe->itens as $item) {
            $cls = self::classificarOperacao(
                $crt,
                $idDest,
                $indFinal,
                $indIeDest,
                $finNFe,
                $tpNf,
                $item->cfop !== null ? (string) $item->cfop : null,
                $item->cst !== null ? (string) $item->cst : null,
                $item->csosn !== null ? (string) $item->csosn : null,
            );
            if ($cls['class'] === self::CLASS_CRT2_PENDENTE) {
                $algumCrt2 = true;
            }
            if ($cls['class'] === self::CLASS_EXIGE) {
                $algumExige = true;
            }
        }

        if ($algumCrt2) {
            throw new FiscalEngineException(
                'DIFAL (ICMSUFDest) para CRT 2 (Simples com excesso de sublimite) ainda não está habilitado. '
                .'Operação interestadual a consumidor final não contribuinte detectada. '
                .'Sem emissão incompleta. Desligar "Utilizar DIFAL" não remove a exigência fiscal.',
            );
        }

        if (! $algumExige) {
            return $vazio;
        }

        $aliqInternaDest = self::resolveAliquotaInternaDestino($empresa);
        $pFcpDest = self::resolveFcpDestino($empresa);

        if ($aliqInternaDest === null || $aliqInternaDest <= 0) {
            throw new FiscalEngineException(
                'Esta NF-e 55 exige ICMSUFDest (DIFAL). Informe a alíquota interna da UF destino '
                .'em Parâmetros > DIFAL > Alíquota Interna UF (e FCP destino, se houver). '
                .'Não inventamos percentual. Desligar "Utilizar DIFAL" não dispensa a obrigação NA01-20.',
            );
        }

        $porItem = [];
        $totFcp = 0.0;
        $totDest = 0.0;
        $totRemet = 0.0;

        foreach ($nfe->itens as $item) {
            $cls = self::classificarOperacao(
                $crt,
                $idDest,
                $indFinal,
                $indIeDest,
                $finNFe,
                $tpNf,
                $item->cfop !== null ? (string) $item->cfop : null,
                $item->cst !== null ? (string) $item->cst : null,
                $item->csosn !== null ? (string) $item->csosn : null,
            );
            if ($cls['class'] !== self::CLASS_EXIGE) {
                continue;
            }

            $origemMerc = self::origemMercadoriaItem($item);
            $aliqInter = self::resolveAliquotaInterestadualParaItem(
                $empresa,
                $cliente,
                $origemMerc,
                $ufOrigem,
            );

            if ($aliqInter === null || $aliqInter <= 0) {
                throw new FiscalEngineException(
                    'Item '.(string) ($item->item ?: $item->id).': DIFAL exige alíquota interestadual '
                    .$ufOrigem.'→'.strtoupper(trim((string) ($cliente->uf ?? '')))
                    .' (Tabela ICMS ou Parâmetros > DIFAL). Origem da mercadoria='.$origemMerc.'. '
                    .'Não inventamos percentual.',
                );
            }

            if ($aliqInternaDest + 0.0001 < $aliqInter) {
                throw new FiscalEngineException(
                    'Item '.(string) ($item->item ?: $item->id).': DIFAL inconsistente — alíquota interna destino ('
                    .$aliqInternaDest.'%) menor que a interestadual ('.$aliqInter.'%). Revise parâmetros/tabela.',
                );
            }

            $calc = self::calcularParaItem($item, $aliqInter, $aliqInternaDest, $pFcpDest);
            if ($calc === null) {
                throw new FiscalEngineException(
                    'Item '.(string) ($item->item ?: $item->id).': base de cálculo do DIFAL inválida (vProd líquido).',
                );
            }
            $porItem[(int) $item->id] = $calc;
            $totFcp += $calc['v_fcp_uf_dest'];
            $totDest += $calc['v_icms_uf_dest'];
            $totRemet += $calc['v_icms_uf_remet'];
        }

        return [
            'exige' => $porItem !== [],
            'por_item' => $porItem,
            'totais' => [
                'vfcp_uf_dest' => round($totFcp, 2),
                'vicms_uf_dest' => round($totDest, 2),
                'vicms_uf_remet' => round($totRemet, 2),
            ],
        ];
    }

    /** @deprecated Use calcularParaEmissao (valida + calcula). */
    public static function assertParaEmissao(
        Nfe $nfe,
        Empresa $empresa,
        Person $cliente,
        int $idDest,
        int $indFinal,
        int $indIeDest,
        int $crt,
        ?string $ufEmitente = null,
    ): void {
        self::calcularParaEmissao($nfe, $empresa, $cliente, $idDest, $indFinal, $indIeDest, $crt, $ufEmitente);
    }

    /**
     * Persiste valores DIFAL nos itens e no cabeçalho (não altera vNF / ST / FCP próprio).
     * Não grava em notas já autorizadas.
     *
     * @param  array{
     *   exige: bool,
     *   por_item: array<int, array<string, float>>,
     *   totais: array{vfcp_uf_dest: float, vicms_uf_dest: float, vicms_uf_remet: float}
     * }  $resultado
     */
    public static function persistir(Nfe $nfe, array $resultado): void
    {
        // Nunca alterar NF-e já autorizada / encerrada (status, situação ou chave+protocolo).
        if (! self::podePersistirDifal($nfe)) {
            return;
        }

        $porItem = $resultado['por_item'] ?? [];
        $totais = $resultado['totais'] ?? [
            'vfcp_uf_dest' => 0.0,
            'vicms_uf_dest' => 0.0,
            'vicms_uf_remet' => 0.0,
        ];

        $nfe->loadMissing('itens');
        foreach ($nfe->itens as $item) {
            $calc = $porItem[(int) $item->id] ?? null;
            $item->forceFill([
                'vbcufdest' => $calc['v_bc_uf_dest'] ?? 0,
                'vicmsufdest' => $calc['v_icms_uf_dest'] ?? 0,
                'vicmsufremet' => $calc['v_icms_uf_remet'] ?? 0,
            ])->save();
        }

        $nfe->forceFill([
            'vfcp_uf_dest' => $totais['vfcp_uf_dest'] ?? 0,
            'vicms_uf_dest' => $totais['vicms_uf_dest'] ?? 0,
            'vicms_uf_remet' => $totais['vicms_uf_remet'] ?? 0,
        ])->save();
    }

    public static function podePersistirDifal(Nfe $nfe): bool
    {
        $statusBloqueados = [
            Nfe::STATUS_TRANSMITIDA,
            Nfe::STATUS_CANCELADA,
            Nfe::STATUS_INUTILIZADA,
            Nfe::STATUS_DENEGADA,
            Nfe::STATUS_DUPLICIDADE,
        ];
        if (in_array((string) $nfe->status, $statusBloqueados, true)) {
            return false;
        }

        $situacaoBloqueada = [
            Nfe::SITUACAO_TRANSMITIDA,
            Nfe::SITUACAO_CANCELADA,
            Nfe::SITUACAO_INUTILIZADA,
            Nfe::SITUACAO_DENEGADA,
            Nfe::SITUACAO_DUPLICIDADE,
        ];
        if (in_array((string) $nfe->situacao, $situacaoBloqueada, true)) {
            return false;
        }

        if (filled($nfe->chave) && filled($nfe->protocolo)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{
     *   v_bc_uf_dest: float,
     *   p_fcp_uf_dest: float,
     *   p_icms_uf_dest: float,
     *   p_icms_inter: float,
     *   p_icms_inter_part: float,
     *   v_fcp_uf_dest: float,
     *   v_icms_uf_dest: float,
     *   v_icms_uf_remet: float
     * }  $calc
     */
    public static function comCalculo(ItemImpostoDto $base, array $calc): ItemImpostoDto
    {
        $vBc = round((float) $calc['v_bc_uf_dest'], 2);
        $pFcp = round((float) $calc['p_fcp_uf_dest'], 4);
        $vFcp = round((float) $calc['v_fcp_uf_dest'], 2);

        return new ItemImpostoDto(
            origem: $base->origem,
            csosn: $base->csosn,
            vBc: $base->vBc,
            vIcms: $base->vIcms,
            vPis: $base->vPis,
            vCofins: $base->vCofins,
            vTotTrib: $base->vTotTrib,
            cstIbsCbs: $base->cstIbsCbs,
            cClassTrib: $base->cClassTrib,
            vBcIbscbs: $base->vBcIbscbs,
            pIbsUf: $base->pIbsUf,
            vIbsUf: $base->vIbsUf,
            pIbsMun: $base->pIbsMun,
            vIbsMun: $base->vIbsMun,
            pCbs: $base->pCbs,
            vCbs: $base->vCbs,
            pRedIbs: $base->pRedIbs,
            pRedCbs: $base->pRedCbs,
            cstIcms: $base->cstIcms,
            pIcms: $base->pIcms,
            pPis: $base->pPis,
            vBcPis: $base->vBcPis,
            cstPis: $base->cstPis,
            pCofins: $base->pCofins,
            vBcCofins: $base->vBcCofins,
            cstCofins: $base->cstCofins,
            vIpi: $base->vIpi,
            pIpi: $base->pIpi,
            vBcIpi: $base->vBcIpi,
            cstIpi: $base->cstIpi,
            vIcmsDeson: $base->vIcmsDeson,
            motivoDesoneracao: $base->motivoDesoneracao,
            indDeduzDeson: $base->indDeduzDeson,
            crt: $base->crt,
            pRedBc: $base->pRedBc,
            modBc: $base->modBc,
            vBcSt: $base->vBcSt,
            pIcmsSt: $base->pIcmsSt,
            vIcmsSt: $base->vIcmsSt,
            pMvaSt: $base->pMvaSt,
            pRedBcSt: $base->pRedBcSt,
            modBcSt: $base->modBcSt,
            vBcFcp: $base->vBcFcp,
            pFcp: $base->pFcp,
            vFcp: $base->vFcp,
            vBcFcpSt: $base->vBcFcpSt,
            pFcpSt: $base->pFcpSt,
            vFcpSt: $base->vFcpSt,
            vBcStRet: $base->vBcStRet,
            pStRet: $base->pStRet,
            vIcmsSubstituto: $base->vIcmsSubstituto,
            vIcmsStRet: $base->vIcmsStRet,
            vBcFcpStRet: $base->vBcFcpStRet,
            pFcpStRet: $base->pFcpStRet,
            vFcpStRet: $base->vFcpStRet,
            vBcUfDest: $vBc,
            vBcFcpUfDest: $pFcp > 0 ? $vBc : 0.0,
            pFcpUfDest: $pFcp,
            pIcmsUfDest: round((float) $calc['p_icms_uf_dest'], 4),
            pIcmsInter: round((float) $calc['p_icms_inter'], 4),
            pIcmsInterPart: round((float) $calc['p_icms_inter_part'], 4),
            vFcpUfDest: $vFcp,
            vIcmsUfDest: round((float) $calc['v_icms_uf_dest'], 2),
            vIcmsUfRemet: round((float) $calc['v_icms_uf_remet'], 2),
        );
    }

    /**
     * @return array{
     *   v_bc_uf_dest: float,
     *   p_fcp_uf_dest: float,
     *   p_icms_uf_dest: float,
     *   p_icms_inter: float,
     *   p_icms_inter_part: float,
     *   v_fcp_uf_dest: float,
     *   v_icms_uf_dest: float,
     *   v_icms_uf_remet: float
     * }|null
     */
    public static function calcularParaItem(
        NfeItem $item,
        float $pIcmsInter,
        float $pIcmsUfDest,
        float $pFcpUfDest = 0.0,
    ): ?array {
        $qtd = (float) ($item->quantidade ?? 0);
        $vu = (float) ($item->valor_unitario ?? 0);
        $vProd = round($qtd * $vu, 2);
        if ($vProd <= 0) {
            $vProd = round((float) ($item->total ?? 0), 2);
        }
        $vDesc = round((float) ($item->desconto ?? 0), 2);
        $vBc = round(max(0, $vProd - $vDesc), 2);
        if ($vBc <= 0) {
            return null;
        }

        $pDiff = round(max(0, $pIcmsUfDest - $pIcmsInter), 4);
        $vIcmsUfDest = round($vBc * $pDiff / 100, 2);
        $vFcpUfDest = $pFcpUfDest > 0 ? round($vBc * $pFcpUfDest / 100, 2) : 0.0;

        return [
            'v_bc_uf_dest' => $vBc,
            'p_fcp_uf_dest' => round($pFcpUfDest, 4),
            'p_icms_uf_dest' => round($pIcmsUfDest, 4),
            'p_icms_inter' => round($pIcmsInter, 4),
            'p_icms_inter_part' => 100.0,
            'v_fcp_uf_dest' => $vFcpUfDest,
            'v_icms_uf_dest' => $vIcmsUfDest,
            'v_icms_uf_remet' => 0.0,
        ];
    }

    /**
     * Interestadual por item: 4% (Res. 13/2012) quando origem aplicável;
     * senão matriz UF→UF; senão param_difal_aliquota_interestadual. Não inventa %.
     */
    public static function resolveAliquotaInterestadualParaItem(
        Empresa $empresa,
        Person $cliente,
        int $origemMercadoria,
        ?string $ufEmitente = null,
    ): ?float {
        if (in_array($origemMercadoria, self::origensAliquotaQuatroPorcento(), true)) {
            return 4.0;
        }

        return self::resolveAliquotaInterestadual($empresa, $cliente, $ufEmitente);
    }

    public static function resolveAliquotaInterestadual(
        Empresa $empresa,
        Person $cliente,
        ?string $ufEmitente = null,
    ): ?float {
        $origem = strtoupper(trim((string) ($ufEmitente ?: $empresa->uf ?? '')));
        $destino = strtoupper(trim((string) ($cliente->uf ?? '')));

        $daTabela = ($origem !== '' && $destino !== '')
            ? IcmsAliquota::rate($origem, $destino)
            : null;

        if ($daTabela !== null && $daTabela > 0) {
            return $daTabela;
        }

        $param = (float) ($empresa->param_difal_aliquota_interestadual ?? 0);

        return $param > 0 ? $param : null;
    }

    public static function resolveAliquotaInternaDestino(Empresa $empresa): ?float
    {
        $param = (float) ($empresa->param_difal_aliquota_interna ?? 0);

        return $param > 0 ? $param : null;
    }

    public static function resolveFcpDestino(Empresa $empresa): float
    {
        return max(0.0, (float) ($empresa->param_difal_fcp_pct ?? 0));
    }

    public static function origemMercadoriaItem(NfeItem $item): int
    {
        if ($item->origem !== null && $item->origem !== '') {
            return (int) $item->origem;
        }

        return (int) ($item->product?->origem ?? 0);
    }

    private static function mapFinNFe(Nfe $nfe): int
    {
        return match (strtolower((string) ($nfe->finalidade ?? 'normal'))) {
            'complementar', '2' => 2,
            'ajuste', '3' => 3,
            'devolucao', '4' => 4,
            default => 1,
        };
    }
}
