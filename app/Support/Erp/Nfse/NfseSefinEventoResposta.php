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

        $xml = self::extrairXmlEvento($json);
        // Emissão usa "erros" (array). Eventos/consultas usam ResponseErro "erro"
        // (objeto único no Swagger; em produção a SEFIN também devolve array).
        $erros = self::errosDoJson($json);
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
            // Confirmação só com XML de e101101 (ou E1102 tipificado). E0840 sozinho nunca confirma.
            registrado: $registrado || ($jaCancelada && $xml !== null && self::xmlConfirmaCancelamento($xml)),
            jaCancelada: $jaCancelada,
            xmlEvento: $xml,
            protocolo: $xml !== null ? self::protocoloDoXml($xml) : self::texto($json['id'] ?? $json['Id'] ?? null),
            dataHoraProcessamento: self::texto($json['dataHoraProcessamento'] ?? $json['DataHoraProcessamento'] ?? null),
            tipoAmbiente: self::texto($json['tipoAmbiente'] ?? $json['TipoAmbiente'] ?? null),
            versaoAplicativo: self::texto($json['versaoAplicativo'] ?? $json['VersaoAplicativo'] ?? null),
            alertas: is_array($json['alertas'] ?? null) ? array_values($json['alertas']) : [],
            erros: $registrado || ($jaCancelada && $xml !== null && self::xmlConfirmaCancelamento($xml))
                ? []
                : ($erros !== [] ? $erros : [[
                    'codigo' => '',
                    'descricao' => 'A SEFIN Nacional não confirmou o cancelamento.',
                ]]),
        );
    }

    /**
     * E0840 = algum evento já vinculado impede novo cancelamento.
     * Pode ser o próprio e101101 ou outro (confirmação, análise fiscal, etc.).
     * Nunca confirma cancelamento sozinho — exige GET com XML e101101.
     *
     * @param  list<array{codigo: string, descricao: string}>  $erros
     */
    public static function exigeConsultaConfirmacao(array $erros): bool
    {
        foreach ($erros as $erro) {
            $codigo = strtoupper(trim($erro['codigo'] ?? ''));

            if ($codigo === 'E0840') {
                return true;
            }

            if (preg_match('/evento .+ j[aá]\s+est[aá]\s+vinculad/iu', (string) ($erro['descricao'] ?? '')) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array{codigo: string, descricao: string}>
     */
    public static function errosDoJson(array $json): array
    {
        foreach (['erros', 'Erros', 'erro', 'Erro'] as $campo) {
            if (! array_key_exists($campo, $json)) {
                continue;
            }

            $bruto = $json[$campo];

            if ($bruto === null) {
                continue;
            }

            if (is_array($bruto) && self::pareceMensagemUnica($bruto)) {
                return self::erros([$bruto]);
            }

            $lista = self::erros($bruto);

            if ($lista !== []) {
                return $lista;
            }
        }

        return [];
    }

    /**
     * @param  array<mixed>  $item
     */
    private static function pareceMensagemUnica(array $item): bool
    {
        if ($item === [] || array_is_list($item)) {
            return false;
        }

        return isset($item['Codigo'])
            || isset($item['codigo'])
            || isset($item['Descricao'])
            || isset($item['descricao'])
            || isset($item['Mensagem'])
            || isset($item['mensagem'])
            || isset($item['Complemento'])
            || isset($item['complemento']);
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

            // E0840 = evento vinculado (pode ser cancelamento OU outro). Nunca sozinho.
            if ($codigo === 'E0840') {
                continue;
            }

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

    /**
     * @param  array<string, mixed>  $json
     */
    private static function extrairXmlEvento(array $json): ?string
    {
        foreach ([
            $json['eventoXmlGZipB64'] ?? null,
            $json['EventoXmlGZipB64'] ?? null,
            $json['xmlGZipB64'] ?? null,
            $json['arquivoXml'] ?? null,
            $json['ArquivoXml'] ?? null,
        ] as $candidato) {
            $xml = self::descompactarXmlSuave($candidato);
            if ($xml !== null) {
                return $xml;
            }

            if (is_string($candidato) && self::pareceXml($candidato)) {
                return trim($candidato);
            }
        }

        $lista = $json['eventos'] ?? $json['Eventos'] ?? null;

        if (! is_array($lista)) {
            return null;
        }

        foreach ($lista as $item) {
            if (! is_array($item)) {
                continue;
            }

            $xml = self::extrairXmlEvento($item);

            if ($xml !== null && self::xmlConfirmaCancelamento($xml)) {
                return $xml;
            }
        }

        return null;
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

    /**
     * Aceita gzip+base64 simples (Swagger) e base64(base64(gzip)) observado na SEFIN real (arquivoXml).
     */
    private static function descompactarXmlSuave(mixed $valor): ?string
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        $trim = preg_replace('/\s+/', '', $valor) ?? '';
        $binario = base64_decode($trim, true);

        if (! is_string($binario) || $binario === '') {
            return null;
        }

        $gz = @gzdecode($binario);

        if (is_string($gz) && $gz !== '') {
            return $gz;
        }

        // Dupla camada: base64 → texto base64 → gzip
        $interno = preg_replace('/\s+/', '', $binario) ?? '';

        if ($interno === '' || preg_match('/^[A-Za-z0-9+\/]+=*$/', $interno) !== 1) {
            return null;
        }

        $binario2 = base64_decode($interno, true);

        if (! is_string($binario2) || $binario2 === '') {
            return null;
        }

        $gz2 = @gzdecode($binario2);

        return is_string($gz2) && $gz2 !== '' ? $gz2 : null;
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
            $codigo = trim((string) ($erro['Codigo'] ?? $erro['codigo'] ?? ''));

            if ($complemento !== '') {
                $descricao = trim($descricao.' '.$complemento);
            }

            if ($codigo === '' && $descricao === '') {
                continue;
            }

            $lista[] = [
                'codigo' => $codigo,
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
