<?php

namespace App\Support\Erp;

use App\Models\Person;

final class PersonListRowFormatter
{
    /**
     * @return array<string, string>
     */
    public function format(Person $record): array
    {
        return [
            'codigo' => e((string) $record->codigo),
            'nome_razao' => e((string) $record->nome_razao),
            'apelido_fantasia' => filled($record->apelido_fantasia) ? e((string) $record->apelido_fantasia) : '—',
            'cpf_cnpj' => filled($record->cpf_cnpj) ? e((string) $record->cpf_cnpj) : '—',
            'rg_ie' => filled($record->rg_ie) ? e((string) $record->rg_ie) : '—',
            'endereco_lista' => filled($record->endereco_lista) ? e((string) $record->endereco_lista) : '—',
        ];
    }
}
