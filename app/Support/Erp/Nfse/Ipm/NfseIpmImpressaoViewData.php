<?php

namespace App\Support\Erp\Nfse\Ipm;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Models\Person;
use App\Support\Erp\Fiscal\IbptLookupService;
use App\Support\Erp\Nfse\NfseImpressao;
use App\Support\Erp\Nfse\NfseRegimeTributario;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

/**
 * Dados de exibição da NFS-e IPM. Só lê nota, cadastros e o XML já gravado.
 */
final class NfseIpmImpressaoViewData
{
    private const NAO_INFORMADO = 'Não informado';

    public static function aplica(Nfse $nfse): bool
    {
        $xml = (string) ($nfse->xml_nfse ?? '');

        if (str_contains($xml, 'http://www.sped.fazenda.gov.br/nfse')) {
            return false;
        }

        if (str_contains($xml, 'http://www.abrasf.org.br/nfse.xsd') || str_contains($xml, '<InfNfse')) {
            return true;
        }

        $empresa = self::empresa($nfse);

        return strtolower(trim((string) ($empresa?->nfse_provedor ?? ''))) === 'ipm';
    }

    /**
     * @return array<string, mixed>
     */
    public static function for(Nfse $nfse, bool $autoPrint = false, bool $embedded = false): array
    {
        $empresa = self::empresa($nfse);
        $nfse->loadMissing('itens');
        $xmlNfse = self::dom((string) ($nfse->xml_nfse ?? ''));
        $xmlDps = self::dom((string) ($nfse->xml_dps ?? ''));
        $pessoa = self::tomadorCadastro($nfse);

        $item = $nfse->itens->first();
        $codigoServico = self::digitos($item?->c_trib_nac);
        $nbs = self::digitos($item?->c_nbs);
        $simples = self::optanteSimples($empresa);
        $valorServicos = self::numero($nfse->valor_servicos);
        $desconto = self::numero($nfse->desconto);
        $deducao = self::numero(self::tag($xmlNfse, 'ValorDeducoes') ?? self::tag($xmlDps, 'ValorDeducoes') ?? '0');
        $issOficial = self::tagPai($xmlNfse, 'ValorIss', ['ValoresNfse']);
        $iss = self::numero($issOficial ?? $nfse->iss);
        $base = max(0, round($valorServicos - $desconto - $deducao, 2));
        $baseOficial = self::tagPai($xmlNfse, 'BaseCalculo', ['ValoresNfse']);
        if ($baseOficial !== null) {
            $base = self::numero($baseOficial);
        }
        $retido = in_array(trim((string) $nfse->tp_ret_issqn), ['2', '3'], true);
        $issRetido = $retido ? $iss : 0.0;

        $ir = self::numero(self::tag($xmlNfse, 'ValorIr') ?? self::tag($xmlDps, 'ValorIr'));
        $inss = self::numero(self::tag($xmlNfse, 'ValorInss') ?? self::tag($xmlDps, 'ValorInss'));
        $csll = self::numero(self::tag($xmlNfse, 'ValorCsll') ?? self::tag($xmlDps, 'ValorCsll'));
        $cofins = self::numero(self::tag($xmlNfse, 'ValorCofins') ?? self::tag($xmlDps, 'ValorCofins'));
        $pis = self::numero(self::tag($xmlNfse, 'ValorPis') ?? self::tag($xmlDps, 'ValorPis'));
        $outrasRetencoes = self::numero(self::tag($xmlNfse, 'OutrasRetencoes') ?? self::tag($xmlDps, 'OutrasRetencoes'));
        $descCondicional = self::numero(self::tag($xmlNfse, 'DescontoCondicionado') ?? self::tag($xmlDps, 'DescontoCondicionado'));
        $federais = round($ir + $inss + $csll + $cofins + $pis + $outrasRetencoes, 2);

        $identificador = self::primeiroTexto([
            $nfse->chave,
            self::tag($xmlNfse, 'CodigoVerificacao'),
        ]);
        $outrasOficial = trim((string) (self::tag($xmlNfse, 'OutrasInformacoes') ?? ''));
        $chaveAcesso = self::primeiroTexto([
            $nfse->chave_acesso,
            self::tag($xmlNfse, 'ChaveAcesso'),
            self::tag($xmlNfse, 'ChaveAcessoNfse'),
            preg_match('/Chave de Acesso NFS-e Nacional:\s*(\d{50})/i', $outrasOficial, $chave) === 1 ? $chave[1] : null,
        ]);
        $numero = self::primeiroTexto([
            $nfse->numero_nfse,
            self::tagPai($xmlNfse, 'Numero', ['InfNfse']),
        ]);

        $localCodigo = self::digitos($nfse->municipio_prestacao_codigo);
        $localNome = trim((string) $nfse->municipio_prestacao_nome);
        $localUf = strtoupper(trim((string) $nfse->municipio_prestacao_uf));
        $prestadorCodigo = self::digitos($empresa?->cidade_codigo);
        $mesmoMunicipio = ($prestadorCodigo !== '' && $prestadorCodigo === $localCodigo)
            || ($localNome !== '' && self::igual($localNome, (string) ($empresa?->cidade ?? '')));

        $municipioLocal = NfseIpmMunicipios::danfse($localCodigo);
        $municipioPrestador = NfseIpmMunicipios::danfse($prestadorCodigo);

        $consulta = self::urlConsulta($empresa, $nfse, $identificador, $xmlNfse);
        $qrConteudo = $consulta ?? ($identificador !== '' ? preg_replace('/\s+/', '', $identificador) : null);

        $lc = NfseLc116::descricao($codigoServico);
        $nbsDescricao = self::descricaoNbs($nbs);
        $cnae = self::digitos($empresa?->cnae);
        if ($cnae === '') {
            $cnae = self::digitos(self::tag($xmlNfse, 'CodigoCnae') ?? self::tag($xmlDps, 'CodigoCnae'));
        }
        $atividade = $cnae !== ''
            ? self::formatarCnae($cnae)
            : self::texto($item?->c_trib_mun, self::NAO_INFORMADO);

        $aliquotaNumerica = self::tagPai($xmlNfse, 'Aliquota', ['ValoresNfse'])
            ?? self::tag($xmlNfse, 'Aliquota')
            ?? self::tag($xmlDps, 'Aliquota');
        $semAliquota = $simples && self::numero($aliquotaNumerica) <= 0;
        $aliquota = $semAliquota
            ? 'SIMPLES NACIONAL'
            : self::aliquota($aliquotaNumerica, $iss, $base);

        $textoIss = $semAliquota ? 'SIMPLES NACIONAL' : self::moeda($iss);
        $textoBase = $semAliquota ? 'SIMPLES NACIONAL' : self::moeda($base);
        $localExibido = $municipioLocal['siafi'] ?? $localCodigo;
        $cei = trim((string) (self::tag($xmlNfse, 'CodigoObra') ?? self::tag($xmlDps, 'CodigoObra') ?? ''));
        $serie = trim((string) ($nfse->serie_dps ?: ($empresa?->nfse_serie_rps ?? '')));

        return [
            'nfse' => $nfse,
            'autoPrint' => $autoPrint,
            'embedded' => $embedded,
            'espelho' => false,
            'impressao_view' => NfseImpressao::VIEW_IPM,
            'ipm' => [
                'espelho' => false,
                'homologacao' => trim((string) $nfse->tipo_ambiente) === '2',
                'cancelada' => $nfse->status === Nfse::STATUS_CANCELADA,
                'substituida' => $nfse->status === Nfse::STATUS_SUBSTITUIDA,
                'prestador' => self::prestador($empresa, $municipioPrestador),
                'serie' => $serie !== '' ? $serie : 'NFS-e',
                'numero' => $numero !== '' ? $numero : '-',
                'situacao' => self::situacao($nfse),
                'tipo' => self::tipo($empresa, $xmlDps, $xmlNfse),
                'qr_data_uri' => is_string($qrConteudo) && $qrConteudo !== '' ? self::qr($qrConteudo) : null,
                'consulta_url' => $consulta,
                'identificador' => $identificador !== '' ? self::grupos($identificador) : '-',
                'codigo_barras' => $identificador !== '' ? NfseIpmCodigoBarras::pngDataUri($identificador) : null,
                'chave_acesso' => $chaveAcesso !== '' ? $chaveAcesso : '-',
                'fato_gerador' => self::dataCurta($nfse->data_emissao) !== '-'
                    ? self::dataCurta($nfse->data_emissao)
                    : self::dataCurta($nfse->competencia),
                'emissao' => self::dataHora($nfse->data_hora_processamento)
                    ?? self::dataHora(self::tag($xmlNfse, 'DataEmissao'))
                    ?? self::dataCurta($nfse->data_emissao),
                'tomador' => self::tomador($nfse, $pessoa),
                'servico_codigo' => $codigoServico !== '' ? (ltrim($codigoServico, '0') ?: $codigoServico) : '-',
                'local_codigo' => $localExibido !== '' ? $localExibido : ($localNome !== '' ? $localNome : '-'),
                'aliquota' => $aliquota,
                'valor_servico' => self::moeda($valorServicos),
                'desconto_incondicional' => self::moeda($desconto),
                'deducao' => self::moeda($deducao),
                'valor_iss' => $textoIss,
                'natureza' => self::natureza((string) $nfse->trib_issqn),
                'nbs' => self::linhaNbs($nbs, $nbsDescricao),
                'descricao' => self::descricoes($nfse),
                'valor_total' => self::moeda($valorServicos),
                'base_calculo' => $textoBase,
                'issqn' => $textoIss,
                'issrf' => self::moeda($issRetido),
                'ir' => self::moeda($ir),
                'inss' => self::moeda($inss),
                'csll' => self::moeda($csll),
                'cofins' => self::moeda($cofins),
                'pis' => self::moeda($pis),
                'outras_retencoes' => self::moeda($outrasRetencoes),
                'total_federais' => self::moeda($federais),
                'desconto_condicional' => self::moeda($descCondicional),
                'valor_liquido' => self::moeda($nfse->total),
                'cei' => $cei,
                'lc116' => self::linhaLc(ltrim($codigoServico, '0') ?: $codigoServico, $lc),
                'atividade' => $atividade,
                'legenda_local' => $municipioLocal !== null
                    ? $municipioLocal['siafi'].' - '.$municipioLocal['nome']
                    : self::legendaLocal($localCodigo, $localNome, $localUf),
                'tributacao' => self::tributacao(ltrim($codigoServico, '0') ?: $codigoServico, $mesmoMunicipio, $localNome, $localUf, $municipioLocal),
                'outras' => self::outras($empresa, $nfse, $municipioPrestador, $mesmoMunicipio, $simples, $retido, $valorServicos, $pis, $cofins, $nbs !== '' ? $nbs : $codigoServico, $consulta, $outrasOficial),
                'observacoes' => self::observacoes($nfse),
                'responsavel' => self::texto($empresa?->razao_social ?: $empresa?->nome, ''),
            ],
        ];
    }

