<?php

namespace App\Support\Erp\Nfe;

use App\Models\Cfop;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\ForcaVendasOrder;
use App\Models\Nfe;
use App\Models\OperacaoFiscal;
use App\Models\Person;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class NfeVendaMercadoriaService
{
    public function validar(Venda $venda, ?Empresa $empresaEmitente = null): void
    {
        $venda->loadMissing(['itens', 'cliente', 'vendedor', 'forcaVendasOrder.pedido']);

        if ((int) ($venda->cliente_id ?? 0) <= 0) {
            throw new RuntimeException('A venda não possui cliente vinculado.');
        }

        if ($venda->itens->isEmpty()) {
            throw new RuntimeException('A venda não possui itens para emitir NF-e.');
        }

        $order = $venda->forcaVendasOrder;

        if ($order && $order->situacao !== ForcaVendasOrder::SITUACAO_FATURADO) {
            throw new RuntimeException('Somente pedido faturado pode gerar NF-e pelo Monitor de Vendas.');
        }

        $empresa = $empresaEmitente ?? $this->resolveEmpresaOrigem($venda, $order);

        if ($empresa === null) {
            throw new RuntimeException('Empresa não identificada para a venda.');
        }

        $this->assertClienteAptoParaNfe($venda->cliente);
        $this->resolveCfop($empresa, $venda->cliente);

        if ($this->temNfeAtiva($venda)) {
            throw new RuntimeException('Esta venda já possui NF-e vinculada.');
        }
    }

    /**
     * Cliente mínimo para NF-e (não consumidor; documento e endereço completos).
     */
    public function assertClienteAptoParaNfe(?Person $cliente): void
    {
        if (! $cliente) {
            throw new RuntimeException('A venda não possui cliente vinculado.');
        }

        if (Person::isCodigoConsumidorFinal($cliente->codigo)) {
            throw new RuntimeException('Consumidor final não pode emitir NF-e. Cadastre o cliente completo.');
        }

        $nome = mb_strtoupper(trim((string) ($cliente->nome_razao ?? '')), 'UTF-8');

        if ($nome === '' || $nome === 'CONSUMIDOR FINAL') {
            throw new RuntimeException('Cliente inválido para NF-e (consumidor final).');
        }

        $digits = preg_replace('/\D/', '', (string) ($cliente->cpf_cnpj ?? '')) ?? '';

        if (strlen($digits) !== 11 && strlen($digits) !== 14) {
            throw new RuntimeException(
                'Cliente sem CPF/CNPJ válido para NF-e: '.trim((string) ($cliente->nome_razao ?? '—')).'.'
            );
        }

        $faltando = [];

        if (trim((string) ($cliente->endereco ?? '')) === '') {
            $faltando[] = 'endereço';
        }

        if (trim((string) ($cliente->bairro ?? '')) === '') {
            $faltando[] = 'bairro';
        }

        if (trim((string) ($cliente->uf ?? '')) === '') {
            $faltando[] = 'UF';
        }

        $cep = preg_replace('/\D/', '', (string) ($cliente->cep ?? '')) ?? '';

        if (strlen($cep) !== 8) {
            $faltando[] = 'CEP';
        }

        if (trim((string) ($cliente->cidade_nome ?? '')) === '') {
            $faltando[] = 'cidade';
        }

        if (! \App\Support\Erp\CepLookupService::isValidIbgeCode($cliente->cidade_codigo ?? null)) {
            $faltando[] = 'código IBGE da cidade';
        }

        if ($faltando !== []) {
            throw new RuntimeException(
                'Cliente incompleto para NF-e ('.trim((string) ($cliente->nome_razao ?? '—')).'): '
                .implode(', ', $faltando).'.'
            );
        }
    }

    /**
     * Motivo curto se o cliente/venda não estiver apto (null = apto).
     */
    public function motivoInaptoParaNfe(Venda $venda): ?string
    {
        try {
            $this->validar($venda);

            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * @return array{
     *     venda_id: int,
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
     *     rows: list<array{product_id: int, quantidade: float, valor_unitario: float, descricao: string, cfop: string}>
     * }
     */
    /**
     * @param  Empresa|null  $empresaEmitente  Quando informada (Monitor multi-empresa), CFOP/natureza usam esta empresa; itens/valores/faturas continuam da venda da Matriz.
     */
    public function montarPayload(Venda $venda, ?Empresa $empresaEmitente = null): array
    {
        $this->validar($venda, $empresaEmitente);

        $venda->loadMissing(['itens.product', 'cliente', 'vendedor', 'forcaVendasOrder.pedido']);

        $order = $venda->forcaVendasOrder;
        $empresa = $empresaEmitente ?? $this->resolveEmpresaOrigem($venda, $order);
        $cliente = $venda->cliente;
        $cfop = $this->resolveCfop($empresa, $cliente);
        $natureza = $this->formatNaturezaOperacao($cfop);
        $data = $venda->data?->format('Y-m-d') ?? now()->format('Y-m-d');
        $numeroPedido = $this->resolveNumeroPedido($venda, $order);
        $formaPayload = is_array($order?->payload) ? ($order->payload['forma_pagamento'] ?? null) : null;
        $formaLabel = (string) ($venda->forma_pagamento ?: $formaPayload ?: '');

        $rows = [];

        foreach ($venda->itens as $item) {
            if (! $item instanceof VendaItem) {
                continue;
            }

            $productId = (int) ($item->product_id ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $qtd = (float) $item->quantidade;
            $valorUnit = $qtd > 0
                ? round((float) $item->total / $qtd, 4)
                : (float) $item->valor_item;

            $rows[] = [
                'product_id' => $productId,
                'quantidade' => $qtd,
                'valor_unitario' => $valorUnit,
                'descricao' => (string) ($item->product?->descricao ?? ''),
                'cfop' => (string) $cfop,
                'pedido' => $numeroPedido,
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('Nenhum item da venda possui produto vinculado para NF-e.');
        }

        $vendedorNome = mb_strtoupper(trim($venda->vendedorNome()), 'UTF-8');
        $obs = 'Pedido FV / DAV nº '.$numeroPedido.'.';

        if ($vendedorNome !== '' && $vendedorNome !== 'SEM OPERADOR') {
            $obs .= ' Vendedor: '.$vendedorNome.'.';
        }

        $transporte = is_array($order?->payload['transporte'] ?? null)
            ? $order->payload['transporte']
            : null;

        return [
            'venda_id' => (int) $venda->id,
            'cliente_id' => (int) $venda->cliente_id,
            'finalidade' => 'normal',
            'movimento' => 'saida',
            'data_emissao' => $data,
            'data_saida' => $data,
            'numero_pedido' => $numeroPedido,
            'natureza_operacao' => $natureza,
            'forma_pgto' => $this->mapFormaPgto($formaLabel),
            'meio_pgto' => $this->mapMeioPgto($formaLabel),
            'obs_contribuinte' => $obs,
            'faturas' => $this->montarFaturas($order),
            'rows' => $rows,
            'transporte' => $transporte,
        ];
    }

    public function temNfeAtiva(Venda $venda): bool
    {
        return Nfe::query()
            ->where('venda_id', $venda->id)
            ->whereNotIn('status', [
                Nfe::STATUS_CANCELADA,
                Nfe::STATUS_INUTILIZADA,
            ])
            ->exists();
    }

    /**
     * Empresa de origem do pedido/venda (Matriz) — estoque/financeiro. Não é a emitente fiscal.
     */
    protected function resolveEmpresaOrigem(Venda $venda, ?ForcaVendasOrder $order): ?Empresa
    {
        $empresaId = (int) ($order?->empresa_id ?? $venda->empresa_id ?? ErpContext::currentEmpresaId() ?? 0);

        return $empresaId > 0 ? Empresa::query()->find($empresaId) : null;
    }

    /** @deprecated Use resolveEmpresaOrigem */
    protected function resolveEmpresa(Venda $venda, ?ForcaVendasOrder $order): ?Empresa
    {
        return $this->resolveEmpresaOrigem($venda, $order);
    }

    protected function resolveNumeroPedido(Venda $venda, ?ForcaVendasOrder $order): string
    {
        $dav = trim((string) ($order?->pedido?->numero ?? ''));

        if ($dav !== '') {
            $digits = ltrim(preg_replace('/\D/', '', $dav) ?? '', '0');

            return $digits !== '' ? $digits : $dav;
        }

        $numero = ltrim((string) ($venda->numero ?? ''), '0');

        return $numero !== '' ? $numero : (string) ($venda->numero ?? '');
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

    /**
     * @return list<array{numero: string, data_vencimento: string, valor: string}>
     */
    protected function montarFaturas(?ForcaVendasOrder $order): array
    {
        if ($order === null) {
            return [];
        }

        $contas = $this->contasDoPedido($order)
            ->orderBy('vencimento')
            ->orderBy('id')
            ->get();

        if ($contas->isEmpty()) {
            return [];
        }

        $faturas = [];

        foreach ($contas->values() as $index => $conta) {
            /** @var ContaReceber $conta */
            $faturas[] = [
                'numero' => str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'data_vencimento' => $conta->vencimento?->format('Y-m-d') ?? '',
                'valor' => ErpMoney::formatBr((float) ($conta->valor ?? 0)),
            ];
        }

        return $faturas;
    }

    protected function contasDoPedido(ForcaVendasOrder $order): Builder
    {
        $base = 'FV-'.$order->id;

        return ContaReceber::query()->where(fn (Builder $q) => $q
            ->where('documento', $base)
            ->orWhere('documento', 'like', $base.'/%'));
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
            str_contains($normalized, 'carteira') || str_contains($normalized, 'duplicata') || str_contains($normalized, 'prazo') => 'credito_loja',
            default => 'dinheiro',
        };
    }
}
