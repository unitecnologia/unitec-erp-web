<?php

namespace App\Support\Inventario;

use App\Models\EstoqueMovimentacao;
use App\Models\InventarioContagem;
use App\Models\InventarioContagemItem;
use App\Models\InventarioEtapa;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Models\User;
use App\Support\Erp\AjusteEstoqueService;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\ProductEstoqueSaldoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InventarioContagemService
{
    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resumo(User $user): array
    {
        $ctx = InventarioAcesso::contexto($user);
        $balanco = $this->balancoAberto($user, $ctx);
        $etapa = $balanco ? $this->etapaAberta($balanco) : null;

        return [
            'balanco_id' => $balanco?->id,
            'aberto' => $balanco !== null,
            'data' => ($balanco?->data ?? now())->format('d/m/Y'),
            'etapa_aberta_id' => $etapa?->id,
            'etapa_aberta' => $etapa?->nome,
            'contados' => $etapa
                ? $etapa->itens()->where('situacao', InventarioContagemItem::SITUACAO_APLICADO)->count()
                : 0,
            'etapas' => $balanco
                ? $balanco->etapas()->orderBy('id')->get()->map(fn (InventarioEtapa $row): array => [
                    'id' => (int) $row->id,
                    'nome' => (string) $row->nome,
                    'status' => (string) $row->status,
                    'aberta' => $row->status === InventarioEtapa::STATUS_ABERTA,
                ])->all()
                : [],
            'empresa_id' => (int) $ctx['empresa']->id,
            'empresa' => (string) $ctx['empresa']->nome,
            'deposito' => trim((string) $ctx['estoque']->codigo.' — '.$ctx['estoque']->nome),
            'responsavel' => (string) $user->name,
        ];
    }

    /**
     * @return array{id: int}
     */
    public function abrirBalanco(User $user): array
    {
        InventarioAcesso::exigir($user, InventarioAcesso::CONTAGEM);

        return DB::transaction(function () use ($user): array {
            $fresh = InventarioAcesso::exigirAgora($user, InventarioAcesso::CONTAGEM);
            $ctx = InventarioAcesso::contexto($fresh);
            $balanco = $this->balancoTravado($fresh, $ctx, true);

            return ['id' => (int) $balanco->id];
        });
    }

    /**
     * @return array{id: int, nome: string}
     */
    public function criarEtapa(User $user, string $nome): array
    {
        InventarioAcesso::exigir($user, InventarioAcesso::CONTAGEM);
        $nome = trim($nome);

        if ($nome === '') {
            throw new InventarioException('Informe o nome do setor.');
        }

        $nome = mb_substr($nome, 0, 80);

        return DB::transaction(function () use ($user, $nome): array {
            $fresh = InventarioAcesso::exigirAgora($user, InventarioAcesso::CONTAGEM);
            $ctx = InventarioAcesso::contexto($fresh);
            $balanco = $this->balancoTravado($fresh, $ctx, false);

            $aberta = $this->etapaAberta($balanco, true);

            if ($aberta) {
                throw new InventarioException('Feche o setor aberto antes de começar outro.');
            }

            $etapa = InventarioEtapa::query()->create([
                'inventario_contagem_id' => $balanco->id,
                'nome' => $nome,
                'status' => InventarioEtapa::STATUS_ABERTA,
                'user_id' => $fresh->id,
            ]);

            return [
                'id' => (int) $etapa->id,
                'nome' => (string) $etapa->nome,
            ];
        });
    }

    public function fecharEtapa(User $user): void
    {
        InventarioAcesso::exigir($user, InventarioAcesso::CONTAGEM);

        DB::transaction(function () use ($user): void {
            $fresh = InventarioAcesso::exigirAgora($user, InventarioAcesso::CONTAGEM);
            $ctx = InventarioAcesso::contexto($fresh);
            $balanco = $this->balancoTravado($fresh, $ctx, false);
            $etapa = $this->etapaAberta($balanco, true);

            if (! $etapa) {
                throw new InventarioException('Não há setor aberto para fechar.');
            }

            $etapa->status = InventarioEtapa::STATUS_FECHADA;
            $etapa->fechada_em = now();
            $etapa->fechada_por = $fresh->id;
            $etapa->save();
        });
    }

    public function encerrarBalanco(User $user): void
    {
        InventarioAcesso::exigir($user, InventarioAcesso::CONTAGEM);

        DB::transaction(function () use ($user): void {
            $fresh = InventarioAcesso::exigirAgora($user, InventarioAcesso::CONTAGEM);
            $ctx = InventarioAcesso::contexto($fresh);
            $balanco = $this->balancoTravado($fresh, $ctx, false);

            if ($this->etapaAberta($balanco, true)) {
                throw new InventarioException('Feche o setor aberto antes de encerrar o balanço.');
            }

            $balanco->status = InventarioContagem::STATUS_ENCERRADO;
            $balanco->finalizada_em = now();
            $balanco->finalizada_por = $fresh->id;
            $balanco->save();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buscar(User $user, string $termo): array
    {
        InventarioAcesso::exigir($user, InventarioAcesso::CONTAGEM);
        InventarioAcesso::contexto($user);

        $q = ErpUppercase::uppercase(trim($termo));

        if (mb_strlen($q) < 2) {
            return [];
        }

        $starts = $q.'%';
        $word = '% '.$q.'%';

        return Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($q, $starts, $word): void {
                $query->where('codigo', $q)
                    ->orWhere('codigo', 'like', $starts)
                    ->orWhere('codigo_barras', $q)
                    ->orWhere('codigo_barras', 'like', $starts)
                    ->orWhere('codigo_barras_caixa', $q)
                    ->orWhere('codigo_barras_caixa', 'like', $starts)
                    ->orWhere('referencia', 'like', $starts)
                    ->orWhere('descricao', 'like', $starts)
                    ->orWhere('descricao', 'like', $word);
            })
            ->orderByRaw(
                'CASE
                    WHEN codigo = ? OR codigo_barras = ? OR codigo_barras_caixa = ? THEN 0
                    WHEN codigo LIKE ? THEN 1
                    WHEN codigo_barras LIKE ? OR codigo_barras_caixa LIKE ? THEN 2
                    WHEN descricao LIKE ? THEN 3
                    ELSE 4
                END',
                [$q, $q, $q, $starts, $starts, $starts, $starts]
            )
            ->orderBy('descricao')
            ->limit(20)
            ->get(['id', 'codigo', 'descricao', 'unidade'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'codigo' => (string) ($product->codigo ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'unidade' => (string) ($product->unidade ?: 'UN'),
            ])
            ->all();
    }

    public function produtoExatoId(string $termo): ?int
    {
        $q = ErpUppercase::uppercase(trim($termo));

        if ($q === '') {
            return null;
        }

        $ids = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($q): void {
                $query->where('codigo', $q)
                    ->orWhere('codigo_barras', $q)
                    ->orWhere('codigo_barras_caixa', $q);
            })
            ->limit(2)
            ->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function abrirProduto(User $user, int $productId): array
    {
        InventarioAcesso::exigir($user, InventarioAcesso::CONTAGEM);

        return DB::transaction(function () use ($user, $productId): array {
            $fresh = InventarioAcesso::exigirAgora($user, InventarioAcesso::CONTAGEM);
            $ctx = InventarioAcesso::contexto($fresh);
            $balanco = $this->balancoTravado($fresh, $ctx, false);
            $etapa = $this->etapaAberta($balanco, true);

            if (! $etapa) {
                throw new InventarioException('Abra um setor antes de contar.');
            }

            $product = Product::query()->whereKey($productId)->where('ativo', true)->lockForUpdate()->first();

            if (! $product) {
                throw new InventarioException('Produto não encontrado.');
            }

            $estoqueId = (int) $ctx['estoque']->id;
            $this->travarSaldo((int) $product->id, $estoqueId);

            $pendente = InventarioContagemItem::query()
                ->where('inventario_etapa_id', $etapa->id)
                ->where('product_id', $product->id)
                ->where('situacao', InventarioContagemItem::SITUACAO_PENDENTE)
                ->lockForUpdate()
                ->first();

            if (! $pendente) {
                $pendente = new InventarioContagemItem([
                    'inventario_contagem_id' => $balanco->id,
                    'inventario_etapa_id' => $etapa->id,
                    'product_id' => $product->id,
                    'idempotencia' => (string) Str::uuid(),
                    'situacao' => InventarioContagemItem::SITUACAO_PENDENTE,
                    'quantidade_contada' => 0,
                ]);
            }

            $this->gravarReferencia($pendente, $product, $estoqueId, $fresh);
            $pendente->save();

            return $this->leitura($pendente, $etapa);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function salvar(User $user, int $productId, mixed $quantidadeInformada, string $token, int $cursor): array
    {
        InventarioAcesso::exigir($user, InventarioAcesso::CONTAGEM);
        $quantidade = BrDecimal::tryParse($quantidadeInformada, 3);

        if ($quantidade === null) {
            throw new InventarioException('Informe a quantidade total contada no depósito. Zero conta o produto; em branco não grava.');
        }

        if ($quantidade < 0) {
            throw new InventarioException('A quantidade contada não pode ser negativa.');
        }

        $token = trim($token);

        if ($token === '') {
            throw new InventarioException('Abra o produto de novo antes de salvar.');
        }

        try {
            return DB::transaction(function () use ($user, $productId, $quantidade, $token, $cursor): array {
                User::query()->whereKey($user->id)->lockForUpdate()->first();
                $fresh = InventarioAcesso::exigirAgora($user, InventarioAcesso::CONTAGEM);
                $ctx = InventarioAcesso::contexto($fresh);

                $item = InventarioContagemItem::query()
                    ->where('idempotencia', $token)
                    ->lockForUpdate()
                    ->first();

                if (! $item || (int) $item->product_id !== $productId) {
                    throw new InventarioException('Abra o produto de novo antes de salvar.');
                }

                $balanco = InventarioContagem::query()->whereKey($item->inventario_contagem_id)->lockForUpdate()->first();
                $etapa = InventarioEtapa::query()->whereKey($item->inventario_etapa_id)->lockForUpdate()->first();

                if (! $balanco || ! $etapa
                    || (int) $balanco->empresa_id !== (int) $ctx['empresa']->id
                    || (int) $balanco->estoque_id !== (int) $ctx['estoque']->id
                    || (int) $etapa->inventario_contagem_id !== (int) $balanco->id) {
                    throw new InventarioException('Empresa ou depósito da contagem não confere com a sessão.');
                }

                $estoqueSessao = $this->saldos->estoqueIdParaEmpresa((int) $ctx['empresa']->id);

                if ($estoqueSessao === null || (int) $estoqueSessao !== (int) $balanco->estoque_id) {
                    throw new InventarioException('O depósito da empresa não confere com o balanço.');
                }

                if ($item->situacao === InventarioContagemItem::SITUACAO_APLICADO) {
                    $item->setRelation('etapa', $etapa);
                    $item->setRelation('responsavel', $fresh);

                    return $this->resultadoSalvo($item, true);
                }

                if ($balanco->status !== InventarioContagem::STATUS_ABERTO || $etapa->status !== InventarioEtapa::STATUS_ABERTA) {
                    throw new InventarioException('Setor fechado não pode ser alterado. Nenhum ajuste foi aplicado.');
                }

                if ((int) $item->movimento_referencia_id !== $cursor) {
                    throw new InventarioException('A referência desta contagem mudou. Abra o produto de novo. Nenhum ajuste foi aplicado.');
                }

                $estoqueId = (int) $ctx['estoque']->id;
                $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();

                if (! $product || ! $product->ativo) {
                    throw new InventarioException('Produto indisponível. Nenhum ajuste foi aplicado.');
                }

                $this->travarSaldo((int) $product->id, $estoqueId);

                if ($this->janelaContaminada($item, $estoqueId)) {
                    throw new InventarioException('O estoque mudou enquanto a quantidade era informada. Nenhum ajuste foi aplicado. Abra o produto de novo e reconte.');
                }

                $referencia = round((float) $item->saldo_referencia, 3);
                $antes = round($this->saldos->fisico((int) $product->id, $estoqueId), 3);
                $delta = round($quantidade - $referencia, 3);
                $ajusteId = null;

                if (abs($delta) >= 0.0005) {
                    InventarioAcesso::exigirAgora($fresh, InventarioAcesso::FINALIZAR);
                    $ajuste = app(AjusteEstoqueService::class)->criar(
                        (int) $product->id,
                        now()->toDateString(),
                        $delta,
                        'somar',
                    );
                    $ajusteId = (int) $ajuste->id;
                }

                $depois = round($this->saldos->fisico((int) $product->id, $estoqueId), 3);

                if (abs($depois - round($antes + $delta, 3)) >= 0.0005) {
                    throw new InventarioException('O saldo não ficou com a diferença apurada. Nenhum ajuste foi aplicado.');
                }

                $item->fill([
                    'user_id' => $fresh->id,
                    'codigo' => mb_substr((string) ($product->codigo ?? ''), 0, 60),
                    'descricao' => mb_substr((string) ($product->descricao ?? ''), 0, 255),
                    'unidade' => mb_substr((string) ($product->unidade ?: 'UN'), 0, 20),
                    'quantidade_contada' => $quantidade,
                    'diferenca' => $delta,
                    'saldo_antes' => $antes,
                    'saldo_depois' => $depois,
                    'ajuste_estoque_id' => $ajusteId,
                    'contado_em' => now(),
                    'situacao' => InventarioContagemItem::SITUACAO_APLICADO,
                ])->save();

                $item->setRelation('etapa', $etapa);
                $item->setRelation('responsavel', $fresh);

                return $this->resultadoSalvo($item, false);
            });
        } catch (AuthorizationException|InventarioException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InventarioException($e->getMessage() !== '' ? $e->getMessage() : 'Não foi possível salvar a contagem.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itensEtapa(User $user): array
    {
        if (! InventarioAcesso::podeEntrar($user)) {
            throw new AuthorizationException('Sem permissão para esta operação.');
        }

        $ctx = InventarioAcesso::contexto($user);
        $balanco = $this->balancoAberto($user, $ctx);
        $etapa = $balanco ? $this->etapaAberta($balanco) : null;

        if (! $etapa) {
            return [];
        }

        return $etapa->itens()
            ->where('situacao', InventarioContagemItem::SITUACAO_APLICADO)
            ->orderByDesc('contado_em')
            ->orderByDesc('id')
            ->get()
            ->map(fn (InventarioContagemItem $item): array => $this->linha($item))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historico(User $user): array
    {
        if (! InventarioAcesso::podeEntrar($user)) {
            throw new AuthorizationException('Sem permissão para esta operação.');
        }

        $ctx = InventarioAcesso::contexto($user);

        return InventarioContagem::query()
            ->with('responsavel:id,name')
            ->withCount(['itens' => fn ($query) => $query->where('situacao', InventarioContagemItem::SITUACAO_APLICADO)])
            ->where('empresa_id', $ctx['empresa']->id)
            ->where('estoque_id', $ctx['estoque']->id)
            ->where('status', InventarioContagem::STATUS_ENCERRADO)
            ->orderByDesc('finalizada_em')
            ->limit(20)
            ->get()
            ->map(fn (InventarioContagem $contagem): array => [
                'id' => (int) $contagem->id,
                'data' => $contagem->data?->format('d/m/Y') ?? '',
                'finalizada_em' => $contagem->finalizada_em?->format('d/m/Y H:i') ?? '',
                'responsavel' => (string) ($contagem->responsavel?->name ?? ''),
                'itens' => (int) $contagem->itens_count,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historicoItens(User $user, int $contagemId): array
    {
        if (! InventarioAcesso::podeEntrar($user)) {
            throw new AuthorizationException('Sem permissão para esta operação.');
        }

        $ctx = InventarioAcesso::contexto($user);
        $contagem = InventarioContagem::query()
            ->whereKey($contagemId)
            ->where('empresa_id', $ctx['empresa']->id)
            ->where('estoque_id', $ctx['estoque']->id)
            ->where('status', InventarioContagem::STATUS_ENCERRADO)
            ->first();

        if (! $contagem) {
            throw new InventarioException('Balanço não encontrado nesta empresa.');
        }

        return $contagem->itens()
            ->with(['etapa:id,nome', 'responsavel:id,name'])
            ->where('situacao', InventarioContagemItem::SITUACAO_APLICADO)
            ->orderByDesc('contado_em')
            ->get()
            ->map(fn (InventarioContagemItem $item): array => $this->linha($item))
            ->all();
    }

    /**
     * @param  array{user: User, empresa: \App\Models\Empresa, estoque: \App\Models\Estoque}  $ctx
     */
    private function balancoAberto(User $user, array $ctx): ?InventarioContagem
    {
        return InventarioContagem::query()
            ->where('empresa_id', $ctx['empresa']->id)
            ->where('estoque_id', $ctx['estoque']->id)
            ->where('status', InventarioContagem::STATUS_ABERTO)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array{user: User, empresa: \App\Models\Empresa, estoque: \App\Models\Estoque}  $ctx
     */
    private function balancoTravado(User $user, array $ctx, bool $criar): InventarioContagem
    {
        $balanco = InventarioContagem::query()
            ->where('empresa_id', $ctx['empresa']->id)
            ->where('estoque_id', $ctx['estoque']->id)
            ->where('status', InventarioContagem::STATUS_ABERTO)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($balanco) {
            return $balanco;
        }

        if (! $criar) {
            throw new InventarioException('Não há balanço aberto neste depósito.');
        }

        return InventarioContagem::query()->create([
            'empresa_id' => $ctx['empresa']->id,
            'estoque_id' => $ctx['estoque']->id,
            'user_id' => $user->id,
            'data' => now()->toDateString(),
            'status' => InventarioContagem::STATUS_ABERTO,
        ]);
    }

    private function etapaAberta(InventarioContagem $balanco, bool $travar = false): ?InventarioEtapa
    {
        $query = InventarioEtapa::query()
            ->where('inventario_contagem_id', $balanco->id)
            ->where('status', InventarioEtapa::STATUS_ABERTA)
            ->orderByDesc('id');

        if ($travar) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function gravarReferencia(InventarioContagemItem $item, Product $product, int $estoqueId, User $user): void
    {
        $item->user_id = $user->id;
        $item->codigo = mb_substr((string) ($product->codigo ?? ''), 0, 60);
        $item->descricao = mb_substr((string) ($product->descricao ?? ''), 0, 255);
        $item->unidade = mb_substr((string) ($product->unidade ?: 'UN'), 0, 20);
        $item->saldo_referencia = round($this->saldos->fisico((int) $product->id, $estoqueId), 3);
        $item->movimento_referencia_id = $this->ultimoMovimentoId((int) $product->id, $estoqueId);
        $item->situacao = InventarioContagemItem::SITUACAO_PENDENTE;
    }

    private function janelaContaminada(InventarioContagemItem $item, int $estoqueId): bool
    {
        $atual = round($this->saldos->fisico((int) $item->product_id, $estoqueId), 3);
        $referencia = round((float) $item->saldo_referencia, 3);

        if (abs($atual - $referencia) >= 0.0005) {
            return true;
        }

        $cursor = (int) ($item->movimento_referencia_id ?? 0);
        $posterior = EstoqueMovimentacao::query()
            ->where('produto_id', $item->product_id)
            ->where('id', '>', $cursor)
            ->where(function ($query) use ($estoqueId): void {
                $query->where('estoque_id', $estoqueId)->orWhereNull('estoque_id');
            })
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('id');

        return $posterior !== null;
    }

    private function ultimoMovimentoId(int $productId, int $estoqueId): int
    {
        return (int) EstoqueMovimentacao::query()
            ->where('produto_id', $productId)
            ->where(function ($query) use ($estoqueId): void {
                $query->where('estoque_id', $estoqueId)->orWhereNull('estoque_id');
            })
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('id');
    }

    private function travarSaldo(int $productId, int $estoqueId): void
    {
        ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->where('estoque_id', $estoqueId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function leitura(InventarioContagemItem $item, InventarioEtapa $etapa): array
    {
        return [
            'id' => (int) $item->product_id,
            'codigo' => (string) $item->codigo,
            'descricao' => (string) $item->descricao,
            'unidade' => (string) $item->unidade,
            'token' => (string) $item->idempotencia,
            'cursor' => (int) $item->movimento_referencia_id,
            'saldo' => $this->formatar((float) $item->saldo_referencia),
            'etapa_id' => (int) $etapa->id,
            'etapa' => (string) $etapa->nome,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultadoSalvo(InventarioContagemItem $item, bool $repetido): array
    {
        $delta = round((float) $item->diferenca, 3);

        return [
            'id' => (int) $item->id,
            'aplicado' => true,
            'repetido' => $repetido,
            'quantidade' => $this->formatar((float) $item->quantidade_contada),
            'diferenca' => $this->formatar($delta),
            'saldo_depois' => $this->formatar((float) $item->saldo_depois),
            'ajuste_id' => $item->ajuste_estoque_id ? (int) $item->ajuste_estoque_id : null,
            'linha' => $this->linha($item),
            'mensagem' => $repetido
                ? 'Esta contagem já estava salva. Nenhum novo ajuste foi feito.'
                : (abs($delta) < 0.0005
                    ? 'Contagem registrada. O saldo do depósito já era essa quantidade.'
                    : 'Ajuste aplicado. Saldo do depósito: '.$this->formatar((float) $item->saldo_depois).'.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function linha(InventarioContagemItem $item): array
    {
        $referencia = round((float) $item->saldo_referencia, 3);
        $contada = round((float) $item->quantidade_contada, 3);
        $diferenca = round((float) ($item->diferenca ?? ($contada - $referencia)), 3);

        return [
            'id' => (int) $item->id,
            'setor' => (string) ($item->etapa?->nome ?? ''),
            'codigo' => (string) $item->codigo,
            'descricao' => (string) $item->descricao,
            'unidade' => (string) $item->unidade,
            'responsavel' => (string) ($item->responsavel?->name ?? ''),
            'horario' => $item->contado_em?->format('d/m/Y H:i') ?? '',
            'saldo' => $this->formatar($referencia),
            'contada' => $this->formatar($contada),
            'diferenca' => $this->formatar($diferenca),
            'diferenca_valor' => $diferenca,
            'saldo_antes' => $item->saldo_antes !== null ? $this->formatar((float) $item->saldo_antes) : '',
            'saldo_depois' => $item->saldo_depois !== null ? $this->formatar((float) $item->saldo_depois) : '',
            'situacao' => (string) $item->situacao,
            'movimento' => $item->ajuste_estoque_id ? (string) $item->ajuste_estoque_id : '',
        ];
    }

    private function formatar(float $valor): string
    {
        return number_format($valor, 3, ',', '.');
    }
}
