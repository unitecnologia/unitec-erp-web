<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expande "vendedores" para uma ficha completa de Colaborador
 * (dados pessoais, endereço, dados trabalhistas, setor de vendas/serviços).
 * Mantém comissao_av / comissao_ap, usadas pelo relatório de comissões.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendedores', function (Blueprint $table): void {
            // Identificação / vínculo
            $this->addIfMissing($table, 'empresa_id', fn () => $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->nullOnDelete());
            $this->addIfMissing($table, 'cargo', fn () => $table->string('cargo', 80)->nullable()->after('nome'));

            // Documentos pessoais
            $this->addIfMissing($table, 'cpf', fn () => $table->string('cpf', 20)->nullable());
            $this->addIfMissing($table, 'rg', fn () => $table->string('rg', 30)->nullable());
            $this->addIfMissing($table, 'pis_pasep', fn () => $table->string('pis_pasep', 30)->nullable());
            $this->addIfMissing($table, 'data_nascimento', fn () => $table->date('data_nascimento')->nullable());

            // Endereço
            $this->addIfMissing($table, 'cep', fn () => $table->string('cep', 12)->nullable());
            $this->addIfMissing($table, 'logradouro', fn () => $table->string('logradouro', 40)->nullable());
            $this->addIfMissing($table, 'endereco', fn () => $table->string('endereco', 150)->nullable());
            $this->addIfMissing($table, 'numero', fn () => $table->string('numero', 12)->nullable());
            $this->addIfMissing($table, 'bairro', fn () => $table->string('bairro', 100)->nullable());
            $this->addIfMissing($table, 'complemento', fn () => $table->string('complemento', 100)->nullable());
            $this->addIfMissing($table, 'cidade_codigo', fn () => $table->string('cidade_codigo', 12)->nullable());
            $this->addIfMissing($table, 'cidade_nome', fn () => $table->string('cidade_nome', 120)->nullable());
            $this->addIfMissing($table, 'uf', fn () => $table->string('uf', 2)->nullable());

            // Contato
            $this->addIfMissing($table, 'telefone', fn () => $table->string('telefone', 20)->nullable());
            $this->addIfMissing($table, 'whatsapp', fn () => $table->string('whatsapp', 20)->nullable());
            $this->addIfMissing($table, 'email', fn () => $table->string('email', 120)->nullable());

            // Dados trabalhistas
            $this->addIfMissing($table, 'ctps', fn () => $table->string('ctps', 30)->nullable());
            $this->addIfMissing($table, 'admissao', fn () => $table->date('admissao')->nullable());
            $this->addIfMissing($table, 'demissao', fn () => $table->date('demissao')->nullable());
            $this->addIfMissing($table, 'tipo_salario', fn () => $table->string('tipo_salario', 30)->nullable());
            $this->addIfMissing($table, 'salario', fn () => $table->decimal('salario', 15, 2)->default(0));
            $this->addIfMissing($table, 'inss', fn () => $table->string('inss', 30)->nullable());
            $this->addIfMissing($table, 'estoque', fn () => $table->string('estoque', 60)->nullable());
            $this->addIfMissing($table, 'usar_agendamento', fn () => $table->boolean('usar_agendamento')->default(false));

            // Setor de Vendas
            $this->addIfMissing($table, 'setor_vendas', fn () => $table->boolean('setor_vendas')->default(true));
            $this->addIfMissing($table, 'tabela_venda_id', fn () => $table->foreignId('tabela_venda_id')->nullable()->constrained('price_tables')->nullOnDelete());
            $this->addIfMissing($table, 'ganha_comissao_todas_vendas', fn () => $table->boolean('ganha_comissao_todas_vendas')->default(false));
            $this->addIfMissing($table, 'mobile_meta_venda', fn () => $table->decimal('mobile_meta_venda', 15, 2)->default(0));

            // Setor de Serviços
            $this->addIfMissing($table, 'setor_servicos', fn () => $table->boolean('setor_servicos')->default(false));
            $this->addIfMissing($table, 'comissao_servico', fn () => $table->decimal('comissao_servico', 5, 2)->default(0));
            $this->addIfMissing($table, 'ganha_comissao_todos_servicos', fn () => $table->boolean('ganha_comissao_todos_servicos')->default(false));

            // Funções
            $this->addIfMissing($table, 'efetua_venda', fn () => $table->boolean('efetua_venda')->default(true));
            $this->addIfMissing($table, 'motorista', fn () => $table->boolean('motorista')->default(false));
            $this->addIfMissing($table, 'ajudante', fn () => $table->boolean('ajudante')->default(false));

            // Observações
            $this->addIfMissing($table, 'observacoes', fn () => $table->text('observacoes')->nullable());
        });
    }

    public function down(): void
    {
        Schema::table('vendedores', function (Blueprint $table): void {
            foreach (['empresa_id', 'tabela_venda_id'] as $fk) {
                if (Schema::hasColumn('vendedores', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }

            $cols = [
                'cargo', 'cpf', 'rg', 'pis_pasep', 'data_nascimento',
                'cep', 'logradouro', 'endereco', 'numero', 'bairro', 'complemento',
                'cidade_codigo', 'cidade_nome', 'uf', 'telefone', 'whatsapp', 'email',
                'ctps', 'admissao', 'demissao', 'tipo_salario', 'salario', 'inss',
                'estoque', 'usar_agendamento', 'setor_vendas', 'ganha_comissao_todas_vendas',
                'mobile_meta_venda', 'setor_servicos', 'comissao_servico',
                'ganha_comissao_todos_servicos', 'efetua_venda', 'motorista', 'ajudante', 'observacoes',
            ];

            $existing = array_values(array_filter($cols, fn (string $c): bool => Schema::hasColumn('vendedores', $c)));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }

    private function addIfMissing(Blueprint $table, string $column, callable $callback): void
    {
        if (! Schema::hasColumn('vendedores', $column)) {
            $callback();
        }
    }
};
