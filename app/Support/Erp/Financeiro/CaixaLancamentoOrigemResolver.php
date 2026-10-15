<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaLancamento;
use App\Models\Compra;
use App\Models\ContaPagar;
use App\Models\ContaPagarPagamento;
use App\Models\ContaReceber;
use App\Models\ContaReceberPagamento;
use App\Models\ForcaVendasOrder;
use App\Models\OrdemServico;
use App\Models\Person;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve, só para consulta, a origem comprovada de um lançamento do Livro Caixa.
 * Não altera lançamentos nem inventa valores sem registro no banco.
 */
final class CaixaLancamentoOrigemResolver
{
    /**
     * @return array{
     *     tem_detalhe: bool,
     *     linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>
     * }
     */
    public function resolver(CaixaLancamento $lancamento): array
    {
        $historico = trim((string) ($lancamento->historico ?? ''));
        $documento = trim((string) ($lancamento->documento ?? ''));
        $movimento = $this->valorMovimento($lancamento);

        if ($historico !== '' && preg_match('/baixa:(\d+)\s*$/u', $historico, $m)) {
            return $this->detalheContaPagarPorMarca((int) $m[1], $historico, $movimento);
        }

        if (preg_match('/^(Estorno\s+)?[Rr]ecebimento conta a receber\s*#(\S+)/u', $historico, $m)) {
            $ehEstorno = filled($m[1] ?? null);

            return $this->detalheContaReceber($m[2], $lancamento, $movimento, $ehEstorno);
        }

        if (preg_match('/^(Estorno\s+)?[Pp]agamento conta a pagar\s*#(\S+)/u', $historico, $m)) {
            $ehEstorno = filled($m[1] ?? null);

            return $this->detalheContaPagarLegado($m[2], $lancamento, $movimento, $ehEstorno);
        }

        if (preg_match('/^OS[-\s]?(\d+)/iu', $documento, $m) || preg_match('/^OS\s+(\d+)/iu', $historico, $m)) {
            return $this->detalheOs((int) $m[1], $historico, $movimento);
        }

        if (preg_match('/^FV-(\d+)/iu', $documento, $m)
            || preg_match('/\[FV-(\d+)\]/iu', $historico, $m)) {
            return $this->detalheForcaVendas((int) $m[1], $historico, $movimento);
        }

        if (preg_match('/^COMPRA-(\S+)/iu', $documento, $m)
            || preg_match('/^COMPRA\s*#(\S+)/iu', $historico, $m)) {
            return $this->detalheCompra((string) $m[1], $historico, $movimento);
        }

        if (preg_match('/^PDV-CX-/iu', $documento) || preg_match('/^FECHAMENTO DO CX/iu', $historico)) {
            return $this->linhas([
                $this->linha('Origem', 'Fechamento PDV (consolidado)'),
                $this->linha('Documento', $documento !== '' ? mb_strtoupper($documento, 'UTF-8') : null),
                $this->linha(
                    'Observação',
                    'Lançamento agregado do fechamento — sem título individual vinculado.',
                    wide: true,
                ),
            ]);
        }

        if (preg_match('/^DEV-/iu', $documento) || preg_match('/^Devolução venda/iu', $historico)) {
            return $this->linhas([
                $this->linha('Origem', 'Devolução de venda'),
                $this->linha('Documento', $documento !== '' ? mb_strtoupper($documento, 'UTF-8') : null),
                $this->linha('Valor no caixa', $this->money($movimento), money: true),
            ]);
        }

        if (str_starts_with(mb_strtoupper($historico, 'UTF-8'), '[MANUAL]')) {
            return $this->linhas([
                $this->linha('Origem', 'Lançamento manual'),
            ]);
        }

        if (preg_match('/^ESTORNO\s+/iu', $historico)) {
            return $this->linhas([
                $this->linha('Origem', 'Estorno'),
                $this->linha('Referência', mb_strtoupper(trim(preg_replace('/^ESTORNO\s+/iu', '', $historico) ?? ''), 'UTF-8') ?: null, wide: true),
                $this->linha('Valor no caixa', $this->money($movimento), money: true),
                $this->linha('Estorno', 'Este lançamento é um estorno.', destaque: 'estorno'),
            ]);
        }

        return ['tem_detalhe' => false, 'linhas' => []];
    }

