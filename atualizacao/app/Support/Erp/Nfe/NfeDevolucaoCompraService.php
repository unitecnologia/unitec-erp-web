<?php

namespace App\Support\Erp\Nfe;

use App\Models\Cfop;
use App\Models\DevolucaoCompra;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\OperacaoFiscal;
use App\Models\Person;
use RuntimeException;

class NfeDevolucaoCompraService
{
    public function validar(DevolucaoCompra $devolucao): void
    {
        $devolucao->loadMissing(['itens', 'compra', 'fornecedor', 'empresa']);

        if ($devolucao->situacao !== DevolucaoCompra::SITUACAO_FINALIZADA) {
            throw new RuntimeException('Somente devolução de compra finalizada pode gerar NF-e.');
        }

        if ((int) ($devolucao->fornecedor_id ?? 0) <= 0) {
            throw new RuntimeException('A devolução não possui fornecedor vinculado.');
        }

        if ($devolucao->itens->isEmpty()) {
            throw new RuntimeException('A devolução não possui itens para emitir NF-e.');
        }

        $chave = $this->normalizeChave($devolucao->compra?->chave_nfe);

        if ($chave === null) {
            throw new RuntimeException('A compra original não possui chave NF-e. Informe a chave no lançamento da compra antes de emitir a devolução.');
        }

        $empresa = $this->resolveEmpresa($devolucao);

        if ($empresa === null) {
            throw new RuntimeException('Empresa não identificada para a devolução.');
        }

        $this->resolveCfop($empresa, $devolucao->fornecedor);

        if ($this->temNfeAtiva($devolucao)) {
            throw new RuntimeException('Esta devolução de compra já possui NF-e vinculada.');
        }
    }

    /**
     * @return array{
     *     devolucao_compra_id: int,
     *     cliente_id: int,
     *     finalidade: string,
     *     movimento: string,
     *     data_emissao: string,
     *     data_saida: string,
     *     numero_pedido: string,
     *     natureza_operacao: string,
     *     obs_contribuinte: string,
     *     referencias: list<array{referencia: string}>,
     *     rows: list<array{product_id: int, quantidade: float, valor_unitario: float, descricao: string, cfop: string}>
     * }
     */
    public function montarPayload(DevolucaoCompra $devolucao): array
    {
        $this->validar($devolucao);

        $devolucao->loadMissing(['itens.product', 'compra', 'fornecedor', 'empresa']);

        $empresa = $this->resolveEmpresa($devolucao);
        $fornecedor = $devolucao->fornecedor;
        $cfop = $this->resolveCfop($empresa, $fornecedor);
        $natureza = $this->formatNaturezaOperacao($cfop);
        $chave = $this->normalizeChave($devolucao->compra?->chave_nfe);
        $data = $devolucao->data?->format('Y-m-d') ?? now()->format('Y-m-d');
        $numeroDevolucao = ltrim((string) $devolucao->numero, '0') ?: (string) $devolucao->numero;
        $numeroCompra = ltrim((string) ($devolucao->compra_numero ?? $devolucao->compra?->numero ?? ''), '0');

        $rows = [];

        foreach ($devolucao->itens as $item) {
            $productId = (int) ($item->product_id ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $rows[] = [
                'product_id' => $productId,
                'quantidade' => (float) $item->qtd,
                'valor_unitario' => (float) $item->preco,
                'descricao' => (string) ($item->produto_descricao ?: ($item->product?->descricao ?? '')),
                'cfop' => (string) $cfop,
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('Nenhum item da devolução possui produto vinculado para NF-e.');
        }

        $obsDevolucao = trim((string) ($devolucao->observacoes ?? ''));
        $origem = 'NF-e de devolução de compra nº '.$numeroDevolucao.'.';

        if ($numeroCompra !== '') {
            $origem .= ' Compra nº '.$numeroCompra.'.';
        }

        return [
            'devolucao_compra_id' => (int) $devolucao->id,
            'cliente_id' => (int) $devolucao->fornecedor_id,
            'finalidade' => 'devolucao',
            'movimento' => 'saida',
            'data_emissao' => $data,
            'data_saida' => $data,
            'numero_pedido' => $numeroDevolucao,
            'natureza_operacao' => $natureza,
            'obs_contribuinte' => trim($obsDevolucao === '' ? $origem : $origem.' '.$obsDevolucao),
            'referencias' => $chave !== null ? [['referencia' => $chave]] : [],
            'rows' => $rows,
        ];
    }

    public function temNfeAtiva(DevolucaoCompra $devolucao): bool
    {
        return Nfe::query()
            ->where('devolucao_compra_id', $devolucao->id)
            ->where('status', '!=', Nfe::STATUS_CANCELADA)
            ->exists();
    }

    protected function resolveEmpresa(DevolucaoCompra $devolucao): ?Empresa
    {
        if ($devolucao->relationLoaded('empresa') && $devolucao->empresa) {
            return $devolucao->empresa;
        }

        $empresaId = (int) ($devolucao->empresa_id ?? 0);

        return $empresaId > 0 ? Empresa::query()->find($empresaId) : null;
    }

    protected function resolveCfop(Empresa $empresa, ?Person $fornecedor): int
    {
        $empresaUf = strtoupper(trim((string) ($empresa->uf ?? '')));
        $fornecedorUf = strtoupper(trim((string) ($fornecedor?->uf ?? '')));
        $interestadual = $fornecedorUf !== ''
            && $empresaUf !== ''
            && $fornecedorUf !== $empresaUf;

        $cfop = OperacaoFiscal::forEmpresa((int) $empresa->id)
            ->cfopDevolucaoCompras($interestadual);

        if ($cfop === null) {
            $label = $interestadual ? 'interestadual' : 'estadual';

            throw new RuntimeException(
                'Configure o CFOP de Devolução de Compras ('.$label.') em Operações Fiscais antes de emitir a NF-e.'
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

    protected function normalizeChave(?string $chave): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $chave) ?? '';

        return strlen($digits) === 44 ? $digits : null;
    }
}
