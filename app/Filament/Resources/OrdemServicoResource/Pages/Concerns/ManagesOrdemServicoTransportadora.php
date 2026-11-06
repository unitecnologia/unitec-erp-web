<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages\Concerns;

use App\Models\OrdemServico;
use App\Models\Transportadora;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpSchema;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;

/**
 * Transportadora / volumes / peso no fechamento da OS (mesmo contrato JSON da NF-e; independente da TV).
 */
trait ManagesOrdemServicoTransportadora
{
    public bool $osTransporteModalOpen = false;

    public ?int $osTransportadoraId = null;

    public string $osTransportadoraCodigo = '0';

    public string $osTransportadoraBusca = '';

    /** @var list<array{id: int, codigo: string, nome: string, cpf_cnpj: string|null, doc_tipo: string}> */
    public array $osTransportadoraSugestoes = [];

    public bool $osTransportadoraSugestoesOpen = false;

    public int $osSelectedTransportadoraSugestaoIndex = 0;

    public string $osTipoFrete = '9';

    public string $osPlacaTransporte = '';

    public string $osUfPlacaTransporte = '';

    public string $osQvol = '1';

    public string $osEspecie = 'CAIXA';

    public string $osPesoB = '0,000';

    public string $osPesoL = '0,000';

    public string $osMarcaTransporte = '';

    public string $osNvol = '';

    public function abrirOsTransporteModal(): void
    {
        $this->osTransporteModalOpen = true;
        $this->fecharOsSugestoesTransportadora();
        $this->dispatch('erp-os-focus-transporte-codigo');
    }

    public function fecharOsTransporteModal(): void
    {
        $this->osTransporteModalOpen = false;
        $this->fecharOsSugestoesTransportadora();
    }

    /**
     * @return array{
     *   transportadora_id: int|null,
     *   transportadora_codigo: string,
     *   transportadora_nome: string,
     *   tipo_frete: string,
     *   placa: string,
     *   uf_placa: string,
     *   qvol: int,
     *   especie: string,
     *   peso_b: float,
     *   peso_l: float,
     *   marca: string,
     *   nvol: string
     * }|null
     */
    public function osTransportePayload(): ?array
    {
        $id = (int) ($this->osTransportadoraId ?? 0);
        $tipoFrete = trim($this->osTipoFrete) !== '' ? trim($this->osTipoFrete) : '9';
        $qvol = max(0, (int) preg_replace('/\D/', '', $this->osQvol));
        $pesoB = ErpMoney::parseBr($this->osPesoB);
        $pesoL = ErpMoney::parseBr($this->osPesoL);
        $placa = mb_strtoupper(trim($this->osPlacaTransporte), 'UTF-8');
        $uf = mb_strtoupper(trim($this->osUfPlacaTransporte), 'UTF-8');
        $especie = mb_strtoupper(trim($this->osEspecie), 'UTF-8');
        $marca = mb_strtoupper(trim($this->osMarcaTransporte), 'UTF-8');
        $nvol = trim($this->osNvol);
        $nome = trim($this->osTransportadoraBusca);
        $codigo = trim($this->osTransportadoraCodigo);

        $temAlgo = $id > 0
            || ($codigo !== '' && $codigo !== '0')
            || $nome !== ''
            || $tipoFrete !== '9'
            || $placa !== ''
            || $uf !== ''
            || $qvol > 1
            || ($especie !== '' && $especie !== 'CAIXA')
            || $pesoB > 0.0005
            || $pesoL > 0.0005
            || $marca !== ''
            || $nvol !== '';

        if (! $temAlgo) {
            return null;
        }

        return [
            'transportadora_id' => $id > 0 ? $id : null,
            'transportadora_codigo' => $codigo !== '' ? $codigo : '0',
            'transportadora_nome' => $nome,
            'tipo_frete' => $tipoFrete,
            'placa' => $placa,
            'uf_placa' => $uf,
            'qvol' => $qvol > 0 ? $qvol : 1,
            'especie' => $especie !== '' ? $especie : 'CAIXA',
            'peso_b' => round($pesoB, 3),
            'peso_l' => round($pesoL, 3),
            'marca' => $marca,
            'nvol' => $nvol,
        ];
    }

