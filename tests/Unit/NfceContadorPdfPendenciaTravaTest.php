<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Filament\Resources\NfceResource\Pages\Concerns\ManagesNfceContadorEmail;
use App\Models\Contador;
use App\Models\Empresa;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trava F11 / envio do pacote NFC-e: contingência e duplicidade na competência.
 */
final class NfceContadorPdfPendenciaTravaTest extends TestCase
{
    use DatabaseTransactions;

    private string $competencia = '2026-08';

    public function test_mes_sem_pendencias_permite(): void
    {
        $empresa = $this->novaEmpresa();
        $this->criarNfce($empresa, PdvVendaNfce::STATUS_AUTORIZADA, '2026-08-15 10:00:00');

        $this->assertTrue($this->harness()->assertPublic($empresa, $this->competencia));
    }

    public function test_mes_com_contingencia_bloqueia(): void
    {
        $empresa = $this->novaEmpresa();
        $this->criarNfce($empresa, PdvVendaNfce::STATUS_CONTINGENCIA, '2026-08-15 10:00:00', autorizadaEm: null);

        $this->assertFalse($this->harness()->assertPublic($empresa, $this->competencia));
    }

    public function test_mes_com_duplicidade_bloqueia(): void
    {
        $empresa = $this->novaEmpresa();
        $this->criarNfce($empresa, 'duplicidade', '2026-08-15 10:00:00', autorizadaEm: null);

        $this->assertFalse($this->harness()->assertPublic($empresa, $this->competencia));
    }

    public function test_pendencia_de_outro_mes_nao_bloqueia(): void
    {
        $empresa = $this->novaEmpresa();
        $this->criarNfce($empresa, PdvVendaNfce::STATUS_CONTINGENCIA, '2026-07-20 10:00:00', autorizadaEm: null);
        $this->criarNfce($empresa, 'duplicidade', '2026-09-02 10:00:00', autorizadaEm: null);

        $this->assertTrue($this->harness()->assertPublic($empresa, $this->competencia));
    }

    public function test_pendencia_de_outra_empresa_nao_bloqueia(): void
    {
        $empresaA = $this->novaEmpresa('A');
        $empresaB = $this->novaEmpresa('B');
        $this->criarNfce($empresaB, PdvVendaNfce::STATUS_CONTINGENCIA, '2026-08-15 10:00:00', autorizadaEm: null);
        $this->criarNfce($empresaB, 'duplicidade', '2026-08-16 10:00:00', autorizadaEm: null);

        $this->assertTrue($this->harness()->assertPublic($empresaA, $this->competencia));
    }

    public function test_envio_bloqueia_se_pendencia_surgir_apos_abrir_modal(): void
    {
        $empresa = $this->novaEmpresa();
        Contador::query()->create([
            'codigo' => (string) random_int(100000, 999999),
            'nome' => 'CONTADOR TESTE',
            'email' => 'contador@teste.local',
        ]);

        $harness = $this->harness();

        // Sem pendência: F11 / assert libera.
        $this->assertTrue($harness->assertPublic($empresa, $this->competencia));

        // Pendência surge depois (antes do envio).
        $this->criarNfce($empresa, PdvVendaNfce::STATUS_CONTINGENCIA, '2026-08-18 12:00:00', autorizadaEm: null);

        $this->assertFalse($harness->assertPublic($empresa, $this->competencia));
        $this->assertNull($harness->buildPublic($empresa, $this->competencia));
    }

    public function test_trava_nao_dispara_consulta_ao_listar_sem_chamar_f11(): void
    {
        $empresa = $this->novaEmpresa();
        $this->criarNfce($empresa, PdvVendaNfce::STATUS_CONTINGENCIA, '2026-08-15 10:00:00', autorizadaEm: null);

        $travaQueries = 0;

        DB::listen(function ($query) use (&$travaQueries): void {
            $sql = strtolower($query->sql);
            $bindings = array_map(
                static fn ($b): string => strtolower((string) $b),
                $query->bindings,
            );

            $mencionaPendencia = str_contains($sql, 'contingencia')
                || str_contains($sql, 'duplicidade')
                || in_array('contingencia', $bindings, true)
                || in_array('duplicidade', $bindings, true);

            $pareceTravaCompetencia = str_contains($sql, 'autorizada_em')
                && str_contains($sql, 'exists')
                && str_contains($sql, 'fechado_em');

            if ($mencionaPendencia && $pareceTravaCompetencia) {
                $travaQueries++;
            }
        });

        // Simula listagem: query da grade por status da aba (sem a trava do contador).
        PdvVendaNfce::query()
            ->where('empresa_id', $empresa->id)
            ->whereIn('status', PdvVendaNfce::statusesForTab(PdvVendaNfce::TAB_TRANSMITIDOS))
            ->limit(50)
            ->get(['id', 'status', 'empresa_id']);

        PdvVendaNfce::query()
            ->where('empresa_id', $empresa->id)
            ->whereIn('status', PdvVendaNfce::statusesForTab(PdvVendaNfce::TAB_CONTINGENCIA))
            ->limit(50)
            ->get(['id', 'status', 'empresa_id']);

        $this->assertSame(0, $travaQueries, 'Listagem não deve executar a query composta da trava do F11.');
    }

    private function harness(): object
    {
        return new class
        {
            use ManagesNfceContadorEmail;

            public string $nfceContadorCompetencia = '';

            public function assertPublic(Empresa $empresa, string $competencia): bool
            {
                return $this->assertNfceContadorSemPendencias($empresa, $competencia);
            }

            public function buildPublic(Empresa $empresa, string $competencia): ?array
            {
                $this->nfceContadorCompetencia = $competencia;

                return $this->buildNfceContadorPacoteOrNotify(
                    app(\App\Support\Erp\Nfce\NfceContadorPacoteService::class),
                    $empresa,
                );
            }
        };
    }

    private function novaEmpresa(string $suffix = ''): Empresa
    {
        return Empresa::query()->create([
            'codigo' => (string) random_int(10000, 99999),
            'nome' => 'EMPRESA TRAVA NFCE '.$suffix.random_int(1000, 9999),
            'ativo' => true,
        ]);
    }

    private function criarNfce(
        Empresa $empresa,
        string $status,
        string $fechadoEm,
        ?string $autorizadaEm = 'use-fechado',
    ): PdvVendaNfce {
        $user = User::factory()->create(['empresa_id' => $empresa->id]);

        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'valor_abertura' => 0,
            'aberto_em' => $fechadoEm,
        ]);

        $venda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'numero' => random_int(1, 999999),
            'subtotal' => 10,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 10,
            'forma_pagamento' => 'DINHEIRO',
            'situacao' => 'F',
            'fechado_em' => $fechadoEm,
        ]);

        $autorizada = $autorizadaEm === 'use-fechado' ? $fechadoEm : $autorizadaEm;

        return PdvVendaNfce::query()->create([
            'pdv_venda_id' => $venda->id,
            'empresa_id' => $empresa->id,
            'operacao' => 'nfce',
            'modelo' => '65',
            'serie' => '1',
            'numero' => random_int(1, 999999),
            'status' => $status,
            'autorizada_em' => $autorizada,
        ]);
    }
}
