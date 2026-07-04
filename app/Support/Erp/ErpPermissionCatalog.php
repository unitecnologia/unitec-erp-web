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
      'vendedores' => [
        'label' => 'Vendedores',
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
        'label' => 'Ajusta Preço',
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
          'import_xml' => 'Ler XML (F6)',
          'close_month' => 'Fechar Mês (F9)',
        ],
      ],
      'orcamentos' => [
        'label' => 'Orçamentos',
        'group' => 'Vendas',
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
      'forca_vendas' => [
        'label' => 'Força de Venda',
        'group' => 'Força de Venda',
        'actions' => [
          'access' => 'Acessar',
          'config' => 'Pareamento / QR Code',
          'delete' => 'Excluir / Revogar',
        ],
      ],
      'vendas_internas' => [
        'label' => 'Vendas Internas',
        'group' => 'Vendas Internas',
        'actions' => [
          'access' => 'Acessar',
          'config' => 'Autorizar aparelhos',
          'delete' => 'Excluir / Revogar',
        ],
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
