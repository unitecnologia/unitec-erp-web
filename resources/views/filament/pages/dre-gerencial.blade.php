<x-filament-panels::page>
@php
    $pageClass = 'erp-rh-treinamentos';
    $r = $this->gerado ? $this->resultado : [];
    $d = is_array($r['despesas'] ?? null) ? $r['despesas'] : [];
    $demo = is_array($r['demonstrativo'] ?? null) ? $r['demonstrativo'] : [];
    $trib = is_array($demo['tributos_informativos'] ?? null) ? $demo['tributos_informativos'] : [];
    $estim = is_array($demo['tributos_estimativa'] ?? null) ? $demo['tributos_estimativa'] : [];
    $pendencias = is_array($demo['pendencias'] ?? null) ? $demo['pendencias'] : [];
    $exibirTributos = ! empty($demo['tributos_exibir']);
    $tribDisponivel = ! empty($trib['disponivel']);
    $tribCrt = (int) ($trib['crt'] ?? ($demo['dre_parametros']['crt'] ?? 0));
    $tribRegimeNormal = $tribCrt === 3;
    $estimAtiva = ! empty($estim['ativa']);
    $aliqPctLabel = $estimAtiva
        ? rtrim(rtrim(number_format((float) ($estim['aliquota_pct'] ?? 0), 4, ',', ''), '0'), ',')
        : '';
    $periodoDeIso = filled($this->periodoDe) ? $this->periodoDe : '';
    $periodoAteIso = filled($this->periodoAte) ? $this->periodoAte : '';
    $fmtDataBr = static function (?string $iso): string {
        $iso = substr(trim((string) $iso), 0, 10);
        if ($iso === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso)) {
            return '—';
        }

        return substr($iso, 8, 2).'/'.substr($iso, 5, 2).'/'.substr($iso, 0, 4);
    };
    // Grade: situação curta; detalhes ficam na seção Pendências.
    $linhas = [
        [
            'key' => 'demonstrativo.receita_mercadorias',
            'label' => 'Receita de mercadorias',
            'tipo' => 'valor',
            'obs' => 'Composição',
        ],
        [
            'key' => 'demonstrativo.receita_servicos',
            'label' => 'Receita de serviços',
            'tipo' => 'valor',
            'obs' => 'Composição',
        ],
        [
            'key' => 'demonstrativo.receita_pecas_os',
            'label' => 'Receita de peças (OS)',
            'tipo' => 'valor',
            'obs' => 'Composição',
        ],
        [
            'key' => 'demonstrativo.receita_bruta',
            'label' => 'Receita comercial (já líquida de descontos)',
            'tipo' => 'subtotal',
            'obs' => 'Não é receita bruta contábil',
        ],
        [
            'key' => 'demonstrativo.deducoes_receita',
            'label' => '(−) Devoluções comprovadas',
            'tipo' => 'valor',
            'obs' => 'Finalizadas',
        ],
        [
            'key' => 'demonstrativo.receita_liquida',
            'label' => '(=) Receita após devoluções',
            'tipo' => 'total',
            'obs' => 'Base comercial',
        ],
    ];
    if ($estimAtiva) {
        $linhas[] = [
            'key' => 'demonstrativo.tributos_estimativa.valor',
            'label' => '(−) Tributos estimados — Simples Nacional ('.$aliqPctLabel.'%)',
            'tipo' => 'info',
            'obs' => 'Estimativa — não deduzida',
            'force_valor' => true,
        ];
    }
    $linhas = array_merge($linhas, [
        [
            'key' => 'demonstrativo.cmv',
            'label' => '(−) CMV histórico',
            'tipo' => (! empty($demo['cmv_disponivel']) ? 'valor' : 'pending'),
            'obs' => ! empty($demo['cmv_disponivel'])
                ? ('Cobertura '.number_format((float) ($demo['cmv_cobertura_pct'] ?? 0), 0).'%')
                : 'Pendente',
            'force_valor' => ! empty($demo['cmv_disponivel']),
        ],
        [
            'key' => 'demonstrativo.resultado_bruto',
            'label' => '(=) Resultado bruto',
            'tipo' => (! empty($demo['resultado_bruto_disponivel']) ? 'total' : 'pending'),
            'obs' => ! empty($demo['resultado_bruto_disponivel']) ? 'Parcial' : 'Pendente',
            'force_valor' => ! empty($demo['resultado_bruto_disponivel']),
        ],
        [
            'key' => 'demonstrativo.despesas_operacionais',
            'label' => '(−) Despesas operacionais',
            'tipo' => 'valor',
            'obs' => 'Competência',
        ],
        [
            'key' => 'demonstrativo.despesas_administrativas',
            'label' => '(−) Despesas administrativas',
            'tipo' => 'valor',
            'obs' => 'Competência',
        ],
        [
            'key' => 'demonstrativo.despesas_comerciais',
            'label' => '(−) Despesas comerciais',
            'tipo' => 'valor',
            'obs' => 'Competência',
        ],
        [
            'key' => 'demonstrativo.despesas_financeiras',
            'label' => '(−) Despesas financeiras',
            'tipo' => 'valor',
            'obs' => 'Competência',
        ],
        [
            'key' => 'demonstrativo.custos_cp_competencia',
            'label' => 'Custos CP (separado do CMV)',
            'tipo' => 'info',
            'obs' => 'Informativo',
        ],
        [
            'key' => 'demonstrativo.resultado_operacional',
            'label' => '(=) Resultado operacional gerencial',
            'tipo' => (! empty($demo['resultado_operacional_disponivel']) ? 'total' : 'pending'),
            'obs' => ! empty($demo['resultado_operacional_disponivel']) ? 'Gerencial' : 'Pendente',
            'force_valor' => ! empty($demo['resultado_operacional_disponivel']),
        ],
    ]);
    $resolveValor = static function (array $root, string $path): mixed {
        $cur = $root;
        foreach (explode('.', $path) as $seg) {
            if (! is_array($cur) || ! array_key_exists($seg, $cur)) {
                return null;
            }
            $cur = $cur[$seg];
        }

        return $cur;
    };
