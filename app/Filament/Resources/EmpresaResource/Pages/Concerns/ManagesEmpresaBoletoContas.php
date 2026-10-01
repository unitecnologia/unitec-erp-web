<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Models\BoletoContaApi;
use App\Models\Empresa;
use App\Support\Erp\Boleto\BoletoContaApiDefaults;
use App\Support\Erp\EmpresaParametros;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait ManagesEmpresaBoletoContas
{
    public bool $boletoContaModalOpen = false;

    public ?int $boletoContaEditId = null;

    /** @var array<string, mixed> */
    public array $boletoContaForm = [];

    /**
     * @return list<array{id: int, rotulo: string, banco: string, ambiente: string, ativo: bool, padrao: bool}>
     */
    public function getBoletoContasApiRowsProperty(): array
    {
        $empresa = $this->record;
        if (! $empresa instanceof Empresa || ! $empresa->exists) {
            return [];
        }

        return BoletoContaApi::query()
            ->where('empresa_id', $empresa->id)
            ->orderByDesc('padrao')
            ->orderBy('id')
            ->get()
            ->map(fn (BoletoContaApi $c): array => [
                'id' => (int) $c->id,
                'rotulo' => $c->rotulo(),
                'banco' => $c->bancoCompe(),
                'ambiente' => (string) ($c->ambiente ?: 'homologacao'),
                'ativo' => (bool) $c->ativo,
                'padrao' => (bool) $c->padrao,
            ])
            ->all();
    }

    public function openBoletoContaCreate(): void
    {
        $this->boletoContaEditId = null;
        $this->boletoContaForm = BoletoContaApiDefaults::forBanco(EmpresaParametros::BOLETO_BANCO_AILOS);
        $this->boletoContaModalOpen = true;
    }

    public function updatedBoletoContaFormBanco(mixed $value): void
    {
        // Só aplica defaults ao criar conta nova — não sobrescreve edição do cliente.
        if ($this->boletoContaEditId) {
            return;
        }

        $banco = preg_replace('/\D/', '', (string) $value) ?? '';
        if ($banco === '') {
            return;
        }

        $keep = [
            'ativo' => (bool) ($this->boletoContaForm['ativo'] ?? true),
            'padrao' => (bool) ($this->boletoContaForm['padrao'] ?? false),
        ];

        $this->boletoContaForm = array_merge(
            BoletoContaApiDefaults::forBanco($banco),
            $keep,
            ['banco' => $banco],
        );
    }

    public function openBoletoContaEdit(int $id): void
    {
        $conta = $this->findBoletoContaOrFail($id);
        $this->boletoContaEditId = (int) $conta->id;
        $this->boletoContaForm = [
            'nome' => (string) ($conta->nome ?? ''),
            'banco' => $conta->bancoCompe(),
            'ativo' => (bool) $conta->ativo,
            'padrao' => (bool) $conta->padrao,
            'ambiente' => (string) ($conta->ambiente ?: 'homologacao'),
            'convenio' => (string) ($conta->convenio ?? ''),
            'carteira' => (string) ($conta->carteira ?? ''),
            'agencia' => (string) ($conta->agencia ?? ''),
            'agencia_dv' => (string) ($conta->agencia_dv ?? ''),
            'conta' => (string) ($conta->conta ?? ''),
            'conta_dv' => (string) ($conta->conta_dv ?? ''),
            'beneficiario_codigo' => (string) ($conta->beneficiario_codigo ?? ''),
            'client_id' => (string) ($conta->client_id ?? ''),
            'client_secret' => (string) ($conta->client_secret ?? ''),
            'dev_app_key' => (string) ($conta->dev_app_key ?? ''),
            'senha_api' => (string) ($conta->senha_api ?? ''),
            'api_url' => (string) ($conta->api_url ?? ''),
            'callback_url' => (string) ($conta->callback_url ?: EmpresaParametros::BOLETO_AILOS_AUTH_CALLBACK_URL),
            'especie_documento' => (string) ($conta->especie_documento ?: 'DM'),
            'instrucao1' => (string) ($conta->instrucao1 ?? ''),
            'instrucao2' => (string) ($conta->instrucao2 ?? ''),
            'juros_pct' => (string) ($conta->juros_pct ?? ''),
            'multa_pct' => (string) ($conta->multa_pct ?? ''),
            'desconto_pct' => (string) ($conta->desconto_pct ?? ''),
            'protesto_dias' => (string) ($conta->protesto_dias ?? ''),
            'pos_vencimento' => (string) ($conta->pos_vencimento ?: 'nenhuma'),
            'pix_hibrido' => (bool) $conta->pix_hibrido,
        ];
        $this->boletoContaModalOpen = true;
    }

    public function closeBoletoContaModal(): void
    {
        $this->boletoContaModalOpen = false;
        $this->boletoContaEditId = null;
        $this->boletoContaForm = [];
    }

    public function salvarBoletoConta(): void
    {
        $empresa = $this->record;
        if (! $empresa instanceof Empresa || ! $empresa->exists) {
            Notification::make()->title('Salve a empresa antes de cadastrar contas de boleto.')->warning()->send();

            return;
        }

        $data = $this->boletoContaForm;
        $banco = preg_replace('/\D/', '', (string) ($data['banco'] ?? '')) ?? '';

        if (! in_array($banco, [
            EmpresaParametros::BOLETO_BANCO_AILOS,
            EmpresaParametros::BOLETO_BANCO_SICREDI,
        ], true)) {
            throw ValidationException::withMessages(['boletoContaForm.banco' => 'Selecione Ailos ou Sicredi.']);
        }

        $pos = EmpresaParametros::boletoPosVencimentoAcao((object) [
            'param_boleto_pos_vencimento' => $data['pos_vencimento'] ?? 'nenhuma',
        ]);
        $dias = (int) preg_replace('/\D/', '', (string) ($data['protesto_dias'] ?? '')) ?: 0;
        if ($pos !== EmpresaParametros::BOLETO_POS_VENCIMENTO_NENHUMA && ($dias < 1 || $dias > 99)) {
            throw ValidationException::withMessages([
                'boletoContaForm.protesto_dias' => 'Informe os dias (1–99) para protesto/negativação.',
            ]);
        }

        $payload = [
            'empresa_id' => $empresa->id,
            'nome' => trim((string) ($data['nome'] ?? '')) ?: null,
            'banco' => $banco,
            'ativo' => (bool) ($data['ativo'] ?? true),
            'padrao' => (bool) ($data['padrao'] ?? false),
            'ambiente' => (($data['ambiente'] ?? '') === 'producao') ? 'producao' : 'homologacao',
            'convenio' => trim((string) ($data['convenio'] ?? '')) ?: null,
            'carteira' => trim((string) ($data['carteira'] ?? '')) ?: null,
            'agencia' => trim((string) ($data['agencia'] ?? '')) ?: null,
            'agencia_dv' => trim((string) ($data['agencia_dv'] ?? '')) ?: null,
            'conta' => trim((string) ($data['conta'] ?? '')) ?: null,
            'conta_dv' => trim((string) ($data['conta_dv'] ?? '')) ?: null,
            'beneficiario_codigo' => trim((string) ($data['beneficiario_codigo'] ?? '')) ?: null,
            'client_id' => trim((string) ($data['client_id'] ?? '')) ?: null,
            'client_secret' => trim((string) ($data['client_secret'] ?? '')) ?: null,
            'dev_app_key' => trim((string) ($data['dev_app_key'] ?? '')) ?: null,
            'senha_api' => trim((string) ($data['senha_api'] ?? '')) ?: null,
            'api_url' => trim((string) ($data['api_url'] ?? '')) ?: null,
            'callback_url' => $banco === EmpresaParametros::BOLETO_BANCO_AILOS
                ? EmpresaParametros::BOLETO_AILOS_AUTH_CALLBACK_URL
                : (trim((string) ($data['callback_url'] ?? '')) ?: null),
            'especie_documento' => trim((string) ($data['especie_documento'] ?? 'DM')) ?: 'DM',
            'instrucao1' => trim((string) ($data['instrucao1'] ?? '')) ?: null,
            'instrucao2' => trim((string) ($data['instrucao2'] ?? '')) ?: null,
            'juros_pct' => trim((string) ($data['juros_pct'] ?? '')) ?: null,
            'multa_pct' => trim((string) ($data['multa_pct'] ?? '')) ?: null,
            'desconto_pct' => trim((string) ($data['desconto_pct'] ?? '')) ?: null,
            'protesto_dias' => $dias > 0 ? (string) $dias : null,
            'pos_vencimento' => $pos,
            'pix_hibrido' => (bool) ($data['pix_hibrido'] ?? false),
        ];

        DB::transaction(function () use ($empresa, $payload): void {
            if ($this->boletoContaEditId) {
                $conta = $this->findBoletoContaOrFail((int) $this->boletoContaEditId);
                $conta->fill($payload)->save();
            } else {
                $conta = BoletoContaApi::query()->create($payload);
            }

            if ($conta->padrao || ! BoletoContaApi::query()->where('empresa_id', $empresa->id)->where('padrao', true)->exists()) {
                $this->marcarBoletoContaPadraoInternal((int) $conta->id);
            }
        });

        $this->closeBoletoContaModal();
        Notification::make()->title('Conta de cobrança salva.')->success()->send();
    }

    public function marcarBoletoContaPadrao(int $id): void
    {
        $this->marcarBoletoContaPadraoInternal($id);
        Notification::make()->title('Conta padrão atualizada.')->success()->send();
    }

    public function excluirBoletoConta(int $id): void
    {
        $conta = $this->findBoletoContaOrFail($id);
        $eraPadrao = (bool) $conta->padrao;
        $empresaId = (int) $conta->empresa_id;
        $conta->delete();

        if ($eraPadrao) {
            $outra = BoletoContaApi::query()
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->orderBy('id')
                ->first();
            if ($outra) {
                $this->marcarBoletoContaPadraoInternal((int) $outra->id);
            }
        }

        Notification::make()->title('Conta de cobrança removida.')->success()->send();
    }

    private function marcarBoletoContaPadraoInternal(int $id): void
    {
        $conta = $this->findBoletoContaOrFail($id);
        BoletoContaApi::query()
            ->where('empresa_id', $conta->empresa_id)
            ->update(['padrao' => false]);
        $conta->forceFill(['padrao' => true, 'ativo' => true])->save();
    }

    private function findBoletoContaOrFail(int $id): BoletoContaApi
    {
        $empresa = $this->record;
        if (! $empresa instanceof Empresa) {
            throw ValidationException::withMessages(['boletoContaForm' => 'Empresa inválida.']);
        }

        $conta = BoletoContaApi::query()
            ->where('empresa_id', $empresa->id)
            ->whereKey($id)
            ->first();

        if (! $conta) {
            throw ValidationException::withMessages(['boletoContaForm' => 'Conta não encontrada.']);
        }

        return $conta;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultBoletoContaForm(): array
    {
        return BoletoContaApiDefaults::forBanco(EmpresaParametros::BOLETO_BANCO_AILOS);
    }
}