    private static function empresa(Nfse $nfse): ?Empresa
    {
        if ($nfse->relationLoaded('empresa')) {
            $empresa = $nfse->empresa;

            return $empresa instanceof Empresa ? $empresa : null;
        }

        if (! filled($nfse->empresa_id)) {
            return null;
        }

        $nfse->loadMissing('empresa');
        $empresa = $nfse->empresa;

        return $empresa instanceof Empresa ? $empresa : null;
    }

    private static function tomadorCadastro(Nfse $nfse): ?Person
    {
        if (! filled($nfse->tomador_id)) {
            return null;
        }

        try {
            $nfse->loadMissing('tomador');
        } catch (Throwable) {
            return null;
        }

        $pessoa = $nfse->tomador;

        return $pessoa instanceof Person ? $pessoa : null;
    }

    /**
     * @param  array{siafi: string, nome: string, secretaria: string, texto_simples: string, vencimento_iss_dia: int}|null  $municipio
     * @return array<string, string>
     */
    private static function prestador(?Empresa $empresa, ?array $municipio): array
    {
        if (! $empresa instanceof Empresa) {
            return [
                'nome' => '-',
                'documento' => self::NAO_INFORMADO,
                'endereco' => self::NAO_INFORMADO,
                'cep_bairro' => self::NAO_INFORMADO,
                'municipio' => self::NAO_INFORMADO,
                'im' => '',
                'ie' => '',
                'email' => self::NAO_INFORMADO,
                'telefone' => self::NAO_INFORMADO,
                'celular' => self::NAO_INFORMADO,
                'prefeitura' => '',
                'secretaria' => '',
                'estado' => '',
            ];
        }

        $logradouro = trim((string) $empresa->endereco);
        $numero = trim((string) $empresa->numero);
        $endereco = $logradouro;
        if ($numero !== '') {
            $endereco = $endereco !== '' ? $endereco.' - '.$numero : $numero;
        }
        if (trim((string) $empresa->complemento) !== '') {
            $endereco = trim($endereco.' - '.trim((string) $empresa->complemento), ' -');
        }

        $cidade = $municipio['nome'] ?? trim((string) $empresa->cidade);
        $uf = strtoupper(trim((string) $empresa->uf));
        $estado = self::estado($uf);
        $telefone = self::telefone($empresa->telefone);

        return [
            'nome' => self::texto($empresa->razao_social ?: $empresa->nome ?: $empresa->fantasia, '-'),
            'documento' => self::documento($empresa->cnpj),
            'endereco' => $endereco !== '' ? $endereco : self::NAO_INFORMADO,
            'cep_bairro' => self::cepBairro($empresa->cep, $empresa->bairro),
            'municipio' => self::municipioLinha($cidade, $estado !== '' ? $estado : $uf),
            'im' => trim((string) $empresa->im),
            'ie' => trim((string) $empresa->ie),
            'email' => self::texto($empresa->email, self::NAO_INFORMADO),
            'telefone' => $telefone,
            'celular' => $telefone,
            'brasao' => self::brasao($empresa->cidade_codigo),
            'prefeitura' => $cidade !== '' ? mb_strtoupper($cidade, 'UTF-8') : '',
            'secretaria' => $municipio['secretaria'] ?? '',
            'estado' => $estado,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function tomador(Nfse $nfse, ?Person $pessoa): array
    {
        $complemento = trim((string) ($pessoa?->complemento ?? ''));
        $telefone = trim((string) $nfse->tomador_telefone);
        if ($telefone === '' && $pessoa instanceof Person) {
            $telefone = trim((string) ($pessoa->fone1 ?: $pessoa->celular1 ?: $pessoa->fone2 ?: ''));
        }
        $email = trim((string) $nfse->tomador_email);
        if ($email === '' && $pessoa instanceof Person) {
            $email = trim((string) ($pessoa->email ?: ''));
        }

        $cidade = trim((string) $nfse->tomador_cidade);
        $uf = strtoupper(trim((string) $nfse->tomador_uf));
        $cidadeFmt = $cidade;
        if ($cidadeFmt !== '' && $uf !== '' && ! str_contains(mb_strtoupper($cidadeFmt, 'UTF-8'), $uf)) {
            $cidadeFmt .= ' - '.$uf;
        }

        $pais = $uf !== '' || $cidade !== '' ? 'Brasil - BR - 1058' : self::NAO_INFORMADO;

        return [
            'nome' => self::texto($nfse->tomador_nome, self::NAO_INFORMADO),
            'documento' => self::documento($nfse->tomador_cpf_cnpj, self::NAO_INFORMADO),
            'endereco' => self::texto($nfse->tomador_endereco, self::NAO_INFORMADO),
            'numero' => self::texto($nfse->tomador_numero, self::NAO_INFORMADO),
            'complemento' => $complemento !== '' ? $complemento : self::NAO_INFORMADO,
            'bairro' => self::texto($nfse->tomador_bairro, self::NAO_INFORMADO),
            'cep' => self::cep($nfse->tomador_cep),
            'cidade' => $cidadeFmt !== '' ? $cidadeFmt : self::NAO_INFORMADO,
            'pais' => $pais,
            'telefone' => $telefone !== '' ? self::telefone($telefone) : self::NAO_INFORMADO,
            'email' => $email !== '' ? $email : self::NAO_INFORMADO,
        ];
    }

    private static function brasao(mixed $codigoIbge): ?string
    {
        $codigo = self::digitos($codigoIbge);
        $arquivo = resource_path('images/nfse-ipm/'.$codigo.'.png');

        if ($codigo === '' || ! is_file($arquivo)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($arquivo));
    }

    private static function situacao(Nfse $nfse): string
    {
        return match ($nfse->status) {
            Nfse::STATUS_AUTORIZADA => 'Emitida',
            Nfse::STATUS_CANCELADA => 'Cancelada',
            Nfse::STATUS_SUBSTITUIDA => 'Substituída',
            default => $nfse->statusLabel(),
        };
    }

    private static function tipo(?Empresa $empresa, ?DOMDocument $xmlDps, ?DOMDocument $xmlNfse): string
    {
        $codigo = self::tagPai($xmlDps, 'Tipo', ['IdentificacaoRps'])
            ?? self::tagPai($xmlNfse, 'Tipo', ['IdentificacaoRps'])
            ?? trim((string) ($empresa?->nfse_tipo_rps ?? ''));

        if ($codigo === '') {
            $codigo = '1';
        }

        return match ($codigo) {
            '1' => 'RPS',
            '2' => 'Nota Fiscal Conjugada',
            '3' => 'Cupom',
            default => $codigo,
        };
    }

    private static function natureza(string $tributacao): string
    {
        return match (trim($tributacao)) {
            '1' => 'Exigível',
            '2' => 'Imunidade',
            '3' => 'Exportação',
            '4' => 'Não incidência',
            default => self::NAO_INFORMADO,
        };
    }

    private static function descricoes(Nfse $nfse): string
    {
        $partes = [];

        foreach ($nfse->itens as $item) {
            if (! $item instanceof NfseItem) {
                continue;
            }
            $texto = trim((string) $item->descricao);
            if ($texto !== '') {
                $partes[] = $texto;
            }
        }

        return $partes !== [] ? implode("\n", $partes) : self::NAO_INFORMADO;
    }

    private static function linhaLc(string $codigo, ?string $descricao): string
    {
        if ($codigo === '') {
            return self::NAO_INFORMADO;
        }

        return $descricao !== null && $descricao !== '' ? $codigo.' - '.$descricao : $codigo;
    }

    private static function linhaNbs(string $nbs, ?string $descricao): string
    {
        if ($nbs === '') {
            return self::NAO_INFORMADO;
        }

        $formatado = self::formatarNbs($nbs);
        if ($descricao === null || $descricao === '') {
            return $formatado;
        }

        return $formatado.' - '.$descricao;
    }

    private static function legendaLocal(string $codigo, string $nome, string $uf): string
    {
        $lugar = $nome;
        if ($lugar !== '' && $uf !== '' && ! str_contains(mb_strtoupper($lugar, 'UTF-8'), $uf)) {
            $lugar .= ' - '.$uf;
        }

        if ($codigo !== '' && $lugar !== '') {
            return $codigo.' - '.$lugar;
        }

        if ($codigo !== '') {
            return $codigo;
        }

        return $lugar !== '' ? $lugar : self::NAO_INFORMADO;
    }

    private static function observacoes(Nfse $nfse): string
    {
        if (! filled($nfse->ordem_servico_id)) {
            return self::NAO_INFORMADO;
        }

        try {
            $nfse->loadMissing('ordemServico');
        } catch (Throwable) {
            return self::NAO_INFORMADO;
        }

        $texto = trim((string) ($nfse->ordemServico?->observacoes ?? ''));

        return $texto !== '' ? $texto : self::NAO_INFORMADO;
    }

    /**
     * @param  array{siafi: string, nome: string, secretaria: string, texto_simples: string, vencimento_iss_dia: int}|null  $municipioLocal
     */
    private static function tributacao(string $codigoServico, bool $mesmoMunicipio, string $localNome, string $localUf, ?array $municipioLocal): string
    {
        if ($codigoServico === '') {
            return '';
        }

        if ($mesmoMunicipio) {
            return '('.$codigoServico.') Serviço tributado no município do prestador';
        }

        $lugar = $municipioLocal['nome'] ?? $localNome;
        if ($lugar === '') {
            return '';
        }

        return '('.$codigoServico.') Serviço tributado em '.($localUf !== '' ? $lugar.' - '.$localUf : $lugar);
    }

    /**
     * Parágrafos de "Outras Informações", separados por linha em branco, na ordem do DANFSe da IPM.
     *
     * @param  array{siafi: string, nome: string, secretaria: string, texto_simples: string, vencimento_iss_dia: int}|null  $municipio
     */
    private static function outras(
        ?Empresa $empresa,
        Nfse $nfse,
        ?array $municipio,
        bool $mesmoMunicipio,
        bool $simples,
        bool $retido,
        float $base,
        float $pis,
        float $cofins,
        string $codigoIbpt,
        ?string $consulta,
        string $oficial,
    ): string {
        $paragrafos = [];

        if ($simples) {
            $paragrafos[] = strtolower(trim((string) ($empresa?->regime_tributario ?? ''))) === 'mei'
                ? 'Contribuinte enquadrado como MEI.'
                : ($municipio['texto_simples'] ?? 'Contribuinte enquadrado no Simples Nacional.');
        }

        $regime = trim((string) ($empresa?->nfse_reg_esp_trib ?? ''));
        $rotuloRegime = NfseRegimeTributario::regimesEspeciais()[$regime] ?? '';
        if ($rotuloRegime !== '' && $regime !== '0') {
            $paragrafos[] = 'Regime especial de tributação: '.$rotuloRegime.'.';
        }

        $restoOficial = trim((string) preg_replace(['#https?://\S+#', '/Chave de Acesso NFS-e Nacional:\s*\d{50}/i'], '', $oficial));
        if ($restoOficial !== '') {
            $paragrafos[] = $restoOficial;
        }

        if ($consulta !== null) {
            $paragrafos[] = "A veracidade das informações declaradas na NFS-e podem ser consultadas no site:\n".$consulta;
        }

        $dia = $municipio['vencimento_iss_dia'] ?? null;
        if ($dia !== null && $mesmoMunicipio && ! $retido && trim((string) $nfse->trib_issqn) === '1' && $nfse->competencia !== null) {
            $vencimento = $nfse->competencia->copy()->startOfMonth()->addMonthNoOverflow()->day($dia);
            $paragrafos[] = 'A data de vencimento do ISS quando o mesmo for devido no município do Prestador: '.$vencimento->format('d/m/Y');
        }

        $ibpt = self::tributosAproximados($codigoIbpt, $base);
        if ($ibpt !== null) {
            $paragrafos[] = $ibpt;
        }

        $paragrafos[] = "Valor do PIS Devido: R$".self::moeda($pis)."\nValor do COFINS Devido: R$".self::moeda($cofins);

        $observacoes = self::observacoes($nfse);
        if ($observacoes !== self::NAO_INFORMADO) {
            $paragrafos[] = 'Observações: '.$observacoes;
        }

        return implode("\n\n", $paragrafos);
    }

    private static function tributosAproximados(string $codigo, float $base): ?string
    {
        if ($codigo === '' || $base <= 0) {
            return null;
        }

        try {
            $calc = app(IbptLookupService::class)->calcularParaBase($codigo, $base);
        } catch (Throwable) {
            return null;
        }

        if (! ($calc['encontrado'] ?? false)) {
            return null;
        }

        $fonte = mb_strtoupper(trim((string) ($calc['fonte'] ?? 'IBPT')), 'UTF-8');
        if ($fonte === '') {
            $fonte = 'IBPT';
        }

        return sprintf(
            'Valor aproximado dos tributos: Federais R$%s (%s%%), Estaduais R$%s (%s%%), Municipais R$%s (%s%%), com base na Lei 12.741/2012 e no Decreto 8.264/2014 - FONTE %s',
            self::moeda((float) ($calc['trib_fed'] ?? 0)),
            self::percentual((float) ($calc['aliq_federal'] ?? 0)),
            self::moeda((float) ($calc['trib_est'] ?? 0)),
            self::percentual((float) ($calc['aliq_estadual'] ?? 0)),
            self::moeda((float) ($calc['trib_mun'] ?? 0)),
            self::percentual((float) ($calc['aliq_municipal'] ?? 0)),
            $fonte,
        );
    }

    private static function descricaoNbs(string $nbs): ?string
    {
        if (strlen($nbs) < 4) {
            return null;
        }

        try {
            $item = app(IbptLookupService::class)->findByNcm($nbs);
        } catch (Throwable) {
            return null;
        }

        $descricao = trim((string) ($item?->descricao ?? ''));

        return $descricao !== '' ? $descricao : null;
    }

    private static function urlConsulta(?Empresa $empresa, Nfse $nfse, string $identificador, ?DOMDocument $xmlNfse): ?string
    {
        $noXml = self::tag($xmlNfse, 'OutrasInformacoes') ?? '';
        if (preg_match('#https?://[^\s<>"]+#', $noXml, $url) === 1) {
            return rtrim($url[0], '.,);');
        }

        $codigo = preg_replace('/\s+/', '', $identificador) ?? '';
        if ($codigo === '' || ! $empresa instanceof Empresa) {
            return null;
        }

        $homologacao = trim((string) $nfse->tipo_ambiente) === '2';
        $configurada = trim((string) ($homologacao ? $empresa->nfse_url_homologacao : $empresa->nfse_url_producao));
        $host = strtolower((string) parse_url($configurada, PHP_URL_HOST));

        if (preg_match('/^nfse-[a-z0-9-]+\.atende\.net$/', $host) !== 1) {
            return null;
        }

        return 'https://'.$host.'/autoatendimento/servicos/consulta-de-autenticidade-de-nota-fiscal-eletronica-nfse/detalhar/1/identificador/'.$codigo;
    }

    private static function optanteSimples(?Empresa $empresa): bool
    {
        return in_array(strtolower(trim((string) ($empresa?->regime_tributario ?? ''))), ['simples', 'mei'], true);
    }

    private static function aliquota(?string $xml, float $iss, float $base): string
    {
        if ($xml !== null && is_numeric(str_replace(',', '.', trim($xml)))) {
            $valor = (float) str_replace(',', '.', trim($xml));
            if ($valor > 0 && $valor < 1) {
                $valor *= 100;
            }

            return self::aliquotaTexto($valor);
        }

        if ($base > 0 && $iss > 0) {
            return self::aliquotaTexto($iss / $base * 100);
        }

        return self::aliquotaTexto(0);
    }

    private static function aliquotaTexto(float $valor): string
    {
        $texto = rtrim(number_format($valor, 4, '.', ''), '0');
        [$inteiro, $decimais] = explode('.', $texto) + [1 => ''];

        return $inteiro.'.'.str_pad($decimais, 2, '0').'%';
    }

    private static function qr(string $conteudo): ?string
    {
        try {
            $png = (new Writer(new GDLibRenderer(96, 2)))->writeString($conteudo);
        } catch (Throwable) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private static function dom(string $xml): ?DOMDocument
    {
        $xml = trim($xml);
        if ($xml === '') {
            return null;
        }

        if (str_contains($xml, '&lt;')) {
            $xml = html_entity_decode($xml, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $documento = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        $ok = $documento->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return $ok ? $documento : null;
    }

    private static function tag(?DOMDocument $documento, string $nome): ?string
    {
        if (! $documento instanceof DOMDocument) {
            return null;
        }

        $nos = (new DOMXPath($documento))->query('//*[local-name()="'.$nome.'"]');
        if ($nos === false) {
            return null;
        }

        foreach ($nos as $no) {
            if (! $no instanceof DOMElement) {
                continue;
            }
            $texto = trim($no->textContent);
            if ($texto !== '') {
                return $texto;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $pais
     */
    private static function tagPai(?DOMDocument $documento, string $nome, array $pais): ?string
    {
        if (! $documento instanceof DOMDocument) {
            return null;
        }

        $nos = (new DOMXPath($documento))->query('//*[local-name()="'.$nome.'"]');
        if ($nos === false) {
            return null;
        }

        foreach ($nos as $no) {
            if (! $no instanceof DOMElement) {
                continue;
            }
            $pai = $no->parentNode instanceof DOMElement ? $no->parentNode->localName : '';
            if (! in_array($pai, $pais, true)) {
                continue;
            }
            $texto = trim($no->textContent);
            if ($texto !== '') {
                return $texto;
            }
        }

        return null;
    }

    /**
     * @param  list<mixed>  $valores
     */
    private static function primeiroTexto(array $valores): string
    {
        foreach ($valores as $valor) {
            $texto = trim((string) ($valor ?? ''));
            if ($texto !== '' && $texto !== '-') {
                return $texto;
            }
        }

        return '';
    }

    private static function texto(mixed $valor, string $vazio): string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto !== '' ? $texto : $vazio;
    }

    private static function digitos(mixed $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }

    private static function numero(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }
        $texto = str_replace(',', '.', trim((string) $valor));

        return is_numeric($texto) ? round((float) $texto, 2) : 0.0;
    }

    private static function moeda(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    private static function percentual(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    private static function documento(mixed $valor, string $vazio = self::NAO_INFORMADO): string
    {
        $digitos = self::digitos($valor);
        if (strlen($digitos) === 14) {
            return substr($digitos, 0, 2).'.'.substr($digitos, 2, 3).'.'.substr($digitos, 5, 3).'/'.substr($digitos, 8, 4).'-'.substr($digitos, 12, 2);
        }
        if (strlen($digitos) === 11) {
            return substr($digitos, 0, 3).'.'.substr($digitos, 3, 3).'.'.substr($digitos, 6, 3).'-'.substr($digitos, 9, 2);
        }

        $texto = trim((string) $valor);

        return $texto !== '' ? $texto : $vazio;
    }

    private static function cep(mixed $valor): string
    {
        $digitos = self::digitos($valor);
        if (strlen($digitos) !== 8) {
            return trim((string) $valor) !== '' ? trim((string) $valor) : self::NAO_INFORMADO;
        }

        return substr($digitos, 0, 2).'.'.substr($digitos, 2, 3).'-'.substr($digitos, 5, 3);
    }

    private static function cepBairro(mixed $cep, mixed $bairro): string
    {
        $cepFmt = self::cep($cep);
        $bairroTexto = trim((string) $bairro);
        $partes = [];
        if ($cepFmt !== self::NAO_INFORMADO) {
            $partes[] = 'CEP: '.$cepFmt;
        }
        if ($bairroTexto !== '') {
            $partes[] = 'Bairro: '.$bairroTexto;
        }

        return $partes !== [] ? implode(' - ', $partes) : self::NAO_INFORMADO;
    }

    private static function municipioLinha(string $cidade, string $ufOuEstado): string
    {
        if ($cidade === '' && $ufOuEstado === '') {
            return self::NAO_INFORMADO;
        }
        $cidadeFmt = $cidade !== '' ? mb_strtoupper($cidade, 'UTF-8') : '';
        $ufFmt = $ufOuEstado !== '' ? mb_strtoupper($ufOuEstado, 'UTF-8') : '';
        if ($cidadeFmt === '') {
            return $ufFmt;
        }
        if ($ufFmt === '') {
            return $cidadeFmt;
        }

        return $cidadeFmt.' - '.$ufFmt;
    }

    private static function telefone(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return self::NAO_INFORMADO;
        }
        $digitos = self::digitos($texto);
        if (strlen($digitos) === 11) {
            return '('.substr($digitos, 0, 2).') '.substr($digitos, 2, 5).'-'.substr($digitos, 7, 4);
        }
        if (strlen($digitos) === 10) {
            return '('.substr($digitos, 0, 2).') '.substr($digitos, 2, 4).'-'.substr($digitos, 6, 4);
        }

        return $texto;
    }

    private static function formatarNbs(string $nbs): string
    {
        if (strlen($nbs) !== 9) {
            return $nbs;
        }

        return $nbs[0].'.'.substr($nbs, 1, 4).'.'.substr($nbs, 5, 2).'.'.substr($nbs, 7, 2);
    }

    private static function formatarCnae(string $cnae): string
    {
        if (strlen($cnae) !== 7) {
            return $cnae;
        }

        return substr($cnae, 0, 4).'-'.substr($cnae, 4, 1).'/'.substr($cnae, 5, 2);
    }

    private static function grupos(string $valor): string
    {
        $limpo = preg_replace('/\s+/', '', $valor) ?? '';

        return $limpo === '' ? '-' : trim(chunk_split($limpo, 4, ' '));
    }

    private static function igual(string $a, string $b): bool
    {
        $normal = static function (string $valor): string {
            $valor = mb_strtoupper(trim($valor), 'UTF-8');
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);

            return preg_replace('/[^A-Z0-9]/', '', is_string($ascii) && $ascii !== '' ? $ascii : $valor) ?? '';
        };

        return $normal($a) !== '' && $normal($a) === $normal($b);
    }

    private static function dataCurta(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y');
        }
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '') {
            return '-';
        }
        try {
            return \Carbon\Carbon::parse($texto)->format('d/m/Y');
        } catch (Throwable) {
            return $texto;
        }
    }

    private static function dataHora(mixed $valor): ?string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y, H:i');
        }
        $texto = trim((string) ($valor ?? ''));
        if ($texto === '' || $texto === '-') {
            return null;
        }
        try {
            $data = \Carbon\Carbon::parse($texto);
        } catch (Throwable) {
            return $texto;
        }

        $temHora = preg_match('/\d{1,2}:\d{2}/', $texto) === 1 || str_contains($texto, 'T');

        return $temHora ? $data->format('d/m/Y, H:i') : $data->format('d/m/Y');
    }

    private static function estado(string $uf): string
    {
        return [
            'AC' => 'ACRE',
            'AL' => 'ALAGOAS',
            'AP' => 'AMAPÁ',
            'AM' => 'AMAZONAS',
            'BA' => 'BAHIA',
            'CE' => 'CEARÁ',
            'DF' => 'DISTRITO FEDERAL',
            'ES' => 'ESPÍRITO SANTO',
            'GO' => 'GOIÁS',
            'MA' => 'MARANHÃO',
            'MT' => 'MATO GROSSO',
            'MS' => 'MATO GROSSO DO SUL',
            'MG' => 'MINAS GERAIS',
            'PA' => 'PARÁ',
            'PB' => 'PARAÍBA',
            'PR' => 'PARANÁ',
            'PE' => 'PERNAMBUCO',
            'PI' => 'PIAUÍ',
            'RJ' => 'RIO DE JANEIRO',
            'RN' => 'RIO GRANDE DO NORTE',
            'RS' => 'RIO GRANDE DO SUL',
            'RO' => 'RONDÔNIA',
            'RR' => 'RORAIMA',
            'SC' => 'SANTA CATARINA',
            'SP' => 'SÃO PAULO',
            'SE' => 'SERGIPE',
            'TO' => 'TOCANTINS',
        ][$uf] ?? '';
    }
}
