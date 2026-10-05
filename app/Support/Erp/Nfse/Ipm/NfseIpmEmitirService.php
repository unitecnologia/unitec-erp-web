<?php

namespace App\Support\Erp\Nfse\Ipm;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use App\Support\Erp\Nfse\NfseSefinAmbiente;
use App\Support\Erp\Nfse\NfseTransmissaoResultado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class NfseIpmEmitirService
{
    public function __construct(
        private readonly NfseIpmXmlGerador $xml = new NfseIpmXmlGerador,
        private readonly NfseIpmCliente $cliente = new NfseIpmCliente,
        private readonly NfseIpmAssinador $assinador = new NfseIpmAssinador,
        private readonly NfseIpmXmlValidador $validador = new NfseIpmXmlValidador,
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

        $teste = $this->cliente->modoTeste($empresa);
        $this->reservarEnvio($nfse);

        try {
            $xml = $this->xmlAssinado($nfse, $teste);
            $resposta = $this->cliente->enviar($empresa, $xml, $teste);
        } catch (NfseNaoTransmitida $exception) {
            $this->liberarEnvio($nfse);

            throw $exception;
        } catch (Throwable) {
            $this->liberarEnvio($nfse);

            throw new NfseNaoTransmitida('Não foi possível transmitir a NFS-e ao IPM.');
        }

        if ($teste || $resposta->modoTeste) {
            return $this->resultadoTeste($nfse, $xml, $resposta);
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

        return new NfseTransmissaoResultado($nfse, false, $erros, $resposta->alertas);
    }

    /**
     * Gera, assina e valida no XSD oficial. Nada é transmitido se o XSD recusar.
     */
    public function xmlAssinado(Nfse $nfse, bool $envioTeste): string
    {
        $xml = $this->assinador->assinarNota($nfse, $this->xml->gerar($nfse, $envioTeste));
        $erros = $this->validador->erros($xml);

        if ($erros !== []) {
            throw new NfseNaoTransmitida("O RPS não passou na validação do XSD oficial IPM:\n".implode("\n", array_slice($erros, 0, 5)));
        }

        return $xml;
    }

    /**
     * EnvioTeste=1: a nota continua aberta, sem número/código de NFS-e e sem XML de retorno gravado nela.
     * O RPS permanece reservado para a mesma nota e será reenviado na emissão real.
     */
    private function resultadoTeste(Nfse $nfse, string $xmlEnvio, NfseIpmResposta $resposta): NfseTransmissaoResultado
    {
        $this->liberarEnvio($nfse);
        $this->arquivarTeste($nfse, $xmlEnvio, $resposta->xmlRetorno);
        $nfse->refresh();

        return new NfseTransmissaoResultado(
            $nfse,
            false,
            $resposta->aceitaEmTeste ? [] : $resposta->erros,
            $resposta->alertas,
            true,
        );
    }

    private function arquivarTeste(Nfse $nfse, string $xmlEnvio, string $xmlRetorno): void
    {
        try {
            $base = 'nfse/ipm/teste/'.$nfse->empresa_id.'/'.$nfse->id.'-'.now()->format('Ymd-His');
            Storage::disk('local')->put($base.'-envio.xml', $xmlEnvio);
            Storage::disk('local')->put($base.'-retorno.xml', $xmlRetorno);
        } catch (Throwable $exception) {
            report($exception);
        }
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
