<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['codigo' => '1', 'referencia' => '1001', 'codigo_barras' => '7891000100101', 'descricao' => 'CAMERA VHD 1220 D FULL COLOR G8', 'grupo' => 'DIVERSOS', 'preco_venda' => 221.54, 'estoque' => 0],
            ['codigo' => '2', 'referencia' => '1002', 'codigo_barras' => '7891000100102', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '3', 'referencia' => '1003', 'codigo_barras' => '7891000100103', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '4', 'referencia' => '1004', 'codigo_barras' => '7891000100104', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '5', 'referencia' => '1005', 'codigo_barras' => '7891000100105', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '6', 'referencia' => '1006', 'codigo_barras' => '7891000100106', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '7', 'referencia' => '1007', 'codigo_barras' => '7891000100107', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '8', 'referencia' => '1008', 'codigo_barras' => '7891000100108', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '9', 'referencia' => '1009', 'codigo_barras' => '7891000100109', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '10', 'referencia' => '1010', 'codigo_barras' => '7891000100110', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '11', 'referencia' => '1011', 'codigo_barras' => '7891000100111', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '12', 'referencia' => '1012', 'codigo_barras' => '7891000100112', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '13', 'referencia' => '1013', 'codigo_barras' => '7891000100113', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '14', 'referencia' => '1014', 'codigo_barras' => '7891000100114', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '15', 'referencia' => '1015', 'codigo_barras' => '7891000100115', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => -1],
            ['codigo' => '16', 'referencia' => '1016', 'codigo_barras' => '7891000100116', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '17', 'referencia' => '1017', 'codigo_barras' => '7891000100117', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '18', 'referencia' => '1018', 'codigo_barras' => '7891000100118', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '19', 'referencia' => '1019', 'codigo_barras' => '7891000100119', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '20', 'referencia' => '1020', 'codigo_barras' => '7891000100120', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '21', 'referencia' => '1021', 'codigo_barras' => '7891000100121', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '22', 'referencia' => '1022', 'codigo_barras' => '7891000100122', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '23', 'referencia' => '1023', 'codigo_barras' => '7891000100123', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '24', 'referencia' => '1024', 'codigo_barras' => '7891000100124', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '25', 'referencia' => '1025', 'codigo_barras' => '7891000100125', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '26', 'referencia' => '1026', 'codigo_barras' => '7891000100126', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '27', 'referencia' => '1027', 'codigo_barras' => '7891000100127', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '28', 'referencia' => '1028', 'codigo_barras' => '7891000100128', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '29', 'referencia' => '1029', 'codigo_barras' => '7891000100129', 'descricao' => 'CONECTOR CONEX 1000 P4 FEMEA 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
            ['codigo' => '30', 'referencia' => '1030', 'codigo_barras' => '7891000100130', 'descricao' => 'CONECTOR CONEX 1000 P4 MACHO 1UN', 'grupo' => 'DIVERSOS', 'preco_venda' => 1.00, 'estoque' => 0],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['codigo' => $product['codigo']],
                [
                    ...$product,
                    'ativo' => true,
                ],
            );
        }
    }
}
