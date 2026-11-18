<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

/**
 * Resumo exibido após importar OFX (C2).
 */
final class OfxImportResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly int $importacaoId,
        public readonly string $status,
        public readonly int $lidos,
        public readonly int $importados,
        public readonly int $jaExistentes,
        public readonly int $erros,
        public readonly int $ambiguos,
        public readonly string $totalEntradas,
        public readonly string $totalSaidas,
        public readonly bool $alertaContaIncompativel,
        public readonly bool $reimportacaoIdempotente,
        public readonly array $warnings = [],
        public readonly ?string $mensagem = null,
        public readonly ?string $periodoDe = null,
        public readonly ?string $periodoAte = null,
    ) {}

    public function tituloNotificacao(): string
    {
        if ($this->reimportacaoIdempotente) {
            return 'Arquivo OFX já importado anteriormente.';
        }

        return match ($this->status) {
            'concluida' => 'Importação OFX concluída.',
            'erro' => 'Importação OFX com falha.',
            default => 'Importação OFX.',
        };
    }

    public function corpoNotificacao(): string
    {
        $linhas = [
            "Lidos: {$this->lidos}",
            "Importados: {$this->importados}",
            "Já existentes: {$this->jaExistentes}",
            "Erros/ambiguidade: {$this->erros} / {$this->ambiguos}",
            'Entradas: R$ '.number_format((float) $this->totalEntradas, 2, ',', '.'),
            'Saídas: R$ '.number_format((float) $this->totalSaidas, 2, ',', '.'),
        ];

        if ($this->periodoDe && $this->periodoAte) {
            $linhas[] = 'Período do extrato: '
                .$this->formatBr($this->periodoDe).' a '.$this->formatBr($this->periodoAte);
        }

        if ($this->alertaContaIncompativel) {
            $linhas[] = 'Conta do OFX incompatível com a selecionada.';
        }

        if ($this->mensagem) {
            $linhas[] = $this->mensagem;
        }

        foreach (array_slice($this->warnings, 0, 3) as $w) {
            $linhas[] = $w;
        }

        return implode("\n", $linhas);
    }

    private function formatBr(string $iso): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
            return $iso;
        }

        return $m[3].'/'.$m[2].'/'.$m[1];
    }
}
