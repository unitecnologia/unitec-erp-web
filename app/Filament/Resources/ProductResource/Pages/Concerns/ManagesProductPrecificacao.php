<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpContext;
use Filament\Notifications\Notification;

trait ManagesProductPrecificacao
{
    public bool $productPrecificacaoOpen = false;

    /** @var array<string, mixed> */
    public array $precificacao = [];

    public function openProductPrecificacao(): void
    {
        $data = $this->data ?? [];
        $custo = BrDecimal::parse($data['preco_custo'] ?? 0, 2);
        $compra = BrDecimal::parse($data['preco_compra'] ?? 0, 2);
        $pctCustos = BrDecimal::parse($data['pct_custos'] ?? 0, 2);
        $margemVarejo = BrDecimal::parse($data['pct_lucro'] ?? 0, 2);
        $comissao = 0.0;
        $desconto = 0.0;

        $varejo = BrDecimal::parse($data['preco_venda'] ?? 0, 2);
        $atacado = BrDecimal::parse($data['preco_atacado'] ?? 0, 2);
        $especial = BrDecimal::parse($data['preco_especial'] ?? 0, 2);

        $margemAtacado = $custo > 0 && $atacado > 0
            ? round((($atacado * 100) / $custo) - 100, 2)
            : $margemVarejo;
        $margemEspecial = $custo > 0 && $especial > 0
            ? round((($especial * 100) / $custo) - 100, 2)
            : $margemVarejo;

        $empresa = ErpContext::statusBar()['Empresa'] ?? '—';

        $this->precificacao = [
            'empresa' => (string) $empresa,
            'codigo' => (string) ($data['codigo'] ?? ''),
            'codigo_barras' => (string) ($data['codigo_barras'] ?? ''),
            'referencia' => (string) ($data['referencia'] ?? ''),
            'descricao' => (string) ($data['descricao'] ?? ''),
            'preco_compra' => $this->formatBrDecimal($compra, 2),
            'pct_custos' => $this->formatBrDecimal($pctCustos, 2),
            'custos_rs' => $this->formatBrDecimal($this->valorFromPercentual($compra, $pctCustos), 2),
            'frete_pct' => $this->formatBrDecimal(0, 2),
            'frete_rs' => $this->formatBrDecimal(0, 2),
            'outras_pct' => $this->formatBrDecimal(0, 2),
            'outras_desp' => $this->formatBrDecimal(0, 2),
            'custo_pct_total' => $this->formatBrDecimal($pctCustos, 2),
            'preco_custo' => $this->formatBrDecimal($custo, 2),
            'niveis' => [
                'varejo' => $this->makeNivelPrecificacao($comissao, $desconto, $margemVarejo, $custo, $varejo),
                'atacado' => $this->makeNivelPrecificacao($comissao, $desconto, max(0, $margemAtacado), $custo, $atacado),
                'especial' => $this->makeNivelPrecificacao($comissao, $desconto, max(0, $margemEspecial), $custo, $especial),
            ],
        ];

        // Atualiza só o bloco de custo. Não recalcular níveis pela margem aqui:
        // isso zerava o Praticado quando o custo era 0 (preço do cadastro sumia).
        $this->recalcularPrecificacaoCusto(false);

        foreach (['varejo' => $varejo, 'atacado' => $atacado, 'especial' => $especial] as $nivel => $preco) {
            if ($preco > 0) {
                $this->precificacao['niveis'][$nivel]['praticado'] = $this->formatBrDecimal($preco, 2);
                $this->recalcularNivelPrecificacao($nivel, 'praticado');
            } else {
                $this->recalcularNivelPrecificacao($nivel, 'margem');
            }
        }

        $this->touchPrecificacao();
        $this->productPrecificacaoOpen = true;

        $this->dispatch('erp-precif-focus', id: 'precif-compra');
    }

    public function closeProductPrecificacao(): void
    {
        $this->productPrecificacaoOpen = false;
        $this->precificacao = [];
    }

