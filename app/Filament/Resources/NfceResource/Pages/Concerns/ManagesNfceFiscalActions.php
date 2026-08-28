<?php

namespace App\Filament\Resources\NfceResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use App\Support\Erp\Pdv\PdvNfceCupomPrinter;
use App\Support\Erp\Pdv\PdvNfceFiscalMensagens;
use App\Support\Erp\Vendas\EstornarVendaService;
use App\Support\Fiscal\PdvNfceConsultaService;
use App\Support\Fiscal\PdvNfceInutilizacaoService;
use App\Support\Fiscal\PdvNfceTransmissaoService;
use DomainException;
use Filament\Notifications\Notification;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

trait ManagesNfceFiscalActions
{
    use ManagesNfceBulkTransmit;
    public ?string $nfceFiscalModal = null;

    public string $nfceCancelJustificativa = '';

    public string $nfceInutilizarSerie = '1';

    public string $nfceInutilizarNumeroIni = '';

    public string $nfceInutilizarNumeroFim = '';

    public string $nfceInutilizarJustificativa = '';

    public function cancelarNfce(): void
    {
        if (method_exists($this, 'erpAuthorizeOrNotify') && ! $this->erpAuthorizeOrNotify('nfce.cancel')) {
            return;
        }

        $id = $this->highlightedRecordIdOrNotify('cancelar');
        if (! $id) {
            return;
        }

        $nfce = PdvVendaNfce::query()->with('pdvVenda')->find($id);

        if (! $nfce) {
            $this->notifyNfceWarning('NFC-e não encontrada.');

            return;
        }

        if ($nfce->status === PdvVendaNfce::STATUS_CANCELADA) {
            $this->notifyNfceWarning('Esta NFC-e já está cancelada.');

            return;
        }

        if ($nfce->status !== PdvVendaNfce::STATUS_AUTORIZADA && ! $nfce->simulada) {
            $this->notifyNfceWarning('Somente NFC-e autorizada pode ser cancelada.');

            return;
        }

        if (! $nfce->pdvVenda) {
            $this->notifyNfceWarning('NFC-e sem venda vinculada.');

            return;
        }

        $this->nfceCancelJustificativa = PdvEstornoMotivo::MOTIVO_AUTOMATICO;
        $this->nfceFiscalModal = 'cancelar';
    }

    public function confirmCancelarNfce(): void
    {
        if (method_exists($this, 'erpAuthorizeOrNotify') && ! $this->erpAuthorizeOrNotify('nfce.cancel')) {
            return;
        }

        $id = $this->highlightedRecordId;
        $nfce = $id ? PdvVendaNfce::query()->with('pdvVenda')->find($id) : null;
        $venda = $nfce?->pdvVenda;
        $empresa = $this->resolveNfceEmpresa($nfce);
        $motivo = PdvEstornoMotivo::normalize($this->nfceCancelJustificativa);
        $erroMotivo = PdvEstornoMotivo::validate($motivo);

        if (! $nfce || ! $venda || ! $empresa) {
            $this->closeNfceFiscalModal();
            $this->notifyNfceWarning('Não foi possível localizar os dados para cancelamento.');

            return;
        }

        if ($erroMotivo !== null) {
            $this->notifyNfceWarning($erroMotivo);

            return;
        }

        try {
            $result = (new EstornarVendaService())->fromPdvVenda(
                $venda,
                $motivo,
                EstornarVendaService::ORIGEM_NFCE_LISTA,
                $empresa,
            );
        } catch (DomainException $exception) {
            $this->notifyNfceWarning($exception->getMessage());

            return;
        } catch (FiscalEngineException $exception) {
            $this->notifyNfceFiscalError($exception);

            return;
        }

        $this->closeNfceFiscalModal();
        $this->resetTable();

        $protocolo = $result->protocoloCancelamento;
        $body = filled($protocolo)
            ? 'Protocolo: '.$protocolo.' — venda estornada (estoque, financeiro e logística).'
            : 'NFC-e cancelada e venda estornada.';

        Notification::make()
            ->title('NFC-e cancelada com sucesso.')
            ->body($body)
            ->success()
            ->send();

        if (filled($protocolo) && $venda->id) {
            $this->js(PdvNfceCupomPrinter::livewireOpenProtocoloCancelamentoJs((int) $venda->id));
        }
    }

