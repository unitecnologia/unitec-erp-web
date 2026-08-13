<?php

namespace App\Support\Erp\Nfe;

use App\Models\Nfe;
use App\Models\NfeCartaCorrecao;
use App\Models\NfeEvento;
use App\Models\NfeFatura;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class NfeHistoricoService
{
    /**
     * @return array<string, mixed>|null
     */
    public function montar(int $nfeId): ?array
    {
        $nfe = Nfe::query()
            ->with([
                'cliente',
                'transportadora',
                'venda',
                'itens.product',
                'faturas',
                'cartasCorrecao',
                'eventos.user',
            ])
            ->find($nfeId);

        if (! $nfe) {
            return null;
        }

        $numero = $this->formatNumero($nfe->numero);
        $clienteNome = mb_strtoupper($nfe->cliente?->nome_razao ?? '—', 'UTF-8');
        $statusLabel = Nfe::statusLabels()[$nfe->status] ?? $nfe->status;

        $eventos = $this->montarEventos($nfe)
            ->sortBy(fn (array $evento): int => $evento['ordenacao'])
            ->values()
            ->all();

        return [
            'titulo' => 'Histórico NF-e nº '.$numero.' — '.$clienteNome,
            'cabecalho' => $this->montarCabecalho($nfe, $numero, $clienteNome, $statusLabel),
            'destinatario' => $this->montarDestinatario($nfe),
            'itens' => $this->montarItens($nfe),
            'totais' => $this->montarTotais($nfe),
            'transporte' => $this->montarTransporte($nfe),
            'faturas' => $this->montarFaturas($nfe),
            'observacoes' => [
                'fisco' => filled($nfe->obs_fisco) ? (string) $nfe->obs_fisco : null,
                'contribuinte' => filled($nfe->obs_contribuinte) ? (string) $nfe->obs_contribuinte : null,
            ],
            'eventos' => $eventos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function montarCabecalho(Nfe $nfe, string $numero, string $clienteNome, string $statusLabel): array
    {
        return [
            'numero' => $numero,
            'serie' => (string) ($nfe->serie ?: '1'),
            'modelo' => (string) ($nfe->modelo ?: '55'),
            'cliente' => $clienteNome,
            'status' => $statusLabel,
            'status_codigo' => $nfe->status,
            'emissao' => $nfe->data_emissao?->format('d/m/Y') ?? '—',
            'saida' => $nfe->data_saida?->format('d/m/Y') ?? '—',
            'cfop' => (string) ($nfe->cfop ?: '—'),
            'finalidade' => (string) ($nfe->finalidade ?: '—'),
            'movimento' => (string) ($nfe->movimento ?: '—'),
            'pedido' => filled($nfe->npedido) ? (string) $nfe->npedido : '—',
            'forma_pgto' => (string) ($nfe->forma_pgto ?: '—'),
            'meio_pgto' => (string) ($nfe->meio_pgto ?: '—'),
            'total' => ErpMoney::formatBr((float) $nfe->total),
            'chave' => $nfe->chave ?: '—',
            'protocolo' => $nfe->protocolo ?: '—',
            'venda' => $nfe->venda_id ? (string) (ltrim((string) ($nfe->venda?->numero ?? $nfe->venda_id), '0') ?: '0') : '—',
            'qtd_itens' => (string) ((int) ($nfe->total_itens ?: $nfe->itens->count())),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function montarDestinatario(Nfe $nfe): array
    {
        $pessoa = $nfe->cliente;

        if (! $pessoa) {
            return [
                'nome' => '—',
                'documento' => '—',
                'ie' => '—',
                'endereco' => '—',
                'bairro' => '—',
                'cidade' => '—',
                'uf' => '—',
                'cep' => '—',
                'fone' => '—',
            ];
        }

        $end = collect([
            $pessoa->endereco,
            filled($pessoa->numero) ? 'nº '.$pessoa->numero : null,
            $pessoa->complemento ?? null,
        ])->filter()->implode(', ');

        return [
            'nome' => mb_strtoupper((string) $pessoa->nome_razao, 'UTF-8'),
            'documento' => (string) ($pessoa->cpf_cnpj ?: '—'),
            'ie' => (string) ($pessoa->rg_ie ?: '—'),
            'endereco' => $end !== '' ? mb_strtoupper($end, 'UTF-8') : '—',
            'bairro' => mb_strtoupper((string) ($pessoa->bairro ?: '—'), 'UTF-8'),
            'cidade' => mb_strtoupper((string) ($pessoa->cidade_nome ?: '—'), 'UTF-8'),
            'uf' => (string) ($pessoa->uf ?: '—'),
            'cep' => (string) ($pessoa->cep ?: '—'),
            'fone' => (string) ($pessoa->fone1 ?: $pessoa->celular1 ?: '—'),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function montarItens(Nfe $nfe): array
    {
        $rows = [];

        foreach ($nfe->itens as $item) {
            $product = $item->product;
            $codigo = $product?->codigo ?? $item->cod_barra ?? '';
            $codigoFormatado = '—';

            if ($codigo !== null && $codigo !== '') {
                $trimmed = ltrim((string) $codigo, '0');
                $codigoFormatado = $trimmed !== '' ? $trimmed : '0';
            }

            $quantidade = (float) $item->quantidade;
            $valorUnitario = (float) $item->valor_unitario;
            $total = (float) ($item->total > 0 ? $item->total : ($quantidade * $valorUnitario));

            $rows[] = [
                'item' => (string) ($item->item ?? ''),
                'codigo' => $codigoFormatado,
                'descricao' => mb_strtoupper((string) ($item->descricao ?: ($product?->descricao ?? '—')), 'UTF-8'),
                'ncm' => (string) ($item->ncm ?? $product?->ncm ?? '—'),
                'cst' => (string) ($item->cst ?? $item->csosn ?? '—'),
                'cfop' => (string) ($item->cfop ?? '—'),
                'un' => (string) ($item->unidade ?: 'UN'),
                'quant' => number_format($quantidade, 4, ',', '.'),
                'valor_unit' => number_format($valorUnitario, 4, ',', '.'),
                'desconto' => number_format((float) ($item->desconto ?? 0), 2, ',', '.'),
                'valor_total' => number_format($total, 2, ',', '.'),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private function montarTotais(Nfe $nfe): array
    {
        $subtotal = (float) ($nfe->subtotal ?: $nfe->itens->sum(fn ($i) => (float) $i->total));

        return [
            'produtos' => ErpMoney::formatBr($subtotal),
            'desconto' => ErpMoney::formatBr((float) ($nfe->desconto ?? 0)),
            'frete' => ErpMoney::formatBr((float) ($nfe->frete ?? 0)),
            'seguro' => ErpMoney::formatBr((float) ($nfe->seguro ?? 0)),
            'outras' => ErpMoney::formatBr((float) (($nfe->outros ?? 0) + ($nfe->despesas ?? 0))),
            'base_icms' => ErpMoney::formatBr((float) ($nfe->base_icms ?? 0)),
            'icms' => ErpMoney::formatBr((float) ($nfe->total_icms ?? 0)),
            'ipi' => ErpMoney::formatBr((float) ($nfe->total_ipi ?? 0)),
            'pis' => ErpMoney::formatBr((float) ($nfe->total_icms_pis ?? 0)),
            'cofins' => ErpMoney::formatBr((float) ($nfe->total_icms_cofins ?? 0)),
            'nota' => ErpMoney::formatBr((float) $nfe->total),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function montarTransporte(Nfe $nfe): array
    {
        return [
            'transportadora' => mb_strtoupper((string) ($nfe->transportadora?->proprietario ?: $nfe->transportadora?->apelido ?: '—'), 'UTF-8'),
            'tipo_frete' => (string) ($nfe->tipo_frete ?: '—'),
            'placa' => (string) ($nfe->placa ?: '—'),
            'uf_placa' => (string) ($nfe->uf_placa ?: '—'),
            'rntc' => (string) ($nfe->rntc ?: '—'),
            'especie' => (string) ($nfe->especie ?: '—'),
            'marca' => (string) ($nfe->marca ?: '—'),
            'volumes' => (string) ($nfe->qvol ?: $nfe->nvol ?: '—'),
            'peso_bruto' => filled($nfe->peso_b) ? number_format((float) $nfe->peso_b, 3, ',', '.') : '—',
            'peso_liquido' => filled($nfe->peso_l) ? number_format((float) $nfe->peso_l, 3, ',', '.') : '—',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function montarFaturas(Nfe $nfe): array
    {
        $rows = [];

        foreach ($nfe->faturas as $fatura) {
            $rows[] = [
                'numero' => (string) ($fatura->numero ?: '—'),
                'vencimento' => $fatura->data_vencimento?->format('d/m/Y') ?? '—',
                'valor' => ErpMoney::formatBr((float) $fatura->valor),
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function montarEventos(Nfe $nfe): Collection
    {
        $eventos = collect();

        if (! $this->possuiEvento($nfe, NfeEvento::TIPO_CRIADA)) {
            $eventos->push($this->evento(
                id: 'synthetic:criada',
                tipo: NfeEvento::TIPO_CRIADA,
                titulo: 'NF-e criada',
                descricao: $this->montarDescricaoCriacao($nfe),
                momento: $nfe->created_at,
            ));
        }

        foreach ($nfe->eventos as $evento) {
            $eventos->push($this->eventoFromModel($evento));
        }

        if ($nfe->status === Nfe::STATUS_TRANSMITIDA && filled($nfe->protocolo) && ! $this->possuiEvento($nfe, NfeEvento::TIPO_TRANSMITIDA)) {
            $eventos->push($this->evento(
                id: 'synthetic:transmitida',
                tipo: NfeEvento::TIPO_TRANSMITIDA,
                titulo: 'NF-e transmitida',
                descricao: $this->montarDescricaoTransmissao($nfe),
                momento: $nfe->updated_at ?? $nfe->data_emissao,
            ));
        }

        if ($nfe->status === Nfe::STATUS_CANCELADA && ! $this->possuiEvento($nfe, NfeEvento::TIPO_CANCELADA)) {
            $eventos->push($this->evento(
                id: 'synthetic:cancelada',
                tipo: NfeEvento::TIPO_CANCELADA,
                titulo: 'NF-e cancelada',
                descricao: $this->montarDescricaoCancelamento($nfe),
                momento: $nfe->updated_at,
            ));
        }

        if ($nfe->status === Nfe::STATUS_INUTILIZADA && ! $this->possuiEvento($nfe, NfeEvento::TIPO_INUTILIZADA)) {
            $eventos->push($this->evento(
                id: 'synthetic:inutilizada',
                tipo: NfeEvento::TIPO_INUTILIZADA,
                titulo: 'Numeração inutilizada',
                descricao: 'A numeração desta NF-e foi inutilizada na SEFAZ.',
                momento: $nfe->updated_at,
            ));
        }

        if (in_array($nfe->status, [Nfe::STATUS_DUPLICIDADE, Nfe::STATUS_DENEGADA, Nfe::STATUS_CONTINGENCIA], true)) {
            $eventos->push($this->evento(
                id: 'synthetic:status:'.$nfe->status,
                tipo: $nfe->status,
                titulo: 'Situação: '.(Nfe::statusLabels()[$nfe->status] ?? $nfe->status),
                descricao: $this->montarDescricaoStatusEspecial($nfe),
                momento: $nfe->updated_at,
            ));
        }

        $cceRegistradas = $nfe->eventos
            ->where('tipo', NfeEvento::TIPO_CARTA_CORRECAO)
            ->pluck('referencia_id')
            ->filter()
            ->map(fn ($id): int => (int) $id);

        foreach ($nfe->cartasCorrecao as $carta) {
            if ($cceRegistradas->contains($carta->id)) {
                continue;
            }

            $eventos->push($this->evento(
                id: 'synthetic:cce:'.$carta->id,
                tipo: NfeEvento::TIPO_CARTA_CORRECAO,
                titulo: 'Carta de Correção nº '.$carta->sequencia,
                descricao: $this->montarDescricaoCartaCorrecao($carta),
                momento: $carta->created_at,
            ));
        }

        $boletosRegistrados = $nfe->eventos
            ->where('tipo', NfeEvento::TIPO_BOLETO)
            ->pluck('referencia_id')
            ->filter()
            ->map(fn ($id): int => (int) $id);

        foreach ($nfe->faturas as $fatura) {
            if (! filled($fatura->path_pdf_boleto) || $boletosRegistrados->contains($fatura->id)) {
                continue;
            }

            $eventos->push($this->evento(
                id: 'synthetic:boleto:'.$fatura->id,
                tipo: NfeEvento::TIPO_BOLETO,
                titulo: 'Boleto gerado — parcela '.($fatura->numero ?: '—'),
                descricao: $this->montarDescricaoBoleto($fatura),
                momento: $fatura->updated_at ?? $fatura->created_at,
            ));
        }

        return $eventos->unique('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function eventoFromModel(NfeEvento $evento): array
    {
        return $this->evento(
            id: 'db:'.$evento->id,
            tipo: $evento->tipo,
            titulo: $evento->titulo,
            descricao: $evento->descricao,
            momento: $evento->created_at,
            destinatario: $evento->destinatario,
            usuario: $evento->user?->name,
            metadata: $evento->metadata,
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    private function evento(
        string $id,
        string $tipo,
        string $titulo,
        ?string $descricao,
        ?CarbonInterface $momento,
        ?string $destinatario = null,
        ?string $usuario = null,
        ?array $metadata = null,
    ): array {
        $local = ErpTimezone::toLocal($momento);

        return [
            'id' => $id,
            'tipo' => $tipo,
            'tipo_label' => NfeEvento::tipoLabels()[$tipo] ?? mb_convert_case(str_replace('_', ' ', $tipo), MB_CASE_TITLE, 'UTF-8'),
            'titulo' => $titulo,
            'descricao' => $descricao,
            'destinatario' => $destinatario,
            'usuario' => $usuario ?: '—',
            'data_hora' => $local->format('d/m/Y H:i'),
            'ordenacao' => $local->timestamp,
            'cor' => $this->corPorTipo($tipo),
        ];
    }

    private function possuiEvento(Nfe $nfe, string $tipo): bool
    {
        return $nfe->eventos->contains(fn (NfeEvento $evento): bool => $evento->tipo === $tipo);
    }

    private function montarDescricaoCriacao(Nfe $nfe): string
    {
        $partes = [
            'Série '.($nfe->serie ?: '1').', número '.$this->formatNumero($nfe->numero).'.',
        ];

        if ($nfe->venda_id) {
            $partes[] = 'Vinculada à venda nº '.(ltrim((string) ($nfe->venda?->numero ?? $nfe->venda_id), '0') ?: '0').'.';
        }

        if (filled($nfe->npedido)) {
            $partes[] = 'Pedido: '.$nfe->npedido.'.';
        }

        return implode(' ', $partes);
    }

    private function montarDescricaoTransmissao(Nfe $nfe): string
    {
        $partes = [];

        if (filled($nfe->protocolo)) {
            $partes[] = 'Protocolo de autorização: '.$nfe->protocolo.'.';
        }

        if (filled($nfe->chave)) {
            $partes[] = 'Chave: '.$nfe->chave.'.';
        }

        return $partes !== [] ? implode(' ', $partes) : 'NF-e autorizada na SEFAZ.';
    }

    private function montarDescricaoCancelamento(Nfe $nfe): string
    {
        $partes = [];

        if (filled($nfe->protocolo_cancelamento)) {
            $partes[] = 'Protocolo de cancelamento: '.$nfe->protocolo_cancelamento.'.';
        }

        if (filled($nfe->chave)) {
            $partes[] = 'Chave: '.$nfe->chave.'.';
        }

        return $partes !== [] ? implode(' ', $partes) : 'Cancelamento registrado na SEFAZ.';
    }

    private function montarDescricaoStatusEspecial(Nfe $nfe): string
    {
        if ($nfe->status === Nfe::STATUS_CONTINGENCIA && filled($nfe->motivo_contingencia)) {
            return 'Motivo: '.$nfe->motivo_contingencia;
        }

        return 'Situação fiscal registrada no sistema.';
    }

    private function montarDescricaoCartaCorrecao(NfeCartaCorrecao $carta): string
    {
        $partes = [mb_substr((string) $carta->correcao, 0, 500)];

        if (filled($carta->protocolo)) {
            $partes[] = 'Protocolo: '.$carta->protocolo.'.';
        }

        return implode(' ', array_filter($partes));
    }

    private function montarDescricaoBoleto(NfeFatura $fatura): string
    {
        $vencimento = $fatura->data_vencimento?->format('d/m/Y') ?? '—';
        $valor = ErpMoney::formatBr((float) $fatura->valor);

        return 'Vencimento '.$vencimento.' — valor R$ '.$valor.'.';
    }

    private function formatNumero(mixed $numero): string
    {
        if (blank($numero)) {
            return '—';
        }

        $normalized = ltrim(preg_replace('/\D/', '', (string) $numero) ?? '', '0');

        return $normalized !== '' ? $normalized : '0';
    }

    private function corPorTipo(string $tipo): string
    {
        return match ($tipo) {
            NfeEvento::TIPO_CRIADA, Nfe::STATUS_ABERTA => 'preta',
            NfeEvento::TIPO_TRANSMITIDA, Nfe::STATUS_TRANSMITIDA, NfeEvento::TIPO_WHATSAPP => 'verde',
            NfeEvento::TIPO_CANCELADA, Nfe::STATUS_CANCELADA => 'vermelha',
            Nfe::STATUS_DUPLICIDADE => 'amarela',
            NfeEvento::TIPO_INUTILIZADA, Nfe::STATUS_INUTILIZADA => 'cinza',
            Nfe::STATUS_DENEGADA => 'branca',
            Nfe::STATUS_CONTINGENCIA => 'laranja',
            NfeEvento::TIPO_CARTA_CORRECAO => 'azul',
            NfeEvento::TIPO_EMAIL => 'azul',
            NfeEvento::TIPO_BOLETO => 'roxa',
            NfeEvento::TIPO_IMPRESSAO => 'azul',
            NfeEvento::TIPO_EDITADA => 'preta',
            default => 'cinza',
        };
    }
}
