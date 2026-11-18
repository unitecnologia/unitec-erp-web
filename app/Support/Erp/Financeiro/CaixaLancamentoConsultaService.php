<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaLancamento;
use App\Models\CaixaLivroMovimento;
use App\Models\ForcaVendasOrder;
use App\Models\OrdemServico;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvVenda;
use App\Models\Person;
use App\Support\Erp\CaixaHistoricoExibicao;
use App\Support\Erp\CaixaListRowFormatter;
use App\Support\Erp\ContaReceberPedidoExibicao;
use App\Support\Erp\ErpContext;
use App\Support\Pix\PixPdvLivroCaixaService;
use Carbon\Carbon;

/**
 * Monta a consulta somente leitura do lançamento de caixa (ícone do olho).
 * Uma única estrutura para qualquer conta (CAIXA, GERAL, PDV).
 * Não inventa vínculo por coincidência de número de pedido.
 */
final class CaixaLancamentoConsultaService
{
    /** Espelho virtual de transferência no fechamento PDV (CaixaPdvMovimentacaoQuery). */
    private const PDV_ID_TRANSFERENCIA = 1_000_000_000;

    public function __construct(
        private readonly CaixaLancamentoOrigemResolver $origemResolver = new CaixaLancamentoOrigemResolver,
    ) {}

    /**
     * @return array{
     *     eh_grupo: bool,
     *     codigo: string,
     *     emissao: string,
     *     documento: string,
     *     historico: string,
     *     plano_contas: string,
     *     conta: string,
     *     entrada: string,
     *     saida: string,
     *     tem_detalhe: bool,
     *     detalhes: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>,
     *     membros: list<array<string, string>>,
     *     pode_ver_itens: bool,
     *     venda_id: int|null,
     *     os_id: int|null,
     *     pdv_venda_id: int|null,
     *     valor_lancamento: string,
     *     total_venda: string|null
     * }
     */
    public function montar(CaixaLancamento $lancamento): array
    {
        $this->assertEmpresa($lancamento);

        $origem = $this->resolverOrigem($lancamento);
        $vinculo = $this->resolverVinculoItens($lancamento, $origem);

        $valorLanc = $this->valorMovimento($lancamento);
        $totalVenda = isset($origem['total_venda']) ? (float) $origem['total_venda'] : null;

        $linhas = $origem['linhas'] ?? [];
        if ($totalVenda !== null && abs($totalVenda - $valorLanc) >= 0.009) {
            $linhas = $this->garantirLinhaValor($linhas, 'Valor do lançamento', $valorLanc);
            $linhas = $this->garantirLinhaValor($linhas, 'Total da venda', $totalVenda);
        } elseif ($linhas === []) {
            $linhas = array_values(array_filter([
                $this->linha('Origem', $this->origemBasica($lancamento)),
                $this->linha('Valor do lançamento', $this->money($valorLanc), money: true),
            ]));
        } else {
            $linhas = $this->garantirLinhaValor($linhas, 'Valor do lançamento', $valorLanc);
        }

        return [
            'eh_grupo' => false,
            'codigo' => filled($lancamento->codigo) ? (string) $lancamento->codigo : '—',
            'emissao' => $this->formatEmissao($lancamento),
            'documento' => $this->documentoPedido($lancamento),
            'historico' => CaixaListRowFormatter::comSessaoPdv(
                CaixaHistoricoExibicao::paraExibicao(
                    (string) $lancamento->historico,
                    ContaReceberPedidoExibicao::mapa([$lancamento]),
                ),
                $lancamento,
            ) ?: '—',
            'plano_contas' => mb_strtoupper((string) ($lancamento->plano_contas ?? ''), 'UTF-8') ?: '—',
            'conta' => mb_strtoupper($lancamento->conta?->nome ?? '—', 'UTF-8'),
            'entrada' => number_format((float) $lancamento->entrada, 2, ',', '.'),
            'saida' => number_format((float) $lancamento->saida, 2, ',', '.'),
            'tem_detalhe' => $linhas !== [],
            'detalhes' => $linhas,
            'membros' => [],
            'pode_ver_itens' => $vinculo['pode'],
            'venda_id' => $vinculo['venda_id'],
            'os_id' => $vinculo['os_id'],
            'pdv_venda_id' => $vinculo['pdv_venda_id'],
            'valor_lancamento' => $this->money($valorLanc),
            'total_venda' => $totalVenda !== null ? $this->money($totalVenda) : null,
        ];
    }