    /**
     * @return array{tem_detalhe: bool, linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>}
     */
    private function detalheContaPagarPorMarca(int $pagamentoId, string $historico, float $movimento): array
    {
        $ehEstorno = (bool) preg_match('/^Estorno\s+/iu', $historico);

        $pagamento = ContaPagarPagamento::query()
            ->with([
                'formaPagamento:id,descricao',
                'fornecedor:id,nome_razao,apelido_fantasia',
                'contaPagar:id,numero,documento,valor,fornecedor_id',
                'contaPagar.fornecedor:id,nome_razao,apelido_fantasia',
            ])
            ->find($pagamentoId);

        if ($pagamento) {
            return $this->linhasDoPagamentoPagar($pagamento, $ehEstorno);
        }

        $ref = null;
        if (preg_match('/conta a pagar\s*#(\S+)/iu', $historico, $m)) {
            $ref = trim($m[1]);
        }

        $conta = $this->encontrarContaPagar($ref);
        $fornecedor = $conta?->fornecedor;

        return $this->linhas([
            $this->linha('Origem', 'Contas a Pagar'),
            $this->linha('Tipo', $ehEstorno ? 'Estorno de baixa' : 'Baixa'),
            $this->linha('Fornecedor', $this->nomePessoa($fornecedor)),
            $this->linha('Título', $conta?->numero ? (string) $conta->numero : ($ref ? '#'.$ref : null)),
            $this->linha('Documento', filled($conta?->documento) ? mb_strtoupper((string) $conta->documento, 'UTF-8') : null),
            $this->linha('Valor no caixa', $this->money($movimento), money: true),
            $this->linha(
                'Estorno',
                $ehEstorno
                    ? 'Este lançamento é um estorno. O detalhe da baixa não está mais disponível.'
                    : 'Baixa já estornada — juros/multa/desconto do pagamento não estão mais no banco.',
                wide: true,
                destaque: 'estorno',
            ),
        ]);
    }

    /**
     * @return array{tem_detalhe: bool, linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>}
     */
    private function detalheContaPagarLegado(string $ref, CaixaLancamento $lancamento, float $movimento, bool $ehEstorno): array
    {
        $conta = $this->encontrarContaPagar($ref);
        $pagamento = null;

        if ($conta && ! $ehEstorno) {
            $pagamento = $this->encontrarPagamentoPagar($conta, $lancamento, $movimento);
        }

        if ($pagamento) {
            return $this->linhasDoPagamentoPagar($pagamento, false);
        }

        return $this->linhas([
            $this->linha('Origem', 'Contas a Pagar'),
            $this->linha('Tipo', $ehEstorno ? 'Estorno de baixa' : 'Baixa'),
            $this->linha('Fornecedor', $this->nomePessoa($conta?->fornecedor)),
            $this->linha('Título', $conta?->numero ? (string) $conta->numero : '#'.$ref),
            $this->linha('Documento', filled($conta?->documento) ? mb_strtoupper((string) $conta->documento, 'UTF-8') : null),
            $this->linha('Valor no caixa', $this->money($movimento), money: true),
            $ehEstorno
                ? $this->linha('Estorno', 'Este lançamento é um estorno.', wide: true, destaque: 'estorno')
                : null,
        ]);
    }

    /**
     * @return array{tem_detalhe: bool, linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>}
     */
    private function detalheContaReceber(string $ref, CaixaLancamento $lancamento, float $movimento, bool $ehEstorno): array
    {
        $conta = $this->encontrarContaReceber($ref);
        $pagamento = null;

        if ($conta && ! $ehEstorno) {
            $pagamento = $this->encontrarPagamentoReceber($conta, $lancamento, $movimento);
        }

        if ($pagamento) {
            return $this->linhasDoPagamentoReceber($pagamento, false);
        }

        return $this->linhas([
            $this->linha('Origem', 'Contas a Receber'),
            $this->linha('Tipo', $ehEstorno ? 'Estorno de recebimento' : 'Recebimento'),
            $this->linha('Cliente', $this->nomePessoa($conta?->cliente)),
            $this->linha('Título', $conta?->numero ? (string) $conta->numero : '#'.$ref),
            $this->linha('Documento', filled($conta?->documento) ? mb_strtoupper((string) $conta->documento, 'UTF-8') : null),
            $this->linha('Valor no caixa', $this->money($movimento), money: true),
            $ehEstorno
                ? $this->linha(
                    'Estorno',
                    'Este lançamento é um estorno. O detalhe da baixa não está mais disponível.',
                    wide: true,
                    destaque: 'estorno',
                )
                : null,
        ]);
    }

