<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Fiscal\IbptLookupService;
use Carbon\CarbonInterface;

/**
 * Layout bobina do cupom não fiscal (DAV) — estilo Delphi.
 */
final class PdvDavCupomLayout
{
    /** Largura bobina 80mm (fonte A) — cabeçalho, totais e rodapé. */
    public const WIDTH = 48;

    /** Largura condensada (fonte B) — área de itens. */
    public const WIDTH_ITEMS = 64;

    /**
     * @return array{
     *     davNumero: string,
     *     lines: list<array{text: string, bold?: bool, center?: bool, font?: string}>,
     *     plain: list<string>
     * }
     */
    public static function build(PdvVenda $venda, ?Empresa $empresa, ?CarbonInterface $printedAt = null): array
    {
        $venda->loadMissing(['itens.product', 'pagamentos', 'person', 'vendedor']);
        $at = $printedAt ?? ($venda->fechado_em ?? $venda->created_at ?? now());
        $w = self::WIDTH;
        $wItems = self::WIDTH_ITEMS;

        $empresaNome = self::up($empresa?->fantasia ?: $empresa?->nome ?: 'UNITEC');
        $endereco = self::formatEndereco($empresa);
        $cidadeLinha = self::formatCidadeLinha($empresa);
        $fone = filled($empresa?->telefone)
            ? 'Fone: '.preg_replace('/\D+/', '', (string) $empresa->telefone)
            : '';

        $davNumero = str_pad((string) ((int) $venda->numero), 4, '0', STR_PAD_LEFT);

        $person = $venda->person;
        $cliCodigo = $person?->codigo !== null && (string) $person->codigo !== ''
            ? str_pad((string) ((int) preg_replace('/\D/', '', (string) $person->codigo) ?: $person->codigo), 4, '0', STR_PAD_LEFT)
            : '0001';
        $cliNome = self::up($person?->nome_razao ?? 'CONSUMIDOR FINAL');
        $clienteLinha = $cliCodigo.'=>'.$cliNome;

        $telefoneCliente = '';
        if ($person) {
            $telefoneCliente = (string) ($person->fone1 ?: $person->fone2 ?: '');
        }

        $vendedor = self::up(
            $venda->vendedor_nome ?: ($venda->vendedor?->nome ?? 'LOJA')
        );

        $lines = [];

        $lines[] = self::line(self::center($empresaNome, $w), bold: true, center: true);
        if ($endereco !== '') {
            $lines[] = self::line(self::center($endereco, $w), center: true);
        }
        if ($cidadeLinha !== '') {
            $lines[] = self::line(self::center($cidadeLinha, $w), center: true);
        }
        if ($fone !== '') {
            $lines[] = self::line(self::center($fone, $w), center: true);
        }

        $lines[] = self::line(self::dash($w));
        $lines[] = self::line(self::starsCenter('DAV No. '.$davNumero, $w), bold: true, center: true);
        $lines[] = self::line(self::dash($w));

        $lines[] = self::line(self::labelDots('Cliente', $clienteLinha, $w));
        $lines[] = self::line(self::labelDots('Telefone', $telefoneCliente, $w));

        $docCliente = trim((string) ($person?->cpf_cnpj ?? ''));
        $ieCliente = trim((string) ($person?->rg_ie ?? ''));
        $cnpjIe = $docCliente;
        if ($ieCliente !== '') {
            $cnpjIe = trim($docCliente.' IE: '.$ieCliente);
        } elseif ($docCliente !== '') {
            $cnpjIe = $docCliente.' IE:';
        }
        if ($cnpjIe !== '') {
            $lines[] = self::line(self::labelDots('CNPJ', $cnpjIe, $w));
        }

        $lines[] = self::line(self::labelDots('Emitido em', $at->format('d/m/Y'), $w));
        $lines[] = self::line(self::labelDots('Hora', $at->format('H:i:s'), $w));
        $lines[] = self::line(self::labelDots('Vendedor', $vendedor, $w));

        $lines[] = self::line(self::dash($wItems), font: 'B');
        $lines[] = self::line(self::itemHeaderLine($wItems), bold: true, font: 'B');
        $lines[] = self::line(self::dash($wItems), font: 'B');

        $itens = $venda->itens->values();
        $ultimo = $itens->count() - 1;
        foreach ($itens as $index => $item) {
            foreach (self::formatItemLines($item, $wItems) as $linhaProduto) {
                $lines[] = self::line($linhaProduto, font: 'B');
            }

            // Espaço leve entre itens (sem grudar).
            if ($index < $ultimo) {
                $lines[] = self::line('', font: 'B');
            }
        }

        $pago = (float) $venda->pagamentos->sum('valor');
        if ($pago <= 0) {
            $pago = (float) $venda->total;
        }

        $lines[] = self::line(self::dash($w));
        $lines[] = self::line(self::moneyLine('SubtTotal', number_format((float) $venda->subtotal, 2, ',', ''), $w));

        $desconto = round((float) ($venda->desconto ?? 0), 2);
        if ($desconto > 0) {
            $lines[] = self::line(self::moneyLine('Desconto', number_format($desconto, 2, ',', ''), $w));
        }

        $acrescimo = round((float) ($venda->acrescimo ?? 0), 2);
        if ($acrescimo > 0) {
            $lines[] = self::line(self::moneyLine('Acrescimo', number_format($acrescimo, 2, ',', ''), $w));
        }

        $lines[] = self::line(self::moneyLine('Total', number_format((float) $venda->total, 2, ',', ''), $w), bold: true);
        $lines[] = self::line(self::moneyLine('Valor Pago', number_format($pago, 2, ',', ''), $w));
        $lines[] = self::line(self::moneyLine('Troco', number_format((float) $venda->troco, 2, ',', ''), $w));

        $lines[] = self::line(self::dash($w));
        $lines[] = self::line(self::starsCenter('Forma de pagamento', $w), bold: true, center: true);

        if ($venda->pagamentos->isNotEmpty()) {
            foreach ($venda->pagamentos as $pagamento) {
                $lines[] = self::line(self::moneyLine(
                    self::up($pagamento->descricaoComCanhoto()),
                    number_format((float) $pagamento->valor, 2, ',', ''),
                    $w
                ));
            }
        } elseif (filled($venda->forma_pagamento)) {
            $lines[] = self::line(self::moneyLine(
                self::up((string) $venda->forma_pagamento),
                number_format((float) $venda->total, 2, ',', ''),
                $w
            ));
        }

        $economizado = self::totalDescontosEconomizados($venda);
        if ($economizado > 0) {
            $lines[] = self::line(self::dash($w));
            $lines[] = self::line(
                self::moneyLine('Voce economizou', number_format($economizado, 2, ',', ''), $w),
                bold: true,
            );
        }

        $lines[] = self::line(self::dash($w));
        foreach (app(IbptLookupService::class)->linhasBobinaDosItens($venda->itens, $w) as $linhaIbpt) {
            $lines[] = self::line($linhaIbpt);
        }
        $lines[] = self::line(self::dash($w));
        $lines[] = self::line(self::center('DOCUMENTO NAO FISCAL', $w), bold: true, center: true);
        $lines[] = self::line(self::center('!!DAV DEVE SER FINALIZADO!!', $w), bold: true, center: true);
        $lines[] = self::line(self::center('**Obrigado Pela Preferência**', $w), center: true);
        $lines[] = self::line(self::dash($w));

        return [
            'davNumero' => $davNumero,
            'lines' => $lines,
            'plain' => array_map(static fn (array $row): string => $row['text'], $lines),
        ];
    }

