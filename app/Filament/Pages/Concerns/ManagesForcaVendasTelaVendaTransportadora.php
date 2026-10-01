<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Transportadora;
use App\Support\Erp\ErpMoney;
use Filament\Notifications\Notification;
use Livewire\Attributes\Computed;

/**
 * Transportadora / volumes / peso na finalização da Tela de Venda (mesmo campos da NF-e).
 */
trait ManagesForcaVendasTelaVendaTransportadora
{
    public bool $fvTransporteModalOpen = false;

    public ?int $fvTransportadoraId = null;

    public string $fvTransportadoraCodigo = '0';

    public string $fvTransportadoraBusca = '';

    /** @var list<array{id: int, codigo: string, nome: string, cpf_cnpj: string|null, doc_tipo: string}> */
    public array $fvTransportadoraSugestoes = [];

    public bool $fvTransportadoraSugestoesOpen = false;

    public int $fvSelectedTransportadoraSugestaoIndex = 0;

    public string $fvTipoFrete = '9';

    public string $fvPlaca = '';

    public string $fvUfPlaca = '';

    public string $fvQvol = '1';

    public string $fvEspecie = 'CAIXA';

    public string $fvPesoB = '0,000';

    public string $fvPesoL = '0,000';

    public string $fvMarca = '';

    public string $fvNvol = '';

    public function abrirFvTransporteModal(): void
    {
        $this->fvTransporteModalOpen = true;
        $this->fecharFvSugestoesTransportadora();
        $this->dispatch('erp-fv-focus-transporte-codigo');
    }

