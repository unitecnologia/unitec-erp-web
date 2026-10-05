<?php

namespace App\Support\Erp\Nfse\Ipm;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use App\Support\Erp\Nfse\NfseObra;
use DOMDocument;
use DOMElement;

/**
 * GerarNfseEnvio na ordem do abrasf.xsd atual da IPM (ABRASF 2.04 + extensões), só com dados já gravados na NFS-e.
 * Regras de alíquota/ISS/incidência: NTE 123/2025 v1.6, item 4.1. IBS/CBS não é gerado (não se aplica ao Simples).
 */
class NfseIpmXmlGerador
{
    public const NS = 'http://www.abrasf.org.br/nfse.xsd';

    /**
     * Regime especial Nacional (empresas.nfse_reg_esp_trib) => tsRegimeEspecialTributacao ABRASF.
     *
     * @var array<string, string>
     */
    private const REGIME_ESPECIAL = [
        '1' => '4',
        '2' => '2',
        '3' => '1',
        '6' => '3',
    ];

    /**
     * Subitens LC 116/2003 cujo ISS não é devido no estabelecimento do prestador (art. 3º, incisos II a XXV),
     * além dos itens 12 (exceto 12.13) e 20. Para eles a incidência segue o município informado na NFS-e;
     * nos demais, o município do prestador.
     *
     * @var list<string>
     */
    private const INCIDENCIA_FORA_DO_PRESTADOR = [
        '0305', '0422', '0423', '0509', '0702', '0704', '0705', '0709', '0710', '0711', '0712', '0716', '0717',
        '0718', '0719', '1004', '1101', '1102', '1104', '1105', '1501', '1509', '1601', '1602', '1705', '1710',
    ];