    public function osTransporteResumo(): string
    {
        $payload = $this->osTransportePayload();
        if ($payload === null) {
            return '';
        }

        $partes = [];
        if ($payload['transportadora_nome'] !== '') {
            $partes[] = $payload['transportadora_nome'];
        } elseif ($payload['transportadora_id']) {
            $partes[] = 'Cód. '.$payload['transportadora_codigo'];
        }
        $partes[] = $payload['qvol'].' vol.';
        if ($payload['peso_b'] > 0.0005) {
            $partes[] = number_format($payload['peso_b'], 3, ',', '.').' kg';
        }

        return implode(' · ', $partes);
    }

    public function confirmarOsTransporteModal(): void
    {
        $this->normalizarOsTransporteCampos();
        $this->persistirOsTransporteNaOrdem();
        $this->osTransporteModalOpen = false;
        $this->fecharOsSugestoesTransportadora();

        Notification::make()
            ->title('Transportadora / volumes atualizados.')
            ->success()
            ->send();
    }

    /** @return array<string, string> */
    #[Computed]
    public function osFretePorContaOptions(): array
    {
        return [
            '9' => '9 - SEM FRETE',
            '0' => '0 - EMITENTE',
            '1' => '1 - DESTINATÁRIO',
            '2' => '2 - TERCEIROS',
            '3' => '3 - PRÓPRIO REMETENTE',
            '4' => '4 - PRÓPRIO DESTINATÁRIO',
        ];
    }

    /** @return array<string, string> */
    #[Computed]
    public function osUfPlacaTransporteOptions(): array
    {
        return \App\Models\Person::ufs();
    }

    public function updatedOsTransportadoraCodigo(): void
    {
        $codigo = trim($this->osTransportadoraCodigo);
        if ($codigo === '' || $codigo === '0') {
            $this->clearOsTransportadoraDisplay();

            return;
        }

        $this->resolverOsTransportadoraPorCodigo();
    }

    public function updatedOsTransportadoraBusca(string $value): void
    {
        $term = trim($value);
        if ($term === '') {
            $this->fecharOsSugestoesTransportadora();

            return;
        }

        if ($this->osTransportadoraJaSelecionadaCorresponde($term)) {
            $this->fecharOsSugestoesTransportadora();

            return;
        }

        $digits = preg_replace('/\D/', '', $term) ?: '';
        $termUpper = mb_strtoupper($term, 'UTF-8');
        $likeContains = '%'.$termUpper.'%';
        $likeStarts = $termUpper.'%';
        $codigoExato = ltrim($term, '0') ?: $term;

        $rows = Transportadora::query()
            ->where('ativo', true)
            ->where(function ($q) use ($likeStarts, $likeContains, $digits): void {
                $q->where('codigo', 'like', $likeStarts)
                    ->orWhereRaw('UPPER(proprietario) LIKE ?', [$likeContains])
                    ->orWhereRaw("UPPER(COALESCE(apelido, '')) LIKE ?", [$likeContains]);

                if ($digits !== '') {
                    $q->orWhere('cnpj_cpf', 'like', '%'.$digits.'%');
                }
            })
            ->orderByRaw(
                "CASE
                    WHEN codigo = ? OR codigo = ? THEN 0
                    WHEN codigo LIKE ? THEN 1
                    WHEN UPPER(proprietario) LIKE ? THEN 2
                    WHEN UPPER(COALESCE(apelido, '')) LIKE ? THEN 3
                    WHEN cnpj_cpf LIKE ? THEN 4
                    ELSE 5
                END",
                [
                    $term,
                    $codigoExato,
                    $likeStarts,
                    $likeStarts,
                    $likeStarts,
                    $digits !== '' ? $digits.'%' : '__never__',
                ]
            )
            ->orderBy('proprietario')
            ->limit(12)
            ->get(['id', 'codigo', 'proprietario', 'apelido', 'cnpj_cpf']);

        $this->osTransportadoraSugestoes = $rows
            ->map(function (Transportadora $t): array {
                $doc = (string) ($t->cnpj_cpf ?? '');
                $digitsOnly = preg_replace('/\D/', '', $doc) ?: '';

                return [
                    'id' => (int) $t->id,
                    'codigo' => (string) ($t->codigo ?: ''),
                    'nome' => trim((string) ($t->proprietario ?: $t->apelido ?: '')),
                    'cpf_cnpj' => $doc !== '' ? $doc : null,
                    'doc_tipo' => strlen($digitsOnly) > 11 ? 'cnpj' : (strlen($digitsOnly) === 11 ? 'cpf' : ''),
                ];
            })
            ->values()
            ->all();

        $this->osTransportadoraSugestoesOpen = $this->osTransportadoraSugestoes !== [];
        $this->osSelectedTransportadoraSugestaoIndex = 0;
    }

