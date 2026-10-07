<?php

namespace App\Support\Erp\Nfse;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\VendasParametro;
use App\Support\Erp\CepLookupService;
use App\Support\Fiscal\NfceFiscalCertificateResolver;
use Illuminate\Support\Facades\DB;
use Throwable;

class NfseTransmitirService
{
    public function __construct(
        private readonly NfseSefinEnvio $envio,
        private readonly NfseDpsBuilder $builder = new NfseDpsBuilder,
        private readonly NfseDpsXmlGerador $gerador = new NfseDpsXmlGerador,
        private readonly NfseDpsXmlValidador $validador = new NfseDpsXmlValidador,
        private readonly NfseDpsAssinador $assinador = new NfseDpsAssinador,
    ) {}

    public function transmitir(Nfse $nfse): NfseTransmissaoResultado
    {
        $nfse->loadMissing(['empresa', 'itens']);
        $this->garantirPronta($nfse);
        $this->reservarEnvio($nfse);

        try {
            $xml = $this->gerador->gerar($this->builder->montar($nfse));
            $this->exigirXmlValido($xml);
            $certificado = $this->assinador->certificadoDaNota($nfse);
            $assinado = $this->assinador->assinarNota($nfse, $xml);
            $this->assinador->validar($assinado, $certificado);
            $this->exigirXmlValido($assinado);

            $empresa = $nfse->empresa;

            if (! $empresa instanceof Empresa) {
                throw new NfseNaoTransmitida('Empresa da NFS-e não encontrada.');
            }

            $ambiente = NfseSefinAmbiente::daEmpresa($empresa->nfse_ambiente);

            if (! str_contains($assinado, '<tpAmb>'.$ambiente->tpAmb().'</tpAmb>')) {
                throw new NfseNaoTransmitida('A DPS não está no ambiente configurado da NFS-e.');
            }

            $client = NfseSefinClient::daEmpresa($empresa);
            $requisicao = $client->preparar($assinado, $certificado);
            $payload = json_decode($requisicao->corpo, true, 512, JSON_THROW_ON_ERROR);
            $restaurado = gzdecode(base64_decode((string) ($payload['dpsXmlGZipB64'] ?? ''), true));

            if ($requisicao->url !== $ambiente->url() || ! NfseSefinEndpoints::urlOficial($requisicao->url) || $restaurado !== $assinado) {
                throw new NfseNaoTransmitida('A DPS pronta para envio não confere com o XML assinado.');
            }

            $resposta = $this->envio->enviar($requisicao);
        } catch (NfseNaoTransmitida $exception) {
            $this->liberarEnvio($nfse);

            throw $exception;
        } catch (NfseDpsNaoAssinada|NfseDpsNaoMontada $exception) {
            $this->liberarEnvio($nfse);

            throw new NfseNaoTransmitida($exception->getMessage());
        } catch (Throwable) {
            $this->liberarEnvio($nfse);

            throw new NfseNaoTransmitida('Não foi possível transmitir a DPS.');
        }

        if ($resposta->autorizada()) {
            return new NfseTransmissaoResultado(
                $this->registrarAutorizacao($nfse, $resposta, $assinado),
                true,
                [],
                $resposta->alertas,
            );
        }

        $this->liberarEnvio($nfse);
        $nfse->refresh();

        return new NfseTransmissaoResultado($nfse, false, $resposta->erros, []);
    }

    public function certificadoValido(Empresa $empresa): bool
    {
        try {
            $parametros = VendasParametro::query()->where('empresa_id', $empresa->id)->first();

            if (! $parametros instanceof VendasParametro) {
                return false;
            }

            $certificado = NfceFiscalCertificateResolver::resolve($empresa, $parametros);
            $dados = openssl_x509_parse($certificado->certificatePem);

            if (! is_array($dados)) {
                return false;
            }

            $inicio = (int) ($dados['validFrom_time_t'] ?? 0);
            $fim = (int) ($dados['validTo_time_t'] ?? 0);

            return $inicio > 0 && $fim > time() && $inicio <= time();
        } catch (Throwable) {
            return false;
        }
    }

