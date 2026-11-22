<?php

namespace App\Support\Fiscal;

use App\Models\ClienteCreditoMovimentacao;
use App\Models\FormaPagamento;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\PdvVendaNfce;
use App\Models\PdvVendaPagamento;
use App\Models\Product;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conferência ao Recuperar (F4): produtos e meios de pagamento vs cadastro atual.
 *
 * Atualiza somente metadados fiscais seguros (ex.: descrição do item) em documentos
 * editáveis. Nunca altera quantidades, preços, totais, descontos, acréscimos, pagamentos
 * (valor/forma), estoque, financeiro nem XML autorizado/cancelado/contingência emitida.
 */
class NfceRecuperarConferenciaService
{
    /**
     * Status em que metadados seguros (ex.: descrição) podem ser gravados antes do próximo XML.
     * Duplicidade fica só em leitura: exige consulta SEFAZ e nunca altera documento já autorizado.
     */
    private const STATUS_EDITAVEIS = [
        PdvVendaNfce::STATUS_PENDENTE,
        PdvVendaNfce::STATUS_REJEITADA,
    ];

    /**
     * Conferência + atualizações permitidas na NFC-e já consultada (status real).
     */
    public function conferirEAtualizar(PdvVendaNfce $nfce): NfceRecuperarConferenciaResultado
    {
        return $this->conferir($nfce, aplicar: true);
    }

    /**
     * @param  bool  $aplicar  false = só analisa (testes / pré-visualização), sem gravar.
     */
    public function conferir(PdvVendaNfce $nfce, bool $aplicar = true): NfceRecuperarConferenciaResultado
    {
        $status = (string) $nfce->status;

        if ((bool) $nfce->simulada || $status === PdvVendaNfce::STATUS_SIMULADA) {
            return NfceRecuperarConferenciaResultado::pulada($status, 'NFC-e simulada: conferência fiscal não se aplica.');
        }

        if (in_array($status, [
            PdvVendaNfce::STATUS_AUTORIZADA,
            PdvVendaNfce::STATUS_CANCELADA,
            PdvVendaNfce::STATUS_DENEGADA,
            PdvVendaNfce::STATUS_INUTILIZADA,
        ], true)) {
            return NfceRecuperarConferenciaResultado::pulada(
                $status,
                'Documento fiscal definitivo ('.$status.'): XML e snapshots preservados; nenhuma atualização.',
            );
        }

        if ($status === PdvVendaNfce::STATUS_CONTINGENCIA) {
            // Contingência efetivamente emitida: preserva XML original; só informa.
            return $this->conferirSomenteLeitura(
                $nfce,
                'Contingência emitida: XML original preservado. Regularize pela transmissão (F5); divergências apenas informativas.',
            );
        }

        if ($status === PdvVendaNfce::STATUS_DUPLICIDADE) {
            // Após F4 a SEFAZ já foi consultada; se a chave conflitante estiver autorizada,
            // tentarResolverDuplicidade promove o registro — aqui não se altera nada definitivo.
            return $this->conferirSomenteLeitura(
                $nfce,
                'Duplicidade: consulta SEFAZ obrigatória. Documento autorizado (se houver) não é alterado. '
                .'Corrija a numeração com F5 somente após confirmar que não há autorização conflitante.',
            );
        }

        if (! in_array($status, self::STATUS_EDITAVEIS, true)) {
            return NfceRecuperarConferenciaResultado::pulada(
                $status,
                'Status "'.$status.'" não permite correção automática na recuperação.',
            );
        }

        $nfce->loadMissing([
            'pdvVenda.itens',
            'pdvVenda.pagamentos',
        ]);

        $venda = $nfce->pdvVenda;
        if (! $venda instanceof PdvVenda) {
            return NfceRecuperarConferenciaResultado::pulada($status, 'NFC-e sem venda vinculada.');
        }

        $produtos = $this->carregarProdutos($venda->itens);
        $formas = $this->carregarFormasPagamento($venda->pagamentos);
        $xmlItens = $this->extrairItensDoXml((string) ($nfce->xml ?? ''));
        $xmlPagtos = $this->extrairPagamentosDoXml((string) ($nfce->xml ?? ''));

        $diffs = [];
        $updatesItens = [];

        foreach ($venda->itens->values() as $idx => $item) {
            if (! $item instanceof PdvVendaItem) {
                continue;
            }
            $nItem = $idx + 1;
            $product = $produtos->get((int) ($item->product_id ?? 0));
            $xmlItem = $xmlItens[$idx] ?? null;

            $diffs = [
                ...$diffs,
                ...$this->diffProduto($item, $product, $xmlItem, $nItem, $updatesItens),
            ];
        }

        foreach ($venda->pagamentos->values() as $pIdx => $pag) {
            if (! $pag instanceof PdvVendaPagamento) {
                continue;
            }
            $xmlPag = $xmlPagtos[$pIdx] ?? null;
            $diffs = [
                ...$diffs,
                ...$this->diffPagamento($pag, $formas, $xmlPag),
            ];
        }

        $aplicadas = 0;
        if ($aplicar && $updatesItens !== []) {
            DB::transaction(function () use ($updatesItens, &$aplicadas): void {
                foreach ($updatesItens as $itemId => $campos) {
                    // Somente descrição — nunca quantidade/preço/total/desconto/acréscimo.
                    $safe = array_intersect_key($campos, array_flip(['descricao']));
                    if ($safe === []) {
                        continue;
                    }
                    PdvVendaItem::query()->whereKey($itemId)->update($safe);
                    $aplicadas++;
                }
            });
        } elseif (! $aplicar) {
            $aplicadas = count($updatesItens);
            foreach ($diffs as &$diff) {
                if (($diff['acao'] ?? '') === 'aplicado') {
                    $diff['acao'] = 'aplicavel';
                }
            }
            unset($diff);
        }

        $pendentes = count(array_filter(
            $diffs,
            static fn (array $d): bool => ($d['acao'] ?? '') === 'pendente',
        ));

        return new NfceRecuperarConferenciaResultado(
            executada: true,
            aplicouAtualizacoes: $aplicar && $aplicadas > 0,
            status: $status,
            motivoPula: '',
            diferencas: $diffs,
            atualizacoesAplicadas: $aplicar ? $aplicadas : 0,
            divergenciasPendentes: $pendentes,
        );
    }