    /**
     * Enter = Tab na precificação: grava valor, recalcula e foca o próximo.
     */
    public function precificacaoEnter(string $fieldId, ?string $value = null): void
    {
        if (! $this->productPrecificacaoOpen || $this->precificacao === []) {
            return;
        }

        $fieldId = trim($fieldId);

        if ($fieldId === '') {
            return;
        }

        $order = array_keys($this->precificacaoFieldMap());
        $index = array_search($fieldId, $order, true);

        if ($index === false) {
            return;
        }

        $this->precificacaoCommitField($fieldId, $value);

        $nextId = $order[$index + 1] ?? null;

        // Foco no próximo é feito no JS após o Livewire responder
        // (focar antes do morph apagava o que o usuário digitava).
        if (is_string($nextId) && $nextId !== '') {
            $this->dispatch('erp-precif-focus', id: $nextId);
        }
    }

    /**
     * Commit explícito (Enter/blur). Evita corrida do wire:model.blur com valor antigo.
     */
    public function precificacaoCommitField(string $fieldId, ?string $value = null): void
    {
        if (! $this->productPrecificacaoOpen || $this->precificacao === []) {
            return;
        }

        $fieldId = trim($fieldId);
        $fieldMap = $this->precificacaoFieldMap();

        if ($fieldId === '' || ! isset($fieldMap[$fieldId])) {
            return;
        }

        if ($value !== null) {
            $this->setPrecificacaoFieldValue($fieldMap[$fieldId], $value);
        }

        $this->syncPrecificacaoAfterFieldEnter($fieldId);
        $this->touchPrecificacao();
    }

    protected function focusPrecificacaoField(string $nextId): void
    {
        // Mesmo padrão do orçamento/NFe: evento Livewire + JS foca o campo.
        $this->dispatch('erp-precif-focus', id: $nextId);
    }

    /**
     * @return array<string, string>
     */
    protected function precificacaoFieldMap(): array
    {
        return [
            'precif-compra' => 'preco_compra',
            'precif-pct-custos' => 'pct_custos',
            'precif-custos-rs' => 'custos_rs',
            'precif-frete' => 'frete_pct',
            'precif-frete-rs' => 'frete_rs',
            'precif-outras-pct' => 'outras_pct',
            'precif-outras' => 'outras_desp',
            'precif-varejo-comissao' => 'niveis.varejo.comissao',
            'precif-varejo-comissao-rs' => 'niveis.varejo.comissao_rs',
            'precif-varejo-desconto' => 'niveis.varejo.desconto',
            'precif-varejo-desconto-rs' => 'niveis.varejo.desconto_rs',
            'precif-varejo-margem' => 'niveis.varejo.margem',
            'precif-varejo-praticado' => 'niveis.varejo.praticado',
            'precif-atacado-comissao' => 'niveis.atacado.comissao',
            'precif-atacado-comissao-rs' => 'niveis.atacado.comissao_rs',
            'precif-atacado-desconto' => 'niveis.atacado.desconto',
            'precif-atacado-desconto-rs' => 'niveis.atacado.desconto_rs',
            'precif-atacado-margem' => 'niveis.atacado.margem',
            'precif-atacado-praticado' => 'niveis.atacado.praticado',
            'precif-especial-comissao' => 'niveis.especial.comissao',
            'precif-especial-comissao-rs' => 'niveis.especial.comissao_rs',
            'precif-especial-desconto' => 'niveis.especial.desconto',
            'precif-especial-desconto-rs' => 'niveis.especial.desconto_rs',
            'precif-especial-margem' => 'niveis.especial.margem',
            'precif-especial-praticado' => 'niveis.especial.praticado',
        ];
    }

    protected function setPrecificacaoFieldValue(string $path, string $value): void
    {
        $formatted = $this->formatBrDecimal(BrDecimal::parse($value, 2), 2);

        if (! str_contains($path, '.')) {
            $this->precificacao[$path] = $formatted;

            return;
        }

        $parts = explode('.', $path);

        if (count($parts) === 3 && $parts[0] === 'niveis') {
            $this->precificacao['niveis'][$parts[1]][$parts[2]] = $formatted;
        }
    }

    /**
     * Reatribui o array para o Livewire detectar mudanças aninhadas e re-renderizar.
     */
    protected function touchPrecificacao(): void
    {
        $this->precificacao = json_decode(json_encode($this->precificacao), true) ?? [];
    }

