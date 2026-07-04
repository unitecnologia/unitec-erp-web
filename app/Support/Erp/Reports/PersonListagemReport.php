<?php

namespace App\Support\Erp\Reports;

use App\Models\Person;

class PersonListagemReport
{
    /**
     * @return array<string, string>
     */
    public static function columnDefinitions(): array
    {
        return [
            'codigo' => 'CÓDIGO',
            'nome_razao' => 'NOME/RAZÃO',
            'apelido_fantasia' => 'APELIDO/FANTASIA',
            'cpf_cnpj' => 'CPF/CNPJ',
            'rg_ie' => 'RG/IE',
            'endereco' => 'ENDEREÇO',
            'cidade_nome' => 'CIDADE',
            'uf' => 'UF',
            'fone1' => 'FONE',
            'celular1' => 'CELULAR',
            'email' => 'E-MAIL',
            'limite_credito' => 'LIMITE CRÉDITO',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return [
            'codigo',
            'nome_razao',
            'apelido_fantasia',
            'cpf_cnpj',
            'rg_ie',
            'endereco',
        ];
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    public static function resolveColumns(?array $requested): array
    {
        $allowed = array_keys(static::columnDefinitions());

        if ($requested === null || $requested === []) {
            return static::defaultColumns();
        }

        $columns = [];

        foreach ($requested as $column) {
            if (in_array($column, $allowed, true)) {
                $columns[] = $column;
            }
        }

        return $columns !== [] ? $columns : static::defaultColumns();
    }

    public static function cellValue(Person $person, string $column): string
    {
        return match ($column) {
            'codigo' => (string) ($person->codigo ?? ''),
            'nome_razao' => (string) ($person->nome_razao ?? ''),
            'apelido_fantasia' => (string) ($person->apelido_fantasia ?? ''),
            'cpf_cnpj' => (string) ($person->cpf_cnpj ?? ''),
            'rg_ie' => (string) ($person->rg_ie ?? ''),
            'endereco' => $person->endereco_lista,
            'cidade_nome' => (string) ($person->cidade_nome ?? ''),
            'uf' => (string) ($person->uf ?? ''),
            'fone1' => (string) ($person->fone1 ?? ''),
            'celular1' => (string) ($person->celular1 ?? ''),
            'email' => (string) ($person->email ?? ''),
            'limite_credito' => static::formatMoney((float) $person->limite_credito),
            default => '',
        };
    }

    public static function isNumericColumn(string $column): bool
    {
        return $column === 'limite_credito';
    }

    public static function isSummableColumn(string $column): bool
    {
        return static::isNumericColumn($column);
    }

    public static function columnRawValue(Person $person, string $column): ?float
    {
        return match ($column) {
            'limite_credito' => (float) $person->limite_credito,
            default => null,
        };
    }

    /**
     * @param  iterable<int, Person>  $people
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    public static function columnTotals(iterable $people, array $columns): array
    {
        $peopleList = is_array($people) ? $people : iterator_to_array($people);
        $count = count($peopleList);
        $sums = array_fill_keys($columns, 0.0);

        foreach ($peopleList as $person) {
            foreach ($columns as $column) {
                $raw = static::columnRawValue($person, $column);

                if ($raw !== null) {
                    $sums[$column] += $raw;
                }
            }
        }

        $totals = [];
        $labelPlaced = false;

        foreach ($columns as $column) {
            if ($column === 'codigo') {
                $totals[$column] = (string) $count;

                continue;
            }

            if (static::isSummableColumn($column)) {
                $totals[$column] = static::formatMoney($sums[$column]);

                continue;
            }

            $totals[$column] = $labelPlaced ? '' : 'TOTAL';
            $labelPlaced = true;
        }

        return $totals;
    }

    public static function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    public static function reportTitle(string $tipoFilter): string
    {
        return match ($tipoFilter) {
            'clientes' => 'LISTAGEM DE CLIENTES',
            'funcionarios' => 'LISTAGEM DE FUNCIONÁRIOS',
            'fornecedores' => 'LISTAGEM DE FORNECEDORES',
            'administradoras' => 'LISTAGEM DE ADMINISTRADORAS',
            'parceiros' => 'LISTAGEM DE PARCEIROS',
            'ccf_spc' => 'LISTAGEM SPC/CCF',
            'todos' => 'LISTAGEM DE PESSOAS',
            default => 'LISTAGEM DE PESSOAS',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'ativos' => 'Ativos',
            'inativos' => 'Inativos',
            'todos' => 'Todos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tipoLabels(): array
    {
        return [
            'clientes' => 'Clientes',
            'funcionarios' => 'Funcionários',
            'fornecedores' => 'Fornecedores',
            'administradoras' => 'Administradoras',
            'parceiros' => 'Parceiros',
            'ccf_spc' => 'SPC/CCF',
            'todos' => 'Todos',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function orderLabels(): array
    {
        return [
            'codigo' => 'Código',
            'nome_razao' => 'Nome/Razão',
            'apelido_fantasia' => 'Apelido/Fantasia',
            'cpf_cnpj' => 'CPF/CNPJ',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function searchFieldLabels(): array
    {
        return [
            'codigo' => 'Código',
            'nome_razao' => 'Razão/Nome',
            'apelido_fantasia' => 'Fantasia/Apelido',
            'cpf_cnpj' => 'CPF/CNPJ',
            'rg_ie' => 'RG/IE',
            'endereco' => 'Endereço',
        ];
    }
}
