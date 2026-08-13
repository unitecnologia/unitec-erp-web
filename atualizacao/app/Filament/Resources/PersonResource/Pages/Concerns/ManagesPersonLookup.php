<?php

namespace App\Filament\Resources\PersonResource\Pages\Concerns;

use App\Models\Person;
use App\Support\Erp\CepLookupService;
use App\Support\Erp\CnpjLookupService;
use App\Support\Erp\MunicipioLookupService;
use Filament\Notifications\Notification;
use RuntimeException;

trait ManagesPersonLookup
{
    /** @var list<array{codigo: string, nome: string, uf: string}> */
    public array $pessoaCidadeSugestoes = [];

    public bool $pessoaCidadeSugestoesOpen = false;

    public int $pessoaCidadeSugestaoIndex = -1;

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

        if (filled($this->data['cep'] ?? null) && ! CepLookupService::isValidIbgeCode($this->data['cidade_codigo'] ?? null)) {
            $this->buscarCepPessoa(silentIncompleteCep: true);
        }

        $this->dispatch('erp-masks-refresh');
    }

    protected function personHasRelevantAddress(): bool
    {
        return filled($this->data['cep'] ?? null)
            || filled($this->data['endereco'] ?? null)
            || filled($this->data['cidade_nome'] ?? null);
    }

    public function buscarCepPessoa(bool $silentIncompleteCep = false): void
    {
        $cep = (string) ($this->data['cep'] ?? '');

        if (strlen(preg_replace('/\D/', '', $cep) ?? '') !== 8) {
            if (! $silentIncompleteCep) {
                Notification::make()
                    ->title('Informe um CEP completo.')
                    ->warning()
                    ->send();
            }

            return;
        }

        try {
            $fields = app(CepLookupService::class)->lookup($cep);
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Consulta de CEP')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $this->data = [
            ...$this->data,
            ...$fields,
        ];

        $this->form->fill($this->data);
        $this->fecharPessoaCidadeSugestoes();

        Notification::make()
            ->title('Endereço preenchido pelo CEP.')
            ->body('Confira o código IBGE da cidade e o complemento, se necessário.')
            ->success()
            ->send();

        $this->dispatch('erp-masks-refresh');
    }

    public function updatedDataCidadeNome(?string $value): void
    {
        $this->buscarMunicipiosPessoa((string) $value);
    }

    public function updatedDataUf(?string $value): void
    {
        $cidade = (string) ($this->data['cidade_nome'] ?? '');

        if (mb_strlen(trim($cidade)) >= 2) {
            $this->buscarMunicipiosPessoa($cidade);

            return;
        }

        $this->fecharPessoaCidadeSugestoes();
    }

    public function buscarMunicipiosPessoa(?string $termo = null): void
    {
        $termo = trim((string) ($termo ?? ($this->data['cidade_nome'] ?? '')));
        $uf = strtoupper(trim((string) ($this->data['uf'] ?? '')));

        if (mb_strlen($termo) < 2) {
            $this->fecharPessoaCidadeSugestoes();

            return;
        }

        try {
            $this->pessoaCidadeSugestoes = app(MunicipioLookupService::class)->search(
                $termo,
                strlen($uf) === 2 ? $uf : null,
                25,
            );
        } catch (RuntimeException $exception) {
            $this->fecharPessoaCidadeSugestoes();
            Notification::make()
                ->title('Consulta de cidades')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $this->pessoaCidadeSugestoesOpen = $this->pessoaCidadeSugestoes !== [];
        $this->pessoaCidadeSugestaoIndex = $this->pessoaCidadeSugestoes !== [] ? 0 : -1;
    }

    public function confirmarPessoaCidadeSugestao(): void
    {
        if ($this->pessoaCidadeSugestoesOpen && $this->pessoaCidadeSugestoes !== []) {
            $index = $this->pessoaCidadeSugestaoIndex;

            if ($index < 0 || ! isset($this->pessoaCidadeSugestoes[$index])) {
                $index = 0;
            }

            $sug = $this->pessoaCidadeSugestoes[$index];
            $this->selecionarPessoaCidade(
                (string) $sug['codigo'],
                (string) $sug['nome'],
                (string) ($sug['uf'] ?? ''),
            );
        } else {
            $termo = trim((string) ($this->data['cidade_nome'] ?? ''));

            if (mb_strlen($termo) >= 2) {
                $this->buscarMunicipiosPessoa($termo);

                if ($this->pessoaCidadeSugestoes !== []) {
                    $sug = $this->pessoaCidadeSugestoes[0];
                    $this->selecionarPessoaCidade(
                        (string) $sug['codigo'],
                        (string) $sug['nome'],
                        (string) ($sug['uf'] ?? ''),
                    );
                }
            }
        }

        // Foco no Email: erp-pessoas-form.js (capture + retries pós-morph).
        $this->dispatch('erp-pessoa-focus-email');
    }

    public function selecionarPessoaCidade(string $codigo, string $nome, ?string $uf = null): void
    {
        $this->data['cidade_codigo'] = preg_replace('/\D/', '', $codigo) ?? '';
        $this->data['cidade_nome'] = mb_strtoupper(trim($nome), 'UTF-8');

        $uf = strtoupper(trim((string) $uf));

        if (strlen($uf) === 2) {
            $this->data['uf'] = $uf;
        }

        $this->form->fill($this->data);
        $this->fecharPessoaCidadeSugestoes();
        $this->dispatch('erp-pessoa-focus-email');
    }

    public function moverPessoaCidadeSugestao(int $delta): void
    {
        if (! $this->pessoaCidadeSugestoesOpen || $this->pessoaCidadeSugestoes === []) {
            return;
        }

        $count = count($this->pessoaCidadeSugestoes);
        $current = $this->pessoaCidadeSugestaoIndex < 0 ? 0 : $this->pessoaCidadeSugestaoIndex;
        $this->pessoaCidadeSugestaoIndex = ($current + $delta + $count) % $count;
    }

    public function fecharPessoaCidadeSugestoes(): void
    {
        $this->pessoaCidadeSugestoes = [];
        $this->pessoaCidadeSugestoesOpen = false;
        $this->pessoaCidadeSugestaoIndex = -1;
    }
}
