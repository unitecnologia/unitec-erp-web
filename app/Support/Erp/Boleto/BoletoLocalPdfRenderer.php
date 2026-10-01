<?php

namespace App\Support\Erp\Boleto;

use App\Models\Boleto;
use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpMoney;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

/**
 * Gera PDF local do boleto (FEBRABAN) — usado quando o banco não entrega PDF
 * (ex.: Ailos com formaEmissao=2 cooperado emite e expede).
 */
final class BoletoLocalPdfRenderer
{
    /**
     * @return array{path: string, name: string, display: string, owned: bool}
     */
    public function saveTo(string $path, Boleto $boleto, Empresa $empresa): array
    {
        $data = $this->viewData($boleto, $empresa);
        if (($data['linha'] ?? '') === '' && ($data['codigo_barras'] ?? '') === '') {
            throw new RuntimeException('Boleto sem linha digitável/código de barras para montar o PDF.');
        }

        Pdf::loadView('reports.boleto-bancario-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->save($path);

        $nn = $data['nosso_numero'] !== '' ? $data['nosso_numero'] : (string) $boleto->id;

        return [
            'path' => $path,
            'name' => 'BOLETO-'.$nn.'.pdf',
            'display' => 'Boleto '.$nn,
            'owned' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(Boleto $boleto, Empresa $empresa): array
    {
        $banco = preg_replace('/\D/', '', (string) ($empresa->param_boleto_banco ?? '')) ?: '';
        if ($banco === '') {
            $banco = '000';
        }
        $banco = str_pad(substr($banco, -3), 3, '0', STR_PAD_LEFT);

        $linha = trim((string) ($boleto->linha_digitavel ?? ''));
        $barras = preg_replace('/\D/', '', (string) ($boleto->codigo_barras ?? '')) ?: '';
        if ($barras === '' && $linha !== '') {
            $barras = $this->codigoBarrasFromLinha($linha);
        }

        $agencia = trim((string) ($empresa->param_boleto_agencia ?? ''));
        $agenciaDv = trim((string) ($empresa->param_boleto_agencia_dv ?? ''));
        $conta = trim((string) ($empresa->param_boleto_conta ?? ''));
        $contaDv = trim((string) ($empresa->param_boleto_conta_dv ?? ''));

        $agenciaConta = trim(
            ($agencia !== '' ? $agencia.($agenciaDv !== '' ? '-'.$agenciaDv : '') : '')
            .($agencia !== '' && $conta !== '' ? ' / ' : '')
            .($conta !== '' ? $conta.($contaDv !== '' ? '-'.$contaDv : '') : '')
        );

        $cedenteNome = trim((string) ($empresa->razao_social ?: $empresa->fantasia ?: $empresa->nome ?: ''));
        $cedenteDoc = $this->formatDocumento((string) ($empresa->cnpj ?? ''));
        $cedenteEndereco = $this->formatEnderecoEmpresa($empresa);

        $sacadoDoc = $this->formatDocumento((string) ($boleto->sacado_documento ?? ''));
        $sacadoEndereco = trim(implode(' — ', array_filter([
            trim(implode(', ', array_filter([
                trim((string) ($boleto->sacado_logradouro ?? '')),
                trim((string) ($boleto->sacado_numero ?? '')),
            ]))),
            trim((string) ($boleto->sacado_bairro ?? '')),
            trim(implode('/', array_filter([
                trim((string) ($boleto->sacado_cidade ?? '')),
                strtoupper(trim((string) ($boleto->sacado_uf ?? ''))),
            ]))),
            $this->formatCep((string) ($boleto->sacado_cep ?? '')),
        ])));

        $instrucoes = array_values(array_filter([
            trim((string) ($boleto->instrucao1 ?? '')),
            trim((string) ($boleto->instrucao2 ?? '')),
        ]));

        $bancoNome = match ($banco) {
            EmpresaParametros::BOLETO_BANCO_AILOS => 'AILOS',
            EmpresaParametros::BOLETO_BANCO_SICREDI => 'SICREDI',
            default => 'BANCO '.$banco,
        };

        return [
            'boleto' => $boleto,
            'banco' => $banco,
            'banco_dv' => $this->bancoDv($banco),
            'banco_nome' => $bancoNome,
            'logo_data_uri' => BoletoBancoAssets::logoDataUri($banco),
            'linha' => $linha !== '' ? $linha : $this->formatLinhaFromBarras($barras),
            'codigo_barras' => $barras,
            'barcode_data_uri' => BoletoInterleaved2of5::dataUri($barras, 52),
            'nosso_numero' => trim((string) ($boleto->nosso_numero ?? '')),
            'numero_documento' => trim((string) ($boleto->numero_documento ?? '')),
            'carteira' => trim((string) ($empresa->param_boleto_carteira ?? '')),
            'especie_doc' => trim((string) ($empresa->param_boleto_especie_documento ?? 'DM')) ?: 'DM',
            'especie' => 'R$',
            'aceite' => 'N',
            'local_pagamento' => $banco === EmpresaParametros::BOLETO_BANCO_AILOS
                ? 'Pagável preferencialmente na Rede Ailos'
                : 'Pagável em qualquer banco até o vencimento',
            'vencimento' => optional($boleto->vencimento)?->format('d/m/Y') ?: '',
            'emissao' => optional($boleto->emissao)?->format('d/m/Y') ?: '',
            'processamento' => optional($boleto->processamento)?->format('d/m/Y')
                ?: (optional($boleto->emissao)?->format('d/m/Y') ?: ''),
            'valor' => 'R$ '.ErpMoney::formatBr((float) ($boleto->valor ?? 0)),
            'agencia_conta' => $agenciaConta !== '' ? $agenciaConta : '—',
            'cedente_nome' => $cedenteNome !== '' ? mb_strtoupper($cedenteNome, 'UTF-8') : '—',
            'cedente_doc' => $cedenteDoc !== '' ? $cedenteDoc : '—',
            'cedente_endereco' => $cedenteEndereco,
            'sacado_nome' => mb_strtoupper(trim((string) ($boleto->sacado_nome ?? '')), 'UTF-8') ?: '—',
            'sacado_doc' => $sacadoDoc !== '' ? $sacadoDoc : '—',
            'sacado_endereco' => $sacadoEndereco !== '' ? $sacadoEndereco : '—',
            'instrucoes' => $instrucoes,
            'pix_copia_cola' => trim((string) ($boleto->pix_copia_cola ?? '')),
            'pix_qr_data_uri' => $this->pixQrDataUri($boleto),
        ];
    }

    private function pixQrDataUri(Boleto $boleto): ?string
    {
        $raw = trim((string) ($boleto->pix_qr_base64 ?? ''));
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'data:')) {
            return $raw;
        }

        return 'data:image/png;base64,'.preg_replace('/\s+/', '', $raw);
    }

    private function bancoDv(string $banco): string
    {
        // DVs usuais dos bancos suportados (exibição do cabeçalho 085-1).
        return match ($banco) {
            '001' => '9',
            '033' => '7',
            '104' => '0',
            '237' => '2',
            '341' => '7',
            '748' => 'X',
            '085' => '1',
            default => '0',
        };
    }

    private function codigoBarrasFromLinha(string $linha): string
    {
        $d = preg_replace('/\D/', '', $linha) ?? '';
        if (strlen($d) !== 47) {
            return '';
        }

        // Linha 47 → código de barras 44 (FEBRABAN).
        return substr($d, 0, 4)
            .substr($d, 32, 15)
            .substr($d, 4, 5)
            .substr($d, 10, 10)
            .substr($d, 21, 10);
    }

    private function formatLinhaFromBarras(string $barras): string
    {
        $d = preg_replace('/\D/', '', $barras) ?? '';
        if (strlen($d) !== 44) {
            return $d;
        }

        $campo1 = substr($d, 0, 4).substr($d, 19, 5);
        $campo2 = substr($d, 24, 10);
        $campo3 = substr($d, 34, 10);
        $campo4 = substr($d, 4, 1);
        $campo5 = substr($d, 5, 14);

        $c1 = $campo1.$this->mod10($campo1);
        $c2 = $campo2.$this->mod10($campo2);
        $c3 = $campo3.$this->mod10($campo3);

        return substr($c1, 0, 5).'.'.substr($c1, 5)
            .' '.substr($c2, 0, 5).'.'.substr($c2, 5)
            .' '.substr($c3, 0, 5).'.'.substr($c3, 5)
            .' '.$campo4
            .' '.$campo5;
    }

    private function mod10(string $number): int
    {
        $sum = 0;
        $weight = 2;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = ((int) $number[$i]) * $weight;
            if ($n > 9) {
                $n = intdiv($n, 10) + ($n % 10);
            }
            $sum += $n;
            $weight = $weight === 2 ? 1 : 2;
        }
        $mod = $sum % 10;

        return $mod === 0 ? 0 : 10 - $mod;
    }

    private function formatDocumento(string $doc): string
    {
        $d = preg_replace('/\D/', '', $doc) ?? '';
        if (strlen($d) === 11) {
            return substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2);
        }
        if (strlen($d) === 14) {
            return substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'
                .substr($d, 8, 4).'-'.substr($d, 12, 2);
        }

        return $doc;
    }

    private function formatCep(string $cep): string
    {
        $d = preg_replace('/\D/', '', $cep) ?? '';
        if (strlen($d) === 8) {
            return substr($d, 0, 5).'-'.substr($d, 5, 3);
        }

        return $cep;
    }

    private function formatEnderecoEmpresa(Empresa $empresa): string
    {
        return trim(implode(' — ', array_filter([
            trim(implode(', ', array_filter([
                trim((string) ($empresa->endereco ?? '')),
                trim((string) ($empresa->numero ?? '')),
                trim((string) ($empresa->complemento ?? '')),
            ]))),
            trim((string) ($empresa->bairro ?? '')),
            trim(implode('/', array_filter([
                trim((string) ($empresa->cidade ?? '')),
                strtoupper(trim((string) ($empresa->uf ?? ''))),
            ]))),
            $this->formatCep((string) ($empresa->cep ?? '')),
        ])));
    }
}
