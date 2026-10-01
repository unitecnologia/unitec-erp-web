<?php

namespace App\Support\Erp\Os;

use App\Models\Boleto;
use App\Models\ContaReceber;
use App\Models\EstoqueMovimentacao;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\OrdemServicoImagem;
use App\Models\OrdemServicoItem;
use App\Support\Erp\ErpMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Espelho da “vida” da OS: dados, itens, mídias, financeiro e documentos relacionados.
 */
final class OrdemServicoEspelhoService
{
    /**
     * @return array{
     *     header: array<string, string>,
     *     equipamento: list<list<array{label: string, value: string}>>,
     *     textos: array{problema: string, observacoes: string, laudo: string},
     *     pecas: list<array<string, string>>,
     *     servicos: list<array<string, string>>,
     *     totais: array<string, string>,
     *     fotos: list<array{url: string, em: string}>,
     *     assinatura: ?array{url: string, em: string},
     *     financeiro: list<array<string, string>>,
     *     boletos: list<array<string, string>>,
     *     notas: list<array<string, string>>,
     *     estoque: list<array<string, string>>,
     *     historico: list<array{quando: string, titulo: string, detalhe: string}>
     * }
     */
    public function build(OrdemServico $ordem): array
    {
        $ordem->loadMissing([
            'cliente',
            'atendente',
            'usuario',
            'itens.product',
            'itens.funcionario',
            'imagens',
        ]);

        $documento = 'OS-'.preg_replace('/\D/', '', (string) $ordem->numero);
        $contas = $this->contasReceber($ordem, $documento);
        $boletos = $this->boletos($contas);
        $estoque = $this->estoque($ordem);
        $notas = $this->notasFiscais($ordem);
        $fotos = $this->mapFotos($ordem);
        $assinatura = $this->mapAssinatura($ordem);

        $servicos = $ordem->itens
            ->filter(static fn (OrdemServicoItem $item): bool => mb_strtoupper(trim((string) $item->tipo), 'UTF-8') === 'S')
            ->values();
        $pecas = $ordem->itens
            ->filter(static fn (OrdemServicoItem $item): bool => mb_strtoupper(trim((string) ($item->tipo ?? 'P')), 'UTF-8') !== 'S')
            ->values();

        $header = [
            'numero' => $this->formatNumero($ordem->numero),
            'situacao' => mb_strtoupper($ordem->situacaoLabel(), 'UTF-8'),
            'cliente' => mb_strtoupper($ordem->clienteNome(), 'UTF-8'),
            'documento' => (string) ($ordem->documento ?: $ordem->cliente?->cpf_cnpj ?: '—'),
            'fone' => (string) ($ordem->fone1 ?: $ordem->cliente?->fone1 ?: '—'),
            'atendente' => mb_strtoupper((string) ($ordem->atendente?->nome ?: '—'), 'UTF-8'),
            'usuario' => mb_strtoupper((string) ($ordem->usuario?->name ?: '—'), 'UTF-8'),
            'abertura' => $this->dataHora($ordem->data_inicio, $ordem->hora_inicio),
            'fechamento' => $this->dataHora($ordem->data_termino, $ordem->hora_termino),
            'entrega' => $this->dataHora($ordem->data_entrega, $ordem->hora_entrega),
            'previsao' => $ordem->previsao_entrega?->format('d/m/Y H:i') ?: '—',
            'whatsapp' => (string) ($ordem->numero_whatsapp ?: '—'),
            'envio_whats' => (string) ($ordem->envio_whats_status ?: '—'),
        ];

        return [
            'header' => $header,
            'equipamento' => OrdemServicoReportData::equipamentoLinhas($ordem),
            'textos' => [
                'problema' => trim((string) ($ordem->problema ?? '')) ?: '—',
                'observacoes' => trim((string) ($ordem->observacoes ?? '')) ?: '—',
                'laudo' => trim((string) ($ordem->laudo ?? '')) ?: '—',
            ],
            'pecas' => $this->mapItens($pecas),
            'servicos' => $this->mapItens($servicos),
            'totais' => [
                'pecas' => 'R$ '.ErpMoney::formatBr((float) $ordem->total_produtos),
                'servicos' => 'R$ '.ErpMoney::formatBr((float) $ordem->total_servicos),
                'desconto' => 'R$ '.ErpMoney::formatBr((float) $ordem->vl_desc_pecas + (float) $ordem->vl_desc_servicos),
                'geral' => 'R$ '.ErpMoney::formatBr((float) $ordem->total_geral),
            ],
            'fotos' => $fotos,
            'assinatura' => $assinatura,
            'financeiro' => $contas->map(static function (ContaReceber $conta): array {
                return [
                    'numero' => (string) $conta->numero,
                    'documento' => (string) ($conta->documento ?: '—'),
                    'emissao' => optional($conta->emissao)?->format('d/m/Y') ?: '—',
                    'vencimento' => optional($conta->vencimento)?->format('d/m/Y') ?: '—',
                    'forma' => ContaReceber::formaLabels()[(string) $conta->forma] ?? mb_strtoupper((string) $conta->forma, 'UTF-8'),
                    'historico' => (string) ($conta->historico ?: '—'),
                    'valor' => 'R$ '.ErpMoney::formatBr((float) $conta->valor),
                    'recebido' => 'R$ '.ErpMoney::formatBr((float) ($conta->valor_recebido ?? 0)),
                    'saldo' => 'R$ '.ErpMoney::formatBr((float) ($conta->saldo ?? ContaReceber::calcularSaldo(
                        (float) $conta->valor,
                        (float) ($conta->desconto ?? 0),
                        (float) ($conta->juros ?? 0),
                        (float) ($conta->valor_recebido ?? 0),
                    ))),
                ];
            })->values()->all(),
            'boletos' => $boletos,
            'notas' => $notas,
            'estoque' => $estoque,
            'historico' => $this->historico($ordem, $fotos, $assinatura, $contas, $boletos, $notas, $estoque),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, OrdemServicoItem>  $itens
     * @return list<array<string, string>>
     */
    private function mapItens($itens): array
    {
        return $itens->map(static function (OrdemServicoItem $item): array {
            $descricao = trim((string) ($item->discriminacao ?: $item->nome ?: $item->product?->descricao ?: ''));

            return [
                'codigo' => (string) ($item->product?->codigo ?? $item->codigo_legado ?? '—'),
                'descricao' => $descricao !== '' ? mb_strtoupper($descricao, 'UTF-8') : '—',
                'tecnico' => mb_strtoupper((string) ($item->funcionario?->nome ?: '—'), 'UTF-8'),
                'qtd' => ErpMoney::formatBr((float) $item->qtd, 3),
                'preco' => 'R$ '.ErpMoney::formatBr((float) $item->preco),
                'total' => 'R$ '.ErpMoney::formatBr((float) $item->total),
            ];
        })->all();
    }

    /**
     * @return list<array{url: string, em: string}>
     */
    private function mapFotos(OrdemServico $ordem): array
    {
        return $ordem->imagens
            ->filter(static fn (OrdemServicoImagem $img): bool => $img->tipo === OrdemServicoImagem::TIPO_FOTO)
            ->values()
            ->map(fn (OrdemServicoImagem $img): ?array => $this->midiaUrl($img))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return ?array{url: string, em: string}
     */
    private function mapAssinatura(OrdemServico $ordem): ?array
    {
        $img = $ordem->imagens
            ->first(static fn (OrdemServicoImagem $item): bool => $item->tipo === OrdemServicoImagem::TIPO_ASSINATURA);

        return $img instanceof OrdemServicoImagem ? $this->midiaUrl($img) : null;
    }

    /**
     * @return ?array{url: string, em: string}
     */
    private function midiaUrl(OrdemServicoImagem $img): ?array
    {
        $path = trim((string) $img->caminho);

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return [
            'url' => Storage::disk('public')->url($path),
            'em' => optional($img->created_at)?->format('d/m/Y H:i') ?: '—',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, ContaReceber>
     */
    private function contasReceber(OrdemServico $ordem, string $documento)
    {
        $numero = trim((string) $ordem->numero);

        return ContaReceber::query()
            ->when($ordem->empresa_id, fn ($q) => $q->where('empresa_id', (int) $ordem->empresa_id))
            ->where(function ($q) use ($documento, $numero, $ordem): void {
                $q->where('documento', $documento)
                    ->orWhere('historico', 'like', 'OS '.$numero.'%');

                if ($ordem->cliente_id) {
                    $q->orWhere(function ($inner) use ($ordem, $documento): void {
                        $inner->where('cliente_id', (int) $ordem->cliente_id)
                            ->where('documento', $documento);
                    });
                }
            })
            ->orderBy('emissao')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ContaReceber>  $contas
     * @return list<array<string, string>>
     */
    private function boletos($contas): array
    {
        $ids = $contas->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            return [];
        }

        return Boleto::query()
            ->whereIn('conta_receber_id', $ids)
            ->orderBy('id')
            ->get()
            ->map(static function (Boleto $boleto): array {
                return [
                    'nosso_numero' => (string) ($boleto->nosso_numero ?: '—'),
                    'documento' => (string) ($boleto->numero_documento ?: '—'),
                    'vencimento' => optional($boleto->vencimento)?->format('d/m/Y') ?: '—',
                    'valor' => 'R$ '.ErpMoney::formatBr((float) ($boleto->valor ?? 0)),
                    'status' => mb_strtoupper($boleto->statusLabel(), 'UTF-8'),
                    'linha' => (string) ($boleto->linha_digitavel ?: '—'),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function estoque(OrdemServico $ordem): array
    {
        return EstoqueMovimentacao::query()
            ->with('produto')
            ->where('origem_tipo', 'ordem_servico')
            ->where('origem_id', (int) $ordem->id)
            ->orderBy('id')
            ->get()
            ->map(static function (EstoqueMovimentacao $mov): array {
                return [
                    'quando' => optional($mov->data_movimentacao ?? $mov->created_at)?->format('d/m/Y H:i') ?: '—',
                    'tipo' => mb_strtoupper(EstoqueMovimentacao::tipoLabel((string) $mov->tipo), 'UTF-8'),
                    'produto' => mb_strtoupper((string) ($mov->produto?->descricao ?: $mov->produto?->codigo ?: '—'), 'UTF-8'),
                    'qtd' => ErpMoney::formatBr((float) ($mov->quantidade ?? 0), 3),
                    'documento' => EstoqueMovimentacao::documentoLabel($mov->origem_tipo, $mov->origem_numero),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function notasFiscais(OrdemServico $ordem): array
    {
        if (! $ordem->cliente_id) {
            return [];
        }

        $inicio = optional($ordem->data_inicio)?->copy()?->subDays(1) ?? now()->subMonth();
        $fim = optional($ordem->data_termino ?? $ordem->data_entrega)?->copy()?->addDays(45) ?? now()->addDays(7);
        $alvoServicos = round((float) $ordem->total_servicos, 2);
        $alvoGeral = round((float) $ordem->total_geral, 2);

        return Nfse::query()
            ->where('tomador_id', (int) $ordem->cliente_id)
            ->when($ordem->empresa_id, fn ($q) => $q->where('empresa_id', (int) $ordem->empresa_id))
            ->whereBetween('data_emissao', [$inicio->toDateString(), $fim->toDateString()])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->filter(static function (Nfse $nfse) use ($alvoServicos, $alvoGeral): bool {
                $total = round((float) $nfse->total, 2);

                if ($alvoServicos <= 0.0001 && $alvoGeral <= 0.0001) {
                    return true;
                }

                return abs($total - $alvoServicos) < 0.02
                    || abs($total - $alvoGeral) < 0.02;
            })
            ->take(8)
            ->values()
            ->map(static function (Nfse $nfse): array {
                return [
                    'tipo' => 'NFS-e',
                    'numero' => (string) ($nfse->numero_nfse ?: $nfse->numero_dps ?: $nfse->id),
                    'emissao' => optional($nfse->data_emissao)?->format('d/m/Y') ?: '—',
                    'status' => mb_strtoupper($nfse->statusLabel(), 'UTF-8'),
                    'total' => 'R$ '.ErpMoney::formatBr((float) $nfse->total),
                    'chave' => (string) ($nfse->chave_acesso ?: $nfse->chave ?: '—'),
                ];
            })
            ->all();
    }

    /**
     * @param  list<array{url: string, em: string}>  $fotos
     * @param  ?array{url: string, em: string}  $assinatura
     * @param  \Illuminate\Support\Collection<int, ContaReceber>  $contas
     * @param  list<array<string, string>>  $boletos
     * @param  list<array<string, string>>  $notas
     * @param  list<array<string, string>>  $estoque
     * @return list<array{quando: string, titulo: string, detalhe: string}>
     */
    private function historico(
        OrdemServico $ordem,
        array $fotos,
        ?array $assinatura,
        $contas,
        array $boletos,
        array $notas,
        array $estoque,
    ): array {
        $events = [];

        $events[] = [
            'quando' => optional($ordem->created_at)?->format('d/m/Y H:i') ?: $this->dataHora($ordem->data_inicio, null),
            'titulo' => 'OS aberta',
            'detalhe' => 'Número '.$this->formatNumero($ordem->numero).' · Situação atual: '.mb_strtoupper($ordem->situacaoLabel(), 'UTF-8'),
            '_sort' => optional($ordem->created_at)?->timestamp ?? 0,
        ];

        if (filled($ordem->hora_inicio) || filled($ordem->data_inicio)) {
            $dt = $this->carbonFrom($ordem->data_inicio, $ordem->hora_inicio);
            $events[] = [
                'quando' => $this->dataHora($ordem->data_inicio, $ordem->hora_inicio),
                'titulo' => 'Início do atendimento',
                'detalhe' => 'Atendente: '.mb_strtoupper((string) ($ordem->atendente?->nome ?: '—'), 'UTF-8'),
                '_sort' => $dt?->timestamp ?? 0,
            ];
        }

        foreach ($fotos as $foto) {
            $events[] = [
                'quando' => $foto['em'],
                'titulo' => 'Foto anexada',
                'detalhe' => 'Registro fotográfico da OS',
                '_sort' => $this->parseBrDateTime($foto['em']),
            ];
        }

        if ($assinatura !== null) {
            $events[] = [
                'quando' => $assinatura['em'],
                'titulo' => 'Assinatura capturada',
                'detalhe' => 'Cliente/responsável assinou a OS',
                '_sort' => $this->parseBrDateTime($assinatura['em']),
            ];
        }

        if (filled($ordem->data_termino) || filled($ordem->hora_termino)) {
            $dt = $this->carbonFrom($ordem->data_termino, $ordem->hora_termino);
            $events[] = [
                'quando' => $this->dataHora($ordem->data_termino, $ordem->hora_termino),
                'titulo' => 'OS fechada / faturada',
                'detalhe' => 'Total R$ '.ErpMoney::formatBr((float) $ordem->total_geral),
                '_sort' => $dt?->timestamp ?? 0,
            ];
        }

        if (filled($ordem->data_entrega) || filled($ordem->hora_entrega)) {
            $dt = $this->carbonFrom($ordem->data_entrega, $ordem->hora_entrega);
            $events[] = [
                'quando' => $this->dataHora($ordem->data_entrega, $ordem->hora_entrega),
                'titulo' => 'Entrega registrada',
                'detalhe' => 'Equipamento entregue ao cliente',
                '_sort' => $dt?->timestamp ?? 0,
            ];
        }

        foreach ($contas as $conta) {
            $events[] = [
                'quando' => optional($conta->emissao)?->format('d/m/Y').' 00:00',
                'titulo' => 'Lançamento financeiro',
                'detalhe' => trim((string) $conta->historico).' · R$ '.ErpMoney::formatBr((float) $conta->valor),
                '_sort' => optional($conta->emissao)?->timestamp ?? 0,
            ];
        }

        foreach ($boletos as $boleto) {
            $events[] = [
                'quando' => ($boleto['vencimento'] ?? '—').' 00:00',
                'titulo' => 'Boleto gerado',
                'detalhe' => 'Nosso nº '.$boleto['nosso_numero'].' · '.$boleto['valor'],
                '_sort' => $this->parseBrDate($boleto['vencimento'] ?? ''),
            ];
        }

        foreach ($notas as $nota) {
            $events[] = [
                'quando' => ($nota['emissao'] ?? '—').' 00:00',
                'titulo' => $nota['tipo'].' '.$nota['status'],
                'detalhe' => 'Nº '.$nota['numero'].' · '.$nota['total'],
                '_sort' => $this->parseBrDate($nota['emissao'] ?? ''),
            ];
        }

        foreach ($estoque as $mov) {
            $events[] = [
                'quando' => $mov['quando'],
                'titulo' => 'Estoque · '.$mov['tipo'],
                'detalhe' => $mov['produto'].' · Qtd '.$mov['qtd'],
                '_sort' => $this->parseBrDateTime($mov['quando']),
            ];
        }

        if ($ordem->situacao === OrdemServico::SITUACAO_CANCELADA) {
            $events[] = [
                'quando' => optional($ordem->updated_at)?->format('d/m/Y H:i') ?: '—',
                'titulo' => 'OS cancelada',
                'detalhe' => 'Situação final: CANCELADA',
                '_sort' => optional($ordem->updated_at)?->timestamp ?? PHP_INT_MAX,
            ];
        }

        usort($events, static fn (array $a, array $b): int => ($a['_sort'] <=> $b['_sort']));

        return array_map(static function (array $event): array {
            return [
                'quando' => $event['quando'],
                'titulo' => $event['titulo'],
                'detalhe' => $event['detalhe'],
            ];
        }, $events);
    }

    private function formatNumero(?string $numero): string
    {
        if (blank($numero)) {
            return '—';
        }

        $digits = (int) preg_replace('/\D/', '', (string) $numero);

        return $digits > 0 ? (string) $digits : (string) $numero;
    }

    private function dataHora(mixed $data, mixed $hora): string
    {
        $d = null;

        if ($data instanceof Carbon) {
            $d = $data->format('d/m/Y');
        } elseif (filled($data)) {
            try {
                $d = Carbon::parse((string) $data)->format('d/m/Y');
            } catch (\Throwable) {
                $d = null;
            }
        }

        $h = filled($hora) ? substr((string) $hora, 0, 5) : null;

        if ($d && $h) {
            return $d.' '.$h;
        }

        return $d ?: ($h ?: '—');
    }

    private function carbonFrom(mixed $data, mixed $hora): ?Carbon
    {
        if (! filled($data) && ! filled($hora)) {
            return null;
        }

        try {
            $base = filled($data) ? Carbon::parse((string) $data) : now()->startOfDay();
            if (filled($hora)) {
                $parts = explode(':', substr((string) $hora, 0, 8));
                $base->setTime((int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0), (int) ($parts[2] ?? 0));
            }

            return $base;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseBrDateTime(string $value): int
    {
        try {
            return Carbon::createFromFormat('d/m/Y H:i', $value)->timestamp;
        } catch (\Throwable) {
            return $this->parseBrDate($value);
        }
    }

    private function parseBrDate(string $value): int
    {
        $value = trim(explode(' ', $value)[0] ?? '');

        try {
            return Carbon::createFromFormat('d/m/Y', $value)->timestamp;
        } catch (\Throwable) {
            return 0;
        }
    }
}
