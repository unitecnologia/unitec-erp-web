<?php

namespace App\Support\Erp\Nfse\Ipm;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use DOMDocument;
use DOMElement;

/**
 * RPS no leiaute ABRASF 2.04 (GerarNfseEnvio), só com dados já gravados na NFS-e.
 */
class NfseIpmXmlGerador
{
    public const NS = 'http://www.abrasf.org.br/nfse.xsd';

    public function gerar(Nfse $nfse): string
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

        $prestador = $this->digitos($empresa->cnpj);

        if (strlen($prestador) !== 14) {
            throw new NfseNaoTransmitida('Informe o CNPJ da empresa.');
        }

        $im = trim((string) $empresa->im);

        if ($im === '') {
            throw new NfseNaoTransmitida('Informe a inscrição municipal da empresa.');
        }

        $itemLista = $this->itemLista((string) $item->c_trib_nac);

        if ($itemLista === null) {
            throw new NfseNaoTransmitida('Informe o código de tributação nacional do serviço com 6 dígitos.');
        }

        $municipio = $this->digitos($nfse->municipio_prestacao_codigo);

        if (strlen($municipio) !== 7) {
            throw new NfseNaoTransmitida('Falta o código IBGE do município da prestação.');
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $raiz = $doc->createElementNS(self::NS, 'GerarNfseEnvio');
        $doc->appendChild($raiz);

        $rps = $doc->createElementNS(self::NS, 'Rps');
        $inf = $doc->createElementNS(self::NS, 'InfDeclaracaoPrestacaoServico');
        $inf->setAttribute('Id', 'rps'.$nfse->numero_dps);
        $rps->appendChild($inf);
        $raiz->appendChild($rps);

        $inf->appendChild($this->identificacaoRps($doc, $nfse, $empresa));
        $this->campo($doc, $inf, 'Competencia', $nfse->competencia?->format('Y-m-d'));
        $inf->appendChild($this->servico($doc, $nfse, $empresa, $item, $itemLista, $municipio));
        $inf->appendChild($this->prestador($doc, $prestador, $im));

        $tomador = $this->tomador($doc, $nfse);

        if ($tomador !== null) {
            $inf->appendChild($tomador);
        }

        $regime = trim((string) $empresa->nfse_reg_esp_trib);

        if (preg_match('/^[1-6]$/', $regime) === 1) {
            $this->campo($doc, $inf, 'RegimeEspecialTributacao', $regime);
        }

        $this->campo($doc, $inf, 'OptanteSimplesNacional', $this->optanteSimples($empresa));
        $this->campo($doc, $inf, 'IncentivoFiscal', '2');

        return $doc->saveXML() ?: '';
    }

    private function identificacaoRps(DOMDocument $doc, Nfse $nfse, Empresa $empresa): DOMElement
    {
        $bloco = $doc->createElementNS(self::NS, 'Rps');
        $id = $doc->createElementNS(self::NS, 'IdentificacaoRps');
        $tipo = trim((string) ($empresa->nfse_tipo_rps ?? ''));
        $this->campo($doc, $id, 'Numero', (string) $nfse->numero_dps);
        $this->campo($doc, $id, 'Serie', trim((string) $nfse->serie_dps));
        $this->campo($doc, $id, 'Tipo', $tipo !== '' ? $tipo : '1');
        $bloco->appendChild($id);
        $this->campo($doc, $bloco, 'DataEmissao', $nfse->data_emissao?->format('Y-m-d'));
        $this->campo($doc, $bloco, 'Status', '1');

        return $bloco;
    }

