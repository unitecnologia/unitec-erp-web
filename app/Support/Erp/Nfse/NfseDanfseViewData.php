<?php

namespace App\Support\Erp\Nfse;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;

/**
 * Dados de exibição do DANFSe v2.0. Não altera emissão, XML nem transmissão.
 */
final class NfseDanfseViewData
{
    public const CONSULTA_PUBLICA = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=';

    public const QR_MENSAGEM = 'A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR ou pela consulta da chave de acesso no portal nacional da NFS-e';

    /**
     * @return array<string, mixed>
     */
    public static function for(Nfse $nfse, bool $autoPrint = false, bool $embedded = false): array
    {
        $xml = trim((string) ($nfse->xml_nfse ?? ''));
        if ($xml !== '') {
            return self::fromXml($nfse, $xml, $autoPrint, $embedded);
        }

        $nfse->loadMissing(['empresa', 'itens']);
        /** @var Empresa|null $empresa */
        $empresa = $nfse->empresa;
        /** @var Collection<int, NfseItem> $itens */
        $itens = $nfse->itens;

        $chave = preg_replace('/\D+/', '', (string) ($nfse->chave_acesso ?: $nfse->chave)) ?: '';
        $qrUrl = $chave !== '' ? self::CONSULTA_PUBLICA.$chave : null;

        return [
            'nfse' => $nfse,
            'empresa' => $empresa,
            'itens' => $itens,
            'autoPrint' => $autoPrint,
            'embedded' => $embedded,
            'danfse' => [
                'fonte' => 'cadastro',
                'versao' => 'DANFSe v2.0',
                'subtitulo' => 'Documento Auxiliar da NFS-e',
                'chave' => $chave !== '' ? $chave : '-',
                'chave_formatada' => $chave !== '' ? self::formatarChave($chave) : '-',
                'numero_nfse' => self::texto($nfse->numero_nfse),
                'numero_dps' => self::texto($nfse->numero_dps),
                'serie_dps' => self::texto($nfse->serie_dps ?: Nfse::SERIE_DPS),
                'competencia' => self::data($nfse->competencia),
                'dh_emissao_nfse' => self::texto($nfse->data_hora_processamento) !== '-'
                    ? self::texto($nfse->data_hora_processamento)
                    : self::dataHora($nfse->data_emissao),
                'dh_emissao_dps' => self::dataHora($nfse->data_emissao),
                'sem_validade_juridica' => self::eHomologacao($nfse),
                'qr_url' => $qrUrl,
                'qr_data_uri' => $qrUrl !== null ? self::qrDataUri($qrUrl) : null,
                'qr_mensagem' => 'A autenticidade desta NFS-e e as informações adicionais estão disponíveis no portal nacional (www.nfse.gov.br/ConsultaPublica) ou através do QR Code.',
                'emitente' => self::emitente($empresa),
                'tomador' => self::tomador($nfse),
                'servico' => self::servico($nfse, $itens),
                'municipal' => self::municipal($nfse),
                'federal' => self::federal(),
                'totais' => self::totais($nfse),
                'tributos_aproximados' => [
                    'federais' => '-',
                    'estaduais' => '-',
                    'municipais' => '-',
                ],
                'complementares' => self::complementares($nfse),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function emitente(?Empresa $empresa): array
    {
        if (! $empresa instanceof Empresa) {
            return [
                'documento' => '-',
                'im' => '-',
                'telefone' => '-',
                'nome' => '-',
                'email' => '-',
                'endereco' => '-',
                'municipio_uf' => '-',
                'cep' => '-',
                'simples' => '-',
                'regime_sn' => '-',
            ];
        }

        $endereco = trim(implode(', ', array_filter([
            trim((string) $empresa->endereco),
            trim((string) $empresa->numero),
            trim((string) $empresa->complemento),
            trim((string) $empresa->bairro),
        ], static fn ($v) => $v !== '')));

        $opSimp = NfseRegimeTributario::opSimpNac($empresa->regime_tributario);
        $simplesLabel = match ($opSimp) {
            '2' => 'MEI',
            '3' => 'Optante pelo Simples Nacional',
            default => 'Não optante',
        };

        $regAp = trim((string) ($empresa->nfse_reg_ap_trib_sn ?? ''));
        $regimeSn = NfseRegimeTributario::regimesApuracaoSimples()[$regAp] ?? ($regAp !== '' ? $regAp : '-');

        return [
            'documento' => self::texto($empresa->cnpj),
            'im' => self::texto($empresa->im),
            'telefone' => self::texto($empresa->telefone),
            'nome' => self::texto($empresa->razao_social ?: $empresa->nome ?: $empresa->fantasia),
            'email' => self::texto($empresa->email),
            'endereco' => $endereco !== '' ? $endereco : '-',
            'municipio_uf' => self::municipioUf($empresa->cidade, $empresa->uf),
            'cep' => self::texto($empresa->cep),
            'simples' => $simplesLabel,
            'regime_sn' => $regimeSn,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function tomador(Nfse $nfse): array
    {
        $endereco = trim(implode(', ', array_filter([
            trim((string) $nfse->tomador_endereco),
            trim((string) $nfse->tomador_numero),
            trim((string) $nfse->tomador_bairro),
        ], static fn ($v) => $v !== '')));

        return [
            'documento' => self::texto($nfse->tomador_cpf_cnpj),
            'im' => '-',
            'telefone' => self::texto($nfse->tomador_telefone),
            'nome' => self::texto($nfse->tomador_nome),
            'email' => self::texto($nfse->tomador_email),
            'endereco' => $endereco !== '' ? $endereco : '-',
            'municipio_uf' => self::municipioUf($nfse->tomador_cidade, $nfse->tomador_uf),
            'cep' => self::texto($nfse->tomador_cep),
        ];
    }

    /**
     * @param  Collection<int, NfseItem>  $itens
     * @return array<string, string>
     */
    private static function servico(Nfse $nfse, Collection $itens): array
    {
        $primeiro = $itens->first();
        $descricoes = $itens
            ->map(static fn (NfseItem $item) => trim($item->descricaoComServicoPrestado()))
            ->filter()
            ->values()
            ->all();

        $local = trim(implode(' / ', array_filter([
            trim((string) $nfse->municipio_prestacao_nome),
            trim((string) $nfse->municipio_prestacao_uf),
        ], static fn ($v) => $v !== '')));

        if ($local === '' && filled($nfse->municipio_prestacao_codigo)) {
            $local = (string) $nfse->municipio_prestacao_codigo;
        }

        return [
            'c_trib_nac' => self::texto($primeiro?->c_trib_nac),
            'c_trib_mun' => self::texto($primeiro?->c_trib_mun),
            'local_prestacao' => $local !== '' ? $local : '-',
            'pais_prestacao' => '-',
            'descricao' => $descricoes !== [] ? implode("\n", $descricoes) : '-',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function municipal(Nfse $nfse): array
    {
        $trib = trim((string) ($nfse->trib_issqn ?? ''));
        $ret = trim((string) ($nfse->tp_ret_issqn ?? ''));
        $regEsp = trim((string) ($nfse->empresa?->nfse_reg_esp_trib ?? ''));

        return [
            'trib_issqn' => Nfse::tributacoesIssqn()[$trib] ?? ($trib !== '' ? $trib : '-'),
            'pais_resultado' => '-',
            'municipio_incidencia' => self::texto($nfse->municipio_incidencia),
            'regime_especial' => NfseRegimeTributario::regimesEspeciais()[$regEsp] ?? ($regEsp !== '' ? $regEsp : '-'),
            'tipo_imunidade' => '-',
            'suspensao' => '-',
            'nro_processo_suspensao' => '-',
            'beneficio_municipal' => '-',
            'valor_servico' => self::money($nfse->valor_servicos),
            'desconto_incondicionado' => self::money($nfse->desconto),
            'total_deducoes' => '-',
            'calculo_bm' => '-',
            'bc_issqn' => '-',
            'aliquota' => '-',
            'retencao_issqn' => Nfse::retencoesIssqn()[$ret] ?? ($ret !== '' ? $ret : '-'),
            'issqn_apurado' => self::money($nfse->iss),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function federal(): array
    {
        return [
            'irrf' => '-',
            'cp' => '-',
            'csll' => '-',
            'pis' => '-',
            'cofins' => '-',
            'retencao_pis_cofins' => '-',
            'total' => '-',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function totais(Nfse $nfse): array
    {
        $issRetido = trim((string) ($nfse->tp_ret_issqn ?? '')) === '2'
            ? self::money($nfse->iss)
            : self::money(0);

        return [
            'valor_servico' => self::money($nfse->valor_servicos),
            'desconto_condicionado' => '-',
            'desconto_incondicionado' => self::money($nfse->desconto),
            'issqn_retido' => $issRetido,
            'irrf_cp_csll_retidos' => '-',
            'pis_cofins_retidos' => '-',
            'valor_liquido' => self::money($nfse->total),
        ];
    }

    private static function complementares(Nfse $nfse): string
    {
        $partes = [];
        if (filled($nfse->protocolo)) {
            $partes[] = 'Protocolo: '.$nfse->protocolo;
        }
        if (filled($nfse->id_dps)) {
            $partes[] = 'Id DPS: '.$nfse->id_dps;
        }
        if (filled($nfse->versao_aplicativo)) {
            $partes[] = 'Versão aplicativo: '.$nfse->versao_aplicativo;
        }

        return $partes !== [] ? implode(' | ', $partes) : '-';
    }

    private static function eHomologacao(Nfse $nfse): bool
    {
        $tp = trim((string) ($nfse->tipo_ambiente ?? ''));

        return $tp === '2';
    }

    private static function qrDataUri(string $conteudo): string
    {
        // ~1,6 cm em 96 dpi (~61 px); especificação exige mínimo 1,52 cm.
        $renderer = new GDLibRenderer(72, 4);
        $png = (new Writer($renderer))->writeString($conteudo);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private static function formatarChave(string $chave): string
    {
        $digits = preg_replace('/\D+/', '', $chave) ?: '';
        if ($digits === '') {
            return '-';
        }

        return trim(chunk_split($digits, 4, ' '));
    }

    private static function municipioUf(mixed $cidade, mixed $uf): string
    {
        $c = trim((string) $cidade);
        $u = trim((string) $uf);
        if ($c === '' && $u === '') {
            return '-';
        }
        if ($c === '') {
            return $u;
        }
        if ($u === '') {
            return $c;
        }

        return $c.' / '.$u;
    }

    private static function texto(mixed $valor): string
    {
        $t = trim((string) ($valor ?? ''));

        return $t !== '' ? $t : '-';
    }

    private static function data(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('m/Y');
        }
        $t = trim((string) ($valor ?? ''));
        if ($t === '') {
            return '-';
        }
        try {
            return \Carbon\Carbon::parse($t)->format('m/Y');
        } catch (\Throwable) {
            return $t;
        }
    }

    private static function dataHora(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y H:i:s');
        }
        $t = trim((string) ($valor ?? ''));
        if ($t === '') {
            return '-';
        }
        try {
            return \Carbon\Carbon::parse($t)->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return $t;
        }
    }

    private static function money(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '-';
        }
        $n = is_numeric($valor) ? (float) $valor : null;
        if ($n === null) {
            return '-';
        }

        return 'R$ '.number_format($n, 2, ',', '.');
    }

    /**
     * DANFSe da NFS-e autorizada. Só lê o XML já gravado.
     *
     * @return array<string, mixed>
     */
    private static function fromXml(Nfse $nfse, string $xml, bool $autoPrint, bool $embedded): array
    {
        $anterior = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $inf = null;
        if ($ok) {
            $item = (new DOMXPath($dom))->query('//*[local-name()="infNFSe"]')->item(0);
            $inf = $item instanceof DOMElement ? $item : null;
        }

        return [
            'nfse' => $nfse,
            'empresa' => null,
            'itens' => collect(),
            'autoPrint' => $autoPrint,
            'embedded' => $embedded,
            'danfse' => $inf instanceof DOMElement
                ? self::danfseXml($dom, $inf)
                : self::danfseXmlVazio(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function danfseXml(DOMDocument $dom, DOMElement $inf): array
    {
        $dps = self::filho(self::filho($inf, 'DPS'), 'infDPS');
        $prest = self::filho($dps, 'prest');
        $toma = self::filho($dps, 'toma');
        $interm = self::filho($dps, 'interm');
        $ibscbsDps = self::filho($dps, 'IBSCBS');
        $dest = self::filho($ibscbsDps, 'dest');
        $ibscbs = self::filho($inf, 'IBSCBS');
        $valores = self::filho($inf, 'valores');
        $serv = self::filho(self::filho($dps, 'serv'), 'cServ');
        $loc = self::filho(self::filho($dps, 'serv'), 'locPrest');
        $tribMun = self::filho(self::filho(self::filho($dps, 'valores'), 'trib'), 'tribMun');
        $tribFed = self::filho(self::filho(self::filho($dps, 'valores'), 'trib'), 'tribFed');
        $pis = self::filho($tribFed, 'piscofins');
        $totTrib = self::filho(self::filho(self::filho($dps, 'valores'), 'trib'), 'totTrib');
        $desc = self::filho(self::filho($dps, 'valores'), 'vDescCondIncond');
        $ded = self::filho(self::filho($dps, 'valores'), 'vDedRed');
        $reg = self::filho($prest, 'regTrib');
        $gIbs = self::filho(self::filho(self::filho($ibscbsDps, 'valores'), 'trib'), 'gIBSCBS');
        $ibsVal = self::filho($ibscbs, 'valores');
        $tot = self::filho($ibscbs, 'totCIBS');
        $gIBS = self::filho($tot, 'gIBS');
        $gCBS = self::filho($tot, 'gCBS');

        $cTribNac = self::tag($serv, 'cTribNac');
        $cLocEmi = self::tag($dps, 'cLocEmi');
        $xLocEmi = self::tag($inf, 'xLocEmi');
        $chave = self::chaveXml($inf);
        $qrUrl = strlen($chave) === 50 ? self::CONSULTA_PUBLICA.$chave : null;
        $tpAmb = self::tag($dps, 'tpAmb');
        $cStat = self::tag($inf, 'cStat');
        $tribIssqn = self::tag($tribMun, 'tribISSQN');
        $tpRetPis = self::tag($pis, 'tpRetPisCofins');
        $vPis = self::tag($pis, 'vPis');
        $vCofins = self::tag($pis, 'vCofins');
        $vRetCsll = self::tag($tribFed, 'vRetCSLL');
        $competencia = self::tag($dps, 'dCompet');
        $anoCompetencia = preg_match('/^(\d{4})/', (string) $competencia, $ano) === 1 ? (int) $ano[1] : null;

        $csll = $tpRetPis === '1'
            ? self::moneyXml(self::somaXml([$vRetCsll, $vPis, $vCofins]))
            : self::moneyXml($vRetCsll);
        $pisProprio = $tpRetPis === '1' ? self::moneyXml('0.00') : self::moneyXml($vPis);
        $cofinsProprio = $tpRetPis === '1' ? self::moneyXml('0.00') : self::moneyXml($vCofins);

        $vDr = self::tag($ded, 'vDR');
        $deducao = $vDr !== null
            ? $vDr
            : self::somaXml([self::tag($valores, 'vCalcDR'), self::tag($ibsVal, 'vCalcReeRepRes')]);
        $calculoBm = self::tag($valores, 'vCalcBM') ?? self::tag(self::filho($tribMun, 'BM'), 'vRedBCBM');

        $grupoIbs = $ibscbs instanceof DOMElement || $ibscbsDps instanceof DOMElement;

        return [
            'fonte' => 'xml',
            'logo_data_uri' => self::logoDataUri(),
            'versao' => 'DANFSe v2.0',
            'subtitulo' => 'Documento Auxiliar da NFS-e',
            'municipio' => self::municipioCabecalho($xLocEmi, $cLocEmi, $cTribNac),
            'amb_ger' => self::opcao(self::tag($inf, 'ambGer'), [
                '1' => 'Prefeitura',
                '2' => 'Sistema Nacional da NFS-e',
            ]),
            'tp_amb' => self::opcao($tpAmb, [
                '1' => 'Produção',
                '2' => 'Homologação',
            ]),
            'sem_validade_juridica' => $tpAmb === '2',
            'marca_dagua' => self::marcaDagua($dom),
            'chave' => $chave !== '' ? $chave : '-',
            'chave_formatada' => $chave !== '' ? $chave : '-',
            'numero_nfse' => self::ouTraco(self::tag($inf, 'nNFSe')),
            'numero_dps' => self::ouTraco(self::tag($dps, 'nDPS')),
            'serie_dps' => self::ouTraco(self::tag($dps, 'serie')),
            'competencia' => self::dataXml($competencia),
            'dh_emissao_nfse' => self::dataHoraXml(self::tag($inf, 'dhProc')),
            'dh_emissao_dps' => self::dataHoraXml(self::tag($dps, 'dhEmi')),
            'emitente_nfse' => self::corta(self::opcao(self::tag($dps, 'tpEmit'), [
                '1' => 'Prestador',
                '2' => 'Tomador',
                '3' => 'Intermediário',
            ]), 37),
            'situacao' => self::corta(self::opcao($cStat, [
                '100' => 'NFS-e Gerada',
                '102' => 'NFS-e de Decisão Judicial',
                '103' => 'NFS-e Avulsa',
                '107' => 'NFS-e MEI',
            ]), 37),
            'finalidade' => self::corta(self::opcao(self::tag($ibscbsDps, 'finNFSe'), [
                '0' => 'NFS-e regular',
            ]), 37),
            'qr_url' => $qrUrl,
            'qr_data_uri' => $qrUrl !== null ? self::qrDataUri($qrUrl) : null,
            'qr_mensagem' => self::QR_MENSAGEM,
            'prestador' => self::pessoa($prest, $xLocEmi, $cLocEmi, true, $reg),
            'tomador_frase' => $toma instanceof DOMElement ? null : 'TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e',
            'tomador' => self::pessoa($toma, $xLocEmi, $cLocEmi, false, null),
            'destinatario_frase' => self::fraseDestinatario($ibscbsDps, $dest),
            'destinatario' => self::pessoa($dest, $xLocEmi, $cLocEmi, false, null),
            'intermediario_frase' => $interm instanceof DOMElement ? null : 'INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e',
            'intermediario' => self::pessoa($interm, $xLocEmi, $cLocEmi, false, null),
            'servico' => [
                'codigo' => self::codigoTributacao($cTribNac, self::tag($serv, 'cTribMun')),
                'nbs' => self::formatarNbs(self::tag($serv, 'cNBS')),
                'local' => self::localPrestacao($inf, $loc),
                'descricao_codigo' => self::corta(self::ouTraco(self::tag($inf, 'xTribMun') ?? self::tag($inf, 'xTribNac')), 167),
                'descricao' => self::corta(self::ouTraco(self::tag($serv, 'xDescServ')), 1297),
            ],
            'issqn_frase' => $tribIssqn === '4' ? 'TRIBUTAÇÃO MUNICIPAL (ISSQN) - OPERAÇÃO NÃO SUJEITA AO ISSQN' : null,
            'municipal' => [
                'trib_issqn' => self::opcao($tribIssqn, [
                    '1' => 'Operação tributável',
                    '2' => 'Imunidade',
                    '3' => 'Exportação de serviço',
                    '4' => 'Não incidência',
                ]),
                'incidencia' => self::incidencia($inf, $tribMun),
                'regime_especial' => self::corta(self::opcao(self::tag($reg, 'regEspTrib'), [
                    '0' => 'Nenhum',
                    '1' => 'Ato Cooperado (Cooperativa)',
                    '2' => 'Estimativa',
                    '3' => 'Microempresa Municipal',
                    '4' => 'Notário ou Registrador',
                    '5' => 'Profissional Autônomo',
                    '6' => 'Sociedade de Profissionais',
                    '9' => 'Outros',
                ]), 27),
                'tipo_imunidade' => self::corta(self::opcao(self::tag($tribMun, 'tpImunidade'), [
                    '0' => 'Imunidade (tipo não informado na nota de origem)',
                    '1' => 'Patrimônio, renda ou serviços, uns dos outros',
                    '2' => 'Templos de qualquer culto',
                    '3' => 'Partidos políticos, entidades sindicais, educação e assistência social',
                    '4' => 'Livros, jornais, periódicos e o papel destinado a sua impressão',
                    '5' => 'Fonogramas e videofonogramas musicais',
                ]), 37),
                'suspensao' => self::corta(self::opcao(self::tag(self::filho($tribMun, 'exigSusp'), 'tpSusp'), [
                    '1' => 'Exigibilidade Suspensa por Decisão Judicial',
                    '2' => 'Exigibilidade Suspensa por Processo Administrativo',
                ]), 37),
                'nro_processo' => self::ouTraco(self::tag(self::filho($tribMun, 'exigSusp'), 'nProcesso')),
                'beneficio_municipal' => self::opcao(self::tag($valores, 'tpBM'), [
                    '1' => 'Isenção',
                    '2' => 'Redução da base de cálculo em percentual',
                    '3' => 'Redução da base de cálculo em valor',
                    '4' => 'Alíquota diferenciada',
                ]),
                'calculo_bm' => self::moneyXml($calculoBm),
                'total_deducoes' => self::moneyXml($deducao),
                'desconto_incondicionado' => self::moneyXml(self::tag($desc, 'vDescIncond')),
                'bc_issqn' => self::moneyXml(self::tag($valores, 'vBC')),
                'aliquota' => self::percentualXml(self::tag($valores, 'pAliqAplic')),
                'retencao_issqn' => self::opcao(self::tag($tribMun, 'tpRetISSQN'), [
                    '1' => 'Não Retido',
                    '2' => 'Retido pelo Tomador',
                    '3' => 'Retido pelo Intermediário',
                ]),
                'issqn_apurado' => self::moneyXml(self::tag($valores, 'vISSQN')),
            ],
            'federal_ate_2026' => $anoCompetencia === null || $anoCompetencia <= 2026,
            'federal' => [
                'irrf' => self::moneyXml(self::tag($tribFed, 'vRetIRRF')),
                'cp' => self::moneyXml(self::tag($tribFed, 'vRetCP')),
                'csll' => $csll,
                'pis' => $pisProprio,
                'cofins' => $cofinsProprio,
                'descricao_retencao' => self::corta(self::opcao($tpRetPis, [
                    '0' => 'PIS/COFINS/CSLL Não Retidos',
                    '1' => 'PIS/COFINS Retidos',
                    '2' => 'PIS/COFINS Não Retidos',
                    '3' => 'PIS/COFINS/CSLL Retidos',
                    '4' => 'PIS/COFINS Retidos, CSLL Não Retido',
                    '5' => 'PIS Retido, COFINS/CSLL Não Retido',
                    '6' => 'COFINS Retido, PIS/CSLL Não Retido',
                    '7' => 'PIS Não Retido, COFINS/CSLL Retidos',
                    '8' => 'PIS/COFINS Não Retidos, CSLL Retido',
                    '9' => 'COFINS Não Retido, PIS/CSLL Retidos',
                ]), 35),
            ],
            'ibs' => $grupoIbs ? [
                'cst' => self::juntar([
                    self::ouTraco(self::tag($gIbs, 'CST')),
                    self::ouTraco(self::tag($gIbs, 'cClassTrib')),
                ]),
                'indicador' => self::juntaIncidenciaIbs($ibscbsDps, $ibscbs),
                'exclusoes' => self::moneyXml(self::somaXml([
                    self::tag($desc, 'vDescIncond'),
                    self::tag($ibsVal, 'vCalcReeRepRes'),
                    self::tag($valores, 'vISSQN'),
                    $vPis,
                    $vCofins,
                ])),
                'bc' => self::moneyXml(self::tag($ibsVal, 'vBC')),
                'red_aliq' => self::juntar([
                    self::percentualXml(self::tag(self::filho($ibsVal, 'uf'), 'pRedAliqUF')),
                    self::percentualXml(self::tag(self::filho($ibsVal, 'mun'), 'pRedAliqMun')),
                    self::percentualXml(self::tag(self::filho($ibsVal, 'fed'), 'pRedAliqCBS')),
                ]),
                'aliq_ibs' => self::juntar([
                    self::percentualXml(self::tag(self::filho($ibsVal, 'uf'), 'pIBSUF')),
                    self::percentualXml(self::tag(self::filho($ibsVal, 'mun'), 'pIBSMun')),
                ]),
                'aliq_efet_mun' => self::percentualXml(self::tag(self::filho($ibsVal, 'mun'), 'pAliqEfetMun')),
                'valor_mun' => self::moneyXml(self::tag(self::filho($gIBS, 'gIBSMunTot'), 'vIBSMun')),
                'aliq_efet_uf' => self::percentualXml(self::tag(self::filho($ibsVal, 'uf'), 'pAliqEfetUF')),
                'valor_uf' => self::moneyXml(self::tag(self::filho($gIBS, 'gIBSUFTot'), 'vIBSUF')),
                'valor_ibs' => self::moneyXml(self::tag($gIBS, 'vIBSTot')),
                'aliq_cbs' => self::percentualXml(self::tag(self::filho($ibsVal, 'fed'), 'pCBS')),
                'aliq_efet_cbs' => self::percentualXml(self::tag(self::filho($ibsVal, 'fed'), 'pAliqEfetCBS')),
                'valor_cbs' => self::moneyXml(self::tag($gCBS, 'vCBS')),
            ] : self::ibsVazio(),
            'totais' => [
                'valor_servico' => self::moneyXml(self::tag(self::filho(self::filho($dps, 'valores'), 'vServPrest'), 'vServ')),
                'desconto_incondicionado' => self::moneyXml(self::tag($desc, 'vDescIncond')),
                'desconto_condicionado' => self::moneyXml(self::tag($desc, 'vDescCond')),
                'total_retencoes' => self::moneyXml(self::tag($valores, 'vTotalRet')),
                'valor_liquido' => self::moneyXml(self::tag($valores, 'vLiq')),
                'total_ibs_cbs' => self::moneyXml(self::somaXml([
                    self::tag($gIBS, 'vIBSTot'),
                    self::tag($gCBS, 'vCBS'),
                ])),
                'valor_liquido_ibs' => self::moneyXml(self::tag($tot, 'vTotNF')),
            ],
            'complementares' => self::complementaresXml($inf, $dps, $totTrib),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function danfseXmlVazio(): array
    {
        $pessoa = self::pessoa(null, null, null, false, null);
        $traco = '-';

        return [
            'fonte' => 'xml',
            'logo_data_uri' => self::logoDataUri(),
            'versao' => 'DANFSe v2.0',
            'subtitulo' => 'Documento Auxiliar da NFS-e',
            'municipio' => $traco,
            'amb_ger' => $traco,
            'tp_amb' => $traco,
            'sem_validade_juridica' => false,
            'marca_dagua' => null,
            'chave' => $traco,
            'chave_formatada' => $traco,
            'numero_nfse' => $traco,
            'numero_dps' => $traco,
            'serie_dps' => $traco,
            'competencia' => $traco,
            'dh_emissao_nfse' => $traco,
            'dh_emissao_dps' => $traco,
            'emitente_nfse' => $traco,
            'situacao' => $traco,
            'finalidade' => $traco,
            'qr_url' => null,
            'qr_data_uri' => null,
            'qr_mensagem' => self::QR_MENSAGEM,
            'prestador' => self::pessoa(null, null, null, true, null),
            'tomador_frase' => 'TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e',
            'tomador' => $pessoa,
            'destinatario_frase' => 'DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e',
            'destinatario' => $pessoa,
            'intermediario_frase' => 'INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e',
            'intermediario' => $pessoa,
            'servico' => [
                'codigo' => $traco,
                'nbs' => $traco,
                'local' => $traco,
                'descricao_codigo' => $traco,
                'descricao' => $traco,
            ],
            'issqn_frase' => null,
            'municipal' => [
                'trib_issqn' => $traco,
                'incidencia' => $traco,
                'regime_especial' => $traco,
                'tipo_imunidade' => $traco,
                'suspensao' => $traco,
                'nro_processo' => $traco,
                'beneficio_municipal' => $traco,
                'calculo_bm' => $traco,
                'total_deducoes' => $traco,
                'desconto_incondicionado' => $traco,
                'bc_issqn' => $traco,
                'aliquota' => $traco,
                'retencao_issqn' => $traco,
                'issqn_apurado' => $traco,
            ],
            'federal_ate_2026' => true,
            'federal' => [
                'irrf' => $traco,
                'cp' => $traco,
                'csll' => $traco,
                'pis' => $traco,
                'cofins' => $traco,
                'descricao_retencao' => $traco,
            ],
            'ibs' => self::ibsVazio(),
            'totais' => [
                'valor_servico' => $traco,
                'desconto_incondicionado' => $traco,
                'desconto_condicionado' => $traco,
                'total_retencoes' => $traco,
                'valor_liquido' => $traco,
                'total_ibs_cbs' => $traco,
                'valor_liquido_ibs' => $traco,
            ],
            'complementares' => 'Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: Federais: - ; Estaduais: - ; Municipais: -',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function ibsVazio(): array
    {
        return [
            'cst' => '-',
            'indicador' => '-',
            'exclusoes' => '-',
            'bc' => '-',
            'red_aliq' => '-',
            'aliq_ibs' => '-',
            'aliq_efet_mun' => '-',
            'valor_mun' => '-',
            'aliq_efet_uf' => '-',
            'valor_uf' => '-',
            'valor_ibs' => '-',
            'aliq_cbs' => '-',
            'aliq_efet_cbs' => '-',
            'valor_cbs' => '-',
        ];
    }

    private static function marcaDagua(DOMDocument $dom): ?string
    {
        $substituida = false;
        $cancelada = false;
        $xp = new DOMXPath($dom);

        foreach ($xp->query('//*[local-name()="tpEvento" or local-name()="descEvento" or local-name()="xDesc"]') ?: [] as $no) {
            if (! $no instanceof DOMElement) {
                continue;
            }
            $valor = self::textoDireto($no);
            $codigo = strtolower(preg_replace('/\s+/', '', $valor) ?? '');
            if (in_array($codigo, ['105102', 'e105102'], true) || str_contains($valor, 'Cancelamento de NFS-e por Substituição')) {
                $substituida = true;
            }
            if (in_array($codigo, ['101101', 'e101101', '105104', 'e105104', '305101', 'e305101'], true)) {
                $cancelada = true;
            }
            if (str_contains($valor, 'Cancelamento de NFS-e')
                && ! str_contains($valor, 'Substituição')
                && ! str_contains($valor, 'Indeferido')
                && ! str_contains($valor, 'Solicitação')) {
                $cancelada = true;
            }
        }

        if ($substituida) {
            return 'SUBSTITUÍDA';
        }
        if ($cancelada) {
            return 'CANCELADA';
        }

        $inf = $xp->query('//*[local-name()="infNFSe"]')->item(0);
        if ($inf instanceof DOMElement && self::tag($inf, 'cStat') === '101') {
            return 'CANCELADA';
        }

        return null;
    }

    private static function fraseDestinatario(?DOMElement $ibscbsDps, ?DOMElement $dest): ?string
    {
        $ind = self::tag($ibscbsDps, 'indDest');
        if ($ind === '0') {
            return 'O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO';
        }
        if ($dest instanceof DOMElement) {
            return null;
        }

        return 'DESTINATÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e';
    }

    /**
     * @return array<string, string>
     */
    private static function pessoa(?DOMElement $pessoa, ?string $xLocEmi, ?string $cLocEmi, bool $prestador, ?DOMElement $reg): array
    {
        $end = self::filho($pessoa, 'end');
        $dados = [
            'documento' => self::documento($pessoa),
            'im' => self::ouTraco(self::tag($pessoa, 'IM')),
            'telefone' => self::ouTraco(self::tag($pessoa, 'fone')),
            'nome' => self::corta(self::ouTraco(self::tag($pessoa, 'xNome')), 77),
            'email' => self::corta(self::ouTraco(self::tag($pessoa, 'email')), 80),
            'endereco' => self::corta(self::endereco($end), 77),
            'municipio_uf' => self::municipioPessoa($end, $xLocEmi, $cLocEmi),
            'ibge_cep' => self::ibgeCep($end),
        ];

        if ($prestador) {
            $dados['simples'] = self::corta(self::opcao(self::tag($reg, 'opSimpNac'), [
                '1' => 'Não Optante',
                '2' => 'Optante - MEI',
                '3' => 'Optante - ME/EPP',
            ]), 37);
            $dados['regime_sn'] = self::corta(self::opcao(self::tag($reg, 'regApTribSN'), [
                '1' => 'Regime de apuração dos tributos federais e municipal pelo Simples Nacional',
                '2' => 'Regime de apuração dos tributos federais pelo Simples Nacional e ISSQN por fora do SN',
                '3' => 'Regime de apuração dos tributos federais e municipal por fora do Simples Nacional',
            ]), 77);
        }

        return $dados;
    }

    private static function documento(?DOMElement $pessoa): string
    {
        $cnpj = self::tag($pessoa, 'CNPJ');
        if ($cnpj !== null) {
            $d = preg_replace('/\D+/', '', $cnpj) ?: $cnpj;

            return strlen($d) === 14
                ? substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'.substr($d, 8, 4).'-'.substr($d, 12, 2)
                : $cnpj;
        }
        $cpf = self::tag($pessoa, 'CPF');
        if ($cpf !== null) {
            $d = preg_replace('/\D+/', '', $cpf) ?: $cpf;

            return strlen($d) === 11
                ? substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2)
                : $cpf;
        }

        return self::ouTraco(self::tag($pessoa, 'NIF') ?? self::tag($pessoa, 'cNaoNIF'));
    }

    private static function endereco(?DOMElement $end): string
    {
        if (! $end instanceof DOMElement) {
            return '-';
        }
        $partes = array_values(array_filter([
            self::tag($end, 'xLgr'),
            self::tag($end, 'nro'),
            self::tag($end, 'xCpl'),
            self::tag($end, 'xBairro'),
        ], static fn (?string $v): bool => $v !== null && $v !== ''));

        return $partes !== [] ? implode(', ', $partes) : '-';
    }

    private static function municipioPessoa(?DOMElement $end, ?string $xLocEmi, ?string $cLocEmi): string
    {
        $nac = self::filho($end, 'endNac');
        if ($nac instanceof DOMElement) {
            $cMun = self::tag($nac, 'cMun');
            $nome = ($cMun !== null && $cMun === $cLocEmi && $xLocEmi !== null) ? $xLocEmi : $cMun;
            $uf = self::ufIbge($cMun);

            return self::juntar([self::ouTraco($nome), self::ouTraco($uf)]);
        }
        $ext = self::filho($end, 'endExt');
        if ($ext instanceof DOMElement) {
            return self::juntar([
                self::ouTraco(self::tag($ext, 'xCidade')),
                self::ouTraco(self::tag($ext, 'xEstProvReg')),
            ]);
        }

        return '-';
    }

    private static function ibgeCep(?DOMElement $end): string
    {
        $nac = self::filho($end, 'endNac');
        if ($nac instanceof DOMElement) {
            return self::juntar([
                self::ouTraco(self::tag($nac, 'cMun')),
                self::cep(self::tag($nac, 'CEP')),
            ]);
        }
        $ext = self::filho($end, 'endExt');
        if ($ext instanceof DOMElement) {
            return self::juntar([
                self::ouTraco(self::tag($ext, 'cPais')),
                self::ouTraco(self::tag($ext, 'cEndPost')),
            ]);
        }

        return '-';
    }

    private static function municipioCabecalho(?string $xLocEmi, ?string $cLocEmi, ?string $cTribNac): string
    {
        $item = preg_replace('/\D+/', '', (string) $cTribNac) ?: '';
        if (str_starts_with($item, '99')) {
            return '';
        }
        $uf = self::ufIbge($cLocEmi);

        return 'Município: '.self::ouTraco($xLocEmi).' / '.self::ouTraco($uf);
    }

    private static function localPrestacao(DOMElement $inf, ?DOMElement $loc): string
    {
        $codigo = self::tag($loc, 'cLocPrestacao');
        $nome = self::tag($inf, 'xLocPrestacao') ?? $codigo;
        $pais = self::tag($loc, 'cPaisPrestacao');

        return self::juntar([
            self::ouTraco($nome),
            self::ouTraco(self::ufIbge($codigo)),
            self::ouTraco($pais),
        ]);
    }

    private static function incidencia(DOMElement $inf, ?DOMElement $tribMun): string
    {
        $codigo = self::tag($inf, 'cLocIncid');

        return self::juntar([
            self::ouTraco(self::tag($inf, 'xLocIncid') ?? $codigo),
            self::ouTraco(self::ufIbge($codigo)),
            self::ouTraco(self::tag($tribMun, 'cPaisResult')),
        ]);
    }

    private static function juntaIncidenciaIbs(?DOMElement $ibscbsDps, ?DOMElement $ibscbs): string
    {
        $codigo = self::tag($ibscbs, 'cLocalidadeIncid');

        return self::juntar([
            self::ouTraco(self::tag($ibscbsDps, 'cIndOp')),
            self::ouTraco($codigo),
            self::ouTraco(self::tag($ibscbs, 'xLocalidadeIncid')),
            self::ouTraco(self::ufIbge($codigo)),
        ]);
    }

    private static function complementaresXml(DOMElement $inf, ?DOMElement $dps, ?DOMElement $totTrib): string
    {
        $info = self::filho(self::filho($dps, 'serv'), 'infoCompl');
        $obra = self::filho(self::filho($dps, 'serv'), 'obra');
        $imovel = self::filho(self::filho($dps, 'IBSCBS'), 'imovel');
        $evento = self::filho(self::filho($dps, 'serv'), 'atvEvento');
        $itens = [];
        $ped = self::filho($info, 'gItemPed');
        if ($ped instanceof DOMElement) {
            foreach ($ped->childNodes as $child) {
                if ($child instanceof DOMElement && $child->localName === 'xItemPed') {
                    $item = trim($child->textContent);
                    if ($item !== '') {
                        $itens[] = $item;
                    }
                }
            }
        }
        $inscricoes = array_values(array_unique(array_filter([
            self::tag($obra, 'inscImobFisc'),
            self::tag($imovel, 'inscImobFisc'),
        ])));

        $partes = [];
        self::push($partes, 'Inf. Cont.: ', self::tag($info, 'xInfComp'));
        self::push($partes, 'NFS-e Subst.: ', self::tag(self::filho($dps, 'subst'), 'chSubstda'));
        self::push($partes, 'Doc. Ref.: ', self::tag($info, 'docRef'));
        self::push($partes, 'Cod. Obra: ', self::tag($obra, 'cObra'));
        self::push($partes, 'Insc. Imob.: ', $inscricoes !== [] ? implode(', ', $inscricoes) : null);
        self::push($partes, 'Cod. Evt.: ', self::tag($evento, 'idAtvEvt'));
        self::push($partes, 'Doc. Tec.: ', self::tag($info, 'idDocTec'));
        self::push($partes, 'Núm. Ped.: ', self::tag($info, 'xPed'));
        self::push($partes, 'Item Ped.: ', $itens !== [] ? implode(', ', $itens) : null);
        self::push($partes, 'Inf. A. T. Mun.: ', self::tag($inf, 'xOutInf'));

        $texto = implode(' | ', $partes);
        if (mb_strlen($texto) > 1997) {
            $texto = mb_substr($texto, 0, 1994).'...';
        }
        $lei = self::linhaTributosAproximados($totTrib);

        return $texto !== '' ? $texto.' | '.$lei : $lei;
    }

    private static function linhaTributosAproximados(?DOMElement $totTrib): string
    {
        $prefixo = 'Totais Aproximados dos Tributos cfe. Lei nº 12.741/2012: ';
        $monet = self::filho($totTrib, 'vTotTrib');
        if ($monet instanceof DOMElement) {
            return $prefixo.'Federais: '.self::moneyXml(self::tag($monet, 'vTotTribFed'))
                .' ; Estaduais: '.self::moneyXml(self::tag($monet, 'vTotTribEst'))
                .' ; Municipais: '.self::moneyXml(self::tag($monet, 'vTotTribMun'));
        }
        $perc = self::filho($totTrib, 'pTotTrib');
        if ($perc instanceof DOMElement) {
            return $prefixo.'Federais: '.self::percentualXml(self::tag($perc, 'pTotTribFed'))
                .' ; Estaduais: '.self::percentualXml(self::tag($perc, 'pTotTribEst'))
                .' ; Municipais: '.self::percentualXml(self::tag($perc, 'pTotTribMun'));
        }
        $sn = self::tag($totTrib, 'pTotTribSN');
        if ($sn !== null) {
            return $prefixo.'Simples Nacional: '.self::percentualXml($sn);
        }

        return $prefixo.'Federais: - ; Estaduais: - ; Municipais: -';
    }

    /**
     * @param  list<string>  $partes
     */
    private static function push(array &$partes, string $rotulo, ?string $valor): void
    {
        if ($valor === null || trim($valor) === '') {
            return;
        }
        $partes[] = $rotulo.trim($valor);
    }

    private static function chaveXml(DOMElement $inf): string
    {
        $id = trim($inf->getAttribute('Id'));
        if ($id === '') {
            $id = trim($inf->getAttribute('id'));
        }

        return preg_replace('/\D+/', '', $id) ?: '';
    }

    private static function codigoTributacao(?string $nacional, ?string $municipal): string
    {
        $n = preg_replace('/\D+/', '', (string) $nacional) ?: '';
        $nacionalFmt = strlen($n) === 6
            ? substr($n, 0, 2).'.'.substr($n, 2, 2).'.'.substr($n, 4, 2)
            : ($nacional !== null && trim($nacional) !== '' ? trim($nacional) : '-');
        $municipalFmt = $municipal !== null && trim($municipal) !== '' ? trim($municipal) : '-';
        if ($nacionalFmt === '-' && $municipalFmt === '-') {
            return '-';
        }

        return $nacionalFmt.' / '.$municipalFmt;
    }

    private static function formatarNbs(?string $nbs): string
    {
        if ($nbs === null || trim($nbs) === '') {
            return '-';
        }
        $d = preg_replace('/\D+/', '', $nbs) ?: '';
        if (strlen($d) !== 9) {
            return trim($nbs);
        }

        return $d[0].'.'.substr($d, 1, 4).'.'.substr($d, 5, 2).'.'.substr($d, 7, 2);
    }

    private static function cep(?string $cep): string
    {
        if ($cep === null || trim($cep) === '') {
            return '-';
        }
        $d = preg_replace('/\D+/', '', $cep) ?: '';
        if (strlen($d) !== 8) {
            return trim($cep);
        }

        return substr($d, 0, 2).'.'.substr($d, 2, 3).'-'.substr($d, 5, 3);
    }

    private static function ufIbge(?string $codigo): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $codigo) ?: '';
        if (strlen($d) < 2) {
            return null;
        }

        return [
            '11' => 'RO', '12' => 'AC', '13' => 'AM', '14' => 'RR', '15' => 'PA', '16' => 'AP', '17' => 'TO',
            '21' => 'MA', '22' => 'PI', '23' => 'CE', '24' => 'RN', '25' => 'PB', '26' => 'PE', '27' => 'AL',
            '28' => 'SE', '29' => 'BA', '31' => 'MG', '32' => 'ES', '33' => 'RJ', '35' => 'SP', '41' => 'PR',
            '42' => 'SC', '43' => 'RS', '50' => 'MS', '51' => 'MT', '52' => 'GO', '53' => 'DF',
        ][substr($d, 0, 2)] ?? null;
    }

    /**
     * @param  array<string, string>  $opcoes
     */
    private static function opcao(?string $codigo, array $opcoes): string
    {
        if ($codigo === null || trim($codigo) === '') {
            return '-';
        }
        $codigo = trim($codigo);

        return $opcoes[$codigo] ?? $codigo;
    }

    /**
     * @param  list<string|null>  $valores
     */
    private static function somaXml(array $valores): ?string
    {
        $total = null;
        foreach ($valores as $valor) {
            if ($valor === null || trim($valor) === '' || ! is_numeric(trim($valor))) {
                continue;
            }
            $total = $total === null ? bcadd(trim($valor), '0', 2) : bcadd($total, trim($valor), 2);
        }

        return $total;
    }

    /**
     * @param  list<string>  $partes
     */
    private static function juntar(array $partes): string
    {
        if ($partes === [] || ! in_array(true, array_map(static fn (string $p): bool => $p !== '-', $partes), true)) {
            return '-';
        }

        return implode(' / ', $partes);
    }

    private static function moneyXml(?string $valor): string
    {
        if ($valor === null || trim($valor) === '' || ! is_numeric(trim($valor))) {
            return '-';
        }

        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    private static function percentualXml(?string $valor): string
    {
        if ($valor === null || trim($valor) === '' || ! is_numeric(trim($valor))) {
            return '-';
        }

        return number_format((float) $valor, 2, ',', '.').'%';
    }

    private static function dataXml(?string $valor): string
    {
        $valor = trim((string) $valor);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m) === 1) {
            return $m[3].'/'.$m[2].'/'.$m[1];
        }

        return $valor !== '' ? $valor : '-';
    }

    private static function dataHoraXml(?string $valor): string
    {
        $valor = trim((string) $valor);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/', $valor, $m) === 1) {
            return $m[3].'/'.$m[2].'/'.$m[1].' '.$m[4].':'.$m[5].':'.$m[6];
        }

        return $valor !== '' ? $valor : '-';
    }

    private static function ouTraco(?string $valor): string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : '-';
    }

    private static function corta(string $texto, int $max): string
    {
        if ($texto === '-' || mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, max(1, $max - 3)).'...';
    }

    private static function filho(?DOMElement $pai, string $nome): ?DOMElement
    {
        if (! $pai instanceof DOMElement) {
            return null;
        }
        foreach ($pai->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $nome) {
                return $child;
            }
        }

        return null;
    }

    private static function tag(?DOMElement $pai, string $nome): ?string
    {
        $el = self::filho($pai, $nome);
        if (! $el instanceof DOMElement) {
            return null;
        }
        $texto = self::textoDireto($el);

        return $texto !== '' ? $texto : null;
    }

    private static function textoDireto(DOMElement $el): string
    {
        $texto = '';
        foreach ($el->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $texto .= $child->data;
            }
        }

        return trim($texto);
    }

    private static function logoDataUri(): ?string
    {
        $path = public_path('img/erp/nfse-logo-horizontal.png');
        if (! is_file($path)) {
            return null;
        }
        $bin = file_get_contents($path);
        if (! is_string($bin) || $bin === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($bin);
    }
}
