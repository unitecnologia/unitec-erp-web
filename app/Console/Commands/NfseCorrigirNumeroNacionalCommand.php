<?php

namespace App\Console\Commands;

use App\Models\Nfse;
use App\Support\Erp\Nfse\NfseSefinXmlLeitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Preenche numero_nfse de NFS-e Nacional a partir do nNFSe do XML autorizado.
 * Idempotente: só altera quando o banco difere do XML. Não mexe em DPS/XML/chave/status.
 */
final class NfseCorrigirNumeroNacionalCommand extends Command
{
    protected $signature = 'nfse:corrigir-numero-nacional
        {--ids= : IDs das NFS-e (vírgula). Sem valor, analisa todas com XML Nacional}
        {--empresa= : Filtra por empresa_id}
        {--apply : Grava as correções (sem esta flag, só confere / dry-run)}';

    protected $description = 'Corrige numero_nfse de NFS-e Nacional com o nNFSe do XML autorizado (idempotente)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('apply');
        $ids = $this->parseIds((string) $this->option('ids'));
        $empresaId = filled($this->option('empresa')) ? (int) $this->option('empresa') : null;

        $query = Nfse::query()
            ->orderBy('id')
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids));

        $notas = $query->get([
            'id',
            'empresa_id',
            'status',
            'numero_dps',
            'serie_dps',
            'numero_nfse',
            'chave_acesso',
            'chave',
            'xml_nfse',
            'total',
            'updated_at',
        ]);

        if ($notas->isEmpty()) {
            $this->warn('Nenhuma NFS-e encontrada com os filtros informados.');

            return self::SUCCESS;
        }

        $this->info($aplicar
            ? 'Modo APPLY — gravará somente numero_nfse quando divergir do XML.'
            : 'Modo DRY-RUN — conferência prévia (use --apply para gravar).');

        $linhas = [];
        $corrigir = 0;
        $jaOk = 0;
        $ignoradas = 0;

        foreach ($notas as $nfse) {
            $xml = (string) ($nfse->xml_nfse ?? '');

            if (! $this->xmlNacional($xml)) {
                $ignoradas++;
                $linhas[] = [
                    'id' => $nfse->id,
                    'dps' => (string) ($nfse->numero_dps ?? ''),
                    'banco' => $this->exibir($nfse->numero_nfse),
                    'xml_nNFSe' => '—',
                    'acao' => 'ignorada (não é XML Nacional)',
                ];

                continue;
            }

            $oficial = NfseSefinXmlLeitor::numeroNfse($xml);

            if ($oficial === null) {
                $ignoradas++;
                $linhas[] = [
                    'id' => $nfse->id,
                    'dps' => (string) ($nfse->numero_dps ?? ''),
                    'banco' => $this->exibir($nfse->numero_nfse),
                    'xml_nNFSe' => '—',
                    'acao' => 'ignorada (XML sem nNFSe)',
                ];

                continue;
            }

            $banco = trim((string) ($nfse->numero_nfse ?? ''));

            if ($banco === $oficial) {
                $jaOk++;
                $linhas[] = [
                    'id' => $nfse->id,
                    'dps' => (string) ($nfse->numero_dps ?? ''),
                    'banco' => $banco,
                    'xml_nNFSe' => $oficial,
                    'acao' => 'ok (sem alteração)',
                ];

                continue;
            }

            $corrigir++;
            $linhas[] = [
                'id' => $nfse->id,
                'dps' => (string) ($nfse->numero_dps ?? ''),
                'banco' => $this->exibir($nfse->numero_nfse),
                'xml_nNFSe' => $oficial,
                'acao' => $aplicar ? "corrigido → {$oficial}" : "pendente → {$oficial}",
            ];

            if ($aplicar) {
                $this->aplicarNumero($nfse, $oficial);
            }
        }

        $this->table(['ID', 'DPS', 'numero_nfse banco', 'nNFSe XML', 'Ação'], array_map(
            fn (array $l): array => [$l['id'], $l['dps'], $l['banco'], $l['xml_nNFSe'], $l['acao']],
            $linhas,
        ));

        $this->newLine();
        $this->info(sprintf(
            'Resumo: analisadas=%d | ok=%d | %s=%d | ignoradas=%d',
            $notas->count(),
            $jaOk,
            $aplicar ? 'corrigidas' : 'pendentes',
            $corrigir,
            $ignoradas,
        ));

        if (! $aplicar && $corrigir > 0) {
            $this->comment('Para gravar: php artisan nfse:corrigir-numero-nacional --apply'.($ids !== [] ? ' --ids='.implode(',', $ids) : '').($empresaId ? " --empresa={$empresaId}" : ''));
        }

        return self::SUCCESS;
    }

    private function aplicarNumero(Nfse $nfse, string $oficial): void
    {
        $antes = [
            'numero_nfse' => $nfse->numero_nfse,
            'numero_dps' => $nfse->numero_dps,
            'serie_dps' => $nfse->serie_dps,
            'status' => $nfse->status,
            'chave_acesso' => $nfse->chave_acesso,
            'chave' => $nfse->chave,
            'total' => $nfse->total,
            'xml_len' => strlen((string) $nfse->xml_nfse),
        ];

        // Só a coluna numero_nfse; sem updated_at para não “sujar” outros campos.
        DB::table($nfse->getTable())
            ->where($nfse->getKeyName(), $nfse->getKey())
            ->update(['numero_nfse' => $oficial]);

        $depois = Nfse::query()->whereKey($nfse->getKey())->first([
            'numero_nfse',
            'numero_dps',
            'serie_dps',
            'status',
            'chave_acesso',
            'chave',
            'total',
            'xml_nfse',
        ]);

        if ($depois === null || (string) $depois->numero_nfse !== $oficial) {
            throw new \RuntimeException('Falha ao gravar numero_nfse da NFS-e #'.$nfse->getKey());
        }

        foreach (['numero_dps', 'serie_dps', 'status', 'chave_acesso', 'chave', 'total'] as $campo) {
            if ((string) ($antes[$campo] ?? '') !== (string) ($depois->{$campo} ?? '')) {
                throw new \RuntimeException("Campo {$campo} foi alterado indevidamente na NFS-e #".$nfse->getKey());
            }
        }

        if ($antes['xml_len'] !== strlen((string) $depois->xml_nfse)) {
            throw new \RuntimeException('xml_nfse foi alterado indevidamente na NFS-e #'.$nfse->getKey());
        }
    }

    /**
     * @return list<int>
     */
    private function parseIds(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $ids = [];

        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $parte) {
            if ($parte === '' || ! ctype_digit($parte)) {
                continue;
            }

            $ids[] = (int) $parte;
        }

        return array_values(array_unique($ids));
    }

    private function xmlNacional(string $xml): bool
    {
        if (trim($xml) === '') {
            return false;
        }

        return str_contains($xml, 'www.sped.fazenda.gov.br/nfse')
            || str_contains($xml, 'SefinNacional')
            || preg_match('/<(?:\w+:)?nNFSe>/i', $xml) === 1;
    }

    private function exibir(mixed $valor): string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? '(vazio)' : $texto;
    }
}
