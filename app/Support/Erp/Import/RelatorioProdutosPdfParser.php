<?php

namespace App\Support\Erp\Import;

use Smalot\PdfParser\Parser;

/**
 * Extrai produtos de um Relatório de Produtos em PDF.
 *
 * Fase 1: só EAN completo na linha/bloco.
 * Fase 2: junta EAN partido (multi-linha ou na mesma linha) e aceita sem EAN.
 *
 * @phpstan-type ProdutoImportado array{ean: string, descricao: string, preco: float}
 * @phpstan-type ProdutoPulado array{motivo: string, trecho: string}
 */
final class RelatorioProdutosPdfParser
{
    /**
     * @return array{importar: list<ProdutoImportado>, pulados: list<ProdutoPulado>}
     */
    public function parseArquivo(string $caminho, int $fase = 1): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($caminho);

        return $this->parseTexto($pdf->getText(), $fase);
    }

    /**
     * @return array{importar: list<ProdutoImportado>, pulados: list<ProdutoPulado>}
     */
    public function parseTexto(string $texto, int $fase = 1): array
    {
        $fase = $fase === 2 ? 2 : 1;

        $linhas = preg_split("/\r\n|\n|\r/", $texto) ?: [];
        $linhas = array_map(trim(...), $linhas);

        $blocos = $this->blocos($linhas);
        $importar = [];
        $pulados = [];
        $eanVistos = [];

        foreach ($blocos as $bloco) {
            $classificacao = $this->classificar($bloco, $fase);

            if ($classificacao['status'] !== 'ok') {
                $pulados[] = [
                    'motivo' => $classificacao['status'],
                    'trecho' => mb_substr(implode(' ', $bloco), 0, 160),
                ];

                continue;
            }

            $ean = $classificacao['ean'];

            if ($ean !== '' && isset($eanVistos[$ean])) {
                $importar[$eanVistos[$ean]] = [
                    'ean' => $ean,
                    'descricao' => $classificacao['descricao'],
                    'preco' => $classificacao['preco'],
                ];

                continue;
            }

            if ($ean !== '') {
                $eanVistos[$ean] = count($importar);
            }

            $importar[] = [
                'ean' => $ean,
                'descricao' => $classificacao['descricao'],
                'preco' => $classificacao['preco'],
            ];
        }

        return [
            'importar' => array_values($importar),
            'pulados' => $pulados,
        ];
    }

    /**
     * @param  list<string>  $linhas
     * @return list<list<string>>
     */
    private function blocos(array $linhas): array
    {
        $blocos = [];
        $i = 0;
        $n = count($linhas);

        while ($i < $n) {
            $linha = $linhas[$i];
            $i++;

            if ($linha === '' || $this->eCabecalho($linha) || ! $this->eInicioProduto($linha)) {
                continue;
            }

            $bloco = [$linha];

            while ($i < $n) {
                $prox = $linhas[$i];

                if ($prox === '' || $this->eCabecalho($prox) || $this->eInicioProduto($prox)) {
                    break;
                }

                $bloco[] = $prox;
                $i++;
            }

            $blocos[] = $bloco;
        }

        return $blocos;
    }

    private function eCabecalho(string $linha): bool
    {
        return (bool) preg_match(
            '/^(Mercado |Data:|Hora:|Página:|Relatório|ID Código|Avenida |TOTAL:|-- |\d+\/\d+$)/u',
            $linha,
        );
    }

    private function eInicioProduto(string $linha): bool
    {
        // ID sozinho (EAN partido multi-linha) ou ID + resto na mesma linha.
        // Não usa só \d{3,6}\s — senão "123265" sozinho não abre bloco e cola no anterior.
        if (preg_match('/^\d{3,6}$/', $linha)) {
            return true;
        }

        // Evita sufixos curtos de EAN partido ("93", "99") e continuações "001 PLAX...".
        if (preg_match('/^\d{1,2}[\s\t]/', $linha)) {
            return false;
        }

        // IDs do relatório não têm zero à esquerda (ex.: 5601, 123265).
        return (bool) preg_match('/^[1-9]\d{2,5}[\s\t]/', $linha);
    }

    /**
     * @param  list<string>  $bloco
     * @return array{status: string, ean?: string, descricao?: string, preco?: float}
     */
    private function classificar(array $bloco, int $fase): array
    {
        $partido = $this->detectarEanPartido($bloco);

        if ($partido !== null) {
            if ($fase === 1) {
                return ['status' => 'ean_quebrado'];
            }

            $ean = $partido['ean'];
            $preco = $this->precoValor(implode(' ', $bloco));

            if ($preco === null) {
                return ['status' => 'sem_preco'];
            }

            $descricao = $partido['descricao'];
            if ($descricao === '') {
                $descricao = $ean;
            }

            return [
                'status' => 'ok',
                'ean' => $ean,
                'descricao' => $descricao,
                'preco' => $preco,
            ];
        }

        $primeira = $bloco[0];
        $resto = preg_replace('/^\d{1,6}\s+/', '', $primeira, 1) ?? $primeira;
        // ID sozinho na 1ª linha: EAN pode estar na 2ª (completo, multi-linha descrição).
        if (preg_match('/^\d{3,6}$/', $primeira) && isset($bloco[1])) {
            $resto = $bloco[1];
        }

        $ean = null;
        if (preg_match('/^(?:\d{1,6}\s+)?(\d{8,14})(?:\s|$)/', $resto, $m)) {
            $ean = $m[1];
        } elseif (preg_match('/^\d{8,14}$/', trim($resto))) {
            $ean = trim($resto);
        }

        if ($ean === null) {
            if ($fase === 1) {
                return ['status' => 'sem_ean'];
            }

            $ean = '';
        }

        $preco = $this->precoValor(implode(' ', $bloco));

        if ($preco === null) {
            return ['status' => 'sem_preco'];
        }

        $descricao = $ean !== ''
            ? $this->descricao($bloco, $ean)
            : $this->descricaoSemEan($bloco);

        if ($descricao === '') {
            $descricao = $ean !== '' ? $ean : 'PRODUTO SEM DESCRICAO';
        }

        return [
            'status' => 'ok',
            'ean' => $ean,
            'descricao' => $descricao,
            'preco' => $preco,
        ];
    }

    /**
     * Detecta EAN partido em multi-linha (ID / prefixo / sufixo / resto)
     * ou na mesma linha (ID PREFIXO SUFIXO DESCRICAO).
     *
     * @param  list<string>  $bloco
     * @return array{ean: string, descricao: string}|null
     */
    private function detectarEanPartido(array $bloco): ?array
    {
        // Multi-linha: 123650 / 78903001268 / 99 / ACHOCOLATADO...
        if (count($bloco) >= 3
            && preg_match('/^\d{3,6}$/', $bloco[0])
            && preg_match('/^\d{8,13}$/', $bloco[1])
            && preg_match('/^\d{1,7}$/', $bloco[2])
        ) {
            $juntado = $bloco[1].$bloco[2];

            if (strlen($juntado) < 8 || strlen($juntado) > 14) {
                return null;
            }

            $resto = array_slice($bloco, 3);
            $junto = implode(' ', $resto);
            $junto = $this->removerCaudaNumerica($junto);
            // Remove EAN completo repetido no início da linha de preços.
            $junto = preg_replace('/^'.preg_quote($juntado, '/').'\b\s*/', '', $junto) ?? $junto;
            $junto = trim(preg_replace('/\s+/u', ' ', $junto) ?? $junto);

            return ['ean' => $juntado, 'descricao' => $junto];
        }

        // Mesma linha / 2 linhas: "123265 78962949019" + "93 ..."
        // Prefixo já com 13–14 dígitos é EAN completo (ex.: "7896045103003 3 coraçoes").
        $primeira = $bloco[0];
        $resto = preg_replace('/^\d{1,6}\s+/', '', $primeira, 1) ?? $primeira;

        if (! preg_match('/^(?:\d{1,6}\s+)?(\d{8,12})(?:\s|$)/', $resto, $m)) {
            return null;
        }

        $prefixo = $m[1];

        // Continuação na mesma linha: PREFIXO SUFIXO DESCRICAO
        $apos = substr($resto, strpos($resto, $prefixo) + strlen($prefixo));
        $apos = ltrim($apos);

        if (preg_match('/^(\d{1,7})(?:\s+|$)/', $apos, $m2) && ! preg_match('/^\d{8,}/', $apos)) {
            $juntado = $prefixo.$m2[1];

            if (strlen($juntado) >= 8 && strlen($juntado) <= 14) {
                $desc = substr($apos, strlen($m2[1]));
                $desc = $this->removerCaudaNumerica(trim($desc));
                $desc = trim(preg_replace('/\s+/u', ' ', $desc) ?? $desc);

                return ['ean' => $juntado, 'descricao' => $desc];
            }
        }

        // Continuação na 2ª linha do bloco.
        if (isset($bloco[1])
            && preg_match('/^(\d{1,7})(?:\s|$)/', $bloco[1], $m2)
            && ! preg_match('/^\d{8,}/', $bloco[1])
        ) {
            $juntado = $prefixo.$m2[1];

            if (strlen($juntado) < 8 || strlen($juntado) > 14) {
                return null;
            }

            $junto = implode(' ', $bloco);
            $junto = $this->removerCaudaNumerica($junto);
            $junto = preg_replace('/^\d{1,6}\s+/', '', $junto, 1) ?? $junto;
            $junto = preg_replace('/^'.preg_quote($prefixo, '/').'\b\s*/', '', $junto) ?? $junto;
            $junto = preg_replace('/^'.preg_quote($m2[1], '/').'\b\s*/', '', $junto) ?? $junto;
            $junto = preg_replace('/\b'.preg_quote($juntado, '/').'\b/', '', $junto) ?? $junto;
            $junto = trim(preg_replace('/\s+/u', ' ', $junto) ?? $junto);

            return ['ean' => $juntado, 'descricao' => $junto];
        }

        return null;
    }

    /**
     * @param  list<string>  $bloco
     */
    private function descricao(array $bloco, string $ean): string
    {
        $junto = implode(' ', $bloco);
        $junto = $this->removerCaudaNumerica($junto);
        $junto = preg_replace('/^\d{1,6}\s+/', '', $junto, 1) ?? $junto;

        if (preg_match('/^(\d{1,6})\s+'.preg_quote($ean, '/').'\b/', $junto, $m) === 1) {
            $junto = substr($junto, strlen($m[0]));
        } else {
            $pos = strpos($junto, $ean);

            if ($pos !== false) {
                $junto = substr($junto, $pos + strlen($ean));
            }
        }

        $junto = trim(preg_replace('/\s+/u', ' ', $junto) ?? $junto);

        return $junto;
    }

    /**
     * @param  list<string>  $bloco
     */
    private function descricaoSemEan(array $bloco): string
    {
        $junto = implode(' ', $bloco);
        $junto = $this->removerCaudaNumerica($junto);
        $junto = preg_replace('/^\d{1,6}\s+/', '', $junto, 1) ?? $junto;
        $junto = trim(preg_replace('/\s+/u', ' ', $junto) ?? $junto);

        return $junto;
    }

    private function precoValor(string $texto): ?float
    {
        // Só tokens em formato moeda (9,99 / -4.015,98). Inteiros da descrição
        // (ex.: 01X100 → 100) não entram — senão pega Custo/Valor total.
        $moedas = $this->tokensMoedaADireita($texto, 6);
        $qtd = count($moedas);

        if ($qtd === 0) {
            return null;
        }

        // 1: Valor | 2: Valor + Valor total | 3+: Custo, Valor, [Custo total,] Valor total
        $escolhido = match (true) {
            $qtd === 1 => $moedas[0],
            $qtd === 2 => $moedas[0],
            default => $moedas[1],
        };

        $preco = $this->moedaParaFloat($escolhido);

        // Preço de venda no PDV/ERP não deve ficar negativo (estoque negativo gera totais negativos).
        if ($preco < 0) {
            foreach ($moedas as $token) {
                $candidato = $this->moedaParaFloat($token);
                if ($candidato > 0) {
                    return $candidato;
                }
            }
        }

        return $preco;
    }

    /**
     * @return list<string>
     */
    private function tokensMoedaADireita(string $texto, int $max): array
    {
        $texto = rtrim($texto);
        $tokens = [];

        while ($texto !== '' && count($tokens) < $max) {
            // Exige espaço (ou início) antes do número — não fatia "01X100".
            if (! preg_match('/^(.*?)(?:^|\s)(-?\d{1,3}(?:\.\d{3})*,\d{2})\s*$/s', $texto, $m)) {
                break;
            }

            array_unshift($tokens, $m[2]);
            $texto = rtrim($m[1]);
        }

        return $tokens;
    }

    private function removerCaudaNumerica(string $texto): string
    {
        $texto = rtrim($texto);
        $max = 5;

        while ($texto !== '' && $max-- > 0) {
            if (! preg_match('/^(.*?)(?:^|\s)(-?\d{1,3}(?:\.\d{3})*,\d{2}|-?\d+)\s*$/s', $texto, $m)) {
                break;
            }

            $texto = rtrim($m[1]);
        }

        return $texto;
    }

    private function moedaParaFloat(string $token): float
    {
        $neg = str_starts_with($token, '-');
        $limpo = str_replace(['.', '-'], '', $token);
        $limpo = str_replace(',', '.', $limpo);

        $valor = (float) $limpo;

        return $neg ? -$valor : $valor;
    }
}
