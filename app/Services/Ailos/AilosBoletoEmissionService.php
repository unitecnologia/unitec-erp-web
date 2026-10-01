<?php

namespace App\Services\Ailos;

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
 * Emite boleto Ailos V2 a partir de Conta a Receber e persiste em `boletos`.
 */
final class AilosBoletoEmissionService
{
    public function __construct(private readonly AilosCobrancaClient $client)
    {
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
        $response = $this->client->gerarBoletoUnico($empresa, $payload);

        return DB::transaction(function () use ($empresa, $conta, $response): Boleto {
            return $this->persistFromResponse($empresa, $conta, $response);
        });
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
        if ($banco !== EmpresaParametros::BOLETO_BANCO_AILOS) {
            throw new RuntimeException(
                'Emissão via API disponível apenas para banco Ailos (085). Configure o banco na aba API Boleto.'
            );
        }

        if (trim((string) ($empresa->param_boleto_carteira ?? '')) === '') {
            throw new RuntimeException('Código da Carteira Ailos não configurado.');
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

        foreach (['endereco' => 'endereço', 'bairro' => 'bairro', 'cidade_nome' => 'cidade', 'uf' => 'UF'] as $field => $label) {
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
        $telefone = $this->splitPhone((string) ($cliente->celular1 ?: $cliente->fone1 ?: ''));
        $bolePix = filter_var($empresa->param_boleto_pix_hibrido ?? false, FILTER_VALIDATE_BOOLEAN);

        $numeroDocumento = (int) preg_replace('/\D/', '', (string) ($conta->numero ?: $conta->id));
        if ($numeroDocumento <= 0) {
            $numeroDocumento = (int) $conta->id;
        }
        // Ailos: até 9 dígitos
        $numeroDocumento = (int) substr((string) $numeroDocumento, -9);

        $payload = [
            'convenioCobranca' => [
                'codigoCarteiraCobranca' => (int) preg_replace('/\D/', '', (string) $empresa->param_boleto_carteira),
            ],
            'documento' => [
                'numeroDocumento' => $numeroDocumento,
                'descricaoDocumento' => $this->asciiTruncate((string) ($conta->documento ?: $conta->numero ?: $conta->id), 15),
                'especieDocumento' => $this->mapEspecie((string) ($empresa->param_boleto_especie_documento ?? 'DM')),
            ],
            'emissao' => [
                // 2 = Cooperado emite e expede (PDF local / dados na API)
                'formaEmissao' => 2,
                'dataEmissaoDocumento' => ($conta->emissao?->toDateString() ?? now()->toDateString()).'T00:00:00.000Z',
            ],
            'pagador' => [
                'entidadeLegal' => [
                    'identificadorReceitaFederal' => $doc,
                    'tipoPessoa' => $cliente->isPessoaFisica() ? 1 : 2,
                    'nome' => $this->asciiTruncate((string) $cliente->nome_razao, 50),
                ],
                'telefone' => $telefone,
                'emails' => array_values(array_filter([
                    filled($cliente->email) ? ['endereco' => (string) $cliente->email] : null,
                ])),
                'endereco' => [
                    'cep' => substr(preg_replace('/\D/', '', (string) $cliente->cep) ?? '', 0, 8),
                    'logradouro' => $this->asciiTruncate((string) $cliente->endereco, 56),
                    'numero' => $this->asciiTruncate((string) ($cliente->numero ?: 'S/N'), 10),
                    'complemento' => $this->asciiTruncate((string) ($cliente->complemento ?? ''), 40),
                    'bairro' => $this->asciiTruncate((string) $cliente->bairro, 30),
                    'cidade' => $this->asciiTruncate((string) $cliente->cidade_nome, 30),
                    'uf' => strtoupper(substr((string) $cliente->uf, 0, 2)),
                ],
                'mensagemPagador' => array_values(array_filter([
                    $this->asciiTruncate((string) ($empresa->param_boleto_instrucao1 ?? ''), 40) ?: null,
                    $this->asciiTruncate((string) ($empresa->param_boleto_instrucao2 ?? ''), 40) ?: null,
                ])),
            ],
            'vencimento' => [
                'dataVencimento' => $conta->vencimento->toDateString().'T00:00:00.000Z',
            ],
            'instrucoes' => $this->buildInstrucoes($empresa),
            'valorBoleto' => [
                'valorNominal' => round((float) $conta->saldo, 2),
            ],
            'indicadorRegistroNuclea' => 1,
            'bolePix' => $bolePix,
        ];

        return $payload;
    }

    /**
     * Expõe instruções para testes unitários.
     *
     * @return array<string, mixed>
     */
    public function buildInstrucoesForTests(Empresa $empresa): array
    {
        return $this->buildInstrucoes($empresa);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInstrucoes(Empresa $empresa): array
    {
        // Manual Ailos V2: tipo 1=R$, 2=Percentual, 3=Isento
        $multaPct = $this->toFloat($empresa->param_boleto_multa_pct ?? null);
        $jurosPct = $this->toFloat($empresa->param_boleto_juros_pct ?? null);
        $descontoPct = $this->toFloat($empresa->param_boleto_desconto_pct ?? null);
        $dias = (int) preg_replace('/\D/', '', (string) ($empresa->param_boleto_protesto_dias ?? '')) ?: 0;
        $acao = EmpresaParametros::boletoPosVencimentoAcao($empresa);

        $instrucoes = [
            'valorAbatimento' => 0,
            'tipoDesconto' => $descontoPct > 0 ? 2 : 3,
            'descontos' => $descontoPct > 0
                ? [['valor' => round($descontoPct, 2), 'diasAteVencimento' => 0]]
                : [],
            'tipoMulta' => $multaPct > 0 ? 2 : 3,
            'valorMulta' => $multaPct > 0 ? round($multaPct, 2) : 0,
            'tipoJurosMora' => $jurosPct > 0 ? 2 : 3,
            'valorJurosMora' => $jurosPct > 0 ? round($jurosPct, 2) : 0,
            'diasNegativacao' => 0,
            'diasProtesto' => 0,
        ];

        // Mesmo critério Sicredi: protesto OU negativação (não os dois).
        if ($dias > 0) {
            if ($acao === EmpresaParametros::BOLETO_POS_VENCIMENTO_PROTESTO) {
                $instrucoes['diasProtesto'] = min(99, $dias);
            } elseif ($acao === EmpresaParametros::BOLETO_POS_VENCIMENTO_NEGATIVACAO) {
                $instrucoes['diasNegativacao'] = min(99, $dias);
            }
        }

        return $instrucoes;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function persistFromResponse(Empresa $empresa, ContaReceber $conta, array $response): Boleto
    {
        $boletoNode = is_array($response['boleto'] ?? null) ? $response['boleto'] : $response;

        $linha = (string) (
            data_get($boletoNode, 'codigoBarras.linhaDigitavel')
            ?? data_get($boletoNode, 'linhaDigitavel')
            ?? data_get($response, 'linhaDigitavel')
            ?? ''
        );

        $codigoBarras = (string) (
            data_get($boletoNode, 'codigoBarras.codigoBarras')
            ?? data_get($boletoNode, 'codigoBarras')
            ?? data_get($response, 'codigoBarras')
            ?? ''
        );
        if (is_array(data_get($boletoNode, 'codigoBarras'))) {
            $codigoBarras = (string) data_get($boletoNode, 'codigoBarras.codigoBarras', $codigoBarras);
        }

        $nossoNumero = (string) (
            data_get($boletoNode, 'documento.nossoNumero')
            ?? data_get($boletoNode, 'nossoNumero')
            ?? ''
        );

        $numeroDoc = (string) (
            data_get($boletoNode, 'documento.numeroDocumento')
            ?? $conta->numero
            ?? $conta->id
        );

        $idExterno = (string) (
            data_get($boletoNode, 'documento.identificadorUnicoTitulo')
            ?? data_get($boletoNode, 'identificadorUnicoTitulo')
            ?? ($nossoNumero !== '' ? $nossoNumero : '')
        );

        // Campos BolePix — nomes flexíveis até a Ailos confirmar o schema.
        $pixQr = (string) (
            data_get($boletoNode, 'bolePix.qrCodeBase64')
            ?? data_get($boletoNode, 'bolePix.imagemQrCode')
            ?? data_get($boletoNode, 'pix.qrCodeBase64')
            ?? data_get($boletoNode, 'qrCodeBase64')
            ?? data_get($boletoNode, 'imagemQrCode')
            ?? data_get($response, 'qrCodeBase64')
            ?? ''
        );

        $pixCopiaCola = (string) (
            data_get($boletoNode, 'bolePix.pixCopiaCola')
            ?? data_get($boletoNode, 'bolePix.copiaCola')
            ?? data_get($boletoNode, 'pix.copiaCola')
            ?? data_get($boletoNode, 'pixCopiaCola')
            ?? data_get($boletoNode, 'copiaCola')
            ?? data_get($response, 'copiaCola')
            ?? ''
        );

        /** @var Person $cliente */
        $cliente = $conta->cliente;

        $attrs = [
            'empresa_id' => $empresa->id,
            'conta_receber_id' => $conta->id,
            'person_id' => $cliente->id,
            'nosso_numero' => $nossoNumero !== '' ? $nossoNumero : null,
            'numero_documento' => $numeroDoc,
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
            'sacado_bairro' => $this->asciiTruncate((string) $cliente->bairro, 50),
            'sacado_cidade' => $this->asciiTruncate((string) $cliente->cidade_nome, 100),
            'sacado_uf' => strtoupper(substr((string) $cliente->uf, 0, 2)),
            'sacado_cep' => preg_replace('/\D/', '', (string) $cliente->cep),
            'instrucao1' => $this->asciiTruncate((string) ($empresa->param_boleto_instrucao1 ?? ''), 250) ?: null,
            'instrucao2' => $this->asciiTruncate((string) ($empresa->param_boleto_instrucao2 ?? ''), 250) ?: null,
            'pix_qr_base64' => $pixQr !== '' ? $pixQr : null,
            'pix_copia_cola' => $pixCopiaCola !== '' ? $pixCopiaCola : null,
            'id_externo' => $idExterno !== '' ? $idExterno : null,
            'status' => Boleto::STATUS_ABERTO,
        ];

        // Preserva vínculo com conta API se o dispatcher já setou no overlay flow.
        // (preenchido pelo dispatcher após o return)

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

    /**
     * @return array{ddi: string, ddd: string, numero: string}
     */
    private function splitPhone(string $raw): array
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (str_starts_with($digits, '55') && strlen($digits) > 11) {
            $digits = substr($digits, 2);
        }

        $ddd = strlen($digits) >= 10 ? substr($digits, 0, 2) : '';
        $numero = strlen($digits) >= 10 ? substr($digits, 2) : $digits;

        return [
            'ddi' => '55',
            'ddd' => $ddd,
            'numero' => $numero,
        ];
    }

    private function mapEspecie(string $especie): int
    {
        return match (strtoupper(trim($especie))) {
            'DM' => 1,
            'DS' => 2,
            'NP' => 3,
            'MENS', 'MEN' => 4,
            'NF', 'FAT' => 5,
            'RC', 'RECI' => 6,
            default => 7,
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
