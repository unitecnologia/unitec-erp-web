<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfse\Ipm\NfseIpmCancelarService;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use App\Support\Erp\Nfse\NfseSefinCancelarService;
use App\Support\Erp\Nfse\NfseSefinEventoXmlGerador;
use App\Support\Erp\Nfse\NfseSefinXmlLeitor;
use Filament\Notifications\Notification;

trait ManagesNfseCancelamento
{
    public bool $nfseCancelarModalOpen = false;

    public ?int $nfseCancelarId = null;

    public string $nfseCancelarMotivo = '1';

    public string $nfseCancelarJustificativa = '';

    public string $nfseCancelarProvedor = '';

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

        if ($nfse->status !== Nfse::STATUS_AUTORIZADA) {
            Notification::make()->title('Só é possível cancelar NFS-e autorizada.')->warning()->send();

            return;
        }

        $empresa = $nfse->empresa;
        $ipm = $this->nfseProvedorIpm($empresa);
        $nacional = $this->nfseProvedorNacional($empresa);

        if (! $ipm && ! $nacional) {
            $this->mostrarNfseFiscalErro(
                'CANCELAMENTO INDISPONÍVEL',
                'O cancelamento automático está disponível para os provedores IPM e Nacional.',
            );

            return;
        }

        if ($ipm && blank($nfse->numero_nfse)) {
            Notification::make()->title('Só é possível cancelar NFS-e autorizada.')->warning()->send();

            return;
        }

        if ($nacional) {
            $chave = preg_replace('/\D/', '', (string) ($nfse->chave_acesso ?: $nfse->chave)) ?? '';

            if (strlen($chave) !== 50) {
                Notification::make()->title('Só é possível cancelar NFS-e Nacional com chave de acesso.')->warning()->send();

                return;
            }

            if (blank($nfse->numero_nfse)) {
                $numero = NfseSefinXmlLeitor::numeroNfse($nfse->xml_nfse);
                if ($numero !== null) {
                    $nfse->forceFill(['numero_nfse' => $numero])->save();
                }
            }
        }

        $this->nfseCancelarId = (int) $nfse->id;
        $this->nfseCancelarProvedor = $ipm ? 'ipm' : 'nacional';
        $this->nfseCancelarMotivo = '1';
        $this->nfseCancelarJustificativa = '';
        $this->nfseCancelarModalOpen = true;
    }

    public function updatedNfseCancelarMotivo(mixed $value): void
    {
        if ((string) $value !== '9') {
            $this->nfseCancelarJustificativa = '';
        }
    }

    public function fecharNfseCancelar(): void
    {
        $this->nfseCancelarModalOpen = false;
        $this->nfseCancelarId = null;
        $this->nfseCancelarProvedor = '';
        $this->nfseCancelarJustificativa = '';
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

        $provedor = $this->nfseCancelarProvedor !== ''
            ? $this->nfseCancelarProvedor
            : ($this->nfseProvedorIpm($nfse->empresa) ? 'ipm' : 'nacional');
        $rotuloProvedor = $provedor === 'ipm' ? 'IPM' : 'SEFIN Nacional';

        if ($provedor === 'nacional' && trim($this->nfseCancelarMotivo) === '9') {
            try {
                NfseSefinEventoXmlGerador::normalizarXMotivo($this->nfseCancelarJustificativa, exigir: true);
            } catch (NfseNaoTransmitida $exception) {
                Notification::make()->title($exception->getMessage())->warning()->send();

                return;
            }
        }

        try {
            $resultado = $provedor === 'ipm'
                ? app(NfseIpmCancelarService::class)->cancelar($nfse, $this->nfseCancelarMotivo)
                : app(NfseSefinCancelarService::class)->cancelar(
                    $nfse,
                    $this->nfseCancelarMotivo,
                    $this->nfseCancelarJustificativa !== '' ? $this->nfseCancelarJustificativa : null,
                );
        } catch (NfseNaoTransmitida $exception) {
            $this->fecharNfseCancelar();
            $this->mostrarNfseFiscalErro('NÃO FOI POSSÍVEL CANCELAR A NFS-E', $exception->getMessage());

            return;
        } catch (\Throwable $exception) {
            report($exception);
            $this->fecharNfseCancelar();
            $this->mostrarNfseFiscalErro(
                'NÃO FOI POSSÍVEL CANCELAR A NFS-E',
                'Falha inesperada ao enviar o cancelamento ao '.$rotuloProvedor.'.',
            );

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
                $mensagem !== '' ? $mensagem : 'O provedor '.$rotuloProvedor.' não confirmou o cancelamento.',
                $codigo !== '' ? $codigo : null,
                false,
                'Esta é uma mensagem do provedor '.$rotuloProvedor.'.',
                'Código '.$rotuloProvedor,
            );

            return;
        }

        unset($this->nfseRegistros, $this->nfseListagemTotaisFormatados);

        $numero = trim((string) ($resultado['nfse']->numero_nfse ?? ''));
        if ($numero === '') {
            $numero = (string) ($resultado['nfse']->numero_dps ?? '');
        }

        Notification::make()
            ->title('NFS-e nº '.$numero.' cancelada.')
            ->body($provedor === 'ipm'
                ? 'Cancelamento confirmado pela prefeitura.'
                : 'Cancelamento confirmado pela SEFIN Nacional.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    public function nfseCancelarMotivos(): array
    {
        if ($this->nfseCancelarProvedor === 'nacional') {
            return NfseSefinEventoXmlGerador::motivos();
        }

        return Nfse::motivosCancelamento();
    }

    /**
     * @return array{numero: string, tomador: string, total: string, emissao: string}|null
     */
    public function nfseCancelarResumo(): ?array
    {
        if ($this->nfseCancelarId === null) {
            return null;
        }

        $nfse = Nfse::query()->find($this->nfseCancelarId, [
            'id',
            'numero_nfse',
            'numero_dps',
            'tomador_nome',
            'total',
            'data_emissao',
            'xml_nfse',
        ]);

        if ($nfse === null) {
            return null;
        }

        $numero = trim((string) ($nfse->numero_nfse ?? ''));
        if ($numero === '') {
            $numero = (string) (NfseSefinXmlLeitor::numeroNfse($nfse->xml_nfse) ?? $nfse->numero_dps ?? '—');
        }

        return [
            'numero' => $numero,
            'tomador' => (string) ($nfse->tomador_nome ?: '—'),
            'total' => $this->formatarNfseListagemDinheiro($nfse->total),
            'emissao' => $nfse->data_emissao?->format('d/m/Y') ?? '—',
        ];
    }

    protected function nfseProvedorNacional(?Empresa $empresa): bool
    {
        return strtolower(trim((string) ($empresa?->nfse_provedor ?? ''))) === 'nacional';
    }
}
