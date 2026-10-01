<?php

namespace App\Services\Sicredi;

use App\Models\Boleto;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\Person;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Emite boleto Sicredi a partir de Conta a Receber e persiste em `boletos`.
 */
final class SicrediBoletoEmissionService
{
    public function __construct(
        private readonly SicrediCobrancaClient $client,
        private readonly SicrediCobrancaAuth $auth,
    ) {
    }

    /**
     * @throws RuntimeException
     */
    public function emitirParaContaReceber(ContaReceber $conta, ?Empresa $empresa = null): Boleto
    {
        $conta->loadMissing('cliente');

        $empresa ??= $conta->empresa_id
            ? Empresa::query()->find($conta->empresa_id)
            : ErpContext::currentEmpresa();

        if (! $empresa instanceof Empresa) {
            throw new RuntimeException('Empresa não encontrada para emitir boleto.');
        }

        $this->assertEmpresaPronta($empresa);
        $this->assertContaPronta($conta);

        $existente = Boleto::query()
            ->where('conta_receber_id', $conta->id)
            ->where('status', Boleto::STATUS_ABERTO)
            ->whereNotNull('linha_digitavel')
            ->where('linha_digitavel', '!=', '')
            ->first();

        if ($existente instanceof Boleto) {
            return $existente;
        }

        $payload = $this->buildPayload($empresa, $conta);
        $response = $this->client->gerarBoleto($empresa, $payload);

        return DB::transaction(function () use ($empresa, $conta, $response): Boleto {
            return $this->persistFromResponse($empresa, $conta, $response);
        });
    }

    /**
     * Expõe o payload para testes unitários.
     *
     * @return array<string, mixed>
     */
    public function buildPayloadForTests(Empresa $empresa, ContaReceber $conta): array
    {
        return $this->buildPayload($empresa, $conta);
    }

