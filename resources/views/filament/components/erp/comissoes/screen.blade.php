@php
    use App\Models\ComissaoPeriodo;
    use Illuminate\Support\Carbon;

    $periodoDeValor = filled($this->periodoDe) ? Carbon::parse($this->periodoDe)->format('d/m/Y') : '';
    $periodoAteValor = filled($this->periodoAte) ? Carbon::parse($this->periodoAte)->format('d/m/Y') : '';
    $statusOptions = ['todos' => '<todos>'] + ComissaoPeriodo::statusLabels();
    $vendedorOptions = ['todos' => '<todos>'] + $this->vendedorOptions();
@endphp

<div class="erp-comissoes" wire:ignore.self>
    <div class="erp-comissoes__topbar">
        <span class="erp-comissoes__title">Comissões de Vendedores</span>
    </div>

    <fieldset class="erp-comissoes__filtros">
        <div class="erp-comissoes__filtros-row">
            <label class="erp-comissoes__field">
                <span>Período</span>
                <div class="erp-comissoes__periodo" wire:ignore data-erp-date-group>
                    <input type="text" data-erp-date data-wire-field="periodoDe" data-erp-date-wire="iso"
                           data-erp-date-initial="{{ $this->periodoDe }}" value="{{ $periodoDeValor }}"
                           class="erp-comissoes__input" placeholder="dd/mm/aaaa" />
                    <span>até</span>
                    <input type="text" data-erp-date data-wire-field="periodoAte" data-erp-date-wire="iso"
                           data-erp-date-initial="{{ $this->periodoAte }}" value="{{ $periodoAteValor }}"
                           class="erp-comissoes__input" placeholder="dd/mm/aaaa" />
                </div>
            </label>

            <label class="erp-comissoes__field">
                <span>Vendedor (filtro)</span>
                <select wire:model.live="vendedorFilter" class="erp-comissoes__select">
                    @foreach ($vendedorOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-comissoes__field">
                <span>Status</span>
                <select wire:model.live="statusFilter" class="erp-comissoes__select">
                    @foreach ($statusOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-comissoes__field">
                <span>Calcular para</span>
                <select wire:model="calcularVendedorId" class="erp-comissoes__select">
                    <option value="">— selecione —</option>
                    @foreach ($this->vendedorOptions() as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <button type="button" class="erp-comissoes__btn" wire:click="consultar">Consultar</button>
        </div>
    </fieldset>
</div>
