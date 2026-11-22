<?php

namespace App\Filament\Resources\OrcamentoResource\Pages\Concerns;

use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Separadores de grupo na grade do Orçamento (títulos livres na sequência dos itens).
 * Não são produtos: sem qtd/preço/custo/estoque e fora dos totalizadores.
 */
trait ManagesOrcamentoSeparadores
{
    public string $separadorTituloInput = '';

    public ?int $separadorEditIndex = null;

    public function orcamentoPermitirSeparadoresGrupo(): bool
    {
        return (bool) (ErpContext::currentEmpresa()?->param_orcamento_permitir_separadores_grupo ?? false);
    }

    public function carregarSeparadorParaEdicao(int $index): void
    {
        if ($this->orcamentoReadOnly() || ! isset($this->itens[$index])) {
            return;
        }

        if (! $this->linhaEhSeparador($this->itens[$index])) {
            return;
        }

        $this->separadorEditIndex = $index;
        $this->separadorTituloInput = (string) ($this->itens[$index]['descricao'] ?? '');
        $this->selectedItemIndex = $index;
        $this->dispatch('erp-orcamento-focus-separador-titulo');
    }

    public function confirmarSeparador(): void
    {
        if ($this->orcamentoReadOnly()) {
            return;
        }

        $titulo = mb_strtoupper(trim($this->separadorTituloInput), 'UTF-8');

        if ($titulo === '') {
            Notification::make()
                ->title('Informe o título do grupo.')
                ->warning()
                ->send();
            $this->dispatch('erp-orcamento-focus-separador-titulo');

            return;
        }

        if ($this->separadorEditIndex !== null) {
            $index = $this->separadorEditIndex;

            if (! isset($this->itens[$index]) || ! $this->linhaEhSeparador($this->itens[$index])) {
                $this->limparCampoSeparador();
                $this->dispatch('erp-orcamento-focus-item-codigo');

                return;
            }

            $itens = $this->itens;
            $itens[$index]['descricao'] = $titulo;
            $this->itens = $itens;
            $this->selectedItemIndex = $index;
            $this->produtoAtualNome = $titulo;
            $this->produtoAtualFoto = null;
            $this->limparCampoSeparador();
            $this->dispatch('erp-orcamento-focus-item-codigo');

            return;
        }

        if (! $this->orcamentoPermitirSeparadoresGrupo()) {
            return;
        }

        $itens = $this->itens;
        $newItem = $this->novaLinhaSeparador($titulo, count($itens) + 1);
        array_unshift($itens, $newItem);
        $this->itens = $this->renumberItens($itens);
        $this->selectedItemIndex = 0;
        $this->produtoAtualNome = $titulo;
        $this->produtoAtualFoto = null;
        $this->limparCampoSeparador();
        $this->dispatch('erp-orcamento-focus-item-codigo');
    }

    protected function limparCampoSeparador(): void
    {
        $this->separadorTituloInput = '';
        $this->separadorEditIndex = null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function linhaEhSeparador(array $row): bool
    {
        return (bool) ($row['is_separador'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function novaLinhaSeparador(string $titulo, int $itemNumero): array
    {
        return [
            'id' => null,
            'key' => 'sep-' . Str::uuid()->toString(),
            'item' => $itemNumero,
            'is_separador' => true,
            'product_id' => null,
            'product_codigo' => '',
            'descricao' => $titulo,
            'quantidade' => ErpMoney::formatBr(0, 3),
            'unidade' => '',
            'preco_unitario' => ErpMoney::formatBr(0),
            'acrescimo' => ErpMoney::formatBr(0),
            'desconto' => ErpMoney::formatBr(0),
            'total' => ErpMoney::formatBr(0),
            'preco_variavel' => false,
            'product_grade_id' => null,
            'grade_descricao' => '',
            'foto' => null,
        ];
    }
}
