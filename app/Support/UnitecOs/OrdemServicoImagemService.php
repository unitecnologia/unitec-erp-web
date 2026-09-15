<?php

namespace App\Support\UnitecOs;

use App\Models\OrdemServico;
use App\Models\OrdemServicoImagem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class OrdemServicoImagemService
{
    public function storeFoto(OrdemServico $os, User $user, UploadedFile $file): OrdemServicoImagem
    {
        $item = (int) ($os->imagens()->where('tipo', OrdemServicoImagem::TIPO_FOTO)->max('item') ?? 0) + 1;
        $path = $file->store('os-imagens/'.$os->id.'/fotos', 'public');

        return $os->imagens()->create([
            'tipo' => OrdemServicoImagem::TIPO_FOTO,
            'empresa_id' => $os->empresa_id,
            'usuario_id' => $user->id,
            'atendente_id' => $user->vendedor_id ? (int) $user->vendedor_id : $os->atendente_id,
            'item' => $item,
            'caminho' => $path,
            'mime' => $file->getMimeType(),
            'tamanho' => $file->getSize(),
        ]);
    }

    public function storeAssinatura(OrdemServico $os, User $user, UploadedFile $file): OrdemServicoImagem
    {
        $this->replaceAssinatura($os);

        $path = $file->store('os-imagens/'.$os->id.'/assinatura', 'public');

        return $os->imagens()->create([
            'tipo' => OrdemServicoImagem::TIPO_ASSINATURA,
            'empresa_id' => $os->empresa_id,
            'usuario_id' => $user->id,
            'atendente_id' => $user->vendedor_id ? (int) $user->vendedor_id : $os->atendente_id,
            'item' => 1,
            'caminho' => $path,
            'mime' => $file->getMimeType() ?: 'image/png',
            'tamanho' => $file->getSize(),
        ]);
    }

    public function replaceAssinatura(OrdemServico $os): void
    {
        $anteriores = $os->imagens()
            ->where('tipo', OrdemServicoImagem::TIPO_ASSINATURA)
            ->get();

        foreach ($anteriores as $img) {
            $this->deleteFileAndRow($img);
        }
    }

    public function deleteFileAndRow(OrdemServicoImagem $img): void
    {
        if (filled($img->caminho) && Storage::disk('public')->exists((string) $img->caminho)) {
            Storage::disk('public')->delete((string) $img->caminho);
        }
        $img->delete();
    }
}
