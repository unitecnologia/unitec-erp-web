<?php

namespace App\Support\Rh;

use App\Models\RhAnexo;
use App\Models\RhEpiEntrega;
use App\Models\RhEscalaItem;
use App\Support\Rh\RhEscalaService;
use App\Models\RhExame;
use App\Models\RhFeriasGozo;
use App\Models\RhFuncionario;
use App\Models\RhTreinamentoParticipante;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class RhDashboardService
{
    /**
     * @return array{
     *   funcionarios: int,
     *   ativos: int,
     *   demitidos: int,
     *   ferias: int,
     *   exames_vencendo: int,
     *   epis_vencendo: int,
     *   treinamentos_vencendo: int,
     *   docs_vencendo: int,
     *   aniversariantes: int,
     *   folga_hoje: int,
     *   plantao_hoje: int
     * }
     */
    public function snapshot(): array
    {
        $empty = [
            'funcionarios' => 0,
            'ativos' => 0,
            'demitidos' => 0,
            'ferias' => 0,
            'exames_vencendo' => 0,
            'epis_vencendo' => 0,
            'treinamentos_vencendo' => 0,
            'docs_vencendo' => 0,
            'aniversariantes' => 0,
            'folga_hoje' => 0,
            'plantao_hoje' => 0,
        ];

        try {
            if (! Schema::hasTable('rh_funcionarios')) {
                return $empty;
            }

            $ativos = RhFuncionario::query()->where('ativo', true)->whereNull('data_demissao');
            $totalAtivos = (int) (clone $ativos)->count();
            $total = (int) RhFuncionario::query()->count();
            $demitidos = (int) RhFuncionario::query()->whereNotNull('data_demissao')->count();

            $hoje = Carbon::today();
            $mes = (int) $hoje->month;
            $dia = (int) $hoje->day;

            $aniversariantes = (int) RhFuncionario::query()
                ->where('ativo', true)
                ->whereNull('data_demissao')
                ->whereNotNull('data_nascimento')
                ->whereMonth('data_nascimento', $mes)
                ->whereDay('data_nascimento', $dia)
                ->count();

            $exames = 0;
            $epis = 0;
            $treinamentos = 0;
            $docs = 0;

            if (Schema::hasTable('rh_anexos')) {
                $docs = (int) RhAnexo::query()
                    ->where('categoria', RhAnexo::CAT_DOCUMENTO)
                    ->vencendoEm(30)
                    ->count();
            }

            $dataHoje = $hoje->toDateString();
            $limite = $hoje->copy()->addDays(30)->toDateString();

            if (Schema::hasTable('rh_epi_entregas')) {
                $epis = (int) RhEpiEntrega::query()
                    ->where('situacao', RhEpiEntrega::SIT_ENTREGUE)
                    ->where('ativo', true)
                    ->whereNotNull('previsao_troca')
                    ->whereDate('previsao_troca', '>=', $dataHoje)
                    ->whereDate('previsao_troca', '<=', $limite)
                    ->count();
            }

            if (Schema::hasTable('rh_exames')) {
                // Conta só próximos exames (prazo informado). Sem conclusão clínica no painel.
                $exames = (int) RhExame::query()
                    ->where('ativo', true)
                    ->whereNotNull('proximo_em')
                    ->whereDate('proximo_em', '>=', $dataHoje)
                    ->whereDate('proximo_em', '<=', $limite)
                    ->count();
            }

            if (Schema::hasTable('rh_treinamento_participantes')) {
                $treinamentos = (int) RhTreinamentoParticipante::query()
                    ->where('situacao', RhTreinamentoParticipante::SIT_ATIVO)
                    ->where('ativo', true)
                    ->whereNotNull('vencimento_em')
                    ->whereDate('vencimento_em', '>=', $dataHoje)
                    ->whereDate('vencimento_em', '<=', $limite)
                    ->count();
            }

            $ferias = 0;

            if (Schema::hasTable('rh_ferias_gozos')) {
                $ferias = (int) RhFeriasGozo::query()
                    ->where('ativo', true)
                    ->where('situacao', RhFeriasGozo::SIT_EM_ANDAMENTO)
                    ->whereDate('gozo_inicio', '<=', $dataHoje)
                    ->whereDate('gozo_fim', '>=', $dataHoje)
                    ->count();
            }

            $folga = 0;
            $plantao = 0;

            if (Schema::hasTable('rh_escala_vinculos')) {
                $contagem = (new RhEscalaService)->contagemHojePorTipo();
                $folga = (int) ($contagem['folga'] ?? 0);
                $plantao = (int) ($contagem['plantao'] ?? 0);
            } elseif (Schema::hasTable('rh_escala_itens')) {
                $dow = (int) $hoje->dayOfWeek;
                $folga = (int) RhEscalaItem::query()
                    ->where('dia_semana', $dow)
                    ->where('tipo', RhEscalaItem::TIPO_FOLGA)
                    ->count();
                $plantao = (int) RhEscalaItem::query()
                    ->where('dia_semana', $dow)
                    ->where('tipo', RhEscalaItem::TIPO_PLANTAO)
                    ->count();
            }

            return [
                'funcionarios' => $total,
                'ativos' => $totalAtivos,
                'demitidos' => $demitidos,
                'ferias' => $ferias,
                'exames_vencendo' => $exames,
                'epis_vencendo' => $epis,
                'treinamentos_vencendo' => $treinamentos,
                'docs_vencendo' => $docs,
                'aniversariantes' => $aniversariantes,
                'folga_hoje' => $folga,
                'plantao_hoje' => $plantao,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }
}
