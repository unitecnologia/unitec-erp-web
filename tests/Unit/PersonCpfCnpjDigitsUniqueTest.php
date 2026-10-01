<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Rules\DocumentoBrasileiroValido;
use App\Rules\PersonDocumentoUnico;
use App\Support\Erp\PersonCpfCnpjUnicidade;
use App\Support\Erp\PersonDocumentoDuplicadoException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class PersonCpfCnpjDigitsUniqueTest extends TestCase
{
    use MigratesSqliteMemory;

    private const CPF = '52998224725';

    private const CPF_MASK = '529.982.247-25';

    private const CNPJ = '11222333000181';

    private const CNPJ_MASK = '11.222.333/0001-81';

    public function test_coluna_gerada_e_indice_existem(): void
    {
        $this->assertTrue(Schema::hasColumn('people', 'cpf_cnpj_digits'));
        $this->assertTrue($this->indiceUnicoExiste());
    }

    public function test_duplicidade_aborta_preserva_dados_e_cria_indice_apos_correcao(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropUnique('people_cpf_cnpj_digits_unique');
        });

        $a = $this->criar(['cpf_cnpj' => self::CPF]);
        $b = $this->criar(['cpf_cnpj' => self::CPF_MASK]);
        $total = Person::query()->count();

        $migration = require database_path('migrations/2026_09_25_120000_add_people_cpf_cnpj_digits_unique.php');

        try {
            $migration->up();
            $this->fail('A migration deveria abortar com documentos duplicados.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('duplicad', mb_strtolower($e->getMessage()));
            $this->assertStringContainsString('people_cpf_cnpj_digits_unique', $e->getMessage());
        }

        $this->assertSame($total, Person::query()->count());
        $this->assertNotNull(Person::query()->find($a->id));
        $this->assertNotNull(Person::query()->find($b->id));
        $this->assertSame(self::CPF, Person::query()->find($a->id)->cpf_cnpj_digits);
        $this->assertSame(self::CPF, Person::query()->find($b->id)->cpf_cnpj_digits);
        $this->assertFalse($this->indiceUnicoExiste());

        $b->forceFill([
            'cpf_cnpj' => self::CNPJ,
            'pessoa_tipo' => Person::PESSOA_JURIDICA,
        ])->save();

        $migration->up();

        $this->assertTrue($this->indiceUnicoExiste());
        $this->assertSame($total, Person::query()->count());
        $this->assertNotNull(Person::query()->find($a->id));
        $this->assertNotNull(Person::query()->find($b->id));
    }

    public function test_cpf_mascarado_vs_digitos_bloqueia(): void
    {
        $this->criar(['cpf_cnpj' => self::CPF_MASK]);
        $person = Person::query()->where('cpf_cnpj_digits', self::CPF)->first();
        $this->assertNotNull($person);
        $this->assertSame(self::CPF, $person->cpf_cnpj_digits);

        $this->expectException(PersonDocumentoDuplicadoException::class);
        app(PersonCpfCnpjUnicidade::class)->assertDisponivel(self::CPF);
    }

    public function test_cnpj_mascarado_vs_digitos_bloqueia(): void
    {
        $this->criar(['cpf_cnpj' => self::CNPJ, 'pessoa_tipo' => Person::PESSOA_JURIDICA]);
        $msg = app(PersonCpfCnpjUnicidade::class)->mensagemDuplicado(self::CNPJ_MASK);
        $this->assertNotNull($msg);
        $this->assertStringContainsString('CNPJ', $msg);
    }

    public function test_varios_null_permitidos(): void
    {
        $this->criar(['cpf_cnpj' => null]);
        $this->criar(['cpf_cnpj' => null]);
        $this->criar(['cpf_cnpj' => '']);
        $this->assertSame(3, Person::query()->whereNull('cpf_cnpj_digits')->count());
    }

    public function test_vazio_vira_null_no_model(): void
    {
        $p = $this->criar(['cpf_cnpj' => '   ']);
        $this->assertNull($p->fresh()->cpf_cnpj);
        $this->assertNull($p->fresh()->cpf_cnpj_digits);
    }

    public function test_edicao_proprio_permite(): void
    {
        $p = $this->criar(['cpf_cnpj' => self::CPF_MASK]);
        $this->assertNull(app(PersonCpfCnpjUnicidade::class)->mensagemDuplicado(self::CPF, $p->id));
    }

    public function test_insert_raw_cpf_cnpj_ainda_bloqueia_duplicado(): void
    {
        $this->criar(['cpf_cnpj' => self::CPF]);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('people')->insert([
            'codigo' => 'RAW'.random_int(1000, 9999),
            'nome_razao' => 'RAW DUP',
            'cpf_cnpj' => self::CPF_MASK,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'ativo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_consulta_unicidade_usa_cpf_cnpj_digits(): void
    {
        $this->criar(['cpf_cnpj' => self::CPF_MASK]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(PersonCpfCnpjUnicidade::class)->encontrar(self::CPF);
        $log = DB::getQueryLog();
        $this->assertNotEmpty($log);
        $this->assertStringContainsString('cpf_cnpj_digits', $log[0]['query']);
        $this->assertStringNotContainsString('REPLACE(', $log[0]['query']);
    }

    public function test_rule_e_dv_continuam(): void
    {
        $this->criar(['cpf_cnpj' => self::CPF]);
        $fail = Validator::make(
            ['cpf_cnpj' => self::CPF_MASK],
            ['cpf_cnpj' => [new DocumentoBrasileiroValido(Person::PESSOA_FISICA), new PersonDocumentoUnico]],
        );
        $this->assertTrue($fail->fails());
    }

    public function test_expressao_migration_portatil(): void
    {
        $migration = require database_path('migrations/2026_09_25_120000_add_people_cpf_cnpj_digits_unique.php');
        $expr = $migration::digitsExpression();
        $this->assertStringContainsString('CASE', $expr);
        $this->assertStringContainsString('REPLACE(', $expr);
        $this->assertStringContainsString('NOT IN (11, 14)', $expr);
        $this->assertStringNotContainsString('REGEXP_REPLACE', $expr);
    }

    public function test_zeros_repetidos_viram_null_e_nao_bloqueiam_o_indice(): void
    {
        $a = $this->criar(['cpf_cnpj' => '000000000000']);
        $b = $this->criar(['cpf_cnpj' => '000.000.000-000']);

        $this->assertTrue($this->indiceUnicoExiste());
        $this->assertNull($a->fresh()->cpf_cnpj_digits);
        $this->assertNull($b->fresh()->cpf_cnpj_digits);
        $this->assertSame('000000000000', $a->fresh()->cpf_cnpj);
        $this->assertSame('000.000.000-000', $b->fresh()->cpf_cnpj);
    }

    private function indiceUnicoExiste(): bool
    {
        foreach (Schema::getIndexes('people') as $index) {
            if (($index['name'] ?? '') === 'people_cpf_cnpj_digits_unique') {
                return true;
            }

            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === ['cpf_cnpj_digits']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function criar(array $extra = []): Person
    {
        return Person::query()->create(array_merge([
            'codigo' => 'T'.random_int(100000, 999999),
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'TESTE DIGITS '.uniqid(),
            'is_cliente' => true,
            'ativo' => true,
        ], $extra));
    }
}