    /**
     * @return array{tem_detalhe: bool, linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>}
     */
    private function detalheOs(int $numero, string $historico, float $movimento): array
    {
        $padded = str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
        $ordem = OrdemServico::query()
            ->with('cliente:id,nome_razao,apelido_fantasia')
            ->where(function ($query) use ($numero, $padded): void {
                $query->where('numero', (string) $numero)
                    ->orWhere('numero', $padded)
                    ->orWhere('id', $numero);
            })
            ->first();

        $forma = null;
        if (preg_match('/\(([^)]+)\)/u', $historico, $m)) {
            $forma = mb_strtoupper(trim($m[1]), 'UTF-8');
        }

        $ehEstorno = (bool) preg_match('/^ESTORNO\s+/iu', $historico);
        $cliente = $this->nomePessoa($ordem?->cliente);
        if ($cliente === null && filled($ordem?->nome)) {
            $cliente = mb_strtoupper(trim((string) $ordem->nome), 'UTF-8');
        }

        return $this->linhas([
            $this->linha('Origem', 'Ordem de Serviço'),
            $this->linha('Tipo', $ehEstorno ? 'Estorno' : 'Recebimento à vista'),
            $this->linha('Cliente', $cliente),
            $this->linha('OS', $ordem?->numero ? (string) $ordem->numero : (string) $numero),
            $this->linha('Forma de pagamento', $forma),
            $this->linha('Valor no caixa', $this->money($movimento), money: true),
            $ehEstorno
                ? $this->linha('Estorno', 'Este lançamento é um estorno.', wide: true, destaque: 'estorno')
                : null,
        ]);
    }

    /**
     * @return array{tem_detalhe: bool, linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>}
     */
    private function detalheForcaVendas(int $orderId, string $historico, float $movimento): array
    {
        $order = ForcaVendasOrder::query()
            ->with(['cliente:id,nome_razao,apelido_fantasia', 'venda:id,numero'])
            ->find($orderId);

        $forma = null;
        if (preg_match('/\(([^)]+)\)/u', $historico, $m)) {
            $forma = mb_strtoupper(trim($m[1]), 'UTF-8');
        }

        $ehEstorno = (bool) preg_match('/^ESTORNO\s+/iu', $historico);
        $pedido = $order?->venda?->numero
            ? ltrim((string) $order->venda->numero, '0') ?: (string) $order->venda->numero
            : 'FV-'.$orderId;

        $cliente = $this->nomePessoa($order?->cliente);
        if ($cliente === null && $order) {
            $fallback = trim((string) $order->clienteNome());
            $cliente = $fallback !== '' ? mb_strtoupper($fallback, 'UTF-8') : null;
        }

        return $this->linhas([
            $this->linha('Origem', 'Força de Vendas'),
            $this->linha('Tipo', $ehEstorno ? 'Estorno' : 'Recebimento à vista'),
            $this->linha('Cliente', $cliente),
            $this->linha('Pedido', $pedido),
            $this->linha('Forma de pagamento', $forma),
            $this->linha('Valor no caixa', $this->money($movimento), money: true),
            $ehEstorno
                ? $this->linha('Estorno', 'Este lançamento é um estorno.', wide: true, destaque: 'estorno')
                : null,
        ]);
    }

    /**
     * @return array{tem_detalhe: bool, linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>}
     */
    private function detalheCompra(string $ref, string $historico, float $movimento): array
    {
        $digits = preg_replace('/\D/', '', $ref) ?? '';
        $compra = null;

        if ($digits !== '') {
            $padded = str_pad($digits, 6, '0', STR_PAD_LEFT);
            $compra = Compra::query()
                ->with('fornecedor:id,nome_razao,apelido_fantasia')
                ->where(function ($query) use ($digits, $padded): void {
                    $query->where('numero', $digits)
                        ->orWhere('numero', $padded)
                        ->orWhere('id', (int) $digits);
                })
                ->first();
        }

        $forma = null;
        if (preg_match('/\(([^)]+)\)\s*$/u', $historico, $m)) {
            $forma = mb_strtoupper(trim($m[1]), 'UTF-8');
        }

        return $this->linhas([
            $this->linha('Origem', 'Compra'),
            $this->linha('Fornecedor', $this->nomePessoa($compra?->fornecedor)),
            $this->linha('Compra', $compra?->numero ? (string) $compra->numero : $ref),
            $this->linha('Forma de pagamento', $forma),
            $this->linha('Valor no caixa', $this->money($movimento), money: true),
        ]);
    }

