<?php

namespace App\Support\Erp\Tef;

use App\Models\CaixaConta;
use App\Models\TefLiquidacaoDestino;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve a conta BANCO prevista para liquidação TEF (C5B.1).
 * Somente configuração — não liquida, não lança caixa, não altera TEF/PDV.
 *
 * Escopo sempre por provedor explícito (supertef|scope).
 * Consulta Scope NUNCA faz fallback para destino SuperTEF (e vice-versa).
 *
 * Prioridade determinística dentro do provedor:
 * 1) empresa + terminal + provedor + CNPJ adquirente (quando informado)
 * 2) empresa + terminal + provedor + CNPJ vazio (padrão do terminal)
 * Se >1 ativo elegível no mesmo nível → ambíguo (sem escolha automática).
 */
final class TefLiquidacaoDestinoResolver
{
    public function resolve(
        int $empresaId,
        int $terminalId,
        ?string $acquirerCnpj = null,
        ?string $provedor = null,
    ): TefLiquidacaoDestinoResolveResult {
        if ($empresaId <= 0 || $terminalId <= 0) {
            return new TefLiquidacaoDestinoResolveResult(
                TefLiquidacaoDestinoResolveResult::STATUS_NAO_ENCONTRADO,
                motivo: 'Empresa ou terminal inválido.',
            );
        }

        if (! Schema::hasTable('tef_liquidacao_destinos')) {
            return new TefLiquidacaoDestinoResolveResult(
                TefLiquidacaoDestinoResolveResult::STATUS_NAO_ENCONTRADO,
                motivo: 'Estrutura de destinos não disponível.',
            );
        }

        $provedorId = $this->normalizeProvedorStrict($provedor);
        $cnpj = TefLiquidacaoDestino::normalizeAcquirerCnpj($acquirerCnpj);

        if ($cnpj !== '') {
            $exatos = $this->buscarAtivos($empresaId, $terminalId, $provedorId, $cnpj);
            $avaliado = $this->avaliarCandidatos($exatos);
            if ($avaliado->status !== TefLiquidacaoDestinoResolveResult::STATUS_NAO_ENCONTRADO) {
                return $avaliado;
            }
        }

        $padrao = $this->buscarAtivos($empresaId, $terminalId, $provedorId, '');

        return $this->avaliarCandidatos($padrao);
    }

    /**
     * Normaliza provedor sem promover Scope→SuperTEF nem o inverso.
     * null/vazio → SuperTEF (compatível com chamadas C5B.1/C5B.2 atuais).
     */
    private function normalizeProvedorStrict(?string $provedor): string
    {
        $raw = strtolower(trim((string) $provedor));
        if ($raw === '' || $raw === TefProviderId::SUPERTEF) {
            return TefProviderId::SUPERTEF;
        }
        if ($raw === TefProviderId::SCOPE) {
            return TefProviderId::SCOPE;
        }

        // Desconhecido: não misturar com SuperTEF — trata como SuperTEF só se era o default legado.
        return TefProviderId::normalize($raw) === TefProviderId::SCOPE
            ? TefProviderId::SCOPE
            : TefProviderId::SUPERTEF;
    }

    /**
     * @return Collection<int, TefLiquidacaoDestino>
     */
    private function buscarAtivos(int $empresaId, int $terminalId, string $provedor, string $cnpj): Collection
    {
        $query = TefLiquidacaoDestino::query()
            ->with(['caixaConta:id,empresa_id,nome,tipo,ativo'])
            ->where('empresa_id', $empresaId)
            ->where('terminal_id', $terminalId)
            ->where('acquirer_cnpj', $cnpj)
            ->where('ativo', true);

        if (Schema::hasColumn('tef_liquidacao_destinos', 'provedor')) {
            $query->where('provedor', $provedor);
        } elseif ($provedor !== TefProviderId::SUPERTEF) {
            // Schema legado sem coluna: só SuperTEF existia — Scope não encontra nada.
            return collect();
        }

        return $query
            ->orderBy('id')
            ->limit(5)
            ->get();
    }

    /**
     * @param  Collection<int, TefLiquidacaoDestino>  $candidatos
     */
    private function avaliarCandidatos(Collection $candidatos): TefLiquidacaoDestinoResolveResult
    {
        if ($candidatos->isEmpty()) {
            return new TefLiquidacaoDestinoResolveResult(
                TefLiquidacaoDestinoResolveResult::STATUS_NAO_ENCONTRADO,
                motivo: 'Nenhuma configuração ativa encontrada.',
            );
        }

        $validos = $candidatos->filter(fn (TefLiquidacaoDestino $d): bool => $this->contaElegivel($d))->values();

        if ($validos->isEmpty()) {
            return new TefLiquidacaoDestinoResolveResult(
                TefLiquidacaoDestinoResolveResult::STATUS_CONTA_INVALIDA,
                candidatos: $candidatos->all(),
                motivo: 'Conta BANCO inativa, inexistente ou de outra empresa.',
            );
        }

        if ($validos->count() > 1) {
            return new TefLiquidacaoDestinoResolveResult(
                TefLiquidacaoDestinoResolveResult::STATUS_AMBIGUO,
                candidatos: $validos->all(),
                motivo: 'Mais de uma configuração ativa elegível — escolha manual necessária.',
            );
        }

        /** @var TefLiquidacaoDestino $unico */
        $unico = $validos->first();

        return new TefLiquidacaoDestinoResolveResult(
            TefLiquidacaoDestinoResolveResult::STATUS_OK,
            destino: $unico,
        );
    }

    private function contaElegivel(TefLiquidacaoDestino $destino): bool
    {
        $conta = $destino->caixaConta;
        if (! $conta) {
            $conta = CaixaConta::query()->whereKey((int) $destino->caixa_conta_id)->first();
        }

        if (! $conta || ! $conta->ativo) {
            return false;
        }

        if ((string) $conta->tipo !== CaixaConta::TIPO_BANCO) {
            return false;
        }

        return CaixaConta::query()
            ->whereKey((int) $conta->id)
            ->where('ativo', true)
            ->where('tipo', CaixaConta::TIPO_BANCO)
            ->visiveisNaEmpresa((int) $destino->empresa_id)
            ->exists();
    }
}