    /**
     * @throws RuntimeException
     */
    private function assertEmpresaPronta(Empresa $empresa): void
    {
        if (! filter_var($empresa->param_boleto_habilitar ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException(
                'API Boleto desabilitada. Ative em Empresa > Parâmetros > API Boleto.'
            );
        }

        $banco = preg_replace('/\D/', '', (string) ($empresa->param_boleto_banco ?? '')) ?? '';
        if ($banco !== EmpresaParametros::BOLETO_BANCO_SICREDI) {
            throw new RuntimeException(
                'Emissão via API disponível apenas para banco Sicredi (748). Configure o banco na aba API Boleto.'
            );
        }

        // Força validação das credenciais mínimas.
        $this->auth->cooperativa($empresa);
        $this->auth->posto($empresa);
        $this->auth->codigoBeneficiario($empresa);

        if (
            trim((string) ($empresa->param_boleto_dev_app_key ?? '')) === ''
            && trim((string) ($empresa->param_boleto_client_id ?? '')) === ''
        ) {
            throw new RuntimeException('Client ID / x-api-key Sicredi não configurado.');
        }

        if (trim((string) ($empresa->param_boleto_senha_api ?? '')) === '') {
            throw new RuntimeException('Código de acesso Sicredi não configurado.');
        }

        $acao = EmpresaParametros::boletoPosVencimentoAcao($empresa);
        if ($acao !== EmpresaParametros::BOLETO_POS_VENCIMENTO_NENHUMA) {
            $dias = (int) preg_replace('/\D/', '', (string) ($empresa->param_boleto_protesto_dias ?? '')) ?: 0;
            if ($dias < 1 || $dias > 99) {
                throw new RuntimeException(
                    'Informe os dias após vencimento (1–99) para protesto/negativação automática.'
                );
            }
        }
    }

    /**
     * @throws RuntimeException
     */
    private function assertContaPronta(ContaReceber $conta): void
    {
        if ((float) $conta->saldo <= 0) {
            throw new RuntimeException('Conta sem saldo para gerar boleto.');
        }

        if (! $conta->cliente instanceof Person) {
            throw new RuntimeException('Conta sem cliente vinculado.');
        }

        $doc = preg_replace('/\D/', '', (string) ($conta->cliente->cpf_cnpj ?? '')) ?? '';
        if (strlen($doc) < 11) {
            throw new RuntimeException('Cliente sem CPF/CNPJ válido para boleto.');
        }

        $cep = preg_replace('/\D/', '', (string) ($conta->cliente->cep ?? '')) ?? '';
        if (strlen($cep) < 8) {
            throw new RuntimeException('Cliente sem CEP válido para boleto.');
        }

        foreach (['endereco' => 'endereço', 'cidade_nome' => 'cidade', 'uf' => 'UF'] as $field => $label) {
            if (trim((string) ($conta->cliente->{$field} ?? '')) === '') {
                throw new RuntimeException("Cliente sem {$label} para boleto.");
            }
        }

        if (! $conta->vencimento) {
            throw new RuntimeException('Conta sem data de vencimento.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Empresa $empresa, ContaReceber $conta): array
    {
        /** @var Person $cliente */
        $cliente = $conta->cliente;
        $doc = preg_replace('/\D/', '', (string) $cliente->cpf_cnpj) ?? '';
        $hibrido = filter_var($empresa->param_boleto_pix_hibrido ?? false, FILTER_VALIDATE_BOOLEAN);

        $seuNumero = $this->asciiTruncate(
            (string) preg_replace('/\D/', '', (string) ($conta->numero ?: $conta->id)) ?: (string) $conta->id,
            10
        );

        $payload = [
            'codigoBeneficiario' => $this->auth->codigoBeneficiario($empresa),
            'dataVencimento' => $conta->vencimento->toDateString(),
            'especieDocumento' => $this->mapEspecie((string) ($empresa->param_boleto_especie_documento ?? 'DM')),
            'tipoCobranca' => $hibrido ? 'HIBRIDO' : 'NORMAL',
            'seuNumero' => $seuNumero,
            'valor' => round((float) $conta->saldo, 2),
            'pagador' => [
                'tipoPessoa' => $cliente->isPessoaFisica() ? 'PESSOA_FISICA' : 'PESSOA_JURIDICA',
                'documento' => $doc,
                'nome' => $this->asciiTruncate((string) $cliente->nome_razao, 40),
                'endereco' => $this->asciiTruncate((string) $cliente->endereco, 40),
                'cidade' => $this->asciiTruncate((string) $cliente->cidade_nome, 25),
                'uf' => strtoupper(substr((string) $cliente->uf, 0, 2)),
                'cep' => substr(preg_replace('/\D/', '', (string) $cliente->cep) ?? '', 0, 8),
            ],
        ];

        $mensagens = array_values(array_filter([
            $this->asciiTruncate((string) ($empresa->param_boleto_instrucao1 ?? ''), 80) ?: null,
            $this->asciiTruncate((string) ($empresa->param_boleto_instrucao2 ?? ''), 80) ?: null,
        ]));
        if ($mensagens !== []) {
            $payload['mensagens'] = $mensagens;
        }

        $this->aplicarJurosMulta($empresa, $payload, $conta);
        $this->aplicarPosVencimento($empresa, $payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function aplicarJurosMulta(Empresa $empresa, array &$payload, ContaReceber $conta): void
    {
        $multaPct = $this->toFloat($empresa->param_boleto_multa_pct ?? null);
        $jurosPct = $this->toFloat($empresa->param_boleto_juros_pct ?? null);
        $inicio = $conta->vencimento?->copy()->addDay()->toDateString()
            ?? now()->addDay()->toDateString();

        if ($multaPct > 0) {
            $payload['tipoMulta'] = 'PERCENTUAL';
            $payload['multa'] = round($multaPct, 2);
            $payload['dataInicioMulta'] = $inicio;
        }

        if ($jurosPct > 0) {
            $payload['tipoJuros'] = 'PERCENTUAL';
            $payload['tipoJurosPercentual'] = 'MENSAL';
            $payload['juros'] = round($jurosPct, 2);
            $payload['dataInicioJuros'] = $inicio;
        }
    }

    /**
     * XOR: diasProtestoAuto OU diasNegativacaoAuto (nunca ambos).
     *
     * @param  array<string, mixed>  $payload
     */
    private function aplicarPosVencimento(Empresa $empresa, array &$payload): void
    {
        $acao = EmpresaParametros::boletoPosVencimentoAcao($empresa);
        $dias = (int) preg_replace('/\D/', '', (string) ($empresa->param_boleto_protesto_dias ?? '')) ?: 0;

        if ($acao === EmpresaParametros::BOLETO_POS_VENCIMENTO_PROTESTO && $dias > 0) {
            $payload['diasProtestoAuto'] = min(99, $dias);
        } elseif ($acao === EmpresaParametros::BOLETO_POS_VENCIMENTO_NEGATIVACAO && $dias > 0) {
            $payload['diasNegativacaoAuto'] = min(99, $dias);
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function persistFromResponse(Empresa $empresa, ContaReceber $conta, array $response): Boleto
    {
        $linha = (string) (
            $response['linhaDigitavel']
            ?? data_get($response, 'linha_digitavel')
            ?? ''
        );
        $codigoBarras = (string) (
            $response['codigoBarras']
            ?? data_get($response, 'codigo_barras')
            ?? ''
        );
        $nossoNumero = (string) (
            $response['nossoNumero']
            ?? data_get($response, 'nosso_numero')
            ?? ''
        );

        $pixQr = (string) (
            $response['qrCode']
            ?? data_get($response, 'qrCodeBase64')
            ?? ''
        );
        $pixCopiaCola = (string) (
            data_get($response, 'pixCopiaCola')
            ?? data_get($response, 'txid')
            ?? ''
        );

        /** @var Person $cliente */
        $cliente = $conta->cliente;

        $attrs = [
            'empresa_id' => $empresa->id,
            'conta_receber_id' => $conta->id,
            'person_id' => $cliente->id,
            'nosso_numero' => $nossoNumero !== '' ? $nossoNumero : null,
            'numero_documento' => (string) ($conta->numero ?: $conta->id),
            'linha_digitavel' => $linha !== '' ? $linha : null,
            'codigo_barras' => $codigoBarras !== '' ? $codigoBarras : null,
            'emissao' => $conta->emissao?->toDateString() ?? now()->toDateString(),
            'vencimento' => $conta->vencimento?->toDateString(),
            'processamento' => now()->toDateString(),
            'valor' => round((float) $conta->saldo, 2),
            'sacado_nome' => $this->asciiTruncate((string) $cliente->nome_razao, 150),
            'sacado_documento' => preg_replace('/\D/', '', (string) $cliente->cpf_cnpj),
            'sacado_logradouro' => $this->asciiTruncate((string) $cliente->endereco, 250),
            'sacado_numero' => $this->asciiTruncate((string) ($cliente->numero ?? ''), 20),
            'sacado_bairro' => $this->asciiTruncate((string) ($cliente->bairro ?? ''), 50),
            'sacado_cidade' => $this->asciiTruncate((string) $cliente->cidade_nome, 100),
            'sacado_uf' => strtoupper(substr((string) $cliente->uf, 0, 2)),
            'sacado_cep' => preg_replace('/\D/', '', (string) $cliente->cep),
            'instrucao1' => $this->asciiTruncate((string) ($empresa->param_boleto_instrucao1 ?? ''), 250) ?: null,
            'instrucao2' => $this->asciiTruncate((string) ($empresa->param_boleto_instrucao2 ?? ''), 250) ?: null,
            'pix_qr_base64' => $pixQr !== '' ? $pixQr : null,
            'pix_copia_cola' => $pixCopiaCola !== '' ? $pixCopiaCola : null,
            'id_externo' => $nossoNumero !== '' ? $nossoNumero : null,
            'status' => Boleto::STATUS_ABERTO,
        ];

        $aberto = Boleto::query()
            ->where('conta_receber_id', $conta->id)
            ->where('status', Boleto::STATUS_ABERTO)
            ->first();

        if ($aberto instanceof Boleto) {
            $aberto->fill($attrs);
            $aberto->save();

            return $aberto->fresh() ?? $aberto;
        }

        return Boleto::query()->create($attrs);
    }

    private function mapEspecie(string $especie): string
    {
        return match (strtoupper(trim($especie))) {
            'DM' => 'DUPLICATA_MERCANTIL_INDICACAO',
            'DS' => 'DUPLICATA_SERVICO_INDICACAO',
            'NP' => 'NOTA_PROMISSORIA',
            'RC', 'RECI' => 'RECIBO',
            'NF', 'FAT' => 'OUTROS',
            default => 'DUPLICATA_MERCANTIL_INDICACAO',
        };
    }

    private function asciiTruncate(string $value, int $max): string
    {
        $value = Str::ascii(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return mb_substr($value, 0, $max);
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace(['.', ' '], ['', ''], (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
