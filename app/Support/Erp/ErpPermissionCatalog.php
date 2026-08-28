<?php

namespace App\Support\Erp;

class ErpPermissionCatalog
{
  /**
   * @return array<string, array{label: string, group: string, menu?: string, actions: array<string, string>}>
   */
  public static function modules(): array
  {
    return [
      'acesso.usuarios' => [
        'label' => 'Usuários',
        'group' => 'Acesso',
        'actions' => [
          'access' => 'Acessar',
          'create' => 'Incluir (F2)',
          'update' => 'Alterar (F3)',
          'delete' => 'Excluir',
        ],
      ],
      'acesso.permissoes' => [
        'label' => 'Permissões',
        'group' => 'Acesso',
        'actions' => [
          'manage' => 'Gerenciar permissões',
        ],
      ],
      'pessoas' => [
        'label' => 'Pessoas / Contatos',
        'group' => 'Pessoas',
        'actions' => static::crudPrintActions(),
      ],
      'entregadores' => [
        'label' => 'Entregadores',
        'group' => 'Pessoas',
        'actions' => static::crudPrintActions(),
      ],
      'contadores' => [
        'label' => 'Contadores',
        'group' => 'Pessoas',
        'actions' => static::crudPrintActions(),
      ],
      'aniversariantes' => [
        'label' => 'Aniversariantes',
        'group' => 'Pessoas',
        'actions' => [
          'access' => 'Acessar',
          'print' => 'Imprimir (F4)',
        ],
      ],
      'produtos' => [
        'label' => 'Produtos',
        'group' => 'Estoque',
        'actions' => [
          ...static::crudPrintActions(),
          'cardex' => 'Histórico / Cardex (F7)',
          'duplicate' => 'Duplicar (F8)',
        ],
      ],
      'grupos' => [
        'label' => 'Grupos',
        'group' => 'Estoque',
        'actions' => static::crudPrintActions(),
      ],
      'unidades' => [
        'label' => 'Unidades',
        'group' => 'Estoque',
        'actions' => static::crudPrintActions(),
      ],
      'marcas' => [
        'label' => 'Marcas',
        'group' => 'Estoque',
        'actions' => static::crudPrintActions(),
      ],
      'etiquetas' => [
        'label' => 'Impressão de Etiquetas',
        'group' => 'Estoque',
        'actions' => [
          'access' => 'Acessar',
          'print' => 'Imprimir',
        ],
      ],
      'ajusta_preco' => [
        'label' => 'Ajuste de Preço em Lote',
        'group' => 'Estoque',
        'actions' => [
          'access' => 'Acessar',
          'update' => 'Alterar',
        ],
      ],
      'ajuste_estoque' => [
        'label' => 'Ajusta Estoque',
        'group' => 'Estoque',
        'actions' => [
          'access' => 'Acessar',
          'create' => 'Incluir',
          'update' => 'Alterar',
        ],
      ],
      'compras' => [
        'label' => 'Compras',
        'group' => 'Compras',
        'actions' => [
          ...static::crudPrintActions(),
          'import_xml' => 'Entrada XML (F2)',
          'close_month' => 'Fechar Mês (F9)',
        ],
      ],
      'devolucoes_compra' => [
        'label' => 'Devolução de Compra',
        'group' => 'Compras',
        'actions' => [
          ...static::crudPrintActions(),
          'emit_nfe' => 'Emitir NF-e (F7)',
        ],
      ],
      'orcamentos' => [
        'label' => 'Orçamentos',
        'group' => 'Vendas',
        'actions' => static::crudPrintActions(),
      ],
      'promocoes' => [
        'label' => 'Promoções',
        'group' => 'Vendas',
        'actions' => static::crudPrintActions(),
      ],
      'devolucoes_venda' => [
        'label' => 'Devolução de Venda',
        'group' => 'Vendas',
        'actions' => static::crudPrintActions(),
      ],
      'ordens_servico' => [
        'label' => 'Ordem de Serviço',
        'group' => 'OS',
        'actions' => static::crudPrintActions(),
      ],
      'pdv' => [
        'label' => 'PDV',
        'group' => 'Vendas',
        'actions' => [
          'access' => 'Acessar',
          'discount' => 'Dar desconto',
          'delete_item' => 'Excluir item',
          'print' => 'Imprimir cupom',
        ],
      ],
      'vendas' => [
        'label' => 'Vendas',
        'group' => 'Vendas',
        'actions' => [
          ...static::crudPrintActions(),
          'cancel' => 'Cancelar venda (F4)',
          'reprint_cupom' => 'Reimprimir cupom PDV',
        ],
      ],
      'formas_pagamento' => [
        'label' => 'Formas de Pagamento',
        'group' => 'Financeiro',
        'actions' => static::crudPrintActions(),
      ],
      'contas_caixa' => [
        'label' => 'Contas Caixa',
        'group' => 'Financeiro',
        'actions' => static::crudPrintActions(),
      ],
      'planos_contas' => [
        'label' => 'Plano de Contas',
        'group' => 'Financeiro',
        'actions' => static::crudPrintActions(),
      ],
      'contas_pagar' => [
        'label' => 'Contas a Pagar',
        'group' => 'Financeiro',
        'actions' => [
          ...static::crudPrintActions(),
          'baixa' => 'Baixar título',
        ],
      ],
      'contas_receber' => [
        'label' => 'Contas a Receber',
        'group' => 'Financeiro',
        'actions' => [
          ...static::crudPrintActions(),
          'baixa' => 'Baixar título',
        ],
      ],
      'caixa' => [
        'label' => 'Livro Caixa',
        'group' => 'Financeiro',
        'actions' => [
          'access' => 'Acessar',
          'create' => 'Lançar',
          'update' => 'Alterar',
          'delete' => 'Excluir',
          'print' => 'Imprimir (F4)',
        ],
      ],
      'recibos' => [
        'label' => 'Impressão de Recibos',
        'group' => 'Financeiro',
        'actions' => [
          'access' => 'Acessar',
          'create' => 'Incluir (F2)',
          'update' => 'Alterar (F3)',
          'delete' => 'Excluir',
          'print' => 'Imprimir (F6)',
        ],
      ],
      'boletos' => [
        'label' => 'Boletos',
        'group' => 'Financeiro',
        'actions' => [
          'access' => 'Acessar',
          'create' => 'Gerar / Importar',
          'update' => 'Alterar configuração',
          'delete' => 'Excluir',
          'print' => 'Imprimir',
        ],
      ],
      'nfce' => [
        'label' => 'NFC-e',
        'group' => 'Fiscal',
        'actions' => [
          'access' => 'Acessar',
          'emit' => 'Emitir',
          'cancel' => 'Cancelar',
          'print' => 'Imprimir',
        ],
      ],
      'cfops' => [
        'label' => 'CFOP',
        'group' => 'Fiscal',
        'actions' => static::crudPrintActions(),
      ],
      'tabela_icms' => [
        'label' => 'Tabela ICMS',
        'group' => 'Fiscal',
        'actions' => [
          'access' => 'Acessar',
          'update' => 'Alterar alíquotas',
        ],
      ],
      'nfe' => [
        'label' => 'NF-e',
        'group' => 'Fiscal',
        'actions' => [
          'access' => 'Acessar',
          'emit' => 'Emitir',
          'cancel' => 'Cancelar (F4)',
          'print' => 'Imprimir DANFE (F7)',
        ],
      ],
      'empresa' => [
        'label' => 'Empresa',
        'group' => 'Configurações',
        'actions' => [
          'access' => 'Acessar',
          'update' => 'Alterar',
        ],
      ],
      'terminais' => [
        'label' => 'Terminais',
        'group' => 'Configurações',
        'actions' => static::crudPrintActions(),
      ],
      'config_fiscais' => [
        'label' => 'Config. Fiscais',
        'group' => 'Configurações',
        'actions' => [
          'access' => 'Acessar',
          'update' => 'Alterar',
        ],
      ],
      'balanca' => [
        'label' => 'Balança',
        'group' => 'Configurações',
        'actions' => [
          'access' => 'Acessar',
          'generate' => 'Gerar arquivo',
          'update' => 'Alterar configuração',
        ],
      ],
      'migra_firebird' => [
        'label' => 'Migra dados FB',
        'group' => 'Configurações',
        'actions' => [
          'access' => 'Acessar',
        ],
      ],
      'comandos' => [
        'label' => 'Comandos do Sistema',
        'group' => 'Configurações',
        'actions' => [
          'access' => 'Acessar',
          'warm' => 'Aquecer sistema',
          'import_data' => 'Importar dados',
        ],
      ],
      'backup' => [
        'label' => 'Backup',
        'group' => 'Configurações',
        'actions' => [
          'access' => 'Acessar',
          'create' => 'Gerar backup',
          'update' => 'Alterar configuração',
          'restore' => 'Restaurar backup',
        ],
      ],
      'mercado_livre' => [
        'label' => 'Mercado Livre',
        'group' => 'Integrações',
        'actions' => [
          'access' => 'Acessar',
          'config' => 'Conectar conta / config',
        ],
      ],
      'logistica' => [
        'label' => 'Logística',
        'group' => 'Logística',
        'actions' => [
          'access' => 'Acessar',
          'update' => 'Alterar status / operar',
          'print' => 'Imprimir',
        ],
      ],
      'transportadoras' => [
        'label' => 'Motorista / Transportador',
        'group' => 'Logística',
        'actions' => static::crudPrintActions(),
      ],
      'veiculos' => [
        'label' => 'Veículos',
        'group' => 'Logística',
        'actions' => static::crudPrintActions(),
      ],
      'rh.dashboard' => [
        'label' => 'Painel RH',
        'group' => 'RH',
        'actions' => [
          'access' => 'Acessar',
        ],
      ],
      'rh.funcionarios' => [
        'label' => 'Funcionários',
        'group' => 'RH',
        'actions' => static::crudPrintActions(),
      ],
      'rh.cargos' => [
        'label' => 'Cargos',
        'group' => 'RH',
        'actions' => static::crudPrintActions(),
      ],
      'rh.departamentos' => [
        'label' => 'Departamentos',
        'group' => 'RH',
        'actions' => static::crudPrintActions(),
      ],
      'tomadores_servico' => [
        'label' => 'Tomador de Serviço',
        'group' => 'Logística',
        'actions' => static::crudPrintActions(),
      ],
      'logistica_destinatarios' => [
        'label' => 'Destinatário',
        'group' => 'Logística',
        'actions' => static::crudPrintActions(),
      ],
      'logistica_remetentes' => [
        'label' => 'Remetente',
        'group' => 'Logística',
        'actions' => static::crudPrintActions(),
      ],
    ];
  }