    /**
     * @param  list<CaixaLancamento>  $ordenados
     * @param  list<array{codigo: string, historico: string, plano_contas: string, entrada: string, saida: string}>  $membros
     * @return array<string, mixed>
     */
    public function montarGrupo(CaixaLancamento $primeiro, array $ordenados, array $membros, float $sumEntrada, float $sumSaida): array
    {
        $this->assertEmpresa($primeiro);

        $pedidosMonitor = ContaReceberPedidoExibicao::mapa($ordenados);
        $grupo = \App\Support\Erp\CaixaListAgrupamentoVisual::montarLinhaGrupo($ordenados, $pedidosMonitor);
        $origem = $this->resolverOrigem($primeiro);
        $vinculo = $this->resolverVinculoItens($primeiro, $origem);

        $planos = [];
        foreach ($membros as $membro) {
            $plano = trim((string) ($membro['plano_contas'] ?? ''));
            if ($plano !== '' && $plano !== '—') {
                $planos[$plano] = true;
            }
        }

        return [
            'eh_grupo' => true,
            'codigo' => count($membros).' lançamentos',
            'emissao' => $this->formatEmissao($primeiro),
            'documento' => $this->documentoPedido($primeiro),
            'historico' => (string) ($grupo->getAttribute('caixa_grupo_resumo') ?? '—'),
            'plano_contas' => $planos === [] ? '—' : implode(' · ', array_keys($planos)),
            'conta' => mb_strtoupper($primeiro->conta?->nome ?? '—', 'UTF-8'),
            'entrada' => number_format($sumEntrada, 2, ',', '.'),
            'saida' => number_format($sumSaida, 2, ',', '.'),
            'tem_detalhe' => ($origem['linhas'] ?? []) !== [],
            'detalhes' => $origem['linhas'] ?? [],
            'membros' => $membros,
            'pode_ver_itens' => $vinculo['pode'],
            'venda_id' => $vinculo['venda_id'],
            'os_id' => $vinculo['os_id'],
            'pdv_venda_id' => $vinculo['pdv_venda_id'],
            'valor_lancamento' => $this->money($sumEntrada > 0 ? $sumEntrada : $sumSaida),
            'total_venda' => isset($origem['total_venda']) ? $this->money((float) $origem['total_venda']) : null,
        ];
    }

