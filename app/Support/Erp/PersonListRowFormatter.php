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
            'endereco_lista' => $this->textOrDash($this->endereco($record)),
            'bairro_lista' => $this->textOrDash(trim((string) ($record->bairro ?? ''))),
            'cidade_lista' => $this->textOrDash($this->cidade($record)),
            'whatsapp_lista' => $this->textOrDash($this->whatsapp($record)),
        ];
    }

    private function endereco(Person $record): string
    {
        $partes = array_filter([
            filled($record->endereco) ? trim((string) $record->endereco) : null,
            filled($record->numero) ? 'nº '.trim((string) $record->numero) : null,
        ]);

        return implode(', ', $partes);
    }

    private function cidade(Person $record): string
    {
        $cidade = trim((string) ($record->cidade_nome ?? ''));
        $uf = trim((string) ($record->uf ?? ''));

        if ($cidade !== '' && $uf !== '') {
            return mb_strtoupper($cidade, 'UTF-8').', '.mb_strtoupper($uf, 'UTF-8');
        }

        if ($cidade !== '') {
            return mb_strtoupper($cidade, 'UTF-8');
        }

        return mb_strtoupper($uf, 'UTF-8');
    }

    private function whatsapp(Person $record): string
    {
        $digits = preg_replace('/\D/', '', (string) ($record->fone1 ?? '')) ?? '';

        if (strlen($digits) === 11) {
            return sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
        }

        if (strlen($digits) === 10) {
            return sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
        }

        return trim((string) ($record->fone1 ?? ''));
    }

    private function textOrDash(string $value): string
    {
        return $value !== '' ? e($value) : '—';
    }
}
