<?php

namespace App\Support\Erp\Fiscal;

/**
 * Mensagens fiscais legais obrigatórias (NF-e / NFC-e).
 *
 * Fonte única para XML (infCpl) e DANFE/cupom — não depende de digitação do usuário.
 * Não calcula impostos: só monta texto a partir de CRT/modelo e de pCredSN/vCredICMSSN já existentes.
 */
final class FiscalMensagensLegais
{
    public const MODELO_NFE = '55';

    public const MODELO_NFCE = '65';

    public const SIMPLES_ME_EPP = 'DOCUMENTO EMITIDO POR ME OU EPP OPTANTE PELO SIMPLES NACIONAL';

    public const SIMPLES_SEM_CREDITO_IPI = 'NÃO GERA DIREITO A CRÉDITO FISCAL DE IPI';

    /**
     * Resolução CGSN nº 140/2018, art. 59, § 5º, II, “a”.
     */
    public const SIMPLES_EXCESSO_SUBLIMITE = 'ESTABELECIMENTO IMPEDIDO DE RECOLHER O ICMS/ISS PELO SIMPLES NACIONAL, NOS TERMOS DO § 1º DO ART. 20 DA LEI COMPLEMENTAR Nº 123, DE 2006';

    public const NFCE_SEM_APROVEITAMENTO_ICMS = 'NÃO PERMITE APROVEITAMENTO DE CRÉDITO DE ICMS';

    /**
     * @param  array{
     *     modelo: string,
     *     crt: int,
     *     p_cred_sn?: float|null,
     *     v_cred_icms_sn?: float|null,
     * }  $contexto
     * @return list<string>
     */
    public function mensagens(array $contexto): array
    {
        $modelo = $this->normalizeModelo((string) ($contexto['modelo'] ?? ''));
        $crt = (int) ($contexto['crt'] ?? 0);
        $mensagens = [];

        if ($crt === 1) {
            $mensagens[] = self::SIMPLES_ME_EPP;
            $mensagens[] = self::SIMPLES_SEM_CREDITO_IPI;

            if ($modelo === self::MODELO_NFE) {
                $credito = $this->mensagemCreditoIcmsSn(
                    $contexto['p_cred_sn'] ?? null,
                    $contexto['v_cred_icms_sn'] ?? null,
                );

                if ($credito !== null) {
                    $mensagens[] = $credito;
                }
            }
        }

        // CRT 2: Resolução CGSN 140/2018, art. 59, § 5º (não usa texto de CRT 1).
        if ($crt === 2) {
            $mensagens[] = self::SIMPLES_EXCESSO_SUBLIMITE;
            $mensagens[] = self::SIMPLES_SEM_CREDITO_IPI;
        }

        // CRT 4 (MEI/SIMEI): não usa o texto de ME/EPP (CRT 1).
        // Art. 59 da CGSN 140/2018 trata de ME ou EPP; sem redação oficial
        // específica equivalente aqui, não inventamos mensagem para MEI.

        return $this->uniquePreserveOrder($mensagens);
    }

    /**
     * Faixa fixa obrigatória do DANFE NFC-e (modelo 65), independente do CRT.
     *
     * @param  int|null  $crt  Mantido por compatibilidade; não condiciona a faixa.
     */
    public function mensagemFormaDanfeNfce(?int $crt = null): string
    {
        return self::NFCE_SEM_APROVEITAMENTO_ICMS;
    }

    /**
     * Junta texto livre do usuário + mensagens legais (+ opcional IBPT), sem duplicar.
     * O bloco legal sempre é reaplicado; trechos equivalentes no texto do usuário são removidos.
     *
     * @param  list<string>  $mensagensLegais
     */
    public function comporInfCpl(string $textoUsuario, array $mensagensLegais, string $textoIbpt = ''): string
    {
        $usuario = $this->stripMensagensLegaisDoTexto(trim($textoUsuario), $mensagensLegais);
        $legais = $this->uniquePreserveOrder(array_values(array_filter(
            array_map(static fn (string $m): string => trim($m), $mensagensLegais),
            static fn (string $m): bool => $m !== '',
        )));
        $ibpt = trim($textoIbpt);

        if ($ibpt !== '' && $this->textoContemIbpt($usuario)) {
            $ibpt = '';
        }

        $partes = [];

        if ($usuario !== '') {
            $partes[] = $usuario;
        }

        if ($legais !== []) {
            $partes[] = implode(' ', $legais);
        }

        if ($ibpt !== '') {
            $partes[] = $ibpt;
        }

        return trim(implode(' ', $partes));
    }

