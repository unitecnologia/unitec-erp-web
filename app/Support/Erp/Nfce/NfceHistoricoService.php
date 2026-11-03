<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVendaNfce;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;

/**
 * Monta o painel de detalhe (olho) da NFC-e — mesmo padrão visual do histórico NF-e.
 */
final class NfceHistoricoService
{
    /**
     * @return array<string, mixed>|null
     */
    public function montar(int $nfceId): ?array
    {
        $nfce = PdvVendaNfce::query()
            ->with([
                'pdvVenda.itens.product',
                'pdvVenda.pagamentos',
                'pdvVenda.person',
                'pdvVenda.vendedor',
                'pdvVenda.venda',
                'pdvVenda.sessao.terminal',
                'pdvVenda.sessao.caixaConta',
                'pdvVenda.user',
                'empresa',
            ])
            ->find($nfceId);

        if (! $nfce) {
            return null;
        }

        $venda = $nfce->pdvVenda;
        $numero = $nfce->numero !== null
            ? str_pad((string) $nfce->numero, 6, '0', STR_PAD_LEFT)
            : '—';
        $clienteNome = $this->clienteNome($nfce);
        $statusLabel = $this->statusLabel($nfce);
        $statusCodigo = $this->statusCodigoCss($nfce);

        return [
            'titulo' => 'NFC-e nº '.$numero.' — '.$clienteNome,
            'cabecalho' => [
                'numero' => $numero,
                'serie' => (string) ($nfce->serie ?: '1'),
                'modelo' => (string) ($nfce->modelo ?: '65'),
                'cliente' => $clienteNome,
                'status' => $statusLabel,
                'status_codigo' => $statusCodigo,
                'emissao' => filled($venda?->fechado_em)
                    ? ErpTimezone::toLocal($venda->fechado_em)->format('d/m/Y H:i')
                    : '—',
                'autorizada_em' => filled($nfce->autorizada_em)
                    ? ErpTimezone::toLocal($nfce->autorizada_em)->format('d/m/Y H:i')
                    : '—',
                'ambiente' => ((int) ($nfce->ambiente ?? 2) === 1) ? 'Produção' : 'Homologação',
                'empresa' => (string) ($nfce->empresa?->nome
                    ?: $nfce->empresa?->razao_social
                    ?: '—'),
                'caixa' => $this->caixaLabel($nfce),
                'vendedor' => $this->vendedorLabel($venda),
                'pedido' => $this->pedidoLabel($venda),
                'dav' => NfceListDavNumero::texto($nfce),
                'forma_pgto' => (string) ($venda?->forma_pagamento ?: '—'),
                'total' => ErpMoney::formatBr((float) ($venda?->total ?? 0)),
                'chave' => filled($nfce->chave) ? (string) $nfce->chave : '—',
                'protocolo' => filled($nfce->protocolo) ? (string) $nfce->protocolo : '—',
                'qtd_itens' => (string) ($venda?->itens?->count() ?? 0),
            ],
            'destinatario' => $this->montarDestinatario($nfce),
            'itens' => $this->montarItens($venda),
            'totais' => [
                'produtos' => ErpMoney::formatBr((float) ($venda?->subtotal ?? 0)),
                'desconto' => ErpMoney::formatBr((float) ($venda?->desconto ?? 0)),
                'acrescimo' => ErpMoney::formatBr((float) ($venda?->acrescimo ?? 0)),
                'nota' => ErpMoney::formatBr((float) ($venda?->total ?? 0)),
            ],
            'pagamentos' => $this->montarPagamentos($venda),
            'observacoes' => [
                'venda' => filled($venda?->observacoes) ? (string) $venda->observacoes : null,
                'rejeicao' => filled($nfce->motivo_rejeicao) ? (string) $nfce->motivo_rejeicao : null,
                'contingencia' => filled($nfce->motivo_contingencia) ? (string) $nfce->motivo_contingencia : null,
            ],
        ];
    }

    private function clienteNome(PdvVendaNfce $nfce): string
    {
        $venda = $nfce->pdvVenda;
        if ($venda === null) {
            return 'CONSUMIDOR FINAL';
        }

        $nota = trim((string) ($venda->nome_nota ?? ''));
        if ($nota !== '' && filled($venda->cpf_nota)) {
            return mb_strtoupper($nota, 'UTF-8');
        }

        return mb_strtoupper(
            NfceConsumidorIdentificado::nome($venda->person) ?: 'CONSUMIDOR FINAL',
            'UTF-8',
        );
    }

    private function statusLabel(PdvVendaNfce $nfce): string
    {
        if ($nfce->simulada || (string) $nfce->status === PdvVendaNfce::STATUS_SIMULADA) {
            return 'Simulada';
        }

        return match ((string) $nfce->status) {
            PdvVendaNfce::STATUS_AUTORIZADA => 'Autorizada',
            PdvVendaNfce::STATUS_CANCELADA => 'Cancelada',
            PdvVendaNfce::STATUS_CONTINGENCIA => 'Contingência',
            PdvVendaNfce::STATUS_PENDENTE => 'Gravada',
            PdvVendaNfce::STATUS_DUPLICIDADE => 'Duplicidade',
            PdvVendaNfce::STATUS_INUTILIZADA => 'Inutilizada',
            PdvVendaNfce::STATUS_DENEGADA => 'Denegada',
            PdvVendaNfce::STATUS_REJEITADA => 'Rejeitada',
            default => mb_convert_case((string) $nfce->status, MB_CASE_TITLE, 'UTF-8'),
        };
    }