    public function resolverOsTransportadoraPorCodigo(): void
    {
        $codigo = trim($this->osTransportadoraCodigo);
        if ($codigo === '' || $codigo === '0') {
            $this->clearOsTransportadoraDisplay();

            return;
        }

        $t = Transportadora::query()
            ->where('ativo', true)
            ->where(function ($q) use ($codigo): void {
                $q->where('codigo', $codigo)
                    ->orWhere('codigo', ltrim($codigo, '0') ?: $codigo);
            })
            ->orderBy('id')
            ->first();

        if (! $t) {
            Notification::make()->title('Transportadora não encontrada.')->warning()->send();
            $this->clearOsTransportadoraDisplay();

            return;
        }

        $this->aplicarOsTransportadora($t, syncBusca: true);
        $this->fecharOsSugestoesTransportadora();
    }

    public function confirmarOsTransportadoraBusca(): void
    {
        if ($this->osTransportadoraSugestoesOpen && $this->osTransportadoraSugestoes !== []) {
            $idx = max(0, min(
                $this->osSelectedTransportadoraSugestaoIndex,
                count($this->osTransportadoraSugestoes) - 1,
            ));
            $this->selecionarOsTransportadora((int) $this->osTransportadoraSugestoes[$idx]['id']);

            return;
        }

        $this->resolverOsTransportadoraPorCodigo();
    }

    public function selecionarOsTransportadora(int $id): void
    {
        $t = Transportadora::query()->where('ativo', true)->find($id);
        if (! $t) {
            Notification::make()->title('Transportadora não encontrada.')->warning()->send();

            return;
        }

        $this->aplicarOsTransportadora($t, syncBusca: true);
        $this->fecharOsSugestoesTransportadora();
    }

    public function moverOsSugestaoTransportadora(int $delta): void
    {
        if (! $this->osTransportadoraSugestoesOpen || $this->osTransportadoraSugestoes === []) {
            return;
        }

        $max = count($this->osTransportadoraSugestoes) - 1;
        $this->osSelectedTransportadoraSugestaoIndex = max(0, min(
            $this->osSelectedTransportadoraSugestaoIndex + $delta,
            $max,
        ));
        $this->dispatch('erp-os-scroll-transporte-sugestao', index: $this->osSelectedTransportadoraSugestaoIndex);
    }

    public function fecharOsSugestoesTransportadora(): void
    {
        $this->osTransportadoraSugestoes = [];
        $this->osTransportadoraSugestoesOpen = false;
        $this->osSelectedTransportadoraSugestaoIndex = 0;
    }

    protected function aplicarOsTransportadora(Transportadora $transportadora, bool $syncBusca = true): void
    {
        $this->osTransportadoraId = (int) $transportadora->id;
        $this->osTransportadoraCodigo = (string) ($transportadora->codigo ?: '0');

        if ($syncBusca) {
            $this->osTransportadoraBusca = $this->formatarOsTransportadoraBusca($transportadora);
        }
    }

    protected function formatarOsTransportadoraBusca(Transportadora $transportadora): string
    {
        $nome = trim((string) ($transportadora->proprietario ?: $transportadora->apelido ?: ''));
        $doc = trim((string) ($transportadora->cnpj_cpf ?? ''));

        if ($nome !== '' && $doc !== '') {
            return $nome.' — '.$doc;
        }

        return $nome !== '' ? $nome : $doc;
    }

    protected function clearOsTransportadoraDisplay(): void
    {
        $this->osTransportadoraId = null;
        $this->osTransportadoraCodigo = '0';
        $this->osTransportadoraBusca = '';
        $this->fecharOsSugestoesTransportadora();
    }

    protected function osTransportadoraJaSelecionadaCorresponde(string $term): bool
    {
        if (! $this->osTransportadoraId) {
            return false;
        }

        $t = Transportadora::query()->find($this->osTransportadoraId);
        if (! $t) {
            return false;
        }

        $fmt = $this->formatarOsTransportadoraBusca($t);

        return mb_strtoupper(trim($term), 'UTF-8') === mb_strtoupper(trim($fmt), 'UTF-8');
    }