    protected function syncPrecificacaoAfterFieldEnter(string $fieldId): void
    {
        match ($fieldId) {
            'precif-compra' => $this->updatedPrecificacaoPrecoCompra(),
            'precif-pct-custos' => $this->updatedPrecificacaoPctCustos(),
            'precif-custos-rs' => $this->updatedPrecificacaoCustosRs(),
            'precif-frete' => $this->updatedPrecificacaoFretePct(),
            'precif-frete-rs' => $this->updatedPrecificacaoFreteRs(),
            'precif-outras-pct' => $this->updatedPrecificacaoOutrasPct(),
            'precif-outras' => $this->updatedPrecificacaoOutrasDesp(),
            'precif-varejo-comissao' => $this->recalcularNivelPrecificacao('varejo', 'comissao'),
            'precif-varejo-comissao-rs' => $this->recalcularNivelPrecificacao('varejo', 'comissao_rs'),
            'precif-varejo-desconto' => $this->recalcularNivelPrecificacao('varejo', 'desconto'),
            'precif-varejo-desconto-rs' => $this->recalcularNivelPrecificacao('varejo', 'desconto_rs'),
            'precif-varejo-margem' => $this->recalcularNivelPrecificacao('varejo', 'margem'),
            'precif-varejo-praticado' => $this->recalcularNivelPrecificacao('varejo', 'praticado'),
            'precif-atacado-comissao' => $this->recalcularNivelPrecificacao('atacado', 'comissao'),
            'precif-atacado-comissao-rs' => $this->recalcularNivelPrecificacao('atacado', 'comissao_rs'),
            'precif-atacado-desconto' => $this->recalcularNivelPrecificacao('atacado', 'desconto'),
            'precif-atacado-desconto-rs' => $this->recalcularNivelPrecificacao('atacado', 'desconto_rs'),
            'precif-atacado-margem' => $this->recalcularNivelPrecificacao('atacado', 'margem'),
            'precif-atacado-praticado' => $this->recalcularNivelPrecificacao('atacado', 'praticado'),
            'precif-especial-comissao' => $this->recalcularNivelPrecificacao('especial', 'comissao'),
            'precif-especial-comissao-rs' => $this->recalcularNivelPrecificacao('especial', 'comissao_rs'),
            'precif-especial-desconto' => $this->recalcularNivelPrecificacao('especial', 'desconto'),
            'precif-especial-desconto-rs' => $this->recalcularNivelPrecificacao('especial', 'desconto_rs'),
            'precif-especial-margem' => $this->recalcularNivelPrecificacao('especial', 'margem'),
            'precif-especial-praticado' => $this->recalcularNivelPrecificacao('especial', 'praticado'),
            default => null,
        };
    }

    public function updatedPrecificacaoPrecoCompra(): void
    {
        $this->recalcularPrecificacaoCusto();
    }

    public function updatedPrecificacaoPctCustos(): void
    {
        $this->sincronizarValorRsFromPct('pct_custos', 'custos_rs');
        $this->recalcularPrecificacaoCusto();
    }

    public function updatedPrecificacaoCustosRs(): void
    {
        $this->sincronizarPctFromValorRs('custos_rs', 'pct_custos');
        $this->recalcularPrecificacaoCusto();
    }

    public function updatedPrecificacaoFretePct(): void
    {
        $this->sincronizarValorRsFromPct('frete_pct', 'frete_rs');
        $this->recalcularPrecificacaoCusto();
    }

    public function updatedPrecificacaoFreteRs(): void
    {
        $this->sincronizarPctFromValorRs('frete_rs', 'frete_pct');
        $this->recalcularPrecificacaoCusto();
    }

    public function updatedPrecificacaoOutrasPct(): void
    {
        $this->sincronizarValorRsFromPct('outras_pct', 'outras_desp');
        $this->recalcularPrecificacaoCusto();
    }

    public function updatedPrecificacaoOutrasDesp(): void
    {
        $this->sincronizarPctFromValorRs('outras_desp', 'outras_pct');
        $this->recalcularPrecificacaoCusto();
    }

