<?php

namespace Tests\Unit;

use App\Support\Erp\WindowsSafeFilesystem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WindowsSafeFilesystemTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'unitec-wsfs-'.bin2hex(random_bytes(4));
        mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        $fs = new WindowsSafeFilesystem;
        if (is_dir($this->tmp)) {
            $fs->deleteDirectory($this->tmp);
        }
        parent::tearDown();
    }

    #[Test]
    public function replace_grava_arquivo_novo_e_sobrescreve(): void
    {
        $fs = new WindowsSafeFilesystem;
        $path = $this->tmp.DIRECTORY_SEPARATOR.'manifest.php';

        $fs->replace($path, "<?php return ['a'=>1];\n");
        $this->assertFileExists($path);
        $this->assertStringContainsString("'a'=>1", (string) file_get_contents($path));

        $fs->replace($path, "<?php return ['a'=>2];\n");
        $this->assertStringContainsString("'a'=>2", (string) file_get_contents($path));
    }

    #[Test]
    public function clear_compiled_views_directory_esvazia_pasta(): void
    {
        $views = $this->tmp.DIRECTORY_SEPARATOR.'views';
        mkdir($views, 0777, true);
        file_put_contents($views.DIRECTORY_SEPARATOR.'abc.php', '<?php // compiled');
        file_put_contents($views.DIRECTORY_SEPARATOR.'def.php', '<?php // compiled');

        $fs = new WindowsSafeFilesystem;
        $fs->clearCompiledViewsDirectory($views);

        $this->assertDirectoryExists($views);
        $left = array_values(array_filter(
            scandir($views) ?: [],
            fn ($n) => $n !== '.' && $n !== '..'
        ));
        $this->assertSame([], $left);
    }
}
