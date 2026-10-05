<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Models\Product;
use App\Support\Erp\BarcodeLookupService;
use App\Support\Erp\Fiscal\NcmCatalogService;
use Filament\Notifications\Notification;
use RuntimeException;

trait ManagesProductBarcodeLookup
{
    public function searchCodigoBarras(?string $codigoBarras = null): void
    {
        if (filled($codigoBarras)) {
            $this->data['codigo_barras'] = trim($codigoBarras);
        }

        if (blank($this->data['codigo_barras'] ?? null)) {
            $this->data['codigo_barras'] = $this->form->getState()['codigo_barras'] ?? null;
        }

        $barcode = preg_replace('/\D/', '', (string) ($this->data['codigo_barras'] ?? ''));

        if (strlen($barcode) < 8) {
            Notification::make()
                ->title('Informe um código de barras válido.')
                ->warning()
                ->send();

            return;
        }

        $excludeProductId = $this->isEditingProduct() ? $this->record?->getKey() : null;

        try {
            $fields = app(BarcodeLookupService::class)->fetch($barcode, $excludeProductId);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Consulta de código de barras')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->data['codigo_barras'] = $barcode;

        $source = (string) ($fields['source'] ?? 'upcitemdb');
        $existingProductId = $fields['existing_product_id'] ?? null;
        $fotoUrl = $fields['foto_url'] ?? null;
        $ccgResumo = null;
        $mantidos = [];
        $ncmConsulta = '';

        if ($source === 'ccg') {
            $ccgResumo = [
                'gtin' => (string) ($fields['gtin'] ?? ''),
                'tp_gtin' => (string) ($fields['tp_gtin'] ?? ''),
                'descricao' => (string) ($fields['descricao'] ?? ''),
                'ncm' => (string) ($fields['ncm'] ?? ''),
                'cest' => (string) ($fields['cest'] ?? ''),
                'cests' => is_array($fields['cests'] ?? null) ? $fields['cests'] : [],
            ];

            $ncmConsulta = preg_replace('/\D/', '', (string) ($fields['ncm'] ?? '')) ?? '';
            unset($fields['ncm'], $fields['ncm_descricao']);

            if ($ncmConsulta !== '' && ! $this->ncmAtualEhPlaceholder()) {
                $mantidos[] = 'NCM';
                $ncmConsulta = '';
            }

            foreach (['descricao' => 'Descrição', 'cest' => 'CEST'] as $key => $rotulo) {
                if (filled($this->data[$key] ?? null) && filled($fields[$key] ?? null)) {
                    $mantidos[] = $rotulo;
                    unset($fields[$key]);
                }
            }
        }

        unset($fields['foto_url'], $fields['source'], $fields['existing_product_id'], $fields['gtin'], $fields['tp_gtin'], $fields['cests']);

        if (
            $source === 'internal'
            && $existingProductId
            && ! $this->isEditingProduct()
        ) {
            $existing = Product::query()->find($existingProductId);

            if ($existing) {
                $this->data['codigo_barras'] = $barcode;
                $this->form->fill($this->data);
                $this->openDuplicateConfirmModal($existing, 'codigo_barras');

                return;
            }
        }

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'peso_kg') {
                $this->data[$key] = $this->formatBrDecimal($value, 3);

                continue;
            }

            if ($key === 'preco_venda') {
                $this->data[$key] = $this->formatBrDecimal($value, 2);

                continue;
            }

            $this->data[$key] = $value;
        }

        $ncmNaoEncontrado = null;

        if ($source === 'ccg' && $ncmConsulta !== '') {
            $ncmNaoEncontrado = $this->aplicarNcmRetornadoPelaConsulta($ncmConsulta);
        }

        $this->setPendingProductFotoFromUrl(is_string($fotoUrl) && $fotoUrl !== '' ? $fotoUrl : null);

        $this->form->fill($this->data);

