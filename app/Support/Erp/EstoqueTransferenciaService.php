<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\EstoqueTransferencia;
use App\Models\EstoqueTransferenciaItem;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Transferência interna (mesma empresa / mesmo estabelecimento fiscal) — Etapa 1.
 *
 * Entre CNPJs / NF-e / trânsito: estrutura preparada, fluxo bloqueado até Etapa 2+.
 */
final class EstoqueTransferenciaService
{
    private const EPS = 0.0005;

    public function __construct(
        private readonly ProductEstoqueSaldoService $saldos = new ProductEstoqueSaldoService(),
        private readonly EstoqueTransferenciaSaldoCompat $compat = new EstoqueTransferenciaSaldoCompat(),
    ) {}

    /**
     * Confirma transferência interna: cria documento (ou usa rascunho) e move estoque
     * origem→destino em uma única transação. Net de products.estoque = 0.
     *
     * @param  array{
     *     empresa_id: int,
     *     estoque_origem_id: int,
     *     estoque_destino_id: int,
     *     data?: string|null,
     *     observacoes?: string|null,
     *     idempotencia_token?: string|null,
     *     transferencia_id?: int|null,
     *     usuario_id?: int|null,
     *     itens: list<array{product_id: int, qtd: float|int|string}>
     * }  $payload
     */
    public function confirmar(array $payload): EstoqueTransferencia
    {
        if (! Schema::hasTable('estoque_transferencias')) {
            throw new EstoqueTransferenciaException('Módulo de transferência não migrado.');
        }

        return DB::transaction(function () use ($payload): EstoqueTransferencia {
            $empresaId = (int) ($payload['empresa_id'] ?? 0);
            $origemId = (int) ($payload['estoque_origem_id'] ?? 0);
            $destinoId = (int) ($payload['estoque_destino_id'] ?? 0);
            $token = $this->normalizeToken($payload['idempotencia_token'] ?? null);
            $transferenciaId = (int) ($payload['transferencia_id'] ?? 0);
            $usuarioId = (int) ($payload['usuario_id'] ?? Auth::id() ?? 0);
            $itens = $this->normalizarItens($payload['itens'] ?? []);

            if ($token !== null) {
                $porToken = EstoqueTransferencia::query()
                    ->where('idempotencia_token', $token)
                    ->lockForUpdate()
                    ->first();

                if ($porToken !== null) {
                    if ($porToken->isConfirmada()) {
                        return $porToken->load('itens');
                    }
                    $transferenciaId = (int) $porToken->id;
                }
            }

            $transferencia = null;
            if ($transferenciaId > 0) {
                $transferencia = EstoqueTransferencia::query()
                    ->whereKey($transferenciaId)
                    ->lockForUpdate()
                    ->first();

                if ($transferencia === null) {
                    throw new EstoqueTransferenciaException('Transferência não encontrada.');
                }

                if ($transferencia->isConfirmada()) {
                    return $transferencia->load('itens');
                }

                if (! $transferencia->isRascunho()) {
                    throw new EstoqueTransferenciaException(
                        'Só é possível confirmar transferências em rascunho (situação: '.$transferencia->situacaoLabel().').'
                    );
                }

                $empresaId = (int) $transferencia->empresa_origem_id;
                $origemId = (int) $transferencia->estoque_origem_id;
                $destinoId = (int) $transferencia->estoque_destino_id;
            }

            [$empresa, $origem, $destino] = $this->validarDepositosInternos($empresaId, $origemId, $destinoId);

            if ($itens === []) {
                throw new EstoqueTransferenciaException('Inclua ao menos um produto na transferência.');
            }

            $data = trim((string) ($payload['data'] ?? ''));
            if ($data === '') {
                $data = now('America/Sao_Paulo')->toDateString();
            }

            $observacoes = $this->trimObs($payload['observacoes'] ?? null);

            if ($transferencia === null) {
                $transferencia = EstoqueTransferencia::query()->create([
                    'empresa_origem_id' => (int) $empresa->id,
                    'empresa_destino_id' => (int) $empresa->id,
                    'estoque_origem_id' => (int) $origem->id,
                    'estoque_destino_id' => (int) $destino->id,
                    'numero' => EstoqueTransferencia::nextNumero((int) $empresa->id),
                    'situacao' => EstoqueTransferencia::SITUACAO_RASCUNHO,
                    'modo' => EstoqueTransferencia::MODO_INTERNA,
                    'data' => $data,
                    'observacoes' => $observacoes,
                    'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                    'idempotencia_token' => $token ?? (string) Str::uuid(),
                ]);

                $transferencia = EstoqueTransferencia::query()
                    ->whereKey($transferencia->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            } else {
                $transferencia->fill([
                    'estoque_origem_id' => (int) $origem->id,
                    'estoque_destino_id' => (int) $destino->id,
                    'empresa_destino_id' => (int) $empresa->id,
                    'data' => $data,
                    'observacoes' => $observacoes,
                    'modo' => EstoqueTransferencia::MODO_INTERNA,
                ]);
                if ($token !== null && $transferencia->idempotencia_token === null) {
                    $transferencia->idempotencia_token = $token;
                }
                $transferencia->save();
                $transferencia->itens()->delete();
            }

            $seq = 0;

            foreach ($itens as $linha) {
                $seq++;
                $productId = (int) $linha['product_id'];
                $qtd = round((float) $linha['qtd'], 3);

                $disponivel = $this->compat->assertProdutoTransferivel($productId, (int) $origem->id, $qtd);
                $product = Product::query()->whereKey($productId)->firstOrFail(['id', 'codigo', 'descricao', 'estoque']);

                $globalAntes = $this->compat->estoqueGlobal($productId);

                EstoqueTransferenciaItem::query()->create([
                    'estoque_transferencia_id' => (int) $transferencia->id,
                    'item' => $seq,
                    'product_id' => $productId,
                    'produto_codigo' => (string) ($product->codigo ?? ''),
                    'produto_descricao' => (string) ($product->descricao ?? ''),
                    'qtd' => $qtd,
                    'qtd_disponivel_origem' => $disponivel,
                ]);

                $origemNumero = (string) $transferencia->numero;
                $obsMov = 'Transferência interna '.$origemNumero
                    .' (dep. '.$origem->codigo.' → '.$destino->codigo.')';

                $this->saldos->decrementar(
                    $productId,
                    $qtd,
                    (int) $origem->id,
                    $empresa,
                    EstoqueMovimentacaoContext::make(
                        EstoqueMovimentacao::TIPO_TRANSFERENCIA,
                        empresaId: (int) $empresa->id,
                        origemTipo: 'estoque_transferencia',
                        origemId: (int) $transferencia->id,
                        origemNumero: $origemNumero,
                        usuarioId: $usuarioId > 0 ? $usuarioId : null,
                        observacao: $obsMov.' — saída',
                    ),
                );

                // Hub, ao criar linha nova, inicializa com products.estoque (legado).
                // Sem alterar o hub: garante destino explícito em 0 antes do incremento.
                $this->garantirSaldoDestinoZeradoSeAusente($productId, (int) $destino->id);

                $this->saldos->incrementar(
                    $productId,
                    $qtd,
                    (int) $destino->id,
                    $empresa,
                    EstoqueMovimentacaoContext::make(
                        EstoqueMovimentacao::TIPO_TRANSFERENCIA,
                        empresaId: (int) $empresa->id,
                        origemTipo: 'estoque_transferencia',
                        origemId: (int) $transferencia->id,
                        origemNumero: $origemNumero,
                        usuarioId: $usuarioId > 0 ? $usuarioId : null,
                        observacao: $obsMov.' — entrada',
                    ),
                );

                $globalDepois = $this->compat->estoqueGlobal($productId);
                if (abs($globalDepois - $globalAntes) > self::EPS) {
                    throw new EstoqueTransferenciaException(
                        'Falha de integridade: o estoque global do produto '
                        .trim((string) ($product->codigo ?? $productId))
                        .' mudaria de '.number_format($globalAntes, 3, ',', '.')
                        .' para '.number_format($globalDepois, 3, ',', '.')
                        .' nesta transferência interna. Operação cancelada (rollback).'
                    );
                }
            }

            $transferencia->situacao = EstoqueTransferencia::SITUACAO_CONFIRMADA;
            $transferencia->confirmado_em = now();
            $transferencia->confirmado_por = $usuarioId > 0 ? $usuarioId : null;
            $transferencia->save();

            return $transferencia->load('itens');
        });
    }

    /**
     * Cancela apenas rascunho (sem movimento de estoque).
     */
    public function cancelarRascunho(int $transferenciaId, ?User $user = null): EstoqueTransferencia
    {
        return DB::transaction(function () use ($transferenciaId, $user): EstoqueTransferencia {
            $transf = EstoqueTransferencia::query()->whereKey($transferenciaId)->lockForUpdate()->first();
            if ($transf === null) {
                throw new EstoqueTransferenciaException('Transferência não encontrada.');
            }
            if ($transf->isConfirmada()) {
                throw new EstoqueTransferenciaException('Transferência confirmada não pode ser cancelada nesta etapa.');
            }
            if ($transf->situacao === EstoqueTransferencia::SITUACAO_CANCELADA) {
                return $transf;
            }
            if (! $transf->isRascunho()) {
                throw new EstoqueTransferenciaException('Situação não permite cancelamento na Etapa 1.');
            }

            $transf->situacao = EstoqueTransferencia::SITUACAO_CANCELADA;
            $transf->save();

            return $transf;
        });
    }

    /**
     * Bloqueia explicitamente transferência entre CNPJs até haver tratamento fiscal.
     *
     * @throws EstoqueTransferenciaException
     */
    public function assertModoInternoPermitido(int $empresaOrigemId, int $empresaDestinoId): void
    {
        if ($empresaOrigemId <= 0 || $empresaDestinoId <= 0) {
            throw new EstoqueTransferenciaException('Empresa inválida.');
        }

        if ($empresaOrigemId !== $empresaDestinoId) {
            throw new EstoqueTransferenciaException(
                'Transferência entre empresas/CNPJs diferentes está bloqueada nesta etapa. '
                .'Aguarde a Etapa 2 (tratamento fiscal com NF-e).'
            );
        }
    }

    /**
     * @return array{0: Empresa, 1: Estoque, 2: Estoque}
     */
    private function validarDepositosInternos(int $empresaId, int $origemId, int $destinoId): array
    {
        if ($empresaId <= 0) {
            throw new EstoqueTransferenciaException('Empresa ativa não informada.');
        }

        $this->assertModoInternoPermitido($empresaId, $empresaId);

        if ($origemId <= 0 || $destinoId <= 0) {
            throw new EstoqueTransferenciaException('Selecione o depósito de origem e o de destino.');
        }

        if ($origemId === $destinoId) {
            throw new EstoqueTransferenciaException('Depósito de origem e destino devem ser diferentes.');
        }

        $empresa = Empresa::query()->whereKey($empresaId)->first();
        if ($empresa === null || ! (bool) $empresa->ativo) {
            throw new EstoqueTransferenciaException('Empresa inválida ou inativa.');
        }

        $user = Auth::user();
        if ($user instanceof User && ! $user->is_admin && ! $user->canAccessEmpresa($empresaId)) {
            throw new EstoqueTransferenciaException('Usuário sem acesso à empresa da transferência.');
        }

        $origem = Estoque::query()->whereKey($origemId)->first();
        $destino = Estoque::query()->whereKey($destinoId)->first();

        if ($origem === null || $destino === null) {
            throw new EstoqueTransferenciaException('Depósito de origem ou destino não encontrado.');
        }

        if ((int) $origem->empresa_id !== $empresaId || (int) $destino->empresa_id !== $empresaId) {
            throw new EstoqueTransferenciaException(
                'Na Etapa 1, origem e destino devem pertencer à mesma empresa ativa. '
                .'Transferência entre estabelecimentos com CNPJ distinto permanece bloqueada.'
            );
        }

        if (! (bool) $origem->ativo || ! (bool) $destino->ativo) {
            throw new EstoqueTransferenciaException('Depósito de origem ou destino está inativo.');
        }

        return [$empresa, $origem, $destino];
    }

    /**
     * @param  mixed  $itens
     * @return list<array{product_id: int, qtd: float}>
     */
    private function normalizarItens(mixed $itens): array
    {
        if (! is_array($itens)) {
            return [];
        }

        $out = [];
        $vistos = [];

        foreach ($itens as $row) {
            if (! is_array($row)) {
                continue;
            }
            $productId = (int) ($row['product_id'] ?? 0);
            $qtd = round((float) BrDecimal::parse($row['qtd'] ?? 0, 3), 3);
            if ($productId <= 0 || $qtd <= self::EPS) {
                continue;
            }
            if (isset($vistos[$productId])) {
                $out[$vistos[$productId]]['qtd'] = round($out[$vistos[$productId]]['qtd'] + $qtd, 3);
                continue;
            }
            $vistos[$productId] = count($out);
            $out[] = ['product_id' => $productId, 'qtd' => $qtd];
        }

        return array_values($out);
    }

    private function normalizeToken(mixed $token): ?string
    {
        $token = trim((string) ($token ?? ''));
        if ($token === '') {
            return null;
        }

        return Str::lower($token);
    }

    private function trimObs(mixed $obs): ?string
    {
        $obs = trim((string) ($obs ?? ''));
        if ($obs === '') {
            return null;
        }

        return mb_substr($obs, 0, 250);
    }

    /**
     * Evita o bootstrap legado do hub (nova linha = products.estoque inteiro)
     * sem alterar ProductEstoqueSaldoService.
     */
    private function garantirSaldoDestinoZeradoSeAusente(int $productId, int $estoqueDestinoId): void
    {
        if (! Schema::hasTable('product_estoque_saldos') || $productId <= 0 || $estoqueDestinoId <= 0) {
            return;
        }

        $existe = ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->where('estoque_id', $estoqueDestinoId)
            ->lockForUpdate()
            ->exists();

        if ($existe) {
            return;
        }

        ProductEstoqueSaldo::query()->create([
            'product_id' => $productId,
            'estoque_id' => $estoqueDestinoId,
            'quantidade' => 0,
        ]);
    }
}

