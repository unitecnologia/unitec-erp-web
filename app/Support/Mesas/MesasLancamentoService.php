<?php

namespace App\Support\Mesas;

use App\Models\Empresa;
use App\Models\PdvMesa;
use App\Models\PdvMesaLancamento;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\User;
use App\Support\Erp\Pdv\PdvComposicaoLinhas;
use App\Support\Erp\Pdv\PdvComposicaoSelecao;
use App\Support\Erp\Pdv\PdvMesaService;
use App\Support\Erp\ProductTabelaPrecoService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Lança os itens do garçom numa mesa do PDV.
 *
 * Usa a própria reserva do PDV (PdvMesaService): se um caixa está com a mesa aberta,
 * nada é gravado e o app tenta de novo depois — assim o PDV nunca sobrescreve itens
 * do garçom. O `uuid` do lançamento é gravado na mesma transação dos itens: reenvio
 * do mesmo lançamento devolve o resultado original, sem duplicar.
 */
final class MesasLancamentoService
{
    private const TOLERANCIA_PRECO = 0.009;

    public function __construct(
        private readonly MesasCatalogoService $catalogo,
        private readonly MesasPainelService $painel,
    ) {}

    /**
     * @param  array{
     *     uuid: string,
     *     numero: int,
     *     itens: list<array{product_id: int, quantidade: float|int|string, preco: float|int|string, obs?: string|null}>,
     *     aberta_em_vista?: string|null,
     *     confirmar_mesa?: bool,
     *     aceitar_precos?: bool
     * }  $dados
     * @return array<string, mixed>
     *
     * @throws MesasConflito
     */
    public function lancar(Empresa $empresa, User $user, string $deviceUuid, array $dados): array
    {
        $empresaId = (int) $empresa->id;
        $uuid = strtolower(trim($dados['uuid']));
        $numero = (int) $dados['numero'];

        $existente = $this->lancamentoExistente($uuid, $empresaId);
        if ($existente !== null) {
            return $existente;
        }

        if ($numero < 1 || $numero > PdvMesaService::quantidade($empresa)) {
            throw new MesasConflito(MesasConflito::MESA_FECHADA, 'Mesa '.$numero.' não existe neste estabelecimento.');
        }

        $novos = $this->montarItens($empresa, $dados['itens'], (bool) ($dados['aceitar_precos'] ?? false));
        $abertaVista = $this->normalizarData($dados['aberta_em_vista'] ?? null);
        $confirmarMesa = (bool) ($dados['confirmar_mesa'] ?? false);
        $mesas = PdvMesaService::make(PdvMesaService::reservaSegundos($empresa));

        try {
            $mesa = DB::transaction(function () use ($empresaId, $numero, $uuid, $user, $deviceUuid, $novos, $abertaVista, $confirmarMesa, $mesas): PdvMesa {
                $registro = PdvMesaLancamento::query()->create([
                    'uuid' => $uuid,
                    'empresa_id' => $empresaId,
                    'numero' => $numero,
                    'user_id' => $user->id,
                    'device_uuid' => mb_substr($deviceUuid, 0, 100),
                    'qtd_itens' => count($novos),
                    'total' => round(array_sum(array_column($novos, 'total')), 2),
                    'itens' => json_encode($novos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                ]);

                $atual = PdvMesa::query()
                    ->where('empresa_id', $empresaId)
                    ->where('numero', $numero)
                    ->lockForUpdate()
                    ->first();

                $this->validarMesa($atual, $numero, $abertaVista, $confirmarMesa);

                $reserva = $mesas->reservar($empresaId, $numero, null, [
                    'user_id' => (int) $user->id,
                    'terminal_id' => null,
                    'nome' => 'Garçom '.$user->name,
                ]);

                if (! $reserva['ok']) {
                    throw $this->ocupada(PdvMesaService::rotuloDaEmpresa($empresaId, $numero), '');
                }

                $mesa = $reserva['mesa'];
                $token = $reserva['token'];
                $itens = $this->juntarItens($mesas->itens($mesa), $novos);

                if (! $mesas->salvarItens((int) $mesa->id, $token, $itens)) {
                    throw $this->ocupada($mesa->rotulo(), '');
                }

                $mesas->liberar((int) $mesa->id, $token, $empresaId);
                $registro->forceFill(['pdv_mesa_id' => $mesa->id])->save();

                return $mesa->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            // Mesmo lançamento chegou duas vezes ao mesmo tempo: vale o que gravou primeiro.
            $existente = $this->lancamentoExistente($uuid, $empresaId);
            if ($existente !== null) {
                return $existente;
            }

            throw new MesasConflito(MesasConflito::MESA_OCUPADA, 'Lançamento em processamento. Tentando de novo.', retry: true);
        }

        return [
            'ok' => true,
            'uuid' => $uuid,
            'duplicado' => false,
            'mesa' => $this->painel->resumo($numero, $mesa),
        ];
    }

    private function validarMesa(?PdvMesa $mesa, int $numero, ?string $abertaVista, bool $confirmarMesa): void
    {
        $rotulo = $mesa?->rotulo() ?? PdvMesaService::rotuloPadrao($numero);
        $qtd = (int) ($mesa?->qtd_itens ?? 0);
        $abertaAtual = $qtd > 0 ? $mesa?->aberta_em?->format('Y-m-d H:i:s') : null;

        if ($mesa !== null
            && $mesa->reserva_token !== null
            && $mesa->reservado_ate !== null
            && $mesa->reservado_ate->isFuture()) {
            throw $this->ocupada($rotulo, trim((string) $mesa->reservado_nome));
        }

        if ($mesa !== null && $mesa->aguardandoFechamento()) {
            throw new MesasConflito(
                MesasConflito::MESA_AGUARDANDO,
                $rotulo.' está aguardando fechamento (pré-conta impressa). Peça ao caixa para reabrir a mesa.',
            );
        }

        // O garçom montou o pedido vendo a mesa aberta, mas ela foi fechada (ou fechada e
        // reaberta por outro atendimento) enquanto ele estava sem conexão.
        if (! $confirmarMesa && $abertaVista !== null && $abertaVista !== $abertaAtual) {
            throw new MesasConflito(
                MesasConflito::MESA_FECHADA,
                $abertaAtual === null
                    ? $rotulo.' foi fechada no caixa depois que o pedido foi feito. Confirme para abrir a mesa de novo com estes itens.'
                    : $rotulo.' foi fechada e reaberta em outro atendimento. Confirme se os itens são desta nova conta.',
                detalhes: ['aberta_em' => $abertaAtual],
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $entrada
     * @return list<array<string, mixed>> itens no formato do cupom do PDV
     *
     * @throws MesasConflito
     */
    private function montarItens(Empresa $empresa, array $entrada, bool $aceitarPrecos): array
    {
        $ids = array_values(array_unique(array_map(static fn (array $i): int => (int) $i['product_id'], $entrada)));

        $products = MesasCatalogoService::produtosLancaveis()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $indisponiveis = array_values(array_diff($ids, $products->keys()->map(fn ($id): int => (int) $id)->all()));

        if ($indisponiveis !== []) {
            throw new MesasConflito(
                MesasConflito::PRODUTO_INDISPONIVEL,
                count($indisponiveis) === 1
                    ? 'Um produto do pedido não está mais disponível. Remova-o para enviar.'
                    : count($indisponiveis).' produtos do pedido não estão mais disponíveis. Remova-os para enviar.',
                detalhes: ['product_ids' => $indisponiveis],
            );
        }

        $tabSvc = app(ProductTabelaPrecoService::class);
        $tabSvc->carregarItens($products->values());
        $empresaId = (int) $empresa->id;

        $precosReferencia = [];
        $alterados = [];

        foreach ($entrada as $item) {
            /** @var Product $product */
            $product = $products->get((int) $item['product_id']);
            $id = (int) $product->id;
            $qtd = $this->quantidade($item['quantidade']);
            $nivel = $tabSvc->normalizarNivel($item['nivel'] ?? null)
                ?? $tabSvc->nivelDaTabelaId(isset($item['price_table_id']) ? (int) $item['price_table_id'] : null);

            if ($product->usa_tab_preco ?? false) {
                if (! $nivel || ! $tabSvc->habilitaNivel($product, $nivel)) {
                    throw new MesasConflito(
                        MesasConflito::PRODUTO_INDISPONIVEL,
                        'Selecione a tabela de preço (Varejo/Atacado/Especial) para '.$product->descricao.'.',
                        detalhes: ['product_id' => $id, 'niveis' => $tabSvc->niveisHabilitados($product)],
                    );
                }

                $precoEsperado = $tabSvc->resolvePrecoNivel($product, $nivel, 1, $empresaId);
            } else {
                $precosPadrao = $precosReferencia[$id] ?? null;

                if ($precosPadrao === null) {
                    $precosPadrao = $this->catalogo->precos($empresa, collect([$product]));
                    $precosReferencia[$id] = $precosPadrao;
                }

                $precoEsperado = $precosPadrao[$id];
            }

            $visto = round((float) $item['preco'], 2);

            if (abs($visto - $precoEsperado) > self::TOLERANCIA_PRECO) {
                $alterados[$id] = [
                    'product_id' => $id,
                    'preco_anterior' => $visto,
                    'preco_atual' => $precoEsperado,
                    'nivel' => $nivel,
                ];
            }
        }

        if ($alterados !== [] && ! $aceitarPrecos) {
            throw new MesasConflito(
                MesasConflito::PRECO_ALTERADO,
                (count($alterados) === 1 ? 'O preço de 1 produto mudou' : 'O preço de '.count($alterados).' produtos mudou').'. Confira e confirme o envio.',
                detalhes: ['precos' => array_values($alterados)],
            );
        }

        $itens = [];
        foreach ($entrada as $item) {
            /** @var Product $product */
            $product = $products->get((int) $item['product_id']);
            $quantidade = $this->quantidade($item['quantidade']);
            $nivel = $tabSvc->normalizarNivel($item['nivel'] ?? null)
                ?? $tabSvc->nivelDaTabelaId(isset($item['price_table_id']) ? (int) $item['price_table_id'] : null);
            $priceTableId = null;

            if (($product->usa_tab_preco ?? false) && $nivel) {
                $preco = $tabSvc->resolvePrecoNivel($product, $nivel, $quantidade, $empresaId);
                $priceTableId = $tabSvc->tabelaIdDoNivel($nivel);
            } else {
                $precosAplicados = $this->catalogo->precos($empresa, collect([$product]), [
                    (int) $product->id => $quantidade,
                ]);
                $preco = $precosAplicados[(int) $product->id];
            }

            $selecao = $this->resolverComposicaoSelecao($product, $item);
            $composicaoLinhas = PdvComposicaoLinhas::montar((int) $product->id, $selecao);
            $acrescimo = $this->acrescimoAdicionais($product, $selecao, $quantidade);
            // Obs = só texto digitado; SEM/ADICIONAL ficam em composicao_linhas.
            $obs = $this->obs(PdvComposicaoLinhas::limparObsManual((string) ($item['obs'] ?? '')));

            $linha = [
                'product_id' => (int) $product->id,
                'product_grade_id' => null,
                'product_serial_id' => null,
                'codigo' => $product->codigo,
                'codigo_barras' => $product->codigo_barras ?? '',
                'descricao' => mb_strtoupper((string) $product->descricao, 'UTF-8'),
                'unidade' => $product->unidade ?: 'UN',
                'quantidade' => $quantidade,
                'preco' => $preco,
                'preco_base' => $preco,
                'price_table_id' => $priceTableId,
                'tabela_nivel' => $nivel,
                'desconto' => 0.0,
                'acrescimo' => $acrescimo,
                'total' => round(($quantidade * $preco) + $acrescimo, 2),
                'composicao_removidos' => $selecao->removidos,
                'composicao_adicionais' => $selecao->adicionais,
                'composicao_linhas' => $composicaoLinhas,
            ];

            if ($obs !== '') {
                $linha['obs'] = $obs;
            }

            $itens[] = $linha;
        }

        return $itens;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolverComposicaoSelecao(Product $product, array $item): PdvComposicaoSelecao
    {
        $selecao = PdvComposicaoSelecao::fromArray($item);

        if (! ($product->is_composicao ?? false)) {
            return PdvComposicaoSelecao::vazia();
        }

        $componentes = ProductComposition::query()
            ->where('product_id', $product->id)
            ->get(['component_product_id', 'is_adicional']);

        $baseIds = [];
        $adicionalIds = [];

        foreach ($componentes as $c) {
            $id = (int) $c->component_product_id;

            if ((bool) ($c->is_adicional ?? false)) {
                $adicionalIds[$id] = $id;
            } else {
                $baseIds[$id] = $id;
            }
        }

        $removidos = array_values(array_filter(
            $selecao->removidos,
            static fn (int $id): bool => isset($baseIds[$id]),
        ));
        $adicionais = array_values(array_filter(
            $selecao->adicionais,
            static fn (int $id): bool => isset($adicionalIds[$id]),
        ));

        return new PdvComposicaoSelecao($removidos, $adicionais);
    }

    private function acrescimoAdicionais(Product $product, PdvComposicaoSelecao $selecao, float $quantidadeKit): float
    {
        if ($selecao->adicionais === [] || ! ($product->is_composicao ?? false)) {
            return 0.0;
        }

        $linhas = ProductComposition::query()
            ->where('product_id', $product->id)
            ->where('is_adicional', true)
            ->whereIn('component_product_id', $selecao->adicionais)
            ->get(['preco', 'quantidade', 'component_product_id']);

        $total = 0.0;

        foreach ($linhas as $linha) {
            $total += ((float) $linha->preco) * ((float) $linha->quantidade) * $quantidadeKit;
        }

        return round($total, 2);
    }

    /**
     * Mesma regra do PDV: item igual (produto, preço, sem grade/série) soma a quantidade.
     * Observação diferente vira outra linha para a cozinha ver.
     *
     * @param  list<array<string, mixed>>  $atuais
     * @param  list<array<string, mixed>>  $novos
     * @return list<array<string, mixed>>
     */
    private function juntarItens(array $atuais, array $novos): array
    {
        foreach ($novos as $novo) {
            $juntou = false;

            foreach ($atuais as $index => $item) {
                if ((int) ($item['product_id'] ?? 0) === $novo['product_id']
                    && empty($item['product_grade_id'])
                    && empty($item['product_serial_id'])
                    && round((float) ($item['preco'] ?? 0), 2) === round((float) $novo['preco'], 2)
                    && (float) ($item['desconto'] ?? 0) == 0.0
                    && (string) ($item['obs'] ?? '') === (string) ($novo['obs'] ?? '')
                    && PdvComposicaoSelecao::fromArray($item)->chave() === PdvComposicaoSelecao::fromArray($novo)->chave()) {
                    $quantidade = round((float) ($item['quantidade'] ?? 0) + (float) $novo['quantidade'], 3);
                    $fatorNovo = (float) $novo['quantidade'] > 0
                        ? ((float) ($novo['acrescimo'] ?? 0) / (float) $novo['quantidade'])
                        : 0.0;
                    $acrescimo = round($quantidade * $fatorNovo, 2);
                    $atuais[$index]['quantidade'] = $quantidade;
                    $atuais[$index]['acrescimo'] = $acrescimo;
                    $atuais[$index]['total'] = round(($quantidade * (float) $novo['preco']) + $acrescimo, 2);
                    $juntou = true;

                    break;
                }
            }

            if (! $juntou) {
                $atuais[] = $novo;
            }
        }

        return array_values($atuais);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lancamentoExistente(string $uuid, int $empresaId): ?array
    {
        $registro = PdvMesaLancamento::query()->where('uuid', $uuid)->first();

        if ($registro === null) {
            return null;
        }

        if ((int) $registro->empresa_id !== $empresaId) {
            throw new MesasConflito(MesasConflito::MESA_FECHADA, 'Lançamento pertence a outra empresa.');
        }

        $mesa = PdvMesa::query()
            ->where('empresa_id', $empresaId)
            ->where('numero', $registro->numero)
            ->first();

        return [
            'ok' => true,
            'uuid' => $uuid,
            'duplicado' => true,
            'mesa' => $this->painel->resumo((int) $registro->numero, $mesa),
        ];
    }

    private function ocupada(string $rotulo, string $quem): MesasConflito
    {
        return new MesasConflito(
            MesasConflito::MESA_OCUPADA,
            $rotulo.' está aberta no caixa'.($quem !== '' ? ' ('.$quem.')' : '').'. O envio será repetido automaticamente.',
            retry: true,
        );
    }

    private function quantidade(mixed $valor): float
    {
        return round(min(9999.0, max(0.001, (float) $valor)), 3);
    }

    private function obs(mixed $valor): string
    {
        $texto = preg_replace('/\s+/u', ' ', trim((string) ($valor ?? ''))) ?? '';

        return mb_strtoupper(mb_substr($texto, 0, 140), 'UTF-8');
    }

    private function normalizarData(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        if ($texto === '') {
            return null;
        }

        return substr(str_replace('T', ' ', $texto), 0, 19);
    }
}
