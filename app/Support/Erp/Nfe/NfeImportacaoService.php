<?php

namespace App\Support\Erp\Nfe;

use App\Models\Empresa;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\PdvVendaNfce;
use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\VendasInternasOrder;
use App\Support\Erp\ErpMoney;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class NfeImportacaoService
{
    /**
     * @param  array{
     *     numero?: string,
     *     cliente?: string,
     *     data_de?: ?string,
     *     data_ate?: ?string
     * }  $filtros
     * @return list<array<string, mixed>>
     */
    public function listar(string $tipo, ?int $empresaId, array $filtros = [], int $limit = 60): array
    {
        if (! NfeImportacaoTipo::isImplemented($tipo)) {
            return [];
        }

        $numero = trim((string) ($filtros['numero'] ?? ''));
        $cliente = trim((string) ($filtros['cliente'] ?? ''));
        $dataDe = filled($filtros['data_de'] ?? null) ? (string) $filtros['data_de'] : null;
        $dataAte = filled($filtros['data_ate'] ?? null) ? (string) $filtros['data_ate'] : null;

        return match ($tipo) {
            NfeImportacaoTipo::ORCAMENTO => $this->listarOrcamentos($numero, $cliente, $dataDe, $dataAte, $limit),
            NfeImportacaoTipo::VENDA => $this->listarVendas($numero, $cliente, $dataDe, $dataAte, $limit),
            NfeImportacaoTipo::PEDIDO_WEB => $this->listarPedidosWeb($empresaId, $numero, $cliente, $dataDe, $dataAte, $limit),
            NfeImportacaoTipo::NFCE => $this->listarNfce($empresaId, $numero, $cliente, $dataDe, $dataAte, $limit),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detalhe(string $tipo, int $documentId): ?array
    {
        if (! NfeImportacaoTipo::isImplemented($tipo)) {
            return null;
        }

        return match ($tipo) {
            NfeImportacaoTipo::ORCAMENTO => $this->detalheOrcamento($documentId),
            NfeImportacaoTipo::VENDA => $this->detalheVenda($documentId),
            NfeImportacaoTipo::PEDIDO_WEB => $this->detalhePedidoWeb($documentId),
            NfeImportacaoTipo::NFCE => $this->detalheNfce($documentId),
            default => null,
        };
    }

    /**
     * @return array{
     *     cliente_id: ?int,
     *     numero_pedido: ?string,
     *     movimento: string,
     *     forma_pgto: string,
     *     meio_pgto: string,
     *     obs_contribuinte: string,
     *     referencias: list<array{referencia: string}>,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function montarPayload(
        string $tipo,
        int $documentId,
        ?Empresa $empresa,
        ?string $ufDestino,
    ): array {
        $detalhe = $this->detalhe($tipo, $documentId);

        if ($detalhe === null) {
            return [
                'cliente_id' => null,
                'numero_pedido' => null,
                'movimento' => 'saida',
                'forma_pgto' => 'a_vista',
                'meio_pgto' => 'dinheiro',
                'obs_contribuinte' => '',
                'referencias' => [],
                'rows' => [],
            ];
        }

        $rawRows = $detalhe['raw_rows'] ?? [];
        $rows = $this->montarLinhasItens($rawRows, $empresa, $ufDestino);

        return [
            'cliente_id' => $detalhe['cliente_id'] ?? null,
            'numero_pedido' => $detalhe['numero_pedido'] ?? null,
            'movimento' => $detalhe['movimento'] ?? 'saida',
            'forma_pgto' => $detalhe['forma_pgto'] ?? 'a_vista',
            'meio_pgto' => $detalhe['meio_pgto'] ?? 'dinheiro',
            'obs_contribuinte' => $detalhe['obs_contribuinte'] ?? '',
            'referencias' => $detalhe['referencias'] ?? [],
            'rows' => $rows,
        ];
    }

    /**
     * @param list<array{product_id: int, quantidade: float, valor_unitario: float, descricao?: string}> $rawRows
     * @return list<array<string, mixed>>
     */
    protected function montarLinhasItens(array $rawRows, ?Empresa $empresa, ?string $ufDestino): array
    {
        if ($rawRows === []) {
            return [];
        }

        $calcInput = [];

        foreach ($rawRows as $raw) {
            $productId = (int) ($raw['product_id'] ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $calcInput[] = [
                'product_id' => $productId,
                'descricao' => $raw['descricao'] ?? '',
                'quantidade' => (float) ($raw['quantidade'] ?? 0),
                'valor_unitario' => (float) ($raw['valor_unitario'] ?? 0),
                'desconto' => (float) ($raw['desconto'] ?? 0),
            ];
        }

        if ($calcInput === []) {
            return [];
        }

        $calculated = app(NfeCalculoService::class)->calcular($calcInput, $empresa, $ufDestino);
        $rows = [];

        foreach ($calculated['rows'] as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $product = $productId > 0 ? Product::query()->find($productId) : null;

            $rows[] = [
                'key' => 'import-' . Str::uuid()->toString(),
                'product_id' => $productId,
                'codigo' => $product ? (string) $product->codigo : '',
                'descricao' => mb_strtoupper((string) ($row['descricao'] ?? $product?->descricao ?? ''), 'UTF-8'),
                'cfop' => (string) ($row['cfop'] ?? ''),
                'cst' => (string) (($row['cst'] ?? '') ?: ($row['csosn'] ?? '')),
                'quantidade' => ErpMoney::formatBr((float) ($row['quantidade'] ?? 0), 4),
                'valor_unitario' => ErpMoney::formatBr((float) ($row['valor_unitario'] ?? 0), 4),
                'unidade' => mb_strtoupper((string) ($row['unidade'] ?? $product?->unidade ?: 'UN'), 'UTF-8'),
                'desconto' => ErpMoney::formatBr((float) ($row['desconto'] ?? 0), 2),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listarOrcamentos(string $numero, string $cliente, ?string $dataDe, ?string $dataAte, int $limit): array
    {
        $query = Orcamento::query()
            ->with('cliente')
            ->whereNotIn('status', [Orcamento::STATUS_CANCELADO, Orcamento::STATUS_IMPORTADO])
            ->orderByDesc('numero');

        $this->applyNumeroFilter($query, 'numero', $numero);
        $this->applyClienteFilter($query, $cliente);
        $this->applyDataFilter($query, 'data', $dataDe, $dataAte);

        return $query->limit($limit)->get()->map(fn (Orcamento $orcamento): array => [
            'document_id' => $orcamento->id,
            'numero' => $orcamento->numero,
            'data' => $orcamento->data?->format('d/m/Y') ?? '—',
            'cliente' => $orcamento->cliente?->nome_razao ?? '—',
            'total' => ErpMoney::formatBr($orcamento->total),
            'info' => $orcamento->statusLabel(),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listarVendas(string $numero, string $cliente, ?string $dataDe, ?string $dataAte, int $limit): array
    {
        $query = Venda::query()
            ->with('cliente')
            ->semDocumentoFiscalEmitido()
            ->whereIn('status', [Venda::STATUS_FECHADO, Venda::STATUS_GRAVADO])
            ->where(function (Builder $q): void {
                // Pedidos explícitos + pedidos PDV espelhados antigos como cupom (fiscal=false).
                $q->where('tipo', Venda::TIPO_PEDIDO)
                    ->orWhere(function (Builder $legacy): void {
                        $legacy->where('plataforma', Venda::PLATAFORMA_PDV)
                            ->whereHas('pdvVenda', fn (Builder $pdv) => $pdv->where('fiscal', false));
                    });
            })
            ->orderByDesc('data')
            ->orderByDesc('numero');

        $this->applyNumeroFilter($query, 'numero', $numero);
        $this->applyClienteFilter($query, $cliente);
        $this->applyDataFilter($query, 'data', $dataDe, $dataAte);

        return $query->limit($limit)->get()->map(fn (Venda $venda): array => [
            'document_id' => $venda->id,
            'numero' => $venda->numero,
            'data' => $venda->data?->format('d/m/Y') ?? '—',
            'cliente' => $venda->cliente?->nome_razao ?? '—',
            'total' => ErpMoney::formatBr($venda->total),
            'info' => $venda->forma_pagamento ?? '—',
            'cliente_id' => $venda->cliente_id,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listarPedidosWeb(?int $empresaId, string $numero, string $cliente, ?string $dataDe, ?string $dataAte, int $limit): array
    {
        $query = VendasInternasOrder::query()
            ->with(['cliente', 'venda'])
            ->where('tipo', VendasInternasOrder::TIPO_PEDIDO)
            ->whereNotNull('venda_id')
            ->whereNotIn('situacao', [VendasInternasOrder::SITUACAO_CANCELADO])
            ->orderByDesc('id');

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        if ($numero !== '') {
            $like = '%' . mb_strtoupper($numero, 'UTF-8') . '%';

            $query->where(function (Builder $q) use ($like, $numero): void {
                $q->where('id', 'like', $like);

                if (is_numeric($numero)) {
                    $q->orWhere('id', (int) $numero)
                        ->orWhereHas('venda', fn (Builder $v) => $v->where('numero', 'like', $like));
                } else {
                    $q->orWhereHas('venda', fn (Builder $v) => $v->where('numero', 'like', $like));
                }
            });
        }

        if ($cliente !== '') {
            $like = '%' . mb_strtoupper($cliente, 'UTF-8') . '%';
            $query->where(function (Builder $q) use ($like): void {
                $q->whereHas('cliente', function (Builder $c) use ($like): void {
                    $c->whereRaw('UPPER(nome_razao) LIKE ?', [$like]);
                })->orWhereHas('venda.cliente', function (Builder $c) use ($like): void {
                    $c->whereRaw('UPPER(nome_razao) LIKE ?', [$like]);
                });
            });
        }

        if ($dataDe || $dataAte) {
            $query->whereHas('venda', function (Builder $v) use ($dataDe, $dataAte): void {
                $this->applyDataFilter($v, 'data', $dataDe, $dataAte);
            });
        }

        return $query->limit($limit)->get()->map(fn (VendasInternasOrder $pedido): array => [
            'document_id' => $pedido->id,
            'numero' => $pedido->venda?->numero ?? (string) $pedido->id,
            'data' => $pedido->dataAberturaAt()?->format('d/m/Y') ?? '—',
            'cliente' => $pedido->clienteNome(),
            'total' => ErpMoney::formatBr($pedido->total),
            'info' => $pedido->situacaoLabel(),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listarNfce(?int $empresaId, string $numero, string $cliente, ?string $dataDe, ?string $dataAte, int $limit): array
    {
        $query = PdvVendaNfce::query()
            ->with(['pdvVenda.person'])
            ->whereNull('nfe_id')
            ->whereIn('status', [
                PdvVendaNfce::STATUS_AUTORIZADA,
                PdvVendaNfce::STATUS_SIMULADA,
                PdvVendaNfce::STATUS_CONTINGENCIA,
            ])
            ->orderByDesc('numero');

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        if ($numero !== '') {
            $like = '%' . mb_strtoupper($numero, 'UTF-8') . '%';

            $query->where(function (Builder $q) use ($like, $numero): void {
                $q->where('numero', 'like', $like)
                    ->orWhere('chave', 'like', $like);

                if (is_numeric($numero)) {
                    $q->orWhere('numero', (int) $numero);
                }
            });
        }

        if ($cliente !== '') {
            $like = '%' . mb_strtoupper($cliente, 'UTF-8') . '%';
            $query->whereHas('pdvVenda.person', function (Builder $person) use ($like): void {
                $person->whereRaw('UPPER(nome_razao) LIKE ?', [$like]);
            });
        }

        $this->applyDataFilter($query, 'autorizada_em', $dataDe, $dataAte);

        return $query->limit($limit)->get()->map(fn (PdvVendaNfce $nfce): array => [
            'document_id' => $nfce->id,
            'numero' => str_pad((string) $nfce->numero, 6, '0', STR_PAD_LEFT),
            'data' => $nfce->autorizada_em?->format('d/m/Y') ?? '—',
            'cliente' => $nfce->pdvVenda?->person?->nome_razao ?? 'CONSUMIDOR',
            'total' => ErpMoney::formatBr($nfce->pdvVenda?->total ?? 0),
            'info' => mb_strtoupper($nfce->status, 'UTF-8'),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function detalheOrcamento(int $id): ?array
    {
        $orcamento = Orcamento::query()
            ->with(['cliente', 'itens.product'])
            ->find($id);

        if (! $orcamento) {
            return null;
        }

        return [
            'tipo' => NfeImportacaoTipo::ORCAMENTO,
            'document_id' => $orcamento->id,
            'numero' => $orcamento->numero,
            'cliente' => $orcamento->cliente?->nome_razao ?? '—',
            'total' => ErpMoney::formatBr($orcamento->total),
            'cliente_id' => $orcamento->cliente_id,
            'numero_pedido' => $orcamento->numero,
            'movimento' => 'saida',
            'forma_pgto' => $this->mapFormaPgto($orcamento->forma_pagamento),
            'meio_pgto' => 'dinheiro',
            'obs_contribuinte' => filled($orcamento->observacoes)
                ? 'Importado do orçamento nº ' . $orcamento->numero . '. ' . $orcamento->observacoes
                : 'Importado do orçamento nº ' . $orcamento->numero . '.',
            'referencias' => [],
            'itens' => $this->mapItensExibicaoOrcamento($orcamento),
            'raw_rows' => $this->mapRawRowsOrcamento($orcamento),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function detalheVenda(int $id): ?array
    {
        $venda = Venda::query()
            ->with(['cliente', 'itens.product'])
            ->find($id);

        if (! $venda) {
            return null;
        }

        return [
            'tipo' => NfeImportacaoTipo::VENDA,
            'document_id' => $venda->id,
            'numero' => $venda->numero,
            'cliente' => $venda->cliente?->nome_razao ?? '—',
            'total' => ErpMoney::formatBr($venda->total),
            'cliente_id' => $venda->cliente_id,
            'numero_pedido' => $venda->numero,
            'movimento' => 'saida',
            'forma_pgto' => $this->mapFormaPgto($venda->forma_pagamento),
            'meio_pgto' => 'dinheiro',
            'obs_contribuinte' => 'Importado do pedido nº ' . $venda->numero . '.',
            'referencias' => [],
            'itens' => $this->mapItensExibicaoVenda($venda),
            'raw_rows' => $this->mapRawRowsVenda($venda),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function detalhePedidoWeb(int $id): ?array
    {
        $pedido = VendasInternasOrder::query()
            ->with(['cliente', 'venda.itens.product'])
            ->find($id);

        if (! $pedido || ! $pedido->venda_id) {
            return null;
        }

        $venda = $pedido->venda;

        if (! $venda) {
            return null;
        }

        $detalhe = $this->detalheVenda((int) $venda->id);
        $detalhe['tipo'] = NfeImportacaoTipo::PEDIDO_WEB;
        $detalhe['document_id'] = $pedido->id;
        $detalhe['numero'] = $venda->numero;
        $detalhe['obs_contribuinte'] = 'Importado do pedido web nº ' . $pedido->id . ' (venda ' . $venda->numero . ').';

        return $detalhe;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function detalheNfce(int $id): ?array
    {
        $nfce = PdvVendaNfce::query()
            ->with(['pdvVenda.person', 'pdvVenda.itens.product'])
            ->find($id);

        if (! $nfce || ! $nfce->pdvVenda) {
            return null;
        }

        $pdvVenda = $nfce->pdvVenda;
        $clienteId = $pdvVenda->person_id;

        return [
            'tipo' => NfeImportacaoTipo::NFCE,
            'document_id' => $nfce->id,
            'numero' => str_pad((string) $nfce->numero, 6, '0', STR_PAD_LEFT),
            'cliente' => $pdvVenda->person?->nome_razao ?? 'CONSUMIDOR',
            'total' => ErpMoney::formatBr($pdvVenda->total),
            'cliente_id' => $clienteId,
            'numero_pedido' => $pdvVenda->venda_id
                ? (string) (Venda::query()->whereKey($pdvVenda->venda_id)->value('numero') ?? '')
                : null,
            'movimento' => 'saida',
            'forma_pgto' => $this->mapFormaPgto($pdvVenda->forma_pagamento),
            'meio_pgto' => 'dinheiro',
            'obs_contribuinte' => 'Importado da NFC-e nº ' . $nfce->numero . '.',
            'referencias' => filled($nfce->chave) ? [['referencia' => $nfce->chave]] : [],
            'itens' => $this->mapItensExibicaoPdv($pdvVenda),
            'raw_rows' => $this->mapRawRowsPdv($pdvVenda),
        ];
    }

    /**
     * @return list<array{descricao: string, quantidade: string, total: string}>
     */
    protected function mapItensExibicaoOrcamento(Orcamento $orcamento): array
    {
        return $orcamento->itens->map(fn (OrcamentoItem $item): array => [
            'descricao' => $item->descricao ?: ($item->product?->descricao ?? '—'),
            'quantidade' => number_format((float) $item->quantidade, 3, ',', '.'),
            'total' => ErpMoney::formatBr($item->total),
        ])->values()->all();
    }

    /**
     * @return list<array{product_id: int, quantidade: float, valor_unitario: float, descricao?: string, desconto?: float}>
     */
    protected function mapRawRowsOrcamento(Orcamento $orcamento): array
    {
        return $orcamento->itens
            ->filter(fn (OrcamentoItem $item): bool => (int) $item->product_id > 0)
            ->map(fn (OrcamentoItem $item): array => [
                'product_id' => (int) $item->product_id,
                'quantidade' => (float) $item->quantidade,
                'valor_unitario' => (float) $item->preco_unitario,
                'descricao' => $item->descricao ?: ($item->product?->descricao ?? ''),
                'desconto' => (float) ($item->desconto ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{descricao: string, quantidade: string, total: string}>
     */
    protected function mapItensExibicaoVenda(Venda $venda): array
    {
        return $venda->itens->map(function (VendaItem $item): array {
            $descricao = $item->product?->descricao ?? '—';

            return [
                'descricao' => $descricao,
                'quantidade' => number_format((float) $item->quantidade, 3, ',', '.'),
                'total' => ErpMoney::formatBr($item->total),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{product_id: int, quantidade: float, valor_unitario: float, descricao?: string}>
     */
    protected function mapRawRowsVenda(Venda $venda): array
    {
        return $venda->itens
            ->filter(fn (VendaItem $item): bool => (int) $item->product_id > 0)
            ->map(function (VendaItem $item): array {
                $qtd = (float) $item->quantidade;
                $valorUnit = $qtd > 0 ? round((float) $item->total / $qtd, 4) : (float) $item->valor_item;

                return [
                    'product_id' => (int) $item->product_id,
                    'quantidade' => $qtd,
                    'valor_unitario' => $valorUnit,
                    'descricao' => $item->product?->descricao ?? '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{descricao: string, quantidade: string, total: string}>
     */
    protected function mapItensExibicaoPdv(PdvVenda $pdvVenda): array
    {
        return $pdvVenda->itens->map(fn (PdvVendaItem $item): array => [
            'descricao' => $item->descricao ?: ($item->product?->descricao ?? '—'),
            'quantidade' => number_format((float) $item->quantidade, 3, ',', '.'),
            'total' => ErpMoney::formatBr($item->total),
        ])->values()->all();
    }

    /**
     * @return list<array{product_id: int, quantidade: float, valor_unitario: float, descricao?: string, desconto?: float}>
     */
    protected function mapRawRowsPdv(PdvVenda $pdvVenda): array
    {
        return $pdvVenda->itens
            ->filter(fn (PdvVendaItem $item): bool => (int) $item->product_id > 0)
            ->map(fn (PdvVendaItem $item): array => [
                'product_id' => (int) $item->product_id,
                'quantidade' => (float) $item->quantidade,
                'valor_unitario' => (float) $item->preco_unitario,
                'descricao' => $item->descricao ?: ($item->product?->descricao ?? ''),
                'desconto' => (float) ($item->desconto ?? 0),
            ])
            ->values()
            ->all();
    }

    protected function mapFormaPgto(?string $forma): string
    {
        $normalized = mb_strtolower(trim((string) $forma), 'UTF-8');

        if (str_contains($normalized, 'prazo') || str_contains($normalized, 'parcel')) {
            return 'a_prazo';
        }

        return 'a_vista';
    }

    /**
     * Monta payload unificado a partir de um ou mais documentos (mesma NF-e).
     *
     * @param  list<int>  $documentIds
     * @return array{
     *     cliente_id: ?int,
     *     numero_pedido: ?string,
     *     movimento: string,
     *     forma_pgto: string,
     *     meio_pgto: string,
     *     obs_contribuinte: string,
     *     referencias: list<array{referencia: string}>,
     *     rows: list<array<string, mixed>>,
     *     numeros: list<string>
     * }
     */
    public function montarPayloadMultiplo(
        string $tipo,
        array $documentIds,
        ?Empresa $empresa,
        ?string $ufDestino,
    ): array {
        $documentIds = array_values(array_unique(array_filter(
            array_map('intval', $documentIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($documentIds === []) {
            return [
                'cliente_id' => null,
                'numero_pedido' => null,
                'movimento' => 'saida',
                'forma_pgto' => 'a_vista',
                'meio_pgto' => 'dinheiro',
                'obs_contribuinte' => '',
                'referencias' => [],
                'rows' => [],
                'numeros' => [],
            ];
        }

        $detalhes = [];

        foreach ($documentIds as $documentId) {
            $detalhe = $this->detalhe($tipo, $documentId);

            if ($detalhe !== null) {
                $detalhes[] = $detalhe;
            }
        }

        if ($detalhes === []) {
            return $this->montarPayloadMultiplo($tipo, [], $empresa, $ufDestino);
        }

        $numeros = [];
        $rawRows = [];
        $referencias = [];
        $obsExtras = [];
        $clienteId = null;
        $formaPgto = 'a_vista';
        $meioPgto = 'dinheiro';
        $movimento = 'saida';

        foreach ($detalhes as $index => $detalhe) {
            $numero = trim((string) ($detalhe['numero'] ?? $detalhe['numero_pedido'] ?? ''));

            if ($numero !== '') {
                $numeros[] = $numero;
            }

            if ($index === 0) {
                $clienteId = $detalhe['cliente_id'] ?? null;
                $formaPgto = $detalhe['forma_pgto'] ?? 'a_vista';
                $meioPgto = $detalhe['meio_pgto'] ?? 'dinheiro';
                $movimento = $detalhe['movimento'] ?? 'saida';
            }

            foreach ($detalhe['raw_rows'] ?? [] as $raw) {
                $rawRows[] = $raw;
            }

            foreach ($detalhe['referencias'] ?? [] as $referencia) {
                $chave = trim((string) ($referencia['referencia'] ?? ''));

                if ($chave === '') {
                    continue;
                }

                $exists = collect($referencias)->contains(
                    fn (array $ref): bool => ($ref['referencia'] ?? '') === $chave,
                );

                if (! $exists) {
                    $referencias[] = ['referencia' => $chave];
                }
            }

            $obs = trim((string) ($detalhe['obs_contribuinte'] ?? ''));

            if ($obs !== '' && ! str_starts_with(mb_strtoupper($obs, 'UTF-8'), 'IMPORTADO DO')) {
                $obsExtras[] = $obs;
            }
        }

        $numeros = array_values(array_unique($numeros));
        $listaNumeros = implode(', ', $numeros);
        $obsContribuinte = match (true) {
            count($numeros) > 1 => 'Importado dos pedidos nº ' . $listaNumeros . '.',
            count($numeros) === 1 => 'Importado do pedido nº ' . $listaNumeros . '.',
            default => '',
        };

        if ($obsExtras !== []) {
            $obsContribuinte = trim($obsContribuinte . ' ' . implode(' ', $obsExtras));
        }

        return [
            'cliente_id' => $clienteId,
            'numero_pedido' => $listaNumeros !== '' ? $listaNumeros : null,
            'movimento' => $movimento,
            'forma_pgto' => $formaPgto,
            'meio_pgto' => $meioPgto,
            'obs_contribuinte' => $obsContribuinte,
            'referencias' => $referencias,
            'rows' => $this->montarLinhasItens($rawRows, $empresa, $ufDestino),
            'numeros' => $numeros,
        ];
    }

    protected function applyNumeroFilter(Builder $query, string $column, string $numero): void
    {
        $numero = trim($numero);

        if ($numero === '') {
            return;
        }

        $like = '%' . mb_strtoupper($numero, 'UTF-8') . '%';

        $query->where(function (Builder $q) use ($column, $like, $numero): void {
            $q->where($column, 'like', $like);

            if (ctype_digit($numero)) {
                $q->orWhere($column, (int) $numero)
                    ->orWhere($column, ltrim($numero, '0') ?: '0');
            }
        });
    }

    protected function applyClienteFilter(Builder $query, string $cliente): void
    {
        $cliente = trim($cliente);

        if ($cliente === '') {
            return;
        }

        $like = '%' . mb_strtoupper($cliente, 'UTF-8') . '%';

        $query->whereHas('cliente', function (Builder $builder) use ($like): void {
            $builder->whereRaw('UPPER(nome_razao) LIKE ?', [$like]);
        });
    }

    protected function applyDataFilter(Builder $query, string $column, ?string $dataDe, ?string $dataAte): void
    {
        if (filled($dataDe)) {
            $query->whereDate($column, '>=', $dataDe);
        }

        if (filled($dataAte)) {
            $query->whereDate($column, '<=', $dataAte);
        }
    }
}
