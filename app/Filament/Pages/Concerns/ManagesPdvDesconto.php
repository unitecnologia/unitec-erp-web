<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Product;
use App\Support\Erp\ErpMoney;
use Filament\Notifications\Notification;

trait ManagesPdvDesconto
{
    /** @var 'desconto'|'acrescimo' */
    public string $descontoItemTipo = 'desconto';

    /** @var 'percentual'|'valor' */
    public string $descontoItemModo = 'percentual';

    public string $descontoItemValor = '0,00';

    public function openDescontoItemModal(): void
    {
        if (! $this->pdvConfig()->permitirDescontoItem()) {
            $this->notifyPdvError('Desconto de item não habilitado nos parâmetros.');

            return;
        }

        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        $item = $this->cupomItemSelecionado;

        if (! $item) {
            $this->notifyPdvError('Selecione um item do cupom para desconto/acréscimo.');

            return;
        }

        $this->descontoItemTipo = 'desconto';
        $this->descontoItemModo = 'percentual';
        $this->descontoItemValor = '0,00';

        $this->openPdvModal('desconto_item');
        $this->dispatch('erp-pdv-focus-desconto');
    }

    public function setDescontoItemTipo(string $tipo): void
    {
        $this->descontoItemTipo = $tipo === 'acrescimo' ? 'acrescimo' : 'desconto';
    }

    public function setDescontoItemModo(string $modo): void
    {
        $this->descontoItemModo = $modo === 'valor' ? 'valor' : 'percentual';
    }

    /**
     * Preço base do item selecionado (antes de desconto/acréscimo).
     */
    protected function descontoItemPrecoBase(array $item): float
    {
        return round((float) ($item['preco_base'] ?? $item['preco'] ?? 0), 2);
    }

    /**
     * Calcula o resultado do desconto/acréscimo para o item informado.
     *
     * @return array{base: float, delta: float, novoPreco: float, total: float, quantidade: float}
     */
    protected function calcularDescontoItem(array $item): array
    {
        $base = $this->descontoItemPrecoBase($item);
        $quantidade = (float) ($item['quantidade'] ?? 1);
        $valor = ErpMoney::parseBr($this->descontoItemValor, 2);

        if ($this->descontoItemModo === 'percentual') {
            $deltaUnit = round($base * ($valor / 100), 2);
        } else {
            $deltaUnit = round($valor, 2);
        }

        $novoPreco = $this->descontoItemTipo === 'acrescimo'
            ? round($base + $deltaUnit, 2)
            : round($base - $deltaUnit, 2);

        if ($novoPreco < 0) {
            $novoPreco = 0.0;
        }

        return [
            'base' => $base,
            'delta' => round($novoPreco - $base, 2),
            'novoPreco' => $novoPreco,
            'total' => round($quantidade * $novoPreco, 2),
            'quantidade' => $quantidade,
        ];
    }

    /**
     * Pré-visualização ao vivo para o modal.
     *
     * @return array{base: string, novoPreco: string, total: string, tipo: string, temAjuste: bool}
     */
    public function getDescontoItemPreviewProperty(): array
    {
        $item = $this->cupomItemSelecionado;

        if (! $item) {
            return [
                'base' => ErpMoney::formatBr(0),
                'novoPreco' => ErpMoney::formatBr(0),
                'total' => ErpMoney::formatBr(0),
                'tipo' => $this->descontoItemTipo,
                'temAjuste' => false,
            ];
        }

        $calc = $this->calcularDescontoItem($item);

        return [
            'base' => ErpMoney::formatBr($calc['base']),
            'novoPreco' => ErpMoney::formatBr($calc['novoPreco']),
            'total' => ErpMoney::formatBr($calc['total']),
            'tipo' => $this->descontoItemTipo,
            'temAjuste' => abs($calc['delta']) > 0.0001,
        ];
    }

    public function confirmDescontoItem(): void
    {
        if ($this->selectedCupomIndex === null || ! isset($this->cupomItens[$this->selectedCupomIndex])) {
            $this->closePdvModal();

            return;
        }

        $index = $this->selectedCupomIndex;
        $item = $this->cupomItens[$index];
        $calc = $this->calcularDescontoItem($item);

        $base = $calc['base'];
        $novoPreco = $calc['novoPreco'];
        $quantidade = $calc['quantidade'];
        $productId = $item['product_id'] ?? null;

        if ($novoPreco <= 0) {
            $this->notifyPdvError('Preço resultante inválido.');

            return;
        }

        if ($productId) {
            $product = Product::query()->find($productId);

            if ($product) {
                $erro = $this->pdvItemValidator()->validaPreco($product, $novoPreco, $quantidade);

                if ($erro) {
                    $this->notifyPdvError($erro);

                    return;
                }

                if ($this->descontoItemTipo === 'desconto') {
                    $maxDescontoPct = $this->pdvConfig()->descontoMaximo();

                    if ($maxDescontoPct > 0) {
                        $precoMinimo = round($base * (1 - $maxDescontoPct / 100), 2);

                        if ($novoPreco < $precoMinimo) {
                            $this->notifyPdvError(
                                'Desconto maior que o máximo permitido pela empresa (' . ErpMoney::formatBr($maxDescontoPct) . '%).'
                            );

                            return;
                        }
                    }
                } else {
                    $maxAcrescimoPct = $this->pdvConfig()->acrescimoMaximo();

                    if ($maxAcrescimoPct > 0) {
                        $precoMaximo = round($base * (1 + $maxAcrescimoPct / 100), 2);

                        if ($novoPreco > $precoMaximo) {
                            $this->notifyPdvError(
                                'Acréscimo maior que o máximo permitido pela empresa (' . ErpMoney::formatBr($maxAcrescimoPct) . '%).'
                            );

                            return;
                        }
                    }
                }
            }
        }

        $delta = round($novoPreco - $base, 2);

        $this->cupomItens[$index]['preco_base'] = $base;
        $this->cupomItens[$index]['preco'] = $novoPreco;
        $this->cupomItens[$index]['total'] = round($quantidade * $novoPreco, 2);
        $this->cupomItens[$index]['desconto'] = $delta < 0 ? abs($delta) : 0.0;
        $this->cupomItens[$index]['acrescimo'] = $delta > 0 ? $delta : 0.0;

        $this->persistCupomToSession();
        $this->closePdvModal();

        Notification::make()
            ->title($this->descontoItemTipo === 'acrescimo' ? 'Acréscimo aplicado.' : 'Desconto aplicado.')
            ->success()
            ->send();

        $this->dispatch('erp-pdv-focus-search');
    }
}
