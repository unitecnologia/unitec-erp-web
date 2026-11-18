<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Product;
use App\Support\Erp\DescontoReaisItemCalculator;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpMoney;
use Filament\Notifications\Notification;

trait ManagesPdvDesconto
{
    /** @var 'desconto'|'acrescimo' */
    public string $descontoItemTipo = 'desconto';

    /** @var 'percentual'|'valor' */
    public string $descontoItemModo = 'percentual';

    public string $descontoItemValor = '0,00';

    #[\Livewire\Attributes\On('erp-pdv-open-desconto')]
    public function openDescontoItemModal(): void
    {
        if ($this->mesaBloqueiaAlteracao()) {
            return;
        }

        if (! $this->pdvConfig()->permitirDescontoItem()) {
            $this->notifyPdvError('Desconto de item não habilitado nos parâmetros.');

            return;
        }

        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if ($this->cupomItens === []) {
            $this->notifyPdvError('Selecione um item do cupom para desconto/acréscimo.');

            return;
        }

        // Após lançar, limparPainelAposLancamento zera a seleção — usar o último item
        // (mesmo critério do PDV offline) para Ctrl+Q / botão Opções funcionarem sem clicar na grade.
        if ($this->selectedCupomIndex === null || ! isset($this->cupomItens[$this->selectedCupomIndex])) {
            $last = array_key_last($this->cupomItens);
            $this->selectedCupomIndex = is_int($last) ? $last : (int) $last;
            $this->pdvMostrarDetalheItem = true;
        }

        $item = $this->cupomItemSelecionado;

        if (! $item) {
            $this->notifyPdvError('Selecione um item do cupom para desconto/acréscimo.');

            return;
        }

        $seed = $this->seedDescontoItemModal($item);
        $this->descontoItemTipo = $seed['tipo'];
        $this->descontoItemModo = 'percentual';
        $this->descontoItemValor = $seed['pct'];

        $this->openPdvModal('desconto_item');
        $this->dispatch('erp-pdv-focus-desconto');
    }

