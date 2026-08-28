<?php

namespace App\Support\Erp\Nfe;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeFatura;
use App\Models\NfeItem;
use App\Models\Person;
use App\Models\Transportadora;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Compra\CompraDanfeReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class NfeDanfeReportService
{
    public function __construct(
        private readonly CompraDanfeReportService $danfe = new CompraDanfeReportService(),
    ) {}

    public function loadNfe(Nfe $nfe): Nfe
    {
        return $nfe->load([
            'cliente',
            'empresa',
            'transportadora',
            'faturas' => fn ($query) => $query->orderBy('numero'),
            'itens' => fn ($query) => $query->orderBy('item')->with('product'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(Nfe $nfe, ?Empresa $empresa = null): array
    {
        $nfe = $this->loadNfe($nfe);
        $empresa ??= $nfe->empresa ?? $this->danfe->resolveEmpresa($nfe->empresa_id);
        $cliente = $nfe->cliente;
        $keyParts = $this->danfe->extractNfeKeyParts($nfe->chave);

        $subtotalProdutos = (float) ($nfe->subtotal > 0 ? $nfe->subtotal : $nfe->itens->sum('total'));
        $totalNota = (float) ($nfe->total > 0 ? $nfe->total : $subtotalProdutos);
        $movimento = (string) ($nfe->movimento ?? '1');
        $isSaida = $movimento !== '0';
        $logoDataUri = $this->danfe->logoDataUri($empresa);

        return [
            'nfe' => $nfe,
            'empresa' => $empresa,
            'cliente' => $cliente,
            'emitente' => $this->buildEmpresaBlock($empresa),
            'destinatario' => $this->buildPersonBlock($cliente),
            'chave' => $this->onlyDigits($nfe->chave),
            'chaveFormatada' => $this->danfe->formatChave($nfe->chave),
            'barcodeDataUri' => $this->danfe->barcodeDataUri($nfe->chave),
            'numeroNota' => $this->danfe->formatNumeroNota($nfe->numero),
            'serie' => str_pad(ltrim((string) ($nfe->serie ?: $keyParts['serie']), '0') ?: '0', 3, '0', STR_PAD_LEFT),
            'modelo' => str_pad((string) ($nfe->modelo ?: $keyParts['modelo'] ?: '55'), 2, '0', STR_PAD_LEFT),
            'tipoOperacao' => $isSaida ? '1' : '0',
            'tipoOperacaoLabel' => $isSaida ? 'SAÍDA' : 'ENTRADA',
            'naturezaOperacao' => mb_strtoupper($this->resolveNaturezaOperacao($nfe), 'UTF-8'),
            'protocolo' => trim((string) ($nfe->protocolo ?? '')),
            'dataEmissao' => $nfe->data_emissao?->format('d/m/Y') ?? '',
            'dataEntrada' => $nfe->data_saida?->format('d/m/Y') ?? ($nfe->data_emissao?->format('d/m/Y') ?? ''),
            'horaEntrada' => filled($nfe->hora_saida) ? substr((string) $nfe->hora_saida, 0, 5) : '',
            'itens' => $this->buildItens($nfe),
            'totais' => $this->buildTotais($nfe, $subtotalProdutos, $totalNota),
            'transportador' => $this->buildTransportador($nfe),
            'volumes' => $this->buildVolumes($nfe),
            'duplicatas' => $this->buildDuplicatas($nfe),
            'fatura' => $this->buildFatura($nfe, $totalNota),
            'informacoesComplementares' => $this->buildInformacoesComplementares($nfe, $empresa),
            'informacoesFisco' => filled($nfe->obs_fisco) ? trim((string) $nfe->obs_fisco) : '',
            'logoDataUri' => $logoDataUri,
            'logoUrl' => $logoDataUri === null ? $empresa?->logoUrl() : null,
            'printedAt' => ErpTimezone::toLocal(),
            'printedBy' => trim((string) (Auth::user()?->name ?? '')),
        ];
    }

    /**
     * @return array{path: string, name: string, display: string}
     */
    public function storePdfAttachment(Nfe $nfe, ?Empresa $empresa = null): array
    {
        $data = $this->buildViewData($nfe, $empresa);
        $directory = storage_path('app/temp/nfe');

        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . 'nfe-' . $nfe->id . '-' . uniqid('', true) . '.pdf';
        $numeroDigits = preg_replace('/\D/', '', (string) $nfe->numero) ?: (string) $nfe->id;
        $name = 'DANFE-NFE-' . $numeroDigits . '.PDF';

        Pdf::loadView('reports.nfe-danfe-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->save($path);

        return [
            'path' => $path,
            'name' => $name,
            'display' => $name,
        ];
    }

    /**
     * @return array{path: string, name: string, display: string}|null
     */
    public function storeXmlAttachment(Nfe $nfe): ?array
    {
        $xml = trim((string) ($nfe->xml ?? ''));

        if ($xml === '') {
            return null;
        }

        $chave = preg_replace('/\D/', '', (string) ($nfe->chave ?? '')) ?? '';

        if ($chave === '') {
            return null;
        }

        $directory = storage_path('app/temp/nfe-xml');
        File::ensureDirectoryExists($directory);

        $name = $chave.'.xml';
        $path = $directory.DIRECTORY_SEPARATOR.$name.'-'.uniqid('', true);

        file_put_contents($path, $xml);

        return [
            'path' => $path,
            'name' => $name,
            'display' => $name,
        ];
    }

    /**
     * @return list<array{id: string, name: string, path: string, display: string}>
     */
    public function buildDispatchAttachments(Nfe $nfe, ?Empresa $empresa = null): array
    {
        $pdf = $this->storePdfAttachment($nfe, $empresa);

        $attachments = [[
            'id' => 'danfe',
            'name' => $pdf['name'],
            'path' => $pdf['path'],
            'display' => $pdf['display'],
        ]];

        $xml = $this->storeXmlAttachment($nfe);

        if ($xml) {
            $attachments[] = [
                'id' => 'xml',
                'name' => $xml['name'],
                'path' => $xml['path'],
                'display' => $xml['display'],
            ];
        }

        return $attachments;
    }

    public function defaultWhatsAppMessage(Nfe $nfe): string
    {
        $nfe = $this->loadNfe($nfe);
        $numero = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;
        $chave = $this->danfe->formatChave($nfe->chave);
        $total = number_format((float) $nfe->total, 2, ',', '.');

        $lines = array_filter([
            'Olá! Segue a NF-e emitida:',
            "Nota: {$numero} | Série: {$nfe->serie}",
            filled($nfe->protocolo) ? "Protocolo: {$nfe->protocolo}" : null,
            filled($chave) ? "Chave: {$chave}" : null,
            "Valor total: R$ {$total}",
        ]);

        return implode("\n", $lines);
    }

    public function defaultEmailSubject(Nfe $nfe): string
    {
        $numero = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;

        return 'NF-e nº ' . $numero . ' — DANFE';
    }

    public function defaultEmailMessage(Nfe $nfe, string $destinatarioNome = ''): string
    {
        $nfe = $this->loadNfe($nfe);
        $numero = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;
        $total = number_format((float) $nfe->total, 2, ',', '.');
        $saudacao = filled($destinatarioNome)
            ? 'Olá, ' . $destinatarioNome . '!'
            : 'Olá!';

        $lines = array_filter([
            $saudacao,
            '',
            'Segue em anexo a DANFE e o XML da NF-e emitida.',
            "Nota: {$numero} | Série: {$nfe->serie}",
            filled($nfe->protocolo) ? "Protocolo: {$nfe->protocolo}" : null,
            "Valor total: R$ {$total}",
            '',
            'Atenciosamente.',
        ], static fn (?string $line): bool => $line !== null);

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    protected function buildEmpresaBlock(?Empresa $empresa): array
    {
        if (! $empresa) {
            return $this->emptyPartyBlock();
        }

        return [
            'nome' => mb_strtoupper((string) ($empresa->razao_social ?: $empresa->nome ?: $empresa->fantasia), 'UTF-8'),
            'endereco' => $this->formatEndereco($empresa->endereco, $empresa->numero, $empresa->bairro, $empresa->cep),
            'municipio' => mb_strtoupper((string) ($empresa->cidade ?? ''), 'UTF-8'),
            'uf' => (string) ($empresa->uf ?? ''),
            'telefone' => (string) ($empresa->telefone ?? ''),
            'ie' => (string) ($empresa->ie ?? ''),
            'im' => (string) ($empresa->im ?? ''),
            'cnpj' => $this->danfe->formatCpfCnpj($empresa->cnpj),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function buildPersonBlock(?Person $person): array
    {
        if (! $person) {
            return $this->emptyPartyBlock();
        }

        return [
            'nome' => mb_strtoupper((string) $person->nome_razao, 'UTF-8'),
            'endereco' => $this->formatEndereco($person->endereco, $person->numero, $person->bairro, $person->cep),
            'municipio' => mb_strtoupper((string) ($person->cidade_nome ?? ''), 'UTF-8'),
            'uf' => (string) ($person->uf ?? ''),
            'telefone' => (string) ($person->fone1 ?: $person->celular1 ?: ''),
            'ie' => (string) ($person->rg_ie ?? ''),
            'im' => '',
            'cnpj' => $this->danfe->formatCpfCnpj($person->cpf_cnpj),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function emptyPartyBlock(): array
    {
        return [
            'nome' => '',
            'endereco' => '',
            'municipio' => '',
            'uf' => '',
            'telefone' => '',
            'ie' => '',
            'im' => '',
            'cnpj' => '',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function buildItens(Nfe $nfe): array
    {
        $rows = [];

        foreach ($nfe->itens as $item) {
            $rows[] = $this->buildItemRow($item);
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    protected function buildItemRow(NfeItem $item): array
    {
        $product = $item->product;
        $codigo = $product?->codigo ?? $item->cod_barra ?? '';
        $codigoFormatado = '—';

        if ($codigo !== null && $codigo !== '') {
            $trimmed = ltrim((string) $codigo, '0');
            $codigoFormatado = $trimmed !== '' ? $trimmed : '0';
        }

        $quantidade = (float) $item->quantidade;
        $valorUnitario = (float) $item->valor_unitario;
        $total = (float) ($item->total > 0 ? $item->total : ($quantidade * $valorUnitario));
        $baseIcms = (float) ($item->base_icms > 0 ? $item->base_icms : $total);
        $aliqIcms = (float) ($item->aliq_icms ?? 0);
        $valorIcms = (float) ($item->valor_icms > 0 ? $item->valor_icms : ($aliqIcms > 0 ? round($baseIcms * ($aliqIcms / 100), 2) : 0));

        return [
            'item' => (string) ($item->item ?? ''),
            'codigo' => $codigoFormatado,
            'descricao' => $item->descricao ?: ($product?->descricao ?? '—'),
            'ncm' => (string) ($item->ncm ?? $product?->ncm ?? ''),
            'cst' => (string) ($item->cst ?? $item->csosn ?? $product?->cst_icms ?? ''),
            'cfop' => (string) ($item->cfop ?? ''),
            'un' => (string) ($item->unidade ?: 'UN'),
            'quant' => number_format($quantidade, 4, ',', '.'),
            'valor_unit' => number_format($valorUnitario, 4, ',', '.'),
            'valor_total' => number_format($total, 2, ',', '.'),
            'desconto' => number_format((float) ($item->desconto ?? 0), 2, ',', '.'),
            'base_icms' => number_format($baseIcms, 2, ',', '.'),
            'valor_icms' => number_format($valorIcms, 2, ',', '.'),
            'valor_ipi' => number_format((float) ($item->valor_ipi ?? 0), 2, ',', '.'),
            'aliq_icms' => number_format($aliqIcms, 2, ',', '.'),
            'aliq_ipi' => number_format((float) ($item->aliq_ipi ?? 0), 2, ',', '.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function buildTotais(Nfe $nfe, float $subtotalProdutos, float $totalNota): array
    {
        $desconto = (float) ($nfe->desconto ?? 0);
        $frete = (float) ($nfe->frete ?? 0);
        $seguro = (float) ($nfe->seguro ?? 0);
        $outros = (float) ($nfe->outros ?? 0);
        $baseIcms = (float) ($nfe->base_icms ?: $subtotalProdutos);
        $valorIcms = (float) ($nfe->total_icms ?? 0);
        $baseSt = (float) ($nfe->base_icms_st ?? 0);
        $valorSt = (float) ($nfe->valor_icms_st ?? 0);
        $totalIpi = (float) ($nfe->total_ipi ?? 0);
        $totalPis = (float) ($nfe->total_icms_pis ?? 0);
        $totalCofins = (float) ($nfe->total_icms_cofins ?? 0);

        if ($nfe->itens->isNotEmpty()) {
            if ($frete <= 0) {
                $frete = (float) $nfe->itens->sum(fn (NfeItem $item): float => (float) ($item->frete ?? 0));
            }

            if ($seguro <= 0) {
                $seguro = (float) $nfe->itens->sum(fn (NfeItem $item): float => (float) ($item->seguro ?? 0));
            }

            if ($outros <= 0) {
                $outros = (float) $nfe->itens->sum(fn (NfeItem $item): float => (float) ($item->outros ?? 0));
            }

            if ($valorIcms <= 0) {
                $valorIcms = (float) $nfe->itens->sum(fn (NfeItem $item): float => (float) ($item->valor_icms ?? 0));
            }

            if ($totalIpi <= 0) {
                $totalIpi = (float) $nfe->itens->sum(fn (NfeItem $item): float => (float) ($item->valor_ipi ?? 0));
            }

            if ($totalPis <= 0) {
                $totalPis = (float) $nfe->itens->sum(fn (NfeItem $item): float => (float) ($item->valor_pis_icms ?? 0));
            }

            if ($totalCofins <= 0) {
                $totalCofins = (float) $nfe->itens->sum(fn (NfeItem $item): float => (float) ($item->valor_cofins_icms ?? 0));
            }

            if ($baseSt <= 0) {
                $baseSt = (float) $nfe->itens->sum(fn (NfeItem $item): float => (float) ($item->base_icms_st ?? 0));
            }

            if ($valorSt <= 0) {
                $valorSt = (float) $nfe->itens->sum(fn (NfeItem $item): float => (float) ($item->valor_icms_st ?? 0));
            }
        }

        $totalProdutos = $subtotalProdutos + $desconto;

        return [
            'base_icms' => number_format($baseIcms, 2, ',', '.'),
            'valor_icms' => number_format($valorIcms, 2, ',', '.'),
            'base_icms_st' => number_format($baseSt, 2, ',', '.'),
            'valor_icms_st' => number_format($valorSt, 2, ',', '.'),
            'total_produtos' => number_format($totalProdutos, 2, ',', '.'),
            'frete' => number_format($frete, 2, ',', '.'),
            'seguro' => number_format($seguro, 2, ',', '.'),
            'desconto' => number_format($desconto, 2, ',', '.'),
            'outras' => number_format($outros, 2, ',', '.'),
            'total_ipi' => number_format($totalIpi, 2, ',', '.'),
            'total_pis' => number_format($totalPis, 2, ',', '.'),
            'total_cofins' => number_format($totalCofins, 2, ',', '.'),
            'total_nota' => number_format($totalNota, 2, ',', '.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function buildTransportador(Nfe $nfe): array
    {
        $transportadora = $nfe->transportadora;
        $modFrete = trim((string) ($nfe->tipo_frete ?? '9')) ?: '9';
        if (! in_array($modFrete, ['0', '1', '2', '3', '4', '9'], true)) {
            $modFrete = '9';
        }

        $block = [
            'nome' => '',
            'cnpj' => '',
            'ie' => '',
            'endereco' => '',
            'municipio' => '',
            'uf' => '',
            'placa' => mb_strtoupper(trim((string) ($nfe->placa ?? '')), 'UTF-8'),
            'placa_uf' => mb_strtoupper(trim((string) ($nfe->uf_placa ?? '')), 'UTF-8'),
            'antt' => trim((string) ($nfe->rntc ?? '')),
            'mod_frete' => $modFrete,
            'mod_frete_label' => $this->modFreteLabel($modFrete),
        ];

        if (! $transportadora instanceof Transportadora) {
            return $block;
        }

        $block['nome'] = mb_strtoupper(
            trim((string) ($transportadora->proprietario ?: $transportadora->apelido ?: '')),
            'UTF-8',
        );
        $block['cnpj'] = $this->danfe->formatCpfCnpj($transportadora->cnpj_cpf);
        $block['ie'] = trim((string) ($transportadora->rg_ie ?? ''));
        $block['endereco'] = $this->formatEndereco(
            $transportadora->endereco,
            $transportadora->numero,
            $transportadora->bairro,
            $transportadora->cep,
        );
        $block['municipio'] = mb_strtoupper(trim((string) ($transportadora->cidade ?? '')), 'UTF-8');
        $block['uf'] = mb_strtoupper(trim((string) ($transportadora->uf ?? '')), 'UTF-8');

        return $block;
    }

    /**
     * @return array<string, string>
     */
    protected function buildVolumes(Nfe $nfe): array
    {
        $qvol = (int) ($nfe->qvol ?? 0);
        $pesoB = (float) ($nfe->peso_b ?? 0);
        $pesoL = (float) ($nfe->peso_l ?? 0);
        $especie = mb_strtoupper(trim((string) ($nfe->especie ?? '')), 'UTF-8');
        $marca = mb_strtoupper(trim((string) ($nfe->marca ?? '')), 'UTF-8');
        $numeracao = trim((string) ($nfe->nvol ?? ''));

        return [
            'quantidade' => $qvol > 0 ? (string) $qvol : '',
            'especie' => $especie,
            'marca' => $marca,
            'numeracao' => $numeracao,
            'peso_bruto' => $pesoB > 0 ? number_format($pesoB, 3, ',', '.') : '',
            'peso_liquido' => $pesoL > 0 ? number_format($pesoL, 3, ',', '.') : '',
        ];
    }

    /**
     * @return list<array{numero: string, vencimento: string, valor: string}>
     */
    protected function buildDuplicatas(Nfe $nfe): array
    {
        return $nfe->faturas
            ->map(fn (NfeFatura $fatura): array => [
                'numero' => trim((string) ($fatura->numero ?? '')),
                'vencimento' => $fatura->data_vencimento?->format('d/m/Y') ?? '',
                'valor' => number_format((float) $fatura->valor, 2, ',', '.'),
            ])
            ->filter(static fn (array $dup): bool => filled($dup['numero']) || filled($dup['valor']))
            ->values()
            ->all();
    }

    /**
     * @return array{numero: string, valor_original: string, valor_desconto: string, valor_liquido: string}
     */
    protected function buildFatura(Nfe $nfe, float $totalNota): array
    {
        $empty = [
            'numero' => '',
            'valor_original' => '',
            'valor_desconto' => '',
            'valor_liquido' => '',
        ];

        if ($nfe->faturas->isEmpty()) {
            return $empty;
        }

        $valorOriginal = (float) $nfe->faturas->sum(fn (NfeFatura $fatura): float => (float) $fatura->valor);
        $desconto = (float) ($nfe->desconto ?? 0);
        $numero = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;

        return [
            'numero' => $numero,
            'valor_original' => number_format($valorOriginal, 2, ',', '.'),
            'valor_desconto' => number_format($desconto, 2, ',', '.'),
            'valor_liquido' => number_format($totalNota > 0 ? $totalNota : $valorOriginal, 2, ',', '.'),
        ];
    }

    protected function modFreteLabel(string $modFrete): string
    {
        return match ($modFrete) {
            '0' => '0 - Por conta do Remetente',
            '1' => '1 - Por conta do Destinatário',
            '2' => '2 - Por conta de Terceiros',
            '3' => '3 - Próprio Remetente',
            '4' => '4 - Próprio Destinatário',
            '9' => '9 - Sem Frete',
            default => $modFrete !== '' ? $modFrete : '',
        };
    }

    protected function buildInformacoesComplementares(Nfe $nfe, ?Empresa $empresa): string
    {
        $obs = filled($nfe->obs_contribuinte) ? trim((string) $nfe->obs_contribuinte) : '';

        if ($obs === '' || (! str_contains($obs, 'Lei 12.741') && ! str_contains($obs, 'Trib. aprox.'))) {
            $totais = [
                'trib_fed' => (float) ($nfe->trib_fed ?? 0),
                'trib_est' => (float) ($nfe->trib_est ?? 0),
                'trib_mun' => (float) ($nfe->trib_mun ?? 0),
                'v_tot_trib' => round(
                    (float) ($nfe->trib_fed ?? 0) + (float) ($nfe->trib_est ?? 0) + (float) ($nfe->trib_mun ?? 0),
                    2,
                ),
                'fonte' => 'IBPT',
            ];

            $textoIbpt = app(\App\Support\Erp\Fiscal\IbptLookupService::class)->formatarTextoLei12741($totais);

            if ($textoIbpt !== '') {
                $obs = $obs === '' ? $textoIbpt : rtrim($obs, " .\n").'. '.$textoIbpt;
            }
        }

        $partes = array_filter([
            $obs !== '' ? $obs : null,
            filled($nfe->obs_fisco) ? trim((string) $nfe->obs_fisco) : null,
            filled($nfe->chave) ? 'CHAVE NF-e: ' . $this->onlyDigits($nfe->chave) : null,
            filled($nfe->protocolo) ? 'PROTOCOLO: ' . $nfe->protocolo : null,
            $empresa ? 'EMITENTE: ' . mb_strtoupper((string) ($empresa->razao_social ?: $empresa->nome), 'UTF-8') : null,
        ]);

        return implode("\n", $partes);
    }

    protected function resolveNaturezaOperacao(Nfe $nfe): string
    {
        $cfop = trim((string) ($nfe->cfop ?? ''));

        if ($cfop !== '') {
            return 'VENDA DE MERCADORIA';
        }

        return 'VENDA DE MERCADORIA';
    }

    protected function formatEndereco(?string $endereco, ?string $numero, ?string $bairro, ?string $cep): string
    {
        $partes = array_filter([
            filled($endereco) ? mb_strtoupper(trim($endereco), 'UTF-8') : null,
            filled($numero) ? 'Nº ' . trim((string) $numero) : null,
            filled($bairro) ? mb_strtoupper(trim($bairro), 'UTF-8') : null,
            filled($cep) ? 'CEP ' . $this->formatCep($cep) : null,
        ]);

        return implode(' - ', $partes);
    }

    protected function formatCep(?string $cep): string
    {
        $digits = preg_replace('/\D/', '', (string) $cep) ?? '';

        if (strlen($digits) !== 8) {
            return (string) $cep;
        }

        return substr($digits, 0, 5) . '-' . substr($digits, 5);
    }

    protected function onlyDigits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }
}
