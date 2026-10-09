<?php

namespace App\Support\Erp\Os;

use App\Models\CaixaLancamento;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\OrdemServico;
use App\Models\OrdemServicoImagem;
use App\Models\OrdemServicoItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Dados de exibição do PDF/impressão da OS. Não altera cálculos nem regras.
 */
final class OrdemServicoReportData
{
    /**
     * @return array<string, mixed>
     */
    public static function for(
        OrdemServico $ordem,
        ?Empresa $empresa,
        bool $autoPrint = false,
        bool $embedded = false,
        bool $omitMidias = false,
        bool $preferPagamentosSnapshot = false,
        bool $tecnica = false,
    ): array {
        $omitMidias = $omitMidias || $tecnica;

        $ordem->loadMissing([
            'cliente',
            'atendente',
            'itens.product',
            ...($omitMidias ? [] : ['imagens']),
        ]);

        $servicos = $ordem->itens
            ->filter(static fn (OrdemServicoItem $item): bool => ($item->tipo ?? '') === 'S')
            ->values();
        $pecas = $ordem->itens
            ->filter(static fn (OrdemServicoItem $item): bool => ($item->tipo ?? 'P') === 'P')
            ->values();

        $fotos = collect();
        $assinatura = null;

        if (! $omitMidias) {
            $fotos = $ordem->imagens
                ->filter(static fn (OrdemServicoImagem $img): bool => $img->tipo === OrdemServicoImagem::TIPO_FOTO)
                ->values();
            $assinatura = $ordem->imagens
                ->first(static fn (OrdemServicoImagem $img): bool => $img->tipo === OrdemServicoImagem::TIPO_ASSINATURA);
        }

        $pagamentos = self::buildPagamentos($ordem, preferSnapshot: $preferPagamentosSnapshot);
        $clienteExibicao = self::clienteExibicao($ordem);

        return [
            'ordem' => $ordem,
            'empresa' => $empresa,
            'numero' => self::formatNumero($ordem->numero),
            'statusLabel' => $ordem->situacaoLabel(),
            'empresaEndereco' => self::formatEmpresaEndereco($empresa),
            'logoDataUri' => self::logoDataUri($empresa),
            'clienteEmail' => $clienteExibicao['email'],
            'clienteEndereco' => $clienteExibicao['endereco'],
            'clienteDocumento' => $clienteExibicao['documento'],
            'clienteTelefone' => $clienteExibicao['telefone'],
            'equipamentoLinhas' => self::equipamentoLinhas($ordem),
            'servicos' => self::mapItens($servicos),
            'pecas' => self::mapPecas($pecas),
            'totais' => self::buildTotais($ordem, $servicos, $pecas),
            'pagamentos' => $pagamentos,
            'meiosPagamento' => $pagamentos !== [] ? self::pagamentosTextoResumo($pagamentos) : null,
            'abertura' => self::dataHora($ordem->data_inicio, $ordem->hora_inicio),
            'conclusao' => self::dataHora(
                $ordem->data_termino ?: $ordem->data_entrega,
                $ordem->hora_termino ?: $ordem->hora_entrega,
            ),
            'tecnico' => trim((string) ($ordem->atendente?->nome ?? '')),
            'fotos' => $omitMidias ? [] : self::mapMidias($fotos),
            'assinatura' => (! $omitMidias && $assinatura instanceof OrdemServicoImagem)
                ? [
                    'data_uri' => self::fileDataUri((string) $assinatura->caminho, (string) ($assinatura->mime ?: 'image/png')),
                    'em' => optional($assinatura->created_at)?->format('d/m/Y H:i'),
                ]
                : null,
            'printedAt' => now(),
            'autoPrint' => $autoPrint,
            'embedded' => $embedded,
            'tecnica' => $tecnica,
        ];
    }

    /**
     * @param  Collection<int, OrdemServicoItem>  $itens
     * @return list<array{codigo: string, descricao: string, qtd: string, unitario: string, desconto: string, acrescimo: string, total: string}>
     */
    private static function mapItens(Collection $itens): array
    {
        return $itens->map(static function (OrdemServicoItem $item): array {
            return self::mapItemRow($item, withEan: false);
        })->all();
    }