    public const MESA_PEDIDO = 'pedido';

    public const MESA_ITEM = 'item';

    public const MESA_PARCIAL = 'parcial';

    /** Largura da linha em fonte dupla (número da mesa). */
    private const WIDTH_BIG = 24;

    /**
     * Pedido da mesa, item avulso ou pré-conta (parcial). Sem valor fiscal; não gera venda.
     *
     * @param  list<array<string, mixed>>  $itens  itens do cupom da mesa (mesmo formato da sessão do PDV)
     * @return array{
     *     titulo: string,
     *     davNumero: string,
     *     lines: list<array{text: string, bold?: bool, center?: bool, font?: string, size?: int}>,
     *     plain: list<string>
     * }
     */
    public static function buildMesa(
        int $numero,
        array $itens,
        string $tipo,
        ?Empresa $empresa,
        string $atendente,
        string $terminal,
        ?CarbonInterface $printedAt = null,
    ): array {
        $at = $printedAt ?? now();
        $w = self::WIDTH;
        $wItems = self::WIDTH_ITEMS;
        $mesaRotulo = 'MESA '.str_pad((string) $numero, 2, '0', STR_PAD_LEFT);

        $titulo = match ($tipo) {
            self::MESA_PARCIAL => 'PRÉ-CONTA',
            self::MESA_ITEM => 'PEDIDO - ITEM',
            default => 'PEDIDO',
        };

        $lines = [];
        $empresaNome = self::up($empresa?->fantasia ?: $empresa?->nome ?: 'UNITEC');
        $lines[] = self::line(self::center($empresaNome, $w), bold: true, center: true);

        if ($tipo === self::MESA_PARCIAL) {
            $endereco = self::formatEndereco($empresa);
            $cidadeLinha = self::formatCidadeLinha($empresa);
            if ($endereco !== '') {
                $lines[] = self::line(self::center($endereco, $w), center: true);
            }
            if ($cidadeLinha !== '') {
                $lines[] = self::line(self::center($cidadeLinha, $w), center: true);
            }
        }

        $lines[] = self::line(self::dash($w));
        $lines[] = self::line(self::starsCenter($titulo, $w), bold: true, center: true);
        $lines[] = self::bigLine($mesaRotulo);
        $lines[] = self::line(self::dash($w));

        $lines[] = self::line(self::labelDots('Emitido em', $at->format('d/m/Y').'  '.$at->format('H:i:s'), $w));
        if ($atendente !== '') {
            $lines[] = self::line(self::labelDots('Atendente', self::up($atendente), $w));
        }
        if ($terminal !== '') {
            $lines[] = self::line(self::labelDots('Terminal', self::up($terminal), $w));
        }

        $lines[] = self::line(self::dash($wItems), font: 'B');
        $lines[] = self::line(self::itemHeaderLine($wItems), bold: true, font: 'B');
        $lines[] = self::line(self::dash($wItems), font: 'B');

        $subtotal = 0.0;
        $descontos = 0.0;
        $acrescimos = 0.0;
        $total = 0.0;
        $ultimo = count($itens) - 1;

        foreach (array_values($itens) as $index => $item) {
            $quantidade = (float) ($item['quantidade'] ?? 0);
            $linha = (object) [
                'codigo' => (string) ($item['codigo'] ?? ''),
                'descricao' => (string) ($item['descricao'] ?? ''),
                'unidade' => (string) ($item['unidade'] ?? 'UN'),
                'quantidade' => $quantidade,
                'preco_unitario' => (float) ($item['preco'] ?? 0),
                'desconto' => round((float) ($item['desconto'] ?? 0), 2),
                'acrescimo' => round((float) ($item['acrescimo'] ?? 0), 2),
                'total' => (float) ($item['total'] ?? 0),
            ];

            foreach (self::formatItemLines($linha, $wItems, descricaoCompleta: true) as $linhaProduto) {
                $lines[] = self::line($linhaProduto, font: 'B');
            }

            if ($index < $ultimo) {
                $lines[] = self::line('', font: 'B');
            }

            $subtotal += round((float) ($item['preco_base'] ?? $item['preco'] ?? 0) * $quantidade, 2);
            $descontos += round($linha->desconto * $quantidade, 2);
            $acrescimos += round($linha->acrescimo * $quantidade, 2);
            $total += $linha->total;
        }

        $lines[] = self::line(self::dash($w));
        $lines[] = self::line(self::labelDots('Itens', (string) count($itens), $w));

        if ($tipo === self::MESA_PARCIAL) {
            $lines[] = self::line(self::moneyLine('SubtTotal', number_format(round($subtotal, 2), 2, ',', ''), $w));
            if (round($descontos, 2) > 0) {
                $lines[] = self::line(self::moneyLine('Desconto', number_format(round($descontos, 2), 2, ',', ''), $w));
            }
            if (round($acrescimos, 2) > 0) {
                $lines[] = self::line(self::moneyLine('Acrescimo', number_format(round($acrescimos, 2), 2, ',', ''), $w));
            }
        }

        $lines[] = self::line(self::moneyLine('Total', number_format(round($total, 2), 2, ',', ''), $w), bold: true);
        $lines[] = self::line(self::dash($w));

        if ($tipo === self::MESA_PARCIAL) {
            $lines[] = self::bigLine('PRÉ-CONTA');
            $lines[] = self::line(self::center('SEM VALOR FISCAL', $w), bold: true, center: true);
            $lines[] = self::line(self::center('Confira os itens antes do fechamento', $w), center: true);
        } else {
            $lines[] = self::line(self::center('PEDIDO DE MESA - SEM VALOR FISCAL', $w), bold: true, center: true);
        }
        $lines[] = self::line(self::dash($w));

        return [
            'titulo' => $titulo.' - '.$mesaRotulo,
            'davNumero' => str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
            'lines' => $lines,
            'plain' => array_map(static fn (array $row): string => $row['text'], $lines),
        ];
    }

