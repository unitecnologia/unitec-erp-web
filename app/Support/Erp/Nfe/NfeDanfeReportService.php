<?php

namespace App\Support\Erp\Nfe;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeItem;
use App\Models\Person;
use App\Support\Erp\Compra\CompraDanfeReportService;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'transportador' => [
                'nome' => '', 'cnpj' => '', 'ie' => '', 'endereco' => '', 'municipio' => '', 'uf' => '',
                'placa' => '', 'placa_uf' => '', 'antt' => '', 'mod_frete' => '', 'mod_frete_label' => '',
            ],
            'volumes' => [
                'quantidade' => '', 'especie' => '', 'marca' => '', 'numeracao' => '',
                'peso_bruto' => '', 'peso_liquido' => '',
            ],
            'duplicatas' => [],
            'fatura' => ['numero' => '', 'valor_original' => '', 'valor_desconto' => '', 'valor_liquido' => ''],
            'informacoesComplementares' => $this->buildInformacoesComplementares($nfe, $empresa),
            'informacoesFisco' => filled($nfe->obs_fisco) ? trim((string) $nfe->obs_fisco) : '',
            'printedAt' => now(),
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
            'Segue em anexo a DANFE da NF-e emitida.',
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
        $zero = '0,00';

        return [
            'base_icms' => number_format((float) ($nfe->base_icms ?: $subtotalProdutos), 2, ',', '.'),
            'valor_icms' => number_format((float) ($nfe->total_icms ?? 0), 2, ',', '.'),
            'base_icms_st' => number_format((float) ($nfe->base_icms_st ?? 0), 2, ',', '.'),
            'valor_icms_st' => number_format((float) ($nfe->valor_icms_st ?? 0), 2, ',', '.'),
            'total_produtos' => number_format($subtotalProdutos, 2, ',', '.'),
            'frete' => number_format((float) ($nfe->frete ?? 0), 2, ',', '.'),
            'seguro' => number_format((float) ($nfe->seguro ?? 0), 2, ',', '.'),
            'desconto' => number_format((float) ($nfe->desconto ?? 0), 2, ',', '.'),
            'outras' => number_format((float) ($nfe->outros ?? 0), 2, ',', '.'),
            'total_ipi' => number_format((float) ($nfe->total_ipi ?? 0), 2, ',', '.'),
            'total_pis' => number_format((float) ($nfe->total_pis ?? 0), 2, ',', '.'),
            'total_cofins' => number_format((float) ($nfe->total_cofins ?? 0), 2, ',', '.'),
            'total_nota' => number_format($totalNota, 2, ',', '.'),
        ];
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
            'DOCUMENTO AUXILIAR GERADO PELO UNITECH ERP WEB.',
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
