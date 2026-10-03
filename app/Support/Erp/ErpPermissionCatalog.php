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
      'dashboard' => [
        'label' => 'Dashboard',
        'group' => 'Acesso',
        'actions' => [
          'access' => 'Acessar',
        ],
      ],
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
        'actions' => [
          ...static::crudPrintActions(),
          'credito_gerar' => 'Gerar crédito do cliente',
          'credito_estornar' => 'Estornar crédito do cliente',
        ],
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
      'comissoes' => [
        'label' => 'Comissões',
        'group' => 'Financeiro',
        'actions' => [
          'access' => 'Acessar',
          'create' => 'Calcular',
          'update' => 'Fechar / Cancelar',
          'print' => 'Imprimir',
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
      'nfse' => [
        'label' => 'NFS-e',
        'group' => 'Fiscal',
        'actions' => [
          'access' => 'Acessar',
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
      'cargas' => [
        'label' => 'Carga / Romaneio',
        'group' => 'Logística',
        'actions' => static::crudPrintActions(),
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

  /**
   * Árvore da aba Permissões, na ordem do menu.
   * Só entram ações que o ERP consulta hoje.
   *
   * @return list<array<string, mixed>>
   */
  public static function editorTree(?\App\Models\Empresa $empresa = null): array
  {
    $groups = [];

    foreach (static::editorDefinition() as $group) {
      $filtered = static::filterEditorGroup($group, $empresa);

      if ($filtered !== null) {
        $groups[] = $filtered;
      }
    }

    return $groups;
  }

  /**
   * @param  array<string, mixed>  $group
   * @return array<string, mixed>|null
   */
  protected static function filterEditorGroup(array $group, ?\App\Models\Empresa $empresa): ?array
  {
    $items = [];

    foreach ($group['items'] ?? [] as $item) {
      $module = (string) ($item['module'] ?? '');

      if ($module !== '' && ! EmpresaModulos::enabled($empresa, $module)) {
        continue;
      }

      unset($item['module']);
      $items[] = $item;
    }

    $children = [];

    foreach ($group['children'] ?? [] as $child) {
      $filtered = static::filterEditorGroup($child, $empresa);

      if ($filtered !== null) {
        $children[] = $filtered;
      }
    }

    if ($items === [] && $children === []) {
      return null;
    }

    $group['items'] = $items;

    if ($children === []) {
      unset($group['children']);
    } else {
      $group['children'] = $children;
    }

    return $group;
  }

  /**
   * @return list<array<string, mixed>>
   */
  protected static function editorDefinition(): array
  {
    return [
      [
        'id' => 'acesso',
        'label' => 'Acesso',
        'items' => [
          [
            'label' => 'Dashboard',
            'module' => 'dashboard',
            'access' => 'dashboard.access',
          ],
          [
            'label' => 'Permissões / Usuários',
            'module' => 'acesso.permissoes',
            'actions' => [
              ['label' => 'Gerenciar', 'key' => 'acesso.permissoes.manage'],
              ['label' => 'Incluir', 'key' => 'acesso.usuarios.create'],
              ['label' => 'Alterar', 'key' => 'acesso.usuarios.update'],
              ['label' => 'Excluir', 'key' => 'acesso.usuarios.delete'],
            ],
          ],
        ],
      ],
      [
        'id' => 'pessoas',
        'label' => 'Pessoas',
        'items' => [
          [
            'label' => 'Contatos',
            'module' => 'pessoas',
            'access' => 'pessoas.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'pessoas.create'],
              ['label' => 'Alterar', 'key' => 'pessoas.update'],
              ['label' => 'Excluir', 'key' => 'pessoas.delete'],
              ['label' => 'Imprimir', 'key' => 'pessoas.print'],
              ['label' => 'Gerar crédito', 'key' => 'pessoas.credito_gerar'],
              ['label' => 'Estornar crédito', 'key' => 'pessoas.credito_estornar'],
            ],
          ],
          [
            'label' => 'Lista Aniversariantes',
            'module' => 'aniversariantes',
            'access' => 'aniversariantes.access',
          ],
        ],
        'children' => [
          [
            'id' => 'rh',
            'label' => 'RH',
            'items' => [
              ['label' => 'Painel RH', 'module' => 'rh.dashboard', 'access' => 'rh.dashboard.access'],
              ['label' => 'Funcionários', 'module' => 'rh.funcionarios', 'access' => 'rh.funcionarios.access'],
              ['label' => 'Contador', 'module' => 'contadores', 'access' => 'contadores.access'],
              ['label' => 'Cargos', 'module' => 'rh.cargos', 'access' => 'rh.cargos.access'],
              ['label' => 'Departamentos', 'module' => 'rh.departamentos', 'access' => 'rh.departamentos.access'],
            ],
          ],
        ],
      ],
      [
        'id' => 'estoque',
        'label' => 'Estoque',
        'items' => [
          [
            'label' => 'Produtos',
            'module' => 'produtos',
            'access' => 'produtos.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'produtos.create'],
              ['label' => 'Alterar', 'key' => 'produtos.update'],
              ['label' => 'Excluir', 'key' => 'produtos.delete'],
              ['label' => 'Imprimir', 'key' => 'produtos.print'],
              ['label' => 'Cardex', 'key' => 'produtos.cardex'],
              ['label' => 'Duplicar', 'key' => 'produtos.duplicate'],
            ],
          ],
          ['label' => 'Grupo', 'module' => 'grupos', 'access' => 'grupos.access'],
          ['label' => 'Unidades', 'module' => 'unidades', 'access' => 'unidades.access'],
          ['label' => 'Marcas', 'module' => 'marcas', 'access' => 'marcas.access'],
          ['label' => 'Impressão Etiquetas', 'module' => 'etiquetas', 'access' => 'etiquetas.access'],
          [
            'label' => 'Ajuste de Preço em Lote',
            'module' => 'ajusta_preco',
            'access' => 'ajusta_preco.access',
            'actions' => [
              ['label' => 'Alterar', 'key' => 'ajusta_preco.update'],
            ],
          ],
          [
            'label' => 'Ajusta estoque, ajuste por grupo, zera negativo e outras saídas',
            'module' => 'ajuste_estoque',
            'access' => 'ajuste_estoque.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'ajuste_estoque.create'],
            ],
          ],
        ],
      ],
      [
        'id' => 'compras',
        'label' => 'Compras',
        'items' => [
          [
            'label' => 'Lista Compras, análise e notas de fornecedores',
            'module' => 'compras',
            'access' => 'compras.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'compras.create'],
              ['label' => 'Entrada XML', 'key' => 'compras.import_xml'],
              ['label' => 'Fechar mês', 'key' => 'compras.close_month'],
              ['label' => 'Imprimir', 'key' => 'compras.print'],
            ],
          ],
          [
            'label' => 'Devolução de Compra',
            'module' => 'devolucoes_compra',
            'access' => 'devolucoes_compra.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'devolucoes_compra.create'],
              ['label' => 'Alterar', 'key' => 'devolucoes_compra.update'],
            ],
          ],
        ],
      ],
      [
        'id' => 'vendas',
        'label' => 'Vendas',
        'items' => [
          ['label' => 'Orçamento', 'module' => 'orcamentos', 'access' => 'orcamentos.access'],
          [
            'label' => 'Promoções',
            'module' => 'promocoes',
            'access' => 'promocoes.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'promocoes.create'],
              ['label' => 'Alterar', 'key' => 'promocoes.update'],
              ['label' => 'Excluir', 'key' => 'promocoes.delete'],
            ],
          ],
          ['label' => 'PDV', 'module' => 'pdv', 'access' => 'pdv.access'],
          [
            'label' => 'Lista de Vendas, Tela de Venda, Monitor e Log',
            'module' => 'vendas',
            'access' => 'vendas.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'vendas.create'],
              ['label' => 'Alterar', 'key' => 'vendas.update'],
              ['label' => 'Cancelar', 'key' => 'vendas.cancel'],
              ['label' => 'Reimprimir cupom', 'key' => 'vendas.reprint_cupom'],
              ['label' => 'Imprimir', 'key' => 'vendas.print'],
            ],
          ],
          [
            'label' => 'Devolução de Venda',
            'module' => 'devolucoes_venda',
            'access' => 'devolucoes_venda.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'devolucoes_venda.create'],
              ['label' => 'Alterar', 'key' => 'devolucoes_venda.update'],
            ],
          ],
        ],
      ],
      [
        'id' => 'logistica',
        'label' => 'Logística',
        'items' => [
          [
            'label' => 'Controle de Expedição',
            'module' => 'logistica',
            'access' => 'logistica.access',
            'actions' => [
              ['label' => 'Alterar', 'key' => 'logistica.update'],
              ['label' => 'Imprimir', 'key' => 'logistica.print'],
            ],
          ],
          [
            'label' => 'Carga / Romaneio e Entregas',
            'module' => 'cargas',
            'access' => 'cargas.access',
            'actions' => [
              ['label' => 'Imprimir', 'key' => 'cargas.print'],
            ],
          ],
          ['label' => 'Motorista / Transportador', 'module' => 'transportadoras', 'access' => 'transportadoras.access'],
          ['label' => 'Veículos', 'module' => 'veiculos', 'access' => 'veiculos.access'],
          ['label' => 'Tomador de Serviço', 'module' => 'tomadores_servico', 'access' => 'tomadores_servico.access'],
          ['label' => 'Destinatário', 'module' => 'logistica_destinatarios', 'access' => 'logistica_destinatarios.access'],
          ['label' => 'Remetente', 'module' => 'logistica_remetentes', 'access' => 'logistica_remetentes.access'],
        ],
      ],
      [
        'id' => 'financeiro',
        'label' => 'Financeiro',
        'items' => [
          ['label' => 'Forma de Pagamentos', 'module' => 'formas_pagamento', 'access' => 'formas_pagamento.access'],
          [
            'label' => 'Contas',
            'module' => 'contas_caixa',
            'access' => 'contas_caixa.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'contas_caixa.create'],
              ['label' => 'Alterar', 'key' => 'contas_caixa.update'],
              ['label' => 'Imprimir', 'key' => 'contas_caixa.print'],
            ],
          ],
          [
            'label' => 'Plano de Contas',
            'module' => 'planos_contas',
            'access' => 'planos_contas.access',
            'actions' => [
              ['label' => 'Imprimir', 'key' => 'planos_contas.print'],
            ],
          ],
          [
            'label' => 'Contas a Pagar',
            'module' => 'contas_pagar',
            'access' => 'contas_pagar.access',
            'actions' => [
              ['label' => 'Alterar', 'key' => 'contas_pagar.update'],
              ['label' => 'Imprimir', 'key' => 'contas_pagar.print'],
              ['label' => 'Baixar título', 'key' => 'contas_pagar.baixa'],
            ],
          ],
          [
            'label' => 'Contas a Receber',
            'module' => 'contas_receber',
            'access' => 'contas_receber.access',
            'actions' => [
              ['label' => 'Alterar', 'key' => 'contas_receber.update'],
              ['label' => 'Imprimir', 'key' => 'contas_receber.print'],
              ['label' => 'Baixar título', 'key' => 'contas_receber.baixa'],
            ],
          ],
          [
            'label' => 'Comissões',
            'module' => 'comissoes',
            'access' => 'comissoes.access',
            'actions' => [
              ['label' => 'Calcular', 'key' => 'comissoes.create'],
              ['label' => 'Fechar / Cancelar', 'key' => 'comissoes.update'],
            ],
          ],
          [
            'label' => 'Livro Caixa',
            'module' => 'caixa',
            'access' => 'caixa.access',
            'actions' => [
              ['label' => 'Lançar', 'key' => 'caixa.create'],
              ['label' => 'Alterar', 'key' => 'caixa.update'],
              ['label' => 'Excluir', 'key' => 'caixa.delete'],
              ['label' => 'Imprimir', 'key' => 'caixa.print'],
            ],
          ],
          [
            'label' => 'Impressão de Recibos',
            'module' => 'recibos',
            'access' => 'recibos.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'recibos.create'],
              ['label' => 'Alterar', 'key' => 'recibos.update'],
              ['label' => 'Excluir', 'key' => 'recibos.delete'],
              ['label' => 'Imprimir', 'key' => 'recibos.print'],
            ],
          ],
          [
            'label' => 'Boleto',
            'module' => 'boletos',
            'access' => 'boletos.access',
            'actions' => [
              ['label' => 'Gerar / Importar', 'key' => 'boletos.create'],
              ['label' => 'Alterar configuração', 'key' => 'boletos.update'],
            ],
          ],
        ],
      ],
      [
        'id' => 'fiscal',
        'label' => 'Fiscal',
        'items' => [
          [
            'label' => 'NFC-e',
            'module' => 'nfce',
            'access' => 'nfce.access',
            'actions' => [
              ['label' => 'Cancelar', 'key' => 'nfce.cancel'],
            ],
          ],
          [
            'label' => 'NF-e',
            'module' => 'nfe',
            'access' => 'nfe.access',
            'actions' => [
              ['label' => 'Emitir', 'key' => 'nfe.emit'],
              ['label' => 'Cancelar', 'key' => 'nfe.cancel'],
            ],
          ],
          ['label' => 'NFS-e', 'module' => 'nfse', 'access' => 'nfse.access'],
          [
            'label' => 'CFOP e Operações fiscais',
            'module' => 'cfops',
            'access' => 'cfops.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'cfops.create'],
              ['label' => 'Alterar', 'key' => 'cfops.update'],
            ],
          ],
          [
            'label' => 'Tabela ICMS',
            'module' => 'tabela_icms',
            'access' => 'tabela_icms.access',
            'actions' => [
              ['label' => 'Alterar alíquotas', 'key' => 'tabela_icms.update'],
            ],
          ],
        ],
      ],
      [
        'id' => 'os',
        'label' => 'OS',
        'items' => [
          [
            'label' => 'Ordem de Serviço',
            'module' => 'ordens_servico',
            'access' => 'ordens_servico.access',
            'actions' => [
              ['label' => 'Incluir', 'key' => 'ordens_servico.create'],
              ['label' => 'Alterar', 'key' => 'ordens_servico.update'],
              ['label' => 'Imprimir', 'key' => 'ordens_servico.print'],
            ],
          ],
        ],
      ],
      [
        'id' => 'configuracoes',
        'label' => 'Configurações',
        'items' => [
          [
            'label' => 'Empresa',
            'module' => 'empresa',
            'access' => 'empresa.access',
            'actions' => [
              ['label' => 'Alterar', 'key' => 'empresa.update'],
            ],
          ],
          [
            'label' => 'Terminais',
            'module' => 'terminais',
            'access' => 'terminais.access',
            'actions' => [
              ['label' => 'Alterar', 'key' => 'terminais.update'],
            ],
          ],
          ['label' => 'Config. Fiscais', 'module' => 'config_fiscais', 'access' => 'config_fiscais.access'],
          [
            'label' => 'Balança',
            'module' => 'balanca',
            'access' => 'balanca.access',
            'actions' => [
              ['label' => 'Gerar arquivo', 'key' => 'balanca.generate'],
              ['label' => 'Alterar configuração', 'key' => 'balanca.update'],
            ],
          ],
          [
            'label' => 'Comandos',
            'module' => 'comandos',
            'access' => 'comandos.access',
            'actions' => [
              ['label' => 'Aquecer', 'key' => 'comandos.warm'],
              ['label' => 'Importar dados', 'key' => 'comandos.import_data'],
            ],
          ],
          [
            'label' => 'Backup',
            'module' => 'backup',
            'access' => 'backup.access',
            'actions' => [
              ['label' => 'Gerar', 'key' => 'backup.create'],
              ['label' => 'Alterar configuração', 'key' => 'backup.update'],
              ['label' => 'Restaurar', 'key' => 'backup.restore'],
            ],
          ],
          [
            'label' => 'Mercado Livre',
            'module' => 'mercado_livre',
            'actions' => [
              ['label' => 'Conectar conta', 'key' => 'mercado_livre.config'],
            ],
          ],
        ],
      ],
    ];
  }
}
