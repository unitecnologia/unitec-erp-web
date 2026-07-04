<?php

namespace App\Filament\Resources\PersonResource\Pages\Concerns;

use App\Models\Person;
use App\Models\PersonContact;
use App\Support\Erp\ErpUppercase;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ManagesPersonContacts
{
    /** @var array<int, array<string, mixed>> */
    public array $personContacts = [];

    public ?int $selectedContactIndex = null;

    public string $contactDataRetorno = '';

    public string $contactMotivo = '';

    public string $contactDescricao = '';

    public bool $contactDraftActive = false;

    public ?int $contactEditingIndex = null;

    protected function loadPersonContacts(?Person $person = null): void
    {
        if (! $person) {
            $this->personContacts = [];

            return;
        }

        $this->personContacts = $person->contacts()
            ->get()
            ->map(fn (PersonContact $contact): array => [
                'id' => $contact->id,
                'contato_em' => $contact->contato_em?->format('d/m/Y H:i'),
                'data_retorno' => $contact->data_retorno?->format('Y-m-d'),
                'pessoa' => $contact->pessoa,
                'motivo' => $contact->motivo,
                'descricao' => $contact->descricao,
            ])
            ->values()
            ->all();
    }

    public function selectPersonContact(int $index): void
    {
        if (! isset($this->personContacts[$index])) {
            return;
        }

        $this->selectedContactIndex = $index;
        $contact = $this->personContacts[$index];

        $this->contactDataRetorno = (string) ($contact['data_retorno'] ?? '');
        $this->contactMotivo = (string) ($contact['motivo'] ?? '');
        $this->contactDescricao = (string) ($contact['descricao'] ?? '');
        $this->contactDraftActive = true;
        $this->contactEditingIndex = $index;
    }

    public function startPersonContact(): void
    {
        $this->resetContactDraft();
        $this->contactDraftActive = true;
        $this->contactEditingIndex = null;
        $this->selectedContactIndex = null;
    }

    public function confirmPersonContact(): void
    {
        if (blank($this->contactMotivo) && blank($this->contactDescricao)) {
            Notification::make()
                ->title('Informe o motivo ou a descrição do contato.')
                ->warning()
                ->send();

            return;
        }

        $payload = [
            'contato_em' => now()->format('d/m/Y H:i'),
            'data_retorno' => $this->contactDataRetorno ?: null,
            'pessoa' => Auth::user()?->name ?? 'Sistema',
            'motivo' => ErpUppercase::normalizeFieldValue('motivo', $this->contactMotivo),
            'descricao' => ErpUppercase::normalizeFieldValue('descricao', $this->contactDescricao),
        ];

        if ($this->contactEditingIndex !== null && isset($this->personContacts[$this->contactEditingIndex])) {
            $payload['id'] = $this->personContacts[$this->contactEditingIndex]['id'] ?? null;
            $payload['contato_em'] = $this->personContacts[$this->contactEditingIndex]['contato_em'] ?? $payload['contato_em'];
            $payload['pessoa'] = $this->personContacts[$this->contactEditingIndex]['pessoa'] ?? $payload['pessoa'];
            $this->personContacts[$this->contactEditingIndex] = $payload;
            $this->selectedContactIndex = $this->contactEditingIndex;
        } else {
            $this->personContacts[] = $payload;
            $this->selectedContactIndex = count($this->personContacts) - 1;
        }

        $this->resetContactDraft();

        Notification::make()
            ->title('Contato registrado na lista. Salve com F5 para gravar.')
            ->success()
            ->send();
    }

    public function cancelPersonContact(): void
    {
        $this->resetContactDraft();
    }

    public function deletePersonContact(): void
    {
        if ($this->selectedContactIndex === null || ! isset($this->personContacts[$this->selectedContactIndex])) {
            Notification::make()
                ->title('Selecione um contato na lista.')
                ->warning()
                ->send();

            return;
        }

        unset($this->personContacts[$this->selectedContactIndex]);
        $this->personContacts = array_values($this->personContacts);
        $this->resetContactDraft();
    }

    protected function resetContactDraft(): void
    {
        $this->contactDataRetorno = '';
        $this->contactMotivo = '';
        $this->contactDescricao = '';
        $this->contactDraftActive = false;
        $this->contactEditingIndex = null;
        $this->selectedContactIndex = null;
    }

    protected function syncPersonContacts(Person $person): void
    {
        $ids = collect($this->personContacts)
            ->pluck('id')
            ->filter()
            ->all();

        $person->contacts()->whereNotIn('id', $ids)->delete();

        foreach ($this->personContacts as $contact) {
            $attributes = [
                'contato_em' => isset($contact['contato_em'])
                    ? \Carbon\Carbon::createFromFormat('d/m/Y H:i', $contact['contato_em'])
                    : now(),
                'data_retorno' => filled($contact['data_retorno'] ?? null) ? $contact['data_retorno'] : null,
                'pessoa' => $contact['pessoa'] ?? null,
                'motivo' => ErpUppercase::normalizeFieldValue('motivo', (string) ($contact['motivo'] ?? '')),
                'descricao' => ErpUppercase::normalizeFieldValue('descricao', (string) ($contact['descricao'] ?? '')),
            ];

            if (filled($contact['id'] ?? null)) {
                PersonContact::query()->whereKey($contact['id'])->update($attributes);
            } else {
                $person->contacts()->create($attributes);
            }
        }
    }
}