    private function servico(DOMDocument $doc, Nfse $nfse, Empresa $empresa, NfseItem $item, string $itemLista, string $municipio): DOMElement
    {
        $servico = $doc->createElementNS(self::NS, 'Servico');
        $valores = $doc->createElementNS(self::NS, 'Valores');
        $this->campo($doc, $valores, 'ValorServicos', $this->decimal($nfse->valor_servicos));
        $this->campo($doc, $valores, 'ValorDeducoes', '0.00');
        $this->campo($doc, $valores, 'ValorIss', $this->decimal($nfse->iss));
        $desconto = $this->decimal($nfse->desconto);

        if (bccomp($desconto, '0', 2) === 1) {
            $this->campo($doc, $valores, 'DescontoIncondicionado', $desconto);
        }

        $servico->appendChild($valores);
        $this->campo($doc, $servico, 'IssRetido', $this->issRetido((string) $nfse->tp_ret_issqn));
        $this->campo($doc, $servico, 'ResponsavelRetencao', $this->responsavelRetencao((string) $nfse->tp_ret_issqn));
        $this->campo($doc, $servico, 'ItemListaServico', $itemLista);

        $cnae = $this->digitos($empresa->cnae);

        if (strlen($cnae) === 7) {
            $this->campo($doc, $servico, 'CodigoCnae', $cnae);
        }

        $municipal = trim((string) $item->c_trib_mun);

        if ($municipal !== '') {
            $this->campo($doc, $servico, 'CodigoTributacaoMunicipio', $municipal);
        }

        $descricao = $this->discriminacao($nfse);

        $this->campo($doc, $servico, 'Discriminacao', $descricao);
        $this->campo($doc, $servico, 'CodigoMunicipio', $municipio);
        $this->campo($doc, $servico, 'ExigibilidadeISS', $this->exigibilidade((string) $nfse->trib_issqn));
        $this->campo($doc, $servico, 'MunicipioIncidencia', $municipio);

        return $servico;
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

        if ($nome === '' && strlen($documento) !== 11 && strlen($documento) !== 14) {
            return null;
        }

        $tomador = $doc->createElementNS(self::NS, 'Tomador');

        if (strlen($documento) === 11 || strlen($documento) === 14) {
            $identificacao = $doc->createElementNS(self::NS, 'IdentificacaoTomador');
            $cpfCnpj = $doc->createElementNS(self::NS, 'CpfCnpj');
            $this->campo($doc, $cpfCnpj, strlen($documento) === 14 ? 'Cnpj' : 'Cpf', $documento);
            $identificacao->appendChild($cpfCnpj);
            $tomador->appendChild($identificacao);
        }

        $this->campo($doc, $tomador, 'RazaoSocial', $nome !== '' ? $nome : null);

        $endereco = $this->endereco($doc, $nfse);

        if ($endereco !== null) {
            $tomador->appendChild($endereco);
        }

        $email = trim((string) $nfse->tomador_email);
        $this->campo($doc, $tomador, 'Email', $email !== '' ? $email : null);

        return $tomador;
    }

    private function endereco(DOMDocument $doc, Nfse $nfse): ?DOMElement
    {
        $municipio = $this->digitos($nfse->tomador_cidade_codigo);
        $logradouro = trim((string) $nfse->tomador_endereco);

        if (strlen($municipio) !== 7 || $logradouro === '') {
            return null;
        }

        $endereco = $doc->createElementNS(self::NS, 'Endereco');
        $this->campo($doc, $endereco, 'Endereco', $logradouro);
        $this->campo($doc, $endereco, 'Numero', trim((string) $nfse->tomador_numero) ?: 'S/N');
        $this->campo($doc, $endereco, 'Bairro', trim((string) $nfse->tomador_bairro) ?: null);

        $cep = $this->digitos($nfse->tomador_cep);

        if (strlen($cep) === 8) {
            $this->campo($doc, $endereco, 'Cep', $cep);
        }

        $this->campo($doc, $endereco, 'CodigoMunicipio', $municipio);

        $uf = strtoupper(trim((string) $nfse->tomador_uf));

        if (strlen($uf) === 2) {
            $this->campo($doc, $endereco, 'Uf', $uf);
        }

        return $endereco;
    }

    private function discriminacao(Nfse $nfse): string
    {
        $partes = [];

        foreach ($nfse->itens as $item) {
            $texto = trim((string) $item->descricao);

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

    private function optanteSimples(Empresa $empresa): string
    {
        return in_array(strtolower(trim((string) $empresa->regime_tributario)), ['simples', 'mei'], true) ? '1' : '2';
    }

    private function issRetido(string $retencao): string
    {
        return in_array($retencao, ['2', '3'], true) ? '1' : '2';
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

    private function itemLista(string $codigo): ?string
    {
        $digitos = $this->digitos($codigo);

        if (strlen($digitos) < 4) {
            return null;
        }

        return substr($digitos, 0, 2).'.'.substr($digitos, 2, 2);
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

        $pai->appendChild($doc->createElementNS(self::NS, $nome, $texto));
    }
}
