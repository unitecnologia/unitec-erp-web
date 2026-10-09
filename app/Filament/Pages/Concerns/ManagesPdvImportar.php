<?php

namespace App\Filament\Pages\Concerns;

use App\Livewire\Erp\PdvHotPath;
use App\Models\Orcamento;
use App\Models\Product;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Orcamento\OrcamentoDescontoService;
use App\Support\Erp\Orcamento\OrcamentoFaturamentoGuard;
use App\Support\Erp\Pdv\PdvImportarPedidoQuery;
use App\Support\Erp\Pdv\PdvImportReserva;
use App\Support\VendasInternas\VendasInternasPdvHookService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
/**
 * Importar no PDV: Pedido (F2), Orçamento (F3) e OS (F4, ainda não implementada).
 *
 * A importação só monta o cupom e reserva o documento para esta sessão de caixa
 * (PdvImportReserva). Estoque, financeiro e status do documento só mudam na
 * finalização da venda, que revalida o documento com lock de linha.
 */
trait ManagesPdvImportar
{
    public const IMPORTAR_PEDIDO = 'pedido';

    public const IMPORTAR_ORCAMENTO = 'orcamento';

    public const IMPORTAR_ORDEM_SERVICO = 'ordem_servico';

    /**
     * Orçamento é exceção ao filtro "somente aberto" (pedido/OS): qualquer status vale,
     * exceto já faturado (importado) ou cancelado. Ver orcamentosImportaveisQuery().
     */
    public const ORCAMENTO_STATUS_BLOQUEADOS = OrcamentoFaturamentoGuard::STATUS_BLOQUEADOS;

    public string $importarSearch = '';

    public ?string $importarTipo = null;

    /** @var array<int, array<string, mixed>> */
    public array $importarResults = [];

    public ?int $selectedImportarIndex = null;

    public ?int $selectedImportarMenuIndex = 0;

    public string $importarPedidoNumero = '';

    public string $importarPedidoDe = '';

    public string $importarPedidoAte = '';

    /** @var array<int, array<string, mixed>> */
    public array $importarPedidoResults = [];

    public ?int $selectedImportarPedidoIndex = null;

    /**
     * @return list<array{key: string, fn: string, label: string}>
     */
    public function getImportarMenuOptionsProperty(): array
    {
        return [
            ['key' => self::IMPORTAR_PEDIDO, 'fn' => 'F2', 'label' => 'Pedido'],
            ['key' => self::IMPORTAR_ORCAMENTO, 'fn' => 'F3', 'label' => 'Orçamento'],
            ['key' => self::IMPORTAR_ORDEM_SERVICO, 'fn' => 'F4', 'label' => 'Ordem de Serviço'],
        ];
    }

    public function getImportarTituloProperty(): string
    {
        return match ($this->importarTipo) {
            self::IMPORTAR_PEDIDO => 'F2 — Importar Pedido',
            self::IMPORTAR_ORCAMENTO => 'F3 — Importar Orçamento',
            self::IMPORTAR_ORDEM_SERVICO => 'F4 — Importar Ordem de Serviço',
            default => 'Importar',
        };
    }

    public function openImportarModal(): void
    {
        if (! $this->assertPodeImportar()) {
            return;
        }

        $this->importarTipo = null;
        $this->importarSearch = '';
        $this->importarResults = [];
        $this->selectedImportarIndex = null;
        $this->selectedImportarMenuIndex = 0;
        $this->openPdvModal('importar_menu');
        $this->dispatch('erp-pdv-focus-importar-menu');
    }

    public function selectImportarMenuRow(int $index): void
    {
        if (isset($this->importarMenuOptions[$index])) {
            $this->selectedImportarMenuIndex = $index;
        }
    }

    public function moveImportarMenuSelection(int $delta): void
    {
        $count = count($this->importarMenuOptions);

        if ($count === 0) {
            return;
        }

        $index = ($this->selectedImportarMenuIndex ?? 0) + $delta;
        $this->selectedImportarMenuIndex = max(0, min($count - 1, $index));
    }

