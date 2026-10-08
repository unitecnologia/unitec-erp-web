<?php

namespace App\Filament\Resources\NfceResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\Erp\Nfce\NfceEmpresaEscopo;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use App\Support\Erp\Pdv\PdvNfceCupomPrinter;
use App\Support\Erp\Pdv\PdvNfceFiscalMensagens;
use App\Support\Erp\Vendas\EstornarVendaService;
use App\Support\Fiscal\NfceNumeracao;
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

    public string $nfceInutilizarOrigem = '';

    public function cancelarNfce(): void
    {
        if (! $this->nfcePodeExecutar('nfce.cancel')) {
            return;
        }

        $id = $this->highlightedRecordIdOrNotify('cancelar');
        if (! $id) {
            return;
        }

        $nfce = $this->findNfceNoEscopo($id, ['pdvVenda']);

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
        if (! $this->nfcePodeExecutar('nfce.cancel')) {
            return;
        }

        $id = $this->highlightedRecordId;
        $nfce = $this->findNfceNoEscopo($id ? (int) $id : null, ['pdvVenda']);
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
        $body = match (true) {
            $result->somenteFiscal => (filled($protocolo) ? 'Protocolo: '.$protocolo.' — ' : '')
                .'NFC-e de regularização cancelada. A venda original não foi alterada e volta para a Regularização (F9).',
            filled($protocolo) => 'Protocolo: '.$protocolo.' — venda estornada (estoque, financeiro e logística).',
            default => 'NFC-e cancelada e venda estornada.',
        };

        $this->sinalizarSefazConcluido();

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
        if (! $this->nfcePodeExecutar()) {
            return;
        }

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
        $primeiraEtapaErro = null;
        $ultimoStatus = null;
        $ultimoMotivo = null;

        foreach ($ids as $id) {
            $nfce = $this->findNfceNoEscopo($id);
            $empresa = $this->resolveNfceEmpresa($nfce);

            if (! $nfce || ! $empresa) {
                $erros++;
                $primeiraMensagemErro ??= 'Não foi possível localizar a NFC-e para consulta.';
                $primeiraEtapaErro ??= 'validacao';

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
                $primeiraEtapaErro ??= $this->etapaDaFalhaFiscal($exception);

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
            $this->notifyNfceWarning($primeiraMensagemErro ?: 'Nenhuma NFC-e foi consultada na SEFAZ.', $primeiraEtapaErro ?? 'sefaz');

            return;
        }

        if ($erros === 0) {
            $this->sinalizarSefazConcluido();
        } else {
            $this->sinalizarSefazFalha(
                "{$ok} consultada(s), {$erros} com erro.".(filled($primeiraMensagemErro) ? ' '.$primeiraMensagemErro : ''),
                $primeiraEtapaErro ?? 'sefaz',
            );
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
        if (! $this->nfcePodeExecutar()) {
            return;
        }

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
        $primeiraEtapaErro = null;

        foreach ($ids as $id) {
            $nfce = $this->findNfceNoEscopo($id);

            if (! $nfce) {
                $erros++;
                $primeiraMensagemErro ??= 'NFC-e não encontrada.';
                $primeiraEtapaErro ??= 'validacao';

                continue;
            }

            $atualizadaEm = $nfce->updated_at;

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
                $primeiraEtapaErro ??= $this->etapaDaFalhaFiscal($exception);

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
                    $nfce->refresh();

                    // O serviço já gravou status/motivo fiscal (ex.: chave citada na 539): não sobrescrever.
                    if ($nfce->updated_at == $atualizadaEm) {
                        $nfce->forceFill([
                            'motivo_rejeicao' => mb_substr($mensagem, 0, 2000, 'UTF-8'),
                        ])->save();
                    }
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

        if ($transmitidas > 0 && $erros === 0) {
            $this->sinalizarSefazConcluido();
        } else {
            $this->sinalizarSefazFalha(
                ($transmitidas > 0 ? "{$transmitidas} transmitida(s), {$erros} com erro. " : '')
                    .($primeiraMensagemErro ?: 'Nenhuma NFC-e foi transmitida.'),
                $primeiraEtapaErro ?? 'sefaz',
            );
        }
    }

    public function inutilizarNfce(): void
    {
        if (! $this->nfcePodeExecutar('nfce.cancel')) {
            return;
        }

        $this->nfceInutilizarSerie = '1';
        $this->nfceInutilizarNumeroIni = '';
        $this->nfceInutilizarNumeroFim = '';
        $this->nfceInutilizarJustificativa = '';
        $this->nfceInutilizarOrigem = '';

        $marcadas = NfceEmpresaEscopo::filtrarIds($this->nfceSelecionadosTransmitir, $this->empresaIdAtiva());

        if (count($marcadas) > 1) {
            $this->nfceInutilizarOrigem = count($marcadas).' NFC-e marcadas — informe a faixa manualmente.';
            $this->nfceFiscalModal = 'inutilizar';

            return;
        }

        $id = $marcadas[0] ?? ($this->highlightedRecordId ? (int) $this->highlightedRecordId : null);
        $nfce = $id ? $this->findNfceNoEscopo((int) $id) : null;

        if ($nfce) {
            $motivo = $this->motivoNfceNaoInutilizavel($nfce);

            if ($motivo !== null) {
                $this->notifyNfceWarning($motivo);

                return;
            }

            $numero = (string) (int) $nfce->numero;
            $this->nfceInutilizarSerie = (string) NfceNumeracao::serieInt($nfce->serie);
            $this->nfceInutilizarNumeroIni = $numero;
            $this->nfceInutilizarNumeroFim = $numero;
            $this->nfceInutilizarOrigem = 'Preenchido com a NFC-e nº '.$numero.' selecionada.';
        }

        $this->nfceFiscalModal = 'inutilizar';
    }

    /** Motivo local para não inutilizar o número desta NFC-e (null = pode seguir para a SEFAZ). */
    protected function motivoNfceNaoInutilizavel(PdvVendaNfce $nfce): ?string
    {
        $rotulo = 'NFC-e nº '.((int) $nfce->numero ?: '—');

        if ($nfce->simulada) {
            return $rotulo.' '.NfceNumeracao::motivoStatusNaoInutilizavel(PdvVendaNfce::STATUS_SIMULADA).'.';
        }

        $motivo = NfceNumeracao::motivoStatusNaoInutilizavel((string) $nfce->status);

        if ($motivo !== null) {
            return $rotulo.' '.$motivo.'.';
        }

        if ((int) $nfce->numero < 1) {
            return $rotulo.' não possui número fiscal para inutilizar.';
        }

        $empresa = $this->resolveNfceEmpresa($nfce);

        if ($empresa && filled($nfce->ambiente)) {
            $ambienteAtual = NfceNumeracao::ambiente(VendasParametro::forEmpresa((int) $empresa->id));

            if ((int) $nfce->ambiente !== $ambienteAtual) {
                $nome = fn (int $a): string => $a === 1 ? 'produção' : 'homologação';

                return $rotulo.' foi emitida em '.$nome((int) $nfce->ambiente).' e o ambiente fiscal atual é '.$nome($ambienteAtual).'.';
            }
        }

        return null;
    }

    public function confirmInutilizarNfce(): void
    {
        if (! $this->nfcePodeExecutar('nfce.cancel')) {
            return;
        }

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
            NfceNumeracao::verificarFaixaInutilizavel(
                (int) $empresa->id,
                $serie,
                VendasParametro::forEmpresa((int) $empresa->id),
                $numeroIni,
                $numeroFim,
            );
        } catch (FiscalEngineException $exception) {
            $this->notifyNfceWarning($exception->getMessage());

            return;
        }

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
        $this->sinalizarSefazConcluido();

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
        $this->nfceInutilizarOrigem = '';
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

    /** Indicador SEFAZ (erp-sefaz-progress.js): sem este sinal a resposta é tratada como falha. */
    protected function sinalizarSefazConcluido(): void
    {
        $this->dispatch('erp-sefaz-resultado', ok: true);
    }

    /**
     * Falha para o indicador SEFAZ: mensagem real e etapa onde parou (chaves de nfce/fiscal-progress).
     *
     * @param  'validacao'|'assinatura'|'sefaz'|'pos'  $etapa
     */
    protected function sinalizarSefazFalha(string $mensagem, string $etapa = 'sefaz'): void
    {
        $this->dispatch(
            'erp-sefaz-resultado',
            ok: false,
            etapa: $etapa,
            mensagem: mb_substr(trim($mensagem), 0, 600, 'UTF-8'),
        );
    }

    /** Em que etapa a exceção fiscal parou (retorno da SEFAZ, certificado/assinatura ou checagem local). */
    protected function etapaDaFalhaFiscal(Throwable $exception): string
    {
        if (! $exception instanceof FiscalEngineException) {
            return 'sefaz';
        }

        $mensagem = mb_strtolower($exception->getMessage(), 'UTF-8');

        if (filled($exception->sefazCodigo ?? null) || preg_match('/\[cstat\s+\d+\]/', $mensagem) === 1) {
            return 'sefaz';
        }

        if (preg_match('/certificad|assinat|\.pfx|senha do certificado/u', $mensagem) === 1) {
            return 'assinatura';
        }

        if (preg_match('/bloquead|inválid|invalid|informe |selecione|faixa|já pertence|justificativa|não encontrad|máximo/u', $mensagem) === 1) {
            return 'validacao';
        }

        return 'sefaz';
    }

    protected function notifyNfceWarning(string $message, string $etapa = 'validacao'): void
    {
        $this->sinalizarSefazFalha($message, $etapa);

        Notification::make()
            ->title($message)
            ->warning()
            ->send();
    }

    protected function notifyNfceFiscalError(FiscalEngineException $exception): void
    {
        $resolvido = PdvNfceFiscalMensagens::resolver($exception);

        $this->sinalizarSefazFalha(
            trim($resolvido['titulo'].($resolvido['corpo'] !== null ? ' — '.$resolvido['corpo'] : '')),
            $this->etapaDaFalhaFiscal($exception),
        );

        $notification = Notification::make()
            ->title($resolvido['titulo'])
            ->danger();

        if ($resolvido['corpo'] !== null) {
            $notification->body($resolvido['corpo']);
        }

        $notification->send();
    }
}
