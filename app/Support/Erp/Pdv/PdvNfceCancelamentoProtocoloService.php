<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Compra\CompraDanfeReportService;
use App\Support\Erp\Orcamento\OrcamentoBobinaFormatter as F;
use Illuminate\Support\Carbon;

final class PdvNfceCancelamentoProtocoloService
{
    public function __construct(
        private readonly CompraDanfeReportService $danfe = new CompraDanfeReportService(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(
        PdvVenda $venda,
        ?Empresa $empresa,
        string $usuario,
        bool $autoPrint = false,
        ?Carbon $printedAt = null,
    ): array {
        $venda->loadMissing('nfce');
        $documento = $venda->nfce;
        $printedAt ??= now();
        $canceladaEm = $documento?->cancelada_em ?? $printedAt;
        $protocolo = (string) ($documento?->protocolo_cancelamento ?? '');
        $chave = (string) ($documento?->chave ?? '');

        $data = [
            'venda' => $venda,
            'empresa' => $empresa,
            'usuario' => $usuario,
            'emitente' => $this->buildEmitente($empresa),
            'chaveFormatada' => $chave !== '' ? $this->danfe->formatChave($chave) : '—',
            'protocolo' => $protocolo,
            'protocoloFormatado' => $this->formatarProtocolo($protocolo),
            'numeroNf' => str_pad((string) ($documento?->numero ?? $venda->numero), 9, '0', STR_PAD_LEFT),
            'serie' => str_pad((string) ($documento?->serie ?? '1'), 3, '0', STR_PAD_LEFT),
            'dataCancelamento' => $canceladaEm->format('d/m/Y'),
            'horaCancelamento' => $canceladaEm->format('H:i:s'),
            'motivoEstorno' => trim((string) ($venda->motivo_estorno ?? '')),
            'autoPrint' => $autoPrint,
            'printedAt' => $printedAt,
        ];

        $data['lines'] = $this->buildLines($data);

        return $data;
    }

    /**
     * Protocolo de cancelamento em A4 (terminal com impressora A4): dados do evento lidos do XML gravado.
     *
     * @return array<string, mixed>
     */
    public function buildA4ViewData(
        PdvVenda $venda,
        ?Empresa $empresa,
        string $usuario,
        bool $autoPrint = false,
        bool $embed = false,
    ): array {
        $data = $this->buildViewData($venda, $empresa, $usuario, $autoPrint);
        $documento = $venda->nfce;
        $xml = (string) ($documento?->xml_cancelamento ?? '');
        $retEvento = preg_match('/<retEvento\b[\s\S]*?<\/retEvento>/', $xml, $m) === 1 ? $m[0] : '';

        $dhEvento = $this->dataHoraXml($this->tagXml($retEvento, 'dhRegEvento') ?: $this->tagXml($xml, 'dhEvento'))
            ?? $documento?->cancelada_em;
        $tpAmb = (int) ($this->tagXml($xml, 'tpAmb') ?: ($documento?->ambiente ?: 0));
        $cStat = $this->tagXml($retEvento, 'cStat');
        $xMotivo = $this->tagXml($retEvento, 'xMotivo');
        $justificativa = $this->tagXml($xml, 'xJust') ?: (string) $data['motivoEstorno'];
        $protocoloAutorizacao = (string) ($documento?->protocolo ?? '');
        $emitidaEm = $documento?->autorizada_em ?? $venda->fechado_em;

        return array_merge($data, [
            'emitente' => array_merge($data['emitente'], [
                'telefone' => (string) ($empresa?->telefone ?? ''),
            ]),
            'logoDataUri' => $this->danfe->logoDataUri($empresa),
            'chaveBlocos' => trim(chunk_split(preg_replace('/\D/', '', (string) ($documento?->chave ?? '')) ?? '', 4, ' ')) ?: '—',
            'modelo' => '65',
            'protocoloAutorizacao' => $protocoloAutorizacao,
            'protocoloAutorizacaoFormatado' => $protocoloAutorizacao !== '' ? $this->formatarProtocolo($protocoloAutorizacao) : '',
            'dataEmissao' => $emitidaEm ? Carbon::parse($emitidaEm)->format('d/m/Y H:i:s') : '',
            'valorTotal' => number_format((float) ($venda->total ?? 0), 2, ',', '.'),
            'dataHoraEvento' => $dhEvento ? Carbon::parse($dhEvento)->format('d/m/Y H:i:s') : '',
            'retornoSefaz' => trim($cStat.($cStat !== '' && $xMotivo !== '' ? ' – ' : '').$xMotivo),
            'homologacao' => $tpAmb === 2,
            'justificativa' => mb_strtoupper(trim($justificativa), 'UTF-8'),
            'embed' => $embed,
        ]);
    }

    private function tagXml(string $xml, string $tag): string
    {
        if ($xml === '' || preg_match('/<'.$tag.'>([^<]*)<\/'.$tag.'>/', $xml, $m) !== 1) {
            return '';
        }

        return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function dataHoraXml(string $valor): ?Carbon
    {
        if ($valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function buildLines(array $data): array
    {
        $emitente = $data['emitente'] ?? [];
        $venda = $data['venda'];
        $lines = [];

        $fantasia = (string) ($emitente['fantasia'] ?: $emitente['nome'] ?? 'EMPRESA');
        foreach (F::wrap($fantasia) as $line) {
            $lines[] = F::center($line);
        }

        $nome = (string) ($emitente['nome'] ?? '');
        if ($nome !== '' && $nome !== $fantasia) {
            foreach (F::wrap($nome) as $line) {
                $lines[] = F::center($line);
            }
        }

        foreach (F::wrap('CNPJ: '.($emitente['cnpj'] ?? '').' IE: '.($emitente['ie'] ?? '')) as $line) {
            $lines[] = F::center($line);
        }

        if (filled($emitente['endereco'] ?? null)) {
            foreach (F::wrap((string) $emitente['endereco']) as $line) {
                $lines[] = F::center($line);
            }
        }

        $cidadeUf = trim(($emitente['municipio'] ?? '').' - '.($emitente['uf'] ?? ''), ' -');
        if ($cidadeUf !== '') {
            foreach (F::wrap($cidadeUf) as $line) {
                $lines[] = F::center($line);
            }
        }

        $lines[] = '';
        $lines[] = F::center('PROTOCOLO DE CANCELAMENTO');
        $lines[] = F::center('NFC-e');
        $lines[] = '';
        $lines[] = F::center('PDV #'.str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT));
        $lines[] = F::center('NFC-e No '.$data['numeroNf'].' Serie '.$data['serie']);
        $lines[] = F::center('Cancelada em '.$data['dataCancelamento'].' '.$data['horaCancelamento']);
        $lines[] = F::center('Operador: '.$data['usuario']);
        $lines[] = F::rule('-');
        $lines[] = F::center('Protocolo de cancelamento');
        $lines[] = '';

        foreach (F::wrap((string) $data['protocoloFormatado']) as $line) {
            $lines[] = F::center($line);
        }
        foreach (F::wrap((string) $data['protocolo']) as $line) {
            $lines[] = F::center($line);
        }

        $motivo = trim((string) ($data['motivoEstorno'] ?? ''));
        if ($motivo !== '') {
            $lines[] = '';
            $lines[] = 'Justificativa';
            foreach (F::wrap(mb_strtoupper($motivo, 'UTF-8')) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = F::rule('-');
        $lines[] = F::center('Chave de acesso');
        foreach (F::wrap((string) $data['chaveFormatada']) as $line) {
            $lines[] = F::center($line);
        }

        $printedAt = $data['printedAt'] ?? now();
        $lines[] = '';
        $lines[] = F::center('Impresso em '.$printedAt->format('d/m/Y H:i:s'));

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    public function buildEmitente(?Empresa $empresa): array
    {
        if ($empresa === null) {
            return [
                'nome' => 'UNITEC',
                'fantasia' => 'UNITEC',
                'cnpj' => '',
                'ie' => '',
                'endereco' => '',
                'municipio' => '',
                'uf' => '',
            ];
        }

        $endereco = trim(implode(', ', array_filter([
            trim((string) ($empresa->endereco ?? '')),
            filled($empresa->numero) ? 'nº '.$empresa->numero : null,
            trim((string) ($empresa->bairro ?? '')),
        ])));

        return [
            'nome' => mb_strtoupper((string) ($empresa->razao_social ?: $empresa->nome ?: $empresa->fantasia), 'UTF-8'),
            'fantasia' => mb_strtoupper((string) ($empresa->fantasia ?: $empresa->nome), 'UTF-8'),
            'cnpj' => $this->formatCnpj((string) $empresa->cnpj),
            'ie' => (string) ($empresa->ie ?? ''),
            'endereco' => mb_strtoupper($endereco, 'UTF-8'),
            'municipio' => mb_strtoupper((string) ($empresa->cidade ?? ''), 'UTF-8'),
            'uf' => (string) ($empresa->uf ?? ''),
        ];
    }

    private function formatCnpj(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 14) {
            return $value;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
    }

    private function formatarProtocolo(string $protocolo): string
    {
        $digits = preg_replace('/\D/', '', $protocolo) ?? '';

        if (strlen($digits) < 15) {
            return $protocolo;
        }

        return substr($digits, 0, 3).' '.substr($digits, 3, 3).' '.substr($digits, 6, 3).' '.substr($digits, 9);
    }
}