    private function conferirSomenteLeitura(PdvVendaNfce $nfce, string $motivo): NfceRecuperarConferenciaResultado
    {
        $nfce->loadMissing(['pdvVenda.itens', 'pdvVenda.pagamentos']);
        $venda = $nfce->pdvVenda;
        if (! $venda instanceof PdvVenda) {
            return NfceRecuperarConferenciaResultado::pulada((string) $nfce->status, $motivo);
        }

        $produtos = $this->carregarProdutos($venda->itens);
        $formas = $this->carregarFormasPagamento($venda->pagamentos);
        $xmlItens = $this->extrairItensDoXml((string) ($nfce->xml ?? ''));
        $xmlPagtos = $this->extrairPagamentosDoXml((string) ($nfce->xml ?? ''));

        $diffs = [];
        $dummy = [];
        foreach ($venda->itens->values() as $idx => $item) {
            if (! $item instanceof PdvVendaItem) {
                continue;
            }
            foreach ($this->diffProduto($item, $produtos->get((int) ($item->product_id ?? 0)), $xmlItens[$idx] ?? null, $idx + 1, $dummy) as $d) {
                $d['acao'] = $d['acao'] === 'aplicado' ? 'info' : $d['acao'];
                $d['mensagem'] = '[somente leitura] '.$d['mensagem'];
                $diffs[] = $d;
            }
        }
        foreach ($venda->pagamentos->values() as $pIdx => $pag) {
            if (! $pag instanceof PdvVendaPagamento) {
                continue;
            }
            foreach ($this->diffPagamento($pag, $formas, $xmlPagtos[$pIdx] ?? null) as $d) {
                if ($d['acao'] === 'aplicado') {
                    $d['acao'] = 'info';
                }
                $d['mensagem'] = '[somente leitura] '.$d['mensagem'];
                $diffs[] = $d;
            }
        }

        return new NfceRecuperarConferenciaResultado(
            executada: true,
            aplicouAtualizacoes: false,
            status: (string) $nfce->status,
            motivoPula: $motivo,
            diferencas: $diffs,
            atualizacoesAplicadas: 0,
            divergenciasPendentes: count(array_filter($diffs, static fn (array $d): bool => ($d['acao'] ?? '') === 'pendente')),
        );
    }