    /**
     * @param  Collection<int, OrdemServicoItem>  $itens
     * @return list<array{codigo: string, descricao: string, ean: string, qtd: string, unitario: string, desconto: string, acrescimo: string, total: string}>
     */
    private static function mapPecas(Collection $itens): array
    {
        return $itens->map(static function (OrdemServicoItem $item): array {
            return self::mapItemRow($item, withEan: true);
        })->all();
    }

    /**
     * @return array<string, string>
     */
    private static function mapItemRow(OrdemServicoItem $item, bool $withEan): array
    {
        $descricao = trim((string) ($item->discriminacao ?: $item->nome ?: ''));
        $row = [
            'codigo' => trim((string) ($item->product?->codigo ?? $item->codigo_legado ?? '')),
            'descricao' => $descricao !== '' ? mb_strtoupper($descricao, 'UTF-8') : '—',
            'servico_prestado' => trim((string) ($item->servico_prestado ?? '')),
            'qtd' => self::qtd($item->qtd),
            'unitario' => self::money($item->preco),
            'desconto' => self::moneyOpcional($item->desconto),
            'acrescimo' => self::moneyOpcional($item->acrescimo),
            'total' => self::money($item->total),
        ];

        if ($withEan) {
            $ean = trim((string) ($item->product?->codigo_barras ?? ''));
            if ($ean === '') {
                $ean = trim((string) ($item->product?->codigo_barras_caixa ?? ''));
            }
            $row['ean'] = $ean;
        }

        return $row;
    }

    /**
     * @param  Collection<int, OrdemServicoItem>  $servicos
     * @param  Collection<int, OrdemServicoItem>  $pecas
     * @return array<string, string|null>
     */
    private static function buildTotais(OrdemServico $ordem, Collection $servicos, Collection $pecas): array
    {
        $descItensPecas = self::sumItens($pecas, 'desconto');
        $descItensServicos = self::sumItens($servicos, 'desconto');
        $acrPecas = self::sumItens($pecas, 'acrescimo');
        $acrServicos = self::sumItens($servicos, 'acrescimo');
        $descGlobalPecas = (float) ($ordem->vl_desc_pecas ?? 0);
        $descGlobalServicos = (float) ($ordem->vl_desc_servicos ?? 0);
        $descPecasTotal = $descItensPecas + $descGlobalPecas;
        $descServicosTotal = $descItensServicos + $descGlobalServicos;
        $descontoTotal = $descPecasTotal + $descServicosTotal;

        $subPecas = (float) ($ordem->subtotal_pecas ?? 0);
        $subServicos = (float) ($ordem->subtotal_servicos ?? 0);
        if ($subPecas <= 0.0001 && $pecas->isNotEmpty()) {
            $subPecas = self::subtotalBrutoItens($pecas);
        }
        if ($subServicos <= 0.0001 && $servicos->isNotEmpty()) {
            $subServicos = self::subtotalBrutoItens($servicos);
        }

        $totalServicosNet = (float) ($ordem->total_servicos ?? 0);
        $totalPecasNet = (float) ($ordem->total_produtos ?? 0);
        $detalhaServicos = $descServicosTotal > 0.0001
            || $acrServicos > 0.0001
            || abs($subServicos - $totalServicosNet) > 0.0001;
        $detalhaPecas = $descPecasTotal > 0.0001
            || $acrPecas > 0.0001
            || abs($subPecas - $totalPecasNet) > 0.0001;

        return [
            'subtotal_servicos' => $detalhaServicos && $subServicos > 0.0001 ? self::money($subServicos) : null,
            'subtotal_produtos' => $detalhaPecas && $subPecas > 0.0001 ? self::money($subPecas) : null,
            'acrescimo_servicos' => $acrServicos > 0.0001 ? self::money($acrServicos) : null,
            'acrescimo_produtos' => $acrPecas > 0.0001 ? self::money($acrPecas) : null,
            'desconto_servicos' => $descServicosTotal > 0.0001 ? self::money($descServicosTotal) : null,
            'desconto_produtos' => $descPecasTotal > 0.0001 ? self::money($descPecasTotal) : null,
            'servicos' => self::money($ordem->total_servicos),
            'produtos' => self::money($ordem->total_produtos),
            'desconto' => $descontoTotal > 0.0001 ? self::money($descontoTotal) : null,
            'geral' => self::money($ordem->total_geral),
        ];
    }