    public function confirmImportarMenuSelection(): void
    {
        $index = $this->selectedImportarMenuIndex ?? 0;
        $option = $this->importarMenuOptions[$index] ?? null;

        if ($option === null) {
            $this->notifyPdvError('Selecione o que deseja importar.');

            return;
        }

        $this->selectImportarTipo($option['key']);
    }

    public function selectImportarTipo(string $tipo): void
    {
        if (! in_array($tipo, [
            self::IMPORTAR_PEDIDO,
            self::IMPORTAR_ORCAMENTO,
            self::IMPORTAR_ORDEM_SERVICO,
        ], true)) {
            return;
        }

        if ($tipo === self::IMPORTAR_ORDEM_SERVICO) {
            // Ainda não implementada: só avisa, sem abrir lista nem alterar o cupom.
            // Ao implementar: listar somente OS ABERTAS (aberta/andamento), sem faturamento
            // (OsFaturamentoService) nem pagamento concluído; reserva + lock na finalização.
            $this->modulePending('Importar Ordem de Serviço');

            return;
        }

        if (! $this->assertPodeImportar()) {
            return;
        }

        if ($tipo === self::IMPORTAR_PEDIDO) {
            $this->openImportarPedidoModal();

            return;
        }

        $this->importarTipo = $tipo;
        $this->importarSearch = '';
        $this->refreshImportarResults();
        $this->activeModal = 'importar';
        $this->dispatch('erp-pdv-focus-importar');
    }

    public function openImportarPedidoModal(): void
    {
        $hoje = now()->format('d/m/Y');
        $this->importarTipo = self::IMPORTAR_PEDIDO;
        $this->importarPedidoNumero = '';
        $this->importarPedidoDe = $hoje;
        $this->importarPedidoAte = $hoje;
        $this->importarPedidoResults = [];
        $this->selectedImportarPedidoIndex = null;
        $this->refreshImportarPedidoResults();
        $this->activeModal = 'importar_pedido';
        $this->dispatch('erp-pdv-focus-importar-pedido');
    }

    public function updatedImportarPedidoNumero(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->importarPedidoNumero !== $upper) {
            $this->importarPedidoNumero = $upper;
        }
    }

    public function refreshImportarPedidoResults(): void
    {
        $query = new PdvImportarPedidoQuery(
            numero: $this->importarPedidoNumero,
            dataDe: $this->parseImportarPedidoDate($this->importarPedidoDe),
            dataAte: $this->parseImportarPedidoDate($this->importarPedidoAte),
        );

        $this->importarPedidoResults = $query->build()
            ->limit(100)
            ->get()
            ->map(fn (Venda $venda): array => [
                'venda_id' => $venda->id,
                'numero' => $venda->numero,
                'cliente' => mb_strtoupper($venda->cliente?->nome_razao ?? '—', 'UTF-8'),
                'data' => $venda->data?->format('d/m/Y') ?? '',
                'total' => ErpMoney::formatBr($venda->total),
                'cancelado' => $venda->status === Venda::STATUS_CANCELADO,
            ])
            ->values()
            ->all();

        $this->selectedImportarPedidoIndex = $this->importarPedidoResults === [] ? null : 0;
    }

    public function selectImportarPedidoRow(int $index): void
    {
        if (isset($this->importarPedidoResults[$index])) {
            $this->selectedImportarPedidoIndex = $index;
        }
    }

    public function moveImportarPedidoSelection(int $delta): void
    {
        if ($this->importarPedidoResults === []) {
            return;
        }

        $count = count($this->importarPedidoResults);
        $index = ($this->selectedImportarPedidoIndex ?? 0) + $delta;
        $this->selectedImportarPedidoIndex = max(0, min($count - 1, $index));
    }

    public function confirmImportarPedido(): void
    {
        $index = $this->selectedImportarPedidoIndex;

        if ($index === null || ! isset($this->importarPedidoResults[$index])) {
            $this->notifyPdvError('Selecione um pedido.');

            return;
        }

        if (! $this->assertPodeImportar()) {
            return;
        }

        $vendaId = (int) ($this->importarPedidoResults[$index]['venda_id'] ?? 0);
        $venda = (new PdvImportarPedidoQuery())->build()
            ->with(['itens.product', 'cliente', 'vendedor'])
            ->whereKey($vendaId)
            ->first();

        if (! $venda) {
            $this->notifyPdvError(
                'Pedido indisponível para importação.',
                'Já faturado, cancelado, com documento fiscal ou vinculado a outra venda.',
            );
            $this->refreshImportarPedidoResults();

            return;
        }

        if ($venda->itens->isEmpty()) {
            $this->notifyPdvError('Pedido sem itens cadastrados.');

            return;
        }

        if ($erro = $this->reservarDocumentoImportado(PdvImportReserva::PEDIDO, (int) $venda->id)) {
            $this->notifyPdvError('Pedido em uso.', $erro);

            return;
        }

        try {
            $linhas = [];

            foreach ($venda->itens as $item) {
                $quantidade = (float) $item->quantidade;
                $preco = (float) $item->valor_item;
                $total = (float) $item->total > 0 ? (float) $item->total : round($quantidade * $preco, 2);

                $linhas[] = [
                    'product' => $item->product,
                    'grade_id' => null,
                    'quantidade' => $quantidade,
                    'preco_bruto' => $preco,
                    'total' => $total,
                    'descricao' => $item->product?->descricao ?? 'Item inválido',
                    'exige_grade' => true,
                ];
            }

            $resultado = $this->carregarItensImportados($linhas, (float) $venda->total);

            if ($resultado['importados'] === 0) {
                $this->desfazerImportacao(PdvImportReserva::PEDIDO, (int) $venda->id);
                $this->notifyPdvError('Nenhum item pôde ser importado.', $this->resumoIgnorados($resultado['ignorados']));

                return;
            }

            $cliente = $venda->cliente;

            session([
                'erp.pdv.venda_id' => $venda->id,
                'erp.pdv.import_cliente_id' => $cliente?->id,
                'erp.pdv.import_cliente_nome' => mb_strtoupper($cliente?->nome_razao ?? 'CONSUMIDOR FINAL', 'UTF-8'),
                'erp.pdv.import_desconto_venda' => $resultado['desconto'],
                'erp.pdv.import_acrescimo_venda' => $resultado['acrescimo'],
            ]);

            if ($venda->vendedor) {
                $this->applyImportVendedor($venda->vendedor);
            } elseif (filled($venda->vendedor_nome)) {
                $this->vendedor = mb_strtoupper((string) $venda->vendedor_nome, 'UTF-8');
                $this->vendedorId = $venda->vendedor_id;
                $this->persistVendedorToSession();
            }
        } catch (\Throwable $e) {
            $this->falhaImportacao(PdvImportReserva::PEDIDO, (int) $venda->id, $e);

            return;
        }

        $this->concluirImportacao('Pedido Nº '.$venda->numero.' importado.', $resultado);
    }

    protected function parseImportarPedidoDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            if (str_contains($value, '/')) {
                return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function backImportarMenu(): void
    {
        $this->importarTipo = null;
        $this->importarSearch = '';
        $this->importarResults = [];
        $this->selectedImportarIndex = null;
        $this->importarPedidoNumero = '';
        $this->importarPedidoDe = '';
        $this->importarPedidoAte = '';
        $this->importarPedidoResults = [];
        $this->selectedImportarPedidoIndex = null;
        $this->selectedImportarMenuIndex = 0;
        $this->activeModal = 'importar_menu';
        $this->dispatch('erp-pdv-focus-importar-menu');
    }

    protected function assertPodeImportar(): bool
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            $this->notifyPdvError('Caixa fechado.');

            return false;
        }

        if ($this->mesaBloqueiaOperacao('Importação')) {
            return false;
        }

        if ($this->cupomTemItens()) {
            $this->notifyPdvError('Cupom possui itens. Cancele (F6) antes de importar.');

            return false;
        }

        return true;
    }

    public function updatedImportarSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->importarSearch !== $upper) {
            $this->importarSearch = $upper;
        }

        $this->refreshImportarResults();
    }

    public function refreshImportarResults(): void
    {
        $term = trim($this->importarSearch);
        $like = $term !== '' ? '%' . $term . '%' : null;

        $query = $this->orcamentosImportaveisQuery()
            ->with(['cliente:id,nome_razao,codigo'])
            ->orderByDesc('data')
            ->orderByDesc('id');

        if ($like) {
            $query->where(function ($q) use ($like): void {
                $q->where('numero', 'like', $like)
                    ->orWhereHas('cliente', fn ($sub) => $sub->where('nome_razao', 'like', $like));
            });
        }

        $this->importarResults = $query
            ->limit(50)
            ->get()
            ->map(fn (Orcamento $orcamento): array => [
                'orcamento_id' => $orcamento->id,
                'numero' => $orcamento->numero,
                'data' => $orcamento->data?->format('d/m/Y') ?? '',
                'cliente' => mb_strtoupper($orcamento->cliente?->nome_razao ?? '—', 'UTF-8'),
                'total' => ErpMoney::formatBr($orcamento->total),
            ])
            ->values()
            ->all();

        $this->selectedImportarIndex = $this->importarResults === [] ? null : 0;
    }

    public function selectImportarRow(int $index): void
    {
        if (isset($this->importarResults[$index])) {
            $this->selectedImportarIndex = $index;
        }
    }

    public function moveImportarSelection(int $delta): void
    {
        if ($this->importarResults === []) {
            return;
        }

        $count = count($this->importarResults);
        $index = ($this->selectedImportarIndex ?? 0) + $delta;
        $this->selectedImportarIndex = max(0, min($count - 1, $index));
    }

    public function confirmImportarOrcamento(): void
    {
        $index = $this->selectedImportarIndex;

        if ($index === null || ! isset($this->importarResults[$index])) {
            $this->notifyPdvError('Selecione um orçamento.');

            return;
        }

        if (! $this->assertPodeImportar()) {
            return;
        }

        $orcamentoId = (int) ($this->importarResults[$index]['orcamento_id'] ?? 0);
        $orcamento = $this->orcamentosImportaveisQuery()
            ->with(['itens.product', 'itens.grade', 'cliente', 'vendedor'])
            ->whereKey($orcamentoId)
            ->first();

        if (! $orcamento) {
            $this->notifyPdvError(
                'Orçamento indisponível para importação.',
                'Já faturado (PDV, OS ou importado em outra venda) ou cancelado.',
            );
            $this->refreshImportarResults();

            return;
        }

        if ($orcamento->itens->isEmpty()) {
            $this->notifyPdvError('Orçamento sem itens cadastrados.');

            return;
        }

        if ($erro = $this->reservarDocumentoImportado(PdvImportReserva::ORCAMENTO, (int) $orcamento->id)) {
            $this->notifyPdvError('Orçamento em uso.', $erro);

            return;
        }

        try {
            $descontos = app(OrcamentoDescontoService::class);
            $linhas = [];

            foreach ($orcamento->itens as $item) {
                $product = $item->product;
                $descricao = $item->descricao
                    ?? ($product && $item->grade
                        ? $product->descricao . ' - ' . $item->grade->descricao
                        : ($product?->descricao ?? 'Item inválido'));

                $linhas[] = [
                    'product' => $product,
                    'grade_id' => $item->product_grade_id ? (int) $item->product_grade_id : null,
                    'quantidade' => (float) $item->quantidade,
                    'preco_bruto' => (float) $item->preco_unitario,
                    'total' => $descontos->partesDaLinha($item)['total'],
                    'descricao' => $descricao,
                    'exige_grade' => false,
                ];
            }

            $resultado = $this->carregarItensImportados($linhas, (float) $orcamento->total);

            if ($resultado['importados'] === 0) {
                $this->desfazerImportacao(PdvImportReserva::ORCAMENTO, (int) $orcamento->id);
                $this->notifyPdvError('Nenhum item pôde ser importado.', $this->resumoIgnorados($resultado['ignorados']));

                return;
            }

            $cliente = $orcamento->cliente;

            session([
                'erp.pdv.orcamento_id' => $orcamento->id,
                'erp.pdv.import_cliente_id' => $cliente?->id,
                'erp.pdv.import_cliente_nome' => mb_strtoupper($cliente?->nome_razao ?? 'CONSUMIDOR FINAL', 'UTF-8'),
                'erp.pdv.import_desconto_venda' => $resultado['desconto'],
                'erp.pdv.import_acrescimo_venda' => $resultado['acrescimo'],
            ]);

            // Status do orçamento só muda na finalização; aqui só sinaliza "no caixa" ao Vendas Internas.
            (new VendasInternasPdvHookService())->onOrcamentoImportado((int) $orcamento->id);

            if ($orcamento->vendedor) {
                $this->applyImportVendedor($orcamento->vendedor);
            }
        } catch (\Throwable $e) {
            $this->falhaImportacao(PdvImportReserva::ORCAMENTO, (int) $orcamento->id, $e);

            return;
        }

        $this->concluirImportacao('Orçamento Nº '.$orcamento->numero.' importado.', $resultado);
    }

    protected function applyImportVendedor(Vendedor $vendedor): void
    {
        // orcamentos.vendedor_id já referencia "vendedores": usa direto.
        $this->vendedor = mb_strtoupper((string) ($vendedor->nome ?? 'SEM OPERADOR'), 'UTF-8');
        $this->vendedorId = $vendedor->id;
        $this->persistVendedorToSession();
    }

    public function cancelImportar(): void
    {
        if (in_array($this->activeModal, ['importar', 'importar_pedido'], true)) {
            $this->backImportarMenu();

            return;
        }

        $this->importarTipo = null;
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    public function cancelImportarMenu(): void
    {
        $this->importarTipo = null;
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    /**
     * Monta o cupom com preço e descontos do documento, no formato do PDV:
     * desconto/acréscimo do item são unitários e embutidos no preço (como o Ctrl+Q);
     * o desconto geral do documento (proporcional aos itens importados) e o ajuste de
     * centavos do arredondamento viram desconto/acréscimo da venda na finalização.
     *
     * @param  list<array{product: ?Product, grade_id: ?int, quantidade: float, preco_bruto: float, total: float, descricao: string, exige_grade: bool}>  $linhas
     * @return array{importados: int, ignorados: list<string>, desconto: float, acrescimo: float}
     */
    protected function carregarItensImportados(array $linhas, float $totalDocumento): array
    {
        $validator = $this->pdvItemValidator();
        $ignorados = [];
        $somaDocumento = 0.0;
        $somaImportada = 0.0;
        $somaCupom = 0.0;
        $importados = 0;

        foreach ($linhas as $linha) {
            $somaDocumento += round(max(0, $linha['total']), 2);
        }

        foreach ($linhas as $linha) {
            $product = $linha['product'];
            $descricao = mb_strtoupper((string) $linha['descricao'], 'UTF-8');
            $quantidade = round((float) $linha['quantidade'], 3);
            $gradeId = $linha['grade_id'];

            $motivo = match (true) {
                ! $product => 'produto não encontrado',
                ! $product->ativo => 'inativo',
                (bool) $product->usa_imei => 'IMEI',
                $product->is_grade && ($linha['exige_grade'] || ! $gradeId) => 'grade',
                default => null,
            };

            if ($motivo === null && $validator->validaQuantidade($quantidade)) {
                $motivo = 'quantidade inválida';
            }

            if ($motivo === null && $validator->validaEstoque($product, $quantidade, $gradeId)) {
                $motivo = 'sem estoque';
            }

            $totalLinha = round(max(0, $linha['total']), 2);
            $precoLiquido = $quantidade > 0 ? round($totalLinha / $quantidade, 2) : 0.0;

            if ($motivo === null && $precoLiquido <= 0) {
                $motivo = 'preço zerado';
            }

            if ($motivo !== null) {
                $ignorados[] = $descricao.' ('.$motivo.')';

                continue;
            }

            $precoBruto = round(max(0, $linha['preco_bruto']), 2);
            $totalCupom = round($quantidade * $precoLiquido, 2);
            $delta = round($precoLiquido - $precoBruto, 2);

            $this->cupomItens[] = [
                'product_id' => $product->id,
                'product_grade_id' => $gradeId,
                'product_serial_id' => null,
                'codigo' => $product->codigo,
                'codigo_barras' => $product->codigo_barras ?? '',
                'descricao' => $descricao,
                'unidade' => $product->unidade ?: 'UN',
                'quantidade' => $quantidade,
                'preco' => $precoLiquido,
                'preco_base' => $precoBruto > 0 ? $precoBruto : $precoLiquido,
                'desconto' => $precoBruto > 0 && $delta < 0 ? abs($delta) : 0.0,
                'acrescimo' => $precoBruto > 0 && $delta > 0 ? $delta : 0.0,
                'total' => $totalCupom,
            ];

            $somaImportada += $totalLinha;
            $somaCupom += $totalCupom;
            $importados++;
        }

        if ($importados > 0) {
            $this->marcarCupomIniciadoSeNecessario();
        }

        $geral = $totalDocumento > 0 ? round($somaDocumento - $totalDocumento, 2) : 0.0;
        $geralProporcional = $somaDocumento > 0 ? $geral * ($somaImportada / $somaDocumento) : 0.0;
        $alvo = round($somaImportada - $geralProporcional, 2);
        $ajuste = $importados > 0 ? round($somaCupom - $alvo, 2) : 0.0;

        return [
            'importados' => $importados,
            'ignorados' => $ignorados,
            'desconto' => $ajuste > 0 ? $ajuste : 0.0,
            'acrescimo' => $ajuste < 0 ? abs($ajuste) : 0.0,
        ];
    }

    /**
     * @param  array{importados: int, ignorados: list<string>, desconto: float, acrescimo: float}  $resultado
     */
    protected function concluirImportacao(string $titulo, array $resultado): void
    {
        $this->persistCupomToSession();
        $this->importarTipo = null;
        $this->closePdvModal();

        if ($this->pdvHotPathEnabled ?? false) {
            $this->dispatch('erp-pdv-hot-reload-cupom')->to(PdvHotPath::class);
        }

        $body = $resultado['importados'].' item(ns) carregado(s).';

        if ($resultado['desconto'] > 0) {
            $body .= ' Desconto da venda: R$ '.ErpMoney::formatBr($resultado['desconto']).'.';
        } elseif ($resultado['acrescimo'] > 0) {
            $body .= ' Acréscimo da venda: R$ '.ErpMoney::formatBr($resultado['acrescimo']).'.';
        }

        if ($resultado['ignorados'] !== []) {
            $body .= ' Ignorados: '.$this->resumoIgnorados($resultado['ignorados']);
        }

        $notification = Notification::make()->title($titulo)->body($body);

        if ($resultado['ignorados'] !== []) {
            $notification->warning()->persistent();
        } else {
            $notification->success();
        }

        $notification->send();
        $this->dispatch('erp-pdv-focus-search');
    }

    /**
     * @param  list<string>  $ignorados
     */
    protected function resumoIgnorados(array $ignorados): string
    {
        if ($ignorados === []) {
            return '';
        }

        return implode(', ', array_slice($ignorados, 0, 5)).(count($ignorados) > 5 ? '...' : '');
    }

    protected function reservarDocumentoImportado(string $tipo, int $id): ?string
    {
        $operador = (string) (Auth::user()?->name ?? 'OPERADOR');

        return PdvImportReserva::reservar($tipo, $id, (int) $this->caixaSessaoId, $operador);
    }

    protected function desfazerImportacao(string $tipo, int $id): void
    {
        $this->cupomItens = [];
        $this->selectedCupomIndex = null;
        session()->forget('erp.pdv.cupom');
        $this->forgetCupomIniciadoEm();
        $this->clearImportSession();
        PdvImportReserva::liberar($tipo, $id, (int) $this->caixaSessaoId);
    }

    protected function falhaImportacao(string $tipo, int $id, \Throwable $e): void
    {
        Log::error('PDV importar: falha ao importar documento.', [
            'tipo' => $tipo,
            'id' => $id,
            'message' => $e->getMessage(),
        ]);

        $this->desfazerImportacao($tipo, $id);
        $this->notifyPdvError('Não foi possível importar.', $e->getMessage());
    }

    /**
     * Na finalização (dentro da transação): trava o orçamento e confirma que ainda
     * pode virar venda. Outra venda que já o faturou bloqueia esta.
     */
    protected function travarOrcamentoImportado(int $orcamentoId): Orcamento
    {
        $orcamento = Orcamento::query()->whereKey($orcamentoId)->lockForUpdate()->first();
        $importavel = $orcamento !== null
            && $this->orcamentosImportaveisQuery()->whereKey($orcamentoId)->exists();

        if (! $importavel) {
            $motivo = OrcamentoFaturamentoGuard::motivoFaturado($orcamentoId)
                ?? 'O orçamento importado já foi faturado ou cancelado em outra venda.';

            throw new \RuntimeException($motivo.' Cancele o cupom (F6).');
        }

        return $orcamento;
    }

    /**
     * Orçamentos que ainda podem virar venda no PDV, sem filtro de status aberto,
     * mas bloqueando faturamento duplicado (PDV, OS ou NF-e válida — ver OrcamentoFaturamentoGuard).
     *
     * @return Builder<Orcamento>
     */
    protected function orcamentosImportaveisQuery(): Builder
    {
        return OrcamentoFaturamentoGuard::aplicarNaoFaturados(
            Orcamento::query()->visivelNaListaOrcamentos(),
        );
    }

    /**
     * Na finalização (dentro da transação): trava o pedido e confirma que segue pendente.
     */
    protected function travarPedidoImportado(int $vendaId): Venda
    {
        $pedido = Venda::query()->whereKey($vendaId)->lockForUpdate()->first();
        $pendente = $pedido !== null
            && (new PdvImportarPedidoQuery())->build()->whereKey($vendaId)->exists();

        if (! $pendente) {
            throw new \RuntimeException(
                'O pedido importado já foi faturado, cancelado ou vinculado a outra venda. Cancele o cupom (F6).'
            );
        }

        return $pedido;
    }

    protected function importDescontoVenda(): float
    {
        return round(max(0, (float) session('erp.pdv.import_desconto_venda', 0)), 2);
    }

    protected function importAcrescimoVenda(): float
    {
        return round(max(0, (float) session('erp.pdv.import_acrescimo_venda', 0)), 2);
    }

    /**
     * @param  bool  $liberar  false ao suspender em espera: a reserva continua com a sessão.
     */
    protected function clearImportSession(bool $liberar = true): void
    {
        if ($liberar) {
            $this->liberarDocumentosImportados(
                (int) session('erp.pdv.orcamento_id', 0),
                (int) session('erp.pdv.venda_id', 0),
            );
        }

        session()->forget([
            'erp.pdv.orcamento_id',
            'erp.pdv.venda_id',
            'erp.pdv.import_cliente_id',
            'erp.pdv.import_cliente_nome',
            'erp.pdv.import_desconto_venda',
            'erp.pdv.import_acrescimo_venda',
        ]);
    }

    /**
     * Libera reservas desta sessão. Após venda finalizada o orçamento já está importado
     * e o Vendas Internas já está pago, então nada é revertido.
     */
    protected function liberarDocumentosImportados(int $orcamentoId, int $vendaId): void
    {
        $sessaoId = (int) $this->caixaSessaoId;

        try {
            if ($orcamentoId > 0) {
                PdvImportReserva::liberar(PdvImportReserva::ORCAMENTO, $orcamentoId, $sessaoId);
                (new VendasInternasPdvHookService())->onOrcamentoLiberado($orcamentoId);
            }

            if ($vendaId > 0) {
                PdvImportReserva::liberar(PdvImportReserva::PEDIDO, $vendaId, $sessaoId);
            }
        } catch (\Throwable $e) {
            Log::warning('PDV importar: falha ao liberar documento importado.', [
                'orcamento_id' => $orcamentoId,
                'venda_id' => $vendaId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
