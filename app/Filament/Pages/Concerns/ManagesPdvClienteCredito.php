<?php

namespace App\Filament\Pages\Concerns;

use App\Models\ClienteCreditoMovimentacao;
use App\Models\FormaPagamento;
use App\Models\PdvVenda;
use App\Support\Erp\ClienteCreditoService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait ManagesPdvClienteCredito
{
    public function getFinalizarCreditoClienteResumoProperty(): ?string
    {
        $saldo = $this->saldoCreditoClienteFinalizar();

        if ($saldo <= 0.009) {
            return null;
        }

        return ErpMoney::formatBr($saldo);
    }

    protected function sincronizarLinhaCreditoCliente(): void
    {
        $pagamentos = [];
        $jaTemLinha = false;

        foreach ($this->finalizarPagamentos as $row) {
            if (! empty($row['credito_cliente_virtual'])) {
                continue;
            }

            if (($row['tipo_movimento'] ?? '') === 'credito_cliente') {
                $row['credito_cliente_linha'] = true;
                $jaTemLinha = true;
            }

            $pagamentos[] = $row;
        }

        $saldo = $this->saldoCreditoClienteFinalizar();

        if (! $jaTemLinha && $saldo > 0.009) {
            $forma = FormaPagamento::query()
                ->where('ativo', true)
                ->where('tipo_movimento', 'credito_cliente')
                ->orderBy('codigo')
                ->first(['descricao', 'atalho', 'tipo', 'tipo_movimento']);

            if ($forma && trim((string) $forma->descricao) !== '') {
                $usados = [];

                foreach ($pagamentos as $pagamento) {
                    $atalho = mb_strtoupper(trim((string) ($pagamento['atalho'] ?? '')), 'UTF-8');

                    if ($atalho !== '') {
                        $usados[] = $atalho;
                    }
                }

                $descricao = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');

                $pagamentos[] = [
                    'forma' => $descricao,
                    'atalho' => $this->resolveAtalhoForma((string) $forma->atalho, $descricao, $usados),
                    'tipo' => (string) ($forma->tipo ?? ''),
                    'tipo_movimento' => 'credito_cliente',
                    'aparece_contas_receber' => false,
                    'max_parcelas' => 1,
                    'prazo_cartao' => 0,
                    'intervalo_parcelas' => 30,
                    'valor' => '0,00',
                    'credito_cliente_linha' => true,
                ];
            }
        }

        $this->finalizarPagamentos = array_values($pagamentos);
        $this->selectedPagamentoIndex = min(
            $this->selectedPagamentoIndex,
            max(0, count($this->finalizarPagamentos) - 1),
        );
    }

    /**
     * Atalho do crédito: usa até o saldo e reduz as outras formas para não gerar troco.
     */
    protected function aplicarCreditoClienteNaLinha(int $index): void
    {
        if (! $this->isLinhaCreditoCliente($this->finalizarPagamentos[$index] ?? [])) {
            return;
        }

        $saldo = $this->saldoCreditoClienteFinalizar();
        $total = $this->finalizarTotalVendaValor();
        $usar = min($saldo, $total);

        if (! $this->finalizarClienteId) {
            Notification::make()
                ->title('Informe o cliente para usar o crédito.')
                ->warning()
                ->send();

            return;
        }

        if ($usar <= 0.009) {
            Notification::make()
                ->title('Este cliente não tem crédito disponível.')
                ->warning()
                ->send();

            return;
        }

        $pagamentos = $this->finalizarPagamentos;
        $excesso = round($usar + $this->somaPagamentosExceto($pagamentos, $index) - $total, 2);

        if ($excesso > 0.009) {
            $pagamentos = $this->reduzirPagamentosExceto($pagamentos, $index, $excesso);
        }

        $pagamentos[$index]['valor'] = ErpMoney::formatBr($usar);
        $this->finalizarPagamentos = $pagamentos;
        $this->selectedPagamentoIndex = $index;
        $this->dispatch('erp-pdv-focus-finalizar-pagamento', index: $index, valor: $pagamentos[$index]['valor']);
    }

    protected function validaCreditoClienteFinalizar(): ?string
    {
        $credito = 0.0;
        $outros = 0.0;

        foreach ($this->finalizarPagamentos as $pagamento) {
            $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

            if ($valor <= 0) {
                continue;
            }

            if ($this->isLinhaCreditoCliente($pagamento)) {
                $credito += $valor;
            } else {
                $outros += $valor;
            }
        }

        $credito = round($credito, 2);

        if ($credito <= 0.009) {
            return null;
        }

        if (! $this->finalizarClienteId) {
            return 'Informe o cliente para usar o crédito.';
        }

        $saldo = $this->saldoCreditoClienteFinalizar();

        if ($credito - $saldo > 0.009) {
            return 'Crédito disponível insuficiente. Disponível: R$ '.ErpMoney::formatBr($saldo).'.';
        }

        $teto = round(max(0, $this->finalizarTotalVendaValor() - min($outros, $this->finalizarTotalVendaValor())), 2);

        if ($credito - $teto > 0.009) {
            return 'O crédito do cliente não gera troco. Reduza as outras formas ou o valor do crédito.';
        }

        return null;
    }

    protected function consumirCreditoClienteDaVenda(PdvVenda $venda, ?int $clienteId, string $numero): void
    {
        $valor = 0.0;

        foreach ($this->finalizarPagamentos as $pagamento) {
            if (! $this->isLinhaCreditoCliente($pagamento)) {
                continue;
            }

            $valor += ErpMoney::parseBr($pagamento['valor'] ?? '0');
        }

        $valor = round($valor, 2);

        if ($valor <= 0.009) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? Auth::user()?->empresa_id ?? 0);

        (new ClienteCreditoService())->usar(
            clienteId: (int) $clienteId,
            valor: $valor,
            empresaId: $empresaId,
            origemTipo: ClienteCreditoMovimentacao::ORIGEM_PDV,
            origemId: (int) $venda->id,
            origemNumero: $numero,
            observacao: 'Venda PDV '.$numero,
            usuarioId: Auth::id() ? (int) Auth::id() : null,
        );
    }

    /**
     * @param  array<string, mixed>  $pagamento
     */
    protected function isLinhaCreditoCliente(array $pagamento): bool
    {
        return ! empty($pagamento['credito_cliente_linha'])
            || ClienteCreditoMovimentacao::isFormaPdv((string) ($pagamento['forma'] ?? ''));
    }

    protected function saldoCreditoClienteFinalizar(): float
    {
        $clienteId = $this->finalizarClienteId ? (int) $this->finalizarClienteId : 0;
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? Auth::user()?->empresa_id ?? 0);

        if ($clienteId <= 0 || $empresaId <= 0) {
            return 0.0;
        }

        $servico = new ClienteCreditoService();

        if (! $servico->podeReceberCredito($clienteId)) {
            return 0.0;
        }

        return $servico->saldo($clienteId, $empresaId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $pagamentos
     */
    private function somaPagamentosExceto(array $pagamentos, int $index): float
    {
        $soma = 0.0;

        foreach ($pagamentos as $i => $pagamento) {
            if ($i === $index) {
                continue;
            }

            $soma += ErpMoney::parseBr($pagamento['valor'] ?? '0');
        }

        return round($soma, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $pagamentos
     * @return array<int, array<string, mixed>>
     */
    private function reduzirPagamentosExceto(array $pagamentos, int $index, float $excesso): array
    {
        $ordem = array_keys($pagamentos);
        usort($ordem, function (int $a, int $b) use ($pagamentos, $index): int {
            if ($a === $index) {
                return 1;
            }

            if ($b === $index) {
                return -1;
            }

            $dinheiroA = mb_strtoupper((string) ($pagamentos[$a]['forma'] ?? ''), 'UTF-8') === 'DINHEIRO';
            $dinheiroB = mb_strtoupper((string) ($pagamentos[$b]['forma'] ?? ''), 'UTF-8') === 'DINHEIRO';

            return $dinheiroA === $dinheiroB ? 0 : ($dinheiroA ? -1 : 1);
        });

        foreach ($ordem as $i) {
            if ($i === $index || $excesso <= 0.009) {
                break;
            }

            $atual = ErpMoney::parseBr($pagamentos[$i]['valor'] ?? '0');

            if ($atual <= 0) {
                continue;
            }

            $corte = min($atual, $excesso);
            $pagamentos[$i]['valor'] = ErpMoney::formatBr(round($atual - $corte, 2));
            $excesso = round($excesso - $corte, 2);
        }

        return $pagamentos;
    }
}