    public function recalcularNivelPrecificacao(string $nivel, string $origem = 'margem'): void
    {
        if (! in_array($nivel, ['varejo', 'atacado', 'especial'], true)) {
            return;
        }

        $custo = BrDecimal::parse($this->precificacao['preco_custo'] ?? 0, 2);

        // R$ → % sobre o sugerido (margem R$), depois soma no praticado.
        if (in_array($origem, ['comissao_rs', 'desconto_rs'], true)) {
            $campo = $origem === 'comissao_rs' ? 'comissao' : 'desconto';
            $this->sincronizarNivelRsPct($nivel, $campo);
            $origem = $campo;
        }

        if ($origem === 'praticado') {
            $praticado = BrDecimal::parse($this->precificacao['niveis'][$nivel]['praticado'] ?? 0, 2);
            $comissao = BrDecimal::parse($this->precificacao['niveis'][$nivel]['comissao'] ?? 0, 2);
            $desconto = BrDecimal::parse($this->precificacao['niveis'][$nivel]['desconto'] ?? 0, 2);
            $sugerido = $this->sugeridoFromPraticado($praticado, $comissao, $desconto);
            $margem = $custo > 0
                ? max(0, round((($sugerido * 100) / $custo) - 100, 2))
                : 0.0;

            $this->precificacao['niveis'][$nivel]['margem'] = $this->formatBrDecimal($margem, 2);
            $this->precificacao['niveis'][$nivel]['sugerido'] = $this->formatBrDecimal($sugerido, 2);
            $this->precificacao['niveis'][$nivel]['praticado'] = $this->formatBrDecimal($praticado, 2);
            $this->sincronizarNivelPctRs($nivel, 'comissao');
            $this->sincronizarNivelPctRs($nivel, 'desconto');

            return;
        }

        $margem = BrDecimal::parse($this->precificacao['niveis'][$nivel]['margem'] ?? 0, 2);
        $comissao = BrDecimal::parse($this->precificacao['niveis'][$nivel]['comissao'] ?? 0, 2);
        $desconto = BrDecimal::parse($this->precificacao['niveis'][$nivel]['desconto'] ?? 0, 2);
        $sugerido = $this->sugeridoFromCusto($custo, $margem);

        $this->precificacao['niveis'][$nivel]['comissao'] = $this->formatBrDecimal($comissao, 2);
        $this->precificacao['niveis'][$nivel]['desconto'] = $this->formatBrDecimal($desconto, 2);
        $this->precificacao['niveis'][$nivel]['margem'] = $this->formatBrDecimal($margem, 2);
        $this->precificacao['niveis'][$nivel]['sugerido'] = $this->formatBrDecimal($sugerido, 2);
        $this->sincronizarNivelPctRs($nivel, 'comissao');
        $this->sincronizarNivelPctRs($nivel, 'desconto');
        $this->precificacao['niveis'][$nivel]['praticado'] = $this->formatBrDecimal(
            $this->praticadoSomaRs($nivel),
            2
        );
    }

    public function sincronizarNivelPctRs(string $nivel, string $campo): void
    {
        if (! in_array($nivel, ['varejo', 'atacado', 'especial'], true)) {
            return;
        }

        if (! in_array($campo, ['comissao', 'desconto'], true)) {
            return;
        }

        // % e R$ de comissão/desconto são sobre a margem R$ (sugerido).
        $base = BrDecimal::parse($this->precificacao['niveis'][$nivel]['sugerido'] ?? 0, 2);
        $pct = BrDecimal::parse($this->precificacao['niveis'][$nivel][$campo] ?? 0, 2);
        $this->precificacao['niveis'][$nivel][$campo] = $this->formatBrDecimal($pct, 2);
        $this->precificacao['niveis'][$nivel][$campo.'_rs'] = $this->formatBrDecimal(
            $this->valorFromPercentual($base, $pct),
            2
        );
    }

    public function sincronizarNivelRsPct(string $nivel, string $campo): void
    {
        if (! in_array($nivel, ['varejo', 'atacado', 'especial'], true)) {
            return;
        }

        if (! in_array($campo, ['comissao', 'desconto'], true)) {
            return;
        }

        $base = BrDecimal::parse($this->precificacao['niveis'][$nivel]['sugerido'] ?? 0, 2);
        $valor = BrDecimal::parse($this->precificacao['niveis'][$nivel][$campo.'_rs'] ?? 0, 2);
        $this->precificacao['niveis'][$nivel][$campo.'_rs'] = $this->formatBrDecimal($valor, 2);
        $this->precificacao['niveis'][$nivel][$campo] = $this->formatBrDecimal(
            $this->percentualFromValor($base, $valor),
            2
        );
    }