    /**
     * @param  Collection<int, OrdemServicoItem>  $itens
     */
    private static function subtotalBrutoItens(Collection $itens): float
    {
        return round($itens->sum(static function (OrdemServicoItem $item): float {
            $qtd = is_numeric($item->qtd) ? (float) $item->qtd : 0.0;
            $preco = is_numeric($item->preco) ? (float) $item->preco : 0.0;
            $acrescimo = is_numeric($item->acrescimo) ? (float) $item->acrescimo : 0.0;

            return round($qtd * $preco + $acrescimo, 2);
        }), 2);
    }

    /**
     * @param  Collection<int, OrdemServicoItem>  $itens
     */
    private static function sumItens(Collection $itens, string $campo): float
    {
        return round($itens->sum(static fn (OrdemServicoItem $item): float => is_numeric($item->{$campo})
            ? (float) $item->{$campo}
            : 0.0), 2);
    }

    /**
     * @return list<array{
     *     forma: string,
     *     valor: string,
     *     parcelas: list<array{dias: int, vencimento: string, valor: string}>|null
     * }>
     */
    private static function buildPagamentos(OrdemServico $ordem, bool $preferSnapshot = false): array
    {
        if ($preferSnapshot) {
            $fromSnapshot = self::buildPagamentosFromSnapshot($ordem);

            if ($fromSnapshot !== []) {
                return $fromSnapshot;
            }
        }

        $fromFinanceiro = self::buildPagamentosFromFinanceiro($ordem);

        if ($fromFinanceiro !== []) {
            return $fromFinanceiro;
        }

        return self::buildPagamentosFromSnapshot($ordem);
    }

