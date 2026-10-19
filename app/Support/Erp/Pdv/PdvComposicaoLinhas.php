<?php

namespace App\Support\Erp\Pdv;

use App\Models\ProductComposition;

/**
 * Textos de composição para comanda/impressão (fora de obs):
 * - SEM [NOME] — remoções
 * - ADICIONAL: [NOME] (+ R$ X,XX) — extras cobrados
 * Composição normal (incluída) não gera linha.
 */
final class PdvComposicaoLinhas
{
    /**
     * @return list<array{tipo: string, product_id: int, texto: string, valor?: float}>
     */
    public static function montar(int $productId, PdvComposicaoSelecao $selecao): array
    {
        if ($productId <= 0 || ($selecao->removidos === [] && $selecao->adicionais === [])) {
            return [];
        }

        $comps = ProductComposition::query()
            ->where('product_id', $productId)
            ->with(['componentProduct:id,descricao'])
            ->get(['component_product_id', 'is_adicional', 'preco', 'quantidade']);

        /** @var array<int, ProductComposition> $byId */
        $byId = [];
        foreach ($comps as $comp) {
            $byId[(int) $comp->component_product_id] = $comp;
        }

        $out = [];

        foreach ($selecao->removidos as $id) {
            $nome = self::nome($byId[$id] ?? null, $id);
            $out[] = [
                'tipo' => 'sem',
                'product_id' => $id,
                'texto' => 'SEM '.$nome,
            ];
        }

        foreach ($selecao->adicionais as $id) {
            $comp = $byId[$id] ?? null;
            $nome = self::nome($comp, $id);
            $valor = $comp !== null
                ? round(((float) $comp->preco) * ((float) $comp->quantidade), 2)
                : 0.0;
            $out[] = [
                'tipo' => 'adicional',
                'product_id' => $id,
                'valor' => $valor,
                'texto' => 'ADICIONAL: '.$nome.' (+ R$ '.number_format($valor, 2, ',', '').')',
            ];
        }

        return $out;
    }

    /**
     * Textos prontos do item da mesa (usa cache gravado ou remonta pelos IDs).
     *
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public static function textosDoItem(array $item): array
    {
        $gravadas = $item['composicao_linhas'] ?? null;

        if (is_array($gravadas) && $gravadas !== []) {
            $textos = [];
            foreach ($gravadas as $linha) {
                if (is_string($linha) && trim($linha) !== '') {
                    $textos[] = mb_strtoupper(trim($linha), 'UTF-8');

                    continue;
                }
                if (is_array($linha)) {
                    $texto = trim((string) ($linha['texto'] ?? ''));
                    if ($texto !== '') {
                        $textos[] = mb_strtoupper($texto, 'UTF-8');
                    }
                }
            }

            if ($textos !== []) {
                return $textos;
            }
        }

        $selecao = PdvComposicaoSelecao::fromArray($item);
        $productId = (int) ($item['product_id'] ?? 0);

        if ($productId <= 0 || ($selecao->removidos === [] && $selecao->adicionais === [])) {
            return [];
        }

        return array_values(array_map(
            static fn (array $linha): string => (string) $linha['texto'],
            self::montar($productId, $selecao),
        ));
    }

    /**
     * Remove trechos automáticos (SEM … / ADICIONAL: …) da observação digitada.
     */
    public static function limparObsManual(string $obs): string
    {
        $obs = trim(preg_replace('/\s+/u', ' ', $obs) ?? '');
        if ($obs === '') {
            return '';
        }

        $partes = preg_split('/\s*\/\s*/u', $obs) ?: [];
        $manuais = [];

        foreach ($partes as $parte) {
            $parte = trim((string) $parte);
            if ($parte === '') {
                continue;
            }
            if (preg_match('/^SEM\s+/iu', $parte) === 1) {
                continue;
            }
            if (preg_match('/^ADICIONAL\s*:/iu', $parte) === 1) {
                continue;
            }
            $manuais[] = $parte;
        }

        return implode(' / ', $manuais);
    }

    private static function nome(?ProductComposition $comp, int $id): string
    {
        $descricao = trim((string) ($comp?->componentProduct?->descricao ?? ''));
        if ($descricao === '') {
            $descricao = 'ITEM '.$id;
        }

        return mb_strtoupper($descricao, 'UTF-8');
    }
}