    /**
     * @return array{tem_detalhe: bool, linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>}
     */
    private function linhasDoPagamentoPagar(ContaPagarPagamento $pagamento, bool $ehEstorno): array
    {
        $conta = $pagamento->contaPagar;
        $fornecedor = $pagamento->fornecedor ?: $conta?->fornecedor;
        $multa = Schema::hasColumn($pagamento->getTable(), 'multa')
            ? (float) ($pagamento->multa ?? 0)
            : null;

        return $this->linhas([
            $this->linha('Origem', 'Contas a Pagar'),
            $this->linha('Tipo', $ehEstorno ? 'Estorno de baixa' : 'Baixa'),
            $this->linha('Fornecedor', $this->nomePessoa($fornecedor)),
            $this->linha('Título', $conta?->numero ? (string) $conta->numero : null),
            $this->linha('Documento', filled($conta?->documento) ? mb_strtoupper((string) $conta->documento, 'UTF-8') : null),
            $this->linha('Forma de pagamento', $this->nomeForma($pagamento->formaPagamento?->descricao)),
            $this->linha('Valor original', $this->money((float) $pagamento->valor_parcela), money: true),
            $this->linha('Juros', $this->money((float) $pagamento->juros), money: true),
            $multa !== null ? $this->linha('Multa', $this->money($multa), money: true) : null,
            $this->linha('Desconto', $this->money((float) $pagamento->desconto), money: true),
            $this->linha('Valor pago', $this->money((float) $pagamento->valor_pago), money: true),
            $this->linha('Data', optional($pagamento->data)?->format('d/m/Y')),
            filled($pagamento->numero_cheque)
                ? $this->linha('Cheque', (string) $pagamento->numero_cheque)
                : null,
            $ehEstorno
                ? $this->linha('Estorno', 'Este lançamento é um estorno.', wide: true, destaque: 'estorno')
                : null,
        ]);
    }

    /**
     * @return array{tem_detalhe: bool, linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>}
     */
    private function linhasDoPagamentoReceber(ContaReceberPagamento $pagamento, bool $ehEstorno): array
    {
        $conta = $pagamento->contaReceber;
        $cliente = $pagamento->cliente ?: $conta?->cliente;

        return $this->linhas([
            $this->linha('Origem', 'Contas a Receber'),
            $this->linha('Tipo', $ehEstorno ? 'Estorno de recebimento' : 'Recebimento'),
            $this->linha('Cliente', $this->nomePessoa($cliente)),
            $this->linha('Título', $conta?->numero ? (string) $conta->numero : null),
            $this->linha('Documento', filled($conta?->documento) ? mb_strtoupper((string) $conta->documento, 'UTF-8') : null),
            $this->linha('Forma de pagamento', $this->nomeForma($pagamento->formaPagamento?->descricao)),
            $this->linha('Valor original', $this->money((float) $pagamento->valor_parcela), money: true),
            $this->linha('Juros', $this->money((float) $pagamento->juros), money: true),
            $this->linha('Multa', $this->money((float) ($pagamento->multa ?? 0)), money: true),
            $this->linha('Desconto', $this->money((float) $pagamento->desconto), money: true),
            $this->linha('Valor recebido', $this->money((float) $pagamento->valor_recebido), money: true),
            $this->linha('Data', optional($pagamento->data)?->format('d/m/Y')),
            filled($pagamento->numero_cheque)
                ? $this->linha('Cheque', (string) $pagamento->numero_cheque)
                : null,
            $ehEstorno
                ? $this->linha('Estorno', 'Este lançamento é um estorno.', wide: true, destaque: 'estorno')
                : null,
        ]);
    }