    /**
     * Linhas do item (fonte condensada).
     * Com $descricaoCompleta=true, a descrição continua em linhas seguintes (sem abreviar com ".").
     *
     * @return list<string>
     */
    public static function formatItemLines(object $item, int $w, bool $descricaoCompleta = false): array
    {
        $codigo = trim((string) $item->codigo);
        $codigoShow = mb_substr($codigo === '' ? '-' : $codigo, 0, 4);
        $codigoCol = str_pad($codigoShow, 4, ' ', STR_PAD_RIGHT);

        $qtd = (float) $item->quantidade;
        $qtdLabel = fmod($qtd, 1.0) === 0.0
            ? (string) (int) $qtd
            : number_format($qtd, 3, ',', '');
        $und = self::up((string) ($item->unidade ?: 'UN'));
        $vlUni = number_format((float) $item->preco_unitario, 2, ',', '');
        $ajuste = self::formatItemDescAcre($item);
        $total = number_format((float) $item->total, 2, ',', '');

        $bloco = self::itemValuesBlock($qtdLabel, $und, $vlUni, $ajuste, $total);
        $descMax = max(8, $w - mb_strlen($bloco) - 5);
        $desc = self::up((string) $item->descricao);

        if (! $descricaoCompleta) {
            if (mb_strlen($desc) > $descMax) {
                $desc = mb_substr($desc, 0, max(1, $descMax - 1)).'.';
            }

            $left = rtrim($codigoCol.' '.$desc);
            $left = mb_substr($left, 0, $w - mb_strlen($bloco));
            $pad = max(0, $w - mb_strlen($left) - mb_strlen($bloco));

            return [$left.str_repeat(' ', $pad).$bloco];
        }

        $firstDesc = mb_substr($desc, 0, $descMax);
        $rest = mb_substr($desc, mb_strlen($firstDesc));
        $left = rtrim($codigoCol.' '.$firstDesc);
        $left = mb_substr($left, 0, $w - mb_strlen($bloco));
        $pad = max(0, $w - mb_strlen($left) - mb_strlen($bloco));

        $lines = [$left.str_repeat(' ', $pad).$bloco];

        $indent = 5; // alinhado sob a descrição (após COD + espaço)
        $wrapWidth = max(8, $w - $indent);
        while ($rest !== '') {
            $chunk = mb_substr($rest, 0, $wrapWidth);
            $rest = mb_substr($rest, mb_strlen($chunk));
            $lines[] = str_repeat(' ', $indent).$chunk;
        }

        return $lines;
    }

