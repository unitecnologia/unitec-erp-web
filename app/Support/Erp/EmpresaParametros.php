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
                'label' => 'Plano de Contas Transferência (Crédito)',
                'default' => 3,
                'type' => 'integer',
            ],
            'param_plano_transferencia_debito' => [
                'label' => 'Plano de Contas Transferência (Débito)',
                'default' => 4,
                'type' => 'integer',
            ],
            'param_empresa_padrao_relatorios' => [
                'label' => 'Empresa Padrão Relatórios',
                'default' => 1,
                'type' => 'integer',
            ],
            'param_prazo_max_nota_cliente' => [
                'label' => 'Prazo Máximo da Nota Cliente',
                'default' => '1.00',
                'type' => 'decimal',
                'decimals' => 2,
            ],
            'param_plano_ficha_cliente' => [
                'label' => 'Cód. Plano de Conta Ficha Cliente',
                'default' => 10,
                'type' => 'integer',
            ],
            'param_ultimo_nsu' => [
                'label' => 'Último NSU',
                'default' => '0000000000',
                'type' => 'string',
            ],
            'param_tempo_bloqueio_pdv_min' => [
                'label' => 'Tempo para bloqueio do PDV(minutos)',
                'default' => null,
                'type' => 'integer',
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
                'label' => 'Modelo Etiqueta Balança (1-4)',
                'default' => 4,
                'type' => 'integer',
            ],
            'param_plano_abertura_caixa' => [
                'label' => 'Plano de Contas Abertura de Caixa',
                'default' => 14,
                'type' => 'integer',
            ],
            'param_cod_dinheiro_fpg' => [
                'label' => 'Código Padrão Dinheiro - FPG',
                'default' => 1,
                'type' => 'integer',
            ],
            'param_nfe_num_inicial' => [
                'label' => 'Nº Inicial NFe',
                'default' => 1,
                'type' => 'integer',
            ],
            'param_nfe_serie' => [
                'label' => 'NFe Série',
                'default' => 1,
                'type' => 'integer',
            ],
            'param_plano_sangria' => [
                'label' => 'Plano de Contas Sangria',
                'default' => 11,
                'type' => 'integer',
            ],
            'param_plano_venda' => [
                'label' => 'Plano de Contas Venda',
                'default' => 2,
                'type' => 'integer',
            ],
            'param_plano_taxa_cartao' => [
                'label' => 'Plano de Contas Taxa Cartão',
                'default' => 8,
                'type' => 'integer',
            ],
            'param_plano_devolucao' => [
                'label' => 'Plano de Contas Devolução',
                'default' => 9,
                'type' => 'integer',
            ],
            'param_plano_compra' => [
                'label' => 'Plano de Contas de Compra',
                'default' => 15,
                'type' => 'integer',
            ],
            'param_plano_boleto' => [
                'label' => 'Plano de Conta Boleto',
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
            'param_pdv_exibir_estoque_negativo' => ['label' => 'Exibir Produto c/ Estoque Negativo', 'default' => true],
            'param_pdv_checar_limite_cliente' => ['label' => 'Checar Limite de Cliente', 'default' => false],
            'param_pdv_pedido_duas_vias' => ['label' => 'Pedido em Duas Vias (PDV)', 'default' => false],
            'param_pdv_bloquear_preco' => ['label' => 'Bloquear Preço no PDV', 'default' => false],
            'param_pdv_permitir_desconto_item' => ['label' => 'Permitir Desconto Item (PDV)', 'default' => true],
            'param_pdv_habilitar_desconto' => ['label' => 'Habilitar Desconto no PDV', 'default' => false],
            'param_pdv_bloquear_inatividade' => ['label' => 'Bloquear PDV por inatividade', 'default' => null, 'tri' => true],
            'param_pdv_exibir_f3_vendedor' => ['label' => 'Exibir F3 Vendedor no PDV', 'default' => false],
            'param_pdv_exibir_f4_busca_avancada' => ['label' => 'Exibir F4 Busca Avançada no PDV', 'default' => false],
            'param_pdv_ativar_som' => ['label' => 'Ativar Som no PDV (bip ao incluir item)', 'default' => false],

            'param_geral_transmitir_cartao_auto' => ['label' => 'Transmitir Cartão Auto', 'default' => null, 'tri' => true],
            'param_geral_informar_gtin' => ['label' => 'Informar GTIN', 'default' => false],
            'param_geral_usar_pdv_retaguarda' => ['label' => 'Usar PDV no Retaguarda', 'default' => true],
            'param_geral_desconto_prod_promocao' => ['label' => 'Dar Desconto Prod. Promoção', 'default' => false],
            'param_geral_bloquear_cpf_repetido' => ['label' => 'Bloquear Cadastro de CPF repetido', 'default' => null, 'tri' => true],
            'param_geral_ratear_preco_custo_xml' => ['label' => 'Ratear Preço de Custo (Compra XML)', 'default' => true],
            'param_geral_bloquear_estoque_negativo' => ['label' => 'Bloquear Estoque Negativo', 'default' => false],
            'param_geral_usar_transportadora' => ['label' => 'Usar Transportadora', 'default' => false],
            'param_geral_ocultar_saldo_livro_caixa' => ['label' => 'Ocultar Saldo Anterior no Livro Caixa', 'default' => null, 'tri' => true],
            'param_geral_usar_smtp_proprio' => ['label' => 'Usar Servidor SMTP próprio', 'default' => null, 'tri' => true],
            'param_geral_cadastrar_produtos_auto' => ['label' => 'Cadastrar Produtos Auto', 'default' => false],
            'param_geral_lancar_cartao_caixa' => ['label' => 'Lançar Cartão no Caixa', 'default' => true],
            'param_geral_rateio_pessoa_pdv' => ['label' => 'Mostra Rateio por pessoa no PDV', 'default' => true],

            'param_fiscal_perguntar_segunda_via_nfce' => ['label' => 'Perguntar - Imprimir Segunda via NFC-e', 'default' => false],
            'param_fiscal_enviar_email_nfe' => ['label' => 'Enviar Email NFe', 'default' => true],
            'param_fiscal_usar_credito_icms' => ['label' => 'Usar Crédito ICMS', 'default' => true],
            'param_fiscal_usar_nfe_num_inicial' => ['label' => 'Usar Nº Inicial da NFe', 'default' => null, 'tri' => true],
            'param_fiscal_puxar_cfop_produto' => ['label' => 'Puxar CFOP do Produto', 'default' => false],
            'param_fiscal_recolhe_fcp' => ['label' => 'Empresa Recolhe FCP', 'default' => true],
            'param_fiscal_resp_tecnico_xml' => ['label' => 'Informar Respons. Técnico XML NFe/NFCe', 'default' => true],
            'param_fiscal_abrir_whatsapp_inicio' => ['label' => 'Abrir Whatsapp Server no início', 'default' => null, 'tri' => true],
            'param_fiscal_imposto_custo_xml' => ['label' => 'Calcula Impostos no preço de Custo ao importar XML de Compra.', 'default' => true],
            'param_fiscal_bloquear_cancelamento_doc' => ['label' => 'Bloquear Cancelamento Venda com Documento Fiscal Emitido', 'default' => true],
        ];
    }

    /**
     * @return array<string, array{label: string, default: int|float|string|null, type: string, decimals?: int}>
     */
    public static function impostoFields(): array
    {
        return [
            'param_imp_csosn' => ['label' => 'CSOSN Padrão', 'default' => '102', 'type' => 'string'],
            'param_imp_icms_cst' => ['label' => 'CST ICMS Padrão', 'default' => '00', 'type' => 'string'],
            'param_imp_icms_aliquota' => ['label' => 'Alíquota ICMS (%)', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_pis_cst' => ['label' => 'CST PIS Padrão', 'default' => '01', 'type' => 'string'],
            'param_imp_pis_aliquota' => ['label' => 'Alíquota PIS (%)', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_cofins_cst' => ['label' => 'CST COFINS Padrão', 'default' => '01', 'type' => 'string'],
            'param_imp_cofins_aliquota' => ['label' => 'Alíquota COFINS (%)', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_ipi_cst' => ['label' => 'CST IPI Padrão', 'default' => '99', 'type' => 'string'],
            'param_imp_ipi_aliquota' => ['label' => 'Alíquota IPI (%)', 'default' => '0.00', 'type' => 'decimal', 'decimals' => 2],
            'param_imp_cfop_venda' => ['label' => 'CFOP Venda Padrão', 'default' => '5102', 'type' => 'string'],
            'param_imp_cfop_compra' => ['label' => 'CFOP Compra Padrão', 'default' => '1102', 'type' => 'string'],
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
            'param_api_servicos_habilitar' => ['label' => 'Habilitar API de Serviços', 'default' => false],
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
            'param_whatsapp_enviar_nfe' => ['label' => 'Permitir envio de NF-e (desabilitado por padrão; use e-mail)', 'default' => false],
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
        ];
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

        foreach (self::sistemaFields() as $field => $meta) {
            $defaults[$field] = $meta['default'];
        }

        foreach (self::sistemaBooleanFields() as $field => $meta) {
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
     * @return array<string, string>
     */
    public static function numericColumnsByGroup(): array
    {
        $col1 = [
            'param_cod_caixa_geral',
            'param_plano_transferencia_credito',
            'param_plano_transferencia_debito',
            'param_empresa_padrao_relatorios',
            'param_prazo_max_nota_cliente',
            'param_plano_ficha_cliente',
            'param_ultimo_nsu',
            'param_tempo_bloqueio_pdv_min',
        ];

        $col2 = [
            'param_desconto_maximo',
            'param_acrescimo_maximo',
            'param_pdv_modelo_balanca',
            'param_plano_abertura_caixa',
            'param_cod_dinheiro_fpg',
            'param_nfe_num_inicial',
            'param_nfe_serie',
            'param_plano_sangria',
            'param_plano_venda',
        ];

        $col3 = [
            'param_plano_taxa_cartao',
            'param_plano_devolucao',
            'param_plano_compra',
            'param_plano_boleto',
            'param_carencia_juros',
            'param_juros_diario_pct',
            'param_lucro_padrao',
        ];

        return [
            'col1' => $col1,
            'col2' => $col2,
            'col3' => $col3,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function parametrosSubTabs(): array
    {
        return [
            'permissoes' => 'Permissões',
            'imposto' => 'Imposto Padrão - Consulte seu contador',
            'difal' => 'DIFAL',
            'pix' => 'API PIX',
            'boleto' => 'API Boleto',
            'api_servicos' => 'API de Serviços',
            'whatsapp' => 'WhatsApp',
            'portal_contador' => 'Portal do Contador',
        ];
    }
}