    protected function normalizarOsTransporteCampos(): void
    {
        $this->osTipoFrete = trim($this->osTipoFrete) !== '' ? trim($this->osTipoFrete) : '9';
        $this->osPlacaTransporte = mb_strtoupper(trim($this->osPlacaTransporte), 'UTF-8');
        $this->osUfPlacaTransporte = mb_strtoupper(trim($this->osUfPlacaTransporte), 'UTF-8');
        $qvol = max(0, (int) preg_replace('/\D/', '', $this->osQvol));
        $this->osQvol = (string) ($qvol > 0 ? $qvol : 1);
        $this->osEspecie = mb_strtoupper(trim($this->osEspecie), 'UTF-8') ?: 'CAIXA';
        $this->osMarcaTransporte = mb_strtoupper(trim($this->osMarcaTransporte), 'UTF-8');
        $this->osNvol = trim($this->osNvol);
        $this->osPesoB = number_format(ErpMoney::parseBr($this->osPesoB), 3, ',', '.');
        $this->osPesoL = number_format(ErpMoney::parseBr($this->osPesoL), 3, ',', '.');
    }

    public function limparOsTransporte(): void
    {
        $this->resetOsTransporte();
        $this->persistirOsTransporteNaOrdem();
        $this->osTransporteModalOpen = false;

        Notification::make()
            ->title('Dados de transporte limpos.')
            ->success()
            ->send();
    }

    /**
     * Grava o JSON transporte na OS atual (sem migration = no-op).
     */
    protected function persistirOsTransporteNaOrdem(): void
    {
        if (! ErpSchema::hasColumn((new OrdemServico)->getTable(), 'transporte')) {
            return;
        }

        $ordem = $this->record ?? null;
        if (! $ordem instanceof OrdemServico || ! $ordem->exists) {
            return;
        }

        $payload = $this->osTransportePayload();
        $ordem->forceFill(['transporte' => $payload])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>|null  $transporte
     */
    protected function aplicarOsTransporteFromPayload(?array $transporte): void
    {
        if (! is_array($transporte) || $transporte === []) {
            $this->resetOsTransporte();

            return;
        }

        $this->osTransportadoraId = filled($transporte['transportadora_id'] ?? null)
            ? (int) $transporte['transportadora_id']
            : null;
        $this->osTransportadoraCodigo = (string) ($transporte['transportadora_codigo'] ?? '0');
        $this->osTransportadoraBusca = (string) ($transporte['transportadora_nome'] ?? '');
        $this->osTipoFrete = (string) ($transporte['tipo_frete'] ?? '9');
        $this->osPlacaTransporte = (string) ($transporte['placa'] ?? '');
        $this->osUfPlacaTransporte = (string) ($transporte['uf_placa'] ?? '');
        $this->osQvol = (string) max(1, (int) ($transporte['qvol'] ?? 1));
        $this->osEspecie = (string) ($transporte['especie'] ?? 'CAIXA');
        $this->osPesoB = number_format((float) ($transporte['peso_b'] ?? 0), 3, ',', '.');
        $this->osPesoL = number_format((float) ($transporte['peso_l'] ?? 0), 3, ',', '.');
        $this->osMarcaTransporte = (string) ($transporte['marca'] ?? '');
        $this->osNvol = (string) ($transporte['nvol'] ?? '');

        if ($this->osTransportadoraId && $this->osTransportadoraBusca === '') {
            $t = Transportadora::query()->find($this->osTransportadoraId);
            if ($t) {
                $this->aplicarOsTransportadora($t, syncBusca: true);
            }
        }
    }

    protected function resetOsTransporte(): void
    {
        $this->osTransporteModalOpen = false;
        $this->osTransportadoraId = null;
        $this->osTransportadoraCodigo = '0';
        $this->osTransportadoraBusca = '';
        $this->osTransportadoraSugestoes = [];
        $this->osTransportadoraSugestoesOpen = false;
        $this->osSelectedTransportadoraSugestaoIndex = 0;
        $this->osTipoFrete = '9';
        $this->osPlacaTransporte = '';
        $this->osUfPlacaTransporte = '';
        $this->osQvol = '1';
        $this->osEspecie = 'CAIXA';
        $this->osPesoB = '0,000';
        $this->osPesoL = '0,000';
        $this->osMarcaTransporte = '';
        $this->osNvol = '';
    }
}
