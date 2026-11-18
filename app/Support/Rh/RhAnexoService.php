<?php

namespace App\Support\Rh;

use App\Models\RhAnexo;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ErpContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class RhAnexoService
{
    public const DISK = 'local';

    public const MAX_BYTES = 5_242_880; // 5 MB

    /**
     * @return list<string>
     */
    public static function categorias(): array
    {
        return [
            RhAnexo::CAT_DOCUMENTO,
            RhAnexo::CAT_EPI,
            RhAnexo::CAT_EXAME,
            RhAnexo::CAT_TREINAMENTO,
            RhAnexo::CAT_UNIFORME,
            RhAnexo::CAT_HOLERITE,
            RhAnexo::CAT_OCORRENCIA,
            RhAnexo::CAT_FERIAS,
            RhAnexo::CAT_OUTRO,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
    }

    /**
     * @param  array{titulo: string, categoria: string, emitido_em?: string|null, valido_ate?: string|null, entregue_em?: string|null, observacao?: string|null}  $meta
     */
    public function store(Model $anexavel, UploadedFile $file, array $meta): RhAnexo
    {
        $categoria = (string) ($meta['categoria'] ?? RhAnexo::CAT_OUTRO);

        if (! in_array($categoria, self::categorias(), true)) {
            throw new InvalidArgumentException('Categoria de anexo inválida.');
        }

        $this->assertUpload($file);

        $titulo = trim((string) ($meta['titulo'] ?? ''));
        if ($titulo === '') {
            $titulo = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Anexo';
        }

        $path = $file->store('rh-anexos/'.$categoria, self::DISK);

        return $anexavel->anexos()->create([
            'categoria' => $categoria,
            'titulo' => mb_strtoupper($titulo, 'UTF-8'),
            'caminho' => $path,
            'mime' => $file->getMimeType(),
            'tamanho' => $file->getSize(),
            'emitido_em' => $meta['emitido_em'] ?? null,
            'valido_ate' => $meta['valido_ate'] ?? null,
            'entregue_em' => $meta['entregue_em'] ?? null,
            'observacao' => $meta['observacao'] ?? null,
            'ativo' => true,
        ]);
    }

    /**
     * @param  array{titulo?: string, emitido_em?: string|null, valido_ate?: string|null, entregue_em?: string|null, observacao?: string|null, ativo?: bool}  $meta
     */
    public function updateMeta(RhAnexo $anexo, array $meta, ?UploadedFile $file = null): RhAnexo
    {
        if ($file instanceof UploadedFile) {
            $this->assertUpload($file);
            $this->deleteStoredFile($anexo);
            $categoria = (string) $anexo->categoria;
            $path = $file->store('rh-anexos/'.$categoria, self::DISK);
            $anexo->caminho = $path;
            $anexo->mime = $file->getMimeType();
            $anexo->tamanho = $file->getSize();
        }

        if (array_key_exists('titulo', $meta)) {
            $titulo = trim((string) $meta['titulo']);
            if ($titulo !== '') {
                $anexo->titulo = mb_strtoupper($titulo, 'UTF-8');
            }
        }

        foreach (['emitido_em', 'valido_ate', 'entregue_em', 'observacao'] as $field) {
            if (array_key_exists($field, $meta)) {
                $anexo->{$field} = $meta[$field];
            }
        }

        if (array_key_exists('ativo', $meta)) {
            $anexo->ativo = (bool) $meta['ativo'];
        }

        $anexo->save();

        return $anexo;
    }

    public function delete(RhAnexo $anexo): void
    {
        $snapshot = [
            'id' => (int) $anexo->id,
            'titulo' => $anexo->titulo,
            'categoria' => $anexo->categoria,
            'anexavel_type' => $anexo->anexavel_type,
            'anexavel_id' => $anexo->anexavel_id ? (int) $anexo->anexavel_id : null,
            'caminho' => $anexo->caminho,
            'emitido_em' => optional($anexo->emitido_em)?->toDateString(),
            'valido_ate' => optional($anexo->valido_ate)?->toDateString(),
        ];

        $this->deleteStoredFile($anexo);

        DB::transaction(function () use ($anexo, $snapshot): void {
            $anexo->delete();

            // Sem tabela rh_anexo_logs: Log de Operações com payload no padrão antes/ação.
            app(ErpOperacaoLogService::class)->registrar(
                operacao: 'EXCLUIR_RH_DOCUMENTO',
                resumo: 'Documento RH excluído: '.($snapshot['titulo'] ?: '#'.$snapshot['id']).'.',
                origem: 'rh_documentos',
                documentoTipo: 'rh_documento',
                documentoId: (int) $snapshot['id'],
                documentoNumero: (string) ($snapshot['titulo'] ?: $snapshot['id']),
                detalhes: [
                    'acao' => 'excluir',
                    'antes' => $snapshot,
                ],
                empresaId: ErpContext::currentEmpresaId(),
            );
        });
    }

    public function absolutePath(RhAnexo $anexo): ?string
    {
        $caminho = trim((string) ($anexo->caminho ?? ''));

        if ($caminho === '') {
            return null;
        }

        if (Storage::disk(self::DISK)->exists($caminho)) {
            return Storage::disk(self::DISK)->path($caminho);
        }

        // Compatível com anexos antigos gravados no disco público.
        if (Storage::disk('public')->exists($caminho)) {
            return Storage::disk('public')->path($caminho);
        }

        return null;
    }

    private function assertUpload(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Arquivo inválido.');
        }

        if ((int) $file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('Arquivo maior que 5 MB.');
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($ext, self::allowedExtensions(), true)) {
            throw new InvalidArgumentException('Tipo de arquivo não permitido.');
        }
    }

    private function deleteStoredFile(RhAnexo $anexo): void
    {
        $caminho = trim((string) ($anexo->caminho ?? ''));

        if ($caminho === '') {
            return;
        }

        if (Storage::disk(self::DISK)->exists($caminho)) {
            Storage::disk(self::DISK)->delete($caminho);
        }

        if (Storage::disk('public')->exists($caminho)) {
            Storage::disk('public')->delete($caminho);
        }
    }
}