@endphp

<div class="{{ $pageClass }} erp-dre-gerencial" wire:keydown.escape.window="onEscape" wire:keydown.f5.window.prevent="gerar">
    <div class="{{ $pageClass }}__filters">
        <div class="{{ $pageClass }}__filters-row">
            <div
                class="erp-conciliacao__period-group"
                wire:ignore
                wire:key="dre-period-{{ $periodoDeIso }}-{{ $periodoAteIso }}"
                data-erp-date-group
            >
                <label class="erp-conciliacao__inline">
                    <span class="{{ $pageClass }}__locate-label">Período de</span>
                    <input
                        type="date"
                        data-erp-date
                        data-wire-field="periodoDe"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $periodoDeIso }}"
                        value="{{ $periodoDeIso }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="{{ $pageClass }}__input erp-conciliacao__period"
                        aria-label="Período inicial"
                    >
                </label>
                <label class="erp-conciliacao__inline">
                    <span class="{{ $pageClass }}__locate-label">Período até</span>
                    <input
                        type="date"
                        data-erp-date
                        data-wire-field="periodoAte"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $periodoAteIso }}"
                        value="{{ $periodoAteIso }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="{{ $pageClass }}__input erp-conciliacao__period"
                        aria-label="Período final"
                    >
                </label>
            </div>

            <div class="{{ $pageClass }}__search-actions">
                <button
                    type="button"
                    wire:click="gerar"
                    class="{{ $pageClass }}__btn"
                    data-erp-key="F5"
                    wire:loading.attr="disabled"
                    wire:target="gerar"
                >
                    <span wire:loading.remove wire:target="gerar"><kbd>F5</kbd> | Gerar</span>
                    <span wire:loading wire:target="gerar">Gerando…</span>
                </button>
                <button
                    type="button"
                    wire:click="abrirClassificar"
                    class="{{ $pageClass }}__btn"
                    wire:loading.attr="disabled"
                    title="Classificar planos para a DRE"
                >
                    Classificar planos
                </button>
                <button
                    type="button"
                    wire:click="abrirParametros"
                    class="{{ $pageClass }}__btn"
                    wire:loading.attr="disabled"
                    title="Parâmetros de tributos da DRE"
                >
                    Parâmetros
                </button>
            </div>

            <span class="erp-dre-gerencial__badge">Apuração parcial</span>
        </div>
    </div>

    <p class="{{ $pageClass }}__hint">
        @if ($this->gerado)
            Demonstrativo gerencial parcial. Impostos não deduzem a receita nesta versão.
        @else
            Informe o período e clique em Gerar.
        @endif
    </p>

    @if ($this->erro !== '')
        <p class="{{ $pageClass }}__hint erp-dre-gerencial__erro">{{ $this->erro }}</p>
    @endif

    @if ($this->gerado)
        <div class="erp-dre-gerencial__meta">
            Empresa #{{ (int) ($r['empresa_id'] ?? 0) }}
            · {{ $fmtDataBr($r['periodo_de'] ?? null) }} a {{ $fmtDataBr($r['periodo_ate'] ?? null) }}
            · {{ (int) (($r['contagens']['vendas_fechadas'] ?? 0) + ($r['contagens']['pdv_sem_espelho'] ?? 0)) }} venda(s)
            · {{ (int) ($r['contagens']['os_faturadas'] ?? 0) }} OS
            · {{ (int) ($r['contagens']['devolucoes_finalizadas'] ?? 0) }} devolução(ões)
            @if (isset($r['tempo_geracao_ms']))
                · {{ (int) $r['tempo_geracao_ms'] }} ms
            @endif
        </div>
    @endif

    <div class="erp-dre-gerencial__body">
        <div class="erp-dre-gerencial__table-wrap">
            <table class="erp-dre-gerencial__table">
                <thead>
                    <tr>
                        <th class="col-desc">Descrição</th>
                        <th class="col-valor is-right">Valor</th>
                        <th class="col-obs">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($linhas as $linha)
                        @php
                            $tipo = $linha['tipo'];
                            $pendente = $tipo === 'pending';
                            $forceValor = (bool) ($linha['force_valor'] ?? false);
                            $obs = $linha['obs'] ?? '—';
                            $valor = '—';
                            if ($this->gerado && isset($linha['key']) && (! $pendente || $forceValor)) {
                                $raw = $resolveValor($r, (string) $linha['key']);
                                $valor = $raw === null ? '—' : $this->formatMoney($raw);
                            }
                            $rowClass = match ($tipo) {
                                'subtotal' => 'is-subtotal',
                                'total' => 'is-total',
                                'info' => 'is-info',
                                'pending' => 'is-pending',
                                default => '',
                            };
                        @endphp
                        <tr @class([$rowClass => $rowClass !== ''])>
                            <td class="col-desc">{{ $linha['label'] }}</td>
                            <td class="col-valor is-right">{{ $valor }}</td>
                            <td class="col-obs">{{ $obs }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($this->gerado)
            @if ($exibirTributos)
                <div class="erp-dre-gerencial__sec">
                    <div class="erp-dre-gerencial__sec-title">Tributos (informativo / estimativa — não deduzidos nos resultados)</div>
                    @if ($estimAtiva)
                        <p class="erp-dre-gerencial__sec-note">
                            Estimativa Simples {{ $aliqPctLabel }}% sobre base comercial
                            ({{ $estim['base_label'] ?? 'receita após devoluções' }}
                            {{ $this->formatMoney($estim['base'] ?? null) }})
                            = {{ $this->formatMoney($estim['valor'] ?? null) }}.
                            Não é o DAS oficial; não entra nos subtotais (evita duplicar DAS nas despesas).
                        </p>
                    @elseif ($tribRegimeNormal && $tribDisponivel)
                        <table class="erp-dre-gerencial__table erp-dre-gerencial__table--compact">
                            <tbody>
                                <tr class="is-info">
                                    <td class="col-desc">Origem</td>
                                    <td class="col-valor is-right" colspan="2">
                                        NF-e saída autorizada · CRT 3 · {{ (int) ($trib['docs_considerados'] ?? 0) }} doc(s)
                                        · cobertura {{ number_format((float) ($trib['cobertura_pct'] ?? 0), 0) }}%
                                    </td>
                                </tr>
                                <tr>
                                    <td class="col-desc">ICMS próprio</td>
                                    <td class="col-valor is-right">{{ $this->formatMoney($trib['icms_proprio'] ?? null) }}</td>
                                    <td class="col-obs">Destacado</td>
                                </tr>
                                <tr>
                                    <td class="col-desc">ICMS-ST cobrada</td>
                                    <td class="col-valor is-right">{{ $this->formatMoney($trib['icms_st_cobrada'] ?? null) }}</td>
                                    <td class="col-obs">Destacado</td>
                                </tr>
                                <tr>
                                    <td class="col-desc">PIS</td>
                                    <td class="col-valor is-right">{{ $this->formatMoney($trib['pis'] ?? null) }}</td>
                                    <td class="col-obs">Destacado</td>
                                </tr>
                                <tr>
                                    <td class="col-desc">COFINS</td>
                                    <td class="col-valor is-right">{{ $this->formatMoney($trib['cofins'] ?? null) }}</td>
                                    <td class="col-obs">Destacado</td>
                                </tr>
                                <tr class="is-subtotal">
                                    <td class="col-desc">Total destacado</td>
                                    <td class="col-valor is-right">{{ $this->formatMoney($trib['total_destacado'] ?? null) }}</td>
                                    <td class="col-obs">Não deduzido</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="erp-dre-gerencial__sec-note">
                            Valores destacados na NF-e não representam automaticamente imposto devido.
                        </p>
                    @elseif ($tribRegimeNormal)
                        <p class="erp-dre-gerencial__sec-note erp-dre-gerencial__sec-note--block">
                            CRT 3: nenhuma NF-e de saída autorizada no período.
                        </p>
                    @elseif ($tribCrt === 1)
                        <p class="erp-dre-gerencial__sec-note erp-dre-gerencial__sec-note--block">
                            Simples Nacional: habilite a estimativa e informe a alíquota nos Parâmetros, se desejar.
                        </p>
                    @else
                        <p class="erp-dre-gerencial__sec-note erp-dre-gerencial__sec-note--block">
                            Regime CRT {{ $tribCrt }} ({{ $trib['regime'] ?? ($demo['dre_parametros']['regime'] ?? '—') }}):
                            apuração tributária informativa não disponível.
                        </p>
                    @endif
                </div>
            @endif

            @if ($pendencias !== [])
                <div class="erp-dre-gerencial__sec erp-dre-gerencial__sec--pend">
                    <div class="erp-dre-gerencial__sec-title">Pendências</div>
                    <ul class="erp-dre-gerencial__pend-list">
                        @foreach ($pendencias as $p)
                            <li>{{ $p }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </div>

    <div class="erp-rh-treinamentos-actions erp-dre-gerencial__actions">
        <button type="button" wire:click="closeScreen" class="erp-rh-treinamentos-actions__btn erp-rh-treinamentos-actions__btn--close">
            <span class="erp-rh-treinamentos-actions__icon erp-rh-treinamentos-actions__icon--close">✕</span>
            <span class="erp-rh-treinamentos-actions__label">Fechar</span>
        </button>
    </div>

    @if ($this->showParametros)
        <div class="erp-dre-classificar">
            <div class="erp-dre-classificar__backdrop" wire:click="fecharParametros"></div>
            <div class="erp-dre-classificar__dialog" role="dialog" aria-modal="true" aria-label="Parâmetros de tributos da DRE">
                <div class="erp-dre-classificar__titlebar">
                    <strong>Parâmetros de tributos</strong>
                    <button type="button" class="erp-dre-classificar__close" wire:click="fecharParametros" aria-label="Fechar">&times;</button>
                </div>
                <p class="erp-dre-classificar__hint">
                    Por empresa · {{ $this->paramRegimeLabel }} · padrões: tributos e estimativa desligados.
                </p>
                <div class="erp-dre-classificar__body" style="padding:0.6rem 0.75rem;">
                    <label style="display:flex;align-items:center;gap:0.4rem;margin-bottom:0.55rem;font-size:0.8125rem;">
                        <input type="checkbox" wire:model="paramExibirTributos">
                        Exibir tributos na DRE
                    </label>
                    <label style="display:flex;align-items:center;gap:0.4rem;margin-bottom:0.55rem;font-size:0.8125rem;@if(! $this->paramPermiteEstimativaSimples) opacity:0.55; @endif">
                        <input
                            type="checkbox"
                            wire:model="paramCalcularEstimativaSimples"
                            @disabled(! $this->paramPermiteEstimativaSimples)
                        >
                        Calcular estimativa do Simples Nacional
                    </label>
                    @if (! $this->paramPermiteEstimativaSimples)
                        <p class="erp-dre-classificar__hint" style="margin:0 0 0.5rem;">
                            Estimativa disponível apenas para Simples Nacional (CRT 1), excluindo MEI.
                        </p>
                    @endif
                    <label style="display:block;font-size:0.8125rem;margin-bottom:0.25rem;">
                        Alíquota estimada do Simples (%)
                    </label>
                    <input
                        type="text"
                        inputmode="decimal"
                        class="erp-dre-classificar__select"
                        style="width:8rem;"
                        wire:model="paramAliquotaSimplesPct"
                        placeholder="ex.: 10"
                        @disabled(! $this->paramPermiteEstimativaSimples)
                    >
                    <p class="erp-dre-classificar__hint" style="margin-top:0.45rem;">
                        Sem valor presumido. Estimativa = receita comercial após devoluções × alíquota / 100.
                        Não é DAS oficial e não deduz dos resultados (evita duplicar despesas).
                    </p>
                </div>
                <div class="erp-dre-classificar__footer">
                    <button type="button" wire:click="salvarParametros">Salvar</button>
                    <button type="button" wire:click="fecharParametros">Cancelar</button>
                </div>
            </div>
        </div>
    @endif

    @if ($this->showClassificar)
        <div class="erp-dre-classificar">
            <div class="erp-dre-classificar__backdrop" wire:click="fecharClassificar"></div>
            <div class="erp-dre-classificar__dialog" role="dialog" aria-modal="true" aria-label="Classificar planos na DRE">
                <div class="erp-dre-classificar__titlebar">
                    <strong>Classificação DRE dos planos</strong>
                    <button type="button" class="erp-dre-classificar__close" wire:click="fecharClassificar" aria-label="Fechar">&times;</button>
                </div>
                <p class="erp-dre-classificar__hint">
                    Não altera o cadastro do plano. Por empresa. Classificações existentes são preservadas.
                </p>
                <div class="erp-dre-classificar__body">
                    <table class="erp-dre-classificar__table">
                        <thead>
                            <tr>
                                <th>Cód.</th>
                                <th>Descrição</th>
                                <th>D/C</th>
                                <th>Classificação DRE</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->planosClassificar as $plano)
                                <tr>
                                    <td>{{ $plano['codigo'] }}</td>
                                    <td>{{ $plano['descricao'] }}</td>
                                    <td>{{ $plano['dc'] ?: '—' }}</td>
                                    <td>
                                        <select
                                            class="erp-dre-classificar__select"
                                            wire:change="salvarClassificacaoPlano({{ (int) $plano['id'] }}, $event.target.value)"
                                        >
                                            @foreach ($this->opcoesClassificacao() as $key => $label)
                                                <option value="{{ $key }}" @selected(($plano['classificacao'] ?? '') === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">Nenhum plano ativo cadastrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="erp-dre-classificar__footer">
                    <button type="button" wire:click="abrirPreviaPadrao" title="Sugestões seguras para planos ainda sem classificação">
                        Aplicar padrão
                    </button>
                    <button type="button" wire:click="fecharClassificar">Fechar</button>
                </div>
            </div>
        </div>
    @endif

    @if ($this->showPreviaPadrao)
        <div class="erp-dre-classificar erp-dre-classificar--previa" wire:key="dre-previa-padrao">
            <div class="erp-dre-classificar__backdrop" wire:click="cancelarPreviaPadrao"></div>
            <div class="erp-dre-classificar__dialog" role="dialog" aria-modal="true" aria-label="Prévia do padrão DRE">
                <div class="erp-dre-classificar__titlebar">
                    <strong>Aplicar padrão — prévia</strong>
                    <button type="button" class="erp-dre-classificar__close" wire:click="cancelarPreviaPadrao" aria-label="Fechar">&times;</button>
                </div>
                <p class="erp-dre-classificar__hint">
                    {{ (int) ($this->previaPadraoResumo['aplicar'] ?? 0) }} a classificar ·
                    {{ (int) ($this->previaPadraoResumo['preservados'] ?? 0) }} já configurado(s) ·
                    {{ (int) ($this->previaPadraoResumo['ignorados'] ?? 0) }} ignorado(s) (ambíguo / sem padrão).
                    Classificações existentes não são alteradas.
                </p>
                <div class="erp-dre-classificar__body">
                    @if (count($this->previaPadrao) === 0)
                        <p class="erp-dre-classificar__empty">
                            Nenhum plano elegível. Já configurados foram preservados; impostos, retirada, devolução e boleto ficam de fora por serem ambíguos.
                        </p>
                    @else
                        <table class="erp-dre-classificar__table">
                            <thead>
                                <tr>
                                    <th>Cód.</th>
                                    <th>Descrição</th>
                                    <th>Atual</th>
                                    <th>Sugestão</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->previaPadrao as $row)
                                    <tr>
                                        <td>{{ $row['codigo'] }}</td>
                                        <td>{{ $row['descricao'] }}</td>
                                        <td>{{ $row['label_atual'] ?? 'Não classificado' }}</td>
                                        <td>{{ $row['label_sugerida'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
                <div class="erp-dre-classificar__footer">
                    @if (count($this->previaPadrao) > 0)
                        <button type="button" wire:click="confirmarAplicarPadrao">Confirmar</button>
                    @endif
                    <button type="button" wire:click="cancelarPreviaPadrao">
                        {{ count($this->previaPadrao) > 0 ? 'Cancelar' : 'Fechar' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
</x-filament-panels::page>
