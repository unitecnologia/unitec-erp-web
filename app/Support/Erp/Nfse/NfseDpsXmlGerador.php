<?php

namespace App\Support\Erp\Nfse;

use DOMDocument;
use DOMElement;

class NfseDpsXmlGerador
{
    public const NS = 'http://www.sped.fazenda.gov.br/nfse';

    public const VERSAO = '1.01';

    /**
     * XML da DPS no leiaute oficial, apenas com dados já presentes na estrutura do builder.
     * Campo obrigatório do XSD sem fonte no builder não é inventado.
     *
     * @param  array<string, mixed>  $dps
     */
    public function gerar(array $dps): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $raiz = $doc->createElementNS(self::NS, 'DPS');
        $raiz->setAttribute('versao', self::VERSAO);
        $doc->appendChild($raiz);

        $inf = $doc->createElementNS(self::NS, 'infDPS');
        $id = $this->idDps($dps);

        if ($id !== null) {
            $inf->setAttribute('Id', $id);
        }

        $raiz->appendChild($inf);

        $this->campo($doc, $inf, 'tpAmb', $this->texto($dps['dps']['tp_amb'] ?? null));
        $this->campo($doc, $inf, 'dhEmi', $this->texto($dps['dps']['dh_emi'] ?? null));
        $this->campo($doc, $inf, 'verAplic', $this->texto($dps['dps']['ver_aplic'] ?? null));
        $this->campo($doc, $inf, 'serie', $this->texto($dps['dps']['serie'] ?? null));
        $this->campo($doc, $inf, 'nDPS', $this->texto($dps['dps']['numero'] ?? null));
        $this->campo($doc, $inf, 'dCompet', $this->dCompet($dps['dps']['competencia'] ?? null));
        $this->campo($doc, $inf, 'tpEmit', $this->texto($dps['dps']['tp_emit'] ?? null));
        $this->campo($doc, $inf, 'cLocEmi', $this->digitos($dps['prestador']['cidade_codigo'] ?? null, 7));

        $prest = $this->prestador($doc, $dps['prestador'] ?? [], $this->texto($dps['dps']['tp_emit'] ?? null));

        if ($prest !== null) {
            $inf->appendChild($prest);
        }

        $toma = $this->tomador($doc, $dps['tomador'] ?? []);

        if ($toma !== null) {
            $inf->appendChild($toma);
        }

        $serv = $this->servico($doc, $dps);

        if ($serv !== null) {
            $inf->appendChild($serv);
        }

        $valores = $this->valores($doc, $dps['valores'] ?? []);

        if ($valores !== null) {
            $inf->appendChild($valores);
        }

