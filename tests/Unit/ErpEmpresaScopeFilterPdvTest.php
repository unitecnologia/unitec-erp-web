<?php

namespace Tests\Unit;

use App\Models\PdvVenda;
use App\Support\Erp\ErpEmpresaScopeFilter;
use App\Support\Erp\ErpSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ForbidDestructiveDatabaseReset;
use Tests\TestCase;

class ErpEmpresaScopeFilterPdvTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ForbidDestructiveDatabaseReset::assertSqliteMemoryOrFail();

        Schema::dropIfExists('pdv_vendas');
        Schema::dropIfExists('pdv_caixa_sessoes');

        Schema::create('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->string('situacao', 1)->default('F');
            $table->timestamps();
        });

        ErpSchema::flush();
        ErpSchema::assumeMigrated(false);
    }

    protected function tearDown(): void
    {
        ErpSchema::assumeMigrated(false);
        ErpSchema::flush();

        parent::tearDown();
    }

    public function test_filtra_pdv_pela_sessao_quando_venda_nao_tem_empresa_id(): void
    {
        ErpSchema::assumeMigrated(true);

        $query = PdvVenda::query();
        ErpEmpresaScopeFilter::applyPdvSessao($query, 7);

        $sql = $query->toSql();

        $this->assertStringNotContainsString('pdv_vendas"."empresa_id', $sql);
        $this->assertStringNotContainsString('pdv_vendas`.`empresa_id', $sql);
        $this->assertStringContainsString('pdv_caixa_sessoes', $sql);
        $this->assertSame([7], $query->getBindings());
    }
}