    /** Classes CSS do modal NF-e (transmitida/cancelada/…). */
    private function statusCodigoCss(PdvVendaNfce $nfce): string
    {
        return match ((string) $nfce->status) {
            PdvVendaNfce::STATUS_AUTORIZADA, PdvVendaNfce::STATUS_SIMULADA => 'transmitida',
            PdvVendaNfce::STATUS_CANCELADA => 'cancelada',
            PdvVendaNfce::STATUS_CONTINGENCIA => 'contingencia',
            PdvVendaNfce::STATUS_DUPLICIDADE => 'duplicidade',
            PdvVendaNfce::STATUS_INUTILIZADA => 'inutilizada',
            PdvVendaNfce::STATUS_DENEGADA, PdvVendaNfce::STATUS_REJEITADA => 'denegada',
            default => 'aberta',
        };
    }

    private function caixaLabel(PdvVendaNfce $nfce): string
    {
        if ((string) $nfce->operacao === NfceListDavNumero::OPERACAO_SCV_HIST) {
            return 'CAIXA GERAL';
        }

        $sessao = $nfce->pdvVenda?->sessao;
        $terminal = trim((string) ($sessao?->terminal?->nome ?? ''));
        if ($terminal !== '') {
            return $terminal;
        }

        $conta = trim((string) ($sessao?->caixaConta?->nome ?? ''));

        return $conta !== '' ? $conta : '—';
    }

    private function vendedorLabel(mixed $venda): string
    {
        if ($venda === null) {
            return '—';
        }

        $nome = trim((string) ($venda->vendedor?->nome ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        $fallback = trim((string) ($venda->vendedor_nome ?? ''));

        return $fallback !== '' ? $fallback : '—';
    }

    private function pedidoLabel(mixed $venda): string
    {
        $pedido = $venda?->venda?->numero;
        if ($pedido === null || $pedido === '') {
            return '—';
        }

        $trimmed = ltrim((string) $pedido, '0');

        return $trimmed !== '' ? $trimmed : '0';
    }

    /**
     * @return array<string, string>
     */
    private function montarDestinatario(PdvVendaNfce $nfce): array
    {
        $venda = $nfce->pdvVenda;
        $person = $venda?->person;

        $doc = filled($venda?->cpf_nota)
            ? (string) $venda->cpf_nota
            : (string) ($person?->cpf_cnpj ?? '');
        $nome = filled($venda?->nome_nota) && filled($venda?->cpf_nota)
            ? (string) $venda->nome_nota
            : (string) ($person?->nome_razao ?? 'CONSUMIDOR FINAL');

        $end = '—';
        $bairro = '—';
        $cidade = '—';
        $uf = '—';
        $cep = '—';
        $fone = '—';
        $ie = '—';

        if ($person && NfceConsumidorIdentificado::ehClienteIdentificado($person)) {
            $end = collect([
                $person->endereco,
                filled($person->numero) ? 'nº '.$person->numero : null,
                $person->complemento ?? null,
            ])->filter()->implode(', ') ?: '—';
            $bairro = (string) ($person->bairro ?: '—');
            $cidade = (string) ($person->cidade_nome ?: '—');
            $uf = (string) ($person->uf ?: '—');
            $cep = (string) ($person->cep ?: '—');
            $fone = (string) ($person->celular1 ?: $person->celular2 ?: '—');
            $ie = (string) ($person->rg_ie ?: '—');
        }

        return [
            'nome' => mb_strtoupper(trim($nome) !== '' ? $nome : 'CONSUMIDOR FINAL', 'UTF-8'),
            'documento' => $doc !== '' ? $doc : '—',
            'ie' => $ie,
            'endereco' => $end,
            'bairro' => $bairro,
            'cidade' => $cidade,
            'uf' => $uf,
            'cep' => $cep,
            'fone' => $fone,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function montarItens(mixed $venda): array
    {
        if ($venda === null) {
            return [];
        }

        $out = [];
        $n = 0;
        foreach ($venda->itens as $item) {
            $n++;
            $out[] = [
                'item' => (string) $n,
                'codigo' => (string) ($item->codigo ?: $item->product?->codigo ?: '—'),
                'descricao' => (string) ($item->descricao ?: $item->product?->descricao ?: '—'),
                'ncm' => (string) ($item->product?->ncm ?: '—'),
                'cst' => '—',
                'cfop' => '—',
                'un' => (string) ($item->unidade ?: 'UN'),
                'quant' => number_format((float) $item->quantidade, 3, ',', '.'),
                'valor_unit' => ErpMoney::formatBr((float) $item->preco_unitario),
                'desconto' => ErpMoney::formatBr((float) $item->desconto),
                'valor_total' => ErpMoney::formatBr((float) $item->total),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{forma: string, valor: string}>
     */
    private function montarPagamentos(mixed $venda): array
    {
        if ($venda === null) {
            return [];
        }

        $out = [];
        foreach ($venda->pagamentos as $pag) {
            $out[] = [
                'forma' => method_exists($pag, 'descricaoComCanhoto')
                    ? $pag->descricaoComCanhoto()
                    : (string) ($pag->forma ?: '—'),
                'valor' => ErpMoney::formatBr((float) $pag->valor),
            ];
        }

        return $out;
    }
}