        return $doc->saveXML() ?: '';
    }

    /**
     * @param  array<string, mixed>  $dps
     */
    private function idDps(array $dps): ?string
    {
        $municipio = $this->digitos($dps['prestador']['cidade_codigo'] ?? null, 7);
        $cnpj = $this->digitos($dps['prestador']['cnpj'] ?? null, 14);
        $serie = $this->digitos($dps['dps']['serie'] ?? null);
        $numero = $this->digitos($dps['dps']['numero'] ?? null);

        if ($municipio === null || $cnpj === null || $serie === null || $numero === null) {
            return null;
        }

        if (strlen($serie) > 5 || strlen($numero) > 15) {
            return null;
        }

        return 'DPS'.$municipio.'2'.$cnpj.str_pad($serie, 5, '0', STR_PAD_LEFT).str_pad($numero, 15, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $prestador
     */
    private function prestador(DOMDocument $doc, array $prestador, ?string $tpEmit): ?DOMElement
    {
        $cnpj = $this->digitos($prestador['cnpj'] ?? null, 14);
        $nome = $this->texto($prestador['nome'] ?? null);

        if ($cnpj === null && $nome === null) {
            return null;
        }

        $no = $doc->createElementNS(self::NS, 'prest');

        if ($cnpj !== null) {
            $this->campo($doc, $no, 'CNPJ', $cnpj);
        }

        $im = $this->texto($prestador['inscricao_municipal'] ?? null);

        if ($im !== null) {
            $this->campo($doc, $no, 'IM', $im);
        }

        if ($tpEmit !== NfseEmitente::Prestador->value) {
            $this->campo($doc, $no, 'xNome', $nome);
            $this->anexarEndereco($doc, $no, $prestador);
        }
        $this->campo($doc, $no, 'fone', $this->digitos($prestador['telefone'] ?? null));
        $this->campo($doc, $no, 'email', $this->texto($prestador['email'] ?? null));
        $this->anexarRegTrib($doc, $no, $prestador);

        return $no;
    }

    /**
     * @param  array<string, mixed>  $prestador
     */
    private function anexarRegTrib(DOMDocument $doc, DOMElement $prest, array $prestador): void
    {
        $opSimpNac = $this->texto($prestador['op_simp_nac'] ?? null);
        $regEspTrib = $this->texto($prestador['reg_esp_trib'] ?? null);

        if ($opSimpNac === null || $regEspTrib === null) {
            return;
        }

        $regTrib = $doc->createElementNS(self::NS, 'regTrib');
        $this->campo($doc, $regTrib, 'opSimpNac', $opSimpNac);
        $this->campo($doc, $regTrib, 'regApTribSN', $this->texto($prestador['reg_ap_trib_sn'] ?? null));
        $this->campo($doc, $regTrib, 'regEspTrib', $regEspTrib);
        $prest->appendChild($regTrib);
    }

    /**
     * @param  array<string, mixed>  $tomador
     */
    private function tomador(DOMDocument $doc, array $tomador): ?DOMElement
    {
        $nome = $this->texto($tomador['nome'] ?? null);
        $documento = $this->digitos($tomador['cpf_cnpj'] ?? null);

        if ($nome === null && $documento === null) {
            return null;
        }

        $no = $doc->createElementNS(self::NS, 'toma');

        if (strlen((string) $documento) === 14) {
            $this->campo($doc, $no, 'CNPJ', $documento);
        } elseif (strlen((string) $documento) === 11) {
            $this->campo($doc, $no, 'CPF', $documento);
        }

        $this->campo($doc, $no, 'xNome', $nome);
        $this->anexarEndereco($doc, $no, $tomador);
        $this->campo($doc, $no, 'fone', $this->digitos($tomador['telefone'] ?? null));
        $this->campo($doc, $no, 'email', $this->texto($tomador['email'] ?? null));

        return $no;
    }

    /**
     * @param  array<string, mixed>  $pessoa
     */
    private function anexarEndereco(DOMDocument $doc, DOMElement $pai, array $pessoa): void
    {
        $municipio = $this->digitos($pessoa['cidade_codigo'] ?? null, 7);
        $cep = $this->digitos($pessoa['cep'] ?? null, 8);
        $logradouro = $this->texto($pessoa['endereco'] ?? null);
        $numero = $this->texto($pessoa['numero'] ?? null);
        $bairro = $this->texto($pessoa['bairro'] ?? null);

        if ($municipio === null || $cep === null || $logradouro === null || $numero === null || $bairro === null) {
            return;
        }

        $end = $doc->createElementNS(self::NS, 'end');
        $nacional = $doc->createElementNS(self::NS, 'endNac');
        $this->campo($doc, $nacional, 'cMun', $municipio);
        $this->campo($doc, $nacional, 'CEP', $cep);
        $end->appendChild($nacional);
        $this->campo($doc, $end, 'xLgr', $logradouro);
        $this->campo($doc, $end, 'nro', $numero);
        $this->campo($doc, $end, 'xBairro', $bairro);
        $pai->appendChild($end);
    }

    /**
     * @param  array<string, mixed>  $dps
     */
    private function servico(DOMDocument $doc, array $dps): ?DOMElement
    {
        $servicos = $dps['servicos'] ?? [];

        if (! is_array($servicos) || count($servicos) !== 1 || ! is_array($servicos[0])) {
            return null;
        }

        $item = $servicos[0];
        $municipio = $this->digitos($dps['municipio_prestacao']['codigo'] ?? null, 7);
        $nacional = $this->digitos($item['cTribNac'] ?? null, 6);
        $descricao = $this->texto($item['descricao'] ?? null);

        if ($municipio === null && $nacional === null && $descricao === null) {
            return null;
        }

        $serv = $doc->createElementNS(self::NS, 'serv');

        if ($municipio !== null) {
            $local = $doc->createElementNS(self::NS, 'locPrest');
            $this->campo($doc, $local, 'cLocPrestacao', $municipio);
            $serv->appendChild($local);
        }

        $codigo = $doc->createElementNS(self::NS, 'cServ');
        $this->campo($doc, $codigo, 'cTribNac', $nacional);
        $this->campo($doc, $codigo, 'cTribMun', $this->cTribMun($dps, $item));
        $this->campo($doc, $codigo, 'xDescServ', $descricao);
        $this->campo($doc, $codigo, 'cNBS', $this->digitos($item['cNBS'] ?? null, 9));

        if ($codigo->childNodes->length > 0) {
            $serv->appendChild($codigo);
            $obra = $this->obra($doc, $item);

            if ($obra !== null) {
                $serv->appendChild($obra);
            }
        }

        return $serv->childNodes->length > 0 ? $serv : null;
    }

    /**
     * TCInfoObra: inscrição opcional e exatamente uma identificação.
     *
     * @param  array<string, mixed>  $item
     */
    private function obra(DOMDocument $doc, array $item): ?DOMElement
    {
        $obra = $item['obra'] ?? null;

        if (! is_array($obra)) {
            return null;
        }

        $tipo = $obra['tipo'] ?? null;
        $no = $doc->createElementNS(self::NS, 'obra');
        $this->campo($doc, $no, 'inscImobFisc', $this->texto($obra['inscImobFisc'] ?? null));

        if ($tipo === 'cObra') {
            $this->campo($doc, $no, 'cObra', $this->texto($obra['cObra'] ?? null));
        } elseif ($tipo === 'cCIB') {
            $this->campo($doc, $no, 'cCIB', $this->texto($obra['cCIB'] ?? null));
        } elseif ($tipo === 'end' && is_array($obra['end'] ?? null)) {
            $endereco = $obra['end'];
            $end = $doc->createElementNS(self::NS, 'end');
            $this->campo($doc, $end, 'CEP', $this->digitos($endereco['CEP'] ?? null, 8));
            $this->campo($doc, $end, 'xLgr', $this->texto($endereco['xLgr'] ?? null));
            $this->campo($doc, $end, 'nro', $this->texto($endereco['nro'] ?? null));
            $this->campo($doc, $end, 'xCpl', $this->texto($endereco['xCpl'] ?? null));
            $this->campo($doc, $end, 'xBairro', $this->texto($endereco['xBairro'] ?? null));

            if ($end->childNodes->length > 0) {
                $no->appendChild($end);
            }
        }

        foreach ($no->childNodes as $filho) {
            if (in_array($filho->localName, ['cObra', 'cCIB', 'end'], true)) {
                return $no;
            }
        }

        return null;
    }

    /**
     * MEI (opSimpNac 2) não informa código municipal, mesmo com o valor no snapshot.
     *
     * @param  array<string, mixed>  $dps
     * @param  array<string, mixed>  $item
     */
    private function cTribMun(array $dps, array $item): ?string
    {
        if ($this->texto($dps['prestador']['op_simp_nac'] ?? null) === '2') {
            return null;
        }

        return $this->digitos($item['cTribMun'] ?? null, 3);
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    private function valores(DOMDocument $doc, array $valores): ?DOMElement
    {
        $servico = $this->texto($valores['valor_servicos'] ?? null);

        if ($servico === null) {
            return null;
        }

        $no = $doc->createElementNS(self::NS, 'valores');
        $prestado = $doc->createElementNS(self::NS, 'vServPrest');
        $this->campo($doc, $prestado, 'vServ', $servico);
        $no->appendChild($prestado);
        $this->anexarTrib($doc, $no, $valores);

        return $no;
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    private function anexarTrib(DOMDocument $doc, DOMElement $valoresNo, array $valores): void
    {
        $tribIssqn = $this->texto($valores['trib_issqn'] ?? null);
        $tpRetIssqn = $this->texto($valores['tp_ret_issqn'] ?? null);
        $indTotTrib = $this->texto($valores['ind_tot_trib'] ?? null);

        if ($tribIssqn === null || $tpRetIssqn === null || $indTotTrib === null) {
            return;
        }

        $trib = $doc->createElementNS(self::NS, 'trib');
        $municipal = $doc->createElementNS(self::NS, 'tribMun');
        $this->campo($doc, $municipal, 'tribISSQN', $tribIssqn);
        $this->campo($doc, $municipal, 'tpRetISSQN', $tpRetIssqn);
        $trib->appendChild($municipal);

        $totais = $doc->createElementNS(self::NS, 'totTrib');
        $this->campo($doc, $totais, 'indTotTrib', $indTotTrib);
        $trib->appendChild($totais);
        $valoresNo->appendChild($trib);
    }

    private function campo(DOMDocument $doc, DOMElement $pai, string $nome, ?string $valor): void
    {
        if ($valor === null || $valor === '') {
            return;
        }

        $pai->appendChild($doc->createElementNS(self::NS, $nome, $valor));
    }

    private function dCompet(mixed $competencia): ?string
    {
        $texto = $this->texto($competencia);

        if ($texto !== null && preg_match('/^\d{4}-\d{2}$/', $texto) === 1) {
            return $texto.'-01';
        }

        return $texto;
    }

    private function texto(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private function digitos(mixed $valor, ?int $tamanho = null): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';

        if ($digitos === '') {
            return null;
        }

        if ($tamanho !== null && strlen($digitos) !== $tamanho) {
            return null;
        }

        return $digitos;
    }
}
