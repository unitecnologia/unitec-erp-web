<?php

namespace App\Support\Erp\Nfse;

use DOMDocument;
use LibXMLError;

class NfseDpsXmlValidador
{
    /**
     * @return array{valido: bool, erros: list<array{tag: string, posicao: string, valor: string, regra: string}>}
     */
    public function validar(string $xml): array
    {
        $xsd = base_path('resources/nfse/xsd/v1.01/DPS_v1.01.xsd');

        if (! is_file($xsd)) {
            return [
                'valido' => false,
                'erros' => [[
                    'tag' => 'DPS',
                    'posicao' => 'schema',
                    'valor' => $xsd,
                    'regra' => 'XSD oficial v1.01 não encontrado para validação local.',
                ]],
            ];
        }

        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new DOMDocument;
        $carregou = $doc->loadXML($xml, LIBXML_NONET);

        if (! $carregou) {
            $erros = $this->formatar(libxml_get_errors(), $xml);
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);

            return ['valido' => false, 'erros' => $this->primeiroConjunto($erros)];
        }

        $flags = LIBXML_NONET;
        $valido = $doc->schemaValidate($xsd, $flags);
        $erros = $valido ? [] : $this->formatar(libxml_get_errors(), $xml);

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return [
            'valido' => $valido && $erros === [],
            'erros' => $this->primeiroConjunto($erros),
        ];
    }

    /**
     * @param  list<LibXMLError>  $erros
     * @return list<array{tag: string, posicao: string, valor: string, regra: string}>
     */
    private function formatar(array $erros, string $xml): array
    {
        $linhas = preg_split("/\r\n|\n|\r/", $xml) ?: [];
        $formatados = [];

        foreach ($erros as $erro) {
            if ($erro->level < LIBXML_ERR_ERROR) {
                continue;
            }

            $mensagem = trim($erro->message);
            $tag = $this->tag($mensagem);
            $linha = max(0, (int) $erro->line);
            $coluna = max(0, (int) $erro->column);
            $trecho = $linha > 0 ? trim((string) ($linhas[$linha - 1] ?? '')) : '';

            $formatados[] = [
                'tag' => $tag,
                'posicao' => 'linha '.$linha.', coluna '.$coluna,
                'valor' => $this->valor($trecho, $mensagem),
                'regra' => $this->regra($mensagem, $tag),
            ];
        }

        return $formatados;
    }

    /**
     * @param  list<array{tag: string, posicao: string, valor: string, regra: string}>  $erros
     * @return list<array{tag: string, posicao: string, valor: string, regra: string}>
     */
    private function primeiroConjunto(array $erros): array
    {
        if ($erros === []) {
            return [];
        }

        $linha = $this->linha($erros[0]['posicao']);
        $conjunto = [];

        foreach ($erros as $erro) {
            if ($this->linha($erro['posicao']) !== $linha) {
                break;
            }

            $conjunto[] = $erro;
        }

        return $conjunto;
    }

    private function tag(string $mensagem): string
    {
        if (preg_match("/Element '\\{[^']+\\}([^']+)'/", $mensagem, $elemento) === 1) {
            return $elemento[1];
        }

        if (preg_match("/Expected is(?: one of)? \\( (.+) \\)\\.?$/", $mensagem, $esperado) === 1) {
            return $this->nomeLocal($esperado[1]);
        }

        return 'DPS';
    }

    private function nomeLocal(string $lista): string
    {
        $nomes = [];

        if (preg_match_all('/\{[^}]+\}([^,\\s]+)/', $lista, $encontrados) > 0) {
            $nomes = $encontrados[1];
        }

        $nomes = array_values(array_unique($nomes));

        return $nomes === [] ? trim($lista) : implode(', ', $nomes);
    }

    private function valor(string $trecho, string $mensagem): string
    {
        if (str_contains($mensagem, 'Missing child element')) {
            return '(ausente)';
        }

        if (preg_match('/>([^<]*)</', $trecho, $conteudo) === 1 && trim($conteudo[1]) !== '') {
            return trim($conteudo[1]);
        }

        return $trecho !== '' ? $trecho : '(ausente)';
    }

    private function regra(string $mensagem, string $tag): string
    {
        $tipo = $this->tipoDoElemento($tag);
        $restricao = $tipo !== null ? $this->restricao($tipo) : null;
        $texto = preg_replace('/\{[^}]+\}/', '', $mensagem) ?? $mensagem;
        $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
        $esperado = $this->esperado($mensagem);

        if ($esperado !== null && $esperado !== $tag) {
            $tipoEsperado = $this->tipoDoElemento($esperado);
            $restricaoEsperada = $tipoEsperado !== null ? $this->restricao($tipoEsperado) : null;
            $regraEsperada = 'Esperado antes, na sequência de TCInfDPS: '.$esperado
                .($tipoEsperado !== null ? ' ('.$tipoEsperado.')' : '')
                .($restricaoEsperada !== null ? '. '.$restricaoEsperada : '')
                .'. Valor não informado: não há esse dado no builder.';

            return $texto.' '.$regraEsperada;
        }

        if ($restricao === null) {
            return $texto;
        }

        return $texto.' Regra XSD: elemento '.$tag.' tipo '.$tipo.'. '.$restricao;
    }

    private function esperado(string $mensagem): ?string
    {
        if (preg_match("/Expected is(?: one of)? \\( (.+) \\)\\.?$/", $mensagem, $esperado) !== 1) {
            return null;
        }

        $nome = $this->nomeLocal($esperado[1]);

        return str_contains($nome, ',') ? null : $nome;
    }

    private function tipoDoElemento(string $tag): ?string
    {
        $primeiro = trim(explode(',', $tag)[0]);
        $xsd = (string) file_get_contents(base_path('resources/nfse/xsd/v1.01/tiposComplexos_v1.01.xsd'));

        if (preg_match('/name="'.preg_quote($primeiro, '/').'" type="([^"]+)"/', $xsd, $tipo) === 1) {
            return $tipo[1];
        }

        return null;
    }

    private function restricao(string $tipo): ?string
    {
        $xsd = (string) file_get_contents(base_path('resources/nfse/xsd/v1.01/tiposSimples_v1.01.xsd'));

        if (preg_match('/simpleType name="'.preg_quote($tipo, '/').'"(.*?)<\\/xs:simpleType>/s', $xsd, $bloco) !== 1) {
            return null;
        }

        $trecho = $bloco[1];
        $partes = [];

        if (preg_match('/<xs:documentation>(.*?)<\\/xs:documentation>/s', $trecho, $doc) === 1) {
            $partes[] = trim(preg_replace('/\s+/', ' ', $doc[1]) ?? '');
        }

        if (preg_match_all('/enumeration value="([^"]+)"/', $trecho, $enums) > 0) {
            $partes[] = 'enumeração: '.implode(' | ', $enums[1]);
        }

        if (preg_match('/pattern value="([^"]+)"/', $trecho, $pattern) === 1) {
            $partes[] = 'pattern: '.$pattern[1];
        }

        $partes = array_values(array_filter($partes, static fn (string $parte): bool => $parte !== ''));

        return $partes === [] ? null : implode(' ', $partes);
    }

    private function linha(string $posicao): int
    {
        if (preg_match('/linha (\d+)/', $posicao, $linha) !== 1) {
            return 0;
        }

        return (int) $linha[1];
    }
}
