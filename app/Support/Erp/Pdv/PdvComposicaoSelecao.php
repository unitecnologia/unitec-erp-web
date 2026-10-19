<?php

namespace App\Support\Erp\Pdv;

use App\Models\ProductComposition;

/**
 * Seleção de composição no lançamento (Mesas/PDV):
 * - removidos: componentes base desmarcados (não baixar / "SEM …")
 * - adicionais: extras marcados (baixar e cobrar)
 */
final class PdvComposicaoSelecao
{
    /**
     * @param  list<int>  $removidos
     * @param  list<int>  $adicionais
     */
    public function __construct(
        public readonly array $removidos = [],
        public readonly array $adicionais = [],
    ) {}

    public static function vazia(): self
    {
        return new self;
    }

    public static function fromArray(?array $data): self
    {
        if ($data === null || $data === []) {
            return self::vazia();
        }

        $removidos = self::ids($data['removidos'] ?? $data['composicao_removidos'] ?? []);
        $adicionais = self::ids($data['adicionais'] ?? $data['composicao_adicionais'] ?? []);

        return new self($removidos, $adicionais);
    }

    public function deveBaixar(ProductComposition $componente): bool
    {
        $id = (int) $componente->component_product_id;

        if ($id <= 0) {
            return false;
        }

        if ((bool) ($componente->is_adicional ?? false)) {
            return in_array($id, $this->adicionais, true);
        }

        return ! in_array($id, $this->removidos, true);
    }

    /**
     * @return array{removidos: list<int>, adicionais: list<int>}
     */
    public function toArray(): array
    {
        return [
            'removidos' => $this->removidos,
            'adicionais' => $this->adicionais,
        ];
    }

    public function chave(): string
    {
        $r = $this->removidos;
        $a = $this->adicionais;
        sort($r);
        sort($a);

        return json_encode(['r' => $r, 'a' => $a], JSON_THROW_ON_ERROR);
    }

    /**
     * @param  mixed  $raw
     * @return list<int>
     */
    private static function ids(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach ($raw as $value) {
            if (is_array($value)) {
                $value = $value['product_id'] ?? $value['component_product_id'] ?? $value['id'] ?? 0;
            }

            $id = (int) $value;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
