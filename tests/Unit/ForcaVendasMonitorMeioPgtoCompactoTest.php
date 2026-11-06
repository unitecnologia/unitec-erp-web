<?php

namespace Tests\Unit;

use App\Filament\Resources\ForcaVendasMonitorResource;
use App\Models\ForcaVendasOrder;
use Tests\TestCase;

class ForcaVendasMonitorMeioPgtoCompactoTest extends TestCase
{
    public function test_pagamento_unico_sem_mais(): void
    {
        $order = $this->orderComPayload([
            'forma_pagamento' => 'DINHEIRO',
        ]);

        $exibicao = ForcaVendasMonitorResource::meioPagamentoExibicao($order);

        $this->assertSame('Dinheiro', $exibicao['visivel']);
        $this->assertSame('DINHEIRO', $exibicao['tooltip']);
    }

    public function test_misto_legado_cabecalho_mostra_mais_sem_inventar_valores(): void
    {
        $order = $this->orderComPayload([
            'forma_pagamento' => 'DINHEIRO / PIX / POS DÉBITO',
        ]);

        $exibicao = ForcaVendasMonitorResource::meioPagamentoExibicao($order);

        $this->assertSame('Dinheiro +', $exibicao['visivel']);
        $this->assertSame("DINHEIRO\nPIX\nPOS DÉBITO", $exibicao['tooltip']);
    }

    public function test_misto_pagamentos_com_valores_no_tooltip(): void
    {
        $order = $this->orderComPayload([
            'forma_pagamento' => 'DINHEIRO / PIX',
            'pagamentos' => [
                [
                    'descricao' => 'DINHEIRO',
                    'forma_pagamento' => 'DINHEIRO',
                    'valor' => 100,
                ],
                [
                    'descricao' => 'PIX',
                    'forma_pagamento' => 'PIX',
                    'valor' => 50,
                ],
            ],
        ]);

        $exibicao = ForcaVendasMonitorResource::meioPagamentoExibicao($order);

        $this->assertSame('Dinheiro +', $exibicao['visivel']);
        $this->assertSame("DINHEIRO — R$ 100,00\nPIX — R$ 50,00", $exibicao['tooltip']);
    }

    public function test_label_completo_mantem_barra_para_compatibilidade(): void
    {
        $order = $this->orderComPayload([
            'forma_pagamento' => 'DINHEIRO / PIX',
            'pagamentos' => [
                ['descricao' => 'DINHEIRO', 'valor' => 10],
                ['descricao' => 'PIX', 'valor' => 5],
            ],
        ]);

        $this->assertSame(
            'Dinheiro / PIX',
            ForcaVendasMonitorResource::meioPagamentoLabel($order),
        );
    }

    public function test_compacto_legado_aceita_barra_e_mais(): void
    {
        $this->assertSame(
            'Dinheiro +',
            ForcaVendasMonitorResource::meioPagamentoLabelCompacto('Dinheiro / PIX'),
        );
        $this->assertSame(
            'Dinheiro +',
            ForcaVendasMonitorResource::meioPagamentoLabelCompacto('Dinheiro + PIX'),
        );
        $this->assertSame(
            'PIX',
            ForcaVendasMonitorResource::meioPagamentoLabelCompacto('PIX'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function orderComPayload(array $payload): ForcaVendasOrder
    {
        $order = new ForcaVendasOrder;
        $order->forceFill(['payload' => $payload]);

        return $order;
    }
}
