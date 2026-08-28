<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Orcamento\OrcamentoBobinaFormatter as F;

/**
 * Linhas monoespaçadas (~48 cols) do comprovante de Sangria/Suprimento.
 * Usado pelo HTML bobina e pelo ESC/POS (Device Service).
 */
final class PdvMovimentoCaixaBobinaBuilder
{
    /**
     * @return array{tipo: string, lines: list<string>}
     */
    public function build(PdvCaixaMovimento $movimento, ?Empresa $empresa, ?string $usuarioFallback = null): array
    {
        $tipo = mb_strtolower(trim((string) $movimento->tipo), 'UTF-8');
        if (! in_array($tipo, ['sangria', 'suprimento'], true)) {
            throw new \InvalidArgumentException('Movimento não é sangria nem suprimento.');
        }

        $movimento->loadMissing(['sessao.user', 'sessao.terminal']);

        $sessao = $movimento->sessao;
        $momento = ErpTimezone::toLocal($movimento->created_at ?? now());
        $emitidoEm = ErpTimezone::toLocal();

        $valor = $tipo === 'sangria'
            ? (float) $movimento->saida
            : (float) $movimento->entrada;

        $historico = mb_strtoupper(trim((string) ($movimento->historico ?? '')), 'UTF-8');
        if ($historico === '') {
            $historico = $tipo === 'sangria' ? 'SANGRIA' : 'SUPRIMENTO DE DINHEIRO NO CAIXA';
        }

        $fantasia = mb_strtoupper(trim((string) ($empresa?->fantasia ?: $empresa?->nome ?? 'EMPRESA')), 'UTF-8');
        $endereco = mb_strtoupper(trim(implode(', ', array_filter([
            trim((string) ($empresa?->endereco ?? '')),
            filled($empresa?->numero ?? null) ? (string) $empresa->numero : null,
            filled($empresa?->bairro ?? null) ? (string) $empresa->bairro : null,
        ]))), 'UTF-8');
        $cidadeUf = trim(implode('-', array_filter([
            filled($empresa?->cidade ?? null) ? mb_strtoupper((string) $empresa->cidade, 'UTF-8') : null,
            filled($empresa?->uf ?? null) ? mb_strtoupper((string) $empresa->uf, 'UTF-8') : null,
        ])));
        $fone = trim((string) ($empresa?->telefone ?? ''));

        $usuario = mb_strtoupper(trim((string) (
            $sessao?->user?->name
            ?? $usuarioFallback
            ?? 'USUARIO'
        )), 'UTF-8');
        $caixaLabel = (string) ($sessao?->terminal?->nome ?? $sessao?->id ?? '');

        $lines = [];

        foreach (F::wrap($fantasia) as $line) {
            $lines[] = F::center($line);
        }
        if ($endereco !== '') {
            foreach (F::wrap($endereco) as $line) {
                $lines[] = F::center($line);
            }
        }
        if ($cidadeUf !== '') {
            foreach (F::wrap($cidadeUf) as $line) {
                $lines[] = F::center($line);
            }
        }
        if ($fone !== '') {
            $lines[] = F::center('Fone: '.$fone);
        }

        $lines[] = '';
        $lines[] = F::center($tipo === 'sangria' ? '****SANGRIA****' : '****SUPRIMENTO****');
        $lines[] = '';
        $lines[] = 'Data....: '.$momento->format('d/m/Y');
        $lines[] = 'Hora....: '.$momento->format('H:i:s');
        $lines[] = 'Usuario.: '.$usuario;
        $lines[] = 'Caixa...: '.$caixaLabel;
        $lines[] = '';

        if ($tipo === 'sangria') {
            $lines[] = 'Ref.....: '.$historico;
        } else {
            foreach (F::wrap($historico) as $line) {
                $lines[] = $line;
            }
        }
        $lines[] = 'Valor R$: '.ErpMoney::formatBr($valor);
        $lines[] = '';
        $lines[] = 'Declaro ter recebido o valor acima,';
        $lines[] = '';
        $lines[] = '';
        $lines[] = 'Assinatura: ________________';
        $lines[] = '';
        $lines[] = F::center('Relatório emitido em '.$emitidoEm->format('d/m/Y H:i:s'));

        return [
            'tipo' => $tipo,
            'lines' => $lines,
        ];
    }
}