        Notification::make()
            ->title('Produto encontrado')
            ->body($this->barcodeLookupMessage($source, $ccgResumo, $mantidos, $ncmNaoEncontrado))
            ->success()
            ->send();

        if ($ncmNaoEncontrado !== null) {
            Notification::make()
                ->title('NCM não encontrado no cadastro fiscal')
                ->body('A SVRS retornou o NCM ' . $ncmNaoEncontrado . ', que não existe na tabela local de NCM. Nenhum NCM foi associado.')
                ->warning()
                ->send();
        }

        $this->dispatch('erp-masks-refresh');
    }

    /**
     * @param  list<string>  $mantidos
     * @param  array{gtin: string, tp_gtin: string, descricao: string, ncm: string, cest: string, cests: list<string>}|null  $ccgResumo
     */
    protected function barcodeLookupMessage(string $source, ?array $ccgResumo, array $mantidos = [], ?string $ncmNaoEncontrado = null): string
    {
        if ($source !== 'ccg' || $ccgResumo === null) {
            return match ($source) {
                'internal' => 'Dados copiados de produto já cadastrado neste ERP.',
                'upcitemdb' => 'Dados preenchidos via consulta externa. Confira descrição e NCM antes de salvar.',
                'openfoodfacts' => 'Dados preenchidos via Open Food Facts. Confira descrição e NCM antes de salvar.',
                default => 'Dados preenchidos. Confira descrição e NCM antes de salvar.',
            };
        }

        $partes = array_filter([
            'SEFAZ/SVRS – Consulta GTIN',
            $ccgResumo['gtin'] !== '' ? 'GTIN ' . $ccgResumo['gtin'] : null,
            $ccgResumo['tp_gtin'] !== '' ? 'tpGTIN ' . $ccgResumo['tp_gtin'] : null,
            $ccgResumo['descricao'] !== '' ? $ccgResumo['descricao'] : null,
            $ccgResumo['ncm'] !== '' ? 'NCM ' . $ccgResumo['ncm'] : null,
            $ccgResumo['cest'] !== '' ? 'CEST ' . $ccgResumo['cest'] : null,
        ]);

        $mensagem = implode(' · ', $partes);

        $outros = array_values(array_filter(
            $ccgResumo['cests'],
            fn (string $cest): bool => $cest !== '' && $cest !== $ccgResumo['cest'],
        ));

        if ($outros !== []) {
            $mensagem .= ' (outros CEST: ' . implode(', ', $outros) . ')';
        }

        if ($mantidos !== []) {
            $mensagem .= ' ' . implode(', ', $mantidos) . ' já informado(s) foram mantidos.';
        }

        if ($ncmNaoEncontrado !== null && $ncmNaoEncontrado !== '') {
            $mensagem .= ' NCM ' . $ncmNaoEncontrado . ' não foi encontrado no cadastro fiscal.';
        }

        return $mensagem . ' Confira os dados antes de salvar.';
    }

    protected function ncmAtualEhPlaceholder(): bool
    {
        $digits = preg_replace('/\D/', '', (string) ($this->data['ncm'] ?? '')) ?? '';

        if ($digits === '') {
            return true;
        }

        return str_pad($digits, 8, '0', STR_PAD_LEFT) === '00000000';
    }

    /**
     * Aplica o NCM da consulta pelo mesmo caminho da lupa: código exato do catálogo e sua descrição.
     * Retorna o código quando ele não existe na tabela local.
     */
    protected function aplicarNcmRetornadoPelaConsulta(string $codigo): ?string
    {
        $catalog = app(NcmCatalogService::class);
        $codigo = $catalog->normalizeCodigo($codigo);

        if ($codigo === null || $codigo === '00000000') {
            return null;
        }

        $record = $catalog->findByCodigo($codigo);

        if ($record === null) {
            return $codigo;
        }

        $this->applyNcmToProductForm((string) $record->codigo, (string) $record->descricao);

        return null;
    }
}
