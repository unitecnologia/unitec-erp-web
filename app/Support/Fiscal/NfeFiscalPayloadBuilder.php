<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeItem;
use App\Models\Person;
use App\Models\VendasParametro;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use Unitec\FiscalEngine\Certificate\CertificateLoader;
use Unitec\FiscalEngine\Dto\EmitenteDto;
use Unitec\FiscalEngine\Dto\EmitirNfeRequest;
use Unitec\FiscalEngine\Dto\FaturaParcelaDto;
use Unitec\FiscalEngine\Dto\IdeDto;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\NfeDestinatarioDto;
use Unitec\FiscalEngine\Dto\PagamentoDto;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class NfeFiscalPayloadBuilder
{
    public function build(Nfe $nfe, Empresa $empresa, VendasParametro $parametros): EmitirNfeRequest
    {
        $this->validarPreRequisitos($empresa, $parametros);

        $certPath = NfeFiscalConfig::certificadoAbsolutePath($parametros);
        $senha = $parametros->safeSenhaCertificado();

        if ($certPath === null || $senha === null) {
            throw new FiscalEngineException('Certificado digital ou senha não configurados.');
        }

        $certificate = CertificateLoader::fromPkcs12File($certPath, $senha, (string) $empresa->cnpj);
        $tpAmb = $this->mapTpAmb((int) $parametros->ambiente);
        $cNf = filled($nfe->cnf)
            ? (int) ltrim((string) $nfe->cnf, '0')
            : random_int(1, 99999999);

        $nfe->loadMissing(['itens.product', 'faturas', 'cliente']);
        $cliente = $nfe->cliente;

        if (! $cliente instanceof Person) {
            throw new FiscalEngineException('Cliente da NF-e não informado.');
        }

        if ($nfe->itens->isEmpty()) {
            throw new FiscalEngineException('NF-e sem itens para transmissão.');
        }

        $empresaUf = strtoupper((string) ($empresa->uf ?? 'SC'));
        $clienteUf = strtoupper((string) ($cliente->uf ?? $empresaUf));
        $idDest = $this->resolveIdDest($empresaUf, $clienteUf);

        if (! $nfe->data_emissao) {
            throw new FiscalEngineException('Data de emissão da NF-e não informada.');
        }

        $emissao = $nfe->data_emissao->startOfDay();
        if ($nfe->hora_emissao) {
            $emissao = $emissao->setTimeFromTimeString((string) $nfe->hora_emissao);
        }

        $dataSaida = $nfe->data_saida;

        if ($nfe->hora_saida && $dataSaida) {
            $dataSaida = $dataSaida->setTimeFromTimeString((string) $nfe->hora_saida);
        }

        $itens = $nfe->itens
            ->sortBy('item')
            ->values()
            ->map(fn (NfeItem $item, int $index): ItemDto => $this->mapItem($item, $index + 1))
            ->all();

        $parcelas = $nfe->faturas
            ->sortBy('numero')
            ->values()
            ->map(function ($fatura): FaturaParcelaDto {
                if (! $fatura->data_vencimento) {
                    throw new FiscalEngineException(
                        'Parcela '.$fatura->numero.' sem data de vencimento.'
                    );
                }

                return new FaturaParcelaDto(
                    numero: (string) $fatura->numero,
                    vencimento: $fatura->data_vencimento,
                    valor: (float) $fatura->valor,
                );
            })
            ->all();

        $informacoesContribuinte = trim((string) ($nfe->obs_contribuinte ?? ''));
        $informacoesFisco = trim((string) ($nfe->obs_fisco ?? ''));
        $valorTotTrib = round(
            (float) ($nfe->trib_fed ?? 0) + (float) ($nfe->trib_est ?? 0) + (float) ($nfe->trib_mun ?? 0),
            2,
        );

        if ($valorTotTrib <= 0 && $informacoesContribuinte === '') {
            $ibptAgg = app(\App\Support\Erp\Fiscal\IbptLookupService::class)->agregarItens(
                $nfe->itens->map(static fn (NfeItem $item): array => [
                    'trib_fed' => (float) ($item->trib_fed ?? 0),
                    'trib_est' => (float) ($item->trib_est ?? 0),
                    'trib_mun' => (float) ($item->trib_mun ?? 0),
                    'trib_imp' => (float) ($item->trib_imp ?? 0),
                ])->all()
            );
            $valorTotTrib = (float) $ibptAgg['v_tot_trib'];
            $informacoesContribuinte = app(\App\Support\Erp\Fiscal\IbptLookupService::class)
                ->formatarTextoLei12741($ibptAgg);
        }

        return new EmitirNfeRequest(
            certificate: $certificate,
            emitente: $this->buildEmitente($empresa),
            ide: new IdeDto(
                serie: (int) ltrim((string) ($nfe->serie ?: '1'), '0') ?: 1,
                numero: (int) ltrim((string) $nfe->numero, '0'),
                cNf: $cNf,
                tpAmb: $tpAmb,
                tpEmis: (int) ltrim((string) ($nfe->tipo_emissao ?: '1'), '0') ?: 1,
                natOp: 'VENDA',
                codigoMunicipioFg: (string) ($empresa->cidade_codigo ?: ''),
                dataEmissao: $emissao,
            ),
            destinatario: $this->buildDestinatario($cliente),
            itens: $itens,
            valorProdutos: round((float) ($nfe->subtotal ?: $nfe->total), 2),
            valorNota: round((float) $nfe->total, 2),
            idDest: $idDest,
            indFinal: $this->resolveIndFinal($nfe, $cliente),
            finNFe: $this->mapFinNFe((string) ($nfe->finalidade ?? 'normal')),
            modFrete: (int) ltrim((string) ($nfe->tipo_frete ?? '9'), '0'),
            valorDesconto: round((float) ($nfe->desconto ?? 0), 2),
            valorFrete: round((float) ($nfe->frete ?? 0), 2),
            valorSeguro: round((float) ($nfe->seguro ?? 0), 2),
            valorOutros: round((float) ($nfe->outros ?? 0), 2),
            valorTotTrib: $valorTotTrib,
            dataSaida: $dataSaida,
            parcelas: $parcelas,
            informacoesComplementares: $informacoesContribuinte,
            informacoesFisco: $informacoesFisco,
            pagamentos: $this->mapPagamentos($nfe),
            homologacao: $tpAmb === 2,
            respTecnico: NfeFiscalConfig::respTecnicoFromParametros($parametros),
        );
    }

    public function podeEmitirReal(VendasParametro $parametros, Empresa $empresa): bool
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

        if (! NfeFiscalConfig::respTecnicoConfigurado($parametros)) {
            return false;
        }

        return filled($empresa->cidade_codigo) && filled($empresa->cnpj) && filled($empresa->ie);
    }

    private function validarPreRequisitos(Empresa $empresa, VendasParametro $parametros): void
    {
        if (! $this->podeEmitirReal($parametros, $empresa)) {
            throw new FiscalEngineException('Transmissão real de NF-e não está configurada para esta empresa/UF.');
        }

        if (blank($empresa->ie)) {
            throw new FiscalEngineException('Inscrição estadual da empresa não informada.');
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

    private function buildDestinatario(Person $cliente): NfeDestinatarioDto
    {
        $digits = preg_replace('/\D/', '', (string) ($cliente->cpf_cnpj ?? '')) ?? '';
        $cpf = strlen($digits) === 11 ? $digits : null;
        $cnpj = strlen($digits) === 14 ? $digits : null;
        [$indIeDest, $ie] = $this->resolveIndIeDest($cliente);

        return new NfeDestinatarioDto(
            cpf: $cpf,
            cnpj: $cnpj,
            nome: (string) ($cliente->nome_razao ?: $cliente->apelido_fantasia ?: 'DESTINATARIO'),
            logradouro: (string) ($cliente->endereco ?: 'NAO INFORMADO'),
            numero: (string) ($cliente->numero ?: 'S/N'),
            bairro: (string) ($cliente->bairro ?: 'CENTRO'),
            codigoMunicipio: (string) ($cliente->cidade_codigo ?: '0000000'),
            municipio: (string) ($cliente->cidade_nome ?: 'NAO INFORMADO'),
            uf: strtoupper((string) ($cliente->uf ?: 'SC')),
            cep: (string) ($cliente->cep ?: '00000000'),
            indIeDest: $indIeDest,
            ie: $ie,
            email: (string) ($cliente->email ?? ''),
            telefone: (string) ($cliente->fone1 ?: $cliente->celular1 ?: ''),
        );
    }

    /**
     * @return array{0: int, 1: ?string}
     */
    private function resolveIndIeDest(Person $cliente): array
    {
        if ($cliente->isPessoaFisica()) {
            return match (strtolower((string) ($cliente->tipo_contribuinte ?? 'nao_contribuinte'))) {
                'isento' => [2, null],
                default => [9, null],
            };
        }

        $tipo = strtolower((string) ($cliente->tipo_contribuinte ?? 'nao_contribuinte'));
        $ie = preg_replace('/\D/', '', (string) ($cliente->rg_ie ?? '')) ?: null;

        return match ($tipo) {
            'contribuinte' => [1, $ie],
            'isento' => [2, null],
            default => [9, null],
        };
    }

    private function resolveIndFinal(Nfe $nfe, Person $cliente): int
    {
        if ($cliente->isConsumidorFinalPadrao()) {
            return 1;
        }

        return (int) ($nfe->consumidor_final === '1' ? 1 : 0);
    }

    private function mapItem(NfeItem $item, int $numero): ItemDto
    {
        $origem = (int) ($item->product?->origem ?? 0);
        $vTotTrib = round(
            (float) ($item->trib_fed ?? 0) + (float) ($item->trib_est ?? 0) + (float) ($item->trib_mun ?? 0),
            2,
        );

        if ($vTotTrib <= 0 && filled($item->ncm)) {
            $ibpt = app(\App\Support\Erp\Fiscal\IbptLookupService::class)->calcularParaBase(
                (string) $item->ncm,
                (float) $item->total,
                $origem,
            );
            $vTotTrib = (float) $ibpt['v_tot_trib'];
        }

        return new ItemDto(
            numero: $numero,
            codigo: (string) ($item->cod_barra ?: $item->product_id ?: $numero),
            descricao: (string) $item->descricao,
            ncm: (string) ($item->ncm ?: '00000000'),
            cfop: (string) ($item->cfop ?: '5102'),
            unidade: (string) ($item->unidade ?: 'UN'),
            quantidade: (float) $item->quantidade,
            valorUnitario: (float) $item->valor_unitario,
            valorTotal: (float) $item->total,
            imposto: IbscbsImpostoFactory::fromNfeItem(
                item: $item,
                origem: $origem,
                csosn: (string) ($item->csosn ?: '102'),
                vIcms: (float) ($item->valor_icms ?? 0),
                vTotTrib: $vTotTrib,
            ),
            desconto: (float) ($item->desconto ?? 0),
        );
    }

    private function resolveIdDest(string $empresaUf, string $clienteUf): int
    {
        if ($clienteUf === '' || $clienteUf === 'EX') {
            return 3;
        }

        return $empresaUf === $clienteUf ? 1 : 2;
    }

    private function mapFinNFe(string $finalidade): int
    {
        return match ($finalidade) {
            'complementar' => 2,
            'ajuste' => 3,
            'devolucao' => 4,
            default => 1,
        };
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

    /**
     * @return list<PagamentoDto>
     */
    private function mapPagamentos(Nfe $nfe): array
    {
        $valor = round((float) $nfe->total, 2);

        if ($valor <= 0) {
            return [];
        }

        return [new PagamentoDto(
            tipo: $this->mapMeioPgto((string) ($nfe->meio_pgto ?? 'dinheiro')),
            valor: $valor,
        )];
    }

    private function mapMeioPgto(string $meio): string
    {
        return match (strtolower($meio)) {
            'cartao' => '03',
            'boleto' => '15',
            'pix' => '20',
            'cheque' => '02',
            'credito_loja' => '05',
            'deposito' => '16',
            'transferencia' => '18',
            default => '01',
        };
    }
}