    /**
     * Abertura virtual do Livro Caixa (troco inicial) — mesma janela padronizada.
     *
     * @return array<string, mixed>|null
     */
    public function montarAberturaLivro(int $virtualId, ?int $contaFilter = null): ?array
    {
        if (! CaixaLivroAberturaDisplay::isLinhaAbertura($virtualId)) {
            return null;
        }

        $movId = CaixaLivroAberturaDisplay::movimentoIdFromVirtual($virtualId);
        $mov = CaixaLivroMovimento::query()
            ->with(['abertoPor:id,name', 'conta:id,nome'])
            ->find($movId);

        if (! $mov) {
            return null;
        }

        $empresaId = ErpContext::currentEmpresaId();
        if ($empresaId && (int) $mov->empresa_id !== (int) $empresaId) {
            return null;
        }

        if ($contaFilter && is_numeric($contaFilter) && (int) $mov->caixa_conta_id !== (int) $contaFilter) {
            return null;
        }

        $linha = CaixaLivroAberturaDisplay::linhaVirtualAbertura($mov);
        if (! $linha) {
            return null;
        }

        $usuario = trim((string) ($mov->abertoPor?->name ?? ''));
        $troco = round((float) $mov->troco_inicial, 2);
        $abertoEm = $linha->getAttribute('livro_abertura_em') ?: ($mov->aberto_em?->format('d/m/Y H:i') ?? '—');

        return [
            'eh_grupo' => false,
            'codigo' => '—',
            'emissao' => (string) $abertoEm,
            'documento' => 'ABERTURA',
            'historico' => (string) ($linha->historico ?: '—'),
            'plano_contas' => 'CONFERÊNCIA',
            'conta' => mb_strtoupper($linha->conta?->nome ?? $mov->conta?->nome ?? '—', 'UTF-8'),
            'entrada' => number_format($troco, 2, ',', '.'),
            'saida' => '0,00',
            'tem_detalhe' => true,
            'detalhes' => array_values(array_filter([
                $this->linha('Origem', 'Abertura de caixa'),
                $this->linha('Tipo', 'Troco inicial (conferência)'),
                $this->linha('Operador', $usuario !== '' ? mb_strtoupper($usuario, 'UTF-8') : null),
                $this->linha('Aberto em', (string) $abertoEm),
                $this->linha('Valor do lançamento', $this->money($troco), money: true),
                $this->linha(
                    'Observação',
                    'Linha de conferência do troco inicial — sem lançamento financeiro vinculado.',
                    wide: true,
                ),
            ])),
            'membros' => [],
            'pode_ver_itens' => false,
            'venda_id' => null,
            'os_id' => null,
            'pdv_venda_id' => null,
            'valor_lancamento' => $this->money($troco),
            'total_venda' => null,
        ];
    }

    public static function isIdMovimentoPdvSessao(int $id): bool
    {
        return $id < 0 && $id > -self::PDV_ID_TRANSFERENCIA;
    }

    /**
     * @return array{
     *     tem_detalhe: bool,
     *     linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>,
     *     total_venda?: float,
     *     pdv_venda_id?: int|null
     * }
     */
    private function resolverOrigem(CaixaLancamento $lancamento): array
    {
        if (self::isIdMovimentoPdvSessao((int) $lancamento->getKey())) {
            return $this->detalhePdvMovimento($lancamento);
        }

        $base = $this->origemResolver->resolver($lancamento);

        // Sessão PDV espelhada (transferência/sangria/fundo) ou atributos de sessão.
        if (! ($base['tem_detalhe'] ?? false) && (int) ($lancamento->getAttribute('pdv_sessao_id') ?? 0) > 0) {
            return $this->detalheSessaoPdvSemVenda($lancamento);
        }

        return $base;
    }

    /**
     * @return array{
     *     tem_detalhe: bool,
     *     linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>,
     *     total_venda?: float,
     *     pdv_venda_id?: int|null
     * }
     */
    private function detalhePdvMovimento(CaixaLancamento $lancamento): array
    {
        $movId = abs((int) $lancamento->getKey());
        $movimento = PdvCaixaMovimento::query()
            ->with([
                'sessao:id,empresa_id,user_id,terminal_id',
                'sessao.user:id,name',
                'venda:id,numero,total,person_id,vendedor_id,vendedor_nome,forma_pagamento',
                'venda.person:id,nome_razao,apelido_fantasia',
                'venda.vendedor:id,nome',
                'venda.pagamentos',
                'venda.nfce:id,pdv_venda_id,numero,serie,status,chave,protocolo',
            ])
            ->find($movId);

        if (! $movimento) {
            return $this->detalheSessaoPdvSemVenda($lancamento);
        }

        $empresaId = ErpContext::currentEmpresaId();
        if ($empresaId && (int) ($movimento->sessao?->empresa_id ?? 0) !== (int) $empresaId) {
            return ['tem_detalhe' => false, 'linhas' => []];
        }

        $tipo = mb_strtolower(trim((string) $movimento->tipo), 'UTF-8');
        $valor = $this->valorMovimento($lancamento);
        $venda = $movimento->venda;
        $pdvVendaId = $venda?->id ? (int) $venda->id : null;

        if ($venda && in_array($tipo, ['venda', 'estorno'], true)) {
            return $this->detalhePdvVenda($lancamento, $movimento, $venda, $valor, $tipo === 'estorno');
        }

        $origemLabel = match ($tipo) {
            'abertura' => 'Abertura de caixa (PDV)',
            'suprimento' => 'Suprimento de caixa (PDV)',
            'sangria' => 'Sangria de caixa (PDV)',
            'recebimento' => 'Recebimento no PDV',
            'estorno' => 'Estorno PDV',
            default => 'Movimento PDV',
        };

        return [
            'tem_detalhe' => true,
            'pdv_venda_id' => $pdvVendaId,
            'linhas' => array_values(array_filter([
                $this->linha('Origem', $origemLabel),
                $this->linha('Tipo', $tipo !== '' ? mb_strtoupper($tipo, 'UTF-8') : null),
                $this->linha('Forma de pagamento', $this->upperOrNull($movimento->forma_pagamento)),
                $this->linha('Operador', $this->upperOrNull($lancamento->getAttribute('pdv_operador') ?: $movimento->sessao?->user?->name)),
                $this->linha('Terminal', $this->upperOrNull($lancamento->getAttribute('pdv_terminal'))),
                $this->linha('Sessão', ($sid = (int) ($lancamento->getAttribute('pdv_sessao_id') ?: $movimento->pdv_caixa_sessao_id)) > 0 ? 'CX '.$sid : null),
                $this->linha('Destino sangria', $this->upperOrNull($movimento->sangria_destino)),
                $this->linha('Valor do lançamento', $this->money($valor), money: true),
            ])),
        ];
    }

