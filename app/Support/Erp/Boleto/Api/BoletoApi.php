<?php

namespace App\Support\Erp\Boleto\Api;

use App\Models\Boleto;
use App\Models\Empresa;
use App\Support\Erp\ErpContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Orquestra operações de boleto via API do banco da empresa (COMPE).
 * Sem driver = só ERP (comportamento legado).
 */
final class BoletoApi
{
    /**
     * @param  list<BoletoApiDriver>  $drivers
     */
    public function __construct(private readonly array $drivers)
    {
    }

    public function driverFor(?Empresa $empresa): ?BoletoApiDriver
    {
        if (! $empresa instanceof Empresa) {
            return null;
        }

        foreach ($this->drivers as $driver) {
            if ($driver->supports($empresa)) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * Baixa na API (se houver driver) todos os boletos abertos da conta a receber.
     * Falhas por boleto são logadas e não propagam (ex.: pós-Pix).
     *
     * @return list<int> IDs de boletos marcados como baixados no ERP
     */
    public function baixarAbertosDaContaReceber(int $contaReceberId, ?Empresa $empresa = null): array
    {
        $boletos = Boleto::query()
            ->where('conta_receber_id', $contaReceberId)
            ->where('status', Boleto::STATUS_ABERTO)
            ->with('boletoContaApi')
            ->orderBy('id')
            ->get();

        if ($boletos->isEmpty()) {
            return [];
        }

        $baixados = [];

        foreach ($boletos as $boleto) {
            try {
                $empresaBoleto = $this->resolverEmpresa($boleto, $empresa);
                $driver = $this->driverFor($empresaBoleto);
                if ($driver === null) {
                    continue;
                }

                if ($driver->baixar($boleto, $empresaBoleto)) {
                    $baixados[] = (int) $boleto->id;
                }
            } catch (Throwable $e) {
                Log::warning('Boleto API: falha ao baixar boleto após Pix/título.', [
                    'boleto_id' => $boleto->id,
                    'conta_receber_id' => $contaReceberId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $baixados;
    }

    /**
     * Altera vencimento na API de todos os boletos abertos com driver.
     * Sem boleto aberto ou sem driver: no-op (retorna 0).
     * Qualquer falha propaga (para o Alterar não divergir ERP x banco).
     *
     * @return int Quantidade de boletos com instrução enviada ao banco
     *
     * @throws RuntimeException
     */
    public function alterarVencimentoAbertosDaContaReceber(
        int $contaReceberId,
        CarbonInterface $novoVencimento,
        ?Empresa $empresa = null,
    ): int {
        $boletos = Boleto::query()
            ->where('conta_receber_id', $contaReceberId)
            ->where('status', Boleto::STATUS_ABERTO)
            ->with('boletoContaApi')
            ->orderBy('id')
            ->get();

        if ($boletos->isEmpty()) {
            return 0;
        }

        $sincronizados = 0;

        foreach ($boletos as $boleto) {
            $empresaBoleto = $this->resolverEmpresa($boleto, $empresa);
            $driver = $this->driverFor($empresaBoleto);
            if ($driver === null) {
                continue;
            }

            $driver->alterarVencimento($boleto, $novoVencimento, $empresaBoleto);
            $sincronizados++;
        }

        return $sincronizados;
    }

    /**
     * Baixa um boleto e só considera sucesso com confirmação do banco.
     * Sem driver (API desligada ou banco sem integração) lança erro — não altera o título.
     *
     * @throws RuntimeException
     */
    public function baixarComConfirmacao(Boleto $boleto, ?Empresa $empresa = null): void
    {
        $empresaBoleto = $this->resolverEmpresa($boleto, $empresa);
        $driver = $this->driverFor($empresaBoleto);

        if ($driver === null) {
            throw new RuntimeException(
                'API de boleto desabilitada ou configuração incompleta. O pedido não foi cancelado.'
            );
        }

        if (! $driver->baixarComConfirmacao($boleto, $empresaBoleto)) {
            throw new RuntimeException(
                'O banco não confirmou a baixa do boleto. O pedido não foi cancelado.'
            );
        }
    }

    private function resolverEmpresa(Boleto $boleto, ?Empresa $empresa): ?Empresa
    {
        $base = $empresa instanceof Empresa
            ? $empresa
            : ($boleto->empresa_id
                ? Empresa::query()->find($boleto->empresa_id)
                : ErpContext::currentEmpresa());

        if (! $base instanceof Empresa) {
            return null;
        }

        if ($boleto->boleto_conta_api_id) {
            $conta = $boleto->relationLoaded('boletoContaApi')
                ? $boleto->boletoContaApi
                : \App\Models\BoletoContaApi::query()->find($boleto->boleto_conta_api_id);

            if ($conta instanceof \App\Models\BoletoContaApi) {
                return $conta->asEmpresaOverlay($base);
            }
        }

        return $base;
    }
}
