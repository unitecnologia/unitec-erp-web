<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forma de pagamento SuperTEF Débito (separada do POS manual).
 * Visibilidade no PDV ainda depende do gate SuperTefPdvGate + terminal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('formas_pagamento')) {
            return;
        }

        $exists = DB::table('formas_pagamento')
            ->where(function ($q): void {
                $q->where('descricao', 'SUPERTEF DEBITO')
                    ->orWhere('descricao', 'SUPERTEF DÉBITO');
            })
            ->exists();

        if ($exists) {
            return;
        }

        $maxCodigo = (int) DB::table('formas_pagamento')->max('codigo');

        $row = [
            'codigo' => $maxCodigo > 0 ? $maxCodigo + 1 : 90,
            'descricao' => 'SUPERTEF DEBITO',
            'tipo' => 'cartao_debito',
            'ativo' => true,
            'aparece_venda' => true,
            'usa_tef' => false,
            'usa_super_tef' => true,
            'gerar_qrcode_pdv' => false,
            'aparece_contas_receber' => false,
            'tipo_movimento' => 'caixa',
            'max_parcelas' => 1,
            'prazo_cartao' => 0,
            'intervalo_parcelas' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('formas_pagamento', 'atalho')) {
            $row['atalho'] = '';
        }

        DB::table('formas_pagamento')->insert($row);
    }

    public function down(): void
    {
        if (! Schema::hasTable('formas_pagamento')) {
            return;
        }

        DB::table('formas_pagamento')
            ->where('usa_super_tef', true)
            ->whereIn('descricao', ['SUPERTEF DEBITO', 'SUPERTEF DÉBITO'])
            ->delete();
    }
};
