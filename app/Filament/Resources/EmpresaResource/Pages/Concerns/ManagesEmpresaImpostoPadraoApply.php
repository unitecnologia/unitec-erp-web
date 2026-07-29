<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Models\FiscalClassificacaoTributaria;
use App\Models\Product;
use App\Support\Erp\BrDecimal;
use Filament\Notifications\Notification;

trait ManagesEmpresaImpostoPadraoApply
{
    public const IMPOSTO_PADRAO_APPLY_BATCH = 300;

    public bool $impostoPadraoApplyConfirmOpen = false;

    public bool $impostoPadraoApplyProgressOpen = false;

    public string $impostoPadraoApplyEmpresaField = '';

    public string $impostoPadraoApplyLabel = '';

    public string $impostoPadraoApplyValueDisplay = '';

    public string $impostoPadraoApplyProductColumn = '';

    public mixed $impostoPadraoApplyValue = null;

    public int $impostoPadraoApplyCursor = 0;

    public int $impostoPadraoApplyTotal = 0;

    public int $impostoPadraoApplyCurrent = 0;

    public int $impostoPadraoApplyPercent = 0;

    public string $impostoPadraoApplyProgressLabel = '';

    public string $impostoPadraoApplyProgressDetail = '';

    /**
     * @return array<string, array{label: string, column: string, type: string, pad?: int}>
     */
    protected function impostoPadraoApplyFieldMap(): array
    {
        return [
            'param_imp_cfop_venda' => ['label' => 'CFOP (ICMS Interno)', 'column' => 'cfop_interno', 'type' => 'string'],
            'param_imp_origem' => ['label' => 'Origem', 'column' => 'origem', 'type' => 'int'],
            'param_imp_icms_cst' => ['label' => 'CST (ICMS Interno)', 'column' => 'cst_icms', 'type' => 'pad', 'pad' => 3],
            'param_imp_csosn' => ['label' => 'CSOSN (ICMS Interno)', 'column' => 'csosn', 'type' => 'pad', 'pad' => 3],
            'param_imp_icms_aliquota' => ['label' => 'Alíq. % (ICMS Interno)', 'column' => 'aliq_icms', 'type' => 'decimal2'],
            'param_imp_cfop_externo' => ['label' => 'CFOP (ICMS Externo)', 'column' => 'cfop_externo', 'type' => 'string'],
            'param_imp_icms_cst_externo' => ['label' => 'CST (ICMS Externo)', 'column' => 'cst_externo', 'type' => 'pad', 'pad' => 3],
            'param_imp_csosn_externo' => ['label' => 'CSOSN (ICMS Externo)', 'column' => 'csosn_externo', 'type' => 'pad', 'pad' => 3],
            'param_imp_icms_aliquota_externo' => ['label' => 'Alíq. % (ICMS Externo)', 'column' => 'aliq_icms_externo', 'type' => 'decimal2'],
            'param_imp_pis_cst' => ['label' => 'CST Ent. (PIS)', 'column' => 'cst_entrada', 'type' => 'pad', 'pad' => 2],
            'param_imp_cofins_cst' => ['label' => 'CST Saída', 'column' => 'cst_saida', 'type' => 'pad', 'pad' => 2],
            'param_imp_cst_cofins' => ['label' => 'CST COFINS', 'column' => 'cst_cofins', 'type' => 'pad', 'pad' => 2],
            'param_imp_pis_aliquota' => ['label' => 'PIS %', 'column' => 'aliq_pis', 'type' => 'decimal2'],
            'param_imp_cofins_aliquota' => ['label' => 'COFINS %', 'column' => 'aliq_cofins', 'type' => 'decimal2'],
            'param_imp_ipi_cst' => ['label' => 'CST (IPI)', 'column' => 'cst_ipi', 'type' => 'pad', 'pad' => 2],
            'param_imp_ipi_aliquota' => ['label' => 'Alíquota (IPI)', 'column' => 'aliq_ipi', 'type' => 'decimal2'],
            'param_imp_cod_enq_ipi' => ['label' => 'Cód. Enq. (IPI)', 'column' => 'cod_enq_ipi', 'type' => 'nullable_string'],
            'param_imp_fcp_pct' => ['label' => '% FCP', 'column' => 'fcp_pct', 'type' => 'decimal2'],
            'param_imp_mva_pct' => ['label' => '% MVA', 'column' => 'mva_pct', 'type' => 'decimal2'],
            'param_imp_mva_normal' => ['label' => '% MVA N.', 'column' => 'mva_normal', 'type' => 'decimal4'],
            'param_imp_reducao_base_pct' => ['label' => '% Base Red.', 'column' => 'reducao_base_pct', 'type' => 'decimal2'],
            'param_imp_cod_beneficio' => ['label' => 'Cód. Benef.', 'column' => 'cod_beneficio', 'type' => 'nullable_string'],
            'param_imp_tipo_tributacao' => ['label' => 'Tipo Trib.', 'column' => 'tipo_tributacao', 'type' => 'nullable_string'],
            'param_imp_icms_diferido' => ['label' => 'ICMS Dif.', 'column' => 'icms_diferido', 'type' => 'decimal4'],
            'param_imp_aliq_deson' => ['label' => 'Alíq. Deson.', 'column' => 'aliq_deson', 'type' => 'decimal4'],
            'param_imp_motivo_desoneracao' => ['label' => 'Mot. Deson.', 'column' => 'motivo_desoneracao', 'type' => 'int'],
            'param_imp_iva_cst' => ['label' => 'CST IBS/CBS', 'column' => 'iva_cst', 'type' => 'nullable_string'],
            'param_imp_cclass_trib' => ['label' => 'Classificação Tributária', 'column' => 'cclass_trib', 'type' => 'nullable_string'],
            'param_imp_aliq_ibs_uf' => ['label' => 'Aliq IBS UF', 'column' => 'aliq_ibs_uf', 'type' => 'decimal4'],
            'param_imp_aliq_cbs' => ['label' => 'Aliq CBS', 'column' => 'aliq_cbs', 'type' => 'decimal4'],
            'param_imp_aliq_ibs_mun' => ['label' => 'Aliq IBS Mun', 'column' => 'aliq_ibs_mun', 'type' => 'decimal4'],
            'param_imp_aliq_adrem_ibs' => ['label' => 'Aliq Adrem IBS', 'column' => 'aliq_adrem_ibs', 'type' => 'decimal4'],
            'param_imp_aliq_adrem_cbs' => ['label' => 'Aliq Adrem CBS', 'column' => 'aliq_adrem_cbs', 'type' => 'decimal4'],
            'param_imp_reducao_cbs' => ['label' => 'Redução CBS', 'column' => 'reducao_cbs', 'type' => 'decimal4'],
            'param_imp_reducao_ibs' => ['label' => 'Redução IBS', 'column' => 'reducao_ibs', 'type' => 'decimal4'],
        ];
    }

