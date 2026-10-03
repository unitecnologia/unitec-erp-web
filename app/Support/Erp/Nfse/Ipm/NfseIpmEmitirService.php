<?php

namespace App\Support\Erp\Nfse\Ipm;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use App\Support\Erp\Nfse\NfseSefinAmbiente;
use App\Support\Erp\Nfse\NfseTransmissaoResultado;
use Illuminate\Support\Facades\DB;
use Throwable;

class NfseIpmEmitirService
{
    public function __construct(
        private readonly NfseIpmXmlGerador $xml = new NfseIpmXmlGerador,
        private readonly NfseIpmCliente $cliente = new NfseIpmCliente,
    ) {}

    public function transmitir(Nfse $nfse): NfseTransmissaoResultado
    {
        $nfse->loadMissing(['empresa', 'itens']);
        $empresa = $nfse->empresa;

        if (! $empresa instanceof Empresa) {
            throw new NfseNaoTransmitida('Empresa da NFS-e não encontrada.');
        }

        if (strtolower(trim((string) $empresa->nfse_provedor)) !== 'ipm') {
            throw new NfseNaoTransmitida('O provedor desta NFS-e não é IPM.');
        }

        $this->reservarEnvio($nfse);
        $xml = '';

        try {
            $xml = $this->xml->gerar($nfse);
            $resposta = $this->cliente->enviar($empresa, $xml);
        } catch (NfseNaoTransmitida $exception) {
            $this->liberarEnvio($nfse);

            throw $exception;
        } catch (Throwable) {
            $this->liberarEnvio($nfse);

            throw new NfseNaoTransmitida('Não foi possível transmitir a NFS-e ao IPM.');
        }

        if ($resposta->autorizada) {
            return new NfseTransmissaoResultado(
                $this->registrarAutorizacao($nfse, $empresa, $xml, $resposta),
                true,
                [],
                $resposta->alertas,
            );
        }

        $this->guardarRetorno($nfse, $xml, $resposta->xmlRetorno);
        $this->liberarEnvio($nfse);
        $nfse->refresh();

        $erros = $resposta->erros !== []
            ? $resposta->erros
            : [['codigo' => '', 'descricao' => 'O provedor IPM rejeitou a NFS-e.']];

        return new NfseTransmissaoResultado($nfse, false, $erros, []);
    }

    private function registrarAutorizacao(Nfse $nfse, Empresa $empresa, string $xmlEnvio, NfseIpmResposta $resposta): Nfse
    {
        $ambiente = NfseSefinAmbiente::tryFrom(strtolower(trim((string) $empresa->nfse_ambiente)));

        $nfse->fill([
            'status' => Nfse::STATUS_AUTORIZADA,
            'numero_nfse' => $this->limitar($resposta->numero, 30),
            'chave' => $this->limitar($resposta->codigoVerificacao, 60),
            'protocolo' => $this->limitar($resposta->protocolo, 40),
            'tipo_ambiente' => $ambiente?->tpAmb(),
            'versao_aplicativo' => 'ABRASF-2.04',
            'data_hora_processamento' => $this->limitar($resposta->dataHora, 64),
            'xml_dps' => $xmlEnvio,
            'xml_nfse' => $resposta->xmlRetorno,
            'alertas' => $resposta->alertas === [] ? null : $resposta->alertas,
            'transmitindo_em' => null,
        ]);
        $nfse->save();

        return $nfse->fresh(['itens', 'empresa']) ?? $nfse;
    }

    private function guardarRetorno(Nfse $nfse, string $xmlEnvio, string $xmlRetorno): void
    {
        if ($xmlEnvio === '' && $xmlRetorno === '') {
            return;
        }

        $nfse->fill([
            'xml_dps' => $xmlEnvio !== '' ? $xmlEnvio : $nfse->xml_dps,
            'xml_nfse' => $xmlRetorno !== '' ? $xmlRetorno : $nfse->xml_nfse,
        ]);
        $nfse->save();
    }

    private function reservarEnvio(Nfse $nfse): void
    {
        DB::transaction(function () use ($nfse): void {
            $atual = Nfse::query()->whereKey($nfse->id)->lockForUpdate()->first();

            if ($atual === null || $atual->status !== Nfse::STATUS_ABERTA || filled($atual->chave_acesso) || filled($atual->numero_nfse)) {
                throw new NfseNaoTransmitida('Esta NFS-e já foi autorizada e não pode ser transmitida novamente.');
            }

            if ($atual->transmitindo_em !== null && $atual->transmitindo_em->gt(now()->subSeconds(90))) {
                throw new NfseNaoTransmitida('Esta NFS-e já está sendo transmitida.');
            }

            $atual->transmitindo_em = now();
            $atual->save();
        });
    }

    private function liberarEnvio(Nfse $nfse): void
    {
        Nfse::query()->whereKey($nfse->id)->where('status', Nfse::STATUS_ABERTA)->update([
            'transmitindo_em' => null,
        ]);
    }

    private function limitar(?string $valor, int $tamanho): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        return mb_substr($texto, 0, $tamanho);
    }
}