    /**
     * Soma crédito SN já calculado nos itens (não inventa alíquota/valor).
     *
     * Aceita chaves: p_cred_sn / pCredSN / p_credicmssn e v_cred_icms_sn / vCredICMSSN / v_credicmssn.
     *
     * @param  iterable<int, object|array<string, mixed>>  $itens
     * @return array{p_cred_sn: float|null, v_cred_icms_sn: float}
     */
    public function resumirCreditoSnDosItens(iterable $itens): array
    {
        $vTotal = 0.0;
        $pUsado = null;

        foreach ($itens as $item) {
            $row = $this->normalizeItemRow($item);
            $v = $this->floatFromRow($row, ['v_cred_icms_sn', 'vCredICMSSN', 'v_credicmssn', 'vcredicmssn']);
            $p = $this->floatFromRow($row, ['p_cred_sn', 'pCredSN', 'p_credicmssn', 'pcredicmssn']);

            if ($v === null || $v <= 0) {
                continue;
            }

            $vTotal = round($vTotal + $v, 2);

            if ($p !== null && $p > 0) {
                $pUsado = $pUsado === null ? $p : $pUsado;
                // Se alíquotas divergirem entre itens, mantém a primeira > 0 (mensagem usa um pCred).
            }
        }

        if ($vTotal <= 0 || $pUsado === null || $pUsado <= 0) {
            return ['p_cred_sn' => null, 'v_cred_icms_sn' => 0.0];
        }

        return ['p_cred_sn' => round($pUsado, 4), 'v_cred_icms_sn' => $vTotal];
    }

    public function mensagemCreditoIcmsSn(mixed $pCredSn, mixed $vCredIcmsSn): ?string
    {
        $p = is_numeric($pCredSn) ? (float) $pCredSn : null;
        $v = is_numeric($vCredIcmsSn) ? (float) $vCredIcmsSn : null;

        if ($p === null || $v === null || $p <= 0 || $v <= 0) {
            return null;
        }

        return sprintf(
            'PERMITE O APROVEITAMENTO DO CRÉDITO DE ICMS NO VALOR DE R$ %s; CORRESPONDENTE À ALÍQUOTA DE %s%%, NOS TERMOS DO ART. 23 DA LEI COMPLEMENTAR Nº 123, DE 2006.',
            number_format($v, 2, ',', '.'),
            $this->formatAliquota($p),
        );
    }

    /**
     * @param  list<string>  $mensagensLegais
     */
    private function stripMensagensLegaisDoTexto(string $texto, array $mensagensLegais): string
    {
        if ($texto === '' || $mensagensLegais === []) {
            return $texto;
        }

        $resultado = $texto;

        foreach ($mensagensLegais as $mensagem) {
            $mensagem = trim($mensagem);

            if ($mensagem === '') {
                continue;
            }

            $resultado = preg_replace(
                '/\s*'.preg_quote($mensagem, '/').'\s*/iu',
                ' ',
                $resultado,
            ) ?? $resultado;
        }

        // Remove também a forma canônica do crédito SN (valores variáveis).
        $resultado = preg_replace(
            '/\s*PERMITE O APROVEITAMENTO DO CRÉDITO DE ICMS NO VALOR DE R\$\s*[\d.,]+;\s*CORRESPONDENTE À ALÍQUOTA DE [\d.,]+%\s*,\s*NOS TERMOS DO ART\.\s*23 DA LEI COMPLEMENTAR Nº\s*123,\s*DE 2006\.\s*/iu',
            ' ',
            $resultado,
        ) ?? $resultado;

        return trim(preg_replace('/\s{2,}/u', ' ', $resultado) ?? $resultado);
    }

    private function textoContemIbpt(string $texto): bool
    {
        return str_contains($texto, 'Lei 12.741') || str_contains($texto, 'Trib. aprox.');
    }

    private function normalizeModelo(string $modelo): string
    {
        $digits = preg_replace('/\D/', '', $modelo) ?? '';

        return match ($digits) {
            '65' => self::MODELO_NFCE,
            default => self::MODELO_NFE,
        };
    }

    private function formatAliquota(float $p): string
    {
        $formatted = number_format($p, 4, ',', '.');
        $formatted = rtrim(rtrim($formatted, '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * @param  list<string>  $mensagens
     * @return list<string>
     */
    private function uniquePreserveOrder(array $mensagens): array
    {
        $seen = [];
        $out = [];

        foreach ($mensagens as $mensagem) {
            $mensagem = trim($mensagem);

            if ($mensagem === '') {
                continue;
            }

            $key = mb_strtoupper($mensagem, 'UTF-8');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $mensagem;
        }

        return $out;
    }

    /**
     * @param  object|array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItemRow(object|array $item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if (isset($item->imposto) && is_object($item->imposto)) {
            $imp = $item->imposto;

            return [
                'p_cred_sn' => $imp->pCredSn ?? $imp->p_cred_sn ?? null,
                'v_cred_icms_sn' => $imp->vCredIcmsSn ?? $imp->v_cred_icms_sn ?? null,
            ];
        }

        if (method_exists($item, 'toArray')) {
            /** @var array<string, mixed> $arr */
            $arr = $item->toArray();

            return $arr;
        }

        return [
            'p_cred_sn' => $item->p_cred_sn ?? $item->pCredSN ?? null,
            'v_cred_icms_sn' => $item->v_cred_icms_sn ?? $item->vCredICMSSN ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function floatFromRow(array $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                continue;
            }

            if (! is_numeric($row[$key])) {
                continue;
            }

            return (float) $row[$key];
        }

        return null;
    }
}