    /**
     * @return array{
     *     tem_detalhe: bool,
     *     linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>,
     *     total_venda: float,
     *     pdv_venda_id: int
     * }
     */
    private function detalhePdvVenda(
        CaixaLancamento $lancamento,
        PdvCaixaMovimento $movimento,
        PdvVenda $venda,
        float $valorLancamento,
        bool $ehEstorno,
    ): array {
        $formas = [];
        foreach ($venda->pagamentos as $pag) {
            $desc = trim((string) $pag->descricaoComCanhoto());
            if ($desc !== '' && $desc !== '—') {
                $formas[] = mb_strtoupper($desc, 'UTF-8');
            }
        }
        if ($formas === []) {
            $fallback = trim((string) ($movimento->forma_pagamento ?: $venda->forma_pagamento ?: ''));
            if ($fallback !== '') {
                $formas[] = mb_strtoupper($fallback, 'UTF-8');
            }
        }

        $nfce = $venda->nfce;
        $docFiscal = null;
        if ($nfce) {
            $partes = array_filter([
                filled($nfce->numero) ? 'NFC-e '.$nfce->numero : null,
                filled($nfce->serie) ? 'Série '.$nfce->serie : null,
                filled($nfce->status) ? mb_strtoupper((string) $nfce->status, 'UTF-8') : null,
            ]);
            $docFiscal = $partes !== [] ? implode(' · ', $partes) : null;
        }

        $cliente = $this->nomePessoa($venda->person);
        $vendedor = trim((string) ($venda->vendedor_nome ?: $venda->vendedor?->nome ?: ''));
        $pedido = filled($venda->numero)
            ? (ltrim((string) $venda->numero, '0') ?: (string) $venda->numero)
            : null;
        $totalVenda = round((float) $venda->total, 2);

        return [
            'tem_detalhe' => true,
            'total_venda' => $totalVenda,
            'pdv_venda_id' => (int) $venda->id,
            'linhas' => array_values(array_filter([
                $this->linha('Origem', 'Venda PDV'),
                $this->linha('Tipo', $ehEstorno ? 'Estorno' : 'Recebimento'),
                $this->linha('Pedido', $pedido),
                $this->linha('Cliente', $cliente),
                $this->linha('Vendedor', $vendedor !== '' ? mb_strtoupper($vendedor, 'UTF-8') : null),
                $this->linha('Meio de pagamento', $formas !== [] ? implode(' · ', $formas) : null, wide: true),
                $this->linha('Documento fiscal', $docFiscal, wide: true),
                filled($nfce?->chave) ? $this->linha('Chave NFC-e', (string) $nfce->chave, wide: true) : null,
                $this->linha('Operador', $this->upperOrNull($lancamento->getAttribute('pdv_operador') ?: $movimento->sessao?->user?->name)),
                $this->linha('Terminal', $this->upperOrNull($lancamento->getAttribute('pdv_terminal'))),
                $this->linha('Sessão', ($sid = (int) ($lancamento->getAttribute('pdv_sessao_id') ?: $movimento->pdv_caixa_sessao_id)) > 0 ? 'CX '.$sid : null),
                $this->linha('Valor do lançamento', $this->money($valorLancamento), money: true),
                $this->linha('Total da venda', $this->money($totalVenda), money: true),
                $ehEstorno
                    ? $this->linha('Estorno', 'Este lançamento é um estorno.', wide: true, destaque: 'estorno')
                    : null,
            ])),
        ];
    }

