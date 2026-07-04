<?php

namespace App\Support\Pix\Contracts;

use App\Support\Pix\Data\PixCobrancaInput;
use App\Support\Pix\Data\PixCobrancaResult;
use Illuminate\Http\Request;

/**
 * Contrato comum para provedores Pix (Mercado Pago hoje; bancos/PSPs depois).
 *
 * Todas as implementações devem normalizar status para os valores do model
 * PixCobranca: 'pendente' | 'pago' | 'expirado' | 'cancelado'.
 */
interface PixProvider
{
    /**
     * Identificador do provedor (ex.: 'mercadopago').
     */
    public function nome(): string;

    /**
     * Cria a cobrança no provedor e devolve o QR (copia-e-cola + imagem).
     */
    public function criarCobranca(PixCobrancaInput $input): PixCobrancaResult;

    /**
     * Consulta o status atual de uma cobrança pelo identificador do provedor.
     * Retorna um status normalizado do PixCobranca.
     */
    public function consultarStatus(string $providerRef): string;

    /**
     * Extrai do webhook o identificador do provedor a ser consultado.
     * Retorna null quando o evento não é relevante.
     */
    public function parseWebhook(Request $request): ?string;
}
