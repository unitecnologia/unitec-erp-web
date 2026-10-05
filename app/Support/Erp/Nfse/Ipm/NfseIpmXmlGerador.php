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

        $rps = $doc->createElementNS(self::NS, 'Rps');
        $raiz->appendChild($rps);
        $inf = $doc->createElementNS(self::NS, 'InfDeclaracaoPrestacaoServico');
        $inf->setAttribute('Id', 'RPS_'.$numero);
        $rps->appendChild($inf);

        $inf->appendChild($this->identificacaoRps($doc, $nfse, $empresa, $numero));
        $this->campo($doc, $inf, 'Competencia', $nfse->competencia->format('Y-m-d'));
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

    private function identificacaoRps(DOMDocument $doc, Nfse $nfse, Empresa $empresa, int $numero): DOMElement
    {
        $serie = trim((string) $nfse->serie_dps);

        if ($serie === '' || mb_strlen($serie) > 5) {
            throw new NfseNaoTransmitida('Informe a série do RPS com até 5 caracteres.');
        }

        $tipo = trim((string) ($empresa->nfse_tipo_rps ?? '')) ?: '1';

        if (! in_array($tipo, ['1', '2', '3'], true)) {
            throw new NfseNaoTransmitida('O tipo do RPS deve ser 1, 2 ou 3.');
        }

        $bloco = $doc->createElementNS(self::NS, 'Rps');
        $id = $doc->createElementNS(self::NS, 'IdentificacaoRps');
        $this->campo($doc, $id, 'Numero', (string) $numero);
        $this->campo($doc, $id, 'Serie', $serie);
        $this->campo($doc, $id, 'Tipo', $tipo);
        $bloco->appendChild($id);
        $this->campo($doc, $bloco, 'DataEmissao', ($nfse->data_emissao ?? now())->format('Y-m-d'));
        $this->campo($doc, $bloco, 'Status', '1');

        return $bloco;
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
        $incidencia = in_array($exigibilidade, ['1', '3', '5', '6', '7'], true) ? $municipio : null;
        $this->exigirIssCalculadoPeloMunicipio($empresa, $exigibilidade, $retido, $incidencia);

        $servico = $doc->createElementNS(self::NS, 'Servico');
        $valores = $doc->createElementNS(self::NS, 'Valores');
        $this->campo($doc, $valores, 'ValorServicos', $this->decimal($nfse->valor_servicos));
        $desconto = $this->decimal($nfse->desconto);

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

    /**
     * Alíquota e ValorIss só podem ir quando o contribuinte os informa (NTE 4.1); o ERP ainda não guarda alíquota de ISS.
     */
    private function exigirIssCalculadoPeloMunicipio(Empresa $empresa, string $exigibilidade, bool $retido, ?string $incidencia): void
    {
        if ($this->optanteSimples($empresa) && $retido) {
            throw new NfseNaoTransmitida('No IPM, ISS retido de empresa do Simples Nacional exige a alíquota do Simples, que ainda não é enviada pelo ERP.');
        }

        $sede = $this->digitos($empresa->cidade_codigo);

        if ($exigibilidade === '1' && $incidencia !== null && strlen($sede) === 7 && $incidencia !== $sede) {
            throw new NfseNaoTransmitida('No IPM, ISS devido fora do município do prestador exige alíquota e valor do ISS, que ainda não são enviados pelo ERP.');
        }
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
