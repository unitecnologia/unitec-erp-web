<?php

namespace App\Support\Erp\Nfce;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\Person;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Pdv\PdvNfceSimuladaService;
use App\Support\Erp\Pdv\PdvPedidoReportData;
use App\Support\Pdv\PdvOfflineTerminalLookup;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Fiscal\NfceXmlProtocolo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

class NfceCupomReportService
{
    public function __construct(
        protected PdvNfceSimuladaService $cupomService = new PdvNfceSimuladaService,
    ) {}

    public function resolveEmpresa(?int $empresaId = null): ?Empresa
    {
        $empresaId ??= session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : Auth::user()?->empresa;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(PdvVenda $venda, ?Empresa $empresa = null): array
    {
        $empresa ??= $this->resolveEmpresa();

        return $this->cupomService->buildViewData(
            venda: $venda,
            empresa: $empresa,
            usuario: (string) (Auth::user()?->name ?? ''),
            operacao: (string) ($venda->nfce_operacao ?? PdvFinalizarOperacao::NFCE_TRANSMITIR),
            copias: 1,
            autoPrint: false,
        );
    }

    /**
     * @return array{path: string, name: string, display: string}
     */
    public function storePdfAttachment(PdvVenda $venda, ?Empresa $empresa = null): array
    {
        $venda->loadMissing(['nfce']);

        if (($bloqueio = NfceImpressaoFiscal::motivoBloqueio($venda->nfce)) !== null) {
            throw new \DomainException($bloqueio);
        }

        $data = $this->buildViewData($venda, $empresa);
        $directory = storage_path('app/temp/nfce-cupom');

        File::ensureDirectoryExists($directory);

        $numero = str_pad((string) ($venda->nfce?->numero ?? $venda->numero), 9, '0', STR_PAD_LEFT);
        $path = $directory.DIRECTORY_SEPARATOR.'nfce-'.$venda->id.'-'.uniqid('', true).'.pdf';
        $name = 'NFCE.PDF';

        Pdf::loadView('reports.nfce-cupom', $data)
            ->setPaper([0, 0, 226.77, 841.89])
            ->save($path);

        return [
            'path' => $path,
            'name' => $name,
            'display' => $name,
        ];
    }

    /**
     * DANFE para envio ao cliente (F8): mesmo layout que o terminal emissor imprime no F6 — A4 ou térmico.
     *
     * @return array{path: string, name: string, display: string, a4: bool}
     */
    public function storeDanfeEnvioAttachment(PdvVenda $venda, ?Empresa $empresa = null): array
    {
        if (! $this->terminalEmissorImprimeA4($venda)) {
            return [...$this->storePdfAttachment($venda, $empresa), 'a4' => false];
        }

        $venda->load(['itens.product', 'pagamentos', 'person', 'sessao', 'nfce', 'venda', 'vendedor']);

        if (($bloqueio = NfceImpressaoFiscal::motivoBloqueio($venda->nfce)) !== null) {
            throw new \DomainException($bloqueio);
        }

        $viewData = array_merge(
            app(NfceDanfeA4Data::class)->build(
                venda: $venda,
                empresa: $empresa ?? $this->resolveEmpresa(),
                usuario: (string) (Auth::user()?->name ?? ''),
                operacao: (string) ($venda->nfce_operacao ?? PdvFinalizarOperacao::NFCE_TRANSMITIR),
            ),
            ['pdf' => true, 'embed' => false],
        );

        $directory = storage_path('app/temp/nfce-cupom');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.'nfce-a4-'.$venda->id.'-'.bin2hex(random_bytes(8)).'.pdf';

        Pdf::loadView('reports.nfce-danfe-a4', $viewData)
            ->setPaper('a4', 'portrait')
            ->save($path);

        return [
            'path' => $path,
            'name' => 'NFCE.PDF',
            'display' => 'NFCE.PDF',
            'a4' => true,
        ];
    }

    /**
     * Terminal que emitiu a venda (sessão de caixa ou PDV offline); sem vínculo, usa o terminal atual como o F6.
     */
    public function terminalEmissorImprimeA4(PdvVenda $venda): bool
    {
        $venda->loadMissing('sessao.terminal');

        $terminal = $venda->sessao?->terminal;

        if (! $terminal && filled($venda->terminal_offline)) {
            $terminal = PdvOfflineTerminalLookup::find((int) $venda->empresa_id, (string) $venda->terminal_offline, false);
        }

        $tipo = $terminal ? (string) ($terminal->tipo_impressora ?? '1') : PdvConfig::make()->tipoImpressora();

        return $tipo === PdvPedidoReportData::TIPO_IMPRESSORA_NFCE_A4;
    }

    /**
     * @return array{path: string, name: string, display: string}|null
     */
    public function storeXmlAttachment(PdvVendaNfce $nfce): ?array
    {
        $xml = trim((string) ($nfce->xml ?? ''));

        if ($xml === '') {
            return null;
        }

        $chave = preg_replace('/\D/', '', (string) ($nfce->chave ?? '')) ?? '';

        if ($chave === '') {
            return null;
        }

        $directory = storage_path('app/temp/nfce-xml');
        File::ensureDirectoryExists($directory);

        $name = $chave.'.xml';
        $path = $directory.DIRECTORY_SEPARATOR.bin2hex(random_bytes(8)).'-'.$name;

        file_put_contents($path, $xml);

        return [
            'path' => $path,
            'name' => $name,
            'display' => $name,
        ];
    }

    /**
     * Envio ao cliente exige NFC-e autorizada, com chave/protocolo e XML com protNFe da própria chave.
     */
    public function motivoBloqueioEnvio(?PdvVendaNfce $nfce): ?string
    {
        if ($nfce === null) {
            return 'NFC-e não encontrada.';
        }

        $numero = 'NFC-e nº '.($nfce->numero ?: '—');

        if ($nfce->simulada || (string) $nfce->status === PdvVendaNfce::STATUS_SIMULADA) {
            return $numero.' é simulada (sem valor fiscal) e não pode ser enviada.';
        }

        return match ((string) $nfce->status) {
            PdvVendaNfce::STATUS_AUTORIZADA => $this->motivoBloqueioXmlAutorizado($nfce, $numero),
            PdvVendaNfce::STATUS_CANCELADA => $numero.' está cancelada e não pode ser enviada ao cliente.',
            PdvVendaNfce::STATUS_CONTINGENCIA => $numero.' em contingência ainda não foi autorizada. Transmita (F5) antes de enviar.',
            default => NfceImpressaoFiscal::motivoBloqueio($nfce) ?? $numero.' não está autorizada.',
        };
    }

    protected function motivoBloqueioXmlAutorizado(PdvVendaNfce $nfce, string $numero): ?string
    {
        $chave = (string) $nfce->chave;

        if (preg_match('/^\d{44}$/', $chave) !== 1 || blank($nfce->protocolo)) {
            return $numero.' autorizada sem chave/protocolo gravados. Use F4 Recuperar antes de enviar.';
        }

        $protNFe = NfceXmlProtocolo::protNFe((string) ($nfce->xml ?? ''));

        if ($protNFe === null || NfceXmlProtocolo::chaveDoProtocolo($protNFe) !== $chave) {
            return 'XML autorizado da '.$numero.' não encontrado. Use F4 Recuperar antes de enviar.';
        }

        return null;
    }

    public function resolveClienteEmail(PdvVenda $venda): string
    {
        foreach ($this->clientePessoas($venda) as $person) {
            foreach (['email', 'email2'] as $campo) {
                $email = trim((string) ($person->{$campo} ?? ''));

                if ($email !== '') {
                    return $email;
                }
            }
        }

        return '';
    }

    public function resolveClienteWhatsApp(PdvVenda $venda): string
    {
        foreach ($this->clientePessoas($venda) as $person) {
            foreach (['whatsapp', 'celular1', 'celular2', 'fone1'] as $campo) {
                $fone = WhatsAppPhone::formatDisplay(trim((string) ($person->{$campo} ?? '')));

                if ($fone !== '') {
                    return $fone;
                }
            }
        }

        return '';
    }

    /**
     * Cliente da venda e, como reserva, o cadastro do CPF/CNPJ informado na nota.
     *
     * @return list<Person>
     */
    protected function clientePessoas(PdvVenda $venda): array
    {
        $venda->loadMissing('person');
        $pessoas = $venda->person ? [$venda->person] : [];

        $cpf = preg_replace('/\D/', '', (string) ($venda->cpf_nota ?? '')) ?? '';

        if ($cpf !== '') {
            $person = Person::query()
                ->whereRaw("REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') = ?", [$cpf])
                ->first();

            if ($person && $person->id !== $venda->person?->id) {
                $pessoas[] = $person;
            }
        }

        return $pessoas;
    }

    public function formatNumero(?string $numero): string
    {
        if (blank($numero)) {
            return '';
        }

        $digits = (int) preg_replace('/\D/', '', $numero);

        return $digits > 0 ? (string) $digits : $numero;
    }

    public function defaultEmailSubject(PdvVendaNfce $nfce, PdvVenda $venda, ?Empresa $empresa): string
    {
        $numero = $this->formatNumero((string) ($nfce->numero ?? $venda->numero));

        return 'NFCE N.'.$numero;
    }

    public function defaultEmailMessage(PdvVendaNfce $nfce, PdvVenda $venda, ?Empresa $empresa): string
    {
        $numero = $this->formatNumero((string) ($nfce->numero ?? $venda->numero));

        return 'SEGUE EM ANEXO NFCE N.'.$numero;
    }
}
