<?php

namespace App\Support\Erp\Pdv;

use App\Support\Fiscal\FiscalEmitenteUf;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class PdvNfceFiscalMensagens
{
    public const CSTAT_PRAZO_CANCELAMENTO = '501';

    /**
     * @param  string|null  $ufEmitente  Sigla do emitente (SC/PR). Null/vazio preserva mensagens SC (compatibilidade).
     * @return array{titulo: string, corpo: string|null, modal: bool}
     */
    public static function resolver(
        FiscalEngineException $exception,
        string $documento = 'nfce',
        ?string $ufEmitente = null,
    ): array {
        $codigo = $exception->sefazCodigo ?? self::extrairCStatDaMensagem($exception->getMessage());
        $documento = strtolower(trim($documento)) === 'nfe' ? 'nfe' : 'nfce';
        $rotulo = $documento === 'nfe' ? 'NF-e' : 'NFC-e';
        $uf = strtoupper(trim((string) $ufEmitente));
        // Callers legados / testes sem UF: comportamento SC intacto.
        if ($uf === '') {
            $uf = 'SC';
        }

        return match ($codigo) {
            self::CSTAT_PRAZO_CANCELAMENTO, '220' => self::mensagemPrazoCancelamento(
                $documento,
                $rotulo,
                $uf,
                $exception,
            ),
            '539' => [
                'titulo' => "Rejeição: duplicidade de {$rotulo} (número já usado na SEFAZ)",
                'corpo' => $documento === 'nfe'
                    ? 'Este número/série já foi autorizado anteriormente com outra chave. '
                        .'O sistema avançou a numeração desta NF-e — transmita novamente.'
                    : 'Este número/série já foi autorizado anteriormente com outra chave. '
                        .'O sistema avançou a numeração. A venda ficou gravada: transmita na tela NFC-e (F5), '
                        .'que emitirá com novo número.',
                'modal' => false,
            ],
            default => [
                'titulo' => self::tituloAmigavel($exception),
                'corpo' => null,
                'modal' => false,
            ],
        };
    }

    /**
     * @return array{titulo: string, corpo: string|null, modal: bool}
     */
    private static function mensagemPrazoCancelamento(
        string $documento,
        string $rotulo,
        string $uf,
        FiscalEngineException $exception,
    ): array {
        $titulo = "Não é possível cancelar esta {$rotulo}";
        $motivoSefaz = self::motivoSefazPreservado($exception);

        // SC NFC-e e SC NF-e (legado): texto exatamente como antes.
        if ($uf === 'SC') {
            return [
                'titulo' => $titulo,
                'corpo' => "O prazo legal de 30 minutos para o cancelamento direto em Santa Catarina já expirou.\n\n"
                    ."O que fazer agora?\n\n"
                    .'Para regularizar esta operação de forma legal perante a SEFAZ-SC, você deve emitir uma '
                    .'Nota Fiscal de Entrada (Estorno de Venda) utilizando o modelo 55. '
                    .'Consulte sua contabilidade para mais detalhes.',
                'modal' => true,
            ];
        }

        if ($uf === 'PR' && $documento === 'nfce') {
            $corpo = "O prazo legal de 30 minutos para o cancelamento direto da NFC-e no Paraná já expirou.\n\n"
                ."O que fazer agora?\n\n"
                .'Não é possível cancelar esta NFC-e na SEFAZ-PR após esse prazo. '
                .'Se a operação não chegou a ser realizada, a regularização costuma ser feita com NF-e modelo 55 '
                .'de estorno/ajuste (conforme orientação da SEFAZ-PR e da contabilidade). '
                .'Se a mercadoria foi efetivamente devolvida pelo consumidor, o caminho adequado é a nota de devolução — '
                .'não o cancelamento tardio. '
                .'Consulte sua contabilidade antes de emitir qualquer documento.'
                .($motivoSefaz !== '' ? "\n\nRetorno SEFAZ: {$motivoSefaz}" : '');

            return [
                'titulo' => $titulo,
                'corpo' => $corpo,
                'modal' => true,
            ];
        }

        if ($uf === 'PR' && $documento === 'nfe') {
            $corpo = "O prazo legal de 168 horas (7 dias) para o cancelamento da NF-e no Paraná já expirou.\n\n"
                ."O que fazer agora?\n\n"
                .'Não utilize o procedimento de prazo curto da NFC-e. '
                .'A regularização depende do caso (operação não realizada, devolução de mercadoria, carta de correção quando cabível, etc.) '
                .'e deve seguir a orientação da SEFAZ-PR e da contabilidade. '
                .'Não há emissão automática de estorno neste sistema.'
                .($motivoSefaz !== '' ? "\n\nRetorno SEFAZ: {$motivoSefaz}" : '');

            return [
                'titulo' => $titulo,
                'corpo' => $corpo,
                'modal' => true,
            ];
        }

        // Outras UFs (não devem ocorrer no núcleo atual SC/PR): título + motivo SEFAZ.
        return [
            'titulo' => $titulo,
            'corpo' => ($motivoSefaz !== '' ? $motivoSefaz."\n\n" : '')
                .'Consulte a legislação da UF '.FiscalEmitenteUf::sefazOrigemRotulo($uf)
                .' e sua contabilidade para regularizar o documento.',
            'modal' => true,
        ];
    }

    private static function motivoSefazPreservado(FiscalEngineException $exception): string
    {
        $motivo = trim((string) ($exception->sefazMotivo ?: ''));
        if ($motivo === '') {
            $motivo = trim(preg_replace('/\s*\[cStat\s+\d+\]\s*$/', '', $exception->getMessage()) ?? '');
        }

        $codigo = trim((string) ($exception->sefazCodigo ?: ''));
        if ($codigo !== '' && $motivo !== '' && ! str_contains($motivo, 'cStat')) {
            return $motivo.' [cStat '.$codigo.']';
        }

        return $motivo;
    }

    private static function tituloAmigavel(FiscalEngineException $exception): string
    {
        $mensagem = trim($exception->getMessage());

        if ($mensagem === '') {
            return 'Não foi possível concluir a operação fiscal.';
        }

        return preg_replace('/\s*\[cStat\s+\d+\]\s*$/', '', $mensagem) ?? $mensagem;
    }

    private static function extrairCStatDaMensagem(string $message): ?string
    {
        if (preg_match('/\[cStat\s+(\d+)\]/', $message, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
