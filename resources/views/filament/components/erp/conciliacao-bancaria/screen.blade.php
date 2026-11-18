@php
    // Mesmo chrome BEM do RH Treinamentos (erp-rh-list.css) — só o conteúdo dos filtros muda.
    $pageClass = 'erp-rh-treinamentos';
    $contas = $this->contaBancariaOptions();
    $pageSizeOptions = [25, 50, 100];
    $periodoDeIso = filled($this->periodoDe) ? $this->periodoDe : '';
    $periodoAteIso = filled($this->periodoAte) ? $this->periodoAte : '';
@endphp

<div class="{{ $pageClass }}" wire:ignore.self>
    <div class="{{ $pageClass }}__filters">
        <div class="{{ $pageClass }}__filters-row">
            <label class="erp-conciliacao__inline">
                <span class="{{ $pageClass }}__locate-label">Conta</span>
                <select
                    wire:model.live="caixaContaId"
                    class="{{ $pageClass }}__select erp-conciliacao__select--conta"
                    aria-label="Conta bancária"
                >
                    <option value="">Selecione…</option>
                    @foreach ($contas as $opt)
                        <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <div
                class="erp-conciliacao__period-group"
                wire:ignore
                wire:key="conciliacao-period-{{ $periodoDeIso }}-{{ $periodoAteIso }}"
                data-erp-date-group
                data-erp-date-auto-apply="1"
                data-erp-date-apply-method="consultar"
            >
                <label class="erp-conciliacao__inline">
                    <span class="{{ $pageClass }}__locate-label">Data inicial</span>
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
                        aria-label="Data inicial"
                    >
                </label>
                <label class="erp-conciliacao__inline">
                    <span class="{{ $pageClass }}__locate-label">Data final</span>
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
                        aria-label="Data final"
                    >
                </label>
            </div>

            <label class="erp-conciliacao__inline">
                <span class="{{ $pageClass }}__locate-label">Situação</span>
                <select wire:model.live="situacao" class="{{ $pageClass }}__select" aria-label="Situação">
                    <option value="todos">Todas</option>
                    <option value="pendente">Pendente</option>
                    <option value="conciliado">Conciliado</option>
                    <option value="ignorado">Ignorado</option>
                </select>
            </label>

            <div class="{{ $pageClass }}__search-actions">
                <button type="button" wire:click="consultar" class="{{ $pageClass }}__btn">
                    Consultar
                </button>
                <button
                    type="button"
                    wire:click="ajustarFiltroParaTodoExtratoDaConta"
                    class="{{ $pageClass }}__btn {{ $pageClass }}__btn--secondary"
                    title="Ajusta as datas para o menor e o maior movimento já importado nesta conta"
                >
                    Período do extrato
                </button>
                <label
                    class="{{ $pageClass }}__btn {{ $pageClass }}__btn--secondary erp-conciliacao__ofx-btn"
                    title="Importar extrato OFX para a conta selecionada"
                >
                    <span wire:loading.remove wire:target="ofxUpload">Importar OFX</span>
                    <span wire:loading wire:target="ofxUpload">Importando…</span>
                    <input
                        type="file"
                        wire:model="ofxUpload"
                        class="erp-conciliacao__ofx-input"
                        accept=".ofx,.OFX,.qfx,.QFX,application/x-ofx,application/xml,text/xml,text/plain"
                    >
                </label>
            </div>

            <div class="{{ $pageClass }}__page-size-group">
                <label class="{{ $pageClass }}__page-size-label">
                    por página
                    <select wire:model.live="tableRecordsPerPage" class="{{ $pageClass }}__select {{ $pageClass }}__page-size-select">
                        @foreach ($pageSizeOptions as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </div>

    <p class="{{ $pageClass }}__hint">
        Selecione a conta BANCO e importe o OFX. Lançamentos entram como Pendentes — sem baixa automática.
    </p>
    @error('ofxUpload')
        <p class="{{ $pageClass }}__hint" style="color:#b91c1c;">{{ $message }}</p>
    @enderror

    @include('filament.components.erp.list-scripts', ['config' => $this->getErpListKeyboardConfigForView()])
</div>
