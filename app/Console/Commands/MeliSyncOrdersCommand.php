<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Support\MercadoLivre\MeliApiClient;
use App\Support\MercadoLivre\MeliOrderIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeliSyncOrdersCommand extends Command
{
    protected $signature = 'erp:meli-sync-orders {--empresa= : ID da empresa} {--limit=30}';

    protected $description = 'Busca pedidos recentes do Mercado Livre (útil para clientes sem webhook público)';

    public function handle(MeliApiClient $api, MeliOrderIngestService $ingest): int
    {
        $empresaId = $this->option('empresa');
        $limit = max(1, min(50, (int) $this->option('limit')));

        $query = Empresa::query()
            ->where('param_meli_habilitar', true)
            ->whereNotNull('param_meli_access_token')
            ->where('param_meli_access_token', '!=', '');

        if (filled($empresaId)) {
            $query->whereKey((int) $empresaId);
        }

        $empresas = $query->get();

        if ($empresas->isEmpty()) {
            $this->warn('Nenhuma empresa com Mercado Livre conectado.');

            return self::SUCCESS;
        }

        $imported = 0;

        foreach ($empresas as $empresa) {
            $token = $api->accessTokenForEmpresa($empresa);

            if ($token === null) {
                $this->warn("Empresa {$empresa->id}: token indisponível.");

                continue;
            }

            $sellerId = trim((string) $empresa->param_meli_user_id);

            if ($sellerId === '') {
                $me = $api->getMe($token);
                $sellerId = (string) ($me['data']['id'] ?? '');
            }

            if ($sellerId === '') {
                $this->warn("Empresa {$empresa->id}: sem user_id ML.");

                continue;
            }

            $response = Http::acceptJson()
                ->timeout(30)
                ->withToken($token)
                ->get(rtrim((string) config('meli.api_url'), '/').'/orders/search', [
                    'seller' => $sellerId,
                    'sort' => 'date_desc',
                    'limit' => $limit,
                ]);

            if (! $response->successful()) {
                $this->error("Empresa {$empresa->id}: falha na busca de pedidos.");
                Log::warning('meli.sync.search_failed', [
                    'empresa_id' => $empresa->id,
                    'body' => $response->json(),
                ]);

                continue;
            }

            $results = $response->json('results');

            if (! is_array($results)) {
                continue;
            }

            foreach ($results as $row) {
                $orderId = (string) ($row['id'] ?? '');

                if ($orderId === '') {
                    continue;
                }

                $result = $ingest->ingestOrder($empresa, $orderId);

                if (($result['ok'] ?? false) && empty($result['data']['duplicado'])) {
                    $imported++;
                    $this->line("Importado ML #{$orderId}");
                }
            }
        }

        $this->info("Concluído. Novos pedidos: {$imported}");

        return self::SUCCESS;
    }
}
