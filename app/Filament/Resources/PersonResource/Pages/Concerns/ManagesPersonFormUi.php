<?php

namespace App\Filament\Resources\PersonResource\Pages\Concerns;

use App\Models\Person;
use App\Models\TabelaPrazo;
use Filament\Notifications\Notification;

trait ManagesPersonFormUi
{
    public string $activeFormTab = 'dados';

    public function setActiveFormTab(string $tab): void
    {
        $this->activeFormTab = $tab;
    }

    public function modulePending(string $module): void
    {
        Notification::make()
            ->title($module)
            ->body('Em implementação.')
            ->info()
            ->send();
    }

    public function updatedDataRgIe(?string $value): void
    {
        $this->syncTipoContribuinteFromIe();
    }

    public function updatedDataPessoaTipo(?string $value): void
    {
        if ($value === Person::PESSOA_FISICA) {
            $this->data['tipo_contribuinte'] = 'nao_contribuinte';
        }
    }

    public function updatedDataCpfCnpj(?string $value): void
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        // 11 dígitos ainda pode ser CNPJ incompleto. Física só no blur (finalizarDocumentoPessoa).
        if (strlen($digits) === 14) {
            $this->data['pessoa_tipo'] = Person::PESSOA_JURIDICA;
        }
    }

    public function finalizarDocumentoPessoa(?string $cpfCnpj = null): void
    {
        if ($cpfCnpj !== null) {
            $this->data['cpf_cnpj'] = trim($cpfCnpj);
        }

        $digits = preg_replace('/\D/', '', (string) ($this->data['cpf_cnpj'] ?? '')) ?? '';

        if (strlen($digits) === 11) {
            $this->data['pessoa_tipo'] = Person::PESSOA_FISICA;
            $this->data['tipo_contribuinte'] = 'nao_contribuinte';
        } elseif (strlen($digits) === 14) {
            $this->data['pessoa_tipo'] = Person::PESSOA_JURIDICA;
        }
    }

    public function updatedDataFormaPagamentoId(mixed $value): void
    {
        $prazoId = $this->data['tabela_prazo_id'] ?? null;

        if ($prazoId === null || $prazoId === '') {
            return;
        }

        if (! $this->tabelaPrazoPertenceAForma($prazoId, $value)) {
            $this->data['tabela_prazo_id'] = null;
        }
    }

    protected function tabelaPrazoPertenceAForma(mixed $prazoId, mixed $formaId): bool
    {
        if ($prazoId === null || $prazoId === '' || $formaId === null || $formaId === '') {
            return false;
        }

        return TabelaPrazo::query()
            ->whereKey((int) $prazoId)
            ->where('forma_pagamento_id', (int) $formaId)
            ->exists();
    }

    protected function syncTipoContribuinteFromIe(): void
    {
        if (($this->data['pessoa_tipo'] ?? null) === Person::PESSOA_FISICA) {
            return;
        }

        $ie = mb_strtoupper(trim((string) ($this->data['rg_ie'] ?? '')), 'UTF-8');

        if ($ie === '' || $ie === '-') {
            $this->data['tipo_contribuinte'] = 'nao_contribuinte';

            return;
        }

        if (in_array($ie, ['ISENTO', 'ISENTA'], true)) {
            $this->data['tipo_contribuinte'] = 'isento';

            return;
        }

        $this->data['tipo_contribuinte'] = 'contribuinte';
    }
}
