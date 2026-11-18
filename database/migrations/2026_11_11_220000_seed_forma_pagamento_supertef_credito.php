<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forma única SUPERTEF CREDITO (à vista + parcelado via campo de parcelas no PDV).
 * Parcelas da adquirente ≠ carnê/CR próprio — movimento caixa, sem canhoto manual.
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
                $q->where('descricao', 'SUPERTEF CREDITO')
                    ->orWhere('descricao', 'SUPERTEF CRÉDITO');
            })
            ->exists();

        if ($exists) {
            return;
        }

        $maxCodigo = (int) DB::table('formas_pagamento')->max('codigo');

        $row = [
            'codigo' => $maxCodigo > 0 ? $maxCodigo + 1 : 91,
            'descricao' => 'SUPERTEF CREDITO',
            'tipo' => 'cartao_credito',
            'ativo' => true,
            'aparece_venda' => true,
            'usa_tef' => false,
            'usa_super_tef' => true,
            'gerar_qrcode_pdv' => false,
            'aparece_contas_receber' => false,
            'tipo_movimento' => 'caixa',
            'modo_prazo' => 'financeiro',
            'max_parcelas' => 12,
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
            ->whereIn('descricao', ['SUPERTEF CREDITO', 'SUPERTEF CRÉDITO'])
            ->delete();
    }
};
