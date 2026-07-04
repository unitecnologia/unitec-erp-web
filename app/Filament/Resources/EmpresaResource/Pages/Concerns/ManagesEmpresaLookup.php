<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Models\Empresa;
use App\Support\Erp\CnpjLookupService;
use Filament\Notifications\Notification;
use RuntimeException;

trait ManagesEmpresaLookup
{
    public function searchEmpresaCnpj(?string $cnpj = null): void
    {
        if (filled($cnpj)) {
            $this->data['cnpj'] = trim($cnpj);
        }

        if (blank($this->data['cnpj'] ?? null)) {
            $this->data['cnpj'] = $this->form->getState()['cnpj'] ?? null;
        }

        if (($this->data['pessoa_tipo'] ?? null) !== Empresa::PESSOA_JURIDICA) {
            Notification::make()
                ->title('Selecione Pessoa Jurídica.')
                ->warning()
                ->send();

            return;
        }

        $digits = preg_replace('/\D/', '', (string) ($this->data['cnpj'] ?? ''));

        if (strlen($digits) !== 14) {
            Notification::make()
                ->title('Informe um CNPJ completo.')
                ->warning()
                ->send();

            return;
        }

        try {
            $fields = app(CnpjLookupService::class)->fetch($digits);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Consulta de CNPJ')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $mapped = array_filter([
            'cnpj' => $fields['cpf_cnpj'] ?? null,
            'razao_social' => $fields['nome_razao'] ?? null,
            'fantasia' => $fields['apelido_fantasia'] ?? null,
            'ie' => $fields['rg_ie'] ?? null,
            'cep' => $fields['cep'] ?? null,
            'endereco' => $fields['endereco'] ?? null,
            'numero' => $fields['numero'] ?? null,
            'complemento' => $fields['complemento'] ?? null,
            'bairro' => $fields['bairro'] ?? null,
            'cidade_codigo' => $fields['cidade_codigo'] ?? null,
            'cidade' => $fields['cidade_nome'] ?? null,
            'uf' => $fields['uf'] ?? null,
            'email' => $fields['email'] ?? null,
            'telefone' => $fields['fone1'] ?? null,
            'regime_tributario' => $fields['regime_tributario'] ?? null,
            'pessoa_tipo' => Empresa::PESSOA_JURIDICA,
        ], fn (?string $value): bool => filled($value));

        $this->data = [
            ...$this->data,
            ...$mapped,
        ];

        $fantasia = trim((string) ($this->data['fantasia'] ?? ''));
        if ($fantasia !== '') {
            $this->data['nome'] = $fantasia;
        }

        $this->form->fill($this->data);

        $hasIe = filled($mapped['ie'] ?? null);
        $missingAddress = blank($mapped['endereco'] ?? null) || blank($mapped['cep'] ?? null);

        $body = match (true) {
            $hasIe && ! $missingAddress => 'Dados preenchidos automaticamente.',
            $hasIe => 'Dados preenchidos com IE. Confira endereço e complementos se necessário.',
            ! $missingAddress => 'Dados preenchidos. IE não informada nas consultas — preencha manualmente se necessário.',
            default => 'Dados parciais preenchidos. Complete endereço e IE manualmente se necessário.',
        };

        Notification::make()
            ->title('Empresa encontrada')
            ->body($body)
            ->success()
            ->send();

        $this->dispatch('erp-masks-refresh');
    }
}
