<?php

namespace Tests\Unit;

use App\Support\Erp\Queries\CaixaListQueryBuilder;
use ReflectionClass;
use Tests\TestCase;

class CaixaLancamentoMeioPagamentoFiltroTest extends TestCase
{
    public function test_campo_meio_pagamento_esta_entre_os_localizaveis(): void
    {
        $builder = new CaixaListQueryBuilder(
            searchColumn: 'meio_pagamento',
            localSearch: '',
        );

        $ref = new ReflectionClass($builder);
        $method = $ref->getMethod('localSearchColumns');
        $method->setAccessible(true);

        $cols = $method->invoke($builder);

        $this->assertContains('meio_pagamento', $cols);
        $this->assertContains('documento', $cols);
        $this->assertContains('historico', $cols);
    }
}
