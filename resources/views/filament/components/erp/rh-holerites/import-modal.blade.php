@if ($this->holeriteImportOpen)
    @php
        $funcionarios = $this->holeriteFuncionarioOptions();
        $meses = \App\Models\RhHolerite::meses();
    @endphp
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-rh-holerite-import-modal"
        wire:keydown.escape.window="closeHoleriteImport"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeHoleriteImport"></div>
        <div class="erp-lookup-modal__window erp-contador-form-modal__window erp-contador-form-modal__window--wide" role="dialog" aria-modal="true">
            <div class="erp-lookup-modal__titlebar">
                <span>Importar holerites (mapeamento manual)</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeHoleriteImport">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-contador-form-modal__body">
                <p class="erp-rh-holerite-form-modal__hint">Informe o <strong>ID do funcionário</strong> em cada linha — não há associação automática por nome.</p>

                <table class="erp-rh-holerite-import-modal__table">
                    <thead>
                        <tr>
                            <th>Linha</th>
                            <th>Funcionário (ID) *</th>
                            <th>Ano</th>
                            <th>Mês</th>
                            <th>PDF *</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->holeriteImportRows as $index => $row)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <select wire:model="holeriteImportRows.{{ $index }}.funcionario_id" class="erp-pcad-form__select">
                                        <option value="">ID…</option>
                                        @foreach ($funcionarios as $opt)
                                            <option value="{{ $opt['id'] }}">{{ $opt['id'] }} — {{ $opt['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" min="2000" max="2100" wire:model="holeriteImportRows.{{ $index }}.competencia_ano" class="erp-pcad-form__input erp-pcad-form__input--date">
                                </td>
                                <td>
                                    <select wire:model="holeriteImportRows.{{ $index }}.competencia_mes" class="erp-pcad-form__select">
                                        @foreach ($meses as $num => $nome)
                                            <option value="{{ $num }}">{{ sprintf('%02d', $num) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="file" wire:model="holeriteImportFiles.{{ $index }}" class="erp-pcad-form__input erp-pcad-form__input--file" accept=".pdf,application/pdf">
                                </td>
                                <td>
                                    @if (count($this->holeriteImportRows) > 1)
                                        <button type="button" class="erp-rh-holerites-actions__btn" wire:click="removeHoleriteImportRow({{ $index }})">✕</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <button type="button" class="erp-rh-holerites-actions__btn" wire:click="addHoleriteImportRow">+ Linha</button>

                @if ($this->holeriteImportPreview !== [])
                    <div class="erp-rh-holerite-import-modal__preview">
                        <h4>Conferência</h4>
                        <table class="erp-rh-holerite-import-modal__table erp-rh-holerite-import-modal__table--preview">
                            <thead>
                                <tr>
                                    <th>Linha</th>
                                    <th>Funcionário</th>
                                    <th>Competência</th>
                                    <th>Status</th>
                                    <th>Mensagem</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->holeriteImportPreview as $prev)
                                    <tr class="{{ ($prev['ok'] ?? false) ? 'erp-rh-holerite-import-modal__ok' : 'erp-rh-holerite-import-modal__err' }}">
                                        <td>{{ $prev['linha'] ?? '—' }}</td>
                                        <td>{{ $prev['funcionario'] ?? '—' }}</td>
                                        <td>{{ $prev['competencia'] ?? '—' }}</td>
                                        <td>{{ ($prev['ok'] ?? false) ? 'OK' : 'Erro' }}</td>
                                        <td>{{ $prev['mensagem'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions">
                <button type="button" wire:click="validarHoleriteImport" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__label">Validar</span>
                </button>
                <button
                    type="button"
                    wire:click="confirmarHoleriteImport"
                    class="erp-pcad-actions__btn"
                    @disabled(! $this->holeriteImportValidado)
                >
                    <span class="erp-pcad-actions__label">Confirmar importação</span>
                </button>
                <button type="button" wire:click="closeHoleriteImport" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__label">Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif
