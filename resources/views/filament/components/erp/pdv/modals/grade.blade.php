@if ($this->activeModal === 'grade')
    <div class="erp-pdv-modal" role="dialog" aria-label="Seleção de grade">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelPdvGrade"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2>Grade / Variação</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <table class="erp-pdv__grid erp-pdv-modal__grid">
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Tamanho</th>
                            <th class="erp-pdv__grid-col-num">Estoque</th>
                            <th class="erp-pdv__grid-col-num">Preço</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->pdvGradeRows as $index => $grade)
                            <tr
                                wire:click="selectPdvGradeRow({{ $index }})"
                                wire:dblclick="confirmPdvGrade"
                                wire:key="pdv-grade-{{ $grade['grade_id'] ?? $index }}"
                                id="erp-pdv-grade-row-{{ $index }}"
                                @class([
                                    'erp-pdv__grid-row',
                                    'erp-pdv__grid-row--selected' => $this->selectedPdvGradeIndex === $index,
                                ])
                            >
                                <td class="erp-pdv__grid-col-descricao">{{ $grade['descricao'] ?? '—' }}</td>
                                <td>{{ $grade['tamanho'] ?? '' }}</td>
                                <td class="erp-pdv__grid-col-num">
                                    @php $qtd = (float) ($grade['qtd'] ?? 0); @endphp
                                    {{ fmod($qtd, 1.0) === 0.0 ? (int) $qtd : number_format($qtd, 3, ',', '') }}
                                </td>
                                <td class="erp-pdv__grid-col-num">{{ number_format((float) ($grade['preco'] ?? 0), 2, ',', '') }}</td>
                            </tr>
                        @empty
                            <tr class="erp-pdv__grid-empty">
                                <td colspan="4">Nenhuma grade cadastrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmPdvGrade" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" id="erp-pdv-grade-confirm">Confirmar</button>
                <button type="button" wire:click="cancelPdvGrade" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
