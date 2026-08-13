<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ManagesOutrasSaidasProdutoInclusao;
use App\Filament\Resources\NfeResource;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\OutrasSaidaMovimento;
use App\Models\OutrasSaidaMovimentoItem;
use App\Models\Person;
use App\Models\PlanoConta;
use App\Models\Product;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class OutrasSaidasMovimentoPage extends Page
{
    use ManagesOutrasSaidasProdutoInclusao;

    private const TIMEZONE = 'America/Sao_Paulo';

    protected static ?string $slug = 'outras-saidas-movimento';

    protected static ?string $routePath = 'outras-saidas-movimento';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.outras-saidas-movimento';

    public string $numero = '';
    public string $tipoMovimento = '';
    public string $dataMovimento = '';
    public string $horaMovimento = '';
    public string $estoqueId = '';
    public string $planoContaId = '';
    public string $nfEmitida = '';
    public string $fornecedorBusca = '';
    public ?int $fornecedorId = null;
    public string $fornecedorNome = '';
    public string $observacoes = '';

    /** @var array<int, array{id:int,nome:string,cnpj:string}> */
    public array $fornecedorResultados = [];

    public int $fornecedorSelecionadoIndex = 0;

    /** @var array<int, array{product_id:?int,codigo:string,descricao:string,qtd:string,preco:string,total:string}> */
    public array $itens = [];

    public int $itemSelecionadoIndex = -1;

    public bool $confirmarExcluirItem = false;

    public bool $consultaAberta = false;

    public string $consultaBusca = '';

    public int $consultaSelecionadoIndex = -1;

    /** @var array<int, string> */
    public array $consultaFornecedores = [];

    /** @var array<int, array{id:int,codigo:string,data:string,hora:string,movimentacao:string,fornecedor:string,observacao:string,situacao:string,status:string,estoque:string}> */
    public array $consultaMovimentos = [];

    public ?int $movimentoId = null;

    public string $situacao = OutrasSaidaMovimento::SITUACAO_ABERTA;

    public string $formAlert = '';

    /** @var list<string> */
    private const TIPOS_MOVIMENTO = ['uso_consumo', 'perda', 'outras'];

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('ajuste_estoque.access');
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-os-form-page',
            'erp-mov-saidas-page',
        ];
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string|Htmlable|null
    {
        return '';
    }

    public function mount(): void
    {
        ErpScreen::set('Outras Saídas/Movimento');
        $this->numero = OutrasSaidaMovimento::nextNumero(ErpContext::currentEmpresaId());
        $this->dataMovimento = now(self::TIMEZONE)->format('Y-m-d');
        $this->horaMovimento = now(self::TIMEZONE)->format('H:i');
        $this->estoqueId = (string) (Estoque::query()
            ->where('empresa_id', ErpContext::currentEmpresaId())
            ->where('ativo', true)
            ->orderBy('codigo')
            ->value('id') ?? '');
    }

    #[Computed]
    public function estoques(): array
    {
        return Estoque::query()
            ->where('empresa_id', ErpContext::currentEmpresaId())
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get()
            ->map(fn (Estoque $estoque): array => [
                'id' => (int) $estoque->id,
                'nome' => trim(($estoque->codigo ? $estoque->codigo.' — ' : '').$estoque->nome),
            ])
            ->all();
    }

    public function updatedFornecedorBusca(): void
    {
        $term = trim($this->fornecedorBusca);
        if (mb_strlen($term) < 2) {
            $this->fornecedorResultados = [];
            return;
        }

        $this->fornecedorResultados = Person::query()
            ->where('ativo', true)
            ->where('is_fornecedor', true)
            ->where('nome_razao', 'like', '%'.$term.'%')
            ->orderBy('nome_razao')
            ->limit(10)
            ->get()
            ->map(fn (Person $pessoa): array => [
                'id' => (int) $pessoa->id,
                'nome' => (string) $pessoa->nome_razao,
                'cnpj' => (string) ($pessoa->cpf_cnpj ?? ''),
            ])
            ->all();
        $this->fornecedorSelecionadoIndex = 0;
    }

    #[Computed]
    public function planosContas(): array
    {
        return PlanoConta::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->orderBy('descricao')
            ->get()
            ->map(fn (PlanoConta $plano): array => [
                'id' => (int) $plano->id,
                'nome' => str_pad((string) $plano->codigo, 3, '0', STR_PAD_LEFT).' — '.$plano->descricao,
            ])
            ->all();
    }

    public function moverFornecedorSelecionado(int $delta): void
    {
        $count = count($this->fornecedorResultados);
        if ($count === 0) return;
        $this->fornecedorSelecionadoIndex = max(0, min($count - 1, $this->fornecedorSelecionadoIndex + $delta));
    }

    public function confirmarFornecedorSelecionado(): void
    {
        $fornecedor = $this->fornecedorResultados[$this->fornecedorSelecionadoIndex] ?? null;
        if ($fornecedor) $this->selecionarFornecedor((int) $fornecedor['id']);
    }

    public function selecionarFornecedor(int $id): void
    {
        $fornecedor = Person::query()->whereKey($id)->where('ativo', true)->first();
        if (! $fornecedor) {
            return;
        }
        $this->fornecedorId = $fornecedor->id;
        $this->fornecedorNome = (string) $fornecedor->nome_razao;
        $this->fornecedorBusca = $this->fornecedorNome;
        $this->fornecedorResultados = [];
    }

    public function gravarMovimento(): void
    {
        if ($this->consultaAberta) {
            return;
        }

        $this->formAlert = '';

        if ($this->situacao === OutrasSaidaMovimento::SITUACAO_FINALIZADA) {
            $this->formAlert = 'Movimento já fechado. Não é possível gravar.';

            return;
        }

        if ($this->situacao === OutrasSaidaMovimento::SITUACAO_CANCELADA) {
            $this->formAlert = 'Movimento cancelado. Abra um novo para gravar.';

            return;
        }

        $tipo = trim($this->tipoMovimento);
        if ($tipo === '' || ! in_array($tipo, self::TIPOS_MOVIMENTO, true)) {
            $this->formAlert = 'Selecione o tipo de movimentação (ex.: Saída por perda).';

            return;
        }

        if (trim($this->dataMovimento) === '') {
            $this->formAlert = 'Informe a data do movimento.';

            return;
        }

        if ((int) $this->estoqueId <= 0) {
            $this->formAlert = 'Selecione o estoque de saída.';

            return;
        }

        if ($this->itens === []) {
            $this->formAlert = 'Inclua ao menos um produto na grade.';

            return;
        }

        $empresaId = ErpContext::currentEmpresaId();
        $estoqueId = (int) $this->estoqueId;

        $estoqueOk = Estoque::query()
            ->whereKey($estoqueId)
            ->when($empresaId, fn ($query, $id) => $query->where('empresa_id', $id))
            ->where('ativo', true)
            ->exists();

        if (! $estoqueOk) {
            $this->formAlert = 'Estoque inválido para a empresa atual.';

            return;
        }

        try {
            $movimento = DB::transaction(function () use ($empresaId, $estoqueId, $tipo) {
                $hora = trim($this->horaMovimento);
                if ($hora === '') {
                    $hora = now(self::TIMEZONE)->format('H:i:s');
                } elseif (strlen($hora) === 5) {
                    $hora .= ':00';
                }

                $payload = [
                    'empresa_id' => $empresaId,
                    'situacao' => OutrasSaidaMovimento::SITUACAO_ABERTA,
                    'tipo_movimento' => $tipo,
                    'data' => $this->dataMovimento,
                    'hora' => $hora,
                    'estoque_id' => $estoqueId,
                    'fornecedor_id' => $this->fornecedorId,
                    'fornecedor_nome' => $this->fornecedorNome !== '' ? $this->fornecedorNome : null,
                    'observacoes' => trim($this->observacoes) !== '' ? trim($this->observacoes) : null,
                    'total' => $this->calcularTotalItens(),
                    'usuario_id' => auth()->id(),
                ];

                if ($this->movimentoId && $this->situacao === OutrasSaidaMovimento::SITUACAO_ABERTA) {
                    $movimento = OutrasSaidaMovimento::query()
                        ->whereKey($this->movimentoId)
                        ->when($empresaId, fn ($query, $id) => $query->where('empresa_id', $id))
                        ->where('situacao', OutrasSaidaMovimento::SITUACAO_ABERTA)
                        ->first();

                    if (! $movimento) {
                        throw new \RuntimeException('Movimento aberto não encontrado para atualizar.');
                    }

                    $movimento->update($payload);
                    $movimento->itens()->delete();
                } else {
                    $payload['numero'] = OutrasSaidaMovimento::nextNumero($empresaId);
                    $movimento = OutrasSaidaMovimento::query()->create($payload);
                }

                foreach (array_values($this->itens) as $index => $item) {
                    $qtd = $this->parseDecimalBr((string) ($item['qtd'] ?? '0'));
                    $preco = $this->parseDecimalBr((string) ($item['preco'] ?? '0'));
                    $total = $this->parseDecimalBr((string) ($item['total'] ?? '0'));
                    if ($total <= 0 && $qtd > 0) {
                        $total = round($qtd * $preco, 2);
                    }

                    $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
                    if ($productId <= 0 && ! empty($item['codigo'])) {
                        $productId = (int) (Product::query()->where('codigo', $item['codigo'])->value('id') ?? 0);
                    }

                    OutrasSaidaMovimentoItem::query()->create([
                        'outras_saida_movimento_id' => $movimento->id,
                        'item' => $index + 1,
                        'product_id' => $productId > 0 ? $productId : null,
                        'produto_codigo' => (string) ($item['codigo'] ?? ''),
                        'produto_descricao' => (string) ($item['descricao'] ?? ''),
                        'qtd' => $qtd,
                        'preco' => $preco,
                        'total' => $total,
                    ]);
                }

                return $movimento->fresh(['itens']);
            });
        } catch (\Throwable $e) {
            report($e);
            $this->formAlert = 'Não foi possível gravar o movimento. Tente novamente.';

            return;
        }

        $numeroGravado = (string) $movimento->numero;
        $this->limparParaNovoMovimento();
        $this->formAlert = 'OK: Movimento '.$numeroGravado.' gravado como Aberto. Pronto para um novo lançamento.';
        $this->dispatch('erp-mov-saidas-focus-produto');
    }

    public function selecionarItem(int $index): void
    {
        if (! isset($this->itens[$index])) {
            return;
        }

        $this->itemSelecionadoIndex = $index;
    }

    public function solicitarExcluirItem(): void
    {
        if (! isset($this->itens[$this->itemSelecionadoIndex])) {
            return;
        }

        $this->confirmarExcluirItem = true;
    }

    public function cancelarExcluirItem(): void
    {
        $this->confirmarExcluirItem = false;
    }

    public function excluirItemSelecionado(): void
    {
        if (! isset($this->itens[$this->itemSelecionadoIndex])) {
            $this->confirmarExcluirItem = false;

            return;
        }

        array_splice($this->itens, $this->itemSelecionadoIndex, 1);
        $this->itens = array_values($this->itens);
        $this->itemSelecionadoIndex = $this->itens === []
            ? -1
            : min($this->itemSelecionadoIndex, count($this->itens) - 1);
        $this->confirmarExcluirItem = false;
    }

    public function emitirNfe(): void
    {
        if (! $this->podeEmitirNfe) {
            $this->formAlert = $this->motivoEmitirNfeBloqueado;

            return;
        }

        if ($this->movimentoId === null) {
            $this->formAlert = 'Grave e conclua o movimento antes de emitir a NF-e.';

            return;
        }

        $movimento = OutrasSaidaMovimento::query()
            ->whereKey($this->movimentoId)
            ->when(ErpContext::currentEmpresaId(), fn ($query, $empresaId) => $query->where('empresa_id', $empresaId))
            ->first();

        if (! $movimento) {
            $this->formAlert = 'Movimento não encontrado. Consulte e abra-o novamente.';

            return;
        }

        if ($movimento->situacao !== OutrasSaidaMovimento::SITUACAO_FINALIZADA) {
            $this->formAlert = 'Conclua o movimento antes de emitir a NF-e.';

            return;
        }

        $this->redirect(
            NfeResource::getUrl('index').'?outras_saida_movimento='.$movimento->id,
            navigate: true,
        );
    }

    #[Computed]
    public function podeEmitirNfe(): bool
    {
        if ($this->movimentoId === null || $this->situacao !== OutrasSaidaMovimento::SITUACAO_FINALIZADA) {
            return false;
        }

        $movimento = OutrasSaidaMovimento::query()
            ->whereKey($this->movimentoId)
            ->when(ErpContext::currentEmpresaId(), fn ($query, $empresaId) => $query->where('empresa_id', $empresaId))
            ->first(['tipo_movimento']);

        if (! $movimento || $movimento->tipo_movimento !== 'perda') {
            return true;
        }

        return $this->temDestinatarioProprioNfe();
    }

    #[Computed]
    public function motivoEmitirNfeBloqueado(): string
    {
        if ($this->situacao !== OutrasSaidaMovimento::SITUACAO_FINALIZADA) {
            return 'Conclua o movimento para emitir a NF-e.';
        }

        if (! $this->temDestinatarioProprioNfe()) {
            return 'Cadastre a própria empresa em Pessoas com o mesmo CNPJ para emitir NF-e de perda.';
        }

        return 'Grave e conclua o movimento antes de emitir a NF-e.';
    }

    public function concluirMovimento(): void
    {
        if ($this->movimentoId === null || $this->situacao !== OutrasSaidaMovimento::SITUACAO_ABERTA) {
            $this->formAlert = 'Grave um movimento aberto antes de concluir.';

            return;
        }

        $movimento = OutrasSaidaMovimento::query()
            ->whereKey($this->movimentoId)
            ->when(ErpContext::currentEmpresaId(), fn ($query, $empresaId) => $query->where('empresa_id', $empresaId))
            ->where('situacao', OutrasSaidaMovimento::SITUACAO_ABERTA)
            ->first();

        if (! $movimento) {
            $this->formAlert = 'Movimento aberto não encontrado. Consulte-o novamente.';

            return;
        }

        $movimento->update(['situacao' => OutrasSaidaMovimento::SITUACAO_FINALIZADA]);
        $this->situacao = OutrasSaidaMovimento::SITUACAO_FINALIZADA;
        $this->formAlert = 'OK: Movimento '.$movimento->numero.' concluído. A emissão de NF-e está liberada.';
    }

    public function reabrirMovimento(): void
    {
        if ($this->movimentoId === null || $this->situacao !== OutrasSaidaMovimento::SITUACAO_FINALIZADA) {
            $this->formAlert = 'Abra um movimento fechado para reabrir.';

            return;
        }

        $movimento = OutrasSaidaMovimento::query()
            ->whereKey($this->movimentoId)
            ->when(ErpContext::currentEmpresaId(), fn ($query, $empresaId) => $query->where('empresa_id', $empresaId))
            ->where('situacao', OutrasSaidaMovimento::SITUACAO_FINALIZADA)
            ->first();

        if (! $movimento) {
            $this->formAlert = 'Movimento fechado não encontrado. Consulte-o novamente.';

            return;
        }

        $movimento->update(['situacao' => OutrasSaidaMovimento::SITUACAO_ABERTA]);
        $this->situacao = OutrasSaidaMovimento::SITUACAO_ABERTA;
        $this->formAlert = 'OK: Movimento '.$movimento->numero.' reaberto para alteração.';
    }

    public function imprimirMovimento(): void
    {
        $params = [
            'slug' => 'outras-saidas-movimento',
            'return' => url('/admin/outras-saidas-movimento'),
        ];

        if ($this->movimentoId !== null) {
            $params['movimento'] = $this->movimentoId;
            $params['auto'] = 1;
        } else {
            $params['de'] = now(self::TIMEZONE)->startOfMonth()->toDateString();
            $params['ate'] = now(self::TIMEZONE)->endOfMonth()->toDateString();
        }

        $this->redirect(route('erp.reports.tabular', $params), navigate: false);
    }

    private function temDestinatarioProprioNfe(): bool
    {
        $empresaId = ErpContext::currentEmpresaId();
        $cnpjEmpresa = preg_replace(
            '/\D/',
            '',
            (string) (Empresa::query()->whereKey($empresaId)->value('cnpj') ?? ''),
        ) ?: '';

        if ($cnpjEmpresa === '') {
            return false;
        }

        return Person::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '/', ''), '-', '') = ?", [$cnpjEmpresa])
            ->exists();
    }

    public function abrirConsultaMovimentos(): void
    {
        $this->consultaAberta = true;
        $this->consultaBusca = '';
        $this->consultaSelecionadoIndex = -1;
        $this->consultaFornecedores = [];
        $this->carregarConsultaMovimentos();
    }

    public function fecharConsultaMovimentos(): void
    {
        $this->consultaAberta = false;
        $this->consultaBusca = '';
        $this->consultaSelecionadoIndex = -1;
        $this->consultaFornecedores = [];
        $this->consultaMovimentos = [];
    }

    public function updatedConsultaBusca(): void
    {
        if (! $this->consultaAberta) {
            return;
        }

        $this->consultaSelecionadoIndex = -1;
        $this->carregarConsultaMovimentos();
        $this->carregarConsultaFornecedores();
    }

    public function selecionarConsultaFornecedor(string $fornecedor): void
    {
        $this->consultaBusca = $fornecedor;
        $this->consultaFornecedores = [];
        $this->consultaSelecionadoIndex = -1;
        $this->carregarConsultaMovimentos();
    }

    public function selecionarConsultaMovimento(int $index): void
    {
        if (! isset($this->consultaMovimentos[$index])) {
            return;
        }

        $this->consultaSelecionadoIndex = $index;
    }

    public function abrirConsultaMovimentoSelecionado(): void
    {
        $movimento = $this->consultaMovimentos[$this->consultaSelecionadoIndex] ?? null;
        if (! $movimento) {
            return;
        }

        $this->abrirConsultaMovimento((int) $movimento['id']);
    }

    public function abrirConsultaMovimento(int $id): void
    {
        $movimento = OutrasSaidaMovimento::query()
            ->with(['estoque', 'itens'])
            ->whereKey($id)
            ->when(ErpContext::currentEmpresaId(), fn ($query, $empresaId) => $query->where('empresa_id', $empresaId))
            ->first();

        if (! $movimento) {
            return;
        }

        $this->movimentoId = (int) $movimento->id;
        $this->situacao = (string) ($movimento->situacao ?: OutrasSaidaMovimento::SITUACAO_ABERTA);
        $this->numero = (string) $movimento->numero;
        $this->tipoMovimento = (string) ($movimento->tipo_movimento ?? '');
        if (in_array($this->tipoMovimento, ['saida', 'producao'], true)) {
            $this->tipoMovimento = '';
        }
        $this->dataMovimento = optional($movimento->data)?->format('Y-m-d') ?? now()->format('Y-m-d');
        $hora = (string) ($movimento->hora ?? '');
        $this->horaMovimento = strlen($hora) >= 5 ? substr($hora, 0, 5) : now()->format('H:i');
        $this->estoqueId = $movimento->estoque_id ? (string) $movimento->estoque_id : '';
        $this->fornecedorId = $movimento->fornecedor_id ? (int) $movimento->fornecedor_id : null;
        $this->fornecedorNome = (string) ($movimento->fornecedor_nome ?? '');
        $this->fornecedorBusca = $this->fornecedorNome;
        $this->observacoes = (string) ($movimento->observacoes ?? '');
        $this->itens = $movimento->itens
            ->sortBy('item')
            ->values()
            ->map(fn ($item): array => [
                'product_id' => $item->product_id ? (int) $item->product_id : null,
                'codigo' => (string) ($item->produto_codigo ?? ''),
                'descricao' => (string) ($item->produto_descricao ?? ''),
                'qtd' => number_format((float) $item->qtd, 3, ',', '.'),
                'preco' => number_format((float) $item->preco, 2, ',', '.'),
                'total' => number_format((float) $item->total, 2, ',', '.'),
            ])
            ->all();

        $this->formAlert = '';
        $this->fecharConsultaMovimentos();
    }

    /**
     * @return array{aberta:string,finalizada:string,cancelada:string}
     */
    public static function situacaoLabels(): array
    {
        return [
            OutrasSaidaMovimento::SITUACAO_ABERTA => 'Aberto',
            OutrasSaidaMovimento::SITUACAO_FINALIZADA => 'Fechado',
            OutrasSaidaMovimento::SITUACAO_CANCELADA => 'Cancelado',
        ];
    }

    protected function limparParaNovoMovimento(): void
    {
        $empresaId = ErpContext::currentEmpresaId();

        $this->movimentoId = null;
        $this->numero = OutrasSaidaMovimento::nextNumero($empresaId);
        $this->situacao = OutrasSaidaMovimento::SITUACAO_ABERTA;
        $this->tipoMovimento = '';
        $this->dataMovimento = now(self::TIMEZONE)->format('Y-m-d');
        $this->horaMovimento = now(self::TIMEZONE)->format('H:i');
        $this->planoContaId = '';
        $this->nfEmitida = '';
        $this->fornecedorBusca = '';
        $this->fornecedorId = null;
        $this->fornecedorNome = '';
        $this->fornecedorResultados = [];
        $this->fornecedorSelecionadoIndex = 0;
        $this->observacoes = '';
        $this->itens = [];
        $this->estoqueId = (string) (Estoque::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('codigo')
            ->value('id') ?? '');
        $this->limparBarraInclusao();
    }

    protected function calcularTotalItens(): float
    {
        $total = 0.0;
        foreach ($this->itens as $item) {
            $linha = $this->parseDecimalBr((string) ($item['total'] ?? '0'));
            if ($linha <= 0) {
                $linha = round(
                    $this->parseDecimalBr((string) ($item['qtd'] ?? '0'))
                    * $this->parseDecimalBr((string) ($item['preco'] ?? '0')),
                    2
                );
            }
            $total += $linha;
        }

        return round($total, 2);
    }

    protected function parseDecimalBr(string $value): float
    {
        $value = trim(str_replace(['R$', ' '], '', $value));
        if ($value === '') {
            return 0.0;
        }

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    protected function carregarConsultaMovimentos(): void
    {
        $term = trim($this->consultaBusca);
        $labels = self::situacaoLabels();

        $this->consultaMovimentos = OutrasSaidaMovimento::query()
            ->with('estoque')
            ->when(ErpContext::currentEmpresaId(), fn ($query, $empresaId) => $query->where('empresa_id', $empresaId))
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('numero', 'like', $like)
                        ->orWhere('fornecedor_nome', 'like', $like)
                        ->orWhere('observacoes', 'like', $like)
                        ->orWhereHas('estoque', fn ($estoque) => $estoque->where('nome', 'like', $like)->orWhere('codigo', 'like', $like));
                });
            })
            ->orderByDesc('data')
            ->orderByDesc('hora')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (OutrasSaidaMovimento $movimento) use ($labels): array {
                $situacao = (string) ($movimento->situacao ?: OutrasSaidaMovimento::SITUACAO_ABERTA);
                $estoque = $movimento->estoque;
                $estoqueLabel = $estoque
                    ? trim(($estoque->codigo ? $estoque->codigo.' — ' : '').(string) $estoque->nome)
                    : '';

                return [
                    'id' => (int) $movimento->id,
                    'codigo' => (string) $movimento->numero,
                    'data' => optional($movimento->data)?->format('d/m/Y') ?? '—',
                    'hora' => $movimento->hora ? substr((string) $movimento->hora, 0, 5) : '—',
                    'movimentacao' => match ($movimento->tipo_movimento) {
                        'uso_consumo' => 'Uso / consumo',
                        'perda' => 'Perda',
                        'outras' => 'Outras',
                        default => '—',
                    },
                    'fornecedor' => (string) ($movimento->fornecedor_nome ?? ''),
                    'observacao' => (string) ($movimento->observacoes ?? ''),
                    'situacao' => $situacao,
                    'status' => $labels[$situacao] ?? ucfirst($situacao),
                    'estoque' => $estoqueLabel,
                ];
            })
            ->all();
    }

    protected function carregarConsultaFornecedores(): void
    {
        $term = trim($this->consultaBusca);

        if (mb_strlen($term) < 2) {
            $this->consultaFornecedores = [];

            return;
        }

        $this->consultaFornecedores = OutrasSaidaMovimento::query()
            ->when(ErpContext::currentEmpresaId(), fn ($query, $empresaId) => $query->where('empresa_id', $empresaId))
            ->whereNotNull('fornecedor_nome')
            ->where('fornecedor_nome', '!=', '')
            ->where('fornecedor_nome', 'like', '%'.$term.'%')
            ->distinct()
            ->orderBy('fornecedor_nome')
            ->limit(8)
            ->pluck('fornecedor_nome')
            ->map(fn ($fornecedor): string => (string) $fornecedor)
            ->all();
    }
}
