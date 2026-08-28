<?php

namespace App\Support\Erp\Recibo;

use App\Models\Empresa;
use App\Models\Recibo;
use App\Support\Erp\Orcamento\OrcamentoBobinaFormatter as F;

final class ReciboBobinaBuilder
{
    /**
     * @return list<string>
     */
    public function buildLines(Recibo $recibo, ?Empresa $empresa): array
    {
        $lines = [];

        $nome = mb_strtoupper(trim((string) ($empresa?->fantasia ?: $empresa?->razao_social ?: $empresa?->nome ?: 'EMPRESA')), 'UTF-8');
        foreach (F::wrap($nome) as $line) {
            $lines[] = $line;
        }

        $endereco = $this->formatEndereco($empresa);
        if ($endereco !== '') {
            foreach (F::wrap($endereco) as $line) {
                $lines[] = $line;
            }
        }

        $cidadeLinha = $this->formatBairroCidadeUf($empresa);
        if ($cidadeLinha !== '') {
            foreach (F::wrap($cidadeLinha) as $line) {
                $lines[] = $line;
            }
        }

        if (filled($empresa?->telefone)) {
            $fone = preg_replace('/\D+/', '', (string) $empresa->telefone) ?: trim((string) $empresa->telefone);
            $lines[] = 'Fone: '.$fone;
        }

        $lines[] = str_repeat('-', 33);
        $lines[] = F::center('*** RECIBO No. '.$this->formatCodigo($recibo).' ***');
        $lines[] = 'VALOR R$'.$recibo->valorFormatado().' ***';

        foreach (F::wrap('Recebi de '.mb_strtoupper(trim((string) $recibo->recebi_de), 'UTF-8')) as $line) {
            $lines[] = $line;
        }

        $extenso = mb_strtoupper(trim($recibo->ensureExtenso()), 'UTF-8');
        foreach (F::wrap('A quantia de '.$extenso) as $line) {
            $lines[] = $line;
        }

        if (filled($recibo->referente_a)) {
            foreach (F::wrap('Referente a '.mb_strtoupper(trim((string) $recibo->referente_a), 'UTF-8')) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = $this->formatDataLinha($empresa, $recibo);
        $lines[] = 'Assinatura:';
        $lines[] = '';
        $lines[] = '';
        $lines[] = '';
        $lines[] = F::center('<<obrigado pela preferencia>>');

        return $lines;
    }

    protected function formatCodigo(Recibo $recibo): string
    {
        return str_pad((string) ((int) $recibo->codigo), 4, '0', STR_PAD_LEFT);
    }

    protected function formatEndereco(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '';
        }

        $partes = array_filter([
            filled($empresa->endereco) ? mb_strtoupper(trim((string) $empresa->endereco), 'UTF-8') : null,
            filled($empresa->numero) ? trim((string) $empresa->numero) : null,
        ]);

        return $partes === [] ? '' : implode(', ', $partes);
    }

    protected function formatBairroCidadeUf(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '';
        }

        $bairro = filled($empresa->bairro) ? mb_strtoupper(trim((string) $empresa->bairro), 'UTF-8') : '';
        $cidade = filled($empresa->cidade) ? mb_strtoupper(trim((string) $empresa->cidade), 'UTF-8') : '';
        $uf = filled($empresa->uf) ? mb_strtoupper(trim((string) $empresa->uf), 'UTF-8') : '';

        $cidadeUf = $cidade !== '' && $uf !== ''
            ? $cidade.'-'.$uf
            : ($cidade !== '' ? $cidade : $uf);

        if ($bairro !== '' && $cidadeUf !== '') {
            return $bairro.' - '.$cidadeUf;
        }

        return $bairro !== '' ? $bairro : $cidadeUf;
    }

    protected function formatDataLinha(?Empresa $empresa, Recibo $recibo): string
    {
        $cidade = filled($empresa?->cidade) ? mb_strtoupper(trim((string) $empresa->cidade), 'UTF-8') : '';
        $uf = filled($empresa?->uf) ? mb_strtoupper(trim((string) $empresa->uf), 'UTF-8') : '';
        $data = optional($recibo->emissao)->format('d/m/Y') ?? '';

        $cidadeUf = $cidade !== '' && $uf !== ''
            ? $cidade.'-'.$uf
            : ($cidade !== '' ? $cidade : $uf);

        if ($cidadeUf !== '' && $data !== '') {
            return $cidadeUf.', '.$data;
        }

        return $cidadeUf !== '' ? $cidadeUf : $data;
    }
}