    public function pedirAplicarImpostoPadraoEmProdutos(string $empresaField): void
    {
        if ($this->impostoPadraoApplyProgressOpen) {
            return;
        }

        $map = $this->impostoPadraoApplyFieldMap();

        if (! isset($map[$empresaField])) {
            Notification::make()
                ->title('Campo não suportado.')
                ->warning()
                ->send();

            return;
        }

        $meta = $map[$empresaField];
        $raw = $this->data[$empresaField] ?? null;
        $value = $this->normalizeImpostoPadraoApplyValue($raw, $meta);
        $display = $this->formatImpostoPadraoApplyDisplay($value, $meta, $raw);

        $this->impostoPadraoApplyEmpresaField = $empresaField;
        $this->impostoPadraoApplyLabel = $meta['label'];
        $this->impostoPadraoApplyProductColumn = $meta['column'];
        $this->impostoPadraoApplyValue = $value;
        $this->impostoPadraoApplyValueDisplay = $display;
        $this->impostoPadraoApplyConfirmOpen = true;
    }

    public function cancelAplicarImpostoPadraoEmProdutos(): void
    {
        $this->impostoPadraoApplyConfirmOpen = false;
        $this->resetImpostoPadraoApplyPayload();
    }

    public function confirmAplicarImpostoPadraoEmProdutos(): void
    {
        if ($this->impostoPadraoApplyProductColumn === '' || $this->impostoPadraoApplyEmpresaField === '') {
            $this->cancelAplicarImpostoPadraoEmProdutos();

            return;
        }

        $this->impostoPadraoApplyConfirmOpen = false;

        $total = (int) Product::query()->count();

        if ($total <= 0) {
            Notification::make()
                ->title('Nenhum produto cadastrado.')
                ->warning()
                ->send();
            $this->resetImpostoPadraoApplyPayload();

            return;
        }

        $this->impostoPadraoApplyCursor = 0;
        $this->impostoPadraoApplyTotal = $total;
        $this->impostoPadraoApplyCurrent = 0;
        $this->impostoPadraoApplyPercent = 0;
        $this->impostoPadraoApplyProgressLabel = 'Preparando aplicação…';
        $this->impostoPadraoApplyProgressDetail = '0 de '.$this->impostoPadraoApplyTotal.' produto(s)';
        $this->impostoPadraoApplyProgressOpen = true;

        $this->js(<<<'JS'
            (async () => {
                try {
                    while (await $wire.processarProximoImpostoPadraoProduto()) {
                        // lotes no servidor — sem pausa artificial
                    }
                } catch (e) {
                    console.error(e);
                }
            })();
        JS);
    }

    public function processarProximoImpostoPadraoProduto(): bool
    {
        if (! $this->impostoPadraoApplyProgressOpen) {
            return false;
        }

        if ($this->impostoPadraoApplyProductColumn === '') {
            $this->finalizarImpostoPadraoApplyProgresso();

            return false;
        }

        $ids = Product::query()
            ->where('id', '>', $this->impostoPadraoApplyCursor)
            ->orderBy('id')
            ->limit(self::IMPOSTO_PADRAO_APPLY_BATCH)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->finalizarImpostoPadraoApplyProgresso();

            return false;
        }

