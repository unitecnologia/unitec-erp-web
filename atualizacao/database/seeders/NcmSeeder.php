<?php

namespace Database\Seeders;

use App\Models\Ncm;
use Illuminate\Database\Seeder;

class NcmSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['00000000', 'PRODUTO NAO ESPECIFICADO NA LISTA DE NCM'],
            ['01012100', 'CAVALOS REPRODUTORES DE RACA PURA'],
            ['02013000', 'CARNES BOVINAS DESOSSADAS CONGELADAS'],
            ['04012010', 'LEITE UHT'],
            ['04022110', 'LEITE EM PO'],
            ['07020000', 'TOMATES FRESCOS OU REFRIGERADOS'],
            ['08051000', 'LARANJAS FRESCAS'],
            ['09012100', 'CAFE NAO TORRADO NAO DESCAFEINADO'],
            ['10063021', 'ARROZ BRANCO PARBOILIZADO'],
            ['17019900', 'ACUCARES OUTROS'],
            ['19053100', 'BISCOITOS DOCE'],
            ['20089900', 'FRUTAS PREPARADAS OUTRAS'],
            ['21069090', 'PREPARACOES ALIMENTICIAS OUTRAS'],
            ['22021000', 'AGUAS AROMATIZADAS'],
            ['22029900', 'REFRIGERANTES OUTROS'],
            ['22030000', 'CERVEJAS DE MALTE'],
            ['27101932', 'OLEO DIESEL'],
            ['27101259', 'GASOLINA'],
            ['30049099', 'MEDICAMENTOS OUTROS'],
            ['33049990', 'PRODUTOS BELEZA OUTROS'],
            ['34011190', 'SABOES OUTROS'],
            ['39232190', 'SACOS PLASTICOS OUTROS'],
            ['40111000', 'PNEUS NOVOS AUTOMOVEIS'],
            ['48191000', 'CAIXAS PAPEL CORRUGADO'],
            ['61102000', 'SUETERES MALHA ALGODAO'],
            ['62034200', 'CALCAS MASCULINAS ALGODAO'],
            ['64039990', 'CALCADOS OUTROS'],
            ['73181500', 'PARAFUSOS ACO'],
            ['84151010', 'MAQUINAS AR CONDICIONADO'],
            ['84713012', 'NOTEBOOKS'],
            ['85171231', 'TELEFONES CELULARES'],
            ['85287200', 'MONITORES VIDEO'],
            ['85444900', 'FIOS CABOS ISOLADOS'],
            ['87032100', 'AUTOMOVEIS ATE 1000CM3'],
            ['94036000', 'MOVEIS MADEIRA OUTROS'],
            ['95030031', 'BRINQUEDOS PLASTICO'],
        ];

        foreach ($items as [$codigo, $descricao]) {
            Ncm::query()->updateOrCreate(
                ['codigo' => $codigo],
                ['descricao' => $descricao, 'ativo' => true],
            );
        }
    }
}