    private static function formatItemDescAcre(object $item): string
    {
        $desconto = round((float) ($item->desconto ?? 0), 2);
        $acrescimo = round((float) ($item->acrescimo ?? 0), 2);

        if ($desconto > 0) {
            return '- '.number_format($desconto, 2, ',', '');
        }

        if ($acrescimo > 0) {
            return '+ '.number_format($acrescimo, 2, ',', '');
        }

        return '0,00';
    }

    /**
     * Soma descontos de itens (unitário x qtd) + desconto da venda.
     */
    public static function totalDescontosEconomizados(PdvVenda $venda): float
    {
        $itens = 0.0;
        foreach ($venda->itens as $item) {
            $itens += round((float) ($item->desconto ?? 0) * (float) ($item->quantidade ?? 0), 2);
        }

        $vendaDesconto = round((float) ($venda->desconto ?? 0), 2);

        return round($itens + $vendaDesconto, 2);
    }

    public static function itemHeaderLine(int $w): string
    {
        $right = self::itemValuesBlock('QTD', 'UN', 'VL.UNI', 'DESC/ACRE', 'TOTAL');
        $left = 'COD DESCRICAO';
        $gap = max(1, $w - mb_strlen($left) - mb_strlen($right));

        return $left.str_repeat(' ', $gap).$right;
    }