    /**
     * @return list<array{
     *     forma: string,
     *     valor: string,
     *     parcelas: list<array{dias: int, vencimento: string, valor: string}>|null
     * }>
     */
    private static function buildPagamentosFromFinanceiro(OrdemServico $ordem): array
    {
        $formaLabels = ContaReceber::formaLabels();
        /** @var array<string, list<ContaReceber>> $grupos */
        $grupos = [];

        foreach (self::contasReceberOs($ordem) as $conta) {
            $forma = self::formaLabelConta($conta, $formaLabels);
            $grupos[$forma][] = $conta;
        }

        $out = [];

        foreach ($grupos as $forma => $contas) {
            usort($contas, static function (ContaReceber $a, ContaReceber $b): int {
                $va = optional($a->vencimento)?->format('Y-m-d') ?? '';
                $vb = optional($b->vencimento)?->format('Y-m-d') ?? '';
                $cmp = $va <=> $vb;

                return $cmp !== 0 ? $cmp : ((int) $a->id <=> (int) $b->id);
            });

            $total = round(collect($contas)->sum(static fn (ContaReceber $c): float => (float) $c->valor), 2);
            $exibeParcelas = self::formaExibeParcelas($forma) || count($contas) > 1;
            $parcelas = [];

            if ($exibeParcelas) {
                foreach ($contas as $conta) {
                    $emissao = $conta->emissao ?? $ordem->data_termino ?? $ordem->data_emissao;
                    $parcelas[] = [
                        'dias' => self::diasAteVencimento($emissao, $conta->vencimento),
                        'vencimento' => optional($conta->vencimento)?->format('d/m/Y') ?? '—',
                        'valor' => self::money($conta->valor),
                    ];
                }
            }

            $out[] = [
                'forma' => $forma,
                'valor' => self::money($total),
                'parcelas' => $parcelas !== [] ? $parcelas : null,
            ];
        }

        foreach (self::caixaLancamentosOs($ordem) as $lancamento) {
            $valor = (float) ($lancamento->entrada ?? 0);
            if ($valor <= 0.0001) {
                continue;
            }

            $out[] = [
                'forma' => self::formaDoHistorico((string) $lancamento->historico),
                'valor' => self::money($valor),
                'parcelas' => null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{
     *     forma: string,
     *     valor: string,
     *     parcelas: list<array{dias: int, vencimento: string, valor: string}>|null
     * }>
     */
    private static function buildPagamentosFromSnapshot(OrdemServico $ordem): array
    {
        $raw = $ordem->faturamento_pagamentos;

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $out = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $forma = mb_strtoupper(trim((string) ($item['forma'] ?? '')), 'UTF-8');
            $valor = is_numeric($item['valor'] ?? null) ? (float) $item['valor'] : 0.0;

            if ($forma === '' || $valor <= 0.0001) {
                continue;
            }

            $parcelas = null;
            $parcelasRaw = $item['parcelas'] ?? null;

            if (is_array($parcelasRaw) && $parcelasRaw !== []) {
                $parcelas = [];
                foreach ($parcelasRaw as $parcela) {
                    if (! is_array($parcela)) {
                        continue;
                    }
                    $parcelas[] = [
                        'dias' => max(0, (int) ($parcela['dias'] ?? 0)),
                        'vencimento' => trim((string) ($parcela['vencimento'] ?? '')) ?: '—',
                        'valor' => self::money($parcela['valor'] ?? 0),
                    ];
                }
                if ($parcelas === []) {
                    $parcelas = null;
                }
            }

            $out[] = [
                'forma' => $forma,
                'valor' => self::money($valor),
                'parcelas' => $parcelas,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{forma: string, valor: string, parcelas: list<array{dias: int, vencimento: string, valor: string}>|null}>  $pagamentos
     */
    private static function pagamentosTextoResumo(array $pagamentos): string
    {
        $partes = [];

        foreach ($pagamentos as $pagamento) {
            $texto = trim((string) ($pagamento['forma'] ?? '')).': '.trim((string) ($pagamento['valor'] ?? ''));
            $parcelas = $pagamento['parcelas'] ?? null;

            if (is_array($parcelas) && $parcelas !== []) {
                $prazos = collect($parcelas)
                    ->map(static fn (array $p): string => (int) ($p['dias'] ?? 0).' '.($p['vencimento'] ?? ''))
                    ->implode(', ');
                $texto .= ' ('.$prazos.')';
            }

            $partes[] = $texto;
        }

        return implode(' / ', $partes);
    }

    /**
     * @param  array<string, string>  $formaLabels
     */
    private static function formaLabelConta(ContaReceber $conta, array $formaLabels): string
    {
        $formaHist = self::formaDoHistorico((string) $conta->historico);

        if ($formaHist !== 'PAGAMENTO') {
            return $formaHist;
        }

        return $formaLabels[(string) $conta->forma] ?? mb_strtoupper((string) $conta->forma, 'UTF-8');
    }

    private static function formaExibeParcelas(string $forma): bool
    {
        $f = mb_strtoupper(trim($forma), 'UTF-8');

        return str_contains($f, 'BOLETO')
            || str_contains($f, 'CREDI')
            || str_contains($f, 'CHEQUE')
            || str_contains($f, 'CARTEIRA')
            || str_contains($f, 'PRAZO');
    }

    private static function diasAteVencimento(mixed $emissao, mixed $vencimento): int
    {
        if ($emissao === null || $vencimento === null) {
            return 0;
        }

        try {
            $de = Carbon::parse($emissao)->startOfDay();
            $ate = Carbon::parse($vencimento)->startOfDay();
        } catch (\Throwable) {
            return 0;
        }

        return max(0, (int) $de->diffInDays($ate, false));
    }

    /**
     * @return \Illuminate\Support\Collection<int, ContaReceber>
     */
    private static function contasReceberOs(OrdemServico $ordem): Collection
    {
        if (OrdemServicoFinanceiroVinculo::documentoBase($ordem) === null) {
            return collect();
        }

        return OrdemServicoFinanceiroVinculo::aplicar(ContaReceber::query(), $ordem)
            ->orderBy('emissao')
            ->orderBy('id')
            ->get();
    }

    /**
     * Entradas de caixa da OS ainda válidas: o estorno da reabertura (OsReabrirService) grava
     * uma saída no mesmo documento/conta e anula as entradas anteriores.
     *
     * @return \Illuminate\Support\Collection<int, CaixaLancamento>
     */
    private static function caixaLancamentosOs(OrdemServico $ordem): Collection
    {
        if (OrdemServicoFinanceiroVinculo::documentoBase($ordem) === null) {
            return collect();
        }

        $lancamentos = OrdemServicoFinanceiroVinculo::aplicar(CaixaLancamento::query(), $ordem)
            ->orderBy('id')
            ->get();

        $validas = collect();
        $grupos = $lancamentos->groupBy(
            static fn (CaixaLancamento $l): string => ((int) ($l->caixa_conta_id ?? 0)).'|'.(string) $l->documento
        );

        foreach ($grupos as $grupo) {
            $pendentes = [];
            $liquido = 0.0;

            foreach ($grupo as $lancamento) {
                $entrada = (float) ($lancamento->entrada ?? 0);
                $saida = (float) ($lancamento->saida ?? 0);

                if ($entrada > 0.0001) {
                    $pendentes[] = $lancamento;
                    $liquido += $entrada;
                }

                if ($saida > 0.0001) {
                    $liquido -= $saida;

                    if ($liquido <= 0.0001) {
                        $pendentes = [];
                        $liquido = 0.0;
                    }
                }
            }

            foreach ($pendentes as $lancamento) {
                $validas->push($lancamento);
            }
        }

        return $validas
            ->sortBy(static fn (CaixaLancamento $l): string => (optional($l->emissao)?->format('Y-m-d') ?? '').'|'.str_pad((string) $l->id, 12, '0', STR_PAD_LEFT))
            ->values();
    }

    private static function formaDoHistorico(string $historico): string
    {
        if (preg_match('/\(([^)]+)\)/u', $historico, $m) === 1) {
            return mb_strtoupper(trim($m[1]), 'UTF-8');
        }

        return 'PAGAMENTO';
    }

    private static function moneyOpcional(mixed $valor): string
    {
        $n = is_numeric($valor) ? (float) $valor : 0.0;

        return $n > 0.0001 ? self::money($n) : '—';
    }

    /**
     * @param  Collection<int, OrdemServicoImagem>  $imagens
     * @return list<array{data_uri: string}>
     */
    private static function mapMidias(Collection $imagens): array
    {
        $out = [];
        foreach ($imagens as $img) {
            $uri = self::fileDataUri((string) $img->caminho, (string) ($img->mime ?: 'image/jpeg'));
            if ($uri !== null) {
                $out[] = ['data_uri' => $uri];
            }
        }

        return $out;
    }

    /**
     * Campos do bloco Equipamento em linhas compactas (impressão e espelho).
     *
     * @return list<list<array{label: string, value: string}>>
     */
    public static function equipamentoLinhas(OrdemServico $ordem): array
    {
        $cell = static function (string $label, mixed $valor): ?array {
            $text = trim((string) ($valor ?? ''));
            if ($text === '') {
                return null;
            }

            return [
                'label' => $label,
                'value' => mb_strtoupper($text, 'UTF-8'),
            ];
        };

        $rows = [];
        foreach ([
            [
                $cell('Equipamento / Marca', $ordem->descricao ?: $ordem->marca ?: $ordem->marca_veiculo),
                $cell('Modelo', $ordem->modelo ?: $ordem->modelo_veiculo),
                $cell('Ano', $ordem->ano ?: $ordem->ano_veiculo),
            ],
            [
                $cell('Placa', $ordem->placa ?: $ordem->placa_veiculo),
                $cell('KM', $ordem->km),
                $cell('Nº Série / IMEI', $ordem->numero_serie),
            ],
            [
                $cell('Cor', $ordem->cor_veiculo),
                $cell('Chassi', $ordem->chassi_veiculo),
            ],
            [
                $cell('Descrição / Complemento', $ordem->descricao2),
            ],
        ] as $row) {
            $filled = array_values(array_filter($row));
            if ($filled !== []) {
                $rows[] = $filled;
            }
        }

        return $rows;
    }

    /**
     * Dados do cliente para impressão: snapshot da OS, com fallback do cadastro (`people`).
     *
     * @return array{documento: string, telefone: string, email: string, endereco: string}
     */
    public static function clienteExibicao(OrdemServico $ordem): array
    {
        $cliente = $ordem->cliente;

        $documento = trim((string) ($ordem->documento ?: $cliente?->cpf_cnpj ?: ''));
        $telefone = trim((string) (
            $ordem->fone1
            ?: $ordem->fone2
            ?: ($cliente?->fone1 ?? '')
            ?: ($cliente?->celular1 ?? '')
            ?: ($cliente?->fone2 ?? '')
        ));
        $email = trim((string) ($cliente?->email ?: $cliente?->email2 ?: ''));

        return [
            'documento' => $documento,
            'telefone' => $telefone,
            'email' => $email,
            'endereco' => self::formatClienteEndereco($ordem),
        ];
    }

    private static function formatClienteEndereco(OrdemServico $ordem): string
    {
        $cliente = $ordem->cliente;
        $logradouro = trim((string) ($ordem->endereco ?: $cliente?->endereco ?: ''));
        $numero = trim((string) ($cliente?->numero ?? ''));
        $complemento = trim((string) ($cliente?->complemento ?? ''));
        $bairro = trim((string) ($ordem->bairro ?: $cliente?->bairro ?: ''));
        $cidade = trim((string) ($ordem->cidade ?: $cliente?->cidade_nome ?: ''));
        $uf = trim((string) ($ordem->uf ?: $cliente?->uf ?: ''));
        $cep = trim((string) ($cliente?->cep ?? ''));

        $linha = trim(implode(', ', array_values(array_filter([
            $logradouro,
            $numero !== '' ? 'Nº '.$numero : '',
            $complemento,
        ], static fn (string $v): bool => $v !== ''))));

        $cidadeUf = trim(implode('/', array_filter([
            $cidade,
            $uf,
        ], static fn (string $v): bool => $v !== '')));

        $partes = array_values(array_filter([
            $linha,
            $bairro,
            $cidadeUf,
            $cep !== '' ? 'CEP '.$cep : '',
        ], static fn (string $v): bool => $v !== ''));

        return $partes !== [] ? mb_strtoupper(implode(' — ', $partes), 'UTF-8') : '';
    }

    private static function formatEmpresaEndereco(?Empresa $empresa): string
    {
        if (! $empresa instanceof Empresa) {
            return '';
        }

        $partes = array_filter([
            filled($empresa->endereco) ? trim((string) $empresa->endereco) : null,
            filled($empresa->numero) ? trim((string) $empresa->numero) : null,
            filled($empresa->bairro) ? trim((string) $empresa->bairro) : null,
            trim(implode('/', array_filter([
                trim((string) ($empresa->cidade ?? '')),
                trim((string) ($empresa->uf ?? '')),
            ]))),
            filled($empresa->cep) ? 'CEP '.trim((string) $empresa->cep) : null,
        ]);

        return $partes !== [] ? mb_strtoupper(implode(', ', $partes), 'UTF-8') : '';
    }

    private static function logoDataUri(?Empresa $empresa): ?string
    {
        if (! $empresa instanceof Empresa || blank($empresa->logo_path)) {
            return null;
        }
        if (! Storage::disk('public')->exists($empresa->logo_path)) {
            return null;
        }
        $contents = Storage::disk('public')->get($empresa->logo_path);
        $mime = Storage::disk('public')->mimeType($empresa->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private static function fileDataUri(string $path, string $mime): ?string
    {
        $path = trim($path);
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $contents = Storage::disk('public')->get($path);

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private static function formatNumero(?string $numero): string
    {
        if (blank($numero)) {
            return '—';
        }
        $digits = (int) preg_replace('/\D/', '', (string) $numero);

        return $digits > 0 ? (string) $digits : (string) $numero;
    }

    private static function dataHora(mixed $data, mixed $hora): string
    {
        $d = '';
        if ($data instanceof \DateTimeInterface) {
            $d = $data->format('d/m/Y');
        } elseif (filled($data)) {
            try {
                $d = \Carbon\Carbon::parse((string) $data)->format('d/m/Y');
            } catch (\Throwable) {
                $d = trim((string) $data);
            }
        }
        $h = trim(substr((string) ($hora ?? ''), 0, 5));
        if ($d === '' && $h === '') {
            return '';
        }
        if ($d === '') {
            return $h;
        }
        if ($h === '') {
            return $d;
        }

        return $d.' '.$h;
    }

    private static function money(mixed $valor): string
    {
        $n = is_numeric($valor) ? (float) $valor : 0.0;

        return 'R$ '.number_format($n, 2, ',', '.');
    }

    private static function qtd(mixed $valor): string
    {
        $n = is_numeric($valor) ? (float) $valor : 0.0;
        if (abs($n - round($n)) < 0.0001) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
    }
}
