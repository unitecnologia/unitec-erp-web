<?php

namespace App\Filament\Resources\PersonResource\Pages\Concerns;

use App\Models\Person;
use App\Support\Erp\CnpjLookupService;
use Filament\Notifications\Notification;
use RuntimeException;

trait ManagesPersonLookup
{
    public function searchPessoaJuridica(?string $cpfCnpj = null): void
    {
        if (filled($cpfCnpj)) {
            $this->data['cpf_cnpj'] = trim($cpfCnpj);
        }

        if (blank($this->data['cpf_cnpj'] ?? null)) {
            $this->data['cpf_cnpj'] = $this->form->getState()['cpf_cnpj'] ?? null;
        }

        if (($this->data['pessoa_tipo'] ?? null) !== Person::PESSOA_JURIDICA) {
            Notification::make()
                ->title('Selecione Pessoa Jurídica.')
                ->warning()
                ->send();

            return;
        }

        $cnpj = preg_replace('/\D/', '', (string) ($this->data['cpf_cnpj'] ?? ''));

        if (strlen($cnpj) !== 14) {
            Notification::make()
                ->title('Informe um CNPJ completo.')
                ->warning()
                ->send();

            return;
        }

        try {
            $fields = app(CnpjLookupService::class)->fetch($cnpj);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Consulta de CNPJ')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $hasIe = filled($fields['rg_ie'] ?? null);
        $missingAddress = blank($fields['endereco'] ?? null) || blank($fields['cep'] ?? null);

        $this->data = [
            ...$this->data,
            ...array_filter($fields, fn (?string $value): bool => filled($value)),
        ];

        if ($hasIe) {
            $this->syncTipoContribuinteFromIe();
        }

        $this->form->fill($this->data);

        $body = match (true) {
            $hasIe && ! $missingAddress => 'Dados preenchidos automaticamente.',
            $hasIe => 'Dados preenchidos com IE. Confira endereço e complementos se necessário.',
            ! $missingAddress => 'Dados preenchidos. IE não informada nas consultas — preencha manualmente se necessário.',
            default => 'Dados parciais preenchidos. Complete endereço e IE manualmente se necessário.',
        };

        Notification::make()
            ->title('Pessoa jurídica encontrada')
            ->body($body)
            ->success()
            ->send();

        $this->dispatch('erp-masks-refresh');
    }
}
