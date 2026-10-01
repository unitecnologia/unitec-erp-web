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
use Illuminate\Support\Facades\Log;
use Throwable;
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
        $ids = $this->resolveNfceIdsParaTransmitir();

        if ($ids === []) {
            $this->notifyNfceWarning('Selecione uma NFC-e para consultar na SEFAZ.');

            return;
        }

        $service = new PdvNfceConsultaService();
        $ok = 0;
        $erros = 0;
        $primeiraMensagemErro = null;
        $primeiraExcecaoFiscal = null;
        $ultimoStatus = null;
        $ultimoMotivo = null;

        foreach ($ids as $id) {
            $nfce = PdvVendaNfce::query()->find($id);
            $empresa = $this->resolveNfceEmpresa($nfce);

            if (! $nfce || ! $empresa) {
                $erros++;
                $primeiraMensagemErro ??= 'Não foi possível localizar a NFC-e para consulta.';

                continue;
            }

            try {
                $nfce = $service->recuperar($nfce, $empresa);
                $ok++;
                $ultimoStatus = (string) $nfce->status;
                $ultimoMotivo = trim((string) ($nfce->motivo_rejeicao ?? ''));
                $this->nfceSelecionadosTransmitir = array_values(array_filter(
                    $this->nfceSelecionadosTransmitir,
                    fn (string $value): bool => $value !== (string) $id,
                ));
            } catch (Throwable $exception) {
                $erros++;
                $mensagem = trim($exception->getMessage());
                if ($mensagem === '') {
                    $mensagem = $exception::class;
                }

                $primeiraMensagemErro ??= $mensagem;

                if ($exception instanceof FiscalEngineException) {
                    $primeiraExcecaoFiscal ??= $exception;
                }

                Log::warning('NFC-e consulta SEFAZ falhou', [
                    'nfce_id' => $nfce->id,
                    'numero' => $nfce->numero,
                    'chave' => $nfce->chave,
                    'message' => $mensagem,
                ]);
            }
        }

        $this->resetTable();

        if ($ok === 0 && $primeiraExcecaoFiscal instanceof FiscalEngineException) {
            $this->notifyNfceFiscalError($primeiraExcecaoFiscal);

            return;
        }

        if ($ok === 0) {
            $this->notifyNfceWarning($primeiraMensagemErro ?: 'Nenhuma NFC-e foi consultada na SEFAZ.');

            return;
        }

        if ($ok === 1 && $erros === 0) {
            $body = 'Status: '.mb_strtoupper((string) $ultimoStatus, 'UTF-8');
            if (filled($ultimoMotivo)) {
                $body .= ' — '.$ultimoMotivo;
            }

            Notification::make()
                ->title('Consulta SEFAZ concluída.')
                ->body($body)
                ->success()
                ->send();

            return;
        }

        $notification = Notification::make()
            ->title($erros > 0
                ? "{$ok} consultada(s), {$erros} com erro."
                : "{$ok} NFC-e consultadas na SEFAZ.");

        if ($erros > 0) {
            $notification->warning();
            if (filled($primeiraMensagemErro)) {
                $notification->body($primeiraMensagemErro);
            }
        } else {
            $notification->success();
        }

        $notification->send();
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
        $primeiraExcecaoFiscal = null;
        $primeiraMensagemErro = null;

        foreach ($ids as $id) {
            $nfce = PdvVendaNfce::query()->find($id);

            if (! $nfce) {
                $erros++;
                $primeiraMensagemErro ??= 'NFC-e não encontrada.';

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
            } catch (Throwable $exception) {
                $erros++;
                $mensagem = trim($exception->getMessage());
                if ($mensagem === '') {
                    $mensagem = $exception::class;
                }

                $primeiraMensagemErro ??= $mensagem;

                if ($exception instanceof FiscalEngineException) {
                    $primeiraExcecaoFiscal ??= $exception;
                }

                Log::warning('NFC-e transmissão falhou', [
                    'nfce_id' => $nfce->id,
                    'numero' => $nfce->numero,
                    'chave' => $nfce->chave,
                    'message' => $mensagem,
                ]);

                try {
                    $nfce->forceFill([
                        'motivo_rejeicao' => mb_substr($mensagem, 0, 2000, 'UTF-8'),
                    ])->save();
                } catch (Throwable) {
                }
            }
        }

        $this->resetTable();

        if ($erros > 0 && $transmitidas === 0 && $primeiraExcecaoFiscal instanceof FiscalEngineException) {
            $this->notifyNfceFiscalError($primeiraExcecaoFiscal);

            return;
        }

        $this->notifyNfceTransmitirResumo($transmitidas, $erros, $ultimoProtocolo, $primeiraMensagemErro);
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