    private function garantirPronta(Nfse $nfse): void
    {
        if ($nfse->status === Nfse::STATUS_AUTORIZADA || filled($nfse->chave_acesso)) {
            throw new NfseNaoTransmitida('Esta DPS já foi autorizada e não pode ser transmitida novamente.');
        }

        if ($nfse->status !== Nfse::STATUS_ABERTA) {
            throw new NfseNaoTransmitida('Só é possível transmitir NFS-e aberta.');
        }

        $empresa = $nfse->empresa;

        if (! $empresa instanceof Empresa) {
            throw new NfseNaoTransmitida('Empresa da NFS-e não encontrada.');
        }

        if (! NfseSefinAmbiente::tryFrom(strtolower(trim((string) $empresa->nfse_ambiente))) instanceof NfseSefinAmbiente) {
            throw new NfseNaoTransmitida('Selecione o ambiente da NFS-e.');
        }

        if ((int) $nfse->tomador_id < 1 || trim((string) $nfse->tomador_nome) === '') {
            throw new NfseNaoTransmitida('Selecione o tomador.');
        }

        $documento = preg_replace('/\D/', '', (string) $nfse->tomador_cpf_cnpj) ?? '';

        if (strlen($documento) !== 11 && strlen($documento) !== 14) {
            throw new NfseNaoTransmitida('Informe o CPF ou CNPJ do tomador.');
        }

        if (! CepLookupService::isValidIbgeCode((string) $nfse->municipio_prestacao_codigo)) {
            throw new NfseNaoTransmitida('Falta o código IBGE do município da prestação.');
        }

        if ($nfse->competencia === null || $nfse->data_emissao === null) {
            throw new NfseNaoTransmitida('Informe a competência e a data de emissão.');
        }

        if (! array_key_exists((string) $nfse->trib_issqn, Nfse::tributacoesIssqn())
            || ! array_key_exists((string) $nfse->tp_ret_issqn, Nfse::retencoesIssqn())) {
            throw new NfseNaoTransmitida('Informe a tributação e a retenção do ISSQN.');
        }

        $itens = $nfse->itens;

        if ($itens->isEmpty()) {
            throw new NfseNaoTransmitida('Informe ao menos um serviço.');
        }

        foreach ($itens as $item) {
            $nacional = preg_replace('/\D/', '', (string) $item->c_trib_nac) ?? '';
            $nbs = preg_replace('/\D/', '', (string) $item->c_nbs) ?? '';

            if (trim((string) $item->descricao) === '' || strlen($nacional) !== 6 || strlen($nbs) !== 9) {
                throw new NfseNaoTransmitida('Faltam dados fiscais do serviço.');
            }

            if (bccomp((string) $item->quantidade, '0', 3) !== 1) {
                throw new NfseNaoTransmitida('Informe a quantidade do serviço.');
            }
        }

        $codigos = NfseDpsXmlGerador::codigosDistintos($itens->map(fn ($item): array => [
            'cTribNac' => $item->c_trib_nac,
            'cNBS' => $item->c_nbs,
            'cTribMun' => $item->c_trib_mun,
            'cIndOp' => $item->c_ind_op,
        ])->all());

        if ($codigos > 1) {
            throw new NfseNaoTransmitida('No padrão Nacional cada NFS-e aceita um único código de serviço. Emita uma nota para cada código de tributação/NBS.');
        }

        if (! $this->certificadoValido($empresa)) {
            throw new NfseNaoTransmitida('Certificado da empresa inválido ou vencido.');
        }
    }

    private function reservarEnvio(Nfse $nfse): void
    {
        DB::transaction(function () use ($nfse): void {
            $atual = Nfse::query()->whereKey($nfse->id)->lockForUpdate()->first();

            if ($atual === null || $atual->status !== Nfse::STATUS_ABERTA || filled($atual->chave_acesso)) {
                throw new NfseNaoTransmitida('Esta DPS já foi autorizada e não pode ser transmitida novamente.');
            }

            if ($atual->transmitindo_em !== null && $atual->transmitindo_em->gt(now()->subSeconds(90))) {
                throw new NfseNaoTransmitida('Esta DPS já está sendo transmitida.');
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

    private function registrarAutorizacao(Nfse $nfse, NfseSefinResposta $resposta, string $xmlDps): Nfse
    {
        if ($resposta->xmlNfse === null || trim($resposta->xmlNfse) === '') {
            $this->liberarEnvio($nfse);

            throw new NfseNaoTransmitida('A NFS-e autorizada não retornou o XML.');
        }

        $nfse->fill([
            'status' => Nfse::STATUS_AUTORIZADA,
            'id_dps' => $resposta->idDps,
            'chave_acesso' => $resposta->chaveAcesso,
            'chave' => $resposta->chaveAcesso,
            'tipo_ambiente' => $resposta->tipoAmbiente,
            'versao_aplicativo' => $resposta->versaoAplicativo,
            'data_hora_processamento' => $resposta->dataHoraProcessamento,
            'xml_nfse' => $resposta->xmlNfse,
            'xml_dps' => $xmlDps,
            'alertas' => $resposta->alertas === [] ? null : $resposta->alertas,
            'transmitindo_em' => null,
        ]);
        $nfse->save();

        return $nfse->fresh(['itens']) ?? $nfse;
    }

    private function exigirXmlValido(string $xml): void
    {
        $resultado = $this->validador->validar($xml);

        if ($resultado['valido']) {
            return;
        }

        $erro = $resultado['erros'][0] ?? null;
        $regra = is_array($erro) ? trim((string) ($erro['regra'] ?? '')) : '';

        throw new NfseNaoTransmitida($regra !== '' ? 'DPS inválida: '.$regra : 'DPS inválida para o XSD.');
    }
}
