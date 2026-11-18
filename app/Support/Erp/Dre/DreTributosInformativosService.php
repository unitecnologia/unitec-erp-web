<?php

namespace App\Support\Erp\Dre;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Support\Erp\Tef\TefMoney;
use App\Support\Fiscal\RegimeCrtMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D2.7.1 — Tributos fiscais informativos (não deduzem receita).
 *
 * Fonte segura: NF-e de saída autorizada/transmitida, empresa CRT 3,
 * valores persistidos (ICMS próprio, ICMS-ST cobrada, PIS, COFINS).
 * Não lê XML de NFC-e. Não inventa alíquotas. IBPT ignorado.
 */
final class DreTributosInformativosService
{
    /**
     * @return array{
     *   disponivel: bool,
     *   regime: string,
     *   crt: int,
     *   origem: string,
     *   competencia: string,
     *   icms_proprio: ?string,
     *   icms_st_cobrada: ?string,
     *   pis: ?string,
     *   cofins: ?string,
     *   total_destacado: ?string,
     *   docs_considerados: int,
     *   cobertura_pct: float,
     *   receita_com_nfe: string,
     *   pendencia: string,
     *   aviso: string
     * }
     */
    public function calcular(int $empresaId, string $periodoDe, string $periodoAte, ?string $receitaBruta = null): array
    {
        $periodoDe = substr(trim($periodoDe), 0, 10);
        $periodoAte = substr(trim($periodoAte), 0, 10);
        $base = [
            'disponivel' => false,
            'regime' => '',
            'crt' => 0,
            'origem' => 'nfes (saída, autorizada)',
            'competencia' => 'data_emissao da NF-e',
            'icms_proprio' => null,
            'icms_st_cobrada' => null,
            'pis' => null,
            'cofins' => null,
            'total_destacado' => null,
            'docs_considerados' => 0,
            'cobertura_pct' => 0.0,
            'receita_com_nfe' => '0.00',
            'pendencia' => 'Sem período/empresa válidos.',
            'aviso' => 'Valores destacados na NF-e — não representam automaticamente imposto devido nem são deduzidos da receita.',
        ];

        if ($empresaId <= 0 || $periodoDe === '' || $periodoAte === '' || $periodoDe > $periodoAte) {
            return $base;
        }

        if (! Schema::hasTable('empresas')) {
            $base['pendencia'] = 'Cadastro de empresa indisponível para regime tributário.';

            return $base;
        }

        $empresa = Empresa::query()->whereKey($empresaId)->first(['id', 'regime_tributario']);
        $regime = (string) ($empresa?->regime_tributario ?? '');
        $crt = RegimeCrtMapper::toCrt($regime !== '' ? $regime : 'simples', false);
        $base['regime'] = RegimeCrtMapper::normalize($regime !== '' ? $regime : 'simples');
        $base['crt'] = $crt;

        if (! RegimeCrtMapper::isRegimeNormal($crt)) {
            $base['origem'] = 'Não aplicável ao regime da empresa';
            $base['pendencia'] = $crt === 1 || $crt === 4
                ? 'Simples/MEI: apuração tributária informativa não disponível (DAS fora do documento fiscal).'
                : 'CRT '.$crt.': apuração tributária informativa não disponível nesta versão.';
            $base['aviso'] = $base['pendencia'];

            return $base;
        }

        if (! Schema::hasTable('nfes')
            || ! Schema::hasColumn('nfes', 'total_icms')
            || ! Schema::hasColumn('nfes', 'total_icms_pis')
        ) {
            $base['pendencia'] = 'Schema de NF-e indisponível para leitura de tributos.';

            return $base;
        }

        $q = DB::table('nfes')
            ->where('empresa_id', $empresaId)
            ->whereDate('data_emissao', '>=', $periodoDe)
            ->whereDate('data_emissao', '<=', $periodoAte)
            ->where(function ($w): void {
                $w->where('status', Nfe::STATUS_TRANSMITIDA)
                    ->orWhere('situacao', Nfe::SITUACAO_TRANSMITIDA);
            })
            ->where(function ($w): void {
                // Saída: movimento 1 / saida / null legado.
                $w->whereNull('movimento')
                    ->orWhere('movimento', '1')
                    ->orWhere('movimento', 'saida');
            })
            ->where(function ($w): void {
                // Exclui ajuste/devolução (entrada/devolução compra).
                $w->whereNull('finalidade')
                    ->orWhereIn('finalidade', ['1', 'normal', 'NORMAL']);
            });

        if (Schema::hasColumn('nfes', 'devolucao_compra_id')) {
            $q->whereNull('devolucao_compra_id');
        }

        $rows = $q->get([
            'id',
            'total',
            'total_icms',
            'valor_icms_st',
            'total_icms_pis',
            'total_icms_cofins',
            'venda_id',
            'pdv_venda_id',
            'ordem_servico_id',
        ]);

        $icms = '0.00';
        $st = '0.00';
        $pis = '0.00';
        $cofins = '0.00';
        $receitaNfe = '0.00';
        $docs = 0;
        $vendaIds = [];

        foreach ($rows as $row) {
            $docs++;
            $icms = TefMoney::add($icms, (string) ($row->total_icms ?? 0));
            $st = TefMoney::add($st, (string) ($row->valor_icms_st ?? 0));
            $pis = TefMoney::add($pis, (string) ($row->total_icms_pis ?? 0));
            $cofins = TefMoney::add($cofins, (string) ($row->total_icms_cofins ?? 0));
            $receitaNfe = TefMoney::add($receitaNfe, (string) ($row->total ?? 0));

            $vid = (int) ($row->venda_id ?? 0);
            if ($vid > 0) {
                $vendaIds[$vid] = true;
            }
        }

        $total = TefMoney::add(TefMoney::add(TefMoney::add($icms, $st), $pis), $cofins);
        $bruta = TefMoney::normalize($receitaBruta ?? 0);
        $cobertura = 0.0;
        if (TefMoney::cmp($bruta, '0') === 1) {
            // Cobertura aproximada: total NF-e / receita bruta comercial (teto 100).
            $pct = (float) $receitaNfe / (float) $bruta * 100.0;
            $cobertura = round(min(100.0, max(0.0, $pct)), 2);
        } elseif ($docs > 0) {
            $cobertura = 100.0;
        }

        $pendencias = [];
        if ($docs === 0) {
            $pendencias[] = 'Nenhuma NF-e de saída autorizada no período para CRT 3.';
        }
        $pendencias[] = 'NFC-e sem tributos denormalizados — fora desta leitura.';
        $pendencias[] = 'NFS-e/ISS e Simples permanecem pendentes.';
        if ($docs > 0) {
            $pendencias[] = 'Destacado ≠ devido: uso apenas informativo.';
        }

        return [
            'disponivel' => $docs > 0,
            'regime' => $base['regime'],
            'crt' => $crt,
            'origem' => 'NF-e saída autorizada (CRT 3) — competência data_emissao',
            'competencia' => 'data_emissao',
            'icms_proprio' => $docs > 0 ? $icms : null,
            'icms_st_cobrada' => $docs > 0 ? $st : null,
            'pis' => $docs > 0 ? $pis : null,
            'cofins' => $docs > 0 ? $cofins : null,
            'total_destacado' => $docs > 0 ? $total : null,
            'docs_considerados' => $docs,
            'cobertura_pct' => $cobertura,
            'receita_com_nfe' => $receitaNfe,
            'vendas_vinculadas' => count($vendaIds),
            'pendencia' => implode(' ', $pendencias),
            'aviso' => $base['aviso'],
        ];
    }
}
