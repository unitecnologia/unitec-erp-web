<?php

namespace App\Support\Erp\Os;

use App\Models\Empresa;
use App\Models\OrdemServico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

/**
 * PDF/anexo da OS para e-mail e WhatsApp (mesmo padrão orçamento/recibo).
 */
final class OrdemServicoReportService
{
    public function resolveEmpresa(?int $empresaId = null): ?Empresa
    {
        $empresaId ??= session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : Auth::user()?->empresa;
    }

    /**
     * @return array{path: string, name: string, display: string}
     */
    public function storePdfAttachment(
        OrdemServico $ordem,
        ?Empresa $empresa = null,
        bool $leveParaEnvio = false,
        bool $tecnica = false,
    ): array {
        $empresa ??= $this->resolveEmpresa((int) ($ordem->empresa_id ?: 0) ?: null);
        $data = OrdemServicoReportData::for(
            $ordem,
            $empresa,
            autoPrint: false,
            embedded: true,
            omitMidias: $leveParaEnvio || $tecnica,
            preferPagamentosSnapshot: $leveParaEnvio,
            tecnica: $tecnica,
        );
        $directory = storage_path('app/temp/ordens-servico');
        File::ensureDirectoryExists($directory);

        $numero = preg_replace('/\D+/', '', (string) ($data['numero'] ?? $ordem->numero)) ?: (string) $ordem->id;
        $path = $directory.DIRECTORY_SEPARATOR.'os-'.$ordem->id.($tecnica ? '-tecnica-' : '-').uniqid('', true).'.pdf';
        $name = 'OS-'.$numero.($tecnica ? '-tecnica' : '').'.pdf';

        Pdf::loadView('reports.ordem-servico-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->save($path);

        return [
            'path' => $path,
            'name' => $name,
            'display' => $name,
        ];
    }

    public function formatNumero(OrdemServico|string|int|null $ordemOrNumero): string
    {
        if ($ordemOrNumero instanceof OrdemServico) {
            $ordemOrNumero = $ordemOrNumero->numero;
        }

        $raw = trim((string) ($ordemOrNumero ?? ''));
        if ($raw === '') {
            return '';
        }

        $digits = (int) preg_replace('/\D/', '', $raw);

        return $digits > 0 ? (string) $digits : $raw;
    }

    public function defaultEmailMessage(string $numero, array $anexoLabels = [], ?OrdemServico $ordem = null): string
    {
        if ($ordem instanceof OrdemServico) {
            return $this->montarMensagemEnvioOs($numero, $ordem, tecnica: false);
        }

        $base = 'SEGUE EM ANEXO A ORDEM DE SERVICO N.'.$numero;

        if ($anexoLabels === []) {
            return $base.'.';
        }

        return $base.' E DOCUMENTOS VINCULADOS: '.implode(', ', $anexoLabels).'.';
    }

    /**
     * @param  list<string>  $anexoLabels
     */
    public function defaultEmailSubject(string $numero, array $anexoLabels = []): string
    {
        if ($anexoLabels === [] || (count($anexoLabels) === 1 && str_starts_with($anexoLabels[0], 'OS'))) {
            return 'ORDEM DE SERVICO N.'.$numero;
        }

        return 'OS N.'.$numero.' + DOCUMENTOS';
    }

    public function defaultTecnicaEmailSubject(string $numero): string
    {
        return 'OS TECNICA N.'.$numero;
    }

    public function defaultTecnicaEmailMessage(string $numero, ?OrdemServico $ordem = null): string
    {
        return $this->montarMensagemEnvioOs($numero, $ordem, tecnica: true);
    }

    /**
     * Texto padrão do modal de envio da OS (editável). O rodapé do WhatsApp
     * é aplicado na hora do envio.
     */
    public function montarMensagemEnvioOs(string $numero, ?OrdemServico $ordem, bool $tecnica): string
    {
        $titulo = $tecnica
            ? 'OS Técnica nº '.$numero
            : 'Ordem de Serviço nº '.$numero;

        if (! $ordem instanceof OrdemServico) {
            return $titulo;
        }

        $linhas = [
            $titulo,
            '',
            'Cliente: '.$this->nomeClienteMensagemOs($ordem),
            'Equipamento: '.$this->equipamentoMensagemOs($ordem),
        ];

        $placa = $this->placaMensagemOs($ordem);
        if ($placa !== '') {
            $linhas[] = 'Placa: '.$placa;
        }

        return implode("\n", $linhas);
    }

    private function nomeClienteMensagemOs(OrdemServico $ordem): string
    {
        $nome = trim((string) ($ordem->nome ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        if ($ordem->relationLoaded('cliente')) {
            return trim((string) ($ordem->cliente?->nome_razao ?? ''));
        }

        return '';
    }

    private function equipamentoMensagemOs(OrdemServico $ordem): string
    {
        $marca = trim((string) ($ordem->descricao ?: $ordem->marca ?: $ordem->marca_veiculo ?: ''));
        $modelo = trim((string) ($ordem->modelo ?: $ordem->modelo_veiculo ?: ''));

        if ($modelo === '') {
            return $marca;
        }

        if ($marca === '') {
            return $modelo;
        }

        return $marca.' '.$modelo;
    }

    private function placaMensagemOs(OrdemServico $ordem): string
    {
        return trim((string) ($ordem->placa ?: $ordem->placa_veiculo ?: ''));
    }
}
