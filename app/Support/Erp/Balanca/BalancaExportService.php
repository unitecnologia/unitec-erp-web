<?php

namespace App\Support\Erp\Balanca;

use App\Models\Product;
use App\Support\Erp\ProductEmpresaPrecoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

final class BalancaExportService
{
    public function __construct(
        private readonly ?ProductEmpresaPrecoService $precos = null,
    ) {}

    /**
     * Produtos com código de balança (prefixo_balanca) preenchido.
     *
     * @return Collection<int, Product>
     */
    public function productsForExport(): Collection
    {
        return Product::query()
            ->whereNotNull('prefixo_balanca')
            ->where('prefixo_balanca', '!=', '')
            ->where('ativo', true)
            ->orderBy('prefixo_balanca')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descricao', 'unidade', 'preco_venda', 'prefixo_balanca', 'produto_pesado', 'validade']);
    }

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     produtos: int,
     *     modelo: string,
     *     diretorio: string,
     *     wrote_to_disk: bool,
     *     disk_error: string|null,
     *     files: list<array{name: string, bytes: int}>,
     *     download_path: string|null,
     *     download_name: string|null
     * }
     */
    public function generate(string $modelo, string $diretorio, ?int $empresaId = null, ?int $digitos = null): array
    {
        $modelo = BalancaModel::normalize($modelo);
        $diretorio = $this->normalizeDirectory($diretorio);
        $digitos = $this->resolveDigitos($digitos, $empresaId);
        $products = $this->productsForExport();

        if ($products->isEmpty()) {
            return [
                'ok' => false,
                'message' => 'Nenhum produto com código balança (Prefixo) preenchido.',
                'produtos' => 0,
                'modelo' => $modelo,
                'diretorio' => $diretorio,
                'wrote_to_disk' => false,
                'disk_error' => null,
                'files' => [],
                'download_path' => null,
                'download_name' => null,
            ];
        }

        $contents = $this->buildFiles($modelo, $products, $empresaId, $digitos);
        $filesMeta = [];

        foreach ($contents as $name => $body) {
            $filesMeta[] = [
                'name' => $name,
                'bytes' => strlen($body),
            ];
        }

        $wrote = false;
        $diskError = null;

        try {
            File::ensureDirectoryExists($diretorio);
            foreach ($contents as $name => $body) {
                $path = rtrim($diretorio, "\\/").DIRECTORY_SEPARATOR.$name;
                if (file_put_contents($path, $body) === false) {
                    throw new \RuntimeException('Falha ao gravar '.$name);
                }
            }
            $wrote = true;
        } catch (Throwable $e) {
            $diskError = $e->getMessage();
        }

        [$downloadPath, $downloadName] = $this->storeDownloadBundle($modelo, $contents);

        $message = $wrote
            ? sprintf(
                'Arquivo gerado: %d produto(s) — %s em %s',
                $products->count(),
                BalancaModel::formatLabel($modelo),
                $diretorio
            )
            : sprintf(
                'Arquivo gerado em memória (%d produto(s)), mas não foi possível gravar em %s. Use o download.%s',
                $products->count(),
                $diretorio,
                $diskError ? ' ('.$diskError.')' : ''
            );

        return [
            'ok' => true,
            'message' => $message,
            'produtos' => $products->count(),
            'modelo' => $modelo,
            'diretorio' => $diretorio,
            'wrote_to_disk' => $wrote,
            'disk_error' => $diskError,
            'files' => $filesMeta,
            'download_path' => $downloadPath,
            'download_name' => $downloadName,
        ];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, string> filename => content
     */
    public function buildFiles(string $modelo, Collection $products, ?int $empresaId = null, ?int $digitos = null): array
    {
        $modelo = BalancaModel::normalize($modelo);
        $digitos = $this->resolveDigitos($digitos, $empresaId);

        return match ($modelo) {
            BalancaModel::FILIZOLA => $this->buildFilizola($products, $empresaId, $digitos),
            BalancaModel::TOLEDO, BalancaModel::TOLEDO_MGV5 => [
                'TXITENS.TXT' => $this->buildTxItens($products, $empresaId, $digitos),
            ],
            BalancaModel::TOLEDO_MGV6, BalancaModel::TOLEDO_MGV7 => [
                'ITENSMGV.TXT' => $this->buildItensMgv($products, $empresaId, $digitos),
            ],
            BalancaModel::URANO, BalancaModel::URANO_S, BalancaModel::URANO_URF32 => [
                'Produtos.txt' => $this->buildUrano($products, $empresaId, $digitos),
            ],
            default => $this->buildFilizola($products, $empresaId, $digitos),
        };
    }

    public function normalizeDirectory(?string $diretorio): string
    {
        $diretorio = trim((string) $diretorio);
        if ($diretorio === '') {
            return BalancaModel::DEFAULT_DIRECTORY;
        }

        return rtrim(str_replace('/', '\\', $diretorio), "\\/");
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, string>
     */
    protected function buildFilizola(Collection $products, ?int $empresaId, int $digitos = 6): array
    {
        $cad = '';
        $setor = '';
        $indice = 1;

        foreach ($products as $product) {
            $codigo = $this->scaleCode($product, $digitos);
            $tipo = $this->isUnitProduct($product) ? 'U' : 'P';
            $desc = $this->padRight($this->sanitizeAscii($product->descricao ?? ''), 22);
            $preco = $this->digitsPrice($this->price($product, $empresaId), 7);
            $validade = $this->validityDays($product, 3);

            $cad .= $codigo.$tipo.$desc.$preco.$validade."\r\n";
            $setor .= $this->padRight('GERAL', 12).$codigo.$this->padLeft((string) $indice, 4).'000'."\r\n";
            $indice++;
        }

        return [
            'CADTXT.TXT' => $cad,
            'SETORTXT.TXT' => $setor,
        ];
    }

    /**
     * Toledo clássico / MGV5 — TXITENS.TXT
     * Layout: DD(2) + tipoEtiqueta(2) + tipo(1) + codigo(6) + preco(6) + validade(3) + descricao(50)
     *
     * @param  Collection<int, Product>  $products
     */
    protected function buildTxItens(Collection $products, ?int $empresaId, int $digitos = 6): string
    {
        $out = '';
        $codeWidth = max(6, $digitos);

        foreach ($products as $product) {
            $tipo = $this->isUnitProduct($product) ? '1' : '0';
            $tipoEtq = $this->isUnitProduct($product) ? '01' : '00';
            $out .= '01'
                .$tipoEtq
                .$tipo
                .$this->scaleCode($product, $codeWidth)
                .$this->digitsPrice($this->price($product, $empresaId), 6)
                .$this->validityDays($product, 3)
                .$this->padRight($this->sanitizeAscii($product->descricao ?? ''), 50)
                ."\r\n";
        }

        return $out;
    }

    /**
     * Toledo MGV6/MGV7 — ITENSMGV.TXT (versão simplificada usada por retaguardas).
     * Layout base: DD(2)T(1)CCCCCC(6)PPPPPP(6)VVV(3)D1+D2(50) + zeros/pad até campos obrigatórios.
     *
     * @param  Collection<int, Product>  $products
     */
    protected function buildItensMgv(Collection $products, ?int $empresaId, int $digitos = 6): string
    {
        $out = '';
        $codeWidth = max(6, $digitos);

        foreach ($products as $product) {
            $tipo = $this->isUnitProduct($product) ? '1' : '0';
            $desc = $this->padRight($this->sanitizeAscii($product->descricao ?? ''), 50);
            $linha = '01'
                .$tipo
                .$this->scaleCode($product, $codeWidth)
                .$this->digitsPrice($this->price($product, $empresaId), 6)
                .$this->validityDays($product, 3)
                .$desc
                .str_repeat('0', 6)   // info extra
                .str_repeat('0', 4)   // imagem
                .str_repeat('0', 6)   // nutricional
                .'0'                  // DV
                .'0'                  // DE
                .str_repeat('0', 4)   // fornecedor
                .str_repeat(' ', 12)  // lote
                .str_repeat('0', 11)  // EAN especial
                .'0'                  // versão preço
                .str_repeat('0', 4)   // som
                .str_repeat('0', 4)   // tara
                .str_repeat('0', 4)   // fracionador
                .str_repeat('0', 4)   // CE1
                .str_repeat('0', 4)   // CE2
                .str_repeat('0', 4)   // CON
                .str_repeat('0', 12)  // EAN
                .str_repeat('0', 6);  // GL

            $out .= $linha."\r\n";
        }

        return $out;
    }

    /**
     * Urano / UranoS / URF32 — Produtos.txt
     * CODIGO(6) FLAG(1) TIPO(1) NOME(20) PRECO(9) VALIDADE(5) UNIDADE_VAL(1)
     *
     * @param  Collection<int, Product>  $products
     */
    protected function buildUrano(Collection $products, ?int $empresaId, int $digitos = 6): string
    {
        $out = '';
        $codeWidth = max(6, $digitos);

        foreach ($products as $product) {
            $tipo = $this->isUnitProduct($product) ? '6' : '0';
            $preco = $this->uranoPrice($this->price($product, $empresaId));
            $validade = $this->validityDays($product, 5);
            $out .= $this->scaleCode($product, $codeWidth)
                .'*'
                .$tipo
                .$this->padRight($this->sanitizeAscii($product->descricao ?? ''), 20)
                .$preco
                .$validade
                .'D'
                ."\r\n";
        }

        return $out;
    }

    protected function scaleCode(Product $product, int $digitos = 6): string
    {
        $width = BalancaEtiquetaLayout::normalizeDigitos($digitos);
        // Arquivos de carga (Filizola/Toledo/Urano) usam campo fixo de 6 nas especificações clássicas;
        // quando digitos < 6, ainda preenche à esquerda com zeros até 6 para não quebrar o layout do arquivo.
        $fileWidth = max(6, $width);

        $raw = preg_replace('/\D/', '', (string) ($product->prefixo_balanca ?? '')) ?? '';
        if ($raw === '') {
            $raw = preg_replace('/\D/', '', (string) ($product->codigo ?? '')) ?? '0';
        }

        // Remove prefixo EAN "2" se o cadastro guardou o código completo da etiqueta.
        $eanPrefix = BalancaEtiquetaLayout::DEFAULT_PREFIXO;
        if (str_starts_with($raw, $eanPrefix) && strlen($raw) > $width) {
            $raw = substr($raw, strlen($eanPrefix));
        }

        if (strlen($raw) > $width) {
            $raw = substr($raw, -$width);
        }

        return $this->padLeft($raw, $fileWidth);
    }

    protected function resolveDigitos(?int $digitos, ?int $empresaId): int
    {
        if ($digitos !== null) {
            return BalancaEtiquetaLayout::normalizeDigitos($digitos);
        }

        try {
            $empresa = \App\Support\Erp\ErpSystemConfig::empresa($empresaId);
            $raw = $empresa?->param_balanca_digitos ?? null;
            if ($raw !== null && $raw !== '') {
                return BalancaEtiquetaLayout::normalizeDigitos($raw);
            }

            $modeloEtq = $empresa?->param_balanca_etiqueta_modelo
                ?? $empresa?->param_pdv_modelo_balanca
                ?? BalancaEtiquetaLayout::DEFAULT_MODELO;

            return BalancaEtiquetaLayout::digitosForModelo((int) $modeloEtq);
        } catch (Throwable) {
            return BalancaEtiquetaLayout::DEFAULT_DIGITOS;
        }
    }

    protected function isUnitProduct(Product $product): bool
    {
        if ((bool) ($product->produto_pesado ?? false)) {
            return false;
        }

        $unidade = Str::upper(trim((string) ($product->unidade ?? '')));

        if ($unidade === '') {
            return true;
        }

        if (str_contains($unidade, 'KG') || in_array($unidade, ['G', 'GR', 'GRAMA', 'KILO', 'QUILO'], true)) {
            return false;
        }

        return true;
    }

    protected function price(Product $product, ?int $empresaId): float
    {
        if ($this->precos !== null) {
            return round($this->precos->resolvePrecoVenda($product, $empresaId), 2);
        }

        try {
            return round(app(ProductEmpresaPrecoService::class)->resolvePrecoVenda($product, $empresaId), 2);
        } catch (Throwable) {
            return round((float) ($product->preco_venda ?? 0), 2);
        }
    }

    protected function digitsPrice(float $price, int $width): string
    {
        $cents = (int) round($price * 100);
        $digits = (string) max(0, $cents);

        if (strlen($digits) > $width) {
            $digits = substr($digits, -$width);
        }

        return $this->padLeft($digits, $width);
    }

    /**
     * Formato Urano: 2 espaços + 4 dígitos inteiros + vírgula + 2 centavos = 9 chars.
     * Ex.: "  1234,56"
     */
    protected function uranoPrice(float $price): string
    {
        $cents = (int) round($price * 100);
        $int = intdiv($cents, 100);
        $dec = $cents % 100;

        if ($int > 9999) {
            $int = $int % 10000;
        }

        return sprintf('  %04d,%02d', $int, $dec);
    }

    protected function validityDays(Product $product, int $width): string
    {
        $raw = trim((string) ($product->validade ?? ''));

        if ($raw !== '' && ctype_digit($raw)) {
            $days = (int) $raw;
            if ($days > 0 && $days <= 99999) {
                return $this->padLeft((string) min($days, (int) str_repeat('9', $width)), $width);
            }
        }

        return str_repeat('0', $width);
    }

    protected function sanitizeAscii(string $value): string
    {
        $value = Str::ascii($value);
        $value = preg_replace('/[^\x20-\x7E]/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    protected function padLeft(string $value, int $width): string
    {
        return str_pad(substr($value, 0, $width), $width, '0', STR_PAD_LEFT);
    }

    protected function padRight(string $value, int $width): string
    {
        return str_pad(substr($value, 0, $width), $width, ' ', STR_PAD_RIGHT);
    }

    /**
     * @param  array<string, string>  $contents
     * @return array{0: ?string, 1: ?string} absolute path, download filename
     */
    protected function storeDownloadBundle(string $modelo, array $contents): array
    {
        $dir = storage_path('app/balanca');
        File::ensureDirectoryExists($dir);

        if (count($contents) === 1) {
            $name = array_key_first($contents);
            $path = $dir.DIRECTORY_SEPARATOR.$name;
            file_put_contents($path, $contents[$name]);

            return [$path, $name];
        }

        $zipName = 'balanca_'.BalancaModel::normalize($modelo).'_'.date('Ymd_His').'.zip';
        $zipPath = $dir.DIRECTORY_SEPARATOR.$zipName;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            // fallback: grava o primeiro arquivo
            $name = array_key_first($contents);
            $path = $dir.DIRECTORY_SEPARATOR.$name;
            file_put_contents($path, $contents[$name]);

            return [$path, $name];
        }

        foreach ($contents as $name => $body) {
            $zip->addFromString($name, $body);
        }
        $zip->close();

        return [$zipPath, $zipName];
    }
}
