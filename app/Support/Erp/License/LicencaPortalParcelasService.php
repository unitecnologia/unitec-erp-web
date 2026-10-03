<?php

namespace App\Support\Erp\License;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lista parcelas de todos os clientes no Portal Financeiro.
 * GET {licenca_api.base_url}/api/erp/parcelas
 * Header: X-ERP-Token
 */
final class LicencaPortalParcelasService
{
    private const PER_PAGE = 50;

    public static function habilitada(): bool
    {
        $enabled = filter_var(config('unitec.portal_erp_parcelas.enabled'), FILTER_VALIDATE_BOOL);
        $token = trim((string) config('unitec.portal_erp_parcelas.token', ''));

        return $enabled && $token !== '';
    }

    /**
     * @param  array{page?: int, status?: string, q?: string, vencimento_de?: string, vencimento_ate?: string}  $filtros
     * @return array{
     *     ok: bool,
     *     resultado: 'ok'|'empty'|'auth'|'unavailable',
     *     message: string,
     *     rows: list<array{cliente: string, cnpj: string, documento: string, vencimento: string, valor: string, situacao: string, situacao_tom: string, pagamento: string}>,
     *     page: int,
     *     per_page: int,
     *     total: int
     * }
     */
    public function listar(array $filtros): array
    {
        $vazio = [
            'ok' => false,
            'resultado' => 'unavailable',
            'message' => 'O portal não respondeu. Tente novamente em instantes.',
            'rows' => [],
            'page' => max(1, (int) ($filtros['page'] ?? 1)),
            'per_page' => self::PER_PAGE,
            'total' => 0,
        ];

        if (! static::habilitada()) {
            return [
                ...$vazio,
                'resultado' => 'auth',
                'message' => 'A consulta de parcelas do portal não está habilitada.',
            ];
        }

        $baseUrl = rtrim((string) config('unitec.licenca_api.base_url', ''), '/');

        if ($baseUrl === '') {
            return $vazio;
        }

        $page = max(1, (int) ($filtros['page'] ?? 1));
        $query = [
            'page' => $page,
            'per_page' => self::PER_PAGE,
        ];

        $status = strtolower(trim((string) ($filtros['status'] ?? '')));

        if (in_array($status, ['pending', 'paid', 'overdue'], true)) {
            $query['status'] = $status;
        }

        $q = trim((string) ($filtros['q'] ?? ''));

        if ($q !== '') {
            $query['q'] = mb_substr($q, 0, 120);
        }

        foreach (['vencimento_de', 'vencimento_ate'] as $campo) {
            $data = trim((string) ($filtros[$campo] ?? ''));

            if ($this->dataIso($data)) {
                $query[$campo] = $data;
            }
        }

        $timeout = max(8, (int) config('unitec.licenca_api.timeout', 8));

        try {
            $response = LicencaHttpClient::make()
                ->timeout($timeout)
                ->connectTimeout(min(3, $timeout))
                ->acceptJson()
                ->withHeader('X-ERP-Token', trim((string) config('unitec.portal_erp_parcelas.token', '')))
                ->get($baseUrl.'/api/erp/parcelas', $query);
        } catch (ConnectionException $e) {
            Log::warning('Portal de parcelas indisponível.', ['message' => $e->getMessage()]);

            return $vazio;
        } catch (Throwable $e) {
            Log::warning('Falha ao consultar parcelas do portal.', ['message' => $e->getMessage()]);

            return $vazio;
        }

        if (in_array($response->status(), [401, 403], true)) {
            return [
                ...$vazio,
                'resultado' => 'auth',
                'message' => 'Não foi possível autenticar no portal. Confira o token da integração.',
            ];
        }

        if ($response->status() === 404) {
            return [
                ...$vazio,
                'message' => 'A consulta de parcelas ainda não está disponível no portal.',
            ];
        }

        if (! $response->successful()) {
            Log::warning('Portal de parcelas recusou a consulta.', ['status' => $response->status()]);

            return $vazio;
        }

        $payload = $response->json();

        if (! is_array($payload) || ! is_array($payload['data'] ?? null) || ! is_array($payload['meta'] ?? null)) {
            return [
                ...$vazio,
                'message' => 'O portal devolveu uma resposta que o ERP não reconhece.',
            ];
        }

        $meta = $payload['meta'];
        $rows = [];

        foreach ($payload['data'] as $item) {
            if (is_array($item)) {
                $rows[] = $this->normalizar($item);
            }
        }

        $total = max(0, (int) ($meta['total'] ?? count($rows)));
        $perPage = max(1, (int) ($meta['per_page'] ?? self::PER_PAGE));
        $pagina = max(1, (int) ($meta['page'] ?? $page));

        return [
            'ok' => true,
            'resultado' => $total === 0 ? 'empty' : 'ok',
            'message' => $total === 0 ? 'Nenhuma parcela encontrada para os filtros informados.' : '',
            'rows' => $rows,
            'page' => $pagina,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{cliente: string, cnpj: string, documento: string, vencimento: string, valor: string, situacao: string, situacao_tom: string, pagamento: string}
     */
    private function normalizar(array $item): array
    {
        $cliente = $this->texto($item['cliente'] ?? $item['clientName'] ?? $item['name'] ?? '');
        $cnpj = $this->formatCnpj((string) ($item['cnpj'] ?? ''));
        $descricao = $this->texto($item['documento'] ?? $item['description'] ?? $item['descricao'] ?? '');
        $id = $item['id'] ?? null;
        $documento = $descricao !== '' ? $descricao : ($id !== null && $id !== '' ? '#'.$id : '—');

        $dueRaw = trim((string) ($item['dueDate'] ?? $item['vencimento'] ?? ''));
        $due = $this->parseData($dueRaw);
        $status = strtolower(trim((string) ($item['status'] ?? '')));
        $atrasada = filter_var($item['atrasada'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($status !== 'paid' && ($atrasada || ($due !== null && $due->copy()->startOfDay()->lt(Carbon::now('America/Sao_Paulo')->startOfDay())))) {
            $status = 'overdue';
        }

        [$situacao, $tom] = match ($status) {
            'paid' => ['Paga', 'paid'],
            'overdue' => ['Atrasada', 'overdue'],
            'pending' => ['Pendente', 'pending'],
            default => [$status !== '' ? $status : '—', 'pending'],
        };

        return [
            'cliente' => $cliente !== '' ? $cliente : '—',
            'cnpj' => $cnpj !== '' ? $cnpj : '—',
            'documento' => $documento,
            'vencimento' => $due?->format('d/m/Y') ?? ($dueRaw !== '' ? $dueRaw : '—'),
            'valor' => $this->formatValor($item['amount'] ?? null),
            'situacao' => $situacao,
            'situacao_tom' => $tom,
            'pagamento' => $this->formatPagamento($item),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function formatPagamento(array $item): string
    {
        $paidAt = $this->parseData((string) ($item['paidAt'] ?? $item['pagoEm'] ?? ''));
        $metodo = strtolower(trim((string) ($item['paymentMethod'] ?? '')));
        $rotulo = match ($metodo) {
            'pix' => 'PIX',
            'boleto' => 'Boleto',
            'manual' => 'Manual',
            '' => '',
            default => mb_strtoupper($metodo, 'UTF-8'),
        };

        if ($paidAt === null && $rotulo === '') {
            return strtolower((string) ($item['status'] ?? '')) === 'paid' ? 'Pago' : '—';
        }

        $partes = array_values(array_filter([
            $paidAt?->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            $rotulo,
        ]));

        return $partes === [] ? '—' : implode(' · ', $partes);
    }

    private function formatValor(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $raw = str_replace(' ', '', (string) $amount);

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        if (! is_numeric($raw)) {
            return (string) $amount;
        }

        return 'R$ '.number_format((float) $raw, 2, ',', '.');
    }

    private function formatCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D/', '', $cnpj) ?? '';

        if (strlen($digits) !== 14) {
            return trim($cnpj);
        }

        return substr($digits, 0, 2).'.'
            .substr($digits, 2, 3).'.'
            .substr($digits, 5, 3).'/'
            .substr($digits, 8, 4).'-'
            .substr($digits, 12, 2);
    }

    private function texto(mixed $value): string
    {
        return trim((string) $value);
    }

    private function dataIso(string $valor): bool
    {
        if ($valor === '') {
            return false;
        }

        $data = \DateTime::createFromFormat('!Y-m-d', $valor);

        return $data instanceof \DateTime && $data->format('Y-m-d') === $valor;
    }

    private function parseData(string $valor): ?Carbon
    {
        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (Throwable) {
            return null;
        }
    }
}
