<?php

namespace App\Support\Rh;

use App\Models\RhAnexo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

final class RhAnexoService
{
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
            RhAnexo::CAT_OUTRO,
        ];
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

        $titulo = trim((string) ($meta['titulo'] ?? ''));
        if ($titulo === '') {
            $titulo = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Anexo';
        }

        $path = $file->store('rh-anexos/'.$categoria, 'public');

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

    public function delete(RhAnexo $anexo): void
    {
        if (filled($anexo->caminho) && Storage::disk('public')->exists((string) $anexo->caminho)) {
            Storage::disk('public')->delete((string) $anexo->caminho);
        }

        $anexo->delete();
    }
}