    /**
     * @return array{descricao: string, baseRaw: float, qtdRaw: float, tipo: string, pct: string, reais: string, modoReais: string}
     */
    public function getDescontoItemModalProperty(): array
    {
        $item = $this->cupomItemSelecionado;
        $seed = $item ? $this->seedDescontoItemModal($item) : [
            'tipo' => 'desconto',
            'pct' => '0,00',
            'reais' => '0,00',
        ];
        $base = $item ? $this->descontoItemPrecoBase($item) : 0.0;
        $qtd = $item ? (float) ($item['quantidade'] ?? 1) : 0.0;

        return [
            'descricao' => (string) ($item['descricao'] ?? ''),
            'baseRaw' => $base,
            'qtdRaw' => $qtd,
            'tipo' => $seed['tipo'],
            'pct' => $seed['pct'],
            'reais' => $seed['reais'],
            'modoReais' => EmpresaParametros::descontoReaisItemModo(),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{tipo: string, pct: string, reais: string}
     */
    protected function seedDescontoItemModal(array $item): array
    {
        $base = $this->descontoItemPrecoBase($item);
        $qtd = (float) ($item['quantidade'] ?? 1);
        $bruto = max(0.0, round($base * $qtd, 2));
        $desc = (float) ($item['desconto'] ?? 0);
        $acr = (float) ($item['acrescimo'] ?? 0);
        // Preferir ajuste de linha fixo quando existir.
        $ajusteLinha = (float) ($item['ajuste_linha_fixo'] ?? 0);
        $tipo = 'desconto';
        $ajuste = 0.0;

        if ($acr > 0.0001 || (($item['acrescimo_linha_fixo'] ?? false) && $ajusteLinha > 0)) {
            $tipo = 'acrescimo';
            $ajuste = $ajusteLinha > 0 ? $ajusteLinha : round($acr * $qtd, 2);
        } elseif ($desc > 0.0001 || (($item['desconto_linha_fixo'] ?? false) && $ajusteLinha > 0)) {
            $ajuste = $ajusteLinha > 0 ? $ajusteLinha : round($desc * $qtd, 2);
        }

        $pct = $bruto > 0 && $ajuste > 0 ? round(($ajuste / $bruto) * 100, 2) : 0.0;

        return [
            'tipo' => $tipo,
            'pct' => ErpMoney::formatBr($pct),
            'reais' => ErpMoney::formatBr($ajuste),
        ];
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
     * @return array{base: float, delta: float, deltaUnit: float, ajusteLinha: float, novoPreco: float, total: float, quantidade: float, modoReais: string}
     */
    protected function calcularDescontoItem(array $item): array
    {
        $base = $this->descontoItemPrecoBase($item);
        $quantidade = (float) ($item['quantidade'] ?? 1);
        $valor = ErpMoney::parseBr($this->descontoItemValor, 2);
        $modoReais = EmpresaParametros::descontoReaisItemModo();
        $calc = DescontoReaisItemCalculator::calcular(
            $base,
            $quantidade,
            $valor,
            $this->descontoItemTipo,
            $this->descontoItemModo,
            $modoReais,
        );

        // PDV persiste preço líquido unitário; o total comercial usa o ajuste de linha.
        $deltaUnit = (float) $calc['deltaUnit'];
        $ajusteLinha = (float) $calc['ajusteLinha'];
        $novoPreco = (float) $calc['novoPreco'];

        if ($this->descontoItemModo === 'valor'
            && $modoReais === EmpresaParametros::DESCONTO_REAIS_ITEM_LINHA
            && $quantidade > 0) {
            // Evita erro de centavos: total = bruto ± linha; unitário derivado.
            $bruto = round($quantidade * $base, 2);
            $total = $this->descontoItemTipo === 'acrescimo'
                ? round($bruto + $ajusteLinha, 2)
                : round(max(0, $bruto - $ajusteLinha), 2);
            $novoPreco = round($total / $quantidade, 4);
            if ($novoPreco < 0) {
                $novoPreco = 0.0;
            }
            $deltaUnit = abs(round($novoPreco - $base, 4));
        } else {
            $total = (float) $calc['total'];
        }

        return [
            'base' => $base,
            'delta' => round($novoPreco - $base, 4),
            'deltaUnit' => $deltaUnit,
            'ajusteLinha' => $ajusteLinha,
            'novoPreco' => $novoPreco,
            'total' => $total,
            'quantidade' => $quantidade,
            'modoReais' => $modoReais,
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

    public function confirmDescontoItem(
        ?string $valorInformado = null,
        ?string $modoEntrada = null,
        ?string $tipoInformado = null,
    ): void {
        if ($tipoInformado !== null && $tipoInformado !== '') {
            $this->descontoItemTipo = $tipoInformado === 'acrescimo' ? 'acrescimo' : 'desconto';
        }
        if ($modoEntrada !== null && $modoEntrada !== '') {
            $this->descontoItemModo = $modoEntrada === 'valor' ? 'valor' : 'percentual';
        }
        if ($valorInformado !== null && trim($valorInformado) !== '') {
            $this->descontoItemValor = ErpMoney::formatBr(ErpMoney::parseBr($valorInformado));
        }

        if ($this->mesaBloqueiaAlteracao()) {
            $this->closePdvModal();

            return;
        }

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

        $delta = (float) $calc['delta'];
        $ajusteLinha = (float) $calc['ajusteLinha'];
        $modoReais = (string) ($calc['modoReais'] ?? EmpresaParametros::descontoReaisItemModo());
        $descontoFixoLinha = $this->descontoItemModo === 'valor'
            && $modoReais === EmpresaParametros::DESCONTO_REAIS_ITEM_LINHA
            && $this->descontoItemTipo === 'desconto'
            && $ajusteLinha > 0;
        $acrescimoFixoLinha = $this->descontoItemModo === 'valor'
            && $modoReais === EmpresaParametros::DESCONTO_REAIS_ITEM_LINHA
            && $this->descontoItemTipo === 'acrescimo'
            && $ajusteLinha > 0;
        $tipo = $this->descontoItemTipo;

        // Fecha antes de persistir (evita remorph reabrir o diálogo).
        $this->activeModal = null;

        $this->cupomItens[$index]['preco_base'] = $base;
        $this->cupomItens[$index]['preco'] = round($novoPreco, 4);
        $this->cupomItens[$index]['total'] = (float) $calc['total'];
        // Compat fiscal/cardex: desconto/acréscimo unitários.
        $this->cupomItens[$index]['desconto'] = $delta < 0 ? abs(round($delta, 4)) : 0.0;
        $this->cupomItens[$index]['acrescimo'] = $delta > 0 ? round($delta, 4) : 0.0;
        $this->cupomItens[$index]['desconto_linha_fixo'] = $descontoFixoLinha;
        $this->cupomItens[$index]['acrescimo_linha_fixo'] = $acrescimoFixoLinha;
        $this->cupomItens[$index]['ajuste_linha_fixo'] = $descontoFixoLinha || $acrescimoFixoLinha
            ? $ajusteLinha
            : 0.0;

        $this->persistCupomToSession();

        Notification::make()
            ->title($tipo === 'acrescimo' ? 'Acréscimo aplicado.' : 'Desconto aplicado.')
            ->success()
            ->send();

        $this->dispatch('erp-pdv-focus-search');
    }

    /**
     * Recalcula preço/total do item do cupom após mudança de quantidade,
     * respeitando desconto/acréscimo "total da linha" fixo.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function pdvRecalcularItemPorQuantidade(array $item, float $novaQuantidade): array
    {
        $novaQuantidade = max(0.001, round($novaQuantidade, 3));
        $base = round((float) ($item['preco_base'] ?? $item['preco'] ?? 0), 2);
        $ajusteFixo = round((float) ($item['ajuste_linha_fixo'] ?? 0), 2);
        $descontoFixo = ! empty($item['desconto_linha_fixo']) && $ajusteFixo > 0;
        $acrescimoFixo = ! empty($item['acrescimo_linha_fixo']) && $ajusteFixo > 0;

        if ($descontoFixo || $acrescimoFixo) {
            $bruto = round($novaQuantidade * $base, 2);
            $total = $descontoFixo
                ? round(max(0, $bruto - $ajusteFixo), 2)
                : round($bruto + $ajusteFixo, 2);
            $novoPreco = round($total / $novaQuantidade, 4);
            $delta = round($novoPreco - $base, 4);

            $item['quantidade'] = $novaQuantidade;
            $item['preco_base'] = $base;
            $item['preco'] = $novoPreco;
            $item['total'] = $total;
            $item['desconto'] = $delta < 0 ? abs($delta) : 0.0;
            $item['acrescimo'] = $delta > 0 ? $delta : 0.0;
            $item['ajuste_linha_fixo'] = $ajusteFixo;
            $item['desconto_linha_fixo'] = $descontoFixo;
            $item['acrescimo_linha_fixo'] = $acrescimoFixo;

            return $item;
        }

        $preco = round((float) ($item['preco'] ?? $base), 4);
        $item['quantidade'] = $novaQuantidade;
        $item['total'] = round($novaQuantidade * $preco, 2);

        return $item;
    }
}
