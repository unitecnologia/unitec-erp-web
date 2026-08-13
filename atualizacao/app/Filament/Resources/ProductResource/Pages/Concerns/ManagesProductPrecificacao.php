<?php

namespace App\Filament\Resources\ProductResource\Pages\Concerns;

use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpContext;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

trait ManagesProductPrecificacao
{
    public bool $productPrecificacaoOpen = false;

    /** Força remount dos inputs após Enter (Livewire não atualiza value= sem wire:model). */
    public int $precificacaoUiEpoch = 0;

    /** Evita blur com 0,00 apagar valor logo após Enter (precisa ser public p/ sobreviver entre requests). */
    public string $precificacaoLastEnterField = '';

    public float $precificacaoLastEnterAt = 0;

    /** @var array<string, mixed> */
    public array $precificacao = [];

    public function openProductPrecificacao(): void
    {
        $this->openProductPrecificacaoFromData($this->data ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function openProductPrecificacaoFromData(array $data): void
    {
        $compra = BrDecimal::parse($data['preco_compra'] ?? 0, 2);
        $pctCustos = BrDecimal::parse($data['pct_custos'] ?? 0, 2);
        $freteRs = BrDecimal::parse($data['frete_rs'] ?? 0, 2);
        $seguroRs = BrDecimal::parse($data['seguro_rs'] ?? 0, 2);
        $outrasRs = BrDecimal::parse($data['outras_desp'] ?? $data['outras_rs'] ?? 0, 2);
        if ($freteRs < 0) {
            $freteRs = 0.0;
        }
        if ($seguroRs < 0) {
            $seguroRs = 0.0;
        }
        if ($outrasRs < 0) {
            $outrasRs = 0.0;
        }

        $fretePct = array_key_exists('frete_pct', $data)
            ? BrDecimal::parse($data['frete_pct'], 2)
            : $this->percentualFromValor($compra, $freteRs);
        $seguroPct = array_key_exists('seguro_pct', $data)
            ? BrDecimal::parse($data['seguro_pct'], 2)
            : $this->percentualFromValor($compra, $seguroRs);
        $outrasPct = array_key_exists('outras_pct', $data)
            ? BrDecimal::parse($data['outras_pct'], 2)
            : $this->percentualFromValor($compra, $outrasRs);

        $custosRs = $this->valorFromPercentual($compra, $pctCustos);
        $custo = BrDecimal::parse(
            $data['preco_custo'] ?? round($compra + $custosRs + $freteRs + $seguroRs + $outrasRs, 2),
            2
        );
        $margemVarejo = BrDecimal::parse($data['pct_lucro'] ?? 0, 2);
        $comissao = 0.0;
        $desconto = 0.0;

        $varejo = BrDecimal::parse($data['preco_venda'] ?? 0, 2);
        $atacado = BrDecimal::parse($data['preco_atacado'] ?? 0, 2);
        $especial = BrDecimal::parse($data['preco_especial'] ?? 0, 2);

        // Atacado/Especial: margem só se já houver preço manual no cadastro.
        // Sem preço, fica zerado até o usuário editar ou clicar em "Aplicar % do varejo".
        $margemAtacado = $custo > 0 && $atacado > 0
            ? round((($atacado * 100) / $custo) - 100, 2)
            : 0.0;
        $margemEspecial = $custo > 0 && $especial > 0
            ? round((($especial * 100) / $custo) - 100, 2)
            : 0.0;

        $empresa = ErpContext::statusBar()['Empresa'] ?? '—';

        $this->precificacao = [
            'empresa' => (string) $empresa,
            'codigo' => (string) ($data['codigo'] ?? ''),
            'codigo_barras' => (string) ($data['codigo_barras'] ?? ''),
            'referencia' => (string) ($data['referencia'] ?? ''),
            'descricao' => (string) ($data['descricao'] ?? ''),
            'preco_compra' => $this->formatBrDecimal($compra, 2),
            'pct_custos' => $this->formatBrDecimal($pctCustos, 2),
            'custos_rs' => $this->formatBrDecimal($custosRs, 2),
            'frete_pct' => $this->formatBrDecimal($fretePct, 2),
            'frete_rs' => $this->formatBrDecimal($freteRs, 2),
            'seguro_pct' => $this->formatBrDecimal($seguroPct, 2),
            'seguro_rs' => $this->formatBrDecimal($seguroRs, 2),
            'outras_pct' => $this->formatBrDecimal($outrasPct, 2),
            'outras_desp' => $this->formatBrDecimal($outrasRs, 2),
            'custo_pct_total' => $this->formatBrDecimal(round($pctCustos + $fretePct + $seguroPct + $outrasPct, 2), 2),
            'preco_custo' => $this->formatBrDecimal($custo, 2),
            'niveis' => [
                'varejo' => $this->makeNivelPrecificacao($comissao, $desconto, $margemVarejo, $custo, $varejo),
                'atacado' => $this->makeNivelPrecificacao(
                    $comissao,
                    $desconto,
                    max(0, $margemAtacado),
                    $atacado > 0 ? $custo : 0.0,
                    $atacado
                ),
                'especial' => $this->makeNivelPrecificacao(
                    $comissao,
                    $desconto,
                    max(0, $margemEspecial),
                    $especial > 0 ? $custo : 0.0,
                    $especial
                ),
            ],
        ];

        $this->resetPrecificacaoEnterGuard();

        // Atualiza só o bloco de custo. Não recalcular níveis pela margem aqui:
        // isso zerava o Praticado quando o custo era 0 (preço do cadastro sumia).
        $this->recalcularPrecificacaoCusto(false);

        // Mantém R$ de frete/seguro/outras vindos do lançamento (evita deriva por arredondamento %).
        if ($freteRs > 0 || $seguroRs > 0 || $outrasRs > 0) {
            $this->precificacao['frete_rs'] = $this->formatBrDecimal($freteRs, 2);
            $this->precificacao['seguro_rs'] = $this->formatBrDecimal($seguroRs, 2);
            $this->precificacao['outras_desp'] = $this->formatBrDecimal($outrasRs, 2);
            $this->sincronizarPctFromValorRs('frete_rs', 'frete_pct');
            $this->sincronizarPctFromValorRs('seguro_rs', 'seguro_pct');
            $this->sincronizarPctFromValorRs('outras_desp', 'outras_pct');
            $compraNorm = BrDecimal::parse($this->precificacao['preco_compra'] ?? 0, 2);
            $custosNorm = BrDecimal::parse($this->precificacao['custos_rs'] ?? 0, 2);
            $this->precificacao['preco_custo'] = $this->formatBrDecimal(
                round($compraNorm + $custosNorm + $freteRs + $seguroRs + $outrasRs, 2),
                2
            );
            $this->precificacao['custo_pct_total'] = $this->formatBrDecimal(
                round(
                    BrDecimal::parse($this->precificacao['pct_custos'] ?? 0, 2)
                    + BrDecimal::parse($this->precificacao['frete_pct'] ?? 0, 2)
                    + BrDecimal::parse($this->precificacao['seguro_pct'] ?? 0, 2)
                    + BrDecimal::parse($this->precificacao['outras_pct'] ?? 0, 2),
                    2
                ),
                2
            );
            $custo = BrDecimal::parse($this->precificacao['preco_custo'], 2);
        }

        // Só restaura custo salvo quando há extras no rateio/dados OU o valor
        // bate com o recálculo. Evita custo “fantasma” (ex.: 528 com frete 0).
        $custoAplicado = BrDecimal::parse($data['preco_custo'] ?? 0, 2);
        $custoRecalc = BrDecimal::parse($this->precificacao['preco_custo'] ?? 0, 2);
        $extrasData = BrDecimal::parse($data['frete_rs'] ?? 0, 2)
            + BrDecimal::parse($data['seguro_rs'] ?? 0, 2)
            + BrDecimal::parse($data['outras_desp'] ?? $data['outras_rs'] ?? 0, 2);

        if ($custoAplicado > 0 && ($extrasData > 0.009 || abs($custoAplicado - $custoRecalc) < 0.05)) {
            $this->precificacao['preco_custo'] = $this->formatBrDecimal($custoAplicado, 2);
        }

        foreach (['varejo' => $varejo, 'atacado' => $atacado, 'especial' => $especial] as $nivel => $preco) {
            if ($preco > 0) {
                $this->precificacao['niveis'][$nivel]['praticado'] = $this->formatBrDecimal($preco, 2);

                $margemNivel = match ($nivel) {
                    'varejo' => $margemVarejo,
                    'atacado' => max(0.0, $margemAtacado),
                    'especial' => max(0.0, $margemEspecial),
                    default => 0.0,
                };

                // Com margem salva: mantém Mg% + praticado (não recalcula como se comissão=0).
                if ($margemNivel > 0) {
                    $custoAtual = BrDecimal::parse($this->precificacao['preco_custo'] ?? 0, 2);
                    $this->precificacao['niveis'][$nivel]['margem'] = $this->formatBrDecimal($margemNivel, 2);
                    $this->precificacao['niveis'][$nivel]['sugerido'] = $this->formatBrDecimal(
                        $this->sugeridoFromCusto($custoAtual, $margemNivel),
                        2
                    );
                    $this->sincronizarNivelPctRs($nivel, 'comissao');
                    $this->sincronizarNivelPctRs($nivel, 'desconto');
                } else {
                    $this->recalcularNivelPrecificacao($nivel, 'praticado');
                }
            } elseif ($nivel === 'varejo') {
                $this->recalcularNivelPrecificacao($nivel, 'margem');
            } else {
                $this->precificacao['niveis'][$nivel]['margem'] = $this->formatBrDecimal(0, 2);
                $this->precificacao['niveis'][$nivel]['sugerido'] = $this->formatBrDecimal(0, 2);
                $this->precificacao['niveis'][$nivel]['praticado'] = $this->formatBrDecimal(0, 2);
                $this->precificacao['niveis'][$nivel]['comissao_rs'] = $this->formatBrDecimal(0, 2);
                $this->precificacao['niveis'][$nivel]['desconto_rs'] = $this->formatBrDecimal(0, 2);
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
        $this->resetPrecificacaoEnterGuard();
        $this->dispatch('erp-precif-reset');
    }

    /**
     * Enter = Tab na precificação: grava valor, recalcula e foca o próximo.
     */
    public function precificacaoEnter(string $fieldId, ?string $value = null, ?array $diag = null): void
    {
        if ($diag !== null) {
            $this->logPrecificacao('enter:tela', ['field' => $fieldId] + $diag);
        }

        if (! $this->productPrecificacaoOpen || $this->precificacao === []) {
            return;
        }

        $fieldId = trim($fieldId);

        if ($fieldId === '') {
            return;
        }

        if (! isset($this->precificacaoFieldMap()[$fieldId])) {
            return;
        }

        $this->logPrecificacao('enter', ['field' => $fieldId, 'value' => $value]);

        $this->precificacaoCommitField($fieldId, $value);

        // Marca Enter recente: blur fantasma com 0,00 não pode apagar o valor.
        $this->precificacaoLastEnterField = $fieldId;
        $this->precificacaoLastEnterAt = microtime(true);

        $order = array_keys($this->precificacaoFieldMap());
        $index = array_search($fieldId, $order, true);
        $nextId = $index === false ? null : ($order[$index + 1] ?? null);

        // O foco confiável é o pós-morph: o JS recebe o estado calculado primeiro
        // e só depois avança. No último campo, apenas confirma o commit.
        $this->dispatch('erp-precif-focus', id: $nextId, committed: $fieldId);
    }

    /**
     * Commit explícito (Enter/blur). Evita corrida do wire:model.blur com valor antigo.
     */
    public function precificacaoCommitField(string $fieldId, ?string $value = null, bool $fromBlur = false): void
    {
        if (! $this->productPrecificacaoOpen || $this->precificacao === []) {
            return;
        }

        $fieldId = trim($fieldId);
        $fieldMap = $this->precificacaoFieldMap();

        if ($fieldId === '' || ! isset($fieldMap[$fieldId])) {
            return;
        }

        $this->logPrecificacao('commit:in', [
            'field' => $fieldId,
            'value' => $value,
            'blur' => $fromBlur,
        ]);

        if ($value !== null) {
            if ($fromBlur && $this->shouldIgnorePrecificacaoZeroBlur($value, $fieldMap[$fieldId])) {
                $this->logPrecificacao('commit:ignorado-blur-zero', ['field' => $fieldId]);

                return;
            }

            $this->setPrecificacaoFieldValue($fieldMap[$fieldId], $value);
        }

        $this->recalcularPrecificacao($fieldMap[$fieldId]);
        $this->touchPrecificacao();

        $this->logPrecificacao('commit:out', ['field' => $fieldId]);
    }

    /**
     * Blur logo após o Enter chega com 0,00 e apagava o valor recém-calculado.
     */
    protected function shouldIgnorePrecificacaoZeroBlur(string $value, string $path): bool
    {
        if ($this->precificacaoLastEnterAt <= 0) {
            return false;
        }

        if ((microtime(true) - $this->precificacaoLastEnterAt) > 2.5) {
            return false;
        }

        if (BrDecimal::parse($value, 2) > 0) {
            return false;
        }

        return $this->readPrecificacaoPathValue($path) > 0;
    }

    /**
     * Diagnóstico temporário (só dev): rastrear quem apaga o valor na modal.
     *
     * @param  array<string, mixed>  $contexto
     */
    protected function logPrecificacao(string $evento, array $contexto = []): void
    {
        if (! app()->environment('local')) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/precificacao.log'),
            'level' => 'debug',
        ])->debug($evento, $contexto + [
            'pct_custos' => $this->precificacao['pct_custos'] ?? null,
            'custos_rs' => $this->precificacao['custos_rs'] ?? null,
            'frete_pct' => $this->precificacao['frete_pct'] ?? null,
            'frete_rs' => $this->precificacao['frete_rs'] ?? null,
            'preco_compra' => $this->precificacao['preco_compra'] ?? null,
            'preco_custo' => $this->precificacao['preco_custo'] ?? null,
        ]);
    }

    protected function resetPrecificacaoEnterGuard(): void
    {
        $this->precificacaoLastEnterField = '';
        $this->precificacaoLastEnterAt = 0;
    }

    protected function readPrecificacaoPathValue(string $path): float
    {
        if (! str_contains($path, '.')) {
            return BrDecimal::parse($this->precificacao[$path] ?? 0, 2);
        }

        $parts = explode('.', $path);

        if (count($parts) === 3 && $parts[0] === 'niveis') {
            return BrDecimal::parse(
                $this->precificacao['niveis'][$parts[1]][$parts[2]] ?? 0,
                2
            );
        }

        return 0.0;
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
            'precif-seguro' => 'seguro_pct',
            'precif-seguro-rs' => 'seguro_rs',
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
            // Substitui o nível inteiro para o Livewire não perder o aninhado.
            $nivel = $this->precificacao['niveis'][$parts[1]] ?? [];
            $nivel[$parts[2]] = $formatted;
            $this->precificacao['niveis'][$parts[1]] = $nivel;
        }
    }

    /**
     * Reatribui o array e remonta os inputs para o valor calculado aparecer na tela.
     */
    protected function touchPrecificacao(): void
    {
        $this->precificacao = json_decode(json_encode($this->precificacao), true) ?? [];
        $this->precificacaoUiEpoch++;
    }

    /**
     * Única entrada de cálculo: a origem define somente o par que deve ser
     * sincronizado; custo e preços derivados são recalculados em sequência.
     */
    public function recalcularPrecificacao(string $origem): void
    {
        $pares = [
            'pct_custos' => ['pct_custos', 'custos_rs'],
            'frete_pct' => ['frete_pct', 'frete_rs'],
            'seguro_pct' => ['seguro_pct', 'seguro_rs'],
            'outras_pct' => ['outras_pct', 'outras_desp'],
            'custos_rs' => ['pct_custos', 'custos_rs'],
            'frete_rs' => ['frete_pct', 'frete_rs'],
            'seguro_rs' => ['seguro_pct', 'seguro_rs'],
            'outras_desp' => ['outras_pct', 'outras_desp'],
        ];

        if ($origem === 'preco_compra') {
            $this->recalcularPrecificacaoCustoPreservandoNiveis();

            return;
        }

        if (isset($pares[$origem])) {
            [$campoPct, $campoRs] = $pares[$origem];

            if (str_ends_with($origem, '_rs') || $origem === 'outras_desp') {
                $this->sincronizarPctFromValorRs($campoRs, $campoPct);
                $this->recalcularPrecificacaoCustoPreservandoNiveis(preserveRs: [$campoRs]);
            } else {
                $this->sincronizarValorRsFromPct($campoPct, $campoRs);
                $this->recalcularPrecificacaoCustoPreservandoNiveis();
            }

            return;
        }

        if (preg_match('/^niveis\.(varejo|atacado|especial)\.(.+)$/', $origem, $matches) === 1) {
            $this->recalcularNivelPrecificacao($matches[1], $matches[2]);
        }
    }

    /**
     * Atualiza o bloco de custo sem zerar varejo/atacado/especial quando a margem
     * ainda não sincronizou no Livewire (caso típico: clique em Aplicar com foco na compra).
     *
     * @param  list<string>  $preserveRs  campos R$ que o usuário acabou de digitar (não recalcular a partir do %)
     */
    protected function recalcularPrecificacaoCustoPreservandoNiveis(array $preserveRs = []): void
    {
        $this->recalcularPrecificacaoCusto(false, $preserveRs);

        $margemVarejo = BrDecimal::parse($this->precificacao['niveis']['varejo']['margem'] ?? 0, 2);
        if ($margemVarejo > 0) {
            $this->recalcularPrecificacaoNiveis();
        }
    }

    public function recalcularNivelPrecificacao(string $nivel, string $origem = 'margem'): void
    {
        if (! in_array($nivel, ['varejo', 'atacado', 'especial'], true)) {
            return;
        }

        $custo = BrDecimal::parse($this->precificacao['preco_custo'] ?? 0, 2);

        // R$ digitado → % sobre o custo. NÃO regrava o R$ a partir do % depois
        // (evita zerar o campo no Enter por corrida do Livewire/wire:model).
        if (in_array($origem, ['comissao_rs', 'desconto_rs'], true)) {
            $campo = $origem === 'comissao_rs' ? 'comissao' : 'desconto';
            $this->sincronizarNivelRsPct($nivel, $campo);

            $margem = BrDecimal::parse($this->precificacao['niveis'][$nivel]['margem'] ?? 0, 2);

            if ($nivel !== 'varejo' && $margem <= 0) {
                $this->precificacao['niveis'][$nivel]['sugerido'] = $this->formatBrDecimal(0, 2);
                $this->precificacao['niveis'][$nivel]['praticado'] = $this->formatBrDecimal(0, 2);

                return;
            }

            $sugerido = $this->sugeridoFromCusto($custo, $margem);
            $this->precificacao['niveis'][$nivel]['margem'] = $this->formatBrDecimal($margem, 2);
            $this->precificacao['niveis'][$nivel]['sugerido'] = $this->formatBrDecimal($sugerido, 2);

            $outro = $campo === 'comissao' ? 'desconto' : 'comissao';
            $this->sincronizarNivelPctRs($nivel, $outro);
            $this->precificacao['niveis'][$nivel]['praticado'] = $this->formatBrDecimal(
                $this->praticadoSomaRs($nivel),
                2
            );

            return;
        }

        if ($origem === 'praticado') {
            $praticado = BrDecimal::parse($this->precificacao['niveis'][$nivel]['praticado'] ?? 0, 2);
            $comissao = BrDecimal::parse($this->precificacao['niveis'][$nivel]['comissao'] ?? 0, 2);
            $desconto = BrDecimal::parse($this->precificacao['niveis'][$nivel]['desconto'] ?? 0, 2);
            $sugerido = $this->sugeridoFromPraticado($praticado, $comissao, $desconto, $custo);
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

        // % digitado → R$ sobre o custo (só o campo editado; não reprocessa o outro).
        if (in_array($origem, ['comissao', 'desconto'], true)) {
            $this->sincronizarNivelPctRs($nivel, $origem);

            $margem = BrDecimal::parse($this->precificacao['niveis'][$nivel]['margem'] ?? 0, 2);

            if ($nivel !== 'varejo' && $margem <= 0) {
                $this->precificacao['niveis'][$nivel]['sugerido'] = $this->formatBrDecimal(0, 2);
                $this->precificacao['niveis'][$nivel]['praticado'] = $this->formatBrDecimal(0, 2);

                return;
            }

            $sugerido = $this->sugeridoFromCusto($custo, $margem);
            $this->precificacao['niveis'][$nivel]['margem'] = $this->formatBrDecimal($margem, 2);
            $this->precificacao['niveis'][$nivel]['sugerido'] = $this->formatBrDecimal($sugerido, 2);

            $outro = $origem === 'comissao' ? 'desconto' : 'comissao';
            $this->sincronizarNivelPctRs($nivel, $outro);
            $this->precificacao['niveis'][$nivel]['praticado'] = $this->formatBrDecimal(
                $this->praticadoSomaRs($nivel),
                2
            );

            return;
        }

        // Margem / demais origens: recalcula formação completa.
        $margem = BrDecimal::parse($this->precificacao['niveis'][$nivel]['margem'] ?? 0, 2);
        $comissao = BrDecimal::parse($this->precificacao['niveis'][$nivel]['comissao'] ?? 0, 2);
        $desconto = BrDecimal::parse($this->precificacao['niveis'][$nivel]['desconto'] ?? 0, 2);

        // Atacado/Especial sem margem: não preenche sugerido/praticado com o custo.
        // Só calcula depois de margem manual ou "Aplicar % do varejo".
        if ($nivel !== 'varejo' && $margem <= 0) {
            $this->precificacao['niveis'][$nivel]['comissao'] = $this->formatBrDecimal($comissao, 2);
            $this->precificacao['niveis'][$nivel]['desconto'] = $this->formatBrDecimal($desconto, 2);
            $this->precificacao['niveis'][$nivel]['margem'] = $this->formatBrDecimal(0, 2);
            $this->precificacao['niveis'][$nivel]['sugerido'] = $this->formatBrDecimal(0, 2);
            $this->precificacao['niveis'][$nivel]['praticado'] = $this->formatBrDecimal(0, 2);
            $this->precificacao['niveis'][$nivel]['comissao_rs'] = $this->formatBrDecimal(0, 2);
            $this->precificacao['niveis'][$nivel]['desconto_rs'] = $this->formatBrDecimal(0, 2);

            return;
        }

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

        // % e R$ de comissão/desconto são sobre o preço de custo.
        $base = BrDecimal::parse($this->precificacao['preco_custo'] ?? 0, 2);
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

        $base = BrDecimal::parse($this->precificacao['preco_custo'] ?? 0, 2);
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
        // false: não recalcular níveis pela margem (zerava Praticado se custo/compra
        // ainda não estivesse sincronizado no Livewire). Usa o estado já calculado na modal.
        // Preserve R$ do bloco de custo: evita zerar frete/seguro/outras se o % ainda
        // estiver defasado no Livewire (mesmo padrão do Enter nos campos R$).
        $this->recalcularPrecificacaoCusto(false, ['custos_rs', 'frete_rs', 'seguro_rs', 'outras_desp']);
        $this->applyPrecificacaoToHost();
        $this->closeProductPrecificacao();

        Notification::make()
            ->title('Precificação aplicada ao produto.')
            ->body($this->precificacaoAplicadaBody())
            ->success()
            ->send();

        $this->handlePrecificacaoReplicaAposAplicar();
    }

    protected function precificacaoAplicadaBody(): string
    {
        return 'Grave o produto para salvar definitivamente.';
    }

    /**
     * Aplica o resultado da precificação no formulário do produto (padrão).
     * Outras telas podem sobrescrever (ex.: lançamento de compra).
     */
    protected function applyPrecificacaoToHost(): void
    {
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
    }

    /**
     * @param  list<string>  $preserveRs
     */
    protected function recalcularPrecificacaoCusto(bool $recalcularNiveis = true, array $preserveRs = []): void
    {
        $compra = BrDecimal::parse($this->precificacao['preco_compra'] ?? 0, 2);
        $pctCustos = BrDecimal::parse($this->precificacao['pct_custos'] ?? 0, 2);
        $fretePct = BrDecimal::parse($this->precificacao['frete_pct'] ?? 0, 2);
        $seguroPct = BrDecimal::parse($this->precificacao['seguro_pct'] ?? 0, 2);
        $outrasPct = BrDecimal::parse($this->precificacao['outras_pct'] ?? 0, 2);

        $custosRs = in_array('custos_rs', $preserveRs, true)
            ? BrDecimal::parse($this->precificacao['custos_rs'] ?? 0, 2)
            : $this->valorFromPercentual($compra, $pctCustos);
        $freteRs = in_array('frete_rs', $preserveRs, true)
            ? BrDecimal::parse($this->precificacao['frete_rs'] ?? 0, 2)
            : $this->valorFromPercentual($compra, $fretePct);
        $seguroRs = in_array('seguro_rs', $preserveRs, true)
            ? BrDecimal::parse($this->precificacao['seguro_rs'] ?? 0, 2)
            : $this->valorFromPercentual($compra, $seguroPct);
        $outrasRs = in_array('outras_desp', $preserveRs, true)
            ? BrDecimal::parse($this->precificacao['outras_desp'] ?? 0, 2)
            : $this->valorFromPercentual($compra, $outrasPct);

        // Se o usuário digitou R$, o % acompanha o valor preservado.
        if (in_array('custos_rs', $preserveRs, true)) {
            $pctCustos = $this->percentualFromValor($compra, $custosRs);
        }
        if (in_array('frete_rs', $preserveRs, true)) {
            $fretePct = $this->percentualFromValor($compra, $freteRs);
        }
        if (in_array('seguro_rs', $preserveRs, true)) {
            $seguroPct = $this->percentualFromValor($compra, $seguroRs);
        }
        if (in_array('outras_desp', $preserveRs, true)) {
            $outrasPct = $this->percentualFromValor($compra, $outrasRs);
        }

        // Normaliza milhar (1.000,00) nos campos editáveis após blur/Enter.
        $this->precificacao['preco_compra'] = $this->formatBrDecimal($compra, 2);
        $this->precificacao['pct_custos'] = $this->formatBrDecimal($pctCustos, 2);
        $this->precificacao['frete_pct'] = $this->formatBrDecimal($fretePct, 2);
        $this->precificacao['seguro_pct'] = $this->formatBrDecimal($seguroPct, 2);
        $this->precificacao['outras_pct'] = $this->formatBrDecimal($outrasPct, 2);
        $this->precificacao['custos_rs'] = $this->formatBrDecimal($custosRs, 2);
        $this->precificacao['frete_rs'] = $this->formatBrDecimal($freteRs, 2);
        $this->precificacao['seguro_rs'] = $this->formatBrDecimal($seguroRs, 2);
        $this->precificacao['outras_desp'] = $this->formatBrDecimal($outrasRs, 2);
        $this->precificacao['custo_pct_total'] = $this->formatBrDecimal(
            round($pctCustos + $fretePct + $seguroPct + $outrasPct, 2),
            2
        );

        $custo = round($compra + $custosRs + $freteRs + $seguroRs + $outrasRs, 2);

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
            $sugerido = $this->sugeridoFromPraticado($praticado, $comissao, $desconto, $custo);
            $margem = $custo > 0
                ? max(0, round((($sugerido * 100) / $custo) - 100, 2))
                : $margem;
        }

        $comissaoRs = $this->valorFromPercentual($custo, $comissao);
        $descontoRs = $this->valorFromPercentual($custo, $desconto);
        $preco = $praticado > 0
            ? round($praticado, 2)
            : round($sugerido + $comissaoRs + $descontoRs, 2);

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
     * Comissão/desconto R$ são sobre o preço de custo.
     */
    protected function praticadoSomaRs(string $nivel): float
    {
        $comissaoRs = BrDecimal::parse($this->precificacao['niveis'][$nivel]['comissao_rs'] ?? 0, 2);
        $descontoRs = BrDecimal::parse($this->precificacao['niveis'][$nivel]['desconto_rs'] ?? 0, 2);
        $margemRs = BrDecimal::parse($this->precificacao['niveis'][$nivel]['sugerido'] ?? 0, 2);

        return round($comissaoRs + $descontoRs + $margemRs, 2);
    }

    protected function praticadoFromSugerido(float $sugerido, float $comissao, float $desconto, float $custo): float
    {
        $comissaoRs = $this->valorFromPercentual($custo, $comissao);
        $descontoRs = $this->valorFromPercentual($custo, $desconto);

        return round($sugerido + $comissaoRs + $descontoRs, 2);
    }

    /**
     * Inverso: sugerido = praticado − (comissão R$ + desconto R$ sobre o custo).
     */
    protected function sugeridoFromPraticado(float $praticado, float $comissao, float $desconto, float $custo): float
    {
        $comissaoRs = $this->valorFromPercentual($custo, $comissao);
        $descontoRs = $this->valorFromPercentual($custo, $desconto);

        return round(max(0, $praticado - $comissaoRs - $descontoRs), 2);
    }
}
