<?php

namespace App\Support\Erp\Os;

use App\Models\Boleto;
use App\Models\ContaReceber;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\OrdemServicoImagem;
use App\Models\OrdemServicoItem;
use App\Support\Erp\ErpMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Monta o espelho da vida da OS (somente leitura).
 */
final class OrdemServicoEspelhoData
{
    /**
     * @return array{
     *     header: array<string, string>,
     *     equipamento: list<list<array{label: string, value: string}>>,
     *     servicos: list<array<string, string>>,
     *     pecas: list<array<string, string>>,
     *     totais: array<string, string>,
     *     fotos: list<array{url: string, em: string}>,
     *     assinatura: ?array{url: string, em: string},
     *     financeiro: list<array<string, string>>,
     *     boletos: list<array<string, string>>,
     *     nfses: list<array<string, string>>,
     *     timeline: list<array{em: string, titulo: string, detalhe: string}>,
     *     textos: array{problema: string, observacoes: string, laudo: string}
     * }
     */
    public static function for(OrdemServico $ordem): array
    {
        $ordem->loadMissing([
            'cliente',
            'atendente',
            'usuario',
            'itens.product',
            'itens.funcionario',
            'imagens',
        ]);

        $servicos = $ordem->itens
            ->filter(static fn (OrdemServicoItem $item): bool => mb_strtoupper(trim((string) $item->tipo), 'UTF-8') === 'S')
            ->values();
        $pecas = $ordem->itens
            ->filter(static fn (OrdemServicoItem $item): bool => mb_strtoupper(trim((string) $item->tipo), 'UTF-8') !== 'S')
            ->values();

        $fotos = $ordem->imagens
            ->filter(static fn (OrdemServicoImagem $img): bool => $img->tipo === OrdemServicoImagem::TIPO_FOTO)
            ->sortBy('created_at')
            ->values();
        $assinatura = $ordem->imagens
            ->first(static fn (OrdemServicoImagem $img): bool => $img->tipo === OrdemServicoImagem::TIPO_ASSINATURA);

        $documento = 'OS-'.preg_replace('/\D/', '', (string) $ordem->numero);
        $contas = self::contasDaOs($ordem, $documento);
        $contaIds = $contas->pluck('id')->all();
        $boletos = $contaIds === []
            ? collect()
            : Boleto::query()->whereIn('conta_receber_id', $contaIds)->orderBy('id')->get();
        $nfses = self::nfsesRelacionadas($ordem);

        return [
            'header' => [
                'numero' => self::formatNumero($ordem->numero),
                'situacao' => mb_strtoupper($ordem->situacaoLabel(), 'UTF-8'),
                'cliente' => mb_strtoupper($ordem->clienteNome(), 'UTF-8'),
                'documento' => self::formatDoc((string) ($ordem->documento ?: $ordem->cliente?->cpf_cnpj ?: '')),
                'fone' => (string) ($ordem->fone1 ?: $ordem->cliente?->fone1 ?: '—'),
                'atendente' => mb_strtoupper((string) ($ordem->atendente?->nome ?? '—'), 'UTF-8'),
                'usuario' => mb_strtoupper((string) ($ordem->usuario?->name ?? '—'), 'UTF-8'),
                'abertura' => self::dataHora($ordem->data_inicio, $ordem->hora_inicio),
                'fechamento' => self::dataHora($ordem->data_termino, $ordem->hora_termino),
                'entrega' => self::dataHora($ordem->data_entrega, $ordem->hora_entrega),
                'previsao' => $ordem->previsao_entrega?->format('d/m/Y H:i') ?: '—',
                'whatsapp' => filled($ordem->envio_whats_status)
                    ? mb_strtoupper((string) $ordem->envio_whats_status, 'UTF-8')
                    : '—',
            ],
            'equipamento' => OrdemServicoReportData::equipamentoLinhas($ordem),
            'servicos' => self::mapItens($servicos),
            'pecas' => self::mapItens($pecas),
            'totais' => [
                'servicos' => ErpMoney::formatBr((float) $ordem->total_servicos),
                'pecas' => ErpMoney::formatBr((float) $ordem->total_produtos),
                'desconto' => ErpMoney::formatBr((float) $ordem->vl_desc_pecas + (float) $ordem->vl_desc_servicos),
                'geral' => ErpMoney::formatBr((float) $ordem->total_geral),
            ],
            'fotos' => $fotos->map(static function (OrdemServicoImagem $img): ?array {
                $url = self::publicUrl((string) $img->caminho);

                if ($url === null) {
                    return null;
                }

                return [
                    'url' => $url,
                    'em' => optional($img->created_at)?->format('d/m/Y H:i') ?: '—',
                ];
            })->filter()->values()->all(),
            'assinatura' => self::mapAssinatura($assinatura),
            'financeiro' => $contas->map(static function (ContaReceber $conta): array {
                $recebido = (float) ($conta->valor_recebido ?? 0);

                return [
                    'numero' => (string) ($conta->numero ?? '—'),
                    'emissao' => optional($conta->emissao)?->format('d/m/Y') ?: '—',
                    'vencimento' => optional($conta->vencimento)?->format('d/m/Y') ?: '—',
                    'forma' => mb_strtoupper(ContaReceber::formaLabels()[$conta->forma] ?? (string) $conta->forma, 'UTF-8'),
                    'historico' => mb_strtoupper((string) ($conta->historico ?? ''), 'UTF-8') ?: '—',
                    'valor' => ErpMoney::formatBr((float) $conta->valor),
                    'status' => $recebido > 0.0001
                        ? 'RECEBIDO'.(optional($conta->recebido_em)?->format(' d/m/Y') ?: '')
                        : 'EM ABERTO',
                ];
            })->all(),
            'boletos' => $boletos->map(static function (Boleto $boleto): array {
                return [
                    'nosso_numero' => (string) ($boleto->nosso_numero ?: '—'),
                    'documento' => (string) ($boleto->numero_documento ?: '—'),
                    'vencimento' => optional($boleto->vencimento)?->format('d/m/Y') ?: '—',
                    'valor' => ErpMoney::formatBr((float) $boleto->valor),
                    'status' => mb_strtoupper($boleto->statusLabel(), 'UTF-8'),
                ];
            })->all(),
            'nfses' => $nfses->map(static function (Nfse $nfse): array {
                return [
                    'numero' => (string) ($nfse->numero_nfse ?: $nfse->numero_dps ?: $nfse->id),
                    'emissao' => optional($nfse->data_emissao)?->format('d/m/Y') ?: '—',
                    'status' => mb_strtoupper($nfse->statusLabel(), 'UTF-8'),
                    'chave' => (string) ($nfse->chave_acesso ?: $nfse->chave ?: '—'),
                    'total' => ErpMoney::formatBr((float) $nfse->total),
                ];
            })->all(),
            'timeline' => self::timeline($ordem, $fotos, $assinatura, $contas, $nfses, $boletos),
            'textos' => [
                'problema' => trim((string) ($ordem->problema ?? '')) ?: '—',
                'observacoes' => trim((string) ($ordem->observacoes ?? '')) ?: '—',
                'laudo' => trim((string) ($ordem->laudo ?? '')) ?: '—',
            ],
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, ContaReceber>
     */
    private static function contasDaOs(OrdemServico $ordem, string $documento)
    {
        $numero = trim((string) $ordem->numero);
        $digits = preg_replace('/\D/', '', $numero) ?: '';

        return ContaReceber::query()
            ->when($ordem->empresa_id, fn ($q) => $q->where('empresa_id', (int) $ordem->empresa_id))
            ->where(function ($q) use ($documento, $numero, $digits): void {
                $q->where('documento', $documento);
                if ($numero !== '') {
                    $q->orWhere('historico', 'like', 'OS '.$numero.'%');
                }
                if ($digits !== '' && $digits !== $numero) {
                    $q->orWhere('historico', 'like', 'OS '.$digits.'%');
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Nfse>
     */
    private static function nfsesRelacionadas(OrdemServico $ordem)
    {
        if ($ordem->cliente_id === null || ! Schema::hasTable('nfses')) {
            return collect();
        }

        $inicio = optional($ordem->data_inicio)?->copy()?->startOfDay()
            ?? optional($ordem->created_at)?->copy()?->startOfDay()
            ?? now()->subMonths(3)->startOfDay();
        $fim = optional($ordem->data_termino ?? $ordem->data_entrega)?->copy()?->addDays(45)->endOfDay()
            ?? now()->endOfDay();

        $query = Nfse::query()
            ->when($ordem->empresa_id, fn ($q) => $q->where('empresa_id', (int) $ordem->empresa_id))
            ->where('tomador_id', (int) $ordem->cliente_id)
            ->where(function ($q) use ($inicio, $fim): void {
                $q->whereBetween('data_emissao', [$inicio->toDateString(), $fim->toDateString()])
                    ->orWhereBetween('created_at', [$inicio, $fim]);
            })
            ->orderByDesc('id');

        $totalServicos = round((float) $ordem->total_servicos, 2);
        $nfses = $query->limit(20)->get();

        if ($totalServicos > 0.009) {
            $filtradas = $nfses->filter(static function (Nfse $nfse) use ($totalServicos): bool {
                return abs((float) $nfse->valor_servicos - $totalServicos) < 0.05
                    || abs((float) $nfse->total - $totalServicos) < 0.05;
            });

            if ($filtradas->isNotEmpty()) {
                return $filtradas->values();
            }
        }

        return $nfses->take(5)->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OrdemServicoItem>  $itens
     * @return list<array<string, string>>
     */
    private static function mapItens($itens): array
    {
        return $itens->map(static function (OrdemServicoItem $item): array {
            $descricao = trim((string) ($item->discriminacao ?: $item->nome ?: $item->product?->descricao ?: ''));

            return [
                'codigo' => (string) ($item->product?->codigo ?? $item->codigo_legado ?? '—'),
                'descricao' => $descricao !== '' ? mb_strtoupper($descricao, 'UTF-8') : '—',
                'tecnico' => mb_strtoupper((string) ($item->funcionario?->nome ?? '—'), 'UTF-8'),
                'qtd' => ErpMoney::formatBr((float) $item->qtd, 3),
                'preco' => ErpMoney::formatBr((float) $item->preco),
                'total' => ErpMoney::formatBr((float) $item->total),
            ];
        })->all();
    }

    /**
     * @return list<array{em: string, titulo: string, detalhe: string}>
     */
    private static function timeline(
        OrdemServico $ordem,
        $fotos,
        ?OrdemServicoImagem $assinatura,
        $contas,
        $nfses,
        $boletos,
    ): array {
        $events = [];

        $push = static function (?Carbon $when, string $titulo, string $detalhe = '') use (&$events): void {
            if ($when === null) {
                return;
            }
            $events[] = [
                'sort' => $when->timestamp,
                'em' => $when->format('d/m/Y H:i'),
                'titulo' => $titulo,
                'detalhe' => $detalhe,
            ];
        };

        $abertura = self::carbonFromDateTime($ordem->data_inicio, $ordem->hora_inicio)
            ?? optional($ordem->created_at)?->copy();
        $push($abertura, 'OS aberta', 'Número '.self::formatNumero($ordem->numero));

        if (filled($ordem->hora_inicio)) {
            $inicio = self::carbonFromDateTime($ordem->data_inicio, $ordem->hora_inicio);
            if ($inicio && (! $abertura || $inicio->ne($abertura))) {
                $push($inicio, 'Atendimento iniciado', (string) ($ordem->atendente?->nome ?? ''));
            } elseif ($inicio) {
                // já registrado como abertura com hora
            } else {
                $push($abertura, 'Atendimento iniciado', (string) ($ordem->atendente?->nome ?? ''));
            }
        }

        foreach ($fotos as $foto) {
            $push(
                optional($foto->created_at)?->copy(),
                'Foto anexada',
                'Mídia #'.$foto->id,
            );
        }

        if ($assinatura) {
            $push(
                optional($assinatura->created_at)?->copy(),
                'Assinatura capturada',
                '',
            );
        }

        foreach ($contas as $conta) {
            $push(
                optional($conta->created_at)?->copy()
                    ?? (optional($conta->emissao)?->copy()?->startOfDay()),
                'Faturamento / conta a receber',
                'Doc. '.$conta->documento.' · R$ '.ErpMoney::formatBr((float) $conta->valor),
            );
            if ($conta->recebido_em) {
                $push(
                    Carbon::parse($conta->recebido_em),
                    'Recebimento',
                    'Conta '.$conta->numero,
                );
            }
        }

        foreach ($boletos as $boleto) {
            $push(
                optional($boleto->created_at)?->copy()
                    ?? (optional($boleto->emissao)?->copy()?->startOfDay()),
                'Boleto gerado',
                'NN '.$boleto->nosso_numero.' · '.$boleto->statusLabel(),
            );
        }

        foreach ($nfses as $nfse) {
            $push(
                optional($nfse->created_at)?->copy()
                    ?? (optional($nfse->data_emissao)?->copy()?->startOfDay()),
                'NFS-e '.$nfse->statusLabel(),
                'Nº '.($nfse->numero_nfse ?: $nfse->numero_dps ?: $nfse->id),
            );
        }

        $fechamento = self::carbonFromDateTime($ordem->data_termino, $ordem->hora_termino);
        $push($fechamento, 'OS fechada / finalizada', mb_strtoupper($ordem->situacaoLabel(), 'UTF-8'));

        $entrega = self::carbonFromDateTime($ordem->data_entrega, $ordem->hora_entrega);
        $push($entrega, 'Equipamento entregue', '');

        if ($ordem->situacao === OrdemServico::SITUACAO_CANCELADA) {
            $push(
                optional($ordem->updated_at)?->copy(),
                'OS cancelada',
                '',
            );
        }

        if (filled($ordem->envio_whats_status)) {
            $push(
                optional($ordem->updated_at)?->copy(),
                'Envio WhatsApp',
                mb_strtoupper((string) $ordem->envio_whats_status, 'UTF-8'),
            );
        }

        usort($events, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_map(static fn (array $e): array => [
            'em' => $e['em'],
            'titulo' => $e['titulo'],
            'detalhe' => $e['detalhe'] !== '' ? $e['detalhe'] : '—',
        ], $events);
    }

    private static function carbonFromDateTime(mixed $date, mixed $time): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $base = $date instanceof Carbon ? $date->copy() : Carbon::parse((string) $date);
            $hora = trim((string) ($time ?? ''));
            if ($hora !== '') {
                $parts = explode(':', $hora);
                $base->setTime((int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0));
            } else {
                $base->startOfDay();
            }

            return $base;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return ?array{url: string, em: string}
     */
    private static function mapAssinatura(?OrdemServicoImagem $assinatura): ?array
    {
        if (! $assinatura instanceof OrdemServicoImagem) {
            return null;
        }

        $url = self::publicUrl((string) $assinatura->caminho);

        if ($url === null) {
            return null;
        }

        return [
            'url' => $url,
            'em' => optional($assinatura->created_at)?->format('d/m/Y H:i') ?: '—',
        ];
    }

    private static function publicUrl(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private static function formatNumero(?string $numero): string
    {
        if (blank($numero)) {
            return '—';
        }
        $digits = (int) preg_replace('/\D/', '', $numero);

        return $digits > 0 ? (string) $digits : $numero;
    }

    private static function formatDoc(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (strlen($digits) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
        }
        if (strlen($digits) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digits) ?: $value;
        }

        return $value !== '' ? $value : '—';
    }

    private static function dataHora(mixed $date, mixed $time): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $d = $date instanceof Carbon ? $date->format('d/m/Y') : Carbon::parse((string) $date)->format('d/m/Y');
        $h = trim((string) ($time ?? ''));
        if ($h !== '') {
            return $d.' '.substr($h, 0, 5);
        }

        return $d;
    }
}
