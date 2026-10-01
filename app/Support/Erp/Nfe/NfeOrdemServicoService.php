<?php

namespace App\Support\Erp\Nfe;

use App\Models\Cfop;
use App\Models\Empresa;
use App\Models\OperacaoFiscal;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Person;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use RuntimeException;

class NfeOrdemServicoService
{
    public function validar(OrdemServico $ordem): void
    {
        $ordem->loadMissing(['itens.product', 'cliente', 'empresa']);

        if ((int) ($ordem->cliente_id ?? 0) <= 0) {
            throw new RuntimeException('A OS não possui cliente vinculado.');
        }

        if ($this->pecasComProduto($ordem)->isEmpty()) {
            throw new RuntimeException('A OS não possui peças com produto para emitir NF-e.');
        }

        $empresa = $this->resolveEmpresa($ordem);

        if ($empresa === null) {
            throw new RuntimeException('Empresa não identificada para a OS.');
        }

        $this->resolveCfop($empresa, $ordem->cliente);
    }

    /**
     * @return array{
     *     ordem_servico_id: int,
     *     cliente_id: int,
     *     finalidade: string,
     *     movimento: string,
     *     data_emissao: string,
     *     data_saida: string,
     *     numero_pedido: string,
     *     natureza_operacao: string,
     *     forma_pgto: string,
     *     meio_pgto: string,
     *     obs_contribuinte: string,
     *     faturas: list<array{numero: string, data_vencimento: string, valor: string}>,
     *     rows: list<array{product_id: int, quantidade: float, valor_unitario: float, descricao: string, cfop: string, pedido: string}>
     * }
     */
    public function montarPayload(OrdemServico $ordem): array
    {
        $this->validar($ordem);

        $ordem->loadMissing(['itens.product', 'cliente', 'empresa']);

        $empresa = $this->resolveEmpresa($ordem);
        $cliente = $ordem->cliente;
        $cfop = $this->resolveCfop($empresa, $cliente);
        $natureza = $this->formatNaturezaOperacao($cfop);
        $data = $ordem->data_termino?->format('Y-m-d')
            ?? $ordem->data_emissao?->format('Y-m-d')
            ?? ErpTimezone::today();
        $numeroOs = $this->formatNumeroOs($ordem);
        $formaLabel = $this->primeiraFormaPagamento($ordem);

        $rows = [];

        foreach ($this->pecasComProduto($ordem) as $item) {
            $productId = (int) ($item->product_id ?? 0);
            $qtd = (float) ($item->qtd ?? 0);

            if ($productId <= 0 || $qtd <= 0.0001) {
                continue;
            }

            $total = (float) ($item->total ?? 0);
            $valorUnit = $qtd > 0
                ? round($total > 0.0001 ? $total / $qtd : (float) ($item->preco ?? 0), 4)
                : (float) ($item->preco ?? 0);

            $rows[] = [
                'product_id' => $productId,
                'quantidade' => $qtd,
                'valor_unitario' => $valorUnit,
                'descricao' => (string) ($item->product?->descricao ?? $item->discriminacao ?? $item->nome ?? ''),
                'cfop' => (string) $cfop,
                'pedido' => $numeroOs,
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('Nenhuma peça da OS possui produto vinculado para NF-e.');
        }

        return [
            'ordem_servico_id' => (int) $ordem->id,
            'cliente_id' => (int) $ordem->cliente_id,
            'finalidade' => 'normal',
            'movimento' => 'saida',
            'data_emissao' => $data,
            'data_saida' => $data,
            'numero_pedido' => $numeroOs,
            'natureza_operacao' => $natureza,
            'forma_pgto' => $this->mapFormaPgto($formaLabel),
            'meio_pgto' => $this->mapMeioPgto($formaLabel),
            'obs_contribuinte' => 'NF-E ORIGINADA DA OS Nº '.$numeroOs.'.',
            'faturas' => [],
            'rows' => $rows,
        ];
    }

    public static function osTemPecasParaNfe(OrdemServico $ordem): bool
    {
        return (new self)->pecasComProduto($ordem)->isNotEmpty();
    }

    /**
     * @return \Illuminate\Support\Collection<int, OrdemServicoItem>
     */
    protected function pecasComProduto(OrdemServico $ordem)
    {
        $ordem->loadMissing('itens.product');

        return $ordem->itens
            ->filter(static function (OrdemServicoItem $item): bool {
                if (mb_strtoupper(trim((string) ($item->tipo ?? '')), 'UTF-8') !== 'P') {
                    return false;
                }

                return (int) ($item->product_id ?? 0) > 0
                    && (float) ($item->qtd ?? 0) > 0.0001;
            })
            ->values();
    }

    protected function resolveEmpresa(OrdemServico $ordem): ?Empresa
    {
        if ($ordem->empresa instanceof Empresa) {
            return $ordem->empresa;
        }

        $empresaId = (int) ($ordem->empresa_id ?? ErpContext::currentEmpresaId() ?? 0);

        return $empresaId > 0 ? Empresa::query()->find($empresaId) : null;
    }

    protected function formatNumeroOs(OrdemServico $ordem): string
    {
        $numero = trim((string) ($ordem->numero ?? ''));
        $digits = ltrim(preg_replace('/\D/', '', $numero) ?? '', '0');

        if ($digits !== '') {
            return $digits;
        }

        if ($numero !== '') {
            return $numero;
        }

        return (string) ($ordem->codigo_legado ?: $ordem->id);
    }

    protected function resolveCfop(Empresa $empresa, ?Person $cliente): int
    {
        $empresaUf = strtoupper(trim((string) ($empresa->uf ?? '')));
        $clienteUf = strtoupper(trim((string) ($cliente?->uf ?? '')));
        $interestadual = $clienteUf !== ''
            && $empresaUf !== ''
            && $clienteUf !== $empresaUf;

        $cfop = OperacaoFiscal::forEmpresa((int) $empresa->id)
            ->cfopVendaMercadoria($interestadual);

        if ($cfop === null) {
            $label = $interestadual ? 'interestadual' : 'estadual';

            throw new RuntimeException(
                'Configure o CFOP de Venda de mercadoria ('.$label.') em Operações Fiscais antes de emitir a NF-e.'
            );
        }

        return $cfop;
    }

    protected function formatNaturezaOperacao(int $cfop): string
    {
        $descricao = Cfop::query()
            ->where('codigo', $cfop)
            ->value('descricao');

        return trim(
            $cfop.($descricao ? ' - '.mb_strtoupper((string) $descricao, 'UTF-8') : '')
        );
    }

    protected function primeiraFormaPagamento(OrdemServico $ordem): string
    {
        $raw = $ordem->faturamento_pagamentos;

        if (! is_array($raw)) {
            return '';
        }

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $forma = trim((string) ($item['forma'] ?? ''));

            if ($forma !== '') {
                return $forma;
            }
        }

        return '';
    }

    protected function mapFormaPgto(?string $forma): string
    {
        $normalized = mb_strtolower(trim((string) $forma), 'UTF-8');

        if (
            str_contains($normalized, 'prazo')
            || str_contains($normalized, 'parcel')
            || str_contains($normalized, 'boleto')
            || str_contains($normalized, 'carteira')
            || str_contains($normalized, 'duplicata')
            || str_contains($normalized, 'credi')
        ) {
            return 'a_prazo';
        }

        return 'a_vista';
    }

    protected function mapMeioPgto(?string $forma): string
    {
        $normalized = mb_strtolower(trim((string) $forma), 'UTF-8');

        return match (true) {
            str_contains($normalized, 'boleto') => 'boleto',
            str_contains($normalized, 'pix') => 'pix',
            str_contains($normalized, 'cart') || str_contains($normalized, 'pos') || str_contains($normalized, 'tef') => 'cartao',
            str_contains($normalized, 'cheque') => 'cheque',
            str_contains($normalized, 'carteira') || str_contains($normalized, 'duplicata') || str_contains($normalized, 'prazo') || str_contains($normalized, 'credi') => 'credito_loja',
            default => 'dinheiro',
        };
    }
}
