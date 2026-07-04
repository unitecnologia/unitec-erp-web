<?php

namespace Database\Seeders;

use App\Models\Person;
use Illuminate\Database\Seeder;

class PersonSeeder extends Seeder
{
    public function run(): void
    {
        $people = [
            ['codigo' => '4', 'nome_razao' => 'A C DE ANDRADE LTDA', 'apelido_fantasia' => 'DIVIACO', 'cpf_cnpj' => '01.704.088/0001-70', 'rg_ie' => null, 'endereco' => 'AV SANTOS DUMONT', 'numero' => '123', 'bairro' => 'CENTRO', 'tipo' => 'cliente', 'ativo' => true, 'data_nascimento' => '1980-03-12'],
            ['codigo' => '11', 'nome_razao' => 'AC DE ANDRADE JUNIOR', 'apelido_fantasia' => null, 'cpf_cnpj' => '021.824.661-59', 'rg_ie' => null, 'endereco' => 'RUA DAS FLORES', 'numero' => '45', 'tipo' => 'cliente', 'ativo' => true, 'data_nascimento' => '1992-05-10'],
            ['codigo' => '12', 'nome_razao' => 'AC DISTRIBUIDORA LTDA', 'apelido_fantasia' => 'AC DIST', 'cpf_cnpj' => '12.345.678/0001-90', 'rg_ie' => '123456789', 'endereco' => 'AV BRASIL', 'numero' => '500', 'tipo' => 'fornecedor', 'ativo' => true],
            ['codigo' => '13', 'nome_razao' => 'ADEMAR COSTA SILVA', 'apelido_fantasia' => 'ADEMAR', 'cpf_cnpj' => '456.789.123-00', 'rg_ie' => null, 'endereco' => 'RUA 7 DE SETEMBRO', 'numero' => '88', 'tipo' => 'funcionario', 'ativo' => true, 'data_nascimento' => '1988-05-18'],
            ['codigo' => '20', 'nome_razao' => 'ALFA ADMINISTRADORA DE CARTÕES', 'apelido_fantasia' => 'ALFA CARD', 'cpf_cnpj' => '98.765.432/0001-10', 'rg_ie' => '987654321', 'endereco' => 'AV PAULISTA', 'numero' => '1000', 'tipo' => 'administradora', 'ativo' => true],
            ['codigo' => '25', 'nome_razao' => 'BETA PARCEIROS COMERCIAIS LTDA', 'apelido_fantasia' => 'BETA', 'cpf_cnpj' => '11.222.333/0001-44', 'rg_ie' => null, 'endereco' => 'RUA COMERCIAL', 'numero' => '200', 'tipo' => 'parceiro', 'ativo' => true],
            ['codigo' => '30', 'nome_razao' => 'CARLOS EDUARDO MENDES', 'apelido_fantasia' => 'CARLOS', 'cpf_cnpj' => '111.222.333-44', 'rg_ie' => 'MG-1234567', 'endereco' => 'RUA MINAS GERAIS', 'numero' => '15', 'tipo' => 'cliente', 'ativo' => true, 'data_nascimento' => '1975-05-24'],
            ['codigo' => '31', 'nome_razao' => 'COMERCIAL NORTE SUL LTDA', 'apelido_fantasia' => 'NORTE SUL', 'cpf_cnpj' => '22.333.444/0001-55', 'rg_ie' => '112233445', 'endereco' => 'AV NORTE SUL', 'numero' => '300', 'tipo' => 'fornecedor', 'ativo' => true],
            ['codigo' => '32', 'nome_razao' => 'DANIELA FERREIRA SANTOS', 'apelido_fantasia' => null, 'cpf_cnpj' => '222.333.444-55', 'rg_ie' => null, 'endereco' => 'RUA DAS PALMEIRAS', 'numero' => '77', 'tipo' => 'funcionario', 'ativo' => false, 'is_ccf_spc' => true, 'data_nascimento' => '1990-05-05'],
            ['codigo' => '33', 'nome_razao' => 'DISTRIBUIDORA CENTRAL GO LTDA', 'apelido_fantasia' => 'CENTRAL GO', 'cpf_cnpj' => '33.444.555/0001-66', 'rg_ie' => '554433221', 'endereco' => 'AV GOIÁS', 'numero' => '1500', 'tipo' => 'cliente', 'ativo' => true],
            ['codigo' => '34', 'nome_razao' => 'ELITE PARCEIROS LTDA', 'apelido_fantasia' => 'ELITE', 'cpf_cnpj' => '44.555.666/0001-77', 'rg_ie' => null, 'endereco' => 'RUA ELITE', 'numero' => '10', 'tipo' => 'parceiro', 'ativo' => true],
            ['codigo' => '35', 'nome_razao' => 'FERNANDO OLIVEIRA LIMA', 'apelido_fantasia' => 'FERNANDO', 'cpf_cnpj' => '333.444.555-66', 'rg_ie' => 'GO-9876543', 'endereco' => 'RUA 20, QD 5 LT 8', 'numero' => null, 'tipo' => 'cliente', 'ativo' => false, 'is_ccf_spc' => true, 'data_nascimento' => '1984-06-03'],
        ];

        foreach ($people as $person) {
            $tipo = $person['tipo'];
            unset($person['tipo']);

            Person::query()->updateOrCreate(
                ['codigo' => $person['codigo']],
                [
                    ...$person,
                    'pessoa_tipo' => str($person['cpf_cnpj'] ?? '')->contains('/') ? Person::PESSOA_JURIDICA : Person::PESSOA_FISICA,
                    'uf' => 'GO',
                    'regime_tributario' => 'simples',
                    'tipo_contribuinte' => 'nao_contribuinte',
                    'is_cliente' => $tipo === 'cliente',
                    'is_fornecedor' => $tipo === 'fornecedor',
                    'is_funcionario' => $tipo === 'funcionario',
                    'is_administradora' => $tipo === 'administradora',
                    'is_parceiro' => $tipo === 'parceiro',
                    'is_ccf_spc' => $person['is_ccf_spc'] ?? false,
                    'data_nascimento' => $person['data_nascimento'] ?? null,
                ],
            );
        }
    }
}