    private static function itemValuesBlock(
        string $qtd,
        string $und,
        string $vlUni,
        string $descAcre,
        string $total,
    ): string {
        return sprintf(
            '%4s %3s %7s %9s %7s',
            mb_substr($qtd, 0, 4),
            mb_substr($und, 0, 3),
            mb_substr($vlUni, 0, 7),
            mb_substr($descAcre, 0, 9),
            mb_substr($total, 0, 7),
        );
    }

    /**
     * @return array{text: string, bold: bool, center: bool, font: string}
     */
    private static function line(string $text, bool $bold = false, bool $center = false, string $font = 'A'): array
    {
        return [
            'text' => $text,
            'bold' => $bold,
            'center' => $center,
            'font' => strtoupper($font) === 'B' ? 'B' : 'A',
        ];
    }

    /**
     * Fonte dupla, centralizada pela impressora (ESC/POS) ou por CSS (HTML): texto sem preenchimento.
     *
     * @return array{text: string, bold: bool, center: bool, font: string, size: int}
     */
    private static function bigLine(string $text): array
    {
        return [
            'text' => mb_substr(trim($text), 0, self::WIDTH_BIG),
            'bold' => true,
            'center' => true,
            'font' => 'A',
            'size' => 2,
        ];
    }

    private static function formatEndereco(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '';
        }

        $rua = self::up(trim((string) $empresa->endereco));
        $numero = trim((string) ($empresa->numero ?? ''));

        if ($rua === '' && $numero === '') {
            return '';
        }

        if ($rua !== '' && $numero !== '') {
            return $rua.', '.$numero;
        }

        return $rua !== '' ? $rua : $numero;
    }

    private static function formatCidadeLinha(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '';
        }

        $bairro = self::up(trim((string) ($empresa->bairro ?? '')));
        $cidade = self::up(trim((string) ($empresa->cidade ?? '')));
        $uf = self::up(trim((string) ($empresa->uf ?? '')));

        $cidadeUf = $cidade;
        if ($cidade !== '' && $uf !== '') {
            $cidadeUf = $cidade.'-'.$uf;
        } elseif ($uf !== '') {
            $cidadeUf = $uf;
        }

        if ($bairro !== '' && $cidadeUf !== '') {
            return $bairro.' - '.$cidadeUf;
        }

        return $bairro !== '' ? $bairro : $cidadeUf;
    }

    private static function dash(int $w): string
    {
        return str_repeat('-', $w);
    }

    private static function center(string $text, int $w): string
    {
        $text = mb_substr($text, 0, $w);
        $pad = max(0, $w - mb_strlen($text));
        $left = intdiv($pad, 2);

        return str_repeat(' ', $left).$text;
    }

    private static function starsCenter(string $text, int $w): string
    {
        $text = ' '.$text.' ';
        $text = mb_substr($text, 0, $w);
        $pad = max(0, $w - mb_strlen($text));
        $left = intdiv($pad, 2);
        $right = $pad - $left;

        return str_repeat('*', $left).$text.str_repeat('*', $right);
    }

    private static function labelDots(string $label, string $value, int $w): string
    {
        $label = rtrim($label, '.:');
        $labelCol = 11;
        $dots = max(0, $labelCol - 1 - mb_strlen($label));
        $left = $label.str_repeat('.', $dots).':';
        $maxValue = max(0, $w - mb_strlen($left) - 1);
        $value = mb_substr($value, 0, $maxValue);

        return $value === '' ? $left : $left.' '.$value;
    }

    private static function moneyLine(string $label, string $value, int $w): string
    {
        $label = rtrim($label, '.:');
        $right = ': '.$value;
        $dots = max(1, $w - mb_strlen($label) - mb_strlen($right));

        return $label.str_repeat('.', $dots).$right;
    }

    private static function up(?string $value): string
    {
        return mb_strtoupper(trim((string) $value), 'UTF-8');
    }
}
