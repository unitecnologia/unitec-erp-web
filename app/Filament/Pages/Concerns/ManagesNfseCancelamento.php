<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Nfse;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfse\Ipm\NfseIpmCancelarService;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use Filament\Notifications\Notification;

trait ManagesNfseCancelamento
{
    public bool $nfseCancelarModalOpen = false;

    public ?int $nfseCancelarId = null;

    public string $nfseCancelarMotivo = '1';

    public function cancelarNfse(): void
    {
        $empresaId = ErpContext::currentEmpresaId();
        $nfse = $this->highlightedRecordId
            ? Nfse::query()
                ->with('empresa')
                ->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId))
                ->find($this->highlightedRecordId)
            : null;

        if ($nfse === null) {
            Notification::make()->title('Selecione a NFS-e que deseja cancelar.')->warning()->send();

            return;
        }

        if ($nfse->status === Nfse::STATUS_CANCELADA) {
            Notification::make()->title('Esta NFS-e já está cancelada.')->warning()->send();

            return;
        }

        if ($nfse->status !== Nfse::STATUS_AUTORIZADA || blank($nfse->numero_nfse)) {
            Notification::make()->title('Só é possível cancelar NFS-e autorizada.')->warning()->send();

            return;
        }

        if (! $this->nfseProvedorIpm($nfse->empresa)) {
            $this->mostrarNfseFiscalErro('CANCELAMENTO INDISPONÍVEL', 'O cancelamento automático está disponível só para o provedor IPM. Cancele esta NFS-e no portal nacional.');

            return;
        }

        $this->nfseCancelarId = (int) $nfse->id;
        $this->nfseCancelarMotivo = '1';
        $this->nfseCancelarModalOpen = true;
    }

    public function fecharNfseCancelar(): void
    {
        $this->nfseCancelarModalOpen = false;
        $this->nfseCancelarId = null;
    }

    public function confirmarNfseCancelar(): void
    {
        if (! $this->nfseCancelarModalOpen || $this->nfseCancelarId === null) {
            return;
        }

        $empresaId = ErpContext::currentEmpresaId();
        $nfse = Nfse::query()
            ->with('empresa')
            ->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId))
            ->find($this->nfseCancelarId);

        if ($nfse === null) {
            $this->fecharNfseCancelar();
            Notification::make()->title('NFS-e não encontrada.')->warning()->send();

            return;
        }

        try {
            $resultado = app(NfseIpmCancelarService::class)->cancelar($nfse, $this->nfseCancelarMotivo);
        } catch (NfseNaoTransmitida $exception) {
            $this->fecharNfseCancelar();
            $this->mostrarNfseFiscalErro('NÃO FOI POSSÍVEL CANCELAR A NFS-E', $exception->getMessage());

            return;
        } catch (\Throwable $exception) {
            report($exception);
            $this->fecharNfseCancelar();
            $this->mostrarNfseFiscalErro('NÃO FOI POSSÍVEL CANCELAR A NFS-E', 'Falha inesperada ao enviar o cancelamento ao IPM.');

            return;
        }

        $this->fecharNfseCancelar();

        if (! $resultado['cancelada']) {
            $erros = $resultado['erros'];
            $mensagem = implode("\n", array_map(
                fn (array $erro): string => trim(($erro['codigo'] !== '' ? $erro['codigo'].' — ' : '').$erro['descricao']),
                $erros,
            ));
            $codigo = $erros[0]['codigo'] ?? '';

            $this->mostrarNfseFiscalErro(
                'NÃO FOI POSSÍVEL CANCELAR A NFS-E',
                $mensagem !== '' ? $mensagem : 'O provedor IPM não confirmou o cancelamento.',
                $codigo !== '' ? $codigo : null,
                false,
                'Esta é uma mensagem do provedor IPM.',
                'Código IPM',
            );

            return;
        }

        unset($this->nfseRegistros, $this->nfseListagemTotalFormatado);

        Notification::make()
            ->title('NFS-e nº '.$resultado['nfse']->numero_nfse.' cancelada.')
            ->body('Cancelamento confirmado pela prefeitura.')
            ->success()
            ->send();
    }

    /**
     * @return array{numero: string, tomador: string, total: string, emissao: string}|null
     */
    public function nfseCancelarResumo(): ?array
    {
        if ($this->nfseCancelarId === null) {
            return null;
        }

        $nfse = Nfse::query()->find($this->nfseCancelarId, ['id', 'numero_nfse', 'tomador_nome', 'total', 'data_emissao']);

        if ($nfse === null) {
            return null;
        }

        return [
            'numero' => (string) $nfse->numero_nfse,
            'tomador' => (string) ($nfse->tomador_nome ?: '—'),
            'total' => $this->formatarNfseListagemDinheiro($nfse->total),
            'emissao' => $nfse->data_emissao?->format('d/m/Y') ?? '—',
        ];
    }
}