    /**
     * @return array{tem_detalhe: bool, linhas: list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>}
     */
    private function detalheSessaoPdvSemVenda(CaixaLancamento $lancamento): array
    {
        $historico = trim((string) $lancamento->historico);
        $origem = 'Movimento PDV';
        if (preg_match('/abertura/iu', $historico)) {
            $origem = 'Abertura de caixa (PDV)';
        } elseif (preg_match('/sangria/iu', $historico)) {
            $origem = 'Sangria de caixa (PDV)';
        } elseif (preg_match('/suprimento/iu', $historico)) {
            $origem = 'Suprimento de caixa (PDV)';
        } elseif (preg_match('/fechamento/iu', $historico)) {
            $origem = 'Fechamento PDV';
        }

        return [
            'tem_detalhe' => true,
            'linhas' => array_values(array_filter([
                $this->linha('Origem', $origem),
                $this->linha('Operador', $this->upperOrNull($lancamento->getAttribute('pdv_operador'))),
                $this->linha('Terminal', $this->upperOrNull($lancamento->getAttribute('pdv_terminal'))),
                $this->linha('Sessão', ($sid = (int) ($lancamento->getAttribute('pdv_sessao_id') ?? 0)) > 0 ? 'CX '.$sid : null),
                $this->linha('Valor do lançamento', $this->money($this->valorMovimento($lancamento)), money: true),
            ])),
        ];
    }

    /**
     * @param  array{pdv_venda_id?: int|null}  $origem
     * @return array{pode: bool, venda_id: int|null, os_id: int|null, pdv_venda_id: int|null}
     */
    private function resolverVinculoItens(CaixaLancamento $lancamento, array $origem): array
    {
        $pdvVendaId = isset($origem['pdv_venda_id']) ? (int) $origem['pdv_venda_id'] : 0;
        if ($pdvVendaId <= 0 && self::isIdMovimentoPdvSessao((int) $lancamento->getKey())) {
            $pdvVendaId = (int) (PdvCaixaMovimento::query()
                ->whereKey(abs((int) $lancamento->getKey()))
                ->value('pdv_venda_id') ?? 0);
        }

        if ($pdvVendaId > 0) {
            return [
                'pode' => true,
                'venda_id' => null,
                'os_id' => null,
                'pdv_venda_id' => $pdvVendaId,
            ];
        }

        $documento = trim((string) ($lancamento->documento ?? ''));
        $historico = (string) ($lancamento->historico ?? '');

        if (preg_match('/^OS-(\d+)$/', $documento, $m) === 1
            || preg_match('/^OS\s+(\d+)/iu', $historico, $m) === 1) {
            $numero = (int) $m[1];
            $padded = str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
            $ordem = OrdemServico::query()
                ->where(function ($q) use ($numero, $padded): void {
                    $q->where('numero', (string) $numero)
                        ->orWhere('numero', $padded);
                })
                ->first(['id', 'venda_id']);

            if ($ordem) {
                return [
                    'pode' => true,
                    'venda_id' => $ordem->venda_id ? (int) $ordem->venda_id : null,
                    'os_id' => (int) $ordem->id,
                    'pdv_venda_id' => null,
                ];
            }
        }

        if (preg_match('/^FV-(\d+)/', $documento, $m) === 1
            || preg_match('/\[FV-(\d+)\]/i', $historico, $m) === 1) {
            $order = ForcaVendasOrder::query()->find((int) $m[1], ['id', 'venda_id']);
            if ($order?->venda_id) {
                return [
                    'pode' => true,
                    'venda_id' => (int) $order->venda_id,
                    'os_id' => null,
                    'pdv_venda_id' => null,
                ];
            }
        }

        // Sem lookup por "VENDA PDV #n" em vendas comerciais — evita vínculo por coincidência.

        return ['pode' => false, 'venda_id' => null, 'os_id' => null, 'pdv_venda_id' => null];
    }

