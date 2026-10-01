<?php

namespace Tests\Unit;

use App\Models\ContaReceber;
use App\Models\Person;
use App\Support\Erp\Queries\ContaReceberListQueryBuilder;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class ContaReceberListDocumentoSearchTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_doc_numerico_encontra_sem_prefixo_e_ignora_numero_parcial(): void
    {
        $cliente = Person::query()->create([
            'codigo' => 'CRDOC1',
            'nome_razao' => 'CLIENTE DOC',
        ]);

        $docs = [
            'FV-83',
            'FV-83/2',
            'PDV-000083',
            'PDV-000083/1',
            'OS-12',
            'FV-183',
            'FV-830',
            'PDV-000012',
        ];

        foreach ($docs as $index => $documento) {
            ContaReceber::query()->create([
                'numero' => str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                'emissao' => '2026-09-30',
                'historico' => 'TESTE',
                'documento' => $documento,
                'cliente_id' => $cliente->id,
                'vencimento' => '2026-10-30',
                'valor' => 10,
                'saldo' => 10,
            ]);
        }

        $this->assertSame(
            ['FV-83', 'FV-83/2', 'PDV-000083', 'PDV-000083/1'],
            $this->documentos('83'),
        );
        $this->assertSame(['OS-12', 'PDV-000012'], $this->documentos('12'));
        $this->assertSame(['PDV-000083', 'PDV-000083/1'], $this->documentos('PDV-000083'));
        $this->assertSame(['FV-183'], $this->documentos('183'));
        $this->assertSame(['FV-830'], $this->documentos('830'));
    }

    /**
     * @return list<string>
     */
    private function documentos(string $termo): array
    {
        $documentos = (new ContaReceberListQueryBuilder(
            searchColumn: 'documento',
            localSearch: $termo,
            applyDefaultOrder: false,
        ))->buildForList()
            ->pluck('documento')
            ->all();

        sort($documentos);

        return $documentos;
    }
}
