<?php

namespace App\Filament\Resources\TefLiquidacaoDestinoResource\Pages\Concerns;

use App\Filament\Resources\TefLiquidacaoDestinoResource;
use App\Models\CaixaConta;
use App\Models\TefLiquidacaoDestino;
use App\Models\Terminal;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Tef\TefProviderId;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

trait ManagesTefLiquidacaoDestinoFormModal
{
    public bool $destinoModalOpen = false;

    public ?int $destinoModalRecordId = null;

    /** @var array<string, mixed> */
    public array $destinoForm = [];

    public function createDestino(): void
    {
        if ($this->destinoModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('tef_liquidacao_destino.create')) {
            Notification::make()->title('Sem permissão para incluir.')->warning()->send();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            Notification::make()->title('Selecione uma empresa antes de configurar.')->warning()->send();

            return;
        }

        $this->destinoModalRecordId = null;
        $this->destinoForm = $this->defaultDestinoForm();
        $this->destinoModalOpen = true;
    }

    public function editDestino(): void
    {
        if ($this->destinoModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('tef_liquidacao_destino.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = TefLiquidacaoDestino::query()
            ->where('empresa_id', (int) (ErpContext::currentEmpresaId() ?? 0))
            ->find($this->highlightedRecordId);

        if (! $record) {
            Notification::make()->title('Configuração não encontrada.')->warning()->send();

            return;
        }

        $this->destinoModalRecordId = (int) $record->getKey();
        $this->destinoForm = $this->destinoFormFromRecord($record);
        $this->destinoModalOpen = true;
    }

    public function closeDestinoModal(): void
    {
        $this->destinoModalOpen = false;
        $this->destinoModalRecordId = null;
        $this->destinoForm = [];
        $this->resetValidation();
    }

    public function saveDestino(): void
    {
        $isEdit = $this->destinoModalRecordId !== null;
        $permission = $isEdit ? 'tef_liquidacao_destino.update' : 'tef_liquidacao_destino.create';

        if (! ErpAccess::currentCan($permission)) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            Notification::make()->title('Empresa não selecionada.')->warning()->send();

            return;
        }

        $this->destinoForm['acquirer_cnpj'] = TefLiquidacaoDestino::normalizeAcquirerCnpj(
            $this->destinoForm['acquirer_cnpj'] ?? ''
        );
        $autorizados = array_keys(TefLiquidacaoDestino::provedoresAutorizadosParaUi());
        $provedor = TefLiquidacaoDestino::normalizeProvedor($this->destinoForm['provedor'] ?? TefProviderId::SUPERTEF);
        if (! in_array($provedor, $autorizados, true)) {
            $provedor = TefProviderId::SUPERTEF;
        }
        $this->destinoForm['provedor'] = $provedor;

        $ignoreId = $this->destinoModalRecordId;

        $this->validate([
            'destinoForm.terminal_id' => [
                'required',
                'integer',
                Rule::exists('terminais', 'id')->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'destinoForm.provedor' => ['required', 'string', Rule::in($autorizados)],
            'destinoForm.acquirer_cnpj' => [
                'nullable',
                'string',
                'max:14',
                Rule::unique('tef_liquidacao_destinos', 'acquirer_cnpj')
                    ->where(fn ($q) => $q
                        ->where('empresa_id', $empresaId)
                        ->where('terminal_id', (int) ($this->destinoForm['terminal_id'] ?? 0))
                        ->where('provedor', $provedor))
                    ->ignore($ignoreId),
            ],
            'destinoForm.caixa_conta_id' => [
                'required',
                'integer',
                Rule::exists('caixa_contas', 'id'),
            ],
            'destinoForm.ativo' => ['boolean'],
        ], [
            'destinoForm.acquirer_cnpj.unique' => 'Já existe destino para este terminal, provedor e adquirente.',
        ], [
            'destinoForm.terminal_id' => 'terminal',
            'destinoForm.provedor' => 'provedor',
            'destinoForm.acquirer_cnpj' => 'CNPJ adquirente',
            'destinoForm.caixa_conta_id' => 'conta BANCO',
        ]);

        $terminalId = (int) $this->destinoForm['terminal_id'];
        $contaId = (int) $this->destinoForm['caixa_conta_id'];
        $cnpj = TefLiquidacaoDestino::normalizeAcquirerCnpj($this->destinoForm['acquirer_cnpj'] ?? '');

        $terminal = Terminal::query()
            ->whereKey($terminalId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (! $terminal) {
            Notification::make()->title('Terminal inválido para a empresa atual.')->warning()->send();

            return;
        }

        $contaOk = CaixaConta::query()
            ->whereKey($contaId)
            ->where('ativo', true)
            ->where('tipo', CaixaConta::TIPO_BANCO)
            ->visiveisNaEmpresa($empresaId)
            ->exists();

        if (! $contaOk) {
            Notification::make()
                ->title('Conta BANCO inativa ou de outra empresa.')
                ->warning()
                ->send();

            return;
        }

        $payload = [
            'empresa_id' => $empresaId,
            'terminal_id' => $terminalId,
            'provedor' => $provedor,
            'acquirer_cnpj' => $cnpj,
            'caixa_conta_id' => $contaId,
            'ativo' => (bool) ($this->destinoForm['ativo'] ?? true),
            'updated_by_user_id' => Auth::id(),
        ];

        if ($isEdit) {
            $record = TefLiquidacaoDestino::query()
                ->where('empresa_id', $empresaId)
                ->find($this->destinoModalRecordId);

            if (! $record) {
                Notification::make()->title('Configuração não encontrada.')->warning()->send();

                return;
            }

            $record->fill($payload)->save();
            $title = 'Destino alterado.';
        } else {
            $record = TefLiquidacaoDestino::query()->create($payload);
            $title = 'Destino incluído.';
        }

        $id = (int) $record->getKey();
        $this->closeDestinoModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($id);

        Notification::make()->title($title)->success()->send();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function destinoTerminalOptions(): array
    {
        $atualId = filled($this->destinoForm['terminal_id'] ?? null)
            ? (int) $this->destinoForm['terminal_id']
            : null;

        $rows = TefLiquidacaoDestinoResource::terminaisQuery()->get(['id', 'nome', 'numero_logico_terminal']);

        if ($atualId && ! $rows->contains(fn (Terminal $t): bool => (int) $t->id === $atualId)) {
            $extra = Terminal::query()->find($atualId, ['id', 'nome', 'numero_logico_terminal']);
            if ($extra) {
                $rows->push($extra);
            }
        }

        return $rows
            ->map(fn (Terminal $t): array => [
                'id' => (int) $t->id,
                'label' => trim(($t->numero_logico_terminal ? $t->numero_logico_terminal.' — ' : '').$t->nome),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function destinoContaOptions(): array
    {
        $atualId = filled($this->destinoForm['caixa_conta_id'] ?? null)
            ? (int) $this->destinoForm['caixa_conta_id']
            : null;

        $rows = TefLiquidacaoDestinoResource::contasBancoQuery()->get(['id', 'codigo', 'nome']);

        if ($atualId && ! $rows->contains(fn (CaixaConta $c): bool => (int) $c->id === $atualId)) {
            $extra = CaixaConta::query()->find($atualId, ['id', 'codigo', 'nome']);
            if ($extra) {
                $rows->push($extra);
            }
        }

        return $rows
            ->map(fn (CaixaConta $c): array => [
                'id' => (int) $c->id,
                'label' => trim(($c->codigo !== null ? $c->codigo.' — ' : '').$c->nome),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, string>
     */
    public function destinoProvedorOptions(): array
    {
        return TefLiquidacaoDestino::provedoresAutorizadosParaUi();
    }

    protected function defaultDestinoForm(): array
    {
        $opts = array_keys(TefLiquidacaoDestino::provedoresAutorizadosParaUi());

        return [
            'terminal_id' => '',
            'provedor' => $opts[0] ?? TefProviderId::SUPERTEF,
            'acquirer_cnpj' => '',
            'caixa_conta_id' => '',
            'ativo' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function destinoFormFromRecord(TefLiquidacaoDestino $record): array
    {
        return [
            'terminal_id' => (string) $record->terminal_id,
            'provedor' => TefLiquidacaoDestino::normalizeProvedor($record->provedor ?? TefProviderId::SUPERTEF),
            'acquirer_cnpj' => (string) ($record->acquirer_cnpj ?? ''),
            'caixa_conta_id' => (string) $record->caixa_conta_id,
            'ativo' => (bool) $record->ativo,
        ];
    }
}
