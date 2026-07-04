<?php

namespace App\Support\Erp\Pdv;

use App\Models\ContaReceber;
use App\Models\Person;
use App\Support\Erp\ErpMoney;

final class PdvClienteLimiteService
{
    public function saldoEmAberto(int $personId): float
    {
        return round((float) ContaReceber::query()
            ->where('cliente_id', $personId)
            ->sum('saldo'), 2);
    }

    public function limiteCredito(Person $person): float
    {
        return round((float) ($person->limite_credito ?? 0), 2);
    }

    public function limiteDisponivel(Person $person, float $vendaAtual = 0): float
    {
        $limite = $this->limiteCredito($person);

        if ($limite <= 0) {
            return 0.0;
        }

        return max(0, round($limite - $this->saldoEmAberto($person->id) - $vendaAtual, 2));
    }

    public function valida(Person $person, float $vendaTotal): ?string
    {
        $limite = $this->limiteCredito($person);

        if ($limite <= 0) {
            return null;
        }

        $saldoAberto = $this->saldoEmAberto($person->id);
        $comprometido = round($saldoAberto + $vendaTotal, 2);

        if ($comprometido > $limite) {
            $disponivel = max(0, round($limite - $saldoAberto, 2));

            return 'Limite de crédito excedido. Limite: R$ '
                . ErpMoney::formatBr($limite)
                . ' | Em aberto: R$ '
                . ErpMoney::formatBr($saldoAberto)
                . ' | Disponível: R$ '
                . ErpMoney::formatBr($disponivel);
        }

        return null;
    }

    /**
     * @return array{limite: string, aberto: string, disponivel: string, venda: string}|null
     */
    public function resumo(int $personId, float $vendaAtual = 0): ?array
    {
        $person = Person::query()->find($personId);

        if (! $person) {
            return null;
        }

        $limite = $this->limiteCredito($person);

        if ($limite <= 0) {
            return null;
        }

        $aberto = $this->saldoEmAberto($personId);
        $disponivel = max(0, round($limite - $aberto - $vendaAtual, 2));

        return [
            'limite' => ErpMoney::formatBr($limite),
            'aberto' => ErpMoney::formatBr($aberto),
            'disponivel' => ErpMoney::formatBr($disponivel),
            'venda' => ErpMoney::formatBr($vendaAtual),
        ];
    }
}
