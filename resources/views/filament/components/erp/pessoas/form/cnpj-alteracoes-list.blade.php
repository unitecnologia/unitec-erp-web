<div class="erp-pcad-cnpj-diff" role="region" aria-label="Alterações encontradas no CNPJ">
    <table class="erp-pcad-cnpj-diff__table">
        <thead>
            <tr>
                <th class="erp-pcad-cnpj-diff__check" scope="col" title="Aplicar">
                    <span class="sr-only">Aplicar</span>
                </th>
                <th scope="col">Campo</th>
                <th scope="col">Cadastro atual</th>
                <th scope="col">Consulta CNPJ</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($this->pessoaCnpjAlteracoes as $index => $row)
                <tr wire:key="pcad-cnpj-diff-{{ $row['campo'] }}">
                    <td class="erp-pcad-cnpj-diff__check">
                        <input
                            type="checkbox"
                            wire:model.live="pessoaCnpjAlteracoes.{{ $index }}.selected"
                            aria-label="Atualizar {{ $row['label'] }}"
                        >
                    </td>
                    <td class="erp-pcad-cnpj-diff__campo">{{ $row['label'] }}</td>
                    <td class="erp-pcad-cnpj-diff__atual">{{ \App\Support\Erp\PersonCnpjFieldDiff::formatDisplay($row['campo'], $row['atual']) }}</td>
                    <td class="erp-pcad-cnpj-diff__novo">{{ \App\Support\Erp\PersonCnpjFieldDiff::formatDisplay($row['campo'], $row['novo']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
