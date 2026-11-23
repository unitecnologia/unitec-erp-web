<?php

namespace App\Support\Erp\Nfse;

use App\Models\Empresa;
use App\Models\Nfse;
use DOMDocument;

/**
 * Monta o pedRegEvento v1.01 de cancelamento (e101101) para a SEFIN Nacional.
 */
class NfseSefinEventoXmlGerador
{
    public const NS = 'http://www.sped.fazenda.gov.br/nfse';

    public const VERSAO = '1.01';

    public const TIPO_CANCELAMENTO = '101101';

    public const SEQ_CANCELAMENTO = '001';

    /**
     * Motivos oficiais do e101101 (TSCodJustCanc).
     *
     * @return array<string, string>
     */
    public static function motivos(): array
    {
        return [
            '1' => 'Erro na emissão',
            '2' => 'Serviço não prestado',
            '9' => 'Outros',
        ];
    }

    /**
     * Converte o motivo da UI (ABRASF/IPM) para cMotivo + xMotivo do layout Nacional.
     *
     * @return array{codigo: string, descricao: string}
     */
    public static function mapearMotivo(string $codigoUi): array
    {
        $codigo = trim($codigoUi);

        return match ($codigo) {
            '1' => [
                'codigo' => '1',
                'descricao' => 'Erro na emissao da NFS-e.',
            ],
            '2' => [
                'codigo' => '2',
                'descricao' => 'Servico nao prestado ao tomador.',
            ],
            '9' => [
                'codigo' => '9',
                'descricao' => 'Cancelamento da NFS-e por outros motivos.',
            ],
            '3' => [
                'codigo' => '9',
                'descricao' => 'Cancelamento por erro de assinatura digital.',
            ],
            '4' => [
                'codigo' => '9',
                'descricao' => 'Cancelamento por duplicidade da NFS-e.',
            ],
            '5' => [
                'codigo' => '9',
                'descricao' => 'Cancelamento por erro de processamento.',
            ],
            default => throw new NfseNaoTransmitida('Selecione o motivo do cancelamento.'),
        };
    }

    public function gerarCancelamento(Nfse $nfse, string $codigoUi): string
    {
        $nfse->loadMissing('empresa');
        $empresa = $nfse->empresa;

        if (! $empresa instanceof Empresa) {
            throw new NfseNaoTransmitida('Empresa da NFS-e não encontrada.');
        }

        $chave = preg_replace('/\D/', '', (string) ($nfse->chave_acesso ?: $nfse->chave)) ?? '';

        if (strlen($chave) !== 50) {
            throw new NfseNaoTransmitida('A NFS-e Nacional não tem chave de acesso válida para cancelar.');
        }

        $cnpj = preg_replace('/\D/', '', (string) $empresa->cnpj) ?? '';

        if (strlen($cnpj) !== 14) {
            throw new NfseNaoTransmitida('Informe o CNPJ da empresa.');
        }

        $motivo = self::mapearMotivo($codigoUi);
        $xMotivo = $this->garantirXMotivo($motivo['descricao']);
        $ambiente = NfseSefinAmbiente::daEmpresa($empresa->nfse_ambiente);
        $verAplic = mb_substr(trim((string) config('unitec.versao', 'UnitecERP')), 0, 20) ?: 'UnitecERP';
        $id = 'PRE'.$chave.self::TIPO_CANCELAMENTO;

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $raiz = $doc->createElementNS(self::NS, 'pedRegEvento');
        $raiz->setAttribute('versao', self::VERSAO);
        $doc->appendChild($raiz);

        $inf = $doc->createElementNS(self::NS, 'infPedReg');
        $inf->setAttribute('Id', $id);
        $raiz->appendChild($inf);

        $this->campo($doc, $inf, 'tpAmb', $ambiente->tpAmb());
        $this->campo($doc, $inf, 'verAplic', $verAplic);
        $this->campo($doc, $inf, 'dhEvento', now('America/Sao_Paulo')->format('Y-m-d\TH:i:sP'));
        $this->campo($doc, $inf, 'CNPJAutor', $cnpj);
        $this->campo($doc, $inf, 'chNFSe', $chave);

        $evento = $doc->createElementNS(self::NS, 'e101101');
        $inf->appendChild($evento);
        $this->campo($doc, $evento, 'xDesc', 'Cancelamento de NFS-e');
        $this->campo($doc, $evento, 'cMotivo', $motivo['codigo']);
        $this->campo($doc, $evento, 'xMotivo', $xMotivo);

        return $doc->saveXML() ?: '';
    }

    private function garantirXMotivo(string $texto): string
    {
        $limpo = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);

        if (mb_strlen($limpo) < 15) {
            $limpo = str_pad($limpo, 15, '.');
        }

        return mb_substr($limpo, 0, 255);
    }

    private function campo(DOMDocument $doc, \DOMElement $pai, string $nome, string $valor): void
    {
        $pai->appendChild($doc->createElementNS(self::NS, $nome, $valor));
    }
}