    private function encontrarContaPagar(?string $ref): ?ContaPagar
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return null;
        }

        $query = ContaPagar::query()->with('fornecedor:id,nome_razao,apelido_fantasia');

        $porNumero = (clone $query)->where('numero', $ref)->first();
        if ($porNumero) {
            return $porNumero;
        }

        if (ctype_digit($ref)) {
            return (clone $query)->whereKey((int) $ref)->first()
                ?? (clone $query)->where('numero', str_pad($ref, 6, '0', STR_PAD_LEFT))->first();
        }

        return null;
    }

    private function encontrarContaReceber(string $ref): ?ContaReceber
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        $query = ContaReceber::query()->with('cliente:id,nome_razao,apelido_fantasia');

        $porNumero = (clone $query)->where('numero', $ref)->first();
        if ($porNumero) {
            return $porNumero;
        }

        if (ctype_digit($ref)) {
            return (clone $query)->whereKey((int) $ref)->first()
                ?? (clone $query)->where('numero', str_pad($ref, 6, '0', STR_PAD_LEFT))->first();
        }

        return null;
    }

    private function encontrarPagamentoPagar(ContaPagar $conta, CaixaLancamento $lancamento, float $movimento): ?ContaPagarPagamento
    {
        $data = optional($lancamento->emissao)?->toDateString();
        $caixaId = $lancamento->caixa_conta_id ? (int) $lancamento->caixa_conta_id : null;

        $candidatos = ContaPagarPagamento::query()
            ->with([
                'formaPagamento:id,descricao',
                'fornecedor:id,nome_razao,apelido_fantasia',
                'contaPagar:id,numero,documento,valor,fornecedor_id',
                'contaPagar.fornecedor:id,nome_razao,apelido_fantasia',
            ])
            ->where('conta_pagar_id', (int) $conta->id)
            ->whereRaw('ABS(valor_pago - ?) < 0.009', [$movimento])
            ->when($data, fn ($q) => $q->whereDate('data', $data))
            ->when($caixaId, fn ($q) => $q->where('caixa_conta_id', $caixaId))
            ->orderByDesc('id')
            ->get();

        return $candidatos->count() === 1 ? $candidatos->first() : null;
    }

    private function encontrarPagamentoReceber(ContaReceber $conta, CaixaLancamento $lancamento, float $movimento): ?ContaReceberPagamento
    {
        $data = optional($lancamento->emissao)?->toDateString();
        $caixaId = $lancamento->caixa_conta_id ? (int) $lancamento->caixa_conta_id : null;

        $candidatos = ContaReceberPagamento::query()
            ->with([
                'formaPagamento:id,descricao',
                'cliente:id,nome_razao,apelido_fantasia',
                'contaReceber:id,numero,documento,valor,cliente_id',
                'contaReceber.cliente:id,nome_razao,apelido_fantasia',
            ])
            ->where('conta_receber_id', (int) $conta->id)
            ->whereRaw('ABS(valor_recebido - ?) < 0.009', [$movimento])
            ->when($data, fn ($q) => $q->whereDate('data', $data))
            ->when($caixaId, fn ($q) => $q->where('caixa_conta_id', $caixaId))
            ->orderByDesc('id')
            ->get();

        return $candidatos->count() === 1 ? $candidatos->first() : null;
    }

    private function valorMovimento(CaixaLancamento $lancamento): float
    {
        $entrada = round((float) $lancamento->entrada, 2);
        $saida = round((float) $lancamento->saida, 2);

        return $entrada > 0 ? $entrada : $saida;
    }

    private function nomePessoa(?Person $pessoa): ?string
    {
        if (! $pessoa) {
            return null;
        }

        $nome = trim((string) ($pessoa->apelido_fantasia ?: $pessoa->nome_razao ?: ''));

        return $nome !== '' ? mb_strtoupper($nome, 'UTF-8') : null;
    }

    private function nomeForma(?string $descricao): ?string
    {
        $nome = trim((string) $descricao);

        return $nome !== '' ? mb_strtoupper($nome, 'UTF-8') : null;
    }

    private function money(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    /**
     * @param  array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}|null  $linha
     * @return array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}|null
     */
    private function linha(string $label, ?string $value, bool $wide = false, bool $money = false, ?string $destaque = null): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $row = [
            'label' => $label,
            'value' => $value,
        ];

        if ($wide) {
            $row['wide'] = true;
        }

        if ($money) {
            $row['money'] = true;
        }

        if ($destaque !== null) {
            $row['destaque'] = $destaque;
        }

        return $row;
    }

    /**
     * @param  list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}|null>  $linhas
     * @return array{tem_detalhe: bool, linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>}
     */
    private function linhas(array $linhas): array
    {
        $filtradas = array_values(array_filter($linhas));

        return [
            'tem_detalhe' => $filtradas !== [],
            'linhas' => $filtradas,
        ];
    }
}