    /**
     * @param  Collection<int, PdvVendaItem>  $itens
     * @return Collection<int, Product>
     */
    private function carregarProdutos(Collection $itens): Collection
    {
        /** @var Collection<int, Product> $loaded */
        $loaded = collect();
        $faltando = [];

        foreach ($itens as $item) {
            $id = (int) ($item->product_id ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($item->relationLoaded('product') && $item->product instanceof Product) {
                $loaded->put($id, $item->product);

                continue;
            }
            $faltando[] = $id;
        }

        $faltando = array_values(array_diff(array_unique($faltando), $loaded->keys()->all()));
        if ($faltando === [] || ! Schema::hasTable('products')) {
            return $loaded;
        }

        $fromDb = Product::query()
            ->whereIn('id', $faltando)
            ->get([
                'id', 'codigo', 'descricao', 'ncm', 'cest', 'cfop_interno', 'origem',
                'csosn', 'cst_icms', 'cst_saida', 'cst_cofins', 'aliq_pis', 'aliq_cofins',
                'iva_cst', 'cclass_trib', 'aliq_ibs_uf', 'aliq_ibs_mun', 'aliq_cbs',
                'reducao_ibs', 'reducao_cbs',
            ])
            ->keyBy('id');

        return $loaded->union($fromDb);
    }

    /**
     * @param  Collection<int, PdvVendaPagamento>  $pagamentos
     * @return Collection<string, FormaPagamento>
     */
    protected function carregarFormasPagamento(Collection $pagamentos): Collection
    {
        $nomes = $pagamentos
            ->map(fn (PdvVendaPagamento $p): string => mb_strtoupper(trim((string) $p->forma), 'UTF-8'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($nomes === [] || ! Schema::hasTable('formas_pagamento')) {
            return collect();
        }

        return FormaPagamento::query()
            ->where(function ($q) use ($nomes): void {
                foreach ($nomes as $nome) {
                    $q->orWhereRaw('UPPER(TRIM(descricao)) = ?', [$nome]);
                }
            })
            ->get([
                'id', 'codigo', 'descricao', 'tipo', 'tipo_movimento',
                'nfce', 'usa_tef', 'usa_pos', 'usa_super_tef', 'ativo',
            ])
            ->keyBy(fn (FormaPagamento $f): string => mb_strtoupper(trim((string) $f->descricao), 'UTF-8'));
    }

    /**
     * @param  array<int, array{descricao: string}>  $updatesItens
     * @param  array{xProd?: string, NCM?: string, CEST?: string, CFOP?: string, CSOSN?: string, CST?: string, orig?: string}|null  $xmlItem
     * @return list<array{escopo: string, campo: string, item?: int|null, pagamento_id?: int|null, antes: string, depois: string, acao: string, mensagem: string}>
     */
    private function diffProduto(
        PdvVendaItem $item,
        ?Product $product,
        ?array $xmlItem,
        int $nItem,
        array &$updatesItens,
    ): array {
        $diffs = [];

        if ($product === null) {
            $diffs[] = [
                'escopo' => 'produto',
                'campo' => 'product_id',
                'item' => $nItem,
                'antes' => (string) ($item->product_id ?? ''),
                'depois' => '',
                'acao' => 'pendente',
                'mensagem' => 'Item '.$nItem.': produto #'.(int) ($item->product_id ?? 0).' não encontrado no cadastro.',
            ];

            return $diffs;
        }

        $descItem = trim((string) $item->descricao);
        $descCad = trim((string) $product->descricao);
        if ($descCad !== '' && $descItem !== $descCad) {
            $updatesItens[(int) $item->id]['descricao'] = $descCad;
            $diffs[] = [
                'escopo' => 'produto',
                'campo' => 'descricao',
                'item' => $nItem,
                'antes' => $descItem,
                'depois' => $descCad,
                'acao' => 'aplicado',
                'mensagem' => 'Item '.$nItem.' descrição: "'.$descItem.'" → "'.$descCad.'"',
            ];
        }

        $cadastroFiscal = [
            'NCM' => preg_replace('/\D/', '', (string) ($product->ncm ?? '')) ?: '',
            'CEST' => preg_replace('/\D/', '', (string) ($product->cest ?? '')) ?: '',
            'CFOP' => preg_replace('/\D/', '', (string) ($product->cfop_interno ?? '')) ?: '',
            'CSOSN' => preg_replace('/\D/', '', (string) ($product->csosn ?? '')) ?: '',
            'CST' => preg_replace('/\D/', '', (string) ($product->cst_icms ?? '')) ?: '',
            'orig' => (string) (int) ($product->origem ?? 0),
        ];

        if ($xmlItem !== null) {
            foreach (['NCM', 'CEST', 'CFOP', 'CSOSN', 'CST', 'orig'] as $campo) {
                $antes = preg_replace('/\D/', '', (string) ($xmlItem[$campo] ?? '')) ?: (string) ($xmlItem[$campo] ?? '');
                $depois = $cadastroFiscal[$campo];
                if ($campo === 'orig') {
                    $antes = (string) (int) ($xmlItem['orig'] ?? 0);
                }
                if ($antes === '' && $depois === '') {
                    continue;
                }
                // CST e CSOSN: XML costuma ter só um; compare o que existir.
                if ($campo === 'CSOSN' && $antes === '' && ($xmlItem['CST'] ?? '') !== '') {
                    continue;
                }
                if ($campo === 'CST' && $antes === '' && ($xmlItem['CSOSN'] ?? '') !== '') {
                    continue;
                }
                if ($antes === $depois) {
                    continue;
                }

                $exigeDecisao = in_array($campo, ['CSOSN', 'CST', 'orig'], true)
                    && $this->mudancaTributariaPodeAlterarValor($antes, $depois, $campo);

                $diffs[] = [
                    'escopo' => 'produto',
                    'campo' => $campo,
                    'item' => $nItem,
                    'antes' => $antes,
                    'depois' => $depois,
                    'acao' => $exigeDecisao ? 'pendente' : 'info',
                    'mensagem' => $exigeDecisao
                        ? 'Item '.$nItem.' '.$campo.': '.$antes.' → '.$depois
                            .' — EXIGE INTERVENÇÃO: pode alterar cálculo de imposto; ajuste o cadastro do produto antes do F5 (preços/qtd intactos)'
                        : 'Item '.$nItem.' '.$campo.': '.$antes.' → '.$depois
                            .' — no F5 Transmitir o XML usará o cadastro atual (sem alterar preços, qtd, descontos ou pagamentos)',
                ];
            }

            $xProd = trim((string) ($xmlItem['xProd'] ?? ''));
            $descAlvo = $descCad !== '' ? $descCad : $descItem;
            if ($xProd !== '' && $descAlvo !== '' && $xProd !== $descAlvo) {
                $diffs[] = [
                    'escopo' => 'produto',
                    'campo' => 'xProd',
                    'item' => $nItem,
                    'antes' => $xProd,
                    'depois' => $descAlvo,
                    'acao' => 'info',
                    'mensagem' => 'Item '.$nItem.' xProd no XML difere do cadastro (novo XML usará a descrição atualizada).',
                ];
            }
        } else {
            // Sem XML para comparar: ainda assim sinaliza campos fiscais do cadastro que entram no próximo XML.
            foreach (['NCM', 'CEST', 'CFOP'] as $campo) {
                if ($cadastroFiscal[$campo] === '') {
                    $diffs[] = [
                        'escopo' => 'produto',
                        'campo' => $campo,
                        'item' => $nItem,
                        'antes' => '',
                        'depois' => '',
                        'acao' => 'pendente',
                        'mensagem' => 'Item '.$nItem.': cadastro sem '.$campo.' — confira antes de transmitir.',
                    ];
                }
            }
        }

        return $diffs;
    }

    private function mudancaTributariaPodeAlterarValor(string $antes, string $depois, string $campo): bool
    {
        if ($antes === $depois) {
            return false;
        }

        // Trocas entre CSOSN de isenção típica (102/103/300/400) tendem a manter vICMS=0 na NFC-e Simples.
        $isentos = ['102', '103', '300', '400', '500'];
        if ($campo === 'CSOSN' && in_array($antes, $isentos, true) && in_array($depois, $isentos, true)) {
            return false;
        }

        // Qualquer mudança de CST ICMS ou origem / CSOSN tributável → decisão do usuário.
        return true;
    }

    /**
     * @param  Collection<string, FormaPagamento>  $formas
     * @param  array{tPag?: string, vPag?: string, tBand?: string, tpIntegra?: string, CNPJ?: string, cAut?: string}|null  $xmlPag
     * @return list<array{escopo: string, campo: string, item?: int|null, pagamento_id?: int|null, antes: string, depois: string, acao: string, mensagem: string}>
     */
    private function diffPagamento(PdvVendaPagamento $pag, Collection $formas, ?array $xmlPag): array
    {
        $diffs = [];
        $formaTxt = trim((string) $pag->forma);
        $chave = mb_strtoupper($formaTxt, 'UTF-8');
        $cadastro = $formas->get($chave);

        $creditoCliente = ClienteCreditoMovimentacao::isFormaPdv($formaTxt)
            || ($cadastro !== null && (string) $cadastro->tipo_movimento === 'credito_cliente');

        $tPagDescricao = FormaPagamentoNfceTPagMap::fromDescricao($formaTxt, $creditoCliente);
        $tPagTipo = $cadastro !== null
            ? FormaPagamentoNfceTPagMap::fromTipo((string) $cadastro->tipo)
            : null;
        $tPagXml = isset($xmlPag['tPag']) ? str_pad(preg_replace('/\D/', '', (string) $xmlPag['tPag']) ?: '', 2, '0', STR_PAD_LEFT) : null;

        if ($cadastro === null) {
            $diffs[] = [
                'escopo' => 'pagamento',
                'campo' => 'forma',
                'pagamento_id' => (int) $pag->id,
                'antes' => $formaTxt,
                'depois' => '',
                'acao' => 'pendente',
                'mensagem' => 'Pagamento "'.$formaTxt.'": não encontrado no cadastro de formas (tPag atual '.$tPagDescricao.').',
            ];
        } else {
            if (! (bool) $cadastro->ativo) {
                $diffs[] = [
                    'escopo' => 'pagamento',
                    'campo' => 'ativo',
                    'pagamento_id' => (int) $pag->id,
                    'antes' => 'ativo',
                    'depois' => 'inativo',
                    'acao' => 'pendente',
                    'mensagem' => 'Pagamento "'.$formaTxt.'": forma inativa no cadastro.',
                ];
            }

            if ($cadastro->nfce === false) {
                $diffs[] = [
                    'escopo' => 'pagamento',
                    'campo' => 'nfce',
                    'pagamento_id' => (int) $pag->id,
                    'antes' => 'nfce=sim',
                    'depois' => 'nfce=não',
                    'acao' => 'pendente',
                    'mensagem' => 'Pagamento "'.$formaTxt.'": desmarcado para NFC-e no cadastro.',
                ];
            }

            if ($tPagTipo !== null && $tPagTipo !== $tPagDescricao) {
                // Tipo do cadastro diverge do texto gravado na venda — não troca a forma automaticamente.
                $diffs[] = [
                    'escopo' => 'pagamento',
                    'campo' => 'tPag',
                    'pagamento_id' => (int) $pag->id,
                    'antes' => $tPagDescricao.' (pela descrição da venda)',
                    'depois' => $tPagTipo.' (pelo Tipo "'.$cadastro->tipo.'" do cadastro)',
                    'acao' => 'pendente',
                    'mensagem' => 'Pagamento "'.$formaTxt.'": tPag descrição ('.$tPagDescricao.') ≠ Tipo cadastro ('.$tPagTipo.')'
                        .' — EXIGE INTERVENÇÃO: alinhe o Tipo da forma no cadastro ou a descrição da parcela na venda antes do F5'
                        .' (valor R$ '.number_format((float) $pag->valor, 2, ',', '.').' e forma original preservados).',
                ];
            }
        }

        if ($tPagXml !== null && $tPagXml !== '' && $tPagXml !== $tPagDescricao) {
            $diffs[] = [
                'escopo' => 'pagamento',
                'campo' => 'tPag_xml',
                'pagamento_id' => (int) $pag->id,
                'antes' => $tPagXml,
                'depois' => $tPagDescricao,
                'acao' => 'info',
                'mensagem' => 'Pagamento "'.$formaTxt.'": tPag no XML ('.$tPagXml.') → próximo XML usaria '.$tPagDescricao.' (valor R$ '.number_format((float) $pag->valor, 2, ',', '.').' preservado).',
            ];
        }

        // Cartão / eletrônico: credenciadora e integração.
        $tPagRef = $tPagTipo ?? $tPagDescricao;
        if (FormaPagamentoNfceTPagMap::isCartao($tPagRef)) {
            if ($pag->tBandFiscal() === null && blank($pag->cartao_bandeira)) {
                $diffs[] = [
                    'escopo' => 'pagamento',
                    'campo' => 'tBand',
                    'pagamento_id' => (int) $pag->id,
                    'antes' => '',
                    'depois' => '',
                    'acao' => 'pendente',
                    'mensagem' => 'Pagamento "'.$formaTxt.'": cartão sem bandeira (tBand) — exigido para pagamento eletrônico.',
                ];
            }

            // CNPJ credenciadora: builder atual usa tpIntegra=2 sem CNPJ (POS). Só alerta se XML tinha CNPJ e vamos perder.
            $cnpjXml = preg_replace('/\D/', '', (string) ($xmlPag['CNPJ'] ?? '')) ?: '';
            if ($cnpjXml !== '' && strlen($cnpjXml) === 14) {
                $diffs[] = [
                    'escopo' => 'pagamento',
                    'campo' => 'cnpjCredenciadora',
                    'pagamento_id' => (int) $pag->id,
                    'antes' => $cnpjXml,
                    'depois' => '',
                    'acao' => 'info',
                    'mensagem' => 'Pagamento "'.$formaTxt.'": XML possui CNPJ credenciadora; remontagem atual usa tpIntegra=2 sem CNPJ (padrão POS/SuperTEF).',
                ];
            }
        }

        // Garantia: valor do pagamento nunca entra em update — só auditoria no resultado.
        if ($xmlPag !== null && isset($xmlPag['vPag'])) {
            $vXml = round((float) str_replace(',', '.', (string) $xmlPag['vPag']), 2);
            $vPag = round((float) $pag->valor, 2);
            if (abs($vXml - $vPag) > 0.009) {
                $diffs[] = [
                    'escopo' => 'pagamento',
                    'campo' => 'vPag',
                    'pagamento_id' => (int) $pag->id,
                    'antes' => number_format($vXml, 2, '.', ''),
                    'depois' => number_format($vPag, 2, '.', ''),
                    'acao' => 'pendente',
                    'mensagem' => 'Pagamento "'.$formaTxt.'": vPag XML ('.$vXml.') ≠ valor da venda ('.$vPag.') — valores comerciais não serão alterados automaticamente.',
                ];
            }
        }

        return $diffs;
    }

    /**
     * @return list<array{xProd: string, NCM: string, CEST: string, CFOP: string, CSOSN: string, CST: string, orig: string}>
     */
    private function extrairItensDoXml(string $xml): array
    {
        $nfe = NfceXmlProtocolo::nfe($xml);
        if ($nfe === null) {
            return [];
        }

        $dom = new DOMDocument();
        if (! @$dom->loadXML($nfe)) {
            return [];
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('n', 'http://www.portalfiscal.inf.br/nfe');
        $itens = [];
        foreach ($xp->query('//n:infNFe/n:det') ?: [] as $det) {
            if (! $det instanceof DOMElement) {
                continue;
            }
            $itens[] = [
                'xProd' => $this->xpathText($xp, 'n:prod/n:xProd', $det),
                'NCM' => $this->xpathText($xp, 'n:prod/n:NCM', $det),
                'CEST' => $this->xpathText($xp, 'n:prod/n:CEST', $det),
                'CFOP' => $this->xpathText($xp, 'n:prod/n:CFOP', $det),
                'CSOSN' => $this->xpathText($xp, 'n:imposto/n:ICMS//n:CSOSN', $det),
                'CST' => $this->xpathText($xp, 'n:imposto/n:ICMS//n:CST', $det),
                'orig' => $this->xpathText($xp, 'n:imposto/n:ICMS//n:orig', $det),
            ];
        }

        return $itens;
    }

    /**
     * @return list<array{tPag: string, vPag: string, tBand: string, tpIntegra: string, CNPJ: string, cAut: string}>
     */
    private function extrairPagamentosDoXml(string $xml): array
    {
        $nfe = NfceXmlProtocolo::nfe($xml);
        if ($nfe === null) {
            return [];
        }

        $dom = new DOMDocument();
        if (! @$dom->loadXML($nfe)) {
            return [];
        }

        $xp = new DOMXPath($dom);
        $xp->registerNamespace('n', 'http://www.portalfiscal.inf.br/nfe');
        $pagamentos = [];
        foreach ($xp->query('//n:infNFe/n:pag/n:detPag') ?: [] as $det) {
            if (! $det instanceof DOMElement) {
                continue;
            }
            $pagamentos[] = [
                'tPag' => $this->xpathText($xp, 'n:tPag', $det),
                'vPag' => $this->xpathText($xp, 'n:vPag', $det),
                'tBand' => $this->xpathText($xp, 'n:card/n:tBand', $det),
                'tpIntegra' => $this->xpathText($xp, 'n:card/n:tpIntegra', $det),
                'CNPJ' => $this->xpathText($xp, 'n:card/n:CNPJ', $det),
                'cAut' => $this->xpathText($xp, 'n:card/n:cAut', $det),
            ];
        }

        return $pagamentos;
    }

    private function xpathText(DOMXPath $xp, string $query, DOMElement $ctx): string
    {
        $node = $xp->query($query, $ctx)?->item(0);

        return $node !== null ? trim((string) $node->textContent) : '';
    }
}
