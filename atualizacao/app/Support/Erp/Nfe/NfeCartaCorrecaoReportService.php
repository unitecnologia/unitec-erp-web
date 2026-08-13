<?php

namespace App\Support\Erp\Nfe;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeCartaCorrecao;
use App\Support\Erp\Compra\CompraDanfeReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Unitec\FiscalEngine\Xml\NfeCartaCorrecaoLiterals;

final class NfeCartaCorrecaoReportService
{
    public const X_COND_USO = NfeCartaCorrecaoLiterals::X_COND_USO;

    public function __construct(
        private readonly CompraDanfeReportService $danfe = new CompraDanfeReportService(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(
        NfeCartaCorrecao $carta,
        ?Empresa $empresa = null,
        bool $autoPrint = false,
        ?Carbon $printedAt = null,
    ): array {
        $carta->loadMissing(['nfe.cliente', 'nfe.transportadora', 'nfe.empresa']);
        $nfe = $carta->nfe;
        $empresa ??= $nfe?->empresa ?? $this->danfe->resolveEmpresa($nfe?->empresa_id);
        $printedAt ??= $carta->created_at ?? now();
        $chave = (string) ($nfe?->chave ?? '');
        $protocolo = (string) ($carta->protocolo ?? '');

        return [
            'carta' => $carta,
            'nfe' => $nfe,
            'empresa' => $empresa,
            'emitente' => $this->buildEmitente($empresa),
            'destinatario' => $this->buildDestinatario($nfe),
            'chaveFormatada' => $chave !== '' ? $this->danfe->formatChave($chave) : '—',
            'numeroNota' => str_pad(ltrim((string) ($nfe?->numero ?? '0'), '0') ?: '0', 9, '0', STR_PAD_LEFT),
            'serie' => str_pad(ltrim((string) ($nfe?->serie ?? '1'), '0') ?: '1', 3, '0', STR_PAD_LEFT),
            'protocoloNfe' => trim((string) ($nfe?->protocolo ?? '')),
            'protocoloEvento' => $protocolo,
            'protocoloFormatado' => $this->formatarProtocolo($protocolo),
            'sequencia' => (int) $carta->sequencia,
            'correcao' => trim((string) $carta->correcao),
            'condicoesUso' => self::X_COND_USO,
            'dataEvento' => $printedAt->format('d/m/Y'),
            'horaEvento' => $printedAt->format('H:i:s'),
            'autoPrint' => $autoPrint,
            'printedAt' => $printedAt,
        ];
    }

    /**
     * @return array{path: string, name: string, display: string}
     */
    public function storePdfAttachment(NfeCartaCorrecao $carta, ?Empresa $empresa = null): array
    {
        $data = $this->buildViewData($carta, $empresa);
        $directory = storage_path('app/temp/nfe-cce');

        File::ensureDirectoryExists($directory);

        $path = $directory . DIRECTORY_SEPARATOR . 'cce-' . $carta->id . '-' . uniqid('', true) . '.pdf';
        $numeroDigits = preg_replace('/\D/', '', (string) ($data['nfe']?->numero ?? '')) ?: (string) $carta->id;
        $name = 'CCE-NFE-' . $numeroDigits . '-SEQ' . $carta->sequencia . '.PDF';

        Pdf::loadView('reports.nfe-carta-correcao-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->save($path);

        return [
            'path' => $path,
            'name' => $name,
            'display' => $name,
        ];
    }

    public function defaultWhatsAppMessage(NfeCartaCorrecao $carta): string
    {
        $carta->loadMissing('nfe');
        $nfe = $carta->nfe;
        $numero = ltrim((string) ($nfe?->numero ?? ''), '0') ?: (string) ($nfe?->numero ?? '');
        $chave = $nfe?->chave ? $this->danfe->formatChave($nfe->chave) : '';

        $lines = array_filter([
            'Segue a Carta de Correção Eletrônica (CC-e) da NF-e:',
            filled($numero) ? "Nota: {$numero} | Série: {$nfe?->serie}" : null,
            'Sequência CC-e: ' . $carta->sequencia,
            filled($carta->protocolo) ? "Protocolo: {$carta->protocolo}" : null,
            filled($chave) ? "Chave: {$chave}" : null,
        ]);

        return implode("\n", $lines);
    }

    public function defaultEmailSubject(NfeCartaCorrecao $carta): string
    {
        $carta->loadMissing('nfe');
        $numero = ltrim((string) ($carta->nfe?->numero ?? ''), '0') ?: (string) ($carta->nfe?->numero ?? '');

        return 'CC-e NF-e ' . $numero . ' — Seq. ' . $carta->sequencia;
    }

    public function defaultEmailMessage(NfeCartaCorrecao $carta, string $destinatarioNome = ''): string
    {
        $saudacao = filled($destinatarioNome)
            ? 'Prezado(a) ' . $destinatarioNome . ','
            : 'Prezado(a),';

        return $saudacao . "\n\n"
            . "Segue em anexo a Carta de Correção Eletrônica (CC-e) referente à NF-e.\n\n"
            . $this->defaultWhatsAppMessage($carta);
    }

    /**
     * @return array{email: string, phoneDigits: string, nome: string}
     */
    public function resolveDestinatarioContato(Nfe $nfe, string $tipo): array
    {
        $nfe->loadMissing(['cliente', 'transportadora']);

        if ($tipo === 'transportadora') {
            $transportadora = $nfe->transportadora;
            $nome = trim((string) ($transportadora?->proprietario ?: $transportadora?->apelido ?: ''));
            $phoneDigits = preg_replace('/\D/', '', (string) ($transportadora?->whatsapp ?? '')) ?? '';

            return [
                'email' => '',
                'phoneDigits' => $phoneDigits,
                'nome' => $nome,
            ];
        }

        $person = $nfe->cliente;
        $nome = trim((string) ($person?->nome_razao ?? $person?->nome ?? ''));
        $email = trim((string) ($person?->email ?? $person?->email2 ?? ''));
        $phoneDigits = preg_replace('/\D/', '', (string) ($person?->celular1 ?: ($person?->whatsapp ?: ($person?->celular2 ?? '')))) ?? '';

        return [
            'email' => $email,
            'phoneDigits' => $phoneDigits,
            'nome' => $nome,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildEmitente(?Empresa $empresa): array
    {
        if ($empresa === null) {
            return [
                'nome' => 'UNITEC',
                'fantasia' => 'UNITEC',
                'cnpj' => '',
                'ie' => '',
                'endereco' => '',
                'municipio' => '',
                'uf' => '',
            ];
        }

        $endereco = trim(implode(', ', array_filter([
            trim((string) ($empresa->endereco ?? '')),
            filled($empresa->numero) ? 'nº ' . $empresa->numero : null,
            trim((string) ($empresa->bairro ?? '')),
        ])));

        return [
            'nome' => mb_strtoupper((string) ($empresa->razao_social ?: $empresa->nome ?: $empresa->fantasia), 'UTF-8'),
            'fantasia' => mb_strtoupper((string) ($empresa->fantasia ?: $empresa->nome), 'UTF-8'),
            'cnpj' => $this->formatCnpj((string) $empresa->cnpj),
            'ie' => (string) ($empresa->ie ?? ''),
            'endereco' => mb_strtoupper($endereco, 'UTF-8'),
            'municipio' => mb_strtoupper((string) ($empresa->cidade ?? ''), 'UTF-8'),
            'uf' => (string) ($empresa->uf ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildDestinatario(?Nfe $nfe): array
    {
        $cliente = $nfe?->cliente;

        if ($cliente === null) {
            return [
                'nome' => '—',
                'documento' => '—',
            ];
        }

        $documento = filled($cliente->cnpj)
            ? $this->formatCnpj((string) $cliente->cnpj)
            : $this->formatCpf((string) ($cliente->cpf ?? ''));

        return [
            'nome' => mb_strtoupper((string) ($cliente->nome_razao ?? $cliente->nome ?? ''), 'UTF-8'),
            'documento' => $documento !== '' ? $documento : '—',
        ];
    }

    private function formatCnpj(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 14) {
            return $value;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
    }

    private function formatCpf(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 11) {
            return $value;
        }

        return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digits) ?: $value;
    }

    private function formatarProtocolo(string $protocolo): string
    {
        $digits = preg_replace('/\D/', '', $protocolo) ?? '';

        if (strlen($digits) < 15) {
            return $protocolo;
        }

        return substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3) . ' ' . substr($digits, 9);
    }
}