    public function fecharFvTransporteModal(): void
    {
        $this->fvTransporteModalOpen = false;
        $this->fecharFvSugestoesTransportadora();
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
    public function fvTransportePayload(): ?array
    {
        $id = (int) ($this->fvTransportadoraId ?? 0);
        $tipoFrete = trim($this->fvTipoFrete) !== '' ? trim($this->fvTipoFrete) : '9';
        $qvol = max(0, (int) preg_replace('/\D/', '', $this->fvQvol));
        $pesoB = ErpMoney::parseBr($this->fvPesoB);
        $pesoL = ErpMoney::parseBr($this->fvPesoL);
        $placa = mb_strtoupper(trim($this->fvPlaca), 'UTF-8');
        $uf = mb_strtoupper(trim($this->fvUfPlaca), 'UTF-8');
        $especie = mb_strtoupper(trim($this->fvEspecie), 'UTF-8');
        $marca = mb_strtoupper(trim($this->fvMarca), 'UTF-8');
        $nvol = trim($this->fvNvol);
        $nome = trim($this->fvTransportadoraBusca);
        $codigo = trim($this->fvTransportadoraCodigo);

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

    public function fvTransporteResumo(): string
    {
        $payload = $this->fvTransportePayload();
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

    public function confirmarFvTransporteModal(): void
    {
        $this->normalizarFvTransporteCampos();
        $this->fvTransporteModalOpen = false;
        $this->fecharFvSugestoesTransportadora();

        Notification::make()
            ->title('Transportadora / volumes atualizados.')
            ->success()
            ->send();
    }

    /** @return array<string, string> */
    #[Computed]
    public function fvFretePorContaOptions(): array
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
    public function fvUfPlacaOptions(): array
    {
        return \App\Models\Person::ufs();
    }

    public function updatedFvTransportadoraCodigo(): void
    {
        $codigo = trim($this->fvTransportadoraCodigo);
        if ($codigo === '' || $codigo === '0') {
            $this->clearFvTransportadoraDisplay();

            return;
        }

        $this->resolverFvTransportadoraPorCodigo();
    }

    public function updatedFvTransportadoraBusca(string $value): void
    {
        $term = trim($value);
        if ($term === '') {
            $this->fecharFvSugestoesTransportadora();

            return;
        }

        if ($this->fvTransportadoraJaSelecionadaCorresponde($term)) {
            $this->fecharFvSugestoesTransportadora();

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

        $this->fvTransportadoraSugestoes = $rows
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

        $this->fvTransportadoraSugestoesOpen = $this->fvTransportadoraSugestoes !== [];
        $this->fvSelectedTransportadoraSugestaoIndex = 0;
    }

    public function resolverFvTransportadoraPorCodigo(): void
    {
        $codigo = trim($this->fvTransportadoraCodigo);
        if ($codigo === '' || $codigo === '0') {
            $this->clearFvTransportadoraDisplay();

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
            $this->clearFvTransportadoraDisplay();

            return;
        }

        $this->aplicarFvTransportadora($t, syncBusca: true);
        $this->fecharFvSugestoesTransportadora();
    }

    public function confirmarFvTransportadoraBusca(): void
    {
        if ($this->fvTransportadoraSugestoesOpen && $this->fvTransportadoraSugestoes !== []) {
            $idx = max(0, min(
                $this->fvSelectedTransportadoraSugestaoIndex,
                count($this->fvTransportadoraSugestoes) - 1,
            ));
            $this->selecionarFvTransportadora((int) $this->fvTransportadoraSugestoes[$idx]['id']);

            return;
        }

        $this->resolverFvTransportadoraPorCodigo();
    }

    public function selecionarFvTransportadora(int $id): void
    {
        $t = Transportadora::query()->where('ativo', true)->find($id);
        if (! $t) {
            Notification::make()->title('Transportadora não encontrada.')->warning()->send();

            return;
        }

        $this->aplicarFvTransportadora($t, syncBusca: true);
        $this->fecharFvSugestoesTransportadora();
    }

    public function moverFvSugestaoTransportadora(int $delta): void
    {
        if (! $this->fvTransportadoraSugestoesOpen || $this->fvTransportadoraSugestoes === []) {
            return;
        }

        $max = count($this->fvTransportadoraSugestoes) - 1;
        $this->fvSelectedTransportadoraSugestaoIndex = max(0, min(
            $this->fvSelectedTransportadoraSugestaoIndex + $delta,
            $max,
        ));
        $this->dispatch('erp-fv-scroll-transporte-sugestao', index: $this->fvSelectedTransportadoraSugestaoIndex);
    }

    public function fecharFvSugestoesTransportadora(): void
    {
        $this->fvTransportadoraSugestoes = [];
        $this->fvTransportadoraSugestoesOpen = false;
        $this->fvSelectedTransportadoraSugestaoIndex = 0;
    }

    protected function aplicarFvTransportadora(Transportadora $transportadora, bool $syncBusca = true): void
    {
        $this->fvTransportadoraId = (int) $transportadora->id;
        $this->fvTransportadoraCodigo = (string) ($transportadora->codigo ?: '0');

        if ($syncBusca) {
            $this->fvTransportadoraBusca = $this->formatarFvTransportadoraBusca($transportadora);
        }
    }

    protected function formatarFvTransportadoraBusca(Transportadora $transportadora): string
    {
        $nome = trim((string) ($transportadora->proprietario ?: $transportadora->apelido ?: ''));
        $doc = trim((string) ($transportadora->cnpj_cpf ?? ''));

        if ($nome !== '' && $doc !== '') {
            return $nome.' — '.$doc;
        }

        return $nome !== '' ? $nome : $doc;
    }

    protected function clearFvTransportadoraDisplay(): void
    {
        $this->fvTransportadoraId = null;
        $this->fvTransportadoraCodigo = '0';
        $this->fvTransportadoraBusca = '';
        $this->fecharFvSugestoesTransportadora();
    }

    protected function fvTransportadoraJaSelecionadaCorresponde(string $term): bool
    {
        if (! $this->fvTransportadoraId) {
            return false;
        }

        $t = Transportadora::query()->find($this->fvTransportadoraId);
        if (! $t) {
            return false;
        }

        $fmt = $this->formatarFvTransportadoraBusca($t);

        return mb_strtoupper(trim($term), 'UTF-8') === mb_strtoupper(trim($fmt), 'UTF-8');
    }

    protected function normalizarFvTransporteCampos(): void
    {
        $this->fvTipoFrete = trim($this->fvTipoFrete) !== '' ? trim($this->fvTipoFrete) : '9';
        $this->fvPlaca = mb_strtoupper(trim($this->fvPlaca), 'UTF-8');
        $this->fvUfPlaca = mb_strtoupper(trim($this->fvUfPlaca), 'UTF-8');
        $qvol = max(0, (int) preg_replace('/\D/', '', $this->fvQvol));
        $this->fvQvol = (string) ($qvol > 0 ? $qvol : 1);
        $this->fvEspecie = mb_strtoupper(trim($this->fvEspecie), 'UTF-8') ?: 'CAIXA';
        $this->fvMarca = mb_strtoupper(trim($this->fvMarca), 'UTF-8');
        $this->fvNvol = trim($this->fvNvol);
        $this->fvPesoB = number_format(ErpMoney::parseBr($this->fvPesoB), 3, ',', '.');
        $this->fvPesoL = number_format(ErpMoney::parseBr($this->fvPesoL), 3, ',', '.');
    }

    public function limparFvTransporte(): void
    {
        $this->resetFvTransporte();
        $this->fvTransporteModalOpen = false;

        Notification::make()
            ->title('Dados de transporte limpos.')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>|null  $transporte
     */
    protected function aplicarFvTransporteFromPayload(?array $transporte): void
    {
        if (! is_array($transporte) || $transporte === []) {
            $this->resetFvTransporte();

            return;
        }

        $this->fvTransportadoraId = filled($transporte['transportadora_id'] ?? null)
            ? (int) $transporte['transportadora_id']
            : null;
        $this->fvTransportadoraCodigo = (string) ($transporte['transportadora_codigo'] ?? '0');
        $this->fvTransportadoraBusca = (string) ($transporte['transportadora_nome'] ?? '');
        $this->fvTipoFrete = (string) ($transporte['tipo_frete'] ?? '9');
        $this->fvPlaca = (string) ($transporte['placa'] ?? '');
        $this->fvUfPlaca = (string) ($transporte['uf_placa'] ?? '');
        $this->fvQvol = (string) max(1, (int) ($transporte['qvol'] ?? 1));
        $this->fvEspecie = (string) ($transporte['especie'] ?? 'CAIXA');
        $this->fvPesoB = number_format((float) ($transporte['peso_b'] ?? 0), 3, ',', '.');
        $this->fvPesoL = number_format((float) ($transporte['peso_l'] ?? 0), 3, ',', '.');
        $this->fvMarca = (string) ($transporte['marca'] ?? '');
        $this->fvNvol = (string) ($transporte['nvol'] ?? '');

        if ($this->fvTransportadoraId && $this->fvTransportadoraBusca === '') {
            $t = Transportadora::query()->find($this->fvTransportadoraId);
            if ($t) {
                $this->aplicarFvTransportadora($t, syncBusca: true);
            }
        }
    }

    protected function resetFvTransporte(): void
    {
        $this->fvTransporteModalOpen = false;
        $this->fvTransportadoraId = null;
        $this->fvTransportadoraCodigo = '0';
        $this->fvTransportadoraBusca = '';
        $this->fvTransportadoraSugestoes = [];
        $this->fvTransportadoraSugestoesOpen = false;
        $this->fvSelectedTransportadoraSugestaoIndex = 0;
        $this->fvTipoFrete = '9';
        $this->fvPlaca = '';
        $this->fvUfPlaca = '';
        $this->fvQvol = '1';
        $this->fvEspecie = 'CAIXA';
        $this->fvPesoB = '0,000';
        $this->fvPesoL = '0,000';
        $this->fvMarca = '';
        $this->fvNvol = '';
    }
}