        $payload = [
            $this->impostoPadraoApplyProductColumn => $this->impostoPadraoApplyValue,
        ];

        if ($this->impostoPadraoApplyProductColumn === 'cclass_trib') {
            $payload['cclass_trib_descricao'] = $this->resolveCclassTribDescricao(
                is_string($this->impostoPadraoApplyValue) ? $this->impostoPadraoApplyValue : null,
            );
        }

        Product::query()
            ->whereIn('id', $ids->all())
            ->update($payload);

        $this->impostoPadraoApplyCursor = (int) $ids->last();
        $this->impostoPadraoApplyCurrent = min(
            $this->impostoPadraoApplyTotal,
            $this->impostoPadraoApplyCurrent + $ids->count(),
        );
        $this->impostoPadraoApplyPercent = (int) round(
            ($this->impostoPadraoApplyCurrent / max(1, $this->impostoPadraoApplyTotal)) * 100
        );

        $amostra = Product::query()
            ->whereKey($this->impostoPadraoApplyCursor)
            ->first(['codigo', 'descricao']);

        $this->impostoPadraoApplyProgressLabel = 'Aplicando em '.$this->impostoPadraoApplyCurrent
            .' de '.$this->impostoPadraoApplyTotal.'…';
        $this->impostoPadraoApplyProgressDetail = $amostra
            ? trim((string) ($amostra->codigo.' — '.$amostra->descricao))
            : 'Lote até #'.$this->impostoPadraoApplyCursor;

        if ($ids->count() < self::IMPOSTO_PADRAO_APPLY_BATCH) {
            $this->finalizarImpostoPadraoApplyProgresso();

            return false;
        }

        return true;
    }

    protected function finalizarImpostoPadraoApplyProgresso(): void
    {
        $total = $this->impostoPadraoApplyTotal;
        $label = $this->impostoPadraoApplyLabel;
        $display = $this->impostoPadraoApplyValueDisplay;

        $this->impostoPadraoApplyProgressOpen = false;
        $this->resetImpostoPadraoApplyPayload();

        Notification::make()
            ->title('Imposto aplicado nos produtos.')
            ->body(sprintf('%s = %s em %d produto(s).', $label, $display, $total))
            ->success()
            ->send();
    }

    protected function resetImpostoPadraoApplyPayload(): void
    {
        $this->impostoPadraoApplyEmpresaField = '';
        $this->impostoPadraoApplyLabel = '';
        $this->impostoPadraoApplyValueDisplay = '';
        $this->impostoPadraoApplyProductColumn = '';
        $this->impostoPadraoApplyValue = null;
        $this->impostoPadraoApplyCursor = 0;
        $this->impostoPadraoApplyTotal = 0;
        $this->impostoPadraoApplyCurrent = 0;
        $this->impostoPadraoApplyPercent = 0;
        $this->impostoPadraoApplyProgressLabel = '';
        $this->impostoPadraoApplyProgressDetail = '';
    }

    /**
     * @param  array{label: string, column: string, type: string, pad?: int}  $meta
     */
    protected function normalizeImpostoPadraoApplyValue(mixed $raw, array $meta): mixed
    {
        $type = $meta['type'];

        if ($type === 'nullable_string') {
            $trimmed = trim((string) ($raw ?? ''));

            return $trimmed === '' ? null : $trimmed;
        }

        return match ($type) {
            'int' => (int) round(BrDecimal::parse($raw, 2)),
            'decimal2' => BrDecimal::parse($raw, 2),
            'decimal4' => BrDecimal::parse($raw, 4),
            'pad' => str_pad(
                substr(trim((string) ($raw ?? '')), 0, (int) ($meta['pad'] ?? 3)),
                (int) ($meta['pad'] ?? 3),
                '0',
                STR_PAD_LEFT,
            ),
            default => trim((string) ($raw ?? '')),
        };
    }

    /**
     * @param  array{label: string, column: string, type: string, pad?: int}  $meta
     */
    protected function formatImpostoPadraoApplyDisplay(mixed $value, array $meta, mixed $raw): string
    {
        if ($value === null || $value === '') {
            $rawDisplay = trim((string) ($raw ?? ''));

            return $rawDisplay !== '' ? $rawDisplay : '(vazio)';
        }

        return match ($meta['type']) {
            'decimal2' => number_format((float) $value, 2, ',', '.'),
            'decimal4' => number_format((float) $value, 4, ',', '.'),
            'int' => (string) (int) $value,
            default => (string) $value,
        };
    }

    protected function resolveCclassTribDescricao(?string $codigo): ?string
    {
        $codigo = trim((string) $codigo);

        if ($codigo === '') {
            return null;
        }

        $descricao = FiscalClassificacaoTributaria::query()
            ->where('codigo', $codigo)
            ->value('descricao');

        $descricao = trim((string) ($descricao ?? ''));

        return $descricao !== '' ? $descricao : null;
    }
}
