<?php

declare(strict_types=1);

namespace App\Support\Pix\Providers;

use App\Models\Empresa;
use App\Models\PixCobranca;
use App\Support\Pix\Ailos\AilosPixConfig;
use App\Support\Pix\Ailos\AilosPixService;
use App\Support\Pix\Contracts\PixProvider;
use App\Support\Pix\Data\PixCobrancaInput;
use App\Support\Pix\Data\PixCobrancaResult;
use Illuminate\Http\Request;

final class AilosPixProvider implements PixProvider
{
    private readonly AilosPixService $service;

    public function __construct(Empresa $empresa)
    {
        $this->service = new AilosPixService(AilosPixConfig::fromEmpresa($empresa));
    }

    public function nome(): string
    {
        return 'ailos';
    }

    public function criarCobranca(PixCobrancaInput $input): PixCobrancaResult
    {
        $amount = number_format($input->valor, 2, '.', '');
        $txid = trim($input->txid) !== ''
            ? trim($input->txid)
            : AilosPixService::buildTxid(
                (string) ($input->externalReference ?? 'REF'),
                $amount,
                $input->descricao,
                (string) ($input->debtorDocument ?? ''),
            );

        $result = $this->service->createCharge([
            'amount' => $amount,
            'description' => $input->descricao,
            'reference' => (string) ($input->externalReference ?? $txid),
            'txid' => $txid,
            'debtorName' => $input->debtorName,
            'debtorDocument' => $input->debtorDocument,
        ]);

        // Registra webhook na Ailos (idempotente) quando houver URL HTTPS pública.
        $webhook = trim((string) ($input->notificationUrl ?? ''));
        if ($webhook !== '' && str_starts_with(strtolower($webhook), 'https://')) {
            try {
                $this->service->configureWebhook($webhook);
            } catch (\Throwable) {
                // Não impede a emissão do QR; polling/webhook já cadastrado cobrem.
            }
        }

        return new PixCobrancaResult(
            providerRef: $result->txid,
            qrCopiaCola: $result->brCode,
            qrImagemBase64: $result->qrCodeBase64,
            status: PixCobranca::STATUS_PENDENTE,
            raw: $result->toArray(),
        );
    }

    public function consultarStatus(string $providerRef): string
    {
        return match ($this->service->getPaymentStatus($providerRef)) {
            'approved' => PixCobranca::STATUS_PAGO,
            'cancelled' => PixCobranca::STATUS_CANCELADO,
            default => PixCobranca::STATUS_PENDENTE,
        };
    }

    public function parseWebhook(Request $request): ?string
    {
        $txid = $request->input('txid')
            ?? $request->input('pix.txid')
            ?? data_get($request->all(), 'pix.0.txid')
            ?? $request->query('txid');

        $txid = is_scalar($txid) ? trim((string) $txid) : '';

        return $txid !== '' ? $txid : null;
    }

    public function service(): AilosPixService
    {
        return $this->service;
    }
}
