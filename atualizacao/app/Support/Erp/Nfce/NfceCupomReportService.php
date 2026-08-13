<?php

namespace App\Support\Erp\Nfce;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\Person;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\PdvNfceSimuladaService;
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
        $path = $directory.DIRECTORY_SEPARATOR.$name.'-'.uniqid('', true);

        file_put_contents($path, $xml);

        return [
            'path' => $path,
            'name' => $name,
            'display' => $name,
        ];
    }

    public function resolveClienteEmail(PdvVenda $venda): string
    {
        $venda->loadMissing('person');

        $email = trim((string) ($venda->person?->email ?? ''));

        if ($email !== '') {
            return $email;
        }

        $email2 = trim((string) ($venda->person?->email2 ?? ''));

        if ($email2 !== '') {
            return $email2;
        }

        $cpf = preg_replace('/\D/', '', (string) ($venda->cpf_nota ?? '')) ?? '';

        if ($cpf !== '') {
            $person = Person::query()
                ->whereRaw("REPLACE(REPLACE(REPLACE(cpf_cnpj, '.', ''), '-', ''), '/', '') = ?", [$cpf])
                ->first();

            if (filled($person?->email)) {
                return trim((string) $person->email);
            }

            if (filled($person?->email2)) {
                return trim((string) $person->email2);
            }
        }

        return '';
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
