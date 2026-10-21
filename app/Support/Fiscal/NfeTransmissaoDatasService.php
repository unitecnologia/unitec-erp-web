<?php

namespace App\Support\Fiscal;

use App\Models\Nfe;
use App\Support\Erp\ErpTimezone;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Na primeira transmissão de NF-e modelo 55 em aberto, alinha dhEmi/dhSaiEnt
 * ao momento atual (fuso do estabelecimento) antes de montar/assinar o XML.
 */
final class NfeTransmissaoDatasService
{
    public function sincronizarNaPrimeiraTransmissao(Nfe $nfe): Nfe
    {
        $attrs = $this->calcularAttrs($nfe);

        if ($attrs === null) {
            return $nfe;
        }

        $nfe->update($attrs);

        return $nfe->fresh() ?? $nfe;
    }

    /**
     * @return array{data_emissao: string, hora_emissao: string, data_saida?: string, hora_saida?: string}|null
     */
    public function calcularAttrs(Nfe $nfe, ?CarbonInterface $agora = null): ?array
    {
        if (! $this->deveAtualizar($nfe)) {
            return null;
        }

        $agora = Carbon::parse($agora ?? ErpTimezone::nowLocal())->timezone(ErpTimezone::DEFAULT);
        $hoje = $agora->toDateString();
        $hora = $agora->format('H:i:s');

        $emissaoAnterior = $this->dataCalendario($nfe->data_emissao);
        $saidaAnterior = $this->dataCalendario($nfe->data_saida);
        $saidaProgramada = $saidaAnterior !== null
            && $emissaoAnterior !== null
            && $saidaAnterior !== $emissaoAnterior;

        $attrs = [
            'data_emissao' => $hoje,
            'hora_emissao' => $hora,
        ];

        if (! $saidaProgramada || $saidaAnterior < $hoje) {
            // Padrão: saída no mesmo dia da emissão. Também alinha quando a saída
            // programada ficou no passado após avançar a emissão (evita cStat 506).
            $attrs['data_saida'] = $hoje;
            $attrs['hora_saida'] = $hora;
        } elseif ($saidaAnterior === $hoje && blank($nfe->hora_saida)) {
            $attrs['hora_saida'] = $hora;
        }

        return $attrs;
    }

    public function deveAtualizar(Nfe $nfe): bool
    {
        $modelo = trim((string) ($nfe->modelo ?: '55'));

        if ($modelo !== '55') {
            return false;
        }

        if ($nfe->status !== Nfe::STATUS_ABERTA) {
            return false;
        }

        // Autorizada / processamento já obtido — não alterar identidade fiscal.
        if (filled($nfe->protocolo)) {
            return false;
        }

        // Retransmissão com chave/XML já formados: manter AAMM/cNF e evitar duplicidade.
        if (filled($nfe->chave) || filled($nfe->xml)) {
            return false;
        }

        $tpEmis = (int) ltrim((string) ($nfe->tipo_emissao ?: '1'), '0') ?: 1;

        if ($tpEmis !== 1) {
            return false;
        }

        return true;
    }

    private function dataCalendario(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            // Cast `date` do Eloquent: usar calendário gravado (sem deslocar por fuso).
            return $value->format('Y-m-d');
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        return Carbon::parse($raw, ErpTimezone::DEFAULT)->toDateString();
    }
}
