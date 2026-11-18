<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

use App\Models\CaixaConta;

/**
 * Equivalência segura entre conta do OFX e cadastro BANCO (C2).
 *
 * Ailos (COMPE 085): ACCTID observado como
 *   agência(4) + DV agência(1) + conta(6, zero à esquerda) + DV conta(1)
 * Ex.: 0109-0 / 48346-0 → ACCTID 010900483460
 */
final class OfxContaEquivalence
{
    public const BANK_AILOS = '085';

    /**
     * @return array{status: 'ok'|'incompativel'|'dados_incompletos', mensagem: string}
     */
    public function avaliar(CaixaConta $conta, OfxParseResult $parsed): array
    {
        $erpBanco = $this->digits((string) ($conta->banco_codigo ?? ''));
        $ofxBanco = $this->digits((string) ($parsed->bankId ?? ''));

        if ($erpBanco !== '' && $ofxBanco !== '' && $this->normalizeBank($erpBanco) !== $this->normalizeBank($ofxBanco)) {
            return [
                'status' => 'incompativel',
                'mensagem' => 'Importação bloqueada: o banco do arquivo OFX ('.$ofxBanco.') é diferente do banco da conta selecionada ('.$erpBanco.'). Selecione a conta correta e tente novamente.',
            ];
        }

        if ($erpBanco === '' && $ofxBanco === '') {
            return [
                'status' => 'dados_incompletos',
                'mensagem' => 'O OFX e o cadastro não informaram banco. Não foi possível validar compatibilidade — confira manualmente.',
            ];
        }

        $ofxParts = $this->extrairPartesOfx($parsed, $erpBanco !== '' ? $erpBanco : $ofxBanco);
        $erpParts = $this->extrairPartesErp($conta);

        if ($ofxParts === null) {
            return [
                'status' => 'dados_incompletos',
                'mensagem' => 'O OFX não informou banco/conta de forma reconhecível. Não foi possível validar compatibilidade com a conta selecionada — confira manualmente.',
            ];
        }

        if ($erpParts['conta'] === '') {
            return [
                'status' => 'dados_incompletos',
                'mensagem' => 'A conta selecionada está sem número no cadastro. Compatibilidade do OFX não pôde ser confirmada — confira o cadastro da conta.',
            ];
        }

        if ($ofxParts['conta'] === '') {
            return [
                'status' => 'dados_incompletos',
                'mensagem' => 'O OFX não informou o número da conta. Não foi possível validar compatibilidade — confira manualmente.',
            ];
        }

        // Números principais (sem DV, sem zeros à esquerda).
        if ($this->stripLeadingZeros($ofxParts['conta']) !== $this->stripLeadingZeros($erpParts['conta'])) {
            return [
                'status' => 'incompativel',
                'mensagem' => 'Importação bloqueada: a conta do arquivo OFX ('.$ofxParts['conta'].') é diferente da conta bancária selecionada ('.$erpParts['conta'].'). Selecione a conta correta e tente novamente.',
            ];
        }

        if ($ofxParts['agencia'] !== '' && $erpParts['agencia'] !== ''
            && $this->stripLeadingZeros($ofxParts['agencia']) !== $this->stripLeadingZeros($erpParts['agencia'])) {
            return [
                'status' => 'incompativel',
                'mensagem' => 'Importação bloqueada: a agência do arquivo OFX ('.$ofxParts['agencia'].') é diferente da agência cadastrada ('.$erpParts['agencia'].'). Selecione a conta correta e tente novamente.',
            ];
        }

        // DV: só bloqueia quando ambos informaram e divergem.
        if ($ofxParts['conta_dv'] !== '' && $erpParts['conta_dv'] !== ''
            && $ofxParts['conta_dv'] !== $erpParts['conta_dv']) {
            return [
                'status' => 'incompativel',
                'mensagem' => 'Importação bloqueada: o dígito da conta no OFX ('.$ofxParts['conta_dv'].') diverge do cadastro ('.$erpParts['conta_dv'].').',
            ];
        }

        if ($ofxParts['agencia_dv'] !== '' && $erpParts['agencia_dv'] !== ''
            && $ofxParts['agencia_dv'] !== $erpParts['agencia_dv']) {
            return [
                'status' => 'incompativel',
                'mensagem' => 'Importação bloqueada: o dígito da agência no OFX ('.$ofxParts['agencia_dv'].') diverge do cadastro ('.$erpParts['agencia_dv'].').',
            ];
        }

        $avisos = [];
        if (($ofxParts['conta_dv'] !== '' || $ofxParts['agencia_dv'] !== '')
            && ($erpParts['conta_dv'] === '' || $erpParts['agencia_dv'] === '')) {
            $avisos[] = 'Conta reconhecida, mas o cadastro está sem DV. Preencha o DV da agência/conta para validação completa.';
        }

        if ($ofxParts['agencia'] === '' && $erpParts['agencia'] !== '') {
            $avisos[] = 'O OFX não informou agência; a equivalência foi feita apenas pelo número da conta.';
        }

        // Equivalência confirmada (números batem). Avisos não bloqueiam a importação.
        return [
            'status' => 'ok',
            'mensagem' => $avisos === [] ? '' : implode(' ', $avisos),
        ];
    }

