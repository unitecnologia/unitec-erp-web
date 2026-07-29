<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Support\Erp\ProductLocalizacao;

trait ManagesProductLocalizacao
{
    public function applyLocalizacaoFormatted(?string $formatted): void
    {
        if (! is_array($this->data)) {
            return;
        }

        $formatted = trim((string) ($formatted ?? ''));

        if ($formatted === '') {
            $this->data['loc_corredor'] = '';
            $this->data['loc_modulo'] = '';
            $this->data['loc_prateleira'] = '';
            $this->data['loc_gaveta'] = '';
            $this->data['loc_legado'] = null;
            $this->data['localizacao'] = null;
            $this->form->fill($this->data);

            return;
        }

        $this->data['localizacao'] = $formatted;
        $this->data = ProductLocalizacao::expandIntoForm($this->data);

        $rebuilt = ProductLocalizacao::format(
            $this->data['loc_corredor'] ?? null,
            $this->data['loc_modulo'] ?? null,
            $this->data['loc_prateleira'] ?? null,
            $this->data['loc_gaveta'] ?? null,
        );

        if ($rebuilt !== null) {
            $this->data['localizacao'] = $rebuilt;
        }

        $this->form->fill($this->data);
    }

    public function applyLocalizacaoInputParts(
        ?string $corredor = null,
        ?string $modulo = null,
        ?string $prateleira = null,
        ?string $gaveta = null,
    ): void {
        if (! is_array($this->data)) {
            return;
        }

        $this->data['loc_corredor'] = $this->sanitizeLocInputPart($corredor);
        $this->data['loc_modulo'] = $this->sanitizeLocInputPart($modulo);
        $this->data['loc_prateleira'] = $this->sanitizeLocInputPart($prateleira);
        $this->data['loc_gaveta'] = $this->sanitizeLocInputPart($gaveta);

        // Leitura explícita do DOM: partes vazias = limpar (diferente do sync before-save).
        $this->data['localizacao'] = ProductLocalizacao::format(
            $this->data['loc_corredor'],
            $this->data['loc_modulo'],
            $this->data['loc_prateleira'],
            $this->data['loc_gaveta'],
        );
        $this->data['loc_legado'] = null;
    }

    public function syncLocalizacaoFromParts(): void
    {
        if (! is_array($this->data)) {
            return;
        }

        $formatted = ProductLocalizacao::format(
            $this->data['loc_corredor'] ?? null,
            $this->data['loc_modulo'] ?? null,
            $this->data['loc_prateleira'] ?? null,
            $this->data['loc_gaveta'] ?? null,
        );

        if ($formatted !== null) {
            $this->data['localizacao'] = $formatted;
            $this->data['loc_legado'] = null;

            return;
        }

        // Partes vazias (ex.: aba Localizações fora do DOM / keys ausentes):
        // não apaga localização já presente no state.
        if (filled($this->data['localizacao'] ?? null)) {
            $this->data = ProductLocalizacao::expandIntoForm($this->data);

            return;
        }

        $this->data['localizacao'] = null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function ensureLocalizacaoFormKeys(array $data): array
    {
        foreach (['loc_corredor', 'loc_modulo', 'loc_prateleira', 'loc_gaveta'] as $key) {
            $data[$key] ??= '';
        }

        return ProductLocalizacao::expandIntoForm($data);
    }

    protected function sanitizeLocInputPart(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) ($value ?? ''));

        return substr($digits, 0, 2);
    }
}
