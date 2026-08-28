<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\Product;
use App\Models\VendasParametro;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfce\NfceConsumidorIdentificado;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use Unitec\FiscalEngine\Certificate\CertificateLoader;
use Unitec\FiscalEngine\Dto\DestinatarioDto;
use Unitec\FiscalEngine\Dto\EmitenteDto;
use Unitec\FiscalEngine\Dto\EmitirNfceRequest;
use Unitec\FiscalEngine\Dto\IdeDto;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\ItemImpostoDto;
use Unitec\FiscalEngine\Dto\PagamentoDto;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class PdvNfceFiscalPayloadBuilder
{
    public function build(
        PdvVenda $venda,
        Empresa $empresa,
        VendasParametro $parametros,
        string $operacao,
        int $numeroNfce,
        ?int $cNfFixo = null,
        ?string $justificativaContingencia = null,
        ?\DateTimeInterface $dataContingencia = null,
        ?int $serieNfce = null,
    ): EmitirNfceRequest {
        $this->validarPreRequisitos($empresa, $parametros, $operacao);

        $certPath = NfeFiscalConfig::certificadoAbsolutePath($parametros);
        $senha = $parametros->safeSenhaCertificado();

        if ($certPath === null || $senha === null) {
            throw new FiscalEngineException('Certificado digital ou senha não configurados.');
        }

        $certificate = CertificateLoader::fromPkcs12File($certPath, $senha, (string) $empresa->cnpj);
        $tpAmb = $this->mapTpAmb((int) $parametros->ambiente);
        $tpEmis = $operacao === PdvFinalizarOperacao::NFCE_CONTINGENCIA ? 9 : 1;
        $cNf = $cNfFixo ?? random_int(1, 99999999);
        // O banco conserva o instante em UTC; o XML fiscal exige data/hora local
        // e offset do estabelecimento para dhEmi/chave de acesso.
        $emissao = ErpTimezone::toLocal($venda->fechado_em ?? now());
        $justificativa = $tpEmis === 9
            ? NfceContingenciaJustificativa::normalize($justificativaContingencia)
            : null;
        $dhContingencia = $tpEmis === 9
            ? ErpTimezone::toLocal($dataContingencia ?? $emissao)
            : null;

        $venda->loadMissing(['itens.product', 'pagamentos', 'person']);

        $itens = [];
        $valorProdutos = 0.0;
        $valorDescontoItens = 0.0;
        $valorAcrescimoItens = 0.0;
        $ibptRows = [];
        $ibptLookup = app(\App\Support\Erp\Fiscal\IbptLookupService::class);

        foreach ($venda->itens as $index => $item) {
            $product = $item->product;
            $quantidade = (float) $item->quantidade;
            $precoLiquido = (float) $item->preco_unitario;
            // No PDV, desconto/acréscimo de item são unitários e já embutidos no preço líquido.
            $descontoUnit = max(0.0, (float) ($item->desconto ?? 0));
            $acrescimoUnit = max(0.0, (float) ($item->acrescimo ?? 0));
            $precoBruto = round($precoLiquido + $descontoUnit - $acrescimoUnit, 4);
            if ($precoBruto < 0) {
                $precoBruto = 0.0;
            }

            $valorBruto = round($quantidade * $precoBruto, 2);
            $descontoItem = round($descontoUnit * $quantidade, 2);
            $acrescimoItem = round($acrescimoUnit * $quantidade, 2);
            $totalLiquido = round((float) $item->total, 2);

            $valorProdutos += $valorBruto;
            $valorDescontoItens += $descontoItem;
            $valorAcrescimoItens += $acrescimoItem;

            $ibpt = $ibptLookup->calcularParaProduto($product, $totalLiquido);
            $ibptRows[] = [
                'trib_fed' => $ibpt['trib_fed'],
                'trib_est' => $ibpt['trib_est'],
                'trib_mun' => $ibpt['trib_mun'],
                'trib_imp' => $ibpt['trib_imp'],
                'ibpt_fonte' => $ibpt['fonte'],
                'ibpt_chave' => $ibpt['chave'],
                'ibpt_versao' => $ibpt['versao'],
            ];
            $imposto = $this->buildItemImposto($product, $totalLiquido, (float) $ibpt['v_tot_trib']);

            $itens[] = new ItemDto(
                numero: $index + 1,
                codigo: (string) ($item->codigo ?: $item->product_id ?: ($index + 1)),
                descricao: (string) $item->descricao,
                ncm: (string) ($product?->ncm ?: '00000000'),
                cfop: (string) ($product?->cfop_interno ?: '5102'),
                unidade: (string) ($item->unidade ?: 'UN'),
                quantidade: $quantidade,
                valorUnitario: $precoBruto,
                valorTotal: $valorBruto,
                imposto: $imposto,
                desconto: $descontoItem,
                acrescimo: $acrescimoItem,
            );
        }

        $ibptTotais = $ibptLookup->agregarItens($ibptRows);
        $textoIbpt = $ibptLookup->formatarTextoLei12741($ibptTotais);
        $obsVenda = trim((string) ($venda->observacoes ?? ''));
        $textoCanhoto = $this->formatCanhotoInformacoes($venda);
        $informacoesComplementares = $this->mergeInformacoesComIbpt(
            $this->mergeInformacoes($obsVenda, $textoCanhoto),
            $textoIbpt,
        );

        $pagamentos = [];

        foreach ($venda->pagamentos as $pagamento) {
            $valor = (float) $pagamento->valor;

            if ($valor <= 0) {
                continue;
            }

            $forma = trim((string) $pagamento->forma);
            $tipo = $this->mapTipoPagamento($forma);
            $isCartao = in_array($tipo, ['03', '04'], true);

            $pagamentos[] = new PagamentoDto(
                tipo: $tipo,
                valor: $valor,
                tpIntegra: $isCartao ? '2' : null,
                tBand: $isCartao ? $pagamento->tBandFiscal() : null,
                cAut: $isCartao && filled($pagamento->cartao_autorizacao)
                    ? (string) $pagamento->cartao_autorizacao
                    : null,
                descricao: $tipo === '99' ? mb_substr($forma !== '' ? $forma : 'Outros', 0, 60, 'UTF-8') : null,
            );
        }

        if ($pagamentos === []) {
            $pagamentos[] = new PagamentoDto('01', (float) $venda->total);
        }

        $destinatario = $this->buildDestinatario($venda);
        $respTecnico = NfeFiscalConfig::respTecnicoFromParametros($parametros);

        $valorDesconto = round($valorDescontoItens + (float) $venda->desconto, 2);
        $valorAcrescimo = round($valorAcrescimoItens + (float) $venda->acrescimo, 2);
        $valorNota = round((float) $venda->total, 2);
        $totalPagamentos = round(array_sum(array_map(
            static fn (PagamentoDto $pagamento): float => (float) $pagamento->valor,
            $pagamentos,
        )), 2);
        $valorTroco = round(max(0, (float) $venda->troco), 2);

        if ($valorTroco <= 0 && $totalPagamentos > $valorNota) {
            $valorTroco = round($totalPagamentos - $valorNota, 2);
        }

        return new EmitirNfceRequest(
            certificate: $certificate,
            emitente: $this->buildEmitente($empresa),
            ide: new IdeDto(
                serie: $serieNfce ?? ((int) ltrim((string) ($parametros->serie ?: '1'), '0') ?: 1),
                numero: $numeroNfce,
                cNf: $cNf,
                tpAmb: $tpAmb,
                tpEmis: $tpEmis,
                natOp: 'VENDA',
                codigoMunicipioFg: (string) ($empresa->cidade_codigo ?: ''),
                dataEmissao: $emissao,
                justificativaContingencia: $justificativa,
                dataContingencia: $dhContingencia,
            ),
            itens: $itens,
            pagamentos: $pagamentos,
            valorProdutos: round($valorProdutos, 2),
            valorNota: $valorNota,
            valorDesconto: $valorDesconto,
            valorAcrescimo: $valorAcrescimo,
            valorTotTrib: (float) ($ibptTotais['v_tot_trib'] ?? 0),
            valorTroco: $valorTroco,
            destinatario: $destinatario,
            idToken: trim((string) ($parametros->id_token ?? '')),
            csc: trim((string) ($parametros->token ?? '')),
            versaoQrcode: (int) ($parametros->versao_qrcode ?: 2),
            informacoesComplementares: $informacoesComplementares,
            homologacao: $tpAmb === 2,
            respTecnico: $respTecnico,
        );
    }

    public function podeEmitirReal(VendasParametro $parametros, Empresa $empresa, string $operacao): bool
    {
        return $this->motivosBloqueioEmissaoReal($parametros, $empresa, $operacao) === [];
    }

    /**
     * @return list<string>
     */
    public function motivosBloqueioEmissaoReal(VendasParametro $parametros, Empresa $empresa, string $operacao): array
    {
        $motivos = [];

        if (! in_array($operacao, [
            PdvFinalizarOperacao::NFCE_TRANSMITIR,
            PdvFinalizarOperacao::FINALIZAR,
            PdvFinalizarOperacao::NFCE_CONTINGENCIA,
        ], true)) {
            $motivos[] = 'Operação não é de emissão fiscal.';
        }

        if (strtoupper((string) ($parametros->uf ?: $empresa->uf)) !== 'SC') {
            $motivos[] = 'Emissão real disponível apenas para UF SC.';
        }

        if (NfeFiscalConfig::certificadoAbsolutePath($parametros) === null) {
            $motivos[] = 'Certificado digital não configurado.';
        }

        if (! $parametros->hasStoredSenhaCertificado()) {
            $motivos[] = 'Senha do certificado não informada.';
        }

        if (blank($parametros->id_token) || blank($parametros->token)) {
            $motivos[] = 'CSC / ID Token da NFC-e não configurados.';
        }

        if (! NfeFiscalConfig::respTecnicoConfigurado($parametros)) {
            $motivos[] = 'Responsável técnico incompleto (Configurações » Técnico Responsável).';
        }

        if (blank($empresa->cidade_codigo)) {
            $motivos[] = 'Código IBGE do município da empresa não informado.';
        }

        return $motivos;
    }

    public function podeOperarReal(VendasParametro $parametros, Empresa $empresa): bool
    {
        return $this->podeCancelarReal($parametros, $empresa);
    }

    public function podeCancelarReal(VendasParametro $parametros, Empresa $empresa): bool
    {
        if (strtoupper((string) ($parametros->uf ?: $empresa->uf)) !== 'SC') {
            return false;
        }

        if (NfeFiscalConfig::certificadoAbsolutePath($parametros) === null) {
            return false;
        }

        if (! $parametros->hasStoredSenhaCertificado()) {
            return false;
        }

        return filled($empresa->cnpj);
    }

    private function validarPreRequisitos(Empresa $empresa, VendasParametro $parametros, string $operacao): void
    {
        if (! $this->podeEmitirReal($parametros, $empresa, $operacao)) {
            throw new FiscalEngineException('Emissão real de NFC-e não está configurada para esta empresa/UF.');
        }

        if (blank($empresa->ie)) {
            throw new FiscalEngineException('Inscrição estadual da empresa não informada.');
        }

        if (! NfeFiscalConfig::respTecnicoConfigurado($parametros)) {
            throw new FiscalEngineException(
                'Responsável técnico não configurado. Preencha em Configurações » Técnico Responsável.',
            );
        }
    }

    private function buildEmitente(Empresa $empresa): EmitenteDto
    {
        return new EmitenteDto(
            cnpj: (string) $empresa->cnpj,
            razaoSocial: (string) ($empresa->razao_social ?: $empresa->nome),
            nomeFantasia: (string) ($empresa->fantasia ?: $empresa->nome),
            ie: (string) $empresa->ie,
            crt: $this->mapCrt((string) ($empresa->regime_tributario ?? 'simples')),
            logradouro: (string) ($empresa->endereco ?? 'NAO INFORMADO'),
            numero: (string) ($empresa->numero ?: 'S/N'),
            bairro: (string) ($empresa->bairro ?? 'CENTRO'),
            codigoMunicipio: (string) $empresa->cidade_codigo,
            municipio: (string) ($empresa->cidade ?? ''),
            uf: strtoupper((string) ($empresa->uf ?? 'SC')),
            cep: (string) ($empresa->cep ?? '00000000'),
            telefone: (string) ($empresa->telefone ?? ''),
        );
    }

    private function buildDestinatario(PdvVenda $venda): ?DestinatarioDto
    {
        $cpf = NfceConsumidorIdentificado::cpfDigitsDaVenda($venda);

        if ($cpf === '') {
            return null;
        }

        $person = NfceConsumidorIdentificado::resolvePerson($venda);
        $nome = NfceConsumidorIdentificado::nome($person);

        if ($person === null || $nome === null) {
            return new DestinatarioDto(
                cpf: $cpf,
                nome: 'CONSUMIDOR',
            );
        }

        $telefone = (string) ($person->fone1 ?: $person->celular1 ?: $person->whatsapp ?: '');

        return new DestinatarioDto(
            cpf: $cpf,
            nome: $nome,
            logradouro: filled($person->endereco) ? (string) $person->endereco : null,
            numero: (string) ($person->numero ?: 'S/N'),
            bairro: filled($person->bairro) ? (string) $person->bairro : null,
            codigoMunicipio: filled($person->cidade_codigo) ? (string) $person->cidade_codigo : null,
            municipio: filled($person->cidade_nome) ? (string) $person->cidade_nome : null,
            uf: filled($person->uf) ? strtoupper((string) $person->uf) : null,
            cep: filled($person->cep) ? (string) $person->cep : null,
            telefone: $telefone !== '' ? $telefone : null,
            email: filled($person->email) ? (string) $person->email : null,
        );
    }

    private function buildItemImposto(?Product $product, float $base, float $vTotTrib = 0.0): ItemImpostoDto
    {
        return IbscbsImpostoFactory::fromProduct($product, $base, $vTotTrib);
    }

    private function mergeInformacoesComIbpt(string $obs, string $ibptTexto): string
    {
        $obs = trim($obs);
        $ibptTexto = trim($ibptTexto);

        if ($ibptTexto === '') {
            return $obs;
        }

        if ($obs === '') {
            return $ibptTexto;
        }

        if (str_contains($obs, 'Lei 12.741') || str_contains($obs, 'Trib. aprox.')) {
            return $obs;
        }

        return rtrim($obs, " .\n").'. '.$ibptTexto;
    }

    private function mergeInformacoes(string $base, string $extra): string
    {
        $base = trim($base);
        $extra = trim($extra);

        if ($extra === '') {
            return $base;
        }

        if ($base === '') {
            return $extra;
        }

        if (str_contains($base, $extra)) {
            return $base;
        }

        return rtrim($base, " .\n").'. '.$extra;
    }

    private function formatCanhotoInformacoes(PdvVenda $venda): string
    {
        $venda->loadMissing('pagamentos');

        $partes = $venda->pagamentos
            ->filter(fn ($pagamento): bool => $pagamento->temCanhotoCartao())
            ->map(fn ($pagamento): string => $pagamento->descricaoComCanhoto())
            ->filter()
            ->values()
            ->all();

        return $partes === [] ? '' : 'Cartão: '.implode(' / ', $partes);
    }

    private function mapCrt(string $regime): int
    {
        return match (strtolower($regime)) {
            'simples' => 1,
            'presumido', 'real', 'normal' => 3,
            default => 1,
        };
    }

    private function mapTpAmb(int $ambienteParametro): int
    {
        return $ambienteParametro === VendasParametro::AMBIENTE_PRODUCAO ? 1 : 2;
    }

    private function mapTipoPagamento(string $forma): string
    {
        $forma = mb_strtoupper(trim($forma), 'UTF-8');

        return match (true) {
            str_contains($forma, 'DINHEIRO') => '01',
            str_contains($forma, 'CHEQUE') => '02',
            // PIX antes de crédito/débito para não confundir descrições compostas.
            // 20 = PIX estático (QR/chave fixa no PDV) — não exige grupo card.
            str_contains($forma, 'PIX') => '20',
            str_contains($forma, 'BOLETO') => '15',
            str_contains($forma, 'DEPOSITO') || str_contains($forma, 'DEPÓSITO') => '16',
            str_contains($forma, 'TRANSFERENCIA') || str_contains($forma, 'TRANSFERÊNCIA') => '18',
            str_contains($forma, 'CREDIARIO')
                || str_contains($forma, 'CREDIÁRIO')
                || str_contains($forma, 'PRAZO')
                || str_contains($forma, 'FIADO') => '05',
            str_contains($forma, 'CREDITO') || str_contains($forma, 'CRÉDITO') => '03',
            str_contains($forma, 'DEBITO') || str_contains($forma, 'DÉBITO') => '04',
            // TEF sem detalhe de débito/crédito: trata como débito POS.
            str_contains($forma, 'TEF') => '04',
            // TROCA e demais → 99 (exige xPag no XML).
            default => '99',
        };
    }
}
