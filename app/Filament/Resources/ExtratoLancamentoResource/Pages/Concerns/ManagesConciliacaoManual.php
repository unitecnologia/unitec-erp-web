<?php

namespace App\Filament\Resources\ExtratoLancamentoResource\Pages\Concerns;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\ExtratoLancamento;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Financeiro\Conciliacao\ConciliacaoCandidatosQuery;
use App\Support\Erp\Financeiro\Conciliacao\ConciliacaoManualException;
use App\Support\Erp\Financeiro\Conciliacao\ConciliacaoManualService;
use App\Support\Erp\Financeiro\Conciliacao\PixIdentificadorOfx;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait ManagesConciliacaoManual
{
    public bool $conciliarModalOpen = false;

    public ?int $conciliarExtratoId = null;

    /** @var array<string, mixed>|null */
    public ?array $conciliarExtratoResumo = null;

    /** @var list<array<string, mixed>> */
    public array $conciliarCandidatos = [];

    public int $conciliarCandidatosPage = 1;

    public int $conciliarCandidatosLastPage = 1;

    public int $conciliarCandidatosTotal = 0;

    public ?int $conciliarCandidatoId = null;

    public ?string $conciliarAvisoPix = null;

    public bool $conciliarFracaConfirmOpen = false;

    public bool $desfazerConfirmOpen = false;

    public ?int $desfazerExtratoId = null;

    public ?string $desfazerResumo = null;

    public function openConciliarModal(): void
    {
        if (! ErpAccess::currentCan('conciliacao_bancaria.conciliar')) {
            Notification::make()->title('Sem permissão para conciliar.')->warning()->send();

            return;
        }

        $id = $this->highlightedRecordIdOrNotify('conciliar');
        if ($id === null) {
            return;
        }

        $extrato = $this->carregarExtratoDaContaAtual($id);
        if ($extrato === null) {
            return;
        }

        if ($extrato->situacao !== ExtratoLancamento::SITUACAO_PENDENTE) {
            Notification::make()
                ->title('Selecione um lançamento pendente para conciliar.')
                ->warning()
                ->send();

            return;
        }

        if ($extrato->temVinculoTefAtivo()) {
            Notification::make()
                ->title('Crédito vinculado a lote TEF — indisponível para conciliação C3.')
                ->warning()
                ->send();

            return;
        }

        $this->conciliarExtratoId = (int) $extrato->id;
        $this->conciliarExtratoResumo = $this->montarResumoExtrato($extrato);
        $this->conciliarCandidatoId = null;
        $this->conciliarFracaConfirmOpen = false;
        $this->conciliarCandidatosPage = 1;
        $this->carregarCandidatosPagina(1);
        $this->conciliarModalOpen = true;
    }

    public function closeConciliarModal(): void
    {
        if ($this->conciliarFracaConfirmOpen) {
            $this->closeConciliarFracaConfirm();

            return;
        }

        $this->resetConciliarEstado();
    }

    public function closeConciliarFracaConfirm(): void
    {
        $this->conciliarFracaConfirmOpen = false;
    }

    private function resetConciliarEstado(): void
    {
        $this->conciliarModalOpen = false;
        $this->conciliarExtratoId = null;
        $this->conciliarExtratoResumo = null;
        $this->conciliarCandidatos = [];
        $this->conciliarCandidatoId = null;
        $this->conciliarCandidatosPage = 1;
        $this->conciliarCandidatosLastPage = 1;
        $this->conciliarCandidatosTotal = 0;
        $this->conciliarAvisoPix = null;
        $this->conciliarFracaConfirmOpen = false;
    }

    public function updatedConciliarCandidatoId(): void
    {
        $this->conciliarFracaConfirmOpen = false;
    }

    public function conciliarCandidatosIrPara(int $page): void
    {
        if (! $this->conciliarModalOpen || $this->conciliarExtratoId === null) {
            return;
        }

        $this->carregarCandidatosPagina($page);
    }

    public function podeConfirmarConciliacaoManual(): bool
    {
        return $this->conciliarModalOpen
            && (int) ($this->conciliarExtratoId ?? 0) > 0
            && (int) ($this->conciliarCandidatoId ?? 0) > 0
            && $this->conciliarCandidatosTotal > 0
            && $this->candidatoSelecionadoPermiteConfirmar()
            && ErpAccess::currentCan('conciliacao_bancaria.conciliar');
    }

    public function confirmarConciliacaoManual(): void
    {
        if (! ErpAccess::currentCan('conciliacao_bancaria.conciliar')) {
            Notification::make()->title('Sem permissão para conciliar.')->warning()->send();

            return;
        }

        if (! $this->conciliarModalOpen) {
            return;
        }

        $extratoId = (int) ($this->conciliarExtratoId ?? 0);
        $caixaId = (int) ($this->conciliarCandidatoId ?? 0);

        if ($this->conciliarCandidatosTotal <= 0) {
            Notification::make()
                ->title('Sem correspondência no Livro Caixa')
                ->body('Não há lançamento financeiro compatível. O extrato permanece Pendente.')
                ->warning()
                ->send();

            return;
        }

        if ($extratoId <= 0 || $caixaId <= 0) {
            Notification::make()
                ->title('Selecione um lançamento financeiro correspondente.')
                ->body('Confirmar fica disponível somente após escolher um candidato na lista.')
                ->warning()
                ->send();

            return;
        }

        // Segurança: o id escolhido precisa estar na página carregada de candidatos.
        $idsPagina = array_map('intval', array_column($this->conciliarCandidatos, 'id'));
        if ($idsPagina === [] || ! in_array($caixaId, $idsPagina, true)) {
            Notification::make()
                ->title('Candidato inválido ou não selecionado.')
                ->warning()
                ->send();
            $this->conciliarCandidatoId = null;

            return;
        }

        $extrato = $this->carregarExtratoDaContaAtual($extratoId);
        if ($extrato === null) {
            return;
        }

        $caixa = CaixaLancamento::query()->whereKey($caixaId)->first();
        if (! $caixa) {
            Notification::make()->title('Lançamento do Livro Caixa não encontrado.')->warning()->send();

            return;
        }

        if (! $this->candidatoSelecionadoPermiteConfirmar()) {
            Notification::make()
                ->title('Selecione um lançamento correspondente.')
                ->warning()
                ->send();

            return;
        }

        $match = $this->candidatoSelecionadoMatch();
        $aceitarFraca = $this->conciliarFracaConfirmOpen
            && $match === PixIdentificadorOfx::MATCH_FRACA;

        if ($match === PixIdentificadorOfx::MATCH_FRACA && ! $aceitarFraca) {
            $this->conciliarFracaConfirmOpen = true;

            return;
        }

        $userId = (int) (Auth::id() ?? 0);
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        try {
            app(ConciliacaoManualService::class)->conciliar(
                $extrato,
                $caixa,
                $userId,
                $empresaId > 0 ? $empresaId : null,
                $aceitarFraca,
            );

            Notification::make()
                ->title('Conciliação realizada')
                ->body('Vínculo 1:1 registrado. Nenhuma baixa ou movimento financeiro foi gerado.')
                ->success()
                ->send();

            $this->resetConciliarEstado();
            $this->clearListSelection();
            $this->resetTable();
        } catch (ConciliacaoManualException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }

    public function confirmarConciliacaoFracaAssinada(): void
    {
        if (! $this->conciliarFracaConfirmOpen) {
            return;
        }

        $this->confirmarConciliacaoManual();
    }

    public function openDesfazerConfirm(): void
    {
        if (! ErpAccess::currentCan('conciliacao_bancaria.desfazer')) {
            Notification::make()->title('Sem permissão para desfazer conciliação.')->warning()->send();

            return;
        }

        $id = $this->highlightedRecordIdOrNotify('desfazer');
        if ($id === null) {
            return;
        }

        $extrato = $this->carregarExtratoDaContaAtual($id);
        if ($extrato === null) {
            return;
        }

        if ($extrato->situacao !== ExtratoLancamento::SITUACAO_CONCILIADO) {
            Notification::make()
                ->title('Selecione um lançamento conciliado para desfazer.')
                ->warning()
                ->send();

            return;
        }

        $this->desfazerExtratoId = (int) $extrato->id;
        $data = $extrato->data_movimento?->format('d/m/Y') ?? '—';
        $valor = number_format((float) $extrato->valor, 2, ',', '.');
        $this->desfazerResumo = "Extrato #{$extrato->id} — {$data} — R$ {$valor}";
        $this->desfazerConfirmOpen = true;
    }

    public function closeDesfazerConfirm(): void
    {
        $this->desfazerConfirmOpen = false;
        $this->desfazerExtratoId = null;
        $this->desfazerResumo = null;
    }

    public function confirmDesfazerConciliacao(): void
    {
        if (! ErpAccess::currentCan('conciliacao_bancaria.desfazer')) {
            Notification::make()->title('Sem permissão para desfazer conciliação.')->warning()->send();

            return;
        }

        $extratoId = (int) ($this->desfazerExtratoId ?? 0);
        $extrato = $this->carregarExtratoDaContaAtual($extratoId);
        if ($extrato === null) {
            $this->closeDesfazerConfirm();

            return;
        }

        $userId = (int) (Auth::id() ?? 0);
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        try {
            app(ConciliacaoManualService::class)->reverter(
                $extrato,
                $userId,
                'Reversão manual pelo usuário',
                $empresaId > 0 ? $empresaId : null,
            );

            Notification::make()
                ->title('Conciliação desfeita')
                ->body('Extrato voltou a Pendente. Histórico de auditoria preservado. Livro Caixa intacto.')
                ->success()
                ->send();

            $this->closeDesfazerConfirm();
            $this->clearListSelection();
            $this->resetTable();
        } catch (ConciliacaoManualException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
        }
    }

    private function candidatoSelecionadoPermiteConfirmar(): bool
    {
        return $this->candidatoSelecionadoMatch() !== null;
    }

    private function candidatoSelecionadoMatch(): ?string
    {
        $id = (int) ($this->conciliarCandidatoId ?? 0);
        if ($id <= 0) {
            return null;
        }

        foreach ($this->conciliarCandidatos as $c) {
            if ((int) ($c['id'] ?? 0) === $id) {
                return (string) ($c['match'] ?? PixIdentificadorOfx::MATCH_COMPATIVEL);
            }
        }

        return null;
    }

    private function carregarCandidatosPagina(int $page): void
    {
        $extratoId = (int) ($this->conciliarExtratoId ?? 0);
        $extrato = $this->carregarExtratoDaContaAtual($extratoId);
        if ($extrato === null) {
            $this->conciliarCandidatos = [];
            $this->conciliarCandidatosTotal = 0;
            $this->conciliarCandidatosLastPage = 1;
            $this->conciliarAvisoPix = null;

            return;
        }

        $paginator = app(ConciliacaoCandidatosQuery::class)->paginate($extrato, $page);

        $this->conciliarCandidatosPage = (int) $paginator->currentPage();
        $this->conciliarCandidatosLastPage = max(1, (int) $paginator->lastPage());
        $this->conciliarCandidatosTotal = (int) $paginator->total();
        $temFraca = false;
        $this->conciliarCandidatos = $paginator->getCollection()->map(function (CaixaLancamento $row) use ($extrato, &$temFraca): array {
            $entrada = (float) $row->entrada;
            $saida = (float) $row->saida;
            $valor = $entrada > 0 ? $entrada : $saida;
            $sentido = $entrada > 0 ? 'Entrada' : 'Saída';
            $match = PixIdentificadorOfx::classificarMatch($extrato, $row);
            if ($match === PixIdentificadorOfx::MATCH_FRACA) {
                $temFraca = true;
            }

            return [
                'id' => (int) $row->id,
                'codigo' => (int) ($row->codigo ?? 0),
                'emissao' => $row->emissao?->format('d/m/Y') ?? '—',
                'documento' => (string) ($row->documento ?? '—'),
                'historico' => (string) ($row->historico ?? '—'),
                'sentido' => $sentido,
                'valor' => number_format($valor, 2, ',', '.'),
                'match' => $match,
                'match_label' => match ($match) {
                    PixIdentificadorOfx::MATCH_EXATA => 'Exata',
                    PixIdentificadorOfx::MATCH_FRACA => 'Fraca',
                    default => 'Conta/valor',
                },
            ];
        })->values()->all();

        $this->conciliarAvisoPix = $temFraca
            ? 'Correspondência fraca: identificação não comprovada (sem txId/endToEndId). FITID não identifica o PIX. Selecione o lançamento e confirme duas vezes.'
            : null;

        if ($this->conciliarCandidatoId !== null) {
            $ids = array_column($this->conciliarCandidatos, 'id');
            if (! in_array((int) $this->conciliarCandidatoId, $ids, true)) {
                $this->conciliarCandidatoId = null;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function montarResumoExtrato(ExtratoLancamento $extrato): array
    {
        $contaLabel = '—';
        $conta = CaixaConta::query()
            ->select(['id', 'nome', 'banco_codigo', 'agencia', 'conta_numero'])
            ->whereKey((int) $extrato->caixa_conta_id)
            ->first();
        if ($conta) {
            $detalhe = trim(implode(' ', array_filter([
                $conta->banco_codigo ? 'Bco '.$conta->banco_codigo : null,
                $conta->agencia ? 'Ag '.$conta->agencia : null,
                $conta->conta_numero ? 'C/C '.$conta->conta_numero : null,
            ])));
            $contaLabel = $detalhe !== '' ? $conta->nome.' ('.$detalhe.')' : (string) $conta->nome;
        }

        $tipo = match ((string) $extrato->tipo) {
            ExtratoLancamento::TIPO_CREDITO => 'Entrada',
            ExtratoLancamento::TIPO_DEBITO => 'Saída',
            default => 'Outro',
        };

        $situacao = match ((string) $extrato->situacao) {
            ExtratoLancamento::SITUACAO_PENDENTE => 'Pendente',
            ExtratoLancamento::SITUACAO_CONCILIADO => 'Conciliado',
            ExtratoLancamento::SITUACAO_IGNORADO => 'Ignorado',
            default => (string) $extrato->situacao,
        };

        return [
            'id' => (int) $extrato->id,
            'data' => $extrato->data_movimento?->format('d/m/Y') ?? '—',
            'tipo' => $tipo,
            'valor' => number_format((float) $extrato->valor, 2, ',', '.'),
            'historico' => (string) ($extrato->historico ?: '—'),
            'fitid' => (string) ($extrato->fitid ?: '—'),
            'conta' => $contaLabel,
            'situacao' => $situacao,
        ];
    }

    private function carregarExtratoDaContaAtual(int $id): ?ExtratoLancamento
    {
        if ($id <= 0) {
            return null;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $contaId = (int) $this->caixaContaId;

        if ($empresaId <= 0 || $contaId <= 0) {
            Notification::make()->title('Selecione a conta bancária.')->warning()->send();

            return null;
        }

        $extrato = ExtratoLancamento::query()
            ->whereKey($id)
            ->where('empresa_id', $empresaId)
            ->where('caixa_conta_id', $contaId)
            ->first();

        if (! $extrato) {
            Notification::make()->title('Lançamento de extrato não encontrado neste filtro.')->warning()->send();

            return null;
        }

        return $extrato;
    }
}
