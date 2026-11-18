<?php

namespace App\Support\Erp\Fabricacao;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\Fabricacao;
use App\Models\FabricacaoItem;
use App\Models\Product;
use App\Models\ProductReceita;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Support\Erp\ProductLoteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
/**
 * Fabricação simplificada — Etapa 2.
 * Consome ingredientes e entra o acabado em um único depósito da empresa da sessão.
 */
final class FabricacaoService
{
    private const EPS = 0.0005;

    private const ORIGEM_TIPO = 'fabricacao';

    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
        private readonly ProductLoteService $lotes = new ProductLoteService(),
    ) {}

    public function tabelasDisponiveis(): bool
    {
        return ErpSchema::hasTable('fabricacoes')
            && ErpSchema::hasTable('fabricacao_itens')
            && ErpSchema::hasTable('product_receitas')
            && ErpSchema::hasTable('product_receita_itens');
    }

    /**
     * Preview sem gravar (leitura). Empresa/depósito só da sessão.
     *
     * @return array<string, mixed>
     */
    public function preview(int $productId, float $quantidade, ?User $user = null): array
    {
        $user ??= Auth::user();
        $this->authorizeUser($user);

        [$empresa, $estoque] = $this->resolverEmpresaDepositoSessao($user);

        return $this->montarPreview($productId, $quantidade, $empresa, $estoque);
    }

    /**
     * @param  array{
     *   product_id: int,
     *   quantidade: float|int|string,
     *   idempotencia_token: string,
     *   estoque_id?: int|null,
     *   empresa_id?: int|null,
     *   observacao?: string|null,
     *   usuario_id?: int|null
     * }  $payload
     */
    public function confirmar(array $payload, ?User $user = null): Fabricacao
    {
        if (! $this->tabelasDisponiveis()) {
            throw new FabricacaoException('Módulo de fabricação não migrado.');
        }

        $user ??= Auth::user();
        $this->authorizeUser($user);

        $token = $this->normalizeToken($payload['idempotencia_token'] ?? null);
        if ($token === null) {
            throw new FabricacaoException('Token de idempotência obrigatório.');
        }

        $productId = (int) ($payload['product_id'] ?? 0);
        $quantidade = round((float) ($payload['quantidade'] ?? 0), 3);
        $usuarioId = (int) ($payload['usuario_id'] ?? $user?->id ?? 0);
        $obs = $this->trimObs($payload['observacao'] ?? null);

        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                return DB::transaction(function () use (
                    $payload,
                    $user,
                    $token,
                    $productId,
                    $quantidade,
                    $usuarioId,
                    $obs,
                ): Fabricacao {
                    $existente = Fabricacao::query()
                        ->where('idempotencia_token', $token)
                        ->lockForUpdate()
                        ->first();

                    if ($existente !== null) {
                        if ($existente->isFinalizada() || $existente->isEstornada()) {
                            return $existente->load('itens');
                        }

                        throw new FabricacaoException('Operação de fabricação em estado inconsistente para este token.');
                    }

                    [$empresa, $estoque] = $this->resolverEmpresaDepositoSessao($user);

                    // Nunca confiar em empresa/depósito arbitrário do browser.
                    $empresaPayload = (int) ($payload['empresa_id'] ?? 0);
                    $estoquePayload = (int) ($payload['estoque_id'] ?? 0);
                    if ($empresaPayload > 0 && $empresaPayload !== (int) $empresa->id) {
                        throw new FabricacaoException('Empresa informada não corresponde à sessão autorizada.');
                    }
                    if ($estoquePayload > 0 && $estoquePayload !== (int) $estoque->id) {
                        throw new FabricacaoException('Depósito informado não corresponde ao depósito da empresa ativa.');
                    }

                    $preview = $this->montarPreview($productId, $quantidade, $empresa, $estoque, revalidarBloqueios: true);

                    if (! empty($preview['bloqueios'])) {
                        throw new FabricacaoException(implode(' ', $preview['bloqueios']));
                    }

                    /** @var list<array<string, mixed>> $linhas */
                    $linhas = $preview['ingredientes'];
                    $idsParaLock = array_values(array_unique(array_merge(
                        [(int) $productId],
                        array_map(static fn (array $l): int => (int) $l['component_product_id'], $linhas),
                    )));
                    sort($idsParaLock);

                    $produtos = Product::query()
                        ->whereIn('id', $idsParaLock)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    $acabado = $produtos->get($productId);
                    if (! $acabado instanceof Product) {
                        throw new FabricacaoException('Produto a fabricar não encontrado.');
                    }

                    // Revalida saldos sob lock.
                    $saldosMap = $this->saldos->fisicoParaProdutos(
                        array_map(static fn (array $l): int => (int) $l['component_product_id'], $linhas),
                        (int) $estoque->id,
                    );

                    foreach ($linhas as &$linha) {
                        $cid = (int) $linha['component_product_id'];
                        $comp = $produtos->get($cid);
                        if (! $comp instanceof Product) {
                            throw new FabricacaoException('Ingrediente não encontrado (id '.$cid.').');
                        }

                        $this->assertProdutoMovimentavel($comp, 'Ingrediente');

                        $custo = ProductReceitaCusto::unitario($comp);
                        if ($custo <= 0) {
                            throw new FabricacaoException(
                                'Ingrediente sem custo confiável: '.trim((string) ($comp->descricao ?: $comp->codigo))
                                .'. Informe preço de custo (ou compra) no cadastro.'
                            );
                        }

                        $necessario = (float) $linha['quantidade_necessaria'];
                        $saldo = (float) ($saldosMap[$cid] ?? $saldosMap[(string) $cid] ?? 0);
                        if ($saldo + self::EPS < $necessario) {
                            throw new FabricacaoException(
                                'Estoque insuficiente de '.trim((string) ($comp->descricao ?: $comp->codigo))
                                .' (saldo: '.number_format($saldo, 3, ',', '.')
                                .', necessário: '.number_format($necessario, 3, ',', '.').').'
                            );
                        }

                        if ($comp->controla_lote_validade) {
                            if (! $this->lotes->possuiLotes($comp)) {
                                throw new FabricacaoException(
                                    'Ingrediente "'.trim((string) ($comp->descricao ?: $comp->codigo))
                                    .'" controla lote/validade sem lotes cadastrados.'
                                );
                            }
                            $saldoLotes = $this->lotes->saldoDisponivel($comp);
                            if ($saldoLotes + self::EPS < $necessario) {
                                throw new FabricacaoException(
                                    'Estoque por lote insuficiente de '
                                    .trim((string) ($comp->descricao ?: $comp->codigo))
                                    .' (lotes: '.number_format($saldoLotes, 3, ',', '.').').'
                                );
                            }
                        }

                        $linha['custo_unitario'] = $custo;
                        $linha['custo_total'] = round($necessario * $custo, 2);
                        $linha['saldo_disponivel'] = $saldo;
                        $linha['produto_codigo'] = (string) ($comp->codigo ?? '');
                        $linha['produto_descricao'] = (string) ($comp->descricao ?? '');
                        $linha['unidade'] = (string) ($comp->unidade ?? '');
                    }
                    unset($linha);

                    if ($acabado->controla_lote_validade && ! $this->lotes->possuiLotes($acabado)) {
                        throw new FabricacaoException(
                            'Produto acabado controla lote/validade e não possui lotes cadastrados.'
                            .' Cadastre um lote antes de fabricar (não é criado lote fictício).'
                        );
                    }

                    $custoTotal = round(array_sum(array_column($linhas, 'custo_total')), 2);
                    $custoUnit = $quantidade > self::EPS ? round($custoTotal / $quantidade, 2) : 0.0;

                    $numero = $this->nextNumeroLocked((int) $empresa->id);

                    $fabricacao = Fabricacao::query()->create([
                        'empresa_id' => (int) $empresa->id,
                        'numero' => $numero,
                        'data_hora' => now(),
                        'product_id' => (int) $acabado->id,
                        'quantidade' => $quantidade,
                        'estoque_id' => (int) $estoque->id,
                        'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                        'custo_total' => $custoTotal,
                        'custo_unitario' => $custoUnit,
                        'status' => Fabricacao::STATUS_FINALIZADA,
                        'idempotencia_token' => $token,
                        'rendimento_receita' => (float) $preview['rendimento'],
                        'fator' => (float) $preview['fator'],
                        'observacao' => $obs,
                    ]);

                    $ctxSaida = EstoqueMovimentacaoContext::make(
                        EstoqueMovimentacao::TIPO_FABRICACAO,
                        empresaId: (int) $empresa->id,
                        origemTipo: self::ORIGEM_TIPO,
                        origemId: (int) $fabricacao->id,
                        origemNumero: (string) $fabricacao->numero,
                        usuarioId: $usuarioId > 0 ? $usuarioId : null,
                        observacao: 'Fabricação '.$fabricacao->numero.' — baixa ingrediente',
                    );

                    $ctxEntrada = EstoqueMovimentacaoContext::make(
                        EstoqueMovimentacao::TIPO_FABRICACAO,
                        empresaId: (int) $empresa->id,
                        origemTipo: self::ORIGEM_TIPO,
                        origemId: (int) $fabricacao->id,
                        origemNumero: (string) $fabricacao->numero,
                        usuarioId: $usuarioId > 0 ? $usuarioId : null,
                        observacao: 'Fabricação '.$fabricacao->numero.' — entrada acabado',
                    );

                    // Baixa ingredientes em ordem de id (já ordenados no lock).
                    usort($linhas, static fn (array $a, array $b): int => ((int) $a['component_product_id']) <=> ((int) $b['component_product_id']));

                    foreach ($linhas as $linha) {
                        $cid = (int) $linha['component_product_id'];
                        $comp = $produtos->get($cid);
                        $qtd = (float) $linha['quantidade_necessaria'];

                        if ($comp->controla_lote_validade) {
                            $fatias = $this->lotes->consumirFefo($comp, $qtd, permitirNegativo: false);
                            $this->lotes->registrarSaidas($comp, $fatias, self::ORIGEM_TIPO, (int) $fabricacao->id);
                        }

                        $this->saldos->decrementar($cid, $qtd, (int) $estoque->id, $empresa, $ctxSaida);

                        FabricacaoItem::query()->create([
                            'fabricacao_id' => (int) $fabricacao->id,
                            'component_product_id' => $cid,
                            'produto_codigo' => $linha['produto_codigo'],
                            'produto_descricao' => $linha['produto_descricao'],
                            'unidade' => $linha['unidade'],
                            'quantidade_consumida' => $qtd,
                            'custo_unitario' => (float) $linha['custo_unitario'],
                            'custo_total' => (float) $linha['custo_total'],
                        ]);
                    }

                    $this->saldos->incrementar((int) $acabado->id, $quantidade, (int) $estoque->id, $empresa, $ctxEntrada);

                    if ($acabado->controla_lote_validade) {
                        $this->lotes->devolver($acabado, $quantidade);
                    }

                    return $fabricacao->load('itens');
                });
            } catch (QueryException $e) {
                if ($attempts < 3 && $this->isUniqueViolation($e)) {
                    $porToken = Fabricacao::query()->where('idempotencia_token', $token)->first();
                    if ($porToken !== null) {
                        return $porToken->load('itens');
                    }

                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function montarPreview(
        int $productId,
        float $quantidade,
        Empresa $empresa,
        Estoque $estoque,
        bool $revalidarBloqueios = false,
    ): array {
        $bloqueios = [];
        $avisos = [];

        if ($productId <= 0) {
            $bloqueios[] = 'Selecione o produto a fabricar.';
        }

        if ($quantidade <= self::EPS) {
            $bloqueios[] = 'Informe a quantidade a fabricar maior que zero.';
        }

        $acabado = $productId > 0
            ? Product::query()->whereKey($productId)->first([
                'id', 'codigo', 'descricao', 'unidade', 'ativo', 'is_servico', 'is_composicao',
                'usa_imei', 'is_grade', 'contr_est_grade', 'controla_lote_validade',
                'preco_custo', 'preco_compra', 'preco_venda',
            ])
            : null;

        if ($productId > 0 && ! $acabado) {
            $bloqueios[] = 'Produto não encontrado.';
        }

        $receita = null;
        $fator = 0.0;
        $rendimento = 0.0;
        $ingredientes = [];

        if ($acabado) {
            try {
                $this->assertAcabadoFabricavel($acabado);
                FabricacaoUnidadeRules::assertQuantidadeCompativel(
                    $quantidade,
                    $acabado->unidade,
                    'Quantidade a fabricar',
                );
            } catch (FabricacaoException $e) {
                $bloqueios[] = $e->getMessage();
            }

            $receita = ProductReceita::query()
                ->where('product_id', (int) $acabado->id)
                ->where('ativo', true)
                ->with(['itens.componentProduct:id,codigo,descricao,unidade,ativo,is_servico,is_composicao,usa_imei,is_grade,contr_est_grade,controla_lote_validade,preco_custo,preco_compra,preco_venda'])
                ->first();

            if (! $receita) {
                $bloqueios[] = 'Produto sem receita ativa de fabricação.';
            } elseif ($receita->itens->isEmpty()) {
                $bloqueios[] = 'Receita sem ingredientes cadastrados.';
            } else {
                $rendimento = round((float) $receita->qtd_produzida, 3);
                if ($rendimento <= self::EPS) {
                    $bloqueios[] = 'Rendimento da receita inválido.';
                } else {
                    $fator = $quantidade / $rendimento;
                }
            }
        }

        if ($receita && $fator > 0) {
            $componentIds = $receita->itens->pluck('component_product_id')->map(fn ($id): int => (int) $id)->all();
            $saldosMap = $this->saldos->fisicoParaProdutos($componentIds, (int) $estoque->id);

            foreach ($receita->itens as $item) {
                $comp = $item->componentProduct;
                $cid = (int) $item->component_product_id;
                $qtdReceita = round((float) $item->quantidade, 3);
                $necessario = round($qtdReceita * $fator, 3);

                $linhaBloqueios = [];
                if (! $comp) {
                    $linhaBloqueios[] = 'Ingrediente id '.$cid.' não encontrado.';
                    $bloqueios[] = end($linhaBloqueios);
                } else {
                    try {
                        $this->assertProdutoMovimentavel($comp, 'Ingrediente');
                        FabricacaoUnidadeRules::assertQuantidadeCompativel(
                            $necessario,
                            $comp->unidade,
                            'Consumo de '.trim((string) ($comp->descricao ?: $comp->codigo)),
                        );
                    } catch (FabricacaoException $e) {
                        $linhaBloqueios[] = $e->getMessage();
                        $bloqueios[] = $e->getMessage();
                    }
                }

                $custo = $comp ? ProductReceitaCusto::unitario($comp) : 0.0;
                if ($comp && $custo <= 0) {
                    $msg = 'Ingrediente sem custo: '.trim((string) ($comp->descricao ?: $comp->codigo));
                    $linhaBloqueios[] = $msg;
                    $bloqueios[] = $msg;
                }

                $saldo = (float) ($saldosMap[$cid] ?? $saldosMap[(string) $cid] ?? 0);
                $okSaldo = $saldo + self::EPS >= $necessario;
                if (! $okSaldo) {
                    $msg = 'Saldo insuficiente: '.trim((string) ($comp?->descricao ?: $comp?->codigo ?: $cid));
                    $linhaBloqueios[] = $msg;
                    $bloqueios[] = $msg;
                }

                $ingredientes[] = [
                    'component_product_id' => $cid,
                    'codigo' => (string) ($comp?->codigo ?? ''),
                    'descricao' => (string) ($comp?->descricao ?? ''),
                    'unidade' => (string) ($comp?->unidade ?? ''),
                    'quantidade_receita' => $qtdReceita,
                    'quantidade_necessaria' => $necessario,
                    'saldo_disponivel' => round($saldo, 3),
                    'disponivel' => $okSaldo && $linhaBloqueios === [],
                    'custo_unitario' => $custo,
                    'custo_total' => round($necessario * $custo, 2),
                    'bloqueios' => $linhaBloqueios,
                ];
            }
        }

        $custoTotal = round(array_sum(array_map(
            static fn (array $l): float => (float) ($l['custo_total'] ?? 0),
            $ingredientes,
        )), 2);
        $custoUnit = $quantidade > self::EPS ? round($custoTotal / $quantidade, 2) : 0.0;

        if ($revalidarBloqueios) {
            $bloqueios = array_values(array_unique($bloqueios));
        }

        return [
            'empresa_id' => (int) $empresa->id,
            'empresa_nome' => (string) ($empresa->nome ?? ''),
            'estoque_id' => (int) $estoque->id,
            'estoque_label' => trim((string) ($estoque->codigo.' — '.$estoque->nome)),
            'product_id' => $productId,
            'produto_codigo' => (string) ($acabado?->codigo ?? ''),
            'produto_descricao' => (string) ($acabado?->descricao ?? ''),
            'produto_unidade' => (string) ($acabado?->unidade ?? ''),
            'quantidade' => $quantidade,
            'rendimento' => $rendimento,
            'fator' => round($fator, 8),
            'ingredientes' => $ingredientes,
            'custo_total' => $custoTotal,
            'custo_unitario' => $custoUnit,
            'pode_confirmar' => $bloqueios === [] && $ingredientes !== [],
            'bloqueios' => array_values(array_unique($bloqueios)),
            'avisos' => $avisos,
        ];
    }

    private function authorizeUser(?User $user): void
    {
        if (! $user instanceof User) {
            throw new AuthorizationException('Usuário não autenticado.');
        }

        ErpAccess::authorize($user, 'ajuste_estoque.access');
        ErpAccess::authorize($user, 'ajuste_estoque.create');
    }

    /**
     * @return array{0: Empresa, 1: Estoque}
     */
    private function resolverEmpresaDepositoSessao(?User $user): array
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? session('erp_empresa_id') ?? $user?->empresa_id ?? 0);
        if ($empresaId <= 0) {
            throw new FabricacaoException('Empresa ativa não definida na sessão.');
        }

        $empresa = Empresa::query()->whereKey($empresaId)->first();
        if ($empresa === null || ! (bool) $empresa->ativo) {
            throw new FabricacaoException('Empresa inválida ou inativa.');
        }

        if ($user instanceof User && ! $user->is_admin && method_exists($user, 'canAccessEmpresa') && ! $user->canAccessEmpresa($empresaId)) {
            throw new FabricacaoException('Usuário sem acesso à empresa ativa.');
        }

        $estoqueId = $this->saldos->estoqueIdParaEmpresa($empresaId);
        if ($estoqueId === null || $estoqueId <= 0) {
            throw new FabricacaoException('Depósito da empresa não encontrado. Cadastre um depósito ativo.');
        }

        $estoque = Estoque::query()->whereKey($estoqueId)->first();
        if ($estoque === null || ! (bool) $estoque->ativo) {
            throw new FabricacaoException('Depósito da empresa está inativo ou inexistente.');
        }

        if ((int) $estoque->empresa_id !== $empresaId) {
            throw new FabricacaoException('Depósito não pertence à empresa da sessão.');
        }

        return [$empresa, $estoque];
    }

    private function assertAcabadoFabricavel(Product $product): void
    {
        if (! ($product->ativo ?? false)) {
            throw new FabricacaoException('Produto inativo não pode ser fabricado.');
        }

        if ($product->is_servico) {
            throw new FabricacaoException('Serviço não pode ser fabricado.');
        }

        if ($product->is_composicao) {
            throw new FabricacaoException(
                'Produto marcado como composição de venda (kit) não pode ser fabricado.'
                .' Isso evita dupla baixa dos ingredientes no PDV.'
            );
        }

        $this->assertProdutoMovimentavel($product, 'Produto acabado');
    }

    private function assertProdutoMovimentavel(Product $product, string $rotulo): void
    {
        if ($product->is_servico) {
            throw new FabricacaoException($rotulo.' não pode ser serviço.');
        }

        if (! ($product->ativo ?? false)) {
            throw new FabricacaoException($rotulo.' inativo: '.trim((string) ($product->descricao ?: $product->codigo)));
        }

        if ($product->usa_imei) {
            throw new FabricacaoException(
                $rotulo.' controla IMEI e não é suportado na fabricação nesta etapa: '
                .trim((string) ($product->descricao ?: $product->codigo))
            );
        }

        if ($product->is_grade && $product->contr_est_grade) {
            throw new FabricacaoException(
                $rotulo.' controla estoque por grade e não é suportado na fabricação nesta etapa: '
                .trim((string) ($product->descricao ?: $product->codigo))
            );
        }
    }

    private function nextNumeroLocked(int $empresaId): string
    {
        $last = Fabricacao::query()
            ->where('empresa_id', $empresaId)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('numero');

        $n = ((int) preg_replace('/\D/', '', (string) ($last ?? '0'))) + 1;

        return str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    private function normalizeToken(mixed $token): ?string
    {
        $token = trim((string) ($token ?? ''));
        if ($token === '' || strlen($token) > 64) {
            return null;
        }

        return $token;
    }

    private function trimObs(mixed $obs): ?string
    {
        $obs = trim((string) ($obs ?? ''));
        if ($obs === '') {
            return null;
        }

        return mb_substr($obs, 0, 500);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? $e->getCode());
        $msg = strtolower($e->getMessage());

        return $code === '1062'
            || str_contains($msg, 'unique')
            || str_contains($msg, 'duplicate');
    }
}
