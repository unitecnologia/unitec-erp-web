<?php

namespace App\Support\Erp;

final class EmpresaParametros
{
    /**
     * Campos numéricos do topo (3 colunas na tela Delphi).
     *
     * @return array<string, array{label: string, default: int|float|string|null, type: string, decimals?: int}>
     */
    public static function numericFields(): array
    {
        return [
            'param_cod_caixa_geral' => [
                'label' => 'Código do Caixa Geral',
                'default' => 1,
                'type' => 'integer',
            ],
            'param_plano_transferencia_credito' => [
                'label' => 'Plano Transf. Crédito',
                'default' => 3,
                'type' => 'integer',
            ],
            'param_plano_transferencia_debito' => [
                'label' => 'Plano Transf. Débito',
                'default' => 4,
                'type' => 'integer',
            ],
            'param_empresa_padrao_relatorios' => [
                'label' => 'Empresa Padrão Relatórios',
                'default' => 1,
                'type' => 'integer',
            ],
            'param_plano_ficha_cliente' => [
                'label' => 'Plano Ficha Cliente',
                'default' => 10,
                'type' => 'integer',
            ],
            'param_ultimo_nsu' => [
                'label' => 'Último NSU',
                'default' => '0000000000',
                'type' => 'string',
            ],
            'param_desconto_maximo' => [
                'label' => 'Desconto Máximo',
                'default' => '0.00',
                'type' => 'decimal',
                'decimals' => 2,
            ],
            'param_acrescimo_maximo' => [
                'label' => 'Acréscimo Máximo',
                'default' => '0.00',
                'type' => 'decimal',
                'decimals' => 2,
            ],
            'param_pdv_modelo_balanca' => [
                'label' => 'Modelo Etiqueta Balança',
                'default' => 4,
                'type' => 'integer',
            ],
            'param_pdv_carga_intervalo_min' => [
                'label' => 'Intervalo Carga PDV (min)',
                'default' => 15,
                'type' => 'integer',
            ],
            'param_pdv_marquee_texto' => [
                'label' => 'Letreiro do PDV',
                'default' => '',
                'type' => 'string',
            ],
            'param_plano_abertura_caixa' => [
                'label' => 'Plano Abertura de Caixa',
                'default' => 14,
                'type' => 'integer',
            ],
            'param_cod_dinheiro_fpg' => [
                'label' => 'Cód. Dinheiro FPG',
                'default' => 1,
                'type' => 'integer',
            ],
            'param_nfe_serie' => [
                'label' => 'NFe Série',
                'default' => 1,
                'type' => 'integer',
            ],
            'param_plano_sangria' => [
                'label' => 'Plano Sangria',
                'default' => 11,
                'type' => 'integer',
            ],
            'param_plano_venda' => [
                'label' => 'Plano Venda',
                'default' => 2,
                'type' => 'integer',
            ],
            'param_plano_taxa_cartao' => [
                'label' => 'Plano Taxa Cartão',
                'default' => 8,
                'type' => 'integer',
            ],
            'param_plano_devolucao' => [
                'label' => 'Plano Devolução',
                'default' => 9,
                'type' => 'integer',
            ],
            'param_plano_compra' => [
                'label' => 'Plano Compra',
                'default' => 15,
                'type' => 'integer',
            ],
            'param_plano_boleto' => [
                'label' => 'Plano Boleto',
                'default' => 16,
                'type' => 'integer',
            ],
            'param_carencia_juros' => [
                'label' => 'Carência Juros',
                'default' => '0.00',
                'type' => 'decimal',
                'decimals' => 2,
            ],
            'param_juros_diario_pct' => [
                'label' => '% de Juros Diário',
                'default' => '0.00',
                'type' => 'decimal',
                'decimals' => 2,
            ],
            'param_lucro_padrao' => [
                'label' => 'Lucro Padrão',
                'default' => '0.00',
                'type' => 'decimal',
                'decimals' => 2,
            ],
            'param_meta_vendas_mensal' => [
                'label' => 'Meta Vendas Mensal',
                'default' => '0.00',
                'type' => 'decimal',
                'decimals' => 2,
                'hint' => 'Preenchida (> 0) aparece no dashboard; vazia ou zero some.',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, default: bool|null, tri?: bool}>
     */
    public static function permissionFields(): array
    {
        return [
            'param_pdv_habilitar_tabela_preco' => ['label' => 'Habilitar Tabela Preço', 'default' => false],
            'param_pdv_exibir_resumo_caixa' => ['label' => 'Exibir Resumo Caixa', 'default' => true],
            'param_pdv_caixa_rapido' => ['label' => 'Caixa Rápido (sem enter)', 'default' => false],
            'param_pdv_checar_limite_cliente' => ['label' => 'Checar Limite de Cliente', 'default' => false],
            'param_pdv_pedido_duas_vias' => ['label' => 'Pedido em Duas Vias (PDV)', 'default' => false],
            'param_pdv_permitir_desconto_item' => ['label' => 'Permitir Desconto Item (PDV)', 'default' => true],
            'param_pdv_habilitar_desconto' => ['label' => 'Habilitar Desconto no PDV', 'default' => false],
            'param_pdv_exibir_f3_vendedor' => ['label' => 'Exibir F3 Vendedor no PDV', 'default' => false],
            'param_pdv_ativar_som' => ['label' => 'Ativar Som no PDV (bip ao incluir item)', 'default' => false],
            'param_pdv_nfce_descricao_completa' => ['label' => 'NFC-e: Descrição Completa dos Itens (sem abreviar)', 'default' => false],
            'param_pdv_carga_auto' => ['label' => 'Carga Automática do PDV Offline', 'default' => true],

            'param_geral_informar_gtin' => ['label' => 'Informar GTIN', 'default' => false],
            'param_geral_desconto_prod_promocao' => ['label' => 'Dar Desconto Prod. Promoção', 'default' => false],
            'param_geral_bloquear_cpf_repetido' => ['label' => 'Bloquear Cadastro de CPF repetido', 'default' => null, 'tri' => true],
            'param_geral_ratear_preco_custo_xml' => ['label' => 'Ratear Preço de Custo (Compra XML)', 'default' => true],
            'param_geral_bloquear_estoque_negativo' => ['label' => 'Bloquear Estoque Negativo', 'default' => false],
            'param_geral_usar_transportadora' => ['label' => 'Usar Transportadora', 'default' => false],
            'param_geral_cadastrar_produtos_auto' => ['label' => 'Cadastrar Produtos Auto', 'default' => false],
            'param_geral_lancar_cartao_caixa' => [
                'label' => 'Lançar Cartão no Caixa',
                'hint' => 'Marcado: cartão entra no caixa como se já tivesse caído. Desmarcado: cartão vai para Contas a Receber até a operadora depositar.',
                'default' => false,
            ],
            'param_geral_rateio_pessoa_pdv' => ['label' => 'Mostra Rateio por pessoa no PDV', 'default' => true],
            'param_geral_perguntar_replicar_preco_filiais' => [
                'label' => 'Perguntar ao replicar preço nas filiais',
                'default' => false,
            ],

            'param_fiscal_puxar_cfop_produto' => ['label' => 'Puxar CFOP do Produto', 'default' => false],
            'param_fiscal_bloquear_cancelamento_doc' => ['label' => 'Bloquear Cancelamento Venda com Documento Fiscal Emitido', 'default' => true],
            'param_fiscal_motivo_estorno_automatico' => ['label' => 'Motivo de Estorno Automático', 'default' => false],
            'param_fiscal_nfe_baixa_estoque' => [
                'label' => 'NF-e baixa estoque',
                'hint' => 'Marcado: ao transmitir NF-e de saída, baixa o estoque dos produtos. Desmarcado: não movimenta estoque.',
                'default' => true,
            ],
        ];
    }

    /**
     * Módulos opcionais da empresa. Eles definem o que existe para todos os
     * usuários; as permissões de usuário definem quem pode operar cada módulo.
     *
     * @return array<string, array{label: string, description: string, default: bool}>
     */
    public static function moduleEnableFields(): array
    {
        return [
            'param_modulo_pdv' => [
                'label' => 'PDV',
                'description' => 'Ponto de venda, caixa e NFC-e no balcão.',
                'default' => true,
            ],
            'param_modulo_ordens_servico' => [
                'label' => 'Ordens de serviço',
                'description' => 'Cadastro e acompanhamento de serviços.',
                'default' => false,
            ],
            'param_modulo_forca_vendas' => [
                'label' => 'Força de vendas',
                'description' => 'Aplicativo, monitor e rotas de vendedores.',
                'default' => false,
            ],
            'param_modulo_vendas_internas' => [
                'label' => 'Vendas internas',
                'description' => 'Aparelhos e operações de vendas internas.',
                'default' => false,
            ],
            'param_modulo_logistica' => [
                'label' => 'Logística e expedição',
                'description' => 'Expedição, transportadores, veículos e rotas.',
                'default' => false,
            ],
            'param_modulo_rh' => [
                'label' => 'Recursos humanos',
                'description' => 'Funcionários, cargos, departamentos e painel RH.',
                'default' => true,
            ],
            'param_modulo_mercado_livre' => [
                'label' => 'Mercado Livre',
                'description' => 'Integração e configuração do Mercado Livre.',
                'default' => false,
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, default: int|float|string|null, type: string, decimals?: int}>
     */
    public static function impostoFields(): array
    {
        return [
            // ICMS Interno
            'param_imp_cfop_venda' => ['label' => 'CFOP Interno', 'default' => '5102', 'type' => 'string'],
            'param_imp_origem' => ['label' => 'Origem', 'default' => '0', 'type' => 'string'],
            'param_imp_icms_cst' => ['label' => 'CST ICMS', 'default' => '000', 'type' => 'string'],
            'param_imp_csosn' => ['label' => 'CSOSN', 'default' => '102', 'type' => 'string'],
            'param_imp_icms_aliquota' => ['label' => 'Alíq. ICMS %', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            // ICMS Externo
            'param_imp_cfop_externo' => ['label' => 'CFOP Externo', 'default' => '6102', 'type' => 'string'],
            'param_imp_icms_cst_externo' => ['label' => 'CST ICMS Ext.', 'default' => '000', 'type' => 'string'],
            'param_imp_csosn_externo' => ['label' => 'CSOSN Ext.', 'default' => '102', 'type' => 'string'],
            'param_imp_icms_aliquota_externo' => ['label' => 'Alíq. ICMS Ext. %', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            // PIS/COFINS — no Simples a NT oficial recomenda CST 99 (outras operações) com alíq. 0
            'param_imp_pis_cst' => ['label' => 'CST Ent.', 'default' => '99', 'type' => 'string'],
            'param_imp_cofins_cst' => ['label' => 'CST Saída', 'default' => '99', 'type' => 'string'],
            'param_imp_cst_cofins' => ['label' => 'CST COFINS', 'default' => '99', 'type' => 'string'],
            'param_imp_pis_aliquota' => ['label' => 'PIS %', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_cofins_aliquota' => ['label' => 'COFINS %', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            // IPI
            'param_imp_ipi_cst' => ['label' => 'CST IPI', 'default' => '99', 'type' => 'string'],
            'param_imp_ipi_aliquota' => ['label' => 'Alíquota IPI', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_cod_enq_ipi' => ['label' => 'Cód. Enq. IPI', 'default' => '', 'type' => 'string'],
            // Outros
            'param_imp_fcp_pct' => ['label' => '% FCP', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_mva_pct' => ['label' => '% MVA', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_mva_normal' => ['label' => '% MVA N.', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_reducao_base_pct' => ['label' => '% Base Red.', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_cod_beneficio' => ['label' => 'Cód. Benef.', 'default' => '', 'type' => 'string'],
            // Fiscal avançado
            'param_imp_tipo_tributacao' => ['label' => 'Tipo Trib.', 'default' => '', 'type' => 'string'],
            'param_imp_icms_diferido' => ['label' => 'ICMS Dif.', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_aliq_deson' => ['label' => 'Alíq. Deson.', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_motivo_desoneracao' => ['label' => 'Mot. Deson.', 'default' => '0', 'type' => 'string'],
            // IVA / IBS / CBS — padrão Simples + alíquotas-teste 2026 (LC 214/2025): IBS 0,1% + CBS 0,9%
            'param_imp_iva_cst' => ['label' => 'CST IBS/CBS', 'default' => '000', 'type' => 'string'],
            'param_imp_cclass_trib' => ['label' => 'Classificação Tributária', 'default' => '000001', 'type' => 'string'],
            'param_imp_aliq_ibs_uf' => ['label' => 'Aliq IBS UF', 'default' => '0.1000', 'type' => 'decimal', 'decimals' => 4],
            'param_imp_aliq_cbs' => ['label' => 'Aliq CBS', 'default' => '0.9000', 'type' => 'decimal', 'decimals' => 4],
            'param_imp_aliq_ibs_mun' => ['label' => 'Aliq IBS Mun', 'default' => '0.0000', 'type' => 'decimal', 'decimals' => 4],
            'param_imp_aliq_adrem_ibs' => ['label' => 'Aliq Adrem IBS', 'default' => '0.0000', 'type' => 'decimal', 'decimals' => 4],
            'param_imp_aliq_adrem_cbs' => ['label' => 'Aliq Adrem CBS', 'default' => '0.0000', 'type' => 'decimal', 'decimals' => 4],
            'param_imp_reducao_cbs' => ['label' => 'Redução CBS', 'default' => '0.0000', 'type' => 'decimal', 'decimals' => 4],
            'param_imp_reducao_ibs' => ['label' => 'Redução IBS', 'default' => '0.0000', 'type' => 'decimal', 'decimals' => 4],
            // Compra (fora do layout do produto, mas útil no padrão da empresa)
            'param_imp_cfop_compra' => ['label' => 'CFOP Compra', 'default' => '1102', 'type' => 'string'],
        ];
    }

    /**
     * @return array<string, array{label: string, default: int|float|string|null, type: string, decimals?: int}>
     */
    public static function difalFields(): array
    {
        return [
            'param_difal_aliquota_interna' => ['label' => 'Alíquota Interna UF (%)', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_difal_aliquota_interestadual' => ['label' => 'Alíquota Interestadual (%)', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_difal_fcp_pct' => ['label' => 'FCP (%)', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_difal_base_calculo' => ['label' => 'Base de Cálculo DIFAL', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
        ];
    }

    /**
     * @return array<string, array{label: string, default: int|float|string|null, type: string}>
     */
    public static function pixFields(): array
    {
        return [
            'param_pix_provedor' => ['label' => 'Provedor Pix', 'default' => 'mercadopago', 'type' => 'string'],
            'param_pix_mp_access_token' => ['label' => 'Mercado Pago — Access Token', 'default' => '', 'type' => 'string'],
            'param_pix_chave' => ['label' => 'Chave PIX', 'default' => '', 'type' => 'string'],
            'param_pix_client_id' => ['label' => 'Client ID', 'default' => '', 'type' => 'string'],
            'param_pix_client_secret' => ['label' => 'Client Secret', 'default' => '', 'type' => 'string'],
            'param_pix_certificado' => ['label' => 'Certificado (.pfx)', 'default' => '', 'type' => 'string'],
            'param_pix_ambiente' => ['label' => 'Ambiente', 'default' => 'homologacao', 'type' => 'string'],
            'param_pix_webhook_url' => ['label' => 'URL Webhook', 'default' => '', 'type' => 'string'],
        ];
    }

    /**
     * Provedores Pix suportados (para o seletor da aba API PIX).
     *
     * @return array<string, string>
     */
    public static function pixProvedorOptions(): array
    {
        return [
            'mercadopago' => 'Mercado Pago',
        ];
    }

    /**
     * Campos da aba "API Boleto" (cobrança registrada via API do banco).
     *
     * Os campos cobrem o que os principais bancos exigem: identificação da
     * conta/convênio, credenciais OAuth2 (client id/secret + chave de app),
     * certificado mTLS (Itaú/Bradesco/Santander) e parâmetros do título.
     * Nem todo banco usa todos os campos — preencha conforme o manual do banco.
     *
     * @return array<string, array{label: string, default: int|float|string|null, type: string}>
     */
    public static function boletoFields(): array
    {
        return [
            'param_boleto_banco' => ['label' => 'Banco', 'default' => '', 'type' => 'string'],
            'param_boleto_ambiente' => ['label' => 'Ambiente', 'default' => 'homologacao', 'type' => 'string'],
            'param_boleto_convenio' => ['label' => 'Convênio / Código do Cedente', 'default' => '', 'type' => 'string'],
            'param_boleto_carteira' => ['label' => 'Carteira', 'default' => '', 'type' => 'string'],
            'param_boleto_variacao_carteira' => ['label' => 'Variação da Carteira', 'default' => '', 'type' => 'string'],
            'param_boleto_agencia' => ['label' => 'Agência', 'default' => '', 'type' => 'string'],
            'param_boleto_agencia_dv' => ['label' => 'Dígito da Agência', 'default' => '', 'type' => 'string'],
            'param_boleto_conta' => ['label' => 'Conta', 'default' => '', 'type' => 'string'],
            'param_boleto_conta_dv' => ['label' => 'Dígito da Conta', 'default' => '', 'type' => 'string'],
            'param_boleto_beneficiario_codigo' => ['label' => 'Código do Beneficiário', 'default' => '', 'type' => 'string'],
            'param_boleto_client_id' => ['label' => 'Client ID (OAuth)', 'default' => '', 'type' => 'string'],
            'param_boleto_client_secret' => ['label' => 'Client Secret (OAuth)', 'default' => '', 'type' => 'string'],
            'param_boleto_dev_app_key' => ['label' => 'Chave de Aplicação (Developer/App Key)', 'default' => '', 'type' => 'string'],
            'param_boleto_oauth_scope' => ['label' => 'Escopo OAuth (scope)', 'default' => '', 'type' => 'string'],
            'param_boleto_api_url' => ['label' => 'URL base da API (opcional)', 'default' => '', 'type' => 'string'],
            'param_boleto_certificado' => ['label' => 'Certificado (.pfx/.pem)', 'default' => '', 'type' => 'string'],
            'param_boleto_certificado_senha' => ['label' => 'Senha do Certificado', 'default' => '', 'type' => 'string'],
            'param_boleto_nosso_numero_inicial' => ['label' => 'Nosso Número inicial', 'default' => '', 'type' => 'string'],
            'param_boleto_especie_documento' => ['label' => 'Espécie do Documento', 'default' => 'DM', 'type' => 'string'],
            'param_boleto_local_pagamento' => ['label' => 'Local de Pagamento', 'default' => '', 'type' => 'string'],
            'param_boleto_instrucao1' => ['label' => 'Instrução (linha 1)', 'default' => '', 'type' => 'string'],
            'param_boleto_instrucao2' => ['label' => 'Instrução (linha 2)', 'default' => '', 'type' => 'string'],
            'param_boleto_juros_pct' => ['label' => 'Juros (% ao mês)', 'default' => '', 'type' => 'string'],
            'param_boleto_multa_pct' => ['label' => 'Multa (%)', 'default' => '', 'type' => 'string'],
            'param_boleto_desconto_pct' => ['label' => 'Desconto (%)', 'default' => '', 'type' => 'string'],
            'param_boleto_carencia_dias' => ['label' => 'Carência após vencimento (dias)', 'default' => '', 'type' => 'string'],
            'param_boleto_protesto_dias' => ['label' => 'Protestar após (dias)', 'default' => '', 'type' => 'string'],
            'param_boleto_baixa_dias' => ['label' => 'Baixar/Devolver após (dias)', 'default' => '', 'type' => 'string'],
        ];
    }

    /**
     * @return array<string, array{label: string, default: bool}>
     */
    public static function boletoBooleanFields(): array
    {
        return [
            'param_boleto_habilitar' => ['label' => 'Habilitar API Boleto', 'default' => false],
            'param_boleto_registrar_automatico' => ['label' => 'Registrar boleto automaticamente na geração', 'default' => false],
            'param_boleto_pix_hibrido' => ['label' => 'Gerar PIX no boleto (híbrido)', 'default' => false],
            'param_boleto_protestar_automatico' => ['label' => 'Enviar para protesto automaticamente', 'default' => false],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function boletoAmbienteOptions(): array
    {
        return [
            'homologacao' => 'Homologação',
            'producao' => 'Produção',
        ];
    }

    /**
     * Espécies de documento aceitas pela maioria dos bancos.
     *
     * @return array<string, string>
     */
    public static function boletoEspecieOptions(): array
    {
        return [
            'DM' => 'DM - Duplicata Mercantil',
            'DS' => 'DS - Duplicata de Serviço',
            'NP' => 'NP - Nota Promissória',
            'NS' => 'NS - Nota de Seguro',
            'RC' => 'RC - Recibo',
            'FAT' => 'FAT - Fatura',
            'ND' => 'ND - Nota de Débito',
            'AP' => 'AP - Apólice de Seguro',
            'CH' => 'CH - Cheque',
            'DD' => 'DD - Documento de Dívida',
            'BDP' => 'BDP - Boleto de Proposta',
            'OUTROS' => 'Outros',
        ];
    }

    /**
     * Bancos brasileiros por código de compensação (COMPE), para o seletor da
     * aba API Boleto.
     *
     * @return array<string, string>
     */
    public static function boletoBancoOptions(): array
    {
        $bancos = [
            '001' => 'Banco do Brasil',
            '003' => 'Banco da Amazônia',
            '004' => 'Banco do Nordeste',
            '021' => 'Banestes',
            '025' => 'Banco Alfa',
            '033' => 'Santander',
            '036' => 'Banco Bradesco BBI',
            '037' => 'Banpará',
            '041' => 'Banrisul',
            '047' => 'Banese',
            '062' => 'Hipercard',
            '069' => 'Banco Crefisa',
            '070' => 'BRB - Banco de Brasília',
            '074' => 'Banco J. Safra',
            '077' => 'Banco Inter',
            '079' => 'Banco Original do Agronegócio',
            '081' => 'BancoSeguro',
            '082' => 'Banco Topázio',
            '083' => 'Banco da China Brasil',
            '084' => 'Uniprime Norte do Paraná',
            '085' => 'Ailos / Cecred',
            '089' => 'Cooperativa Credisan',
            '091' => 'Unicred Central RS',
            '093' => 'Pólocred',
            '094' => 'Banco Finaxis',
            '095' => 'Banco Confidence de Câmbio',
            '096' => 'Banco B3',
            '097' => 'Credisis',
            '098' => 'Credialiança',
            '099' => 'Uniprime Central',
            '104' => 'Caixa Econômica Federal',
            '107' => 'Banco BOCOM BBM',
            '108' => 'PortoCred',
            '114' => 'Central Cooperativa de Crédito (Cecoop)',
            '119' => 'Banco Western Union',
            '120' => 'Banco Rodobens',
            '121' => 'Banco Agibank',
            '122' => 'Banco Bradesco BERJ',
            '124' => 'Banco Woori Bank do Brasil',
            '125' => 'Banco Genial (Plural)',
            '126' => 'BR Partners Banco de Investimento',
            '129' => 'UBS Brasil Banco de Investimento',
            '130' => 'Caruana SCFI',
            '132' => 'ICBC do Brasil',
            '133' => 'Cresol Confederação',
            '136' => 'Unicred',
            '138' => 'Get Money Corretora de Câmbio',
            '139' => 'Intesa Sanpaolo Brasil',
            '143' => 'Treviso Corretora de Câmbio',
            '144' => 'Bexs Banco de Câmbio',
            '169' => 'Banco Olé Bonsucesso Consignado',
            '174' => 'Pernambucanas Financiadora',
            '177' => 'Guide Investimentos',
            '180' => 'CM Capital Markets',
            '183' => 'Socred',
            '184' => 'Banco Itaú BBA',
            '188' => 'Ativa Investimentos',
            '189' => 'HS Financeira',
            '190' => 'Servicoop',
            '191' => 'Nova Futura Corretora',
            '194' => 'Parmetal DTVM',
            '197' => 'Stone',
            '208' => 'Banco BTG Pactual',
            '212' => 'Banco Original',
            '213' => 'Banco Arbi',
            '217' => 'Banco John Deere',
            '218' => 'Banco BS2',
            '222' => 'Banco Credit Agricole Brasil',
            '224' => 'Banco Fibra',
            '233' => 'Banco Cifra',
            '237' => 'Bradesco',
            '241' => 'Banco Clássico',
            '243' => 'Banco Master',
            '246' => 'Banco ABC Brasil',
            '249' => 'Banco Investcred Unibanco',
            '250' => 'BCV - Banco de Crédito e Varejo',
            '253' => 'Bexs Corretora de Câmbio',
            '254' => 'Paraná Banco',
            '260' => 'Nubank',
            '265' => 'Banco Fator',
            '266' => 'Banco Cédula',
            '268' => 'Bari (Barigui)',
            '269' => 'Banco HSBC',
            '270' => 'Sagitur Corretora de Câmbio',
            '271' => 'IB Corretora de Câmbio',
            '272' => 'AGK Corretora de Câmbio',
            '273' => 'CCR de São Miguel do Oeste',
            '274' => 'Money Plus',
            '276' => 'Senff',
            '278' => 'Genial Investimentos',
            '279' => 'Cooperativa de Crédito Primavera do Leste',
            '280' => 'Will Financeira (Avista)',
            '281' => 'Cooperativa Sicoob Credialiança',
            '283' => 'RB Capital Investimentos',
            '285' => 'Frente Corretora de Câmbio',
            '286' => 'Cooperativa Sicoob Sul',
            '288' => 'Carol DTVM',
            '290' => 'PagBank (PagSeguro)',
            '292' => 'BS2 DTVM',
            '293' => 'Banco Lecca',
            '296' => 'Vision DTVM',
            '298' => 'Vips Corretora de Câmbio',
            '299' => 'Banco Sorocred',
            '300' => 'Banco de la Nación Argentina',
            '301' => 'BPP Instituição de Pagamento',
            '306' => 'Portopar DTVM',
            '307' => 'Terra Investimentos',
            '309' => 'Cambionet Corretora de Câmbio',
            '310' => 'VORTX DTVM',
            '311' => 'Dourada Corretora de Câmbio',
            '312' => 'HSCM Cooperativa de Crédito',
            '313' => 'Amazônia Corretora de Câmbio',
            '315' => 'PI DTVM',
            '318' => 'Banco BMG',
            '319' => 'OM DTVM',
            '320' => 'China Construction Bank (CCB Brasil)',
            '321' => 'Crefaz SCMEPP',
            '322' => 'Cooperativa de Crédito Rio Grande do Sul',
            '323' => 'Mercado Pago',
            '325' => 'Órama DTVM',
            '326' => 'Parati - Crédito Financiamento',
            '329' => 'QI Sociedade de Crédito Direto',
            '330' => 'Banco Bari',
            '331' => 'Fram Capital DTVM',
            '332' => 'Acesso Soluções de Pagamento',
            '335' => 'Banco Digio',
            '336' => 'Banco C6',
            '340' => 'Super Pagamentos (Superdigital)',
            '341' => 'Itaú Unibanco',
            '342' => 'Creditas SCD',
            '343' => 'FFA SCMEPP',
            '348' => 'Banco XP',
            '349' => 'AMAGGI Crédito Financiamento',
            '352' => 'Toro CTVM',
            '354' => 'Necton Investimentos',
            '355' => 'Ótimo SCD',
            '358' => 'Midway',
            '359' => 'Zema CFI',
            '360' => 'Trinus Capital DTVM',
            '362' => 'Cielo',
            '363' => 'Singulare CTVM (Socopa)',
            '364' => 'Efí (Gerencianet)',
            '365' => 'Solidus CCVM',
            '366' => 'Banco Société Générale Brasil',
            '367' => 'Vitreo DTVM',
            '368' => 'Banco CSF (Carrefour)',
            '370' => 'Banco Mizuho do Brasil',
            '376' => 'Banco J.P. Morgan',
            '377' => 'BMS SCD',
            '378' => 'Banco Brasileiro de Crédito',
            '379' => 'CooperForte',
            '380' => 'PicPay',
            '381' => 'Banco Mercedes-Benz',
            '382' => 'Fidúcia SCMEPP',
            '383' => 'Juno (Boletobancário/Ebanx)',
            '384' => 'Global Finanças SCMEPP',
            '385' => 'Cooperativa Eup. Nordeste (Cecm)',
            '386' => 'Nu Financeira SCFI',
            '387' => 'Banco Toyota do Brasil',
            '389' => 'Banco Mercantil do Brasil',
            '390' => 'Banco GM',
            '391' => 'Cooperativa de Crédito Capal',
            '393' => 'Banco Volkswagen',
            '394' => 'Banco Bradesco Financiamentos',
            '395' => 'F.D. Gold DTVM',
            '396' => 'Hub Pagamentos',
            '397' => 'Listo SCD',
            '398' => 'Ideal CTVM',
            '399' => 'Kirton Bank (HSBC)',
            '400' => 'Cooperativa Coopcredi (Jacarezinho)',
            '401' => 'Iugu Instituição de Pagamento',
            '402' => 'Cobuccio SCD',
            '403' => 'Cora SCD',
            '404' => 'Sumup SCD',
            '406' => 'Accredito SCD',
            '407' => 'Índigo Investimentos DTVM',
            '408' => 'Bonuspago SCD',
            '410' => 'Planner SCM',
            '411' => 'Via Certa Financiadora',
            '412' => 'Banco Capital',
            '413' => 'Banco BV',
            '414' => 'Work SCD',
            '416' => 'Lamara SCD',
            '418' => 'Zipdin SCD',
            '419' => 'Numbrs SCD',
            '421' => 'LAR Cooperativa de Crédito',
            '422' => 'Banco Safra',
            '425' => 'Socinal Crédito Financiamento',
            '426' => 'Biorc Financeira',
            '427' => 'Cooperativa Cresol (Crednossa)',
            '428' => 'Cred-System SCD',
            '429' => 'Crediare CFI',
            '430' => 'Cooperativa de Crédito Rural Seara',
            '433' => 'BR-Capital DTVM',
            '435' => 'Delcred SCD',
            '438' => 'Planner Trustee DTVM',
            '439' => 'ID CTVM',
            '440' => 'Credibrf Cooperativa de Crédito',
            '442' => 'Magnetis DTVM',
            '443' => 'Credihome SCD',
            '444' => 'Trinus SCD',
            '445' => 'Plantae CFI',
            '447' => 'Mirae Asset CCTVM',
            '448' => 'Hemera DTVM',
            '449' => 'Dmcard SCD',
            '450' => 'Fitbank Pagamentos Eletrônicos',
            '451' => 'J17 DTVM',
            '452' => 'Credifit SCD',
            '454' => 'Open Co SCD (Rebel)',
            '456' => 'Banco MUFG Brasil',
            '457' => 'Uy3 SCD',
            '458' => 'Hedge Investments DTVM',
            '459' => 'Cooperativa de Crédito Municípios (Credcrea)',
            '460' => 'Unavanti SCD',
            '461' => 'Asaas IP',
            '462' => 'Stark SCD',
            '463' => 'Azumi DTVM',
            '464' => 'Banco Sumitomo Mitsui',
            '465' => 'Capital Consig SCD',
            '467' => 'Master S/A CCTVM',
            '468' => 'PortoPay',
            '469' => 'Levycam CCV',
            '470' => 'CDC SCD',
            '471' => 'Cecm Servidores do Estado do ES',
            '473' => 'Banco Caixa Geral Brasil',
            '477' => 'Citibank N.A.',
            '478' => 'Gazincred',
            '479' => 'Banco ItauBank',
            '481' => 'Superlógica SCD',
            '482' => 'Sbcash SCD',
            '484' => 'Maf DTVM',
            '487' => 'Deutsche Bank',
            '488' => 'JPMorgan Chase Bank',
            '489' => 'Euroinvest CVMC',
            '492' => 'ING Bank N.V.',
            '494' => 'Banco de La Republica Oriental del Uruguay',
            '495' => 'Banco de La Provincia de Buenos Aires',
            '505' => 'Banco Credit Suisse Brasil',
            '506' => 'RJI Corretora de Títulos',
            '508' => 'Avenue Securities DTVM',
            '509' => 'Celcoin Instituição de Pagamento',
            '511' => 'Magnum SCD',
            '512' => 'Captalys DTVM',
            '513' => 'ATF Crédito e Financiamento',
            '514' => 'Efí (Gerencianet) S.A.',
            '516' => 'QISTA Crédito Financiamento',
            '518' => 'Mercado Crédito SCFI',
            '519' => 'Liga Invest DTVM',
            '520' => 'Somapay SCD',
            '522' => 'Rede Confiança Cooperativa',
            '525' => 'Interpag IP',
            '527' => 'ATICCA SCD',
            '528' => 'Reag DTVM',
            '529' => 'Pinbank IP',
            '530' => 'Ser Finance SCD',
            '531' => 'BMP SCMEPP',
            '532' => 'Eaglepoint SCD',
            '534' => 'Evertec do Brasil IP',
            '535' => 'Marú SCD',
            '536' => 'Neon Pagamentos',
            '537' => 'Microcash SCMEPP',
            '538' => 'Sudacred SCMEPP',
            '539' => 'Santinvest CFI',
            '540' => 'PagPrest SCD',
            '541' => 'Fundo Garantidor de Créditos',
            '542' => 'Cloud Walk IP',
            '543' => 'Comeici Cooperativa',
            '545' => 'Senso CCVM',
            '546' => 'U4Crypto SCD',
            '547' => 'Hbi SCD',
            '548' => 'RPW S.A. SCFI',
            '549' => 'Intercam Corretora de Câmbio',
            '550' => 'BeeTech IP',
            '551' => 'Vero SCD',
            '552' => 'UY3 IP',
            '553' => 'Perfin SCD',
            '554' => 'Stark Banco',
            '555' => 'Pagar.me IP',
            '556' => 'Bndes',
            '560' => 'Mag IP',
            '561' => 'Pague Veloz IP',
            '562' => 'Azimut Brasil DTVM',
            '563' => 'Pinbank Brasil IP',
            '565' => 'Áurea SCD',
            '566' => 'Lifepay IP',
            '600' => 'Banco Luso Brasileiro',
            '604' => 'Banco Industrial do Brasil',
            '610' => 'Banco VR',
            '611' => 'Banco Paulista',
            '612' => 'Banco Guanabara',
            '613' => 'Omni Banco',
            '623' => 'Banco PAN',
            '626' => 'Banco C6 Consignado (Ficsa)',
            '630' => 'Banco Smartbank (Intercap)',
            '633' => 'Banco Rendimento',
            '634' => 'Banco Triângulo (Tribanco)',
            '637' => 'Banco Sofisa',
            '643' => 'Banco Pine',
            '652' => 'Itaú Unibanco Holding',
            '653' => 'Banco Voiter (Indusval)',
            '654' => 'Banco Digimais',
            '655' => 'Banco Votorantim (BV)',
            '707' => 'Banco Daycoval',
            '712' => 'Banco Ourinvest',
            '720' => 'Banco RNX (Maxima)',
            '739' => 'Banco Cetelem',
            '741' => 'Banco Ribeirão Preto',
            '743' => 'Banco Semear',
            '745' => 'Banco Citibank',
            '746' => 'Banco Modal',
            '747' => 'Banco Rabobank International Brasil',
            '748' => 'Sicredi',
            '751' => 'Scotiabank Brasil',
            '752' => 'BNP Paribas Brasil',
            '753' => 'Novo Banco Continental',
            '754' => 'Banco Sistema',
            '755' => 'Bank of America Merrill Lynch',
            '756' => 'Sicoob (Bancoob)',
            '757' => 'Banco KEB Hana do Brasil',
        ];

        $options = ['' => 'Selecione...'];

        foreach ($bancos as $codigo => $nome) {
            $options[$codigo] = $codigo . ' - ' . $nome;
        }

        return $options;
    }

    /**
     * @return array<string, array{label: string, default: int|float|string|null, type: string}>
     */
    public static function apiServicosFields(): array
    {
        return [
            'param_api_servicos_url' => ['label' => 'URL da API', 'default' => '', 'type' => 'string'],
            'param_api_servicos_usuario' => ['label' => 'Usuário', 'default' => '', 'type' => 'string'],
            'param_api_servicos_senha' => ['label' => 'Senha', 'default' => '', 'type' => 'string'],
            'param_api_servicos_token' => ['label' => 'Token / API Key', 'default' => '', 'type' => 'string'],
            'param_api_servicos_timeout' => ['label' => 'Timeout (segundos)', 'default' => 30, 'type' => 'integer'],
        ];
    }

    /**
     * Links públicos (Cloudflare Tunnel / acesso remoto).
     *
     * @return array<string, array{label: string, default: string, type: string}>
     */
    public static function acessoRemotoFields(): array
    {
        return [
            'param_erp_public_url' => [
                'label' => 'URL pública do ERP',
                'default' => 'https://sua-loja.unierp.uk',
                'type' => 'text',
            ],
            'param_gestor_public_url' => [
                'label' => 'URL pública do Gestor',
                'default' => 'https://sua-loja.unierp.uk/gestor',
                'type' => 'text',
            ],
        ];
    }

    /**
     * Credenciais / estado do provisionamento Cloudflare (acesso remoto).
     *
     * @return array<string, array{label: string, default: string, type: string}>
     */
    public static function cloudflareAcessoFields(): array
    {
        return [
            'param_cf_api_token' => [
                'label' => 'Token Cloudflare',
                'default' => '',
                'type' => 'string',
            ],
            'param_cf_account_id' => [
                'label' => 'Account ID',
                'default' => '',
                'type' => 'string',
            ],
            'param_cf_zone_id' => [
                'label' => 'Zone ID',
                'default' => '',
                'type' => 'string',
            ],
            'param_cf_base_domain' => [
                'label' => 'Domínio base',
                'default' => 'unierp.uk',
                'type' => 'string',
            ],
            'param_cf_subdomain' => [
                'label' => 'Subdomínio',
                'default' => '',
                'type' => 'string',
            ],
            'param_cf_tunnel_id' => [
                'label' => 'Tunnel ID',
                'default' => '',
                'type' => 'string',
            ],
            'param_cf_hostname' => [
                'label' => 'Hostname',
                'default' => '',
                'type' => 'string',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, default: bool}>
     */
    public static function acessoRemotoBooleanFields(): array
    {
        return [
            'param_acesso_remoto_habilitar' => [
                'label' => 'Habilitar acesso remoto',
                'default' => true,
            ],
        ];
    }

    /**
     * @return array<string, bool|null>
     */
    public static function difalBooleanFields(): array
    {
        return [
            'param_difal_usar' => ['label' => 'Utilizar DIFAL', 'default' => false],
            'param_difal_destacar_nfe' => ['label' => 'Destacar DIFAL na NFe', 'default' => false],
        ];
    }

    /**
     * @return array<string, bool|null>
     */
    public static function pixBooleanFields(): array
    {
        return [
            'param_pix_habilitar' => ['label' => 'Habilitar API PIX', 'default' => false],
        ];
    }

    /**
     * @return array<string, bool|null>
     */
    public static function apiServicosBooleanFields(): array
    {
        return [
            'param_api_servicos_habilitar' => ['label' => 'Habilitar Busca Produto Auto', 'default' => false],
        ];
    }

    /**
     * Timeout da API de licença (URL do portal é nativa em config/unitec.php).
     *
     * @return array<string, array{label: string, default: int|float|string|null, type: string}>
     */
    public static function licencaApiFields(): array
    {
        return [
            'param_licenca_api_timeout' => [
                'label' => 'Timeout (segundos)',
                'default' => 8,
                'type' => 'integer',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, default: bool}>
     */
    public static function licencaApiBooleanFields(): array
    {
        return [
            'param_licenca_api_habilitar' => [
                'label' => 'Validar licença online (bloqueio pelo gerenciador)',
                'default' => true,
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, default: int|float|string|null, type: string}>
     */
    public static function whatsAppFields(): array
    {
        return [
            'param_whatsapp_gateway_port' => ['label' => 'Porta do serviço interno', 'default' => 8091, 'type' => 'integer'],
            'param_whatsapp_interno_chave' => ['label' => 'Chave interna do gateway', 'default' => '', 'type' => 'string'],
            'param_whatsapp_status' => ['label' => 'Status da conexão', 'default' => 'desconectado', 'type' => 'string'],
            'param_whatsapp_numero' => ['label' => 'Número conectado', 'default' => '', 'type' => 'string'],
            'param_whatsapp_timeout' => ['label' => 'Timeout (segundos)', 'default' => 30, 'type' => 'integer'],
            'param_whatsapp_limite_dia' => ['label' => 'Limite de mensagens por dia', 'default' => 100, 'type' => 'integer'],
            'param_whatsapp_msgs_hoje' => ['label' => 'Mensagens enviadas hoje', 'default' => 0, 'type' => 'integer'],
            'param_whatsapp_msgs_data' => ['label' => 'Data do contador diário', 'default' => null, 'type' => 'date'],
        ];
    }

    /**
     * @return array<string, array{label: string, default: bool}>
     */
    public static function whatsAppBooleanFields(): array
    {
        return [
            'param_whatsapp_habilitar' => ['label' => 'Habilitar envio de WhatsApp pelo ERP', 'default' => false],
            'param_whatsapp_enviar_orcamento' => ['label' => 'Permitir envio de orçamentos', 'default' => true],
            'param_whatsapp_enviar_cobranca' => ['label' => 'Permitir envio de cobranças', 'default' => true],
            'param_whatsapp_enviar_nfe' => ['label' => 'Permitir envio de NF-e', 'default' => true],
        ];
    }

    /**
     * @return array<string, array{label: string, default: int|float|string|null, type: string}>
     */
    public static function portalContadorFields(): array
    {
        return [
            'param_portal_contador_url' => ['label' => 'URL da API (nuvem)', 'default' => '', 'type' => 'string'],
            'param_portal_contador_empresa_id' => ['label' => 'ID da empresa na nuvem', 'default' => '', 'type' => 'string'],
            'param_portal_contador_token' => ['label' => 'Token / API Key', 'default' => '', 'type' => 'string'],
            'param_portal_contador_ambiente' => ['label' => 'Ambiente', 'default' => 'homologacao', 'type' => 'string'],
            'param_portal_contador_timeout' => ['label' => 'Timeout (segundos)', 'default' => 30, 'type' => 'integer'],
            'param_portal_contador_contador_id' => ['label' => 'Contador vinculado', 'default' => null, 'type' => 'integer'],
            'param_portal_contador_email' => ['label' => 'E-mail do escritório contábil', 'default' => '', 'type' => 'string'],
            'param_portal_contador_vinculo_id' => ['label' => 'ID do vínculo no portal', 'default' => '', 'type' => 'string'],
            'param_portal_contador_contador_nome_portal' => ['label' => 'Contador no portal', 'default' => '', 'type' => 'string'],
        ];
    }

    /**
     * Campos preenchidos automaticamente após vínculo — não exibir no formulário manual.
     *
     * @return list<string>
     */
    public static function portalContadorAutoFields(): array
    {
        return [
            'param_portal_contador_url',
            'param_portal_contador_empresa_id',
            'param_portal_contador_token',
            'param_portal_contador_vinculo_id',
            'param_portal_contador_contador_nome_portal',
        ];
    }

    /**
     * @return array<string, array{label: string, default: int|float|string|null, type: string}>
     */
    public static function portalContadorManualFields(): array
    {
        return array_diff_key(
            self::portalContadorFields(),
            array_flip(self::portalContadorAutoFields()),
        );
    }

    /**
     * @return array<string, array{label: string, default: bool}>
     */
    public static function portalContadorBooleanFields(): array
    {
        return [
            'param_portal_contador_habilitar' => ['label' => 'Habilitar envio para o Portal do Contador', 'default' => false],
            'param_portal_contador_enviar_compras' => ['label' => 'Enviar compras (NF-e entrada)', 'default' => true],
            'param_portal_contador_enviar_vendas' => ['label' => 'Enviar vendas (NF-e / NFC-e saída)', 'default' => false],
            'param_portal_contador_enviar_xml' => ['label' => 'Enviar XML completo', 'default' => true],
            'param_portal_contador_enviar_canceladas' => ['label' => 'Enviar cancelamentos', 'default' => true],
            'param_portal_contador_enviar_pacote_mensal' => ['label' => 'Gerar pacote mensal (ZIP)', 'default' => false],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function portalContadorAmbienteOptions(): array
    {
        return [
            'homologacao' => 'Homologação',
            'producao' => 'Produção',
        ];
    }

    /**
     * @return array<string, array{label: string, default: int|float|string|null, type: string}>
     */
    public static function mercadoLivreFields(): array
    {
        return [
            'param_meli_modo' => ['label' => 'Modo de conexão', 'default' => 'hub', 'type' => 'string'],
            'param_meli_client_id' => ['label' => 'Client ID', 'default' => '', 'type' => 'string'],
            'param_meli_client_secret' => ['label' => 'Client Secret', 'default' => '', 'type' => 'string'],
            'param_meli_redirect_uri' => ['label' => 'URI de redirect', 'default' => '', 'type' => 'string'],
            'param_meli_app_url' => ['label' => 'APP_URL (ML)', 'default' => '', 'type' => 'string'],
            'param_meli_hub_url' => ['label' => 'Hub URL (ML)', 'default' => '', 'type' => 'string'],
            'param_meli_user_id' => ['label' => 'ID do usuário ML', 'default' => '', 'type' => 'string'],
            'param_meli_nickname' => ['label' => 'Apelido ML', 'default' => '', 'type' => 'string'],
            'param_meli_access_token' => ['label' => 'Access Token', 'default' => '', 'type' => 'string'],
            'param_meli_refresh_token' => ['label' => 'Refresh Token', 'default' => '', 'type' => 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mercadoLivreModoOptions(): array
    {
        return [
            'hub' => 'Servidor Unitec (sem site próprio)',
            'proprio' => 'Domínio próprio do cliente',
        ];
    }

    /**
     * @return array<string, array{label: string, default: bool}>
     */
    public static function mercadoLivreBooleanFields(): array
    {
        return [
            'param_meli_habilitar' => ['label' => 'Habilitar integração Mercado Livre', 'default' => false],
            'param_meli_is_hub' => ['label' => 'Este servidor é o hub ML', 'default' => false],
        ];
    }

    /**
     * Campos da aba "Atualização e Backup" (parâmetros de sistema).
     *
     * @return array<string, array{label: string, default: int|float|string|null, type: string}>
     */
    public static function sistemaFields(): array
    {
        return [
            'param_update_download_url' => [
                'label' => 'Link do arquivo de atualização (Unitec-ERP-Update.zip)',
                'default' => '',
                'type' => 'text',
            ],
            'param_backup_pasta_destino' => [
                'label' => 'Pasta de destino do backup',
                'default' => '',
                'type' => 'string',
            ],
            'param_balanca_modelo' => [
                'label' => 'Modelo padrão da balança (arquivo)',
                'default' => 'modFilizola',
                'type' => 'string',
            ],
            'param_balanca_diretorio' => [
                'label' => 'Diretório de arquivos da balança',
                'default' => 'C:\\UNITECNOLOGIA_WEB\\balanca',
                'type' => 'string',
            ],
            'param_balanca_etiqueta_modelo' => [
                'label' => 'Modelo da etiqueta de balança (01–05)',
                'default' => 4,
                'type' => 'integer',
            ],
            'param_balanca_prefixo_barra' => [
                'label' => 'Prefixo do código de barras da balança',
                'default' => '2',
                'type' => 'string',
            ],
            'param_balanca_digitos' => [
                'label' => 'Dígitos do código do produto na etiqueta',
                'default' => 6,
                'type' => 'integer',
            ],
            'param_backup_intervalo_horas' => [
                'label' => 'Intervalo entre backups (horas)',
                'default' => 24,
                'type' => 'integer',
            ],
            'param_backup_ultimo_em' => [
                'label' => 'Último backup em',
                'default' => '',
                'type' => 'string',
            ],
            'param_backup_ultimo_status' => [
                'label' => 'Status do último backup',
                'default' => '',
                'type' => 'string',
            ],
            'param_ui_density' => [
                'label' => 'Tamanho da letra',
                'default' => '14',
                'type' => 'string',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, default: bool}>
     */
    public static function sistemaBooleanFields(): array
    {
        return [
            'param_backup_habilitar' => [
                'label' => 'Habilitar backup automático',
                'default' => false,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sistemaBackupStatusOptions(): array
    {
        return [
            '' => 'Nunca executado',
            'ok' => 'Concluído',
            'running' => 'Em andamento',
            'failed' => 'Falhou',
        ];
    }

    /**
     * Tamanhos de letra (px) disponíveis na raiz do HTML.
     *
     * @return array<string, string>
     */
    public static function sistemaUiDensityOptions(): array
    {
        $options = [];

        foreach ([12, 13, 14, 15, 16, 17, 18, 19, 20, 22] as $px) {
            $suffix = $px === 14 ? ' (padrão)' : '';
            $options[(string) $px] = $px.' px'.$suffix;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function permissionGroups(): array
    {
        return [
            'pdv' => 'Ajustes no PDV',
            'geral' => 'Ajustes Gerais',
            'fiscal' => 'Ajustes Fiscais',
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionGroupForField(string $field): string
    {
        if (str_starts_with($field, 'param_pdv_')) {
            return 'pdv';
        }

        if (str_starts_with($field, 'param_geral_')) {
            return 'geral';
        }

        return 'fiscal';
    }

    /**
     * @return array<string, array{label: string, default: int|float|string|null, type: string, decimals?: int}>
     */
    public static function impostoTextFields(): array
    {
        return [
            'param_imp_observacao' => [
                'label' => 'Observação — Consulte seu contador',
                'default' => '',
                'type' => 'text',
            ],
            'param_imp_cclass_trib_arquivo' => [
                'label' => 'Arquivo Classificação Tributária IVA',
                'default' => '',
                'type' => 'string',
            ],
            'param_imp_cclass_trib_arquivo_nome' => [
                'label' => 'Nome Classificação Tributária IVA',
                'default' => '',
                'type' => 'string',
            ],
            'param_imp_cclass_trib_importado_em' => [
                'label' => 'Importado em — Classificação Tributária IVA',
                'default' => '',
                'type' => 'string',
            ],
            'param_imp_ipbtax_arquivo' => [
                'label' => 'Arquivo IPBTAX',
                'default' => '',
                'type' => 'string',
            ],
            'param_imp_ipbtax_arquivo_nome' => [
                'label' => 'Nome IPBTAX',
                'default' => '',
                'type' => 'string',
            ],
            'param_imp_ipbtax_importado_em' => [
                'label' => 'Importado em — IPBTAX',
                'default' => '',
                'type' => 'string',
            ],
            'param_imp_ibpt_token' => [
                'label' => 'Token API IBPT (De Olho no Imposto)',
                'default' => '',
                // TEXT: evita estourar o limite de row size do InnoDB em unitec_empresas.
                'type' => 'text',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultFormValues(): array
    {
        $defaults = [];

        foreach (self::numericFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::permissionFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::moduleEnableFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::impostoFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::impostoTextFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::difalFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::difalBooleanFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::pixFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::pixBooleanFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::boletoFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::boletoBooleanFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::apiServicosFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::apiServicosBooleanFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::acessoRemotoFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::acessoRemotoBooleanFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::cloudflareAcessoFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::licencaApiFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::licencaApiBooleanFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::whatsAppFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::whatsAppBooleanFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::portalContadorFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::portalContadorBooleanFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::mercadoLivreFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::mercadoLivreBooleanFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::sistemaFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::sistemaBooleanFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::expedicaoBooleanFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::expedicaoFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        return $defaults;
    }

    /**
     * @return list<string>
     */
    public static function allFieldNames(): array
    {
        return array_keys(self::defaultFormValues());
    }

    /**
     * @return array<string, list<string>>
     */
    public static function numericColumnsByGroup(): array
    {
        // 3 colunas equilibradas (mesmo padrão compacto label + input).
        $col1 = [
            'param_cod_caixa_geral',
            'param_empresa_padrao_relatorios',
            'param_ultimo_nsu',
            'param_nfe_serie',
            'param_desconto_maximo',
            'param_acrescimo_maximo',
            'param_carencia_juros',
            'param_juros_diario_pct',
        ];

        $col2 = [
            'param_plano_venda',
            'param_plano_compra',
            'param_plano_devolucao',
            'param_plano_boleto',
            'param_plano_taxa_cartao',
            'param_plano_sangria',
            'param_plano_abertura_caixa',
            'param_plano_ficha_cliente',
        ];

        $col3 = [
            'param_plano_transferencia_credito',
            'param_plano_transferencia_debito',
            'param_cod_dinheiro_fpg',
            'param_pdv_modelo_balanca',
            'param_pdv_carga_intervalo_min',
            'param_lucro_padrao',
            'param_meta_vendas_mensal',
            'param_pdv_marquee_texto',
        ];

        return [
            'col1' => $col1,
            'col2' => $col2,
            'col3' => $col3,
        ];
    }

    /**
     * @return array<string, array{label: string, default: bool}>
     */
    public static function expedicaoBooleanFields(): array
    {
        return [
            'param_expedicao_ativar' => ['label' => 'Ativar Expedição', 'default' => false],
            'param_expedicao_pedir_quantidade' => ['label' => 'Pedir Quantidade', 'default' => false],
            'param_expedicao_origem_pdv' => ['label' => 'Gerar expedição — PDV', 'default' => true],
            'param_expedicao_origem_monitor' => ['label' => 'Gerar expedição — Monitor / Força de Vendas', 'default' => true],
            'param_expedicao_origem_vi' => ['label' => 'Gerar expedição — Vendas Internas', 'default' => true],
            'param_expedicao_origem_erp' => ['label' => 'Gerar expedição — ERP / Retaguarda', 'default' => true],
        ];
    }

    /**
     * @return array<string, array{label: string, default: int|float|string|null, type: string, decimals?: int}>
     */
    public static function expedicaoFields(): array
    {
        return [
            'param_expedicao_max_pedidos_controle' => [
                'label' => 'Qtd. máxima de pedidos no Controle',
                'default' => 5,
                'type' => 'integer',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function parametrosSubTabs(): array
    {
        return [
            'permissoes' => 'Permissões',
            'expedicao' => 'Expedição',
            'imposto' => 'Imposto Padrão - Consulte seu contador',
            'difal' => 'DIFAL',
            'pix' => 'API PIX',
            'boleto' => 'API Boleto',
            'api_servicos' => 'API de Serviços',
            'whatsapp' => 'WhatsApp',
            'email' => 'E-mail',
            'portal_contador' => 'Portal do Contador',
            'mercado_livre' => 'Mercado Livre',
            'estoques' => 'Cadastro de Estoque',
            'sistema' => 'Atualização e Backup',
        ];
    }
}