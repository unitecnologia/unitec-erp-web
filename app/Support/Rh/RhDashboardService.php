<?php

namespace App\Support\Rh;

use App\Models\RhAnexo;
use App\Models\RhEscalaItem;
use App\Models\RhFuncionario;
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
            $docs = 0;

            if (Schema::hasTable('rh_anexos')) {
                $exames = (int) RhAnexo::query()
                    ->where('categoria', RhAnexo::CAT_EXAME)
                    ->vencendoEm(30)
                    ->count();
                $epis = (int) RhAnexo::query()
                    ->where('categoria', RhAnexo::CAT_EPI)
                    ->vencendoEm(30)
                    ->count();
                $docs = (int) RhAnexo::query()
                    ->where('categoria', RhAnexo::CAT_DOCUMENTO)
                    ->vencendoEm(30)
                    ->count();
            }

            $folga = 0;
            $plantao = 0;

            if (Schema::hasTable('rh_escala_itens')) {
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
                'ferias' => 0,
                'exames_vencendo' => $exames,
                'epis_vencendo' => $epis,
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
