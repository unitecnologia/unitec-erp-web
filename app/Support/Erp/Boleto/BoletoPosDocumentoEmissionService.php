<?php

namespace App\Support\Erp\Boleto;

use App\Models\Boleto;
use App\Models\BoletoContaApi;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Support\Erp\ErpContext;

/**
 * Emite boletos para Contas a Receber recém-criadas por um documento
 * (PDV / FV / OS), reusando o dispatcher das Contas a Receber.
 */
final class BoletoPosDocumentoEmissionService
{
    public function __construct(
        private readonly BoletoContaApiResolver $resolver,
        private readonly BoletoEmissionDispatcher $dispatcher,
    ) {}

    /**
     * @param  list<int|ContaReceber>  $contas
     * @return list<ContaReceber>
     */
    public function filtrarBoletos(array $contas): array
    {
        $ids = [];
        $models = [];

        foreach ($contas as $item) {
            if ($item instanceof ContaReceber) {
                $models[] = $item;
                continue;
            }

            $id = (int) $item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids !== []) {
            $loaded = ContaReceber::query()
                ->with('cliente')
                ->whereIn('id', $ids)
                ->get();
            foreach ($loaded as $conta) {
                $models[] = $conta;
            }
        }

        return collect($models)
            ->unique(fn (ContaReceber $c): int => (int) $c->id)
            ->filter(function (ContaReceber $conta): bool {
                if ((string) ($conta->forma ?? '') !== ContaReceber::FORMA_BOLETO) {
                    return false;
                }

                return (float) ($conta->saldo ?? $conta->valor ?? 0) > 0.009;
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<ContaReceber|int>  $contas
     * @return array{boletos: list<Boleto>, erros: list<string>}
     */
    public function emitirVarias(array $contas, BoletoContaApi $contaApi, ?Empresa $empresa = null): array
    {
        $boletos = [];
        $erros = [];

        foreach ($this->filtrarBoletos($contas) as $conta) {
            $existente = Boleto::query()
                ->where('conta_receber_id', $conta->id)
                ->where('status', Boleto::STATUS_ABERTO)
                ->whereNotNull('linha_digitavel')
                ->where('linha_digitavel', '!=', '')
                ->orderByDesc('id')
                ->first();

            if ($existente instanceof Boleto) {
                $boletos[] = $existente;

                continue;
            }

            try {
                $empresaConta = $empresa
                    ?? ($conta->empresa_id ? Empresa::query()->find($conta->empresa_id) : null)
                    ?? ErpContext::currentEmpresa();

                if (! $empresaConta instanceof Empresa) {
                    throw new \RuntimeException('Empresa não encontrada para a conta #'.$conta->numero);
                }

                $ativa = $this->resolver->findAtiva($empresaConta, (int) $contaApi->id);
                $boletos[] = $this->dispatcher->emitirParaContaReceber($conta, $ativa, $empresaConta);
            } catch (\Throwable $e) {
                $erros[] = 'Conta #'.($conta->numero ?? $conta->id).': '.$e->getMessage();
            }
        }

        return ['boletos' => $boletos, 'erros' => $erros];
    }
}
