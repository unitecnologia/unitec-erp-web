<?php

namespace App\Support\Erp\Nfse;

final class NfseSefinResposta
{
    /**
     * @param  list<array{codigo: string, descricao: string}>  $erros
     * @param  list<mixed>  $alertas
     */
    public function __construct(
        public readonly int $http,
        public readonly ?string $idDps,
        public readonly ?string $chaveAcesso,
        public readonly ?string $tipoAmbiente,
        public readonly ?string $versaoAplicativo,
        public readonly ?string $dataHoraProcessamento,
        public readonly ?string $xmlNfse,
        public readonly array $alertas,
        public readonly array $erros,
    ) {}

    public function autorizada(): bool
    {
        return $this->http === 201;
    }

    public static function interpretar(int $http, string $body): self
    {
        $json = json_decode($body, true);

        if (! is_array($json)) {
            return new self($http, null, null, null, null, null, null, [], []);
        }

        $gzip = $json['nfseXmlGZipB64'] ?? null;
        $xml = null;

        if (is_string($gzip) && trim($gzip) !== '') {
            $binario = base64_decode(preg_replace('/\s+/', '', $gzip) ?? '', true);
            $descompactado = is_string($binario) ? gzdecode($binario) : false;

            if (! is_string($descompactado) || $descompactado === '') {
                throw new NfseNaoTransmitida('A NFS-e autorizada não pôde ser descompactada.');
            }

            $xml = $descompactado;
        }

        return new self(
            http: $http,
            idDps: self::texto($json['idDps'] ?? $json['idDPS'] ?? $json['IdDps'] ?? null),
            chaveAcesso: self::texto($json['chaveAcesso'] ?? null),
            tipoAmbiente: self::texto($json['tipoAmbiente'] ?? null),
            versaoAplicativo: self::texto($json['versaoAplicativo'] ?? null),
            dataHoraProcessamento: self::texto($json['dataHoraProcessamento'] ?? null),
            xmlNfse: $xml,
            alertas: is_array($json['alertas'] ?? null) ? array_values($json['alertas']) : [],
            erros: self::erros($json['erros'] ?? null),
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

            $lista[] = [
                'codigo' => (string) ($erro['Codigo'] ?? $erro['codigo'] ?? ''),
                'descricao' => (string) ($erro['Descricao'] ?? $erro['descricao'] ?? $erro['Mensagem'] ?? $erro['mensagem'] ?? ''),
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
}
