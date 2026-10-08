<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Numeração fiscal NFC-e única por empresa + modelo + série + ambiente:
 * - nfce_numeracoes: contador travado (SELECT ... FOR UPDATE);
 * - nfce_numeros_usados: livro de números com índice único (nenhum número sai duas vezes);
 * - nfce_inutilizacoes: protocolo/XML das inutilizações (F3);
 * - nfce_tentativas: histórico imutável das tentativas fiscais (entra no dump de backup).
 * Números já existentes em pdv_venda_nfce são registrados como "legado" (nada é renumerado).
 * O vínculo pdv_venda_nfce → pdv_vendas deixa de ser em cascata: excluir venda/sessão/usuário
 * não pode apagar documento fiscal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfce_numeracoes')) {
            Schema::create('nfce_numeracoes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->string('modelo', 2)->default('65');
                $table->unsignedSmallInteger('serie');
                $table->unsignedTinyInteger('ambiente');
                $table->unsignedInteger('ultimo_numero')->default(0);
                $table->timestamp('inicializado_em')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'modelo', 'serie', 'ambiente'], 'nfce_numeracao_uq');
            });
        }

        if (! Schema::hasTable('nfce_numeros_usados')) {
            Schema::create('nfce_numeros_usados', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->string('modelo', 2)->default('65');
                $table->unsignedSmallInteger('serie');
                $table->unsignedTinyInteger('ambiente');
                $table->unsignedInteger('numero');
                $table->string('origem', 20);
                $table->unsignedBigInteger('terminal_id')->nullable();
                $table->unsignedBigInteger('pdv_venda_nfce_id')->nullable();
                $table->string('observacao', 255)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->unique(['empresa_id', 'modelo', 'serie', 'ambiente', 'numero'], 'nfce_numero_usado_uq');
                $table->index('pdv_venda_nfce_id', 'nfce_numero_usado_nfce_idx');
            });
        }

        if (! Schema::hasTable('nfce_inutilizacoes')) {
            Schema::create('nfce_inutilizacoes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->string('modelo', 2)->default('65');
                $table->unsignedSmallInteger('serie');
                $table->unsignedTinyInteger('ambiente');
                $table->unsignedInteger('numero_inicial');
                $table->unsignedInteger('numero_final');
                $table->string('protocolo', 30)->nullable();
                $table->string('status_codigo', 5)->nullable();
                $table->string('justificativa', 255)->nullable();
                $table->longText('xml')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'serie', 'ambiente'], 'nfce_inutilizacao_faixa_idx');
            });
        }

        if (! Schema::hasTable('nfce_tentativas')) {
            Schema::create('nfce_tentativas', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('pdv_venda_nfce_id');
                $table->unsignedBigInteger('pdv_venda_id')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->string('chave', 44)->nullable();
                $table->string('serie', 3)->nullable();
                $table->unsignedInteger('numero')->nullable();
                $table->string('status', 20)->nullable();
                $table->string('protocolo', 30)->nullable();
                $table->string('motivo', 500);
                $table->longText('registro');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('arquivado_em')->nullable();

                $table->index(['pdv_venda_nfce_id', 'chave'], 'nfce_tentativa_nfce_chave_idx');
                $table->index('chave', 'nfce_tentativa_chave_idx');
            });
        }

        $this->registrarNumerosLegados();
        $this->importarTentativasArquivadas();
        $this->protegerNfceContraCascata();
    }

    public function down(): void
    {
        // Tabelas fiscais (livro de números, inutilizações, histórico) não são removidas no rollback.
    }

    private function registrarNumerosLegados(): void
    {
        if (! Schema::hasTable('pdv_venda_nfce')) {
            return;
        }

        $agora = now();

        DB::table('pdv_venda_nfce')
            ->select(['id', 'empresa_id', 'modelo', 'serie', 'numero', 'ambiente', 'status'])
            ->whereNotNull('empresa_id')
            ->whereNotNull('numero')
            ->where('numero', '>', 0)
            ->where(fn ($q) => $q->where('simulada', false)->orWhereNull('simulada'))
            ->where('status', '<>', 'simulada')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($agora): void {
                // Documento válido tem prioridade sobre tentativa rejeitada com o mesmo número.
                $validos = ['autorizada', 'cancelada', 'contingencia', 'pendente'];
                $ordenadas = collect($rows)->sortBy(fn ($r): int => in_array((string) $r->status, $validos, true) ? 0 : 1);

                $linhas = $ordenadas->map(fn ($r): array => [
                    'empresa_id' => (int) $r->empresa_id,
                    'modelo' => (string) ($r->modelo ?: '65'),
                    'serie' => ((int) ltrim((string) ($r->serie ?: '1'), '0')) ?: 1,
                    'ambiente' => (int) ($r->ambiente ?: 2),
                    'numero' => (int) $r->numero,
                    'origem' => 'legado',
                    'pdv_venda_nfce_id' => (int) $r->id,
                    'observacao' => 'Registrado na criação do livro de numeração ('.$r->status.').',
                    'created_at' => $agora,
                ])->values()->all();

                if ($linhas !== []) {
                    DB::table('nfce_numeros_usados')->insertOrIgnore($linhas);
                }
            });
    }

    private function importarTentativasArquivadas(): void
    {
        $base = storage_path('app/fiscal/nfce-tentativas');

        if (! is_dir($base)) {
            return;
        }

        foreach (File::allFiles($base) as $arquivo) {
            if (strtolower($arquivo->getExtension()) !== 'json') {
                continue;
            }

            $dados = json_decode((string) @file_get_contents($arquivo->getPathname()), true);
            $registro = is_array($dados) ? ($dados['registro'] ?? null) : null;

            if (! is_array($registro) || empty($registro['id'])) {
                continue;
            }

            $chave = $registro['chave'] ?? null;
            $arquivadoEm = $dados['arquivado_em'] ?? null;

            $existe = DB::table('nfce_tentativas')
                ->where('pdv_venda_nfce_id', (int) $registro['id'])
                ->where('chave', $chave)
                ->where('arquivado_em', $arquivadoEm ? date('Y-m-d H:i:s', strtotime((string) $arquivadoEm)) : null)
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('nfce_tentativas')->insert([
                'pdv_venda_nfce_id' => (int) $registro['id'],
                'pdv_venda_id' => $registro['pdv_venda_id'] ?? null,
                'empresa_id' => $registro['empresa_id'] ?? null,
                'chave' => $chave,
                'serie' => $registro['serie'] ?? null,
                'numero' => $registro['numero'] ?? null,
                'status' => $registro['status'] ?? null,
                'protocolo' => $registro['protocolo'] ?? null,
                'motivo' => mb_substr((string) ($dados['motivo'] ?? 'Importado do arquivo de histórico'), 0, 500, 'UTF-8'),
                'registro' => json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'arquivado_em' => $arquivadoEm ? date('Y-m-d H:i:s', strtotime((string) $arquivadoEm)) : now(),
            ]);
        }
    }

    /**
     * pdv_venda_nfce.pdv_venda_id: ON DELETE CASCADE → RESTRICT (MySQL/MariaDB).
     * Falha aqui não pode impedir a atualização do cliente: só registra aviso.
     */
    private function protegerNfceContraCascata(): void
    {
        if (! Schema::hasTable('pdv_venda_nfce') || ! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        try {
            $tabela = DB::getTablePrefix().'pdv_venda_nfce';
            $referenciada = DB::getTablePrefix().'pdv_vendas';

            $fk = DB::selectOne(
                'SELECT rc.CONSTRAINT_NAME AS nome, rc.DELETE_RULE AS regra
                   FROM information_schema.REFERENTIAL_CONSTRAINTS rc
                   JOIN information_schema.KEY_COLUMN_USAGE k
                     ON k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                  WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
                    AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ? AND k.REFERENCED_TABLE_NAME = ?
                  LIMIT 1',
                [$tabela, 'pdv_venda_id', $referenciada],
            );

            if ($fk !== null && strtoupper((string) $fk->regra) === 'RESTRICT') {
                return;
            }

            if ($fk !== null) {
                DB::statement('ALTER TABLE `'.$tabela.'` DROP FOREIGN KEY `'.$fk->nome.'`');
            }

            DB::statement(
                'ALTER TABLE `'.$tabela.'` ADD CONSTRAINT `'.$tabela.'_pdv_venda_id_foreign` '
                .'FOREIGN KEY (`pdv_venda_id`) REFERENCES `'.$referenciada.'` (`id`) ON DELETE RESTRICT'
            );
        } catch (Throwable $e) {
            Log::warning('Migration NFC-e: não foi possível trocar o vínculo em cascata por RESTRICT.', [
                'erro' => $e->getMessage(),
            ]);
        }
    }
};
