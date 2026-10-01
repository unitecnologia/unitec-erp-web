<?php

namespace App\Filament\Resources\PersonResource\Pages\Concerns;

use Illuminate\Validation\ValidationException;

trait ManagesPersonDocumentoDuplicadoModal
{
    public bool $personDocumentoDuplicadoOpen = false;

    public string $personDocumentoDuplicadoTipo = 'CPF';

    public string $personDocumentoDuplicadoCodigo = '';

    public string $personDocumentoDuplicadoNome = '';

    public function dismissPersonDocumentoDuplicado(): void
    {
        $this->personDocumentoDuplicadoOpen = false;
        $this->activeFormTab = 'dados';
        $this->dispatch('erp-pessoa-focus-cpf');
    }

    /**
     * Intercepta só o erro de CPF/CNPJ duplicado (PersonDocumentoUnico) para o modal de Atenção.
     * Demais falhas de validação seguem o toast genérico.
     */
    protected function presentPersonDocumentoDuplicadoModalIfNeeded(ValidationException $exception): bool
    {
        $messages = $exception->errors()['data.cpf_cnpj'] ?? [];

        $hit = null;
        foreach ($messages as $message) {
            if (
                is_string($message)
                && (
                    str_starts_with($message, 'Já existe um cadastro com este CPF.')
                    || str_starts_with($message, 'Já existe um cadastro com este CNPJ.')
                )
            ) {
                $hit = $message;
                break;
            }
        }

        if ($hit === null) {
            return false;
        }

        $tipo = str_contains($hit, 'CNPJ') ? 'CNPJ' : 'CPF';
        [$codigo, $nome] = $this->parsePersonDocumentoDuplicadoRef($hit);

        $this->personDocumentoDuplicadoOpen = true;
        $this->personDocumentoDuplicadoTipo = $tipo;
        $this->personDocumentoDuplicadoCodigo = $codigo;
        $this->personDocumentoDuplicadoNome = $nome;
        $this->activeFormTab = 'dados';

        return true;
    }

    /**
     * Extrai código/nome da mensagem já montada por PersonCpfCnpjUnicidade (sem nova query).
     *
     * @return array{0: string, 1: string}
     */
    private function parsePersonDocumentoDuplicadoRef(string $message): array
    {
        if (! preg_match('/\((.+)\)\s*\.?$/u', $message, $m)) {
            return ['', ''];
        }

        $ref = trim($m[1]);

        if (preg_match('/^código\s+(.+?)\s+—\s+(.+)$/u', $ref, $parts)) {
            return [trim($parts[1]), trim($parts[2])];
        }

        if (preg_match('/^código\s+(.+)$/u', $ref, $parts)) {
            return [trim($parts[1]), ''];
        }

        return ['', $ref];
    }
}
