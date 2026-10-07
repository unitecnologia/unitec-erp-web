<?php

namespace App\Support\Erp\Nfse\Ipm;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class NfseIpmCancelarService
{
    public function __construct(
        private readonly NfseIpmXmlGerador $xml = new NfseIpmXmlGerador,
        private readonly NfseIpmCliente $cliente = new NfseIpmCliente,
        private readonly NfseIpmAssinador $assinador = new NfseIpmAssinador,
        private readonly NfseIpmXmlValidador $validador = new NfseIpmXmlValidador,
    ) {}

    /**
     * @return array{nfse: Nfse, cancelada: bool, erros: list<array{codigo: string, descricao: string}>}
     */
    public function cancelar(Nfse $nfse, string $codigo): array
    {
        $nfse->loadMissing('empresa');
        $empresa = $nfse->empresa;

        if (! $empresa instanceof Empresa) {
            throw new NfseNaoTransmitida('Empresa da NFS-e não encontrada.');
        }

        if (strtolower(trim((string) $empresa->nfse_provedor)) !== 'ipm') {
            throw new NfseNaoTransmitida('O cancelamento automático está disponível só para o provedor IPM.');
        }

        $this->reservar($nfse);

        try {
            $xml = $this->xmlAssinado($nfse, $codigo);
            $resposta = $this->cliente->cancelar($empresa, $xml);
        } catch (NfseNaoTransmitida $exception) {
            $this->liberar($nfse);

            throw $exception;
        } catch (Throwable) {
            $this->liberar($nfse);

            throw new NfseNaoTransmitida('Não foi possível enviar o cancelamento ao IPM.');
        }

        if (! $resposta->cancelada) {
            $this->liberar($nfse);

            return ['nfse' => $nfse->refresh(), 'cancelada' => false, 'erros' => $resposta->erros];
        }

        $nfse->fill([
            'status' => Nfse::STATUS_CANCELADA,
            'cancelamento_codigo' => $codigo,
            'cancelada_em' => now(),
            'cancelada_por' => mb_substr(trim((string) (Auth::user()?->name ?? '')), 0, 120) ?: null,
            'xml_cancelamento' => $resposta->xmlRetorno,
            'transmitindo_em' => null,
        ]);
        $nfse->save();

        return ['nfse' => $nfse->fresh() ?? $nfse, 'cancelada' => true, 'erros' => []];
    }

    public function xmlAssinado(Nfse $nfse, string $codigo): string
    {
        $xml = $this->assinador->assinarCancelamento($nfse, $this->xml->gerarCancelamento($nfse, $codigo));
        $erros = $this->validador->erros($xml);

        if ($erros !== []) {
            throw new NfseNaoTransmitida("O pedido de cancelamento não passou na validação do XSD oficial IPM:\n".implode("\n", array_slice($erros, 0, 5)));
        }

        return $xml;
    }

    private function reservar(Nfse $nfse): void
    {
        DB::transaction(function () use ($nfse): void {
            $atual = Nfse::query()->whereKey($nfse->id)->lockForUpdate()->first();

            if ($atual === null || $atual->status !== Nfse::STATUS_AUTORIZADA || blank($atual->numero_nfse)) {
                throw new NfseNaoTransmitida('Só é possível cancelar NFS-e autorizada.');
            }

            if ($atual->transmitindo_em !== null && $atual->transmitindo_em->gt(now()->subSeconds(90))) {
                throw new NfseNaoTransmitida('Esta NFS-e já está sendo enviada ao IPM. Aguarde.');
            }

            $atual->transmitindo_em = now();
            $atual->save();
        });
    }

    private function liberar(Nfse $nfse): void
    {
        Nfse::query()->whereKey($nfse->id)->update(['transmitindo_em' => null]);
    }
}
