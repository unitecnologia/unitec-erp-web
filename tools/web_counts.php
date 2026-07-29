<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$map = [
  'empresas' => 'Empresa',
  'products' => 'Product',
  'people' => 'Person',
  'grupos' => 'Grupo',
  'marcas' => 'Marca',
  'unidades' => 'Unidade',
  'vendedores' => 'Vendedor',
  'users' => 'User',
  'formas_pagamento' => 'FormaPagamento',
  'caixa_contas' => 'CaixaConta',
  'contas_receber' => 'ContaReceber',
  'contas_pagar' => 'ContaPagar',
  'compras' => 'Compra',
  'compra_itens' => 'CompraItem',
  'vendas' => 'Venda',
  'venda_itens' => 'VendaItem',
  'orcamentos' => 'Orcamento',
  'orcamento_itens' => 'OrcamentoItem',
  'nfes' => 'Nfe',
  'nfe_itens' => 'NfeItem',
  'terminais' => 'Terminal',
  'transportadoras' => 'Transportadora',
  'veiculos' => 'Veiculo',
  'contadores' => 'Contador',
  'price_tables' => 'PriceTable',
  'tabelas_prazo' => 'TabelaPrazo',
  'cartao_bandeiras' => 'CartaoBandeira',
  'pdv_vendas' => 'PdvVenda',
  'estoques' => 'Estoque',
  'vendas_parametros' => 'VendasParametro',
];

$prefix = DB::getTablePrefix();
foreach ($map as $table => $label) {
  $full = $prefix.$table;
  if (!Schema::hasTable($table)) {
    echo "$label\tMISSING\n";
    continue;
  }
  $n = DB::table($table)->count();
  echo "$label\t$n\t$full\n";
}