    public function aplicarPercentuaisPadraoPrecificacao(): void
    {
        $margem = BrDecimal::parse($this->precificacao['niveis']['varejo']['margem'] ?? 0, 2);
        $comissao = BrDecimal::parse($this->precificacao['niveis']['varejo']['comissao'] ?? 0, 2);
        $desconto = BrDecimal::parse($this->precificacao['niveis']['varejo']['desconto'] ?? 0, 2);

        foreach (['atacado', 'especial'] as $nivel) {
            $this->precificacao['niveis'][$nivel]['margem'] = $this->formatBrDecimal($margem, 2);
            $this->precificacao['niveis'][$nivel]['comissao'] = $this->formatBrDecimal($comissao, 2);
            $this->precificacao['niveis'][$nivel]['desconto'] = $this->formatBrDecimal($desconto, 2);
        }

        $this->recalcularPrecificacaoNiveis();
        $this->touchPrecificacao();

        Notification::make()
            ->title('Percentuais do varejo aplicados ao atacado e especial.')
            ->success()
            ->send();
    }

    public function aplicarProductPrecificacao(): void
    {
        $this->recalcularPrecificacaoCusto();

        $this->suppressProductPriceRecalculation = true;

        try {
            $this->data['preco_compra'] = $this->precificacao['preco_compra'] ?? '0,00';
            $this->data['pct_custos'] = $this->precificacao['pct_custos'] ?? '0,00';
            $this->data['preco_custo'] = $this->precificacao['preco_custo'] ?? '0,00';
            $this->data['pct_lucro'] = $this->precificacao['niveis']['varejo']['margem'] ?? '0,00';
            $this->data['preco_venda'] = $this->precificacao['niveis']['varejo']['praticado'] ?? '0,00';
            $this->data['preco_atacado'] = $this->precificacao['niveis']['atacado']['praticado'] ?? '0,00';
            $this->data['preco_especial'] = $this->precificacao['niveis']['especial']['praticado'] ?? '0,00';

            $formatted = $this->formatProductFormDataForDisplay($this->data);
            $this->data = $formatted;
            $this->form->fill($formatted);
            $this->dispatch('erp-masks-refresh');
        } finally {
            $this->suppressProductPriceRecalculation = false;
        }

        $this->closeProductPrecificacao();

        Notification::make()
            ->title('Precificação aplicada ao produto.')
            ->body('Grave o produto para salvar definitivamente.')
            ->success()
            ->send();

        $this->handlePrecificacaoReplicaAposAplicar();
    }

    protected function recalcularPrecificacaoCusto(bool $recalcularNiveis = true): void
    {
        $compra = BrDecimal::parse($this->precificacao['preco_compra'] ?? 0, 2);
        $pctCustos = BrDecimal::parse($this->precificacao['pct_custos'] ?? 0, 2);
        $fretePct = BrDecimal::parse($this->precificacao['frete_pct'] ?? 0, 2);
        $outrasPct = BrDecimal::parse($this->precificacao['outras_pct'] ?? 0, 2);

        $custosRs = $this->valorFromPercentual($compra, $pctCustos);
        $freteRs = $this->valorFromPercentual($compra, $fretePct);
        $outrasRs = $this->valorFromPercentual($compra, $outrasPct);

        // Normaliza milhar (1.000,00) nos campos editáveis após blur/Enter.
        $this->precificacao['preco_compra'] = $this->formatBrDecimal($compra, 2);
        $this->precificacao['pct_custos'] = $this->formatBrDecimal($pctCustos, 2);
        $this->precificacao['frete_pct'] = $this->formatBrDecimal($fretePct, 2);
        $this->precificacao['outras_pct'] = $this->formatBrDecimal($outrasPct, 2);
        $this->precificacao['custos_rs'] = $this->formatBrDecimal($custosRs, 2);
        $this->precificacao['frete_rs'] = $this->formatBrDecimal($freteRs, 2);
        $this->precificacao['outras_desp'] = $this->formatBrDecimal($outrasRs, 2);
        $this->precificacao['custo_pct_total'] = $this->formatBrDecimal(
            round($pctCustos + $fretePct + $outrasPct, 2),
            2
        );

        $custo = round($compra + $custosRs + $freteRs + $outrasRs, 2);

        $this->precificacao['preco_custo'] = $this->formatBrDecimal($custo, 2);

        if ($recalcularNiveis) {
            $this->recalcularPrecificacaoNiveis();
        }
    }

