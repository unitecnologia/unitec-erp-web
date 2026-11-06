<?php

namespace App\Support\Erp;

/**
 * Normalização somente de apresentação do histórico do Caixa.
 * Não altera gravação, idempotência, estorno nem vínculos internos.
 *
 * VENDA ERP FV (DINHEIRO) [FV-12] #P1 → TV {nº pedido} (DINHEIRO)
 * VENDA APP FV (PIX) [FV-83]       → FV {nº pedido} (PIX)
 * OS 74 (PIX)                      → inalterado
 *
 * O nº exibido é o do Pedido/Monitor (vendas.numero), não o id interno FV/DAV.
 */
final class CaixaHistoricoExibicao
{
    /**
     * Histórico pronto para listagem/consulta/relatório (sem sessão PDV).
     *
     * @param  array<int, string>  $pedidosMonitor  id forca_vendas_orders => nº pedido
     */
    public static function paraExibicao(string $historico, array $pedidosMonitor = []): string
    {
        $limpo = preg_replace('/^\[MANUAL\]\s*/iu', '', $historico) ?? $historico;
        $upper = mb_strtoupper(trim($limpo), 'UTF-8');

        return self::compactarForcaVendas($upper, $pedidosMonitor);
    }

    /**
     * Compacta prefixos VENDA ERP|APP FV … [FV-n] [#Pk] no padrão OS (TV/FV n (MEIO)).
     * Preserva sufixos (ex.: sessão PDV) e prefixo ESTORNO.
     *
     * @param  array<int, string>  $pedidosMonitor
     */
    public static function compactarForcaVendas(string $historico, array $pedidosMonitor = []): string
    {
        if ($historico === '') {
            return $historico;
        }

        $pattern = '/^(ESTORNO\s+)?VENDA\s+(ERP|APP)\s+FV\s+\(([^)]+)\)\s+\[FV-(\d+)\](?:\s+#P\d+)?/u';

        $resultado = preg_replace_callback(
            $pattern,
            static function (array $m) use ($pedidosMonitor): string {
                $estorno = $m[1] ?? '';
                $canal = strtoupper((string) $m[2]);
                $meio = self::formatarMeio((string) $m[3]);
                $orderId = (int) $m[4];
                $numero = ContaReceberPedidoExibicao::numeroDoMapa($pedidosMonitor[$orderId] ?? null)
                    ?? (string) $orderId;
                if ($numero === '') {
                    $numero = '0';
                }
                $prefixo = $canal === 'ERP' ? 'TV' : 'FV';

                return $estorno.$prefixo.' '.$numero.' ('.$meio.')';
            },
            $historico,
            1,
        );

        return is_string($resultado) ? $resultado : $historico;
    }

    /**
     * Meio em caixa-alta, com acentuação usual (POS DÉBITO, etc.).
     */
    public static function formatarMeio(string $meio): string
    {
        $chave = mb_strtoupper(trim($meio), 'UTF-8');
        $chaveAscii = str_replace(
            ['Á', 'À', 'Ã', 'Â', 'É', 'Ê', 'Í', 'Ó', 'Ô', 'Õ', 'Ú', 'Ç'],
            ['A', 'A', 'A', 'A', 'E', 'E', 'I', 'O', 'O', 'O', 'U', 'C'],
            $chave,
        );

        return match ($chaveAscii) {
            'DINHEIRO' => 'DINHEIRO',
            'PIX' => 'PIX',
            'POS DEBITO' => 'POS DÉBITO',
            'POS CREDITO' => 'POS CRÉDITO',
            'CREDIARIO' => 'CREDIÁRIO',
            'BOLETO' => 'BOLETO',
            default => $chave !== '' ? $chave : '—',
        };
    }
}
