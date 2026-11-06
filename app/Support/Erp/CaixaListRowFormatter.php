<?php

namespace App\Support\Erp;

use App\Models\CaixaLancamento;
use Illuminate\Support\Carbon;

final class CaixaListRowFormatter
{
    /**
     * id do pedido no Monitor => Nº Pedido exibido.
     *
     * @var array<int, string>
     */
    public array $pedidosMonitor = [];

    /**
     * @return array<string, string>
     */
    public function format(CaixaLancamento $record): array
    {
        $evento = (string) ($record->getAttribute('livro_evento') ?? '');
        $isAbertura = $evento === 'abertura' || (bool) $record->getAttribute('livro_abertura');
        $isFechamento = $evento === 'fechamento' || (bool) $record->getAttribute('livro_fechamento');
        $isVirtual = $isAbertura || $isFechamento;

        $isGrupo = (bool) $record->getAttribute('caixa_grupo');
        $historicoTooltip = '';

        if ($isGrupo) {
            $historico = (string) ($record->getAttribute('caixa_grupo_resumo') ?? '');
            $historicoTooltip = (string) ($record->getAttribute('caixa_grupo_tooltip') ?? '');
        } else {
            $historico = CaixaHistoricoExibicao::paraExibicao(
                (string) ($record->historico ?? ''),
                $this->pedidosMonitor,
            );

            if (! $isVirtual) {
                $historico = self::comSessaoPdv($historico, $record);
            }
        }

        $emissao = match (true) {
            $isAbertura => (string) ($record->getAttribute('livro_abertura_em') ?: $this->formatData($record->emissao)),
            $isFechamento => (string) ($record->getAttribute('livro_fechamento_em') ?: $this->formatData($record->emissao)),
            default => $this->formatData($record->emissao),
        };

        $documento = match (true) {
            $isAbertura => 'ABERTURA',
            $isFechamento => 'FECHAMENTO',
            default => ContaReceberPedidoExibicao::texto($record->documento, $this->pedidosMonitor),
        };

        $rowClass = match (true) {
            $isAbertura => ' erp-caixa-row--abertura',
            $isFechamento => ' erp-caixa-row--fechamento',
            $isGrupo => ' erp-caixa-row--grupo',
            default => '',
        };

        $verItens = '';
        if ($isFechamento) {
            $movId = (int) ($record->getAttribute('livro_movimento_id') ?? 0);
            $verItens = $this->formatFechamentoButton($movId);
        } elseif (! $isVirtual) {
            $qtdGrupo = (int) ($record->getAttribute('caixa_grupo_qtd') ?? 0);
            $grupoIds = $isGrupo
                ? array_values(array_map('intval', (array) ($record->getAttribute('caixa_grupo_ids') ?? [])))
                : [];
            $verItens = $this->formatViewButton(
                (int) $record->getKey(),
                $isGrupo ? 'Ver os '.$qtdGrupo.' lançamentos deste pagamento' : null,
                $grupoIds,
            );
        }

        return [
            'codigo' => e($isVirtual ? '—' : (string) ($record->codigo ?? '—')),
            'emissao' => e($emissao),
            'documento' => e($documento),
            'historico' => self::htmlHistorico($historico !== '' ? $historico : '—', $historicoTooltip),
            'plano_contas' => $isVirtual
                ? 'CONFERÊNCIA'
                : (filled($record->plano_contas) ? e((string) $record->plano_contas) : '—'),
            'conta' => e($record->conta?->nome ?? '—'),
            'entrada' => $this->formatMoneyCell((float) ($record->entrada ?? 0)),
            'saida' => $this->formatMoneyCell((float) ($record->saida ?? 0)),
            'ver_itens' => $verItens,
            'row_class' => $rowClass,
        ];
    }

    private static function htmlHistorico(string $historico, string $tooltip = ''): string
    {
        $visivel = e($historico);

        if ($tooltip === '') {
            return '<span class="erp-caixa-historico" title="'.$visivel.'">'.$visivel.'</span>';
        }

        return '<span class="erp-caixa-historico erp-caixa-historico--grupo" title="'.e($tooltip).'">'.$visivel.'</span>';
    }

    /**
     * Linhas vindas da sessão PDV (tela Caixa com conta tipo PDV): sessão · operador · terminal.
     */
    public static function comSessaoPdv(string $historico, CaixaLancamento $record): string
    {
        $sessaoId = (int) ($record->getAttribute('pdv_sessao_id') ?? 0);

        if ($sessaoId <= 0) {
            return $historico;
        }

        $partes = array_filter([
            'CX '.$sessaoId,
            mb_strtoupper(trim((string) $record->getAttribute('pdv_operador')), 'UTF-8'),
            mb_strtoupper(trim((string) $record->getAttribute('pdv_terminal')), 'UTF-8'),
        ], static fn (string $parte): bool => $parte !== '');

        return trim($historico.' · '.implode(' · ', $partes), ' ·');
    }

    private function formatData(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        try {
            return Carbon::parse($state)->format('d/m/Y');
        } catch (\Throwable) {
            return '—';
        }
    }

    private function formatMoneyCell(float $value): string
    {
        $valor = number_format($value, 2, ',', '.');

        return '<span class="erp-caixa-money">'
            . '<span class="erp-caixa-money__currency">R$</span>'
            . '<span class="erp-caixa-money__amount" title="R$ '.e($valor).'">'.e($valor).'</span>'
            . '</span>';
    }

    /**
     * @param  list<int>  $grupoIds
     */
    private function formatViewButton(int $lancamentoId, ?string $title = null, array $grupoIds = []): string
    {
        $label = $title !== null && $title !== '' ? $title : 'Visualizar lançamento';
        $payload = $grupoIds !== []
            ? '{ lancamentoId: '.$lancamentoId.', grupoIds: '.json_encode(array_values($grupoIds)).' }'
            : '{ lancamentoId: '.$lancamentoId.' }';

        return '<span role="button" tabindex="0"'
            . ' wire:click.stop="$dispatch(\'erp-caixa-open-view\', '.$payload.')"'
            . ' wire:keydown.enter.stop="$dispatch(\'erp-caixa-open-view\', '.$payload.')"'
            . ' class="erp-caixa__eye-btn" title="'.e($label).'" aria-label="'.e($label).'">'
            . '<svg class="erp-caixa__eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"'
            . ' stroke-width="1.75" stroke="currentColor" aria-hidden="true">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'
            . '</svg></span>';
    }

    private function formatFechamentoButton(int $movimentoId): string
    {
        if ($movimentoId <= 0) {
            return '';
        }

        return '<span role="button" tabindex="0"'
            . ' wire:click.stop="$dispatch(\'erp-caixa-open-fechamento\', { movimentoId: '.$movimentoId.' })"'
            . ' wire:keydown.enter.stop="$dispatch(\'erp-caixa-open-fechamento\', { movimentoId: '.$movimentoId.' })"'
            . ' class="erp-caixa__eye-btn" title="Consultar fechamento" aria-label="Consultar fechamento">'
            . '<svg class="erp-caixa__eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"'
            . ' stroke-width="1.75" stroke="currentColor" aria-hidden="true">'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>'
            . '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'
            . '</svg></span>';
    }
}
