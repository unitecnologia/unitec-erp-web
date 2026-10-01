<?php

namespace App\Filament\Resources\PersonResource\Pages\Concerns;

use App\Models\ClienteCreditoMovimentacao;
use App\Models\Person;
use App\Support\Erp\ClienteCreditoService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

trait ManagesPersonCreditos
{
    public string $creditoModal = '';

    public string $creditoValor = '';

    public string $creditoObservacao = '';

    public ?int $creditoEstornoId = null;

    public ?int $creditoDetalheId = null;

    public function abrirGerarCredito(): void
    {
        if (! $this->personCreditoPronta()) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'pessoas.credito_gerar')) {
            return;
        }

        $this->creditoModal = 'gerar';
        $this->creditoValor = '';
        $this->creditoObservacao = '';
        $this->creditoEstornoId = null;
    }

    public function abrirUsarCredito(): void
    {
        if (! $this->personCreditoPronta()) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'pessoas.credito_gerar')) {
            return;
        }

        $this->creditoModal = 'usar';
        $this->creditoValor = '';
        $this->creditoObservacao = '';
        $this->creditoEstornoId = null;
    }

    public function abrirEstornarCredito(int $movimentoId): void
    {
        if (! $this->personCreditoPronta()) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'pessoas.credito_estornar')) {
            return;
        }

        $movimento = $this->movimentoCreditoDoCliente($movimentoId);

        if (! $movimento) {
            Notification::make()->title('Lançamento não encontrado.')->warning()->send();

            return;
        }

        if ($movimento->estorna_id) {
            Notification::make()->title('Não é possível estornar um estorno.')->warning()->send();

            return;
        }

        if ($this->movimentoCreditoJaEstornado($movimentoId)) {
            Notification::make()->title('Este lançamento já foi estornado.')->warning()->send();

            return;
        }

        $this->creditoModal = 'estornar';
        $this->creditoEstornoId = $movimentoId;
        $this->creditoValor = ErpMoney::formatBr((float) $movimento->valor);
        $this->creditoObservacao = '';
    }

    public function confirmarCreditoCliente(): void
    {
        $personId = $this->personCreditoId();
        $empresaId = ErpContext::currentEmpresaId();

        if (! $personId || ! $empresaId) {
            Notification::make()->title('Salve a pessoa e selecione a empresa.')->warning()->send();

            return;
        }

        $servico = new ClienteCreditoService();

        try {
            if ($this->creditoModal === 'gerar') {
                if (! ErpAccess::authorizeOrNotify(Auth::user(), 'pessoas.credito_gerar')) {
                    return;
                }

                $servico->gerar(
                    clienteId: $personId,
                    valor: ErpMoney::parseBr($this->creditoValor),
                    empresaId: $empresaId,
                    origemTipo: ClienteCreditoMovimentacao::ORIGEM_MANUAL,
                    observacao: $this->creditoObservacao,
                );
                $titulo = 'Crédito gerado.';
            } elseif ($this->creditoModal === 'usar') {
                if (! ErpAccess::authorizeOrNotify(Auth::user(), 'pessoas.credito_gerar')) {
                    return;
                }

                $servico->usar(
                    clienteId: $personId,
                    valor: ErpMoney::parseBr($this->creditoValor),
                    empresaId: $empresaId,
                    origemTipo: ClienteCreditoMovimentacao::ORIGEM_MANUAL,
                    observacao: $this->creditoObservacao !== '' ? $this->creditoObservacao : 'Uso manual',
                );
                $titulo = 'Crédito utilizado.';
            } elseif ($this->creditoModal === 'estornar' && $this->creditoEstornoId) {
                if (! ErpAccess::authorizeOrNotify(Auth::user(), 'pessoas.credito_estornar')) {
                    return;
                }

                $movimento = $this->movimentoCreditoDoCliente($this->creditoEstornoId);

                if (! $movimento) {
                    throw new DomainException('Lançamento não encontrado.');
                }

                $servico->estornar(
                    $movimento->id,
                    Auth::id() ? (int) Auth::id() : null,
                    $this->creditoObservacao !== '' ? $this->creditoObservacao : null,
                );
                $titulo = 'Lançamento estornado.';
            } else {
                return;
            }
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->warning()->send();

            return;
        }

        $this->fecharCreditoModal();

        Notification::make()->title($titulo)->success()->send();
    }

    public function fecharCreditoModal(): void
    {
        $this->creditoModal = '';
        $this->creditoValor = '';
        $this->creditoObservacao = '';
        $this->creditoEstornoId = null;
    }

    public function toggleCreditoDetalhe(int $movimentoId): void
    {
        $this->creditoDetalheId = $this->creditoDetalheId === $movimentoId ? null : $movimentoId;
    }

    public function getCreditoClienteSaldoProperty(): ?string
    {
        $personId = $this->personCreditoId();
        $empresaId = ErpContext::currentEmpresaId();

        if (! $personId || ! $empresaId) {
            return null;
        }

        return ErpMoney::formatBr((new ClienteCreditoService())->saldo($personId, $empresaId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCreditoClienteHistoricoProperty(): array
    {
        $personId = $this->personCreditoId();
        $empresaId = ErpContext::currentEmpresaId();

        if (! $personId || ! $empresaId) {
            return [];
        }

        $movimentos = ClienteCreditoMovimentacao::query()
            ->with(['usuario:id,name', 'empresa:id,razao_social'])
            ->where('cliente_id', $personId)
            ->where('empresa_id', $empresaId)
            ->orderByDesc('id')
            ->limit(80)
            ->get();

        $estornados = ClienteCreditoMovimentacao::query()
            ->whereIn('estorna_id', $movimentos->pluck('id'))
            ->pluck('estorna_id')
            ->all();

        return $movimentos->map(function (ClienteCreditoMovimentacao $movimento) use ($estornados): array {
            $efeito = $movimento->efeito();
            $positivo = $efeito >= 0;

            return [
                'id' => (int) $movimento->id,
                'data' => $movimento->data_movimentacao?->timezone('America/Sao_Paulo')->format('d/m/Y') ?? '—',
                'hora' => $movimento->data_movimentacao?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') ?? '—',
                'tipo' => ClienteCreditoMovimentacao::tipoLabel((string) $movimento->tipo, (bool) $movimento->estorna_id),
                'tipo_chave' => (string) $movimento->tipo,
                'codigo_publico' => ClienteCreditoMovimentacao::codigoBarras($movimento->codigo_publico),
                'origem' => ClienteCreditoMovimentacao::origemLabel(
                    $movimento->origem_tipo,
                    $movimento->origem_numero,
                    $movimento->observacao,
                ),
                'valor' => ($positivo ? '+ ' : '− ').'R$ '.ErpMoney::formatBr(abs($efeito)),
                'positivo' => $positivo,
                'saldo' => 'R$ '.ErpMoney::formatBr((float) $movimento->saldo_atual),
                'empresa' => (string) ($movimento->empresa?->razao_social ?: '—'),
                'usuario' => (string) ($movimento->usuario?->name ?: '—'),
                'observacao' => trim((string) ($movimento->observacao ?? '')),
                'pode_estornar' => ! $movimento->estorna_id
                    && ! in_array($movimento->id, $estornados, true),
            ];
        })->all();
    }

    public function personCreditoId(): ?int
    {
        if (! $this instanceof EditRecord) {
            return null;
        }

        $record = $this->getRecord();

        return $record instanceof Person && $record->exists ? (int) $record->id : null;
    }

    protected function personCreditoPronta(): bool
    {
        if (! $this->personCreditoId()) {
            Notification::make()->title('Salve a pessoa para lançar créditos.')->warning()->send();

            return false;
        }

        $record = $this->getRecord();

        if ($record instanceof Person && ! (new ClienteCreditoService())->podeReceberCredito((int) $record->id)) {
            Notification::make()
                ->title('Só cliente cadastrado recebe crédito.')
                ->body('Consumidor final não entra neste saldo.')
                ->warning()
                ->send();

            return false;
        }

        if (! ErpContext::currentEmpresaId()) {
            Notification::make()->title('Selecione a empresa atual.')->warning()->send();

            return false;
        }

        return true;
    }

    protected function movimentoCreditoDoCliente(int $movimentoId): ?ClienteCreditoMovimentacao
    {
        $personId = $this->personCreditoId();
        $empresaId = ErpContext::currentEmpresaId();

        if (! $personId || ! $empresaId) {
            return null;
        }

        return ClienteCreditoMovimentacao::query()
            ->whereKey($movimentoId)
            ->where('cliente_id', $personId)
            ->where('empresa_id', $empresaId)
            ->first();
    }

    protected function movimentoCreditoJaEstornado(int $movimentoId): bool
    {
        return ClienteCreditoMovimentacao::query()->where('estorna_id', $movimentoId)->exists();
    }
}