    protected function sincronizarValorRsFromPct(string $pctField, string $rsField): void
    {
        $compra = BrDecimal::parse($this->precificacao['preco_compra'] ?? 0, 2);
        $pct = BrDecimal::parse($this->precificacao[$pctField] ?? 0, 2);
        $this->precificacao[$rsField] = $this->formatBrDecimal($this->valorFromPercentual($compra, $pct), 2);
    }

    protected function sincronizarPctFromValorRs(string $rsField, string $pctField): void
    {
        $compra = BrDecimal::parse($this->precificacao['preco_compra'] ?? 0, 2);
        $valor = BrDecimal::parse($this->precificacao[$rsField] ?? 0, 2);
        $this->precificacao[$pctField] = $this->formatBrDecimal($this->percentualFromValor($compra, $valor), 2);
    }

    protected function valorFromPercentual(float $base, float $percentual): float
    {
        return round($base * $percentual / 100, 2);
    }

    protected function percentualFromValor(float $base, float $valor): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        return max(0, round(($valor * 100) / $base, 2));
    }

    protected function recalcularPrecificacaoNiveis(): void
    {
        foreach (['varejo', 'atacado', 'especial'] as $nivel) {
            $this->recalcularNivelPrecificacao($nivel, 'margem');
        }
    }

    /**
     * @return array{comissao: string, comissao_rs: string, desconto: string, desconto_rs: string, margem: string, sugerido: string, praticado: string}
     */
    protected function makeNivelPrecificacao(
        float $comissao,
        float $desconto,
        float $margem,
        float $custo,
        float $praticado
    ): array {
        $sugerido = $this->sugeridoFromCusto($custo, $margem);

        // Se já existe preço praticado no cadastro, deriva a margem R$ pela soma inversa.
        if ($praticado > 0) {
            $sugerido = $this->sugeridoFromPraticado($praticado, $comissao, $desconto);
            $margem = $custo > 0
                ? max(0, round((($sugerido * 100) / $custo) - 100, 2))
                : $margem;
        }

        $comissaoRs = $this->valorFromPercentual($sugerido, $comissao);
        $descontoRs = $this->valorFromPercentual($sugerido, $desconto);
        $preco = round($sugerido + $comissaoRs + $descontoRs, 2);

        return [
            'comissao' => $this->formatBrDecimal($comissao, 2),
            'comissao_rs' => $this->formatBrDecimal($comissaoRs, 2),
            'desconto' => $this->formatBrDecimal($desconto, 2),
            'desconto_rs' => $this->formatBrDecimal($descontoRs, 2),
            'margem' => $this->formatBrDecimal($margem, 2),
            'sugerido' => $this->formatBrDecimal($sugerido, 2),
            'praticado' => $this->formatBrDecimal($preco, 2),
        ];
    }

    protected function sugeridoFromCusto(float $custo, float $margem): float
    {
        return round($custo + ($custo * $margem / 100), 2);
    }

    /**
     * Praticado = Comissão R$ + Desconto R$ + Margem R$ (sugerido).
     */
    protected function praticadoSomaRs(string $nivel): float
    {
        $comissaoRs = BrDecimal::parse($this->precificacao['niveis'][$nivel]['comissao_rs'] ?? 0, 2);
        $descontoRs = BrDecimal::parse($this->precificacao['niveis'][$nivel]['desconto_rs'] ?? 0, 2);
        $margemRs = BrDecimal::parse($this->precificacao['niveis'][$nivel]['sugerido'] ?? 0, 2);

        return round($comissaoRs + $descontoRs + $margemRs, 2);
    }

    protected function praticadoFromSugerido(float $sugerido, float $comissao, float $desconto): float
    {
        $comissaoRs = $this->valorFromPercentual($sugerido, $comissao);
        $descontoRs = $this->valorFromPercentual($sugerido, $desconto);

        return round($sugerido + $comissaoRs + $descontoRs, 2);
    }

    /**
     * Inverso da soma: margem R$ = praticado ÷ (1 + comissão% + desconto%).
     */
    protected function sugeridoFromPraticado(float $praticado, float $comissao, float $desconto): float
    {
        $fator = 1 + (($comissao + $desconto) / 100);

        if ($fator <= 0) {
            return 0.0;
        }

        return round($praticado / $fator, 2);
    }
}