    public function recuperarNfce(): void
    {
        $id = $this->highlightedRecordIdOrNotify('recuperar');
        if (! $id) {
            return;
        }

        $nfce = PdvVendaNfce::query()->find($id);
        $empresa = $this->resolveNfceEmpresa($nfce);

        if (! $nfce || ! $empresa) {
            $this->notifyNfceWarning('Não foi possível localizar a NFC-e para consulta.');

            return;
        }

        try {
            $nfce = (new PdvNfceConsultaService())->recuperar($nfce, $empresa);
        } catch (FiscalEngineException $exception) {
            $this->notifyNfceFiscalError($exception);

            return;
        }

        $this->resetTable();

        $motivo = trim((string) ($nfce->motivo_rejeicao ?? ''));
        $body = 'Status: '.mb_strtoupper((string) $nfce->status, 'UTF-8');
        if ($motivo !== '') {
            $body .= ' — '.$motivo;
        }

        Notification::make()
            ->title('Consulta SEFAZ concluída.')
            ->body($body)
            ->success()
            ->send();
    }

    public function transmitirNfce(): void
    {
        $ids = $this->resolveNfceIdsParaTransmitir();

        if ($ids === []) {
            $this->notifyNfceWarning('Selecione uma ou mais NFC-e em contingência para transmitir.');

            return;
        }

        $empresa = $this->resolveNfceEmpresa();

        if (! $empresa) {
            $this->notifyNfceWarning('Empresa não configurada para transmissão fiscal.');

            return;
        }

        $service = new PdvNfceTransmissaoService();
        $transmitidas = 0;
        $erros = 0;
        $ultimoProtocolo = null;
        $primeiraExcecao = null;

        foreach ($ids as $id) {
            $nfce = PdvVendaNfce::query()->find($id);

            if (! $nfce) {
                $erros++;

                continue;
            }

            try {
                $nfce = $service->transmitir($nfce, $empresa);
                $transmitidas++;
                $ultimoProtocolo = $nfce->protocolo;
                $this->nfceSelecionadosTransmitir = array_values(array_filter(
                    $this->nfceSelecionadosTransmitir,
                    fn (string $value): bool => $value !== (string) $id,
                ));
            } catch (FiscalEngineException $exception) {
                $erros++;
                $primeiraExcecao ??= $exception;
            }
        }

        $this->resetTable();

        if ($transmitidas === 0 && $primeiraExcecao instanceof FiscalEngineException) {
            $this->notifyNfceFiscalError($primeiraExcecao);

            return;
        }

        $this->notifyNfceTransmitirResumo($transmitidas, $erros, $ultimoProtocolo);
    }

    public function inutilizarNfce(): void
    {
        $this->nfceInutilizarSerie = '1';
        $this->nfceInutilizarNumeroIni = '';
        $this->nfceInutilizarNumeroFim = '';
        $this->nfceInutilizarJustificativa = '';
        $this->nfceFiscalModal = 'inutilizar';
    }

    public function confirmInutilizarNfce(): void
    {
        $empresa = $this->resolveNfceEmpresa();

        if (! $empresa) {
            $this->closeNfceFiscalModal();
            $this->notifyNfceWarning('Empresa não configurada para inutilização fiscal.');

            return;
        }

        $serie = (int) ltrim($this->nfceInutilizarSerie, '0') ?: 1;
        $numeroIni = (int) $this->nfceInutilizarNumeroIni;
        $numeroFim = (int) ($this->nfceInutilizarNumeroFim !== '' ? $this->nfceInutilizarNumeroFim : $this->nfceInutilizarNumeroIni);

        try {
            $response = (new PdvNfceInutilizacaoService())->inutilizar(
                $empresa,
                $serie,
                $numeroIni,
                $numeroFim,
                $this->nfceInutilizarJustificativa,
            );
        } catch (FiscalEngineException $exception) {
            $this->notifyNfceFiscalError($exception);

            return;
        }

        $this->closeNfceFiscalModal();

        Notification::make()
            ->title('Numeração inutilizada com sucesso.')
            ->body(sprintf(
                'Série %d — notas %d a %d. Protocolo: %s',
                $response->serie,
                $response->numeroInicial,
                $response->numeroFinal,
                $response->protocolo ?: '—',
            ))
            ->success()
            ->send();
    }

    public function closeNfceFiscalModal(): void
    {
        $this->nfceFiscalModal = null;
        $this->nfceCancelJustificativa = '';
        $this->nfceInutilizarJustificativa = '';
    }

    protected function resolveNfceEmpresa(?PdvVendaNfce $nfce = null): ?Empresa
    {
        if ($nfce?->empresa_id) {
            $empresa = Empresa::query()->find($nfce->empresa_id);

            if ($empresa) {
                return $empresa;
            }
        }

        $empresaId = $this->empresaIdAtiva();

        return $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : null;
    }

    protected function notifyNfceWarning(string $message): void
    {
        Notification::make()
            ->title($message)
            ->warning()
            ->send();
    }

    protected function notifyNfceFiscalError(FiscalEngineException $exception): void
    {
        $resolvido = PdvNfceFiscalMensagens::resolver($exception);

        $notification = Notification::make()
            ->title($resolvido['titulo'])
            ->danger();

        if ($resolvido['corpo'] !== null) {
            $notification->body($resolvido['corpo']);
        }

        $notification->send();
    }
}
