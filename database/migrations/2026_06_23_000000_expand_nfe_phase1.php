<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nfes', function (Blueprint $table): void {
            $table->string('modelo', 10)->default('55')->after('serie');
            $table->time('hora_emissao')->nullable()->after('data_emissao');
            $table->time('hora_saida')->nullable()->after('data_saida');
            $table->unsignedBigInteger('transportadora_id')->nullable()->after('cliente_id');
            $table->string('situacao', 1)->default('1')->after('status');
            $table->string('finalidade', 1)->default('1')->after('situacao');
            $table->string('movimento', 1)->default('1')->after('finalidade');
            $table->string('consumidor_final', 1)->default('0')->after('movimento');
            $table->string('tipo_emissao', 1)->nullable()->after('consumidor_final');
            $table->unsignedInteger('cfop')->nullable()->after('tipo_emissao');
            $table->string('npedido', 20)->nullable()->after('cfop');
            $table->string('chave_nfe_referenciada', 44)->nullable()->after('chave');
            $table->string('cnf', 20)->nullable()->after('protocolo');
            $table->longText('xml')->nullable()->after('cnf');
            $table->longText('xml_cancelamento')->nullable()->after('xml');
            $table->text('obs_fisco')->nullable()->after('xml_cancelamento');
            $table->text('obs_contribuinte')->nullable()->after('obs_fisco');
            $table->decimal('subtotal', 15, 2)->default(0)->after('total');
            $table->decimal('desconto', 15, 2)->default(0)->after('subtotal');
            $table->decimal('frete', 15, 2)->default(0)->after('desconto');
            $table->decimal('seguro', 15, 2)->default(0)->after('frete');
            $table->decimal('despesas', 15, 2)->default(0)->after('seguro');
            $table->decimal('outros', 15, 2)->default(0)->after('despesas');
            $table->decimal('troco', 15, 2)->default(0)->after('outros');
            $table->decimal('base_icms', 15, 2)->default(0)->after('troco');
            $table->decimal('total_icms', 15, 2)->default(0)->after('base_icms');
            $table->decimal('base_icms_st', 15, 2)->default(0)->after('total_icms');
            $table->decimal('valor_icms_st', 15, 2)->default(0)->after('base_icms_st');
            $table->decimal('base_ipi', 15, 2)->default(0)->after('valor_icms_st');
            $table->decimal('total_ipi', 15, 2)->default(0)->after('base_ipi');
            $table->decimal('base_icms_pis', 15, 2)->default(0)->after('total_ipi');
            $table->decimal('total_icms_pis', 15, 2)->default(0)->after('base_icms_pis');
            $table->decimal('base_icms_cofins', 15, 2)->default(0)->after('total_icms_pis');
            $table->decimal('total_icms_cofins', 15, 2)->default(0)->after('base_icms_cofins');
            $table->decimal('total_desoneracao', 15, 2)->default(0)->after('total_icms_cofins');
            $table->decimal('vfcp', 15, 2)->default(0)->after('total_desoneracao');
            $table->decimal('vfcp_uf_dest', 15, 2)->default(0)->after('vfcp');
            $table->decimal('vicms_uf_dest', 15, 2)->default(0)->after('vfcp_uf_dest');
            $table->decimal('vicms_uf_remet', 15, 2)->default(0)->after('vicms_uf_dest');
            $table->decimal('trib_mun', 15, 2)->default(0)->after('vicms_uf_remet');
            $table->decimal('trib_est', 15, 2)->default(0)->after('trib_mun');
            $table->decimal('trib_fed', 15, 2)->default(0)->after('trib_est');
            $table->decimal('trib_imp', 15, 2)->default(0)->after('trib_fed');
            $table->decimal('total_itens', 15, 4)->default(0)->after('trib_imp');
            $table->string('tipo_frete', 30)->nullable()->after('total_itens');
            $table->string('especie', 40)->nullable()->after('tipo_frete');
            $table->string('marca', 40)->nullable()->after('especie');
            $table->string('nvol', 40)->nullable()->after('marca');
            $table->unsignedInteger('qvol')->nullable()->after('nvol');
            $table->decimal('peso_b', 15, 3)->nullable()->after('qvol');
            $table->decimal('peso_l', 15, 3)->nullable()->after('peso_b');
            $table->string('placa', 7)->nullable()->after('peso_l');
            $table->string('uf_placa', 2)->nullable()->after('placa');
            $table->string('rntc', 8)->nullable()->after('uf_placa');
            $table->string('motivo_contingencia', 100)->nullable()->after('rntc');
            $table->smallInteger('ind_pag')->nullable()->after('motivo_contingencia');
            $table->smallInteger('tp_pag')->nullable()->after('ind_pag');
            $table->string('forma_pgto', 20)->nullable()->after('tp_pag');
            $table->string('meio_pgto', 20)->nullable()->after('forma_pgto');

            $table->index('situacao');
        });

        DB::table('nfes')->whereNull('situacao')->orWhere('situacao', '')->update([
            'situacao' => '1',
        ]);

        Schema::table('nfe_itens', function (Blueprint $table): void {
            $table->unsignedSmallInteger('item')->default(1)->after('nfe_id');
            $table->string('cod_barra', 14)->nullable()->after('product_id');
            $table->string('ncm', 10)->nullable()->after('cod_barra');
            $table->string('cfop', 4)->nullable()->after('ncm');
            $table->string('cst', 3)->nullable()->after('cfop');
            $table->string('csosn', 4)->nullable()->after('cst');
            $table->string('cest', 8)->nullable()->after('csosn');
            $table->string('unidade', 3)->nullable()->after('cest');
            $table->string('situacao', 1)->default('1')->after('total');
            $table->decimal('desconto', 15, 2)->default(0)->after('situacao');
            $table->decimal('frete', 15, 2)->default(0)->after('desconto');
            $table->decimal('seguro', 15, 2)->default(0)->after('frete');
            $table->decimal('despesas', 15, 2)->default(0)->after('seguro');
            $table->decimal('outros', 15, 2)->default(0)->after('despesas');
            $table->decimal('base_icms', 15, 2)->default(0)->after('outros');
            $table->decimal('aliq_icms', 15, 2)->default(0)->after('base_icms');
            $table->decimal('valor_icms', 15, 2)->default(0)->after('aliq_icms');
            $table->decimal('base_icms_st', 15, 2)->default(0)->after('valor_icms');
            $table->decimal('aliq_icms_st', 15, 2)->default(0)->after('base_icms_st');
            $table->decimal('valor_icms_st', 15, 2)->default(0)->after('aliq_icms_st');
            $table->string('cst_ipi', 2)->nullable()->after('valor_icms_st');
            $table->decimal('base_ipi', 15, 2)->default(0)->after('cst_ipi');
            $table->decimal('aliq_ipi', 15, 2)->default(0)->after('base_ipi');
            $table->decimal('valor_ipi', 15, 2)->default(0)->after('aliq_ipi');
            $table->string('cst_pis', 2)->nullable()->after('valor_ipi');
            $table->decimal('base_pis_icms', 15, 2)->default(0)->after('cst_pis');
            $table->decimal('aliq_pis_icms', 15, 2)->default(0)->after('base_pis_icms');
            $table->decimal('valor_pis_icms', 15, 2)->default(0)->after('aliq_pis_icms');
            $table->string('cst_cofins', 2)->nullable()->after('valor_pis_icms');
            $table->decimal('base_cofins_icms', 15, 2)->default(0)->after('cst_cofins');
            $table->decimal('aliq_cofins_icms', 15, 2)->default(0)->after('base_cofins_icms');
            $table->decimal('valor_cofins_icms', 15, 2)->default(0)->after('aliq_cofins_icms');
            $table->decimal('trib_mun', 15, 2)->default(0)->after('valor_cofins_icms');
            $table->decimal('trib_est', 15, 2)->default(0)->after('trib_mun');
            $table->decimal('trib_fed', 15, 2)->default(0)->after('trib_est');
            $table->decimal('trib_imp', 15, 2)->default(0)->after('trib_fed');
            $table->decimal('vbcufdest', 15, 2)->default(0)->after('trib_imp');
            $table->decimal('vicmsufdest', 15, 2)->default(0)->after('vbcufdest');
            $table->decimal('vicmsufremet', 15, 2)->default(0)->after('vicmsufdest');
            $table->decimal('vfcp', 15, 2)->default(0)->after('vicmsufremet');
            $table->string('descricao_complementar', 300)->nullable()->after('descricao');
            $table->string('info_adicionais', 100)->nullable()->after('descricao_complementar');
        });

        Schema::create('nfe_faturas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nfe_id')->constrained('nfes')->cascadeOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('numero', 10);
            $table->date('data_vencimento');
            $table->decimal('valor', 15, 2)->default(0);
            $table->string('path_pdf_boleto', 500)->nullable();
            $table->timestamps();

            $table->index(['nfe_id', 'numero']);
        });

        Schema::create('nfe_referencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nfe_id')->constrained('nfes')->cascadeOnDelete();
            $table->string('referencia', 44);
            $table->timestamps();

            $table->index('nfe_id');
        });

        Schema::create('vendas_parametros', function (Blueprint $table): void {
            $table->foreignId('empresa_id')->primary()->constrained('empresas')->cascadeOnDelete();
            $table->string('uf', 2)->nullable();
            $table->unsignedSmallInteger('ambiente')->default(1);
            $table->unsignedSmallInteger('versao_nfe')->nullable();
            $table->unsignedSmallInteger('forma_emissao')->nullable();
            $table->unsignedSmallInteger('tipo_emissao')->nullable();
            $table->string('caminho_certificado', 500)->nullable();
            $table->text('senha_certificado')->nullable();
            $table->string('numero_serie_certificado', 100)->nullable();
            $table->string('crypt_lib', 20)->nullable();
            $table->string('http_lib', 20)->nullable();
            $table->string('xml_sign', 20)->nullable();
            $table->unsignedSmallInteger('ssl_tipo')->nullable();
            $table->unsignedSmallInteger('aguardar')->nullable();
            $table->unsignedSmallInteger('tentativas')->nullable();
            $table->unsignedSmallInteger('intervalo')->nullable();
            $table->string('ajustar_auto', 1)->nullable();
            $table->string('proxy_host', 100)->nullable();
            $table->string('proxy_porta', 50)->nullable();
            $table->string('proxy_usuario', 50)->nullable();
            $table->text('proxy_senha')->nullable();
            $table->string('path_salvar_nfe', 500)->nullable();
            $table->string('path_schemas_nfe', 500)->nullable();
            $table->string('path_enviada_nfe', 500)->nullable();
            $table->string('path_can_nfe', 500)->nullable();
            $table->string('path_inuti_nfe', 500)->nullable();
            $table->string('path_evento_nfe', 500)->nullable();
            $table->string('path_pdf_nfe', 500)->nullable();
            $table->string('logomarca', 500)->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->string('serie', 10)->nullable();
            $table->unsignedInteger('serie_nfe')->nullable();
            $table->string('id_token', 40)->nullable();
            $table->string('token', 40)->nullable();
            $table->unsignedSmallInteger('versao_qrcode')->nullable();
            $table->string('email_host', 100)->nullable();
            $table->string('email_porta', 10)->nullable();
            $table->string('email_user', 100)->nullable();
            $table->text('email_senha')->nullable();
            $table->string('email_assunto', 100)->nullable();
            $table->string('email_ssl', 1)->nullable();
            $table->string('email_tls', 1)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas_parametros');
        Schema::dropIfExists('nfe_referencias');
        Schema::dropIfExists('nfe_faturas');

        Schema::table('nfe_itens', function (Blueprint $table): void {
            $table->dropColumn([
                'item', 'cod_barra', 'ncm', 'cfop', 'cst', 'csosn', 'cest', 'unidade', 'situacao',
                'desconto', 'frete', 'seguro', 'despesas', 'outros',
                'base_icms', 'aliq_icms', 'valor_icms', 'base_icms_st', 'aliq_icms_st', 'valor_icms_st',
                'cst_ipi', 'base_ipi', 'aliq_ipi', 'valor_ipi',
                'cst_pis', 'base_pis_icms', 'aliq_pis_icms', 'valor_pis_icms',
                'cst_cofins', 'base_cofins_icms', 'aliq_cofins_icms', 'valor_cofins_icms',
                'trib_mun', 'trib_est', 'trib_fed', 'trib_imp',
                'vbcufdest', 'vicmsufdest', 'vicmsufremet', 'vfcp',
                'descricao_complementar', 'info_adicionais',
            ]);
        });

        Schema::table('nfes', function (Blueprint $table): void {
            $table->dropColumn([
                'modelo', 'hora_emissao', 'hora_saida', 'transportadora_id', 'situacao',
                'finalidade', 'movimento', 'consumidor_final', 'tipo_emissao', 'cfop', 'npedido',
                'chave_nfe_referenciada', 'cnf', 'xml', 'xml_cancelamento', 'obs_fisco', 'obs_contribuinte',
                'subtotal', 'desconto', 'frete', 'seguro', 'despesas', 'outros', 'troco',
                'base_icms', 'total_icms', 'base_icms_st', 'valor_icms_st', 'base_ipi', 'total_ipi',
                'base_icms_pis', 'total_icms_pis', 'base_icms_cofins', 'total_icms_cofins', 'total_desoneracao',
                'vfcp', 'vfcp_uf_dest', 'vicms_uf_dest', 'vicms_uf_remet',
                'trib_mun', 'trib_est', 'trib_fed', 'trib_imp', 'total_itens',
                'tipo_frete', 'especie', 'marca', 'nvol', 'qvol', 'peso_b', 'peso_l', 'placa', 'uf_placa', 'rntc',
                'motivo_contingencia', 'ind_pag', 'tp_pag', 'forma_pgto', 'meio_pgto',
            ]);
        });
    }
};
