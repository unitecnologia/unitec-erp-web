<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Support\Erp\PersonListRowFormatter;
use PHPUnit\Framework\TestCase;

class PersonListRowFormatterTest extends TestCase
{
    public function test_endereco_da_grade_fica_sem_cidade_e_uf(): void
    {
        $cells = (new PersonListRowFormatter)->format(new Person([
            'codigo' => '1',
            'nome_razao' => 'CLIENTE',
            'endereco' => 'RUA A',
            'numero' => '10',
            'bairro' => 'CENTRO',
            'cidade_nome' => 'Joinville',
            'uf' => 'sc',
            'fone1' => '47988187826',
        ]));

        $this->assertSame('RUA A, nº 10', $cells['endereco_lista']);
        $this->assertSame('CENTRO', $cells['bairro_lista']);
        $this->assertSame('JOINVILLE, SC', $cells['cidade_lista']);
        $this->assertSame('(47)98818-7826', $cells['whatsapp_lista']);
    }

    public function test_cidade_e_whatsapp_vazios_viram_traco(): void
    {
        $cells = (new PersonListRowFormatter)->format(new Person([
            'codigo' => '2',
            'nome_razao' => 'SEM ENDERECO',
        ]));

        $this->assertSame('—', $cells['endereco_lista']);
        $this->assertSame('—', $cells['bairro_lista']);
        $this->assertSame('—', $cells['cidade_lista']);
        $this->assertSame('—', $cells['whatsapp_lista']);
    }

    public function test_whatsapp_ja_mascarado_permanece_no_formato(): void
    {
        $cells = (new PersonListRowFormatter)->format(new Person([
            'codigo' => '3',
            'nome_razao' => 'CLIENTE',
            'fone1' => '(47)3333-4444',
        ]));

        $this->assertSame('(47)3333-4444', $cells['whatsapp_lista']);
    }
}
