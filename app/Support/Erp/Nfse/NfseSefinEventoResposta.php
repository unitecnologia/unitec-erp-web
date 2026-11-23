<?php

namespace App\Support\Erp\Nfse;

/**
 * Retorno do POST/GET de eventos SEFIN Nacional (cancelamento e101101).
 */
final class NfseSefinEventoResposta
{
    /**
     * @param  list<array{codigo: string, descricao: string}>  $erros
     * @param  list<mixed>  $alertas
     */
    public function __construct(
        public readonly int $http,
        public readonly bool $registrado,
        public readonly bool $jaCancelada,
        public readonly ?string $xmlEvento,
        public readonly ?string $protocolo,
        public readonly ?string $dataHoraProcessamento,
        public readonly ?string $tipoAmbiente,
        public readonly ?string $versaoAplicativo,
        public readonly array $alertas,
        public readonly array $erros,
    ) {}

    public static function interpretar(int $http, string $body): self
    {
        $json = json_decode($body, true);

        if (! is_array($json)) {
            $xmlBruto = self::pareceXml($body) ? trim($body) : null;

            return new self(
                http: $http,
                registrado: $http >= 200 && $http < 300 && $xmlBruto !== null && self::xmlConfirmaCancelamento($xmlBruto),
                jaCancelada: false,
                xmlEvento: $xmlBruto,
                protocolo: $xmlBruto !== null ? self::protocoloDoXml($xmlBruto) : null,
                dataHoraProcessamento: null,
                tipoAmbiente: null,
                versaoAplicativo: null,
                alertas: [],
                erros: $http >= 200 && $http < 300 ? [] : [[
                    'codigo' => '',
                    'descricao' => 'A SEFIN Nacional não retornou um JSON de evento.',
                ]],
            );
        }

        $xml = self::descompactarXml(
            $json['eventoXmlGZipB64']
                ?? $json['EventoXmlGZipB64']
                ?? $json['xmlGZipB64']
                ?? null
        );
        $erros = self::erros($json['erros'] ?? $json['Erros'] ?? null);
        $jaCancelada = self::indicaJaCancelada($erros);
        $registrado = ($http === 200 || $http === 201)
            && $xml !== null
            && self::xmlConfirmaCancelamento($xml)
            && ! self::temErroBloqueante($erros, $jaCancelada);

        if ($registrado) {
            $erros = [];
        }

        return new self(
            http: $http,
            registrado: $registrado || $jaCancelada,
            jaCancelada: $jaCancelada,
            xmlEvento: $xml,
            protocolo: $xml !== null ? self::protocoloDoXml($xml) : self::texto($json['id'] ?? $json['Id'] ?? null),
            dataHoraProcessamento: self::texto($json['dataHoraProcessamento'] ?? $json['DataHoraProcessamento'] ?? null),
            tipoAmbiente: self::texto($json['tipoAmbiente'] ?? $json['TipoAmbiente'] ?? null),
            versaoAplicativo: self::texto($json['versaoAplicativo'] ?? $json['VersaoAplicativo'] ?? null),
            alertas: is_array($json['alertas'] ?? null) ? array_values($json['alertas']) : [],
            erros: $registrado || $jaCancelada ? [] : ($erros !== [] ? $erros : [[
                'codigo' => '',
                'descricao' => 'A SEFIN Nacional não confirmou o cancelamento.',
            ]]),
        );
    }

    public function mensagemErros(): string
    {
        $linhas = [];

        foreach ($this->erros as $erro) {
            $codigo = $erro['codigo'];
            $descricao = $erro['descricao'];
            $linhas[] = $codigo !== '' && $descricao !== ''
                ? $codigo.' — '.$descricao
                : ($codigo !== '' ? $codigo : $descricao);
        }

        return implode("\n", array_values(array_filter($linhas, fn (string $linha): bool => $linha !== '')));
    }

    /**
     * @param  list<array{codigo: string, descricao: string}>  $erros
     */
    private static function indicaJaCancelada(array $erros): bool
    {
        foreach ($erros as $erro) {
            $codigo = strtoupper(trim($erro['codigo']));
            $descricao = $erro['descricao'];

            if (in_array($codigo, ['E1102', 'E101101'], true)) {
                return true;
            }

            if (preg_match('/j[aá]\s+(se\s+encontra\s+|est[aá]\s+)?cancelad/iu', $descricao) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{codigo: string, descricao: string}>  $erros
     */
    private static function temErroBloqueante(array $erros, bool $jaCancelada): bool
    {
        return $erros !== [] && ! $jaCancelada;
    }

    private static function xmlConfirmaCancelamento(string $xml): bool
    {
        if (preg_match('/<(?:\w+:)?e101101\b/i', $xml) === 1) {
            return true;
        }

        return preg_match('/Cancelamento de NFS-e/iu', $xml) === 1
            && preg_match('/<(?:\w+:)?infEvento\b/i', $xml) === 1;
    }

    private static function protocoloDoXml(string $xml): ?string
    {
        if (preg_match('/<(?:\w+:)?infEvento\b[^>]*\bId="([^"]+)"/i', $xml, $m) === 1) {
            return trim($m[1]);
        }

        if (preg_match('/<(?:\w+:)?infPedReg\b[^>]*\bId="([^"]+)"/i', $xml, $m) === 1) {
            return trim($m[1]);
        }

        return null;
    }

    private static function descompactarXml(mixed $gzip): ?string
    {
        if (! is_string($gzip) || trim($gzip) === '') {
            return null;
        }

        $binario = base64_decode(preg_replace('/\s+/', '', $gzip) ?? '', true);
        $descompactado = is_string($binario) ? gzdecode($binario) : false;

        if (! is_string($descompactado) || $descompactado === '') {
            throw new NfseNaoTransmitida('O XML do evento SEFIN não pôde ser descompactado.');
        }

        return $descompactado;
    }

    /**
     * @return list<array{codigo: string, descricao: string}>
     */
    private static function erros(mixed $erros): array
    {
        if (! is_array($erros)) {
            return [];
        }

        $lista = [];

        foreach ($erros as $erro) {
            if (! is_array($erro)) {
                continue;
            }

            $complemento = trim((string) ($erro['Complemento'] ?? $erro['complemento'] ?? ''));
            $descricao = trim((string) ($erro['Descricao'] ?? $erro['descricao'] ?? $erro['Mensagem'] ?? $erro['mensagem'] ?? ''));

            if ($complemento !== '') {
                $descricao = trim($descricao.' '.$complemento);
            }

            $lista[] = [
                'codigo' => (string) ($erro['Codigo'] ?? $erro['codigo'] ?? ''),
                'descricao' => $descricao,
            ];
        }

        return $lista;
    }

    private static function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private static function pareceXml(string $corpo): bool
    {
        $trim = ltrim($corpo);

        return str_starts_with($trim, '<') || str_starts_with($trim, '<?xml');
    }
}