  /**
   * @return array<string, string>
   */
  protected static function crudPrintActions(): array
  {
    return [
      'access' => 'Acessar',
      'create' => 'Incluir (F2)',
      'update' => 'Alterar (F3)',
      'delete' => 'Excluir',
      'print' => 'Imprimir (F4)',
    ];
  }

  /**
   * @return list<string>
   */
  public static function allKeys(): array
  {
    $keys = [];

    foreach (static::modules() as $module => $meta) {
      foreach (array_keys($meta['actions']) as $action) {
        $keys[] = static::key($module, $action);
      }
    }

    sort($keys);

    return $keys;
  }

  public static function key(string $module, string $action): string
  {
    return $module . '.' . $action;
  }

  /**
   * @return array<string, array{label: string, modules: array<string, array{label: string, actions: array<string, string>}>}>
   */
  public static function groupedForUi(): array
  {
    $groups = [];

    foreach (static::modules() as $module => $meta) {
      $group = $meta['group'];
      $groups[$group]['label'] = $group;
      $groups[$group]['modules'][$module] = [
        'label' => $meta['label'],
        'actions' => $meta['actions'],
      ];
    }

    return $groups;
  }

  public static function labelForKey(string $key): string
  {
    foreach (static::modules() as $module => $meta) {
      foreach ($meta['actions'] as $action => $label) {
        if (static::key($module, $action) === $key) {
          return $meta['label'] . ' — ' . $label;
        }
      }
    }

    return $key;
  }

  /**
   * @return list<string>
   */
  public static function accessKeysForMenu(): array
  {
    $keys = [];

    foreach (static::modules() as $module => $meta) {
      if (isset($meta['actions']['access'])) {
        $keys[] = static::key($module, 'access');
      }

      if (isset($meta['actions']['manage'])) {
        $keys[] = static::key($module, 'manage');
      }
    }

    return $keys;
  }
}