    /**
     * @return array{agencia: string, agencia_dv: string, conta: string, conta_dv: string}|null
     */
    private function extrairPartesOfx(OfxParseResult $parsed, string $bankCode): ?array
    {
        $acctId = $this->digits((string) ($parsed->accountId ?? ''));
        $branchId = $this->digits((string) ($parsed->branchId ?? ''));

        if ($acctId === '' && $branchId === '') {
            return null;
        }

        if ($this->normalizeBank($bankCode) === self::BANK_AILOS) {
            $ailos = $this->parseAilosAcctId($acctId, $branchId);
            if ($ailos !== null) {
                return $ailos;
            }
        }

        // Demais bancos / fallback: ACCTID = conta (com ou sem DV no final não estruturado).
        return [
            'agencia' => $branchId,
            'agencia_dv' => '',
            'conta' => $acctId,
            'conta_dv' => '',
        ];
    }

    /**
     * Formato comprovado Ailos (085): AAAADCCCCCCD
     * Ex.: agência 0109-0, conta 48346-0 → 010900483460
     *
     * @return array{agencia: string, agencia_dv: string, conta: string, conta_dv: string}|null
     */
    private function parseAilosAcctId(string $acctId, string $branchId): ?array
    {
        // BRANCHID = agência e ACCTID = conta (quando o banco separa; sem assumir DV no final).
        if ($branchId !== '' && $acctId !== '' && strlen($acctId) < 12) {
            return [
                'agencia' => $branchId,
                'agencia_dv' => '',
                'conta' => $acctId,
                'conta_dv' => '',
            ];
        }

        // ACCTID composto 12 dígitos (formato Ailos comprovado): ag(4)+agDv(1)+conta(6)+contaDv(1).
        // Ex.: 010900483460. O ACCTID é a identidade; BRANCHID, se divergir, é ignorado.
        if (strlen($acctId) === 12 && ctype_digit($acctId)) {
            $agencia = substr($acctId, 0, 4);
            $agenciaDv = substr($acctId, 4, 1);
            $conta = substr($acctId, 5, 6);
            $contaDv = substr($acctId, 11, 1);

            return [
                'agencia' => $agencia,
                'agencia_dv' => $agenciaDv,
                'conta' => $conta,
                'conta_dv' => $contaDv,
            ];
        }

        return null;
    }

    /**
     * @return array{agencia: string, agencia_dv: string, conta: string, conta_dv: string}
     */
    private function extrairPartesErp(CaixaConta $conta): array
    {
        return [
            'agencia' => $this->digits((string) ($conta->agencia ?? '')),
            'agencia_dv' => $this->digits((string) ($conta->agencia_dv ?? '')),
            'conta' => $this->digits((string) ($conta->conta_numero ?? '')),
            'conta_dv' => $this->digits((string) ($conta->conta_dv ?? '')),
        ];
    }

    private function normalizeBank(string $bank): string
    {
        $bank = $this->digits($bank);

        return $bank === '' ? '' : str_pad($bank, 3, '0', STR_PAD_LEFT);
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function stripLeadingZeros(string $digits): string
    {
        $trimmed = ltrim($digits, '0');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