    private function assertEmpresa(CaixaLancamento $lancamento): void
    {
        $empresaId = ErpContext::currentEmpresaId();
        if (! $empresaId) {
            return;
        }

        $lancEmpresa = (int) ($lancamento->empresa_id ?? 0);
        if ($lancEmpresa > 0 && $lancEmpresa !== (int) $empresaId) {
            throw new \RuntimeException('Lançamento de outra empresa.');
        }
    }

    private function documentoPedido(CaixaLancamento $lancamento): string
    {
        $numeroPix = PixPdvLivroCaixaService::numeroPedidoExibicao(
            $lancamento->documento,
            (string) ($lancamento->historico ?? ''),
        );
        if ($numeroPix !== null) {
            return $numeroPix;
        }

        $texto = ContaReceberPedidoExibicao::texto(
            $lancamento->documento,
            ContaReceberPedidoExibicao::mapa([$lancamento]),
        );

        return $texto !== '' ? $texto : '—';
    }

    private function origemBasica(CaixaLancamento $lancamento): string
    {
        $historico = (string) $lancamento->historico;
        if (str_starts_with(mb_strtoupper($historico, 'UTF-8'), '[MANUAL]')) {
            return 'Lançamento manual';
        }

        return 'Lançamento de caixa';
    }

    private function formatEmissao(CaixaLancamento $lancamento): string
    {
        $evento = (string) ($lancamento->getAttribute('livro_evento') ?? '');
        if ($evento === 'abertura') {
            $abertura = trim((string) ($lancamento->getAttribute('livro_abertura_em') ?? ''));
            if ($abertura !== '') {
                return $abertura;
            }
        }

        // Cast `date` zera a hora; usar bruto/ordem/created_at quando houver horário.
        $candidatos = [
            $lancamento->getRawOriginal('emissao') ?? null,
            $lancamento->getAttribute('ordem'),
            $lancamento->getAttributes()['created_at'] ?? null,
            $lancamento->created_at,
            $lancamento->emissao,
        ];

        $comHora = null;
        $soData = null;

        foreach ($candidatos as $candidato) {
            if ($candidato === null || $candidato === '') {
                continue;
            }

            try {
                $dt = $candidato instanceof \DateTimeInterface
                    ? Carbon::instance(\DateTimeImmutable::createFromInterface($candidato))
                    : Carbon::parse((string) $candidato);
            } catch (\Throwable) {
                continue;
            }

            if ($dt->format('H:i:s') !== '00:00:00') {
                $comHora ??= $dt;

                continue;
            }

            $soData ??= $dt;
        }

        if ($comHora) {
            return $comHora->format('d/m/Y H:i');
        }

        return $soData ? $soData->format('d/m/Y H:i') : '—';
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

    private function upperOrNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? mb_strtoupper($text, 'UTF-8') : null;
    }

    private function money(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    /**
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
     * @param  list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>  $linhas
     * @return list<array{label: string, value: string, wide?: bool, money?: bool, destaque?: string}>
     */
    private function garantirLinhaValor(array $linhas, string $label, float $valor): array
    {
        foreach ($linhas as $linha) {
            if (($linha['label'] ?? '') === $label) {
                return $linhas;
            }
        }

        $nova = $this->linha($label, $this->money($valor), money: true);
        if ($nova) {
            $linhas[] = $nova;
        }

        return $linhas;
    }
}
