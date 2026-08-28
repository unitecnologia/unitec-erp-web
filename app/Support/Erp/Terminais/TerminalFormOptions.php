<?php

namespace App\Support\Erp\Terminais;

final class TerminalFormOptions
{
    /**
     * @return list<string>
     */
    public static function modelosEscPos(): array
    {
        return ['ELGIN', 'BEMATECH', 'EPSON', 'DARUMA', 'SWEDA', 'TANCA'];
    }

    /**
     * @return array<string, string>
     */
    public static function tiposImpressao(): array
    {
        return [
            '0' => 'Pedido A4',
            '1' => 'ESC/POS',
            '2' => 'Gráfico',
            '3' => 'NFC-e - A4',
        ];
    }

    /**
     * Portas fixas (como no Delphi) + impressoras Windows entram como RAW:Nome.
     *
     * @return list<string>
     */
    public static function portasFisicas(): array
    {
        return [
            'COM1', 'COM2', 'COM3', 'COM4',
            'LPT1', 'USB',
            '/Dev/ttyS0', '/Dev/ttyS1',
            '/Dev/USB0', '/Dev/USB1',
        ];
    }

    /**
     * @param  list<string>  $windowsPrinters  Nomes das impressoras instaladas no Windows
     * @return list<string>
     */
    public static function portasImpressora(array $windowsPrinters = []): array
    {
        $raw = [];

        foreach ($windowsPrinters as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $raw[] = 'RAW:'.$name;
        }

        $raw = array_values(array_unique($raw));
        sort($raw, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values(array_unique([...$raw, ...self::portasFisicas()]));
    }

    /** Portas + impressoras Windows detectadas neste PC (RAW:Nome). */
    public static function portasComImpressorasWindows(): array
    {
        return self::portasImpressora(WindowsPrinterEnumerator::names());
    }

    /** Extrai o nome Windows de um caminho RAW:POS-80C → POS-80C */
    public static function windowsPrinterFromPorta(?string $porta): ?string
    {
        $porta = trim((string) $porta);
        if ($porta === '') {
            return null;
        }

        if (preg_match('/^RAW:(.+)$/iu', $porta, $m) !== 1) {
            return null;
        }

        $name = trim($m[1]);

        return $name !== '' ? $name : null;
    }


    /**
     * @return array<string, string>
     */
    public static function tiposOperacaoPadrao(): array
    {
        return [
            'modo_hibrido' => 'Modo Híbrido',
            'nfce_contingencia' => 'NFCe - Contingência',
            'nfce_transmitir' => 'NFCe - Transmitir',
            'pedido_nao_fiscal' => 'Pedido Não Fiscal',
        ];
    }

    public static function normalizeTipoOperacaoPadrao(?string $value): string
    {
        $key = mb_strtolower(trim((string) $value), 'UTF-8');

        $aliases = [
            'nfce' => 'nfce_transmitir',
            'nfe' => 'pedido_nao_fiscal',
            'orcamento' => 'pedido_nao_fiscal',
            'ecf_fiscal_finalizar' => 'pedido_nao_fiscal',
        ];

        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        return array_key_exists($key, self::tiposOperacaoPadrao()) ? $key : 'pedido_nao_fiscal';
    }

    /**
     * @return array<string, string>
     */
    public static function botoesOperacaoPadrao(): array
    {
        return [
            'exibe_f3' => 'Botão F3 — Contingência (NFCe)',
            'exibe_f4' => 'Botão F4 — Transmitir (NFCe)',
            'exibe_f5' => 'Botão F5 — Pedido',
            'exibe_f6' => 'Botão F6 — Finalizar',
        ];
    }

    /**
     * Marcas/drivers de balança serial no terminal (PDV).
     *
     * @return list<string>
     */
    public static function marcasBalancaSerial(): array
    {
        return [
            '',
            'balFilizola',
            'balToledo',
            'balUrano',
        ];
    }

    /**
     * @return list<string>
     */
    public static function portasBalanca(): array
    {
        $portas = [];
        for ($i = 1; $i <= 20; $i++) {
            $portas[] = 'COM'.$i;
        }

        return $portas;
    }

    /**
     * @return list<string>
     */
    public static function velocidadesBalanca(): array
    {
        return ['2400', '4800', '9600', '19200', '38400', '57600', '115200'];
    }

    /**
     * @return list<string>
     */
    public static function dataBitsBalanca(): array
    {
        return ['7', '8'];
    }

    /**
     * @return list<string>
     */
    public static function paridadesBalanca(): array
    {
        return ['None', 'Even', 'Odd'];
    }

    /**
     * @return list<string>
     */
    public static function stopBitsBalanca(): array
    {
        return ['1', '2'];
    }

    /**
     * @return list<string>
     */
    public static function handshakingsBalanca(): array
    {
        return ['None', 'XOnXOff', 'RTS'];
    }

    /**
     * Garante que o valor já gravado apareça no select (não perde dado).
     *
     * @param  list<string>  $options
     * @return list<string>
     */
    public static function withCurrentOption(array $options, ?string $current): array
    {
        $current = trim((string) $current);
        if ($current === '') {
            return array_values($options);
        }

        foreach ($options as $option) {
            if ((string) $option === $current) {
                return array_values($options);
            }
        }

        return array_values([$current, ...$options]);
    }
}
