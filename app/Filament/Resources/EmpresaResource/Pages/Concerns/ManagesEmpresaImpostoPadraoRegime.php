<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Support\Fiscal\ImpostoPadraoCompatibilidade;
use App\Support\Fiscal\ImpostoPadraoSugestoes;
use App\Support\Fiscal\RegimeCrtMapper;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

trait ManagesEmpresaImpostoPadraoRegime
{
    public bool $impostoPadraoRegimeConfirmOpen = false;

    public string $impostoPadraoRegimePendente = '';

    public string $impostoPadraoRegimeAnterior = '';

    /** @var list<array{campo: string, atual: string, sugerido: string}> */
    public array $impostoPadraoRegimeDiff = [];

    /**
     * Antes de gravar o novo regime no state: guarda o valor atual para possível revert.
     */
    public function updatingDataRegimeTributario(mixed $value): void
    {
        $this->impostoPadraoRegimeAnterior = RegimeCrtMapper::normalize(
            (string) ($this->data['regime_tributario'] ?? 'simples'),
        );
    }

    /**
     * Livewire: alteração do select Regime Tributário.
     * Empresa nova (ainda não gravada): aplica sugestão compatível.
     * Empresa existente: abre revisão explícita — nunca sobrescreve sozinho.
     */
    public function updatedDataRegimeTributario(mixed $value): void
    {
        $novo = RegimeCrtMapper::normalize(is_string($value) ? $value : (string) $value);
        $this->data['regime_tributario'] = $novo;
        $anterior = RegimeCrtMapper::normalize($this->impostoPadraoRegimeAnterior ?: 'simples');

        if ($novo === $anterior) {
            return;
        }

        if ($this->isEmpresaFormCreate()) {
            $this->aplicarSugestaoImpostoPadraoNoForm($novo, notificar: true);

            return;
        }

        $diff = ImpostoPadraoSugestoes::diffComAtuais($this->data ?? [], $novo);

        if ($diff === []) {
            Notification::make()
                ->title('Regime atualizado.')
                ->body('O Imposto Padrão atual já está alinhado à sugestão de '.ImpostoPadraoSugestoes::rotulo($novo).'.')
                ->success()
                ->send();

            return;
        }

        $this->impostoPadraoRegimePendente = $novo;
        $this->impostoPadraoRegimeDiff = $diff;
        $this->impostoPadraoRegimeConfirmOpen = true;
    }

    public function confirmarAplicarSugestaoImpostoPadraoRegime(): void
    {
        $regime = RegimeCrtMapper::normalize($this->impostoPadraoRegimePendente);
        if ($regime === '') {
            $this->fecharRevisaoImpostoPadraoRegime();

            return;
        }

        $this->data['regime_tributario'] = $regime;
        $this->aplicarSugestaoImpostoPadraoNoForm($regime, notificar: false);
        $this->fecharRevisaoImpostoPadraoRegime();

        Notification::make()
            ->title('Sugestão de Imposto Padrão aplicada.')
            ->body(ImpostoPadraoSugestoes::orientacao($regime).' Revise a aba Parâmetros > Imposto Padrão antes de salvar.')
            ->success()
            ->send();
    }

    public function manterImpostoPadraoAoTrocarRegime(): void
    {
        $regime = RegimeCrtMapper::normalize($this->impostoPadraoRegimePendente);
        if ($regime !== '') {
            $this->data['regime_tributario'] = $regime;
        }

        $analise = ImpostoPadraoCompatibilidade::analisarParametrosEmpresa(
            is_array($this->data) ? $this->data : [],
            $regime !== '' ? $regime : null,
        );
        $aviso = ImpostoPadraoCompatibilidade::resumirParaAviso($analise);

        $this->fecharRevisaoImpostoPadraoRegime();

        $notification = Notification::make()
            ->title('Regime alterado; Imposto Padrão mantido.')
            ->body($aviso);

        if ($analise['erros'] !== []) {
            $notification->danger();
        } else {
            $notification->warning();
        }

        $notification->send();
    }

    /** Esc / backdrop: desfaz a troca do select. */
    public function cancelarRevisaoImpostoPadraoRegime(): void
    {
        if ($this->impostoPadraoRegimeConfirmOpen && $this->impostoPadraoRegimeAnterior !== '') {
            $this->data['regime_tributario'] = RegimeCrtMapper::normalize($this->impostoPadraoRegimeAnterior);
        }

        $this->fecharRevisaoImpostoPadraoRegime();
    }

    protected function fecharRevisaoImpostoPadraoRegime(): void
    {
        $this->impostoPadraoRegimeConfirmOpen = false;
        $this->impostoPadraoRegimePendente = '';
        $this->impostoPadraoRegimeAnterior = '';
        $this->impostoPadraoRegimeDiff = [];
    }

    protected function aplicarSugestaoImpostoPadraoNoForm(string $regime, bool $notificar): void
    {
        foreach (ImpostoPadraoSugestoes::paraRegime($regime) as $campo => $valor) {
            $this->data[$campo] = $valor;
        }

        $this->dispatch('erp-masks-refresh');

        if ($notificar) {
            Notification::make()
                ->title('Imposto Padrão sugerido: '.ImpostoPadraoSugestoes::rotulo($regime))
                ->body(ImpostoPadraoSugestoes::orientacao($regime))
                ->success()
                ->send();
        }
    }

    protected function isEmpresaFormCreate(): bool
    {
        return $this instanceof CreateRecord
            || ! isset($this->record)
            || $this->record === null
            || ! $this->record->exists;
    }
}
