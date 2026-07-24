<?php

namespace App\Support\Pix;

use App\Models\Empresa;
use App\Models\PixCobranca;
use App\Support\Pix\Contracts\PixProvider;
use App\Support\Pix\Providers\MercadoPagoPixProvider;
use RuntimeException;

/**
 * Resolve o provedor Pix a partir dos parâmetros da empresa
 * (Empresa > Parâmetros > API PIX). Credenciais ficam centralizadas no ERP,
 * com fallback opcional para config/env. A interface PixProvider permite
 * plugar bancos/PSPs depois sem refatorar.
 */
class PixProviderManager
{
    /** Provedor + credenciais conforme os parâmetros da empresa. */
    public function paraEmpresa(?int $empresaId): PixProvider
    {
        return $this->construir($this->empresa($empresaId));
    }

    /** Provedor da cobrança (usa a empresa gravada nela). */
    public function paraCobranca(PixCobranca $cobranca): PixProvider
    {
        return $this->construir($this->empresa($cobranca->empresa_id), $cobranca->provedor);
    }

    /** Nome do provedor configurado para a empresa (sem instanciar). */
    public function provedorDaEmpresa(?int $empresaId): string
    {
        $empresa = $this->empresa($empresaId);

        return (string) ($empresa?->param_pix_provedor ?: 'mercadopago');
    }

    private function empresa(?int $empresaId): ?Empresa
    {
        if ($empresaId !== null) {
            return Empresa::query()->find($empresaId);
        }

        return Empresa::query()->orderBy('id')->first();
    }

    private function construir(?Empresa $empresa, ?string $forcarProvedor = null): PixProvider
    {
        if (! $this->apiHabilitada($empresa)) {
            throw new RuntimeException(
                'API PIX desabilitada nesta empresa. Habilite em Empresa > Parâmetros > Permissões.'
            );
        }

        $provedor = $forcarProvedor ?: (string) ($empresa?->param_pix_provedor ?: 'mercadopago');

        return match ($provedor) {
            'mercadopago' => $this->mercadopago($empresa),
            default => throw new RuntimeException('Provedor Pix não suportado: '.$provedor),
        };
    }

    public function apiHabilitada(?Empresa $empresa): bool
    {
        return (bool) ($empresa?->param_pix_habilitar ?? false);
    }

    public function apiHabilitadaParaEmpresa(?int $empresaId): bool
    {
        return $this->apiHabilitada($this->empresa($empresaId));
    }

    private function mercadopago(?Empresa $empresa): PixProvider
    {
        $token = (string) ($empresa?->param_pix_mp_access_token
            ?: config('services.mercadopago.access_token'));

        if ($token === '') {
            throw new RuntimeException(
                'Token do Mercado Pago não configurado. Preencha em Empresa > Parâmetros > API PIX.'
            );
        }

        return new MercadoPagoPixProvider(
            $token,
            (string) config('services.mercadopago.base_url', 'https://api.mercadopago.com'),
        );
    }
}