    public function gerar(Nfse $nfse, bool $envioTeste = false): string
    {
        $nfse->loadMissing(['empresa', 'itens']);
        $empresa = $nfse->empresa;

        if (! $empresa instanceof Empresa) {
            throw new NfseNaoTransmitida('Empresa da NFS-e não encontrada.');
        }

        $item = $nfse->itens->first();

        if (! $item instanceof NfseItem) {
            throw new NfseNaoTransmitida('Informe ao menos um serviço.');
        }

        $this->exigirServicoUnico($nfse);

        $prestador = $this->digitos($empresa->cnpj);

        if (strlen($prestador) !== 14) {
            throw new NfseNaoTransmitida('Informe o CNPJ da empresa.');
        }

        $im = trim((string) $empresa->im);

        if ($im === '') {
            throw new NfseNaoTransmitida('Informe a inscrição municipal da empresa.');
        }

        $numero = (int) $nfse->numero_dps;

        if ($numero < 1) {
            throw new NfseNaoTransmitida('A NFS-e ainda não tem número de RPS.');
        }

        if ($nfse->competencia === null) {
            throw new NfseNaoTransmitida('Informe a competência da NFS-e.');
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $raiz = $doc->createElementNS(self::NS, 'GerarNfseEnvio');
        $doc->appendChild($raiz);

        if ($envioTeste) {
            $this->campo($doc, $raiz, 'EnvioTeste', '1');
        }

        // Como nos exemplos oficiais da IPM, a identificação do RPS não é enviada: a IPM numera a NFS-e
        // e recusa séries de RPS não autorizadas (E97).
        $rps = $doc->createElementNS(self::NS, 'Rps');
        $raiz->appendChild($rps);
        $inf = $doc->createElementNS(self::NS, 'InfDeclaracaoPrestacaoServico');
        $inf->setAttribute('Id', 'RPS_'.$numero);
        $rps->appendChild($inf);

        $this->campo($doc, $inf, 'Competencia', $this->competencia($nfse));
        $inf->appendChild($this->servico($doc, $nfse, $empresa, $item));
        $inf->appendChild($this->prestador($doc, $prestador, $im));

        $tomador = $this->tomador($doc, $nfse);

        if ($tomador !== null) {
            $inf->appendChild($tomador);
        }

        $construcao = $this->construcaoCivil($doc, $item);

        if ($construcao !== null) {
            $inf->appendChild($construcao);
        }

        $this->campo($doc, $inf, 'RegimeEspecialTributacao', $this->regimeEspecial($empresa));
        $this->campo($doc, $inf, 'OptanteSimplesNacional', $this->optanteSimples($empresa) ? '1' : '2');
        $this->campo($doc, $inf, 'IncentivoFiscal', '2');

        return $doc->saveXML() ?: '';
    }

    /**
     * Sem RPS no XML, a IPM recusa competência anterior à data de emissão (L1028).
     */
    private function competencia(Nfse $nfse): string
    {
        $hoje = now('America/Sao_Paulo')->startOfDay();
        $competencia = $nfse->competencia->copy()->startOfDay();

        if ($competencia->format('Y-m') === $hoje->format('Y-m')) {
            return $competencia->max($hoje)->format('Y-m-d');
        }

        if ($competencia->lessThan($hoje)) {
            throw new NfseNaoTransmitida('A IPM não aceita competência retroativa ('.$competencia->format('m/Y').'). Ajuste a competência para o mês atual.');
        }

        return $competencia->format('Y-m-d');
    }

    private function servico(DOMDocument $doc, Nfse $nfse, Empresa $empresa, NfseItem $item): DOMElement
    {
        $itemLista = $this->itemLista((string) $item->c_trib_nac);

        if ($itemLista === null) {
            throw new NfseNaoTransmitida('Informe o código de tributação nacional do serviço com 6 dígitos.');
        }

        $nbs = $this->digitos($item->c_nbs);

        if (strlen($nbs) !== 9) {
            throw new NfseNaoTransmitida('Informe o código NBS do serviço com 9 dígitos.');
        }

        $municipio = $this->digitos($nfse->municipio_prestacao_codigo);

        if (strlen($municipio) !== 7) {
            throw new NfseNaoTransmitida('Falta o código IBGE do município da prestação.');
        }

        $exigibilidade = $this->exigibilidade((string) $nfse->trib_issqn);
        $retido = in_array((string) $nfse->tp_ret_issqn, ['2', '3'], true);
        $incidencia = in_array($exigibilidade, ['1', '3', '5', '6', '7'], true)
            ? $this->municipioIncidencia($empresa, (string) $item->c_trib_nac, $municipio)
            : null;
        $issInformado = $this->issInformadoPeloContribuinte($empresa, $nfse, $exigibilidade, $incidencia);

        $servico = $doc->createElementNS(self::NS, 'Servico');
        $valores = $doc->createElementNS(self::NS, 'Valores');
        $this->campo($doc, $valores, 'ValorServicos', $this->decimal($nfse->valor_servicos));
        $desconto = $this->decimal($nfse->desconto);

        if ($issInformado !== null) {
            $this->campo($doc, $valores, 'ValorIss', $issInformado['valor']);
            $this->campo($doc, $valores, 'Aliquota', $issInformado['aliquota']);
        }

        if (bccomp($desconto, '0', 2) === 1) {
            $this->campo($doc, $valores, 'DescontoIncondicionado', $desconto);
        }

        $servico->appendChild($valores);
        $this->campo($doc, $servico, 'IssRetido', $retido ? '1' : '2');
        $this->campo($doc, $servico, 'ResponsavelRetencao', $this->responsavelRetencao((string) $nfse->tp_ret_issqn));
        $this->campo($doc, $servico, 'ItemListaServico', $itemLista);

        $cnae = $this->digitos($empresa->cnae);

        if (strlen($cnae) === 7) {
            $this->campo($doc, $servico, 'CodigoCnae', $cnae);
        }

        $this->campo($doc, $servico, 'CodigoTributacaoMunicipio', mb_substr(trim((string) $item->c_trib_mun), 0, 20));
        $this->campo($doc, $servico, 'CodigoNbs', $nbs);
        $this->campo($doc, $servico, 'Discriminacao', $this->discriminacao($nfse));
        $this->campo($doc, $servico, 'CodigoMunicipio', $municipio);
        $this->campo($doc, $servico, 'ExigibilidadeISS', $exigibilidade);
        $this->campo($doc, $servico, 'MunicipioIncidencia', $incidencia);

        $obra = $this->obra($doc, $item, $municipio);

        if ($obra !== null) {
            $servico->appendChild($obra);
        }

        return $servico;
    }

    private function municipioIncidencia(Empresa $empresa, string $cTribNac, string $prestacao): string
    {
        $sede = $this->digitos($empresa->cidade_codigo);
        $subitem = substr($this->digitos($cTribNac), 0, 4);
        $foraDoPrestador = in_array($subitem, self::INCIDENCIA_FORA_DO_PRESTADOR, true)
            || (str_starts_with($subitem, '12') && $subitem !== '1213')
            || str_starts_with($subitem, '20');

        return $foraDoPrestador || strlen($sede) !== 7 ? $prestacao : $sede;
    }

    /**
     * Alíquota e ValorIss só podem ir quando o contribuinte os informa (NTE 4.1: Simples Nacional ou ISS devido fora
     * do município do prestador); fora desses casos o IPM rejeita a alíquota.
     *
     * @return array{aliquota: string, valor: string}|null
     */
    private function issInformadoPeloContribuinte(Empresa $empresa, Nfse $nfse, string $exigibilidade, ?string $incidencia): ?array
    {
        if ($exigibilidade !== '1') {
            return null;
        }

        $sede = $this->digitos($empresa->cidade_codigo);
        $simples = $this->optanteSimples($empresa);
        $foraDoPrestador = $incidencia !== null && strlen($sede) === 7 && $incidencia !== $sede;

        if (! $simples && ! $foraDoPrestador) {
            return null;
        }

        $aliquota = $this->decimal($nfse->aliquota_iss);

        if (bccomp($aliquota, '0', 2) !== 1 || bccomp($aliquota, '99.99', 2) === 1) {
            throw new NfseNaoTransmitida($simples
                ? 'No IPM, empresa do Simples Nacional deve informar a alíquota do ISS do Simples (faixa do PGDAS-D). Informe a alíquota na aba ISS da NFS-e.'
                : 'No IPM, ISS devido fora do município do prestador exige a alíquota do ISS do município de incidência. Informe a alíquota na aba ISS da NFS-e.');
        }

        $base = bcsub($this->decimal($nfse->valor_servicos), $this->decimal($nfse->desconto), 2);

        if (bccomp($base, '0', 2) !== 1) {
            throw new NfseNaoTransmitida('A base de cálculo do ISS deve ser maior que zero.');
        }

        $valor = bcadd(bcdiv(bcmul($base, $aliquota, 6), '100', 6), '0.005', 2);

        return ['aliquota' => $aliquota, 'valor' => $valor];
    }

    private function prestador(DOMDocument $doc, string $cnpj, string $im): DOMElement
    {
        $prestador = $doc->createElementNS(self::NS, 'Prestador');
        $documento = $doc->createElementNS(self::NS, 'CpfCnpj');
        $this->campo($doc, $documento, 'Cnpj', $cnpj);
        $prestador->appendChild($documento);
        $this->campo($doc, $prestador, 'InscricaoMunicipal', $im);

        return $prestador;
    }

    private function tomador(DOMDocument $doc, Nfse $nfse): ?DOMElement
    {
        $nome = trim((string) $nfse->tomador_nome);
        $documento = $this->digitos($nfse->tomador_cpf_cnpj);
        $identificado = strlen($documento) === 11 || strlen($documento) === 14;

        if ($nome === '' && ! $identificado) {
            return null;
        }

        if ($nome === '') {
            throw new NfseNaoTransmitida('Informe o nome do tomador.');
        }

        $tomador = $doc->createElementNS(self::NS, 'TomadorServico');

        if ($identificado) {
            $identificacao = $doc->createElementNS(self::NS, 'IdentificacaoTomador');
            $cpfCnpj = $doc->createElementNS(self::NS, 'CpfCnpj');
            $this->campo($doc, $cpfCnpj, strlen($documento) === 14 ? 'Cnpj' : 'Cpf', $documento);
            $identificacao->appendChild($cpfCnpj);
            $tomador->appendChild($identificacao);
        }

        $this->campo($doc, $tomador, 'RazaoSocial', mb_substr($nome, 0, 150));

        $endereco = $this->endereco($doc, $nfse, $identificado);

        if ($endereco !== null) {
            $tomador->appendChild($endereco);
        }

        $contato = $this->contato($doc, $nfse);

        if ($contato !== null) {
            $tomador->appendChild($contato);
        }

        return $tomador;
    }

    private function endereco(DOMDocument $doc, Nfse $nfse, bool $obrigatorio): ?DOMElement
    {
        $logradouro = trim((string) $nfse->tomador_endereco);
        $bairro = trim((string) $nfse->tomador_bairro);
        $municipio = $this->digitos($nfse->tomador_cidade_codigo);
        $uf = strtoupper(trim((string) $nfse->tomador_uf));
        $cep = $this->digitos($nfse->tomador_cep);

        if ($logradouro === '' && $bairro === '' && $cep === '' && ! $obrigatorio) {
            return null;
        }

        if ($logradouro === '' || $bairro === '' || strlen($municipio) !== 7 || strlen($uf) !== 2 || strlen($cep) !== 8) {
            throw new NfseNaoTransmitida('Complete o endereço do tomador: logradouro, bairro, município, UF e CEP com 8 dígitos.');
        }

        $endereco = $doc->createElementNS(self::NS, 'Endereco');
        $this->campo($doc, $endereco, 'Endereco', mb_substr($logradouro, 0, 255));
        $this->campo($doc, $endereco, 'Numero', mb_substr(trim((string) $nfse->tomador_numero) ?: 'S/N', 0, 60));
        $this->campo($doc, $endereco, 'Bairro', mb_substr($bairro, 0, 60));
        $this->campo($doc, $endereco, 'CodigoMunicipio', $municipio);
        $this->campo($doc, $endereco, 'Uf', $uf);
        $this->campo($doc, $endereco, 'Cep', $cep);

        return $endereco;
    }

    private function contato(DOMDocument $doc, Nfse $nfse): ?DOMElement
    {
        $telefone = substr($this->digitos($nfse->tomador_telefone), 0, 20);
        $email = trim((string) $nfse->tomador_email);
        $email = $email !== '' && mb_strlen($email) <= 80 ? $email : '';

        if ($telefone === '' && $email === '') {
            return null;
        }

        $contato = $doc->createElementNS(self::NS, 'Contato');
        $this->campo($doc, $contato, 'Telefone', $telefone);
        $this->campo($doc, $contato, 'Email', $email);

        return $contato;
    }

    private function obra(DOMDocument $doc, NfseItem $item, string $municipio): ?DOMElement
    {
        $grupo = $this->grupoObra($item);

        if ($grupo === null || $grupo['tipo'] !== 'end') {
            return null;
        }

        $end = $grupo['end'];
        $obra = $doc->createElementNS(self::NS, 'Obra');
        $this->campo($doc, $obra, 'Cep', $end['CEP']);
        $this->campo($doc, $obra, 'CodigoMunicipio', $municipio);
        $this->campo($doc, $obra, 'Endereco', $end['xLgr']);
        $this->campo($doc, $obra, 'Bairro', $end['xBairro']);
        $this->campo($doc, $obra, 'Numero', $end['nro']);
        $this->campo($doc, $obra, 'Complemento', $end['xCpl'] !== null ? mb_substr($end['xCpl'], 0, 60) : null);

        return $obra;
    }

    private function construcaoCivil(DOMDocument $doc, NfseItem $item): ?DOMElement
    {
        $grupo = $this->grupoObra($item);

        if ($grupo === null || $grupo['tipo'] !== 'cObra') {
            return null;
        }

        $construcao = $doc->createElementNS(self::NS, 'ConstrucaoCivil');
        $this->campo($doc, $construcao, 'CodigoObra', $grupo['cObra']);

        return $construcao;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function grupoObra(NfseItem $item): ?array
    {
        if (! NfseObra::exige($item->c_trib_nac)) {
            return null;
        }

        $grupo = NfseObra::paraXml($item->c_trib_nac, $item->getAttributes());

        if ($grupo === null) {
            throw new NfseNaoTransmitida('Informe os dados da obra do serviço.');
        }

        if ($grupo['tipo'] === 'cCIB') {
            throw new NfseNaoTransmitida('O IPM não recebe o CIB da obra. Informe o código CNO/CEI ou o endereço da obra.');
        }

        return $grupo;
    }

    private function exigirServicoUnico(Nfse $nfse): void
    {
        $codigos = $nfse->itens
            ->map(fn (NfseItem $item): string => $this->digitos($item->c_trib_nac).'|'.$this->digitos($item->c_nbs).'|'.trim((string) $item->c_trib_mun))
            ->unique();

        if ($codigos->count() > 1) {
            throw new NfseNaoTransmitida('No IPM, cada NFS-e aceita um único código de serviço. Emita uma nota para cada código de tributação/NBS.');
        }
    }

    private function discriminacao(Nfse $nfse): string
    {
        $partes = [];

        foreach ($nfse->itens as $item) {
            $texto = trim(preg_replace('/\s+/u', ' ', (string) $item->descricao) ?? '');

            if ($texto !== '') {
                $partes[] = $texto;
            }
        }

        $descricao = implode(' | ', $partes);

        if ($descricao === '') {
            throw new NfseNaoTransmitida('Informe a descrição do serviço.');
        }

        return mb_substr($descricao, 0, 2000);
    }

    private function regimeEspecial(Empresa $empresa): ?string
    {
        if (strtolower(trim((string) $empresa->regime_tributario)) === 'mei') {
            return '5';
        }

        return self::REGIME_ESPECIAL[trim((string) $empresa->nfse_reg_esp_trib)] ?? null;
    }

    private function optanteSimples(Empresa $empresa): bool
    {
        return in_array(strtolower(trim((string) $empresa->regime_tributario)), ['simples', 'mei'], true);
    }

    private function responsavelRetencao(string $retencao): ?string
    {
        return match ($retencao) {
            '2' => '1',
            '3' => '2',
            default => null,
        };
    }

    private function exigibilidade(string $tributacao): string
    {
        return match ($tributacao) {
            '2' => '5',
            '3' => '4',
            '4' => '2',
            default => '1',
        };
    }

    /**
     * Subitem LC 116 com desdobro nacional (99.99.99), exigido pela NTE (L1099/L1103).
     */
    private function itemLista(string $codigo): ?string
    {
        $digitos = $this->digitos($codigo);

        if (strlen($digitos) !== 6) {
            return null;
        }

        return substr($digitos, 0, 2).'.'.substr($digitos, 2, 2).'.'.substr($digitos, 4, 2);
    }

    private function decimal(mixed $valor): string
    {
        $texto = str_replace(',', '.', trim((string) $valor));

        if ($texto === '' || ! is_numeric($texto)) {
            return '0.00';
        }

        return number_format((float) $texto, 2, '.', '');
    }

    private function digitos(mixed $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }

    private function campo(DOMDocument $doc, DOMElement $pai, string $nome, ?string $valor): void
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return;
        }

        $elemento = $doc->createElementNS(self::NS, $nome);
        $elemento->appendChild($doc->createTextNode($texto));
        $pai->appendChild($elemento);
    }
}
