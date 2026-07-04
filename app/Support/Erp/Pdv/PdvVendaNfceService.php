<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;

final class PdvVendaNfceService
{
    public function __construct(
        private readonly PdvNfceSimuladaService $simulada = new PdvNfceSimuladaService(),
    ) {}

    public function registrarSimulada(PdvVenda $venda, ?Empresa $empresa, string $operacao): PdvVendaNfce
    {
        $parametros = $empresa?->id
            ? VendasParametro::forEmpresa((int) $empresa->id)
            : null;

        $serie = str_pad((string) ($parametros?->serie ?? '1'), 3, '0', STR_PAD_LEFT);
        $numeroNfce = $parametros?->peekNumero() ?? (int) $venda->numero;
        $tipoEmissao = $operacao === PdvFinalizarOperacao::NFCE_CONTINGENCIA ? '9' : '1';
        $chave = $this->simulada->gerarChaveAcesso($empresa, $venda, $operacao);
        $protocolo = $this->simulada->gerarProtocolo($venda);
        $cnf = substr($chave, 35, 8);

        $status = $operacao === PdvFinalizarOperacao::NFCE_CONTINGENCIA
            ? PdvVendaNfce::STATUS_CONTINGENCIA
            : PdvVendaNfce::STATUS_AUTORIZADA;

        return PdvVendaNfce::query()->create([
            'pdv_venda_id' => $venda->id,
            'empresa_id' => $empresa?->id,
            'operacao' => $operacao,
            'modelo' => '65',
            'serie' => ltrim($serie, '0') ?: '1',
            'numero' => $numeroNfce,
            'cnf' => $cnf,
            'chave' => $chave,
            'protocolo' => $protocolo,
            'status' => $status,
            'ambiente' => PdvVendaNfce::AMBIENTE_HOMOLOGACAO,
            'tipo_emissao' => $tipoEmissao,
            'simulada' => true,
            'qr_code_conteudo' => $this->simulada->gerarQrTexto($chave, $operacao),
            'autorizada_em' => $venda->fechado_em ?? now(),
        ]);
    }
}
