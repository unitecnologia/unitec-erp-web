<?php

namespace App\Support\Fiscal;

/**
 * Resultado da conferência produto + pagamento no F4 Recuperar.
 *
 * @phpstan-type Diff array{
 *     escopo: string,
 *     campo: string,
 *     item?: int|null,
 *     pagamento_id?: int|null,
 *     antes: string,
 *     depois: string,
 *     acao: string,
 *     mensagem: string
 * }
 */
final class NfceRecuperarConferenciaResultado
{
    /** @param list<Diff> $diferencas */
    public function __construct(
        public readonly bool $executada,
        public readonly bool $aplicouAtualizacoes,
        public readonly string $status,
        public readonly string $motivoPula,
        public readonly array $diferencas,
        public readonly int $atualizacoesAplicadas,
        public readonly int $divergenciasPendentes,
    ) {}

    public static function pulada(string $status, string $motivo): self
    {
        return new self(
            executada: false,
            aplicouAtualizacoes: false,
            status: $status,
            motivoPula: $motivo,
            diferencas: [],
            atualizacoesAplicadas: 0,
            divergenciasPendentes: 0,
        );
    }

    public function temAlteracoesOuAvisos(): bool
    {
        return $this->diferencas !== [] || ($this->motivoPula !== '' && $this->executada);
    }

    public function exigeIntervencao(): bool
    {
        return $this->divergenciasPendentes > 0;
    }

    /** Texto curto para Notification Filament — separa corrigido × intervenção × próximo XML. */
    public function resumoNotificacao(int $maxLinhas = 10): string
    {
        if (! $this->executada) {
            return $this->motivoPula !== '' ? $this->motivoPula : '';
        }

        $blocos = [];
        if ($this->motivoPula !== '') {
            $blocos[] = $this->motivoPula;
        }

        if ($this->diferencas === []) {
            $blocos[] = 'Conferência OK: produtos e meios de pagamento alinhados ao cadastro.';

            return implode("\n", $blocos);
        }

        $corrigidos = [];
        $intervencao = [];
        $proximoXml = [];

        foreach ($this->diferencas as $diff) {
            $msg = trim((string) ($diff['mensagem'] ?? ''));
            if ($msg === '') {
                continue;
            }
            match ($diff['acao'] ?? '') {
                'aplicado', 'aplicavel' => $corrigidos[] = $msg,
                'pendente' => $intervencao[] = $msg,
                default => $proximoXml[] = $msg,
            };
        }

        $linhasUsadas = 0;
        $appendSecao = static function (string $titulo, array $msgs) use (&$blocos, &$linhasUsadas, $maxLinhas): void {
            if ($msgs === [] || $linhasUsadas >= $maxLinhas) {
                return;
            }
            $restante = $maxLinhas - $linhasUsadas;
            $slice = array_slice($msgs, 0, $restante);
            $blocos[] = $titulo;
            foreach ($slice as $m) {
                $blocos[] = '• '.$m;
                $linhasUsadas++;
            }
            $omitidos = count($msgs) - count($slice);
            if ($omitidos > 0) {
                $blocos[] = '• ... +'.$omitidos.' item(ns)';
            }
        };

        $appendSecao('CORRIGIDO agora:', $corrigidos);
        $appendSecao('EXIGE INTERVENÇÃO (não alterado automaticamente):', $intervencao);
        $appendSecao('No próximo F5 (XML), sem mudar valores comerciais:', $proximoXml);

        $rodape = [];
        if ($this->atualizacoesAplicadas > 0) {
            $rodape[] = $this->atualizacoesAplicadas.' correção(ões) gravada(s)';
        }
        if ($this->divergenciasPendentes > 0) {
            $rodape[] = $this->divergenciasPendentes.' pendência(s) de decisão';
        }
        if ($rodape !== []) {
            $blocos[] = '('.implode('; ', $rodape).')';
        }

        return implode("\n", $blocos);
    }
}
