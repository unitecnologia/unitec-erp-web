<?php

namespace Tests\Unit;

use App\Models\RhAnexo;
use App\Support\Rh\RhAnexoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class RhAnexoServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rejeita_extensao_nao_permitida(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');
        $anexavel = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
        $anexavel->shouldNotReceive('anexos');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipo de arquivo não permitido.');

        (new RhAnexoService)->store($anexavel, $file, [
            'titulo' => 'X',
            'categoria' => RhAnexo::CAT_DOCUMENTO,
        ]);
    }

    public function test_rejeita_arquivo_maior_que_5mb(): void
    {
        Storage::fake(RhAnexoService::DISK);

        $file = UploadedFile::fake()->create('grande.pdf', 6_000, 'application/pdf');
        $anexavel = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
        $anexavel->shouldNotReceive('anexos');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Arquivo maior que 5 MB.');

        (new RhAnexoService)->store($anexavel, $file, [
            'titulo' => 'X',
            'categoria' => RhAnexo::CAT_DOCUMENTO,
        ]);
    }

    public function test_categorias_incluem_documento(): void
    {
        $this->assertContains(RhAnexo::CAT_DOCUMENTO, RhAnexoService::categorias());
        $this->assertContains('pdf', RhAnexoService::allowedExtensions());
    }

    public function test_absolute_path_retorna_null_sem_caminho(): void
    {
        $anexo = new RhAnexo(['caminho' => null]);

        $this->assertNull((new RhAnexoService)->absolutePath($anexo));
    }
}
