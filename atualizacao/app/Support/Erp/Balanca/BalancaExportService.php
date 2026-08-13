<?php

namespace App\Support\Erp\Balanca;

use App\Models\Grupo;
use App\Models\Product;
use App\Support\Erp\ProductEmpresaPrecoService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

final class BalancaExportService
{
    public function __construct(
        private readonly ?ProductEmpresaPrecoService $precos = null,
    ) {}

    /**
     * Produtos marcados como "Produto de Balança" (produto_pesado).
     * PLU no arquivo = codigo do produto (nunca o id).
     * Tipo peso/unidade vem da unidade (KG × UN), não do flag.
     *
     * @return Collection<int, Product>
     */
    public function productsForExport(): Collection
    {
        return Product::query()
            ->where('ativo', true)
            ->where('produto_pesado', true)
            ->orderBy('codigo')
            ->get([
                'id', 'codigo', 'descricao', 'unidade', 'preco_venda', 'produto_pesado', 'validade', 'codigo_barras', 'grupo',
                'tem_info_nutricional', 'nutri_porcao_qtd', 'nutri_porcao_unidade', 'nutri_medida_inteiro',
                'nutri_medida_fracao', 'nutri_medida_tipo', 'nutri_valor_energetico', 'nutri_carboidratos',
                'nutri_proteinas', 'nutri_gorduras_totais', 'nutri_gorduras_saturadas', 'nutri_gorduras_trans',
                'nutri_fibra', 'nutri_sodio',
            ]);
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
                'message' => 'Nenhum produto marcado para balança.',
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
            BalancaModel::TOLEDO_MGV6, BalancaModel::TOLEDO_MGV7 => $this->buildToledoMgvBundle($products, $empresaId, $digitos),
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
     * Toledo clássico / MGV5 — TXITENS.TXT (sem DEPTO/INFNUTRI).
     * Layout: DD(2) + tipoEtiqueta(2) + tipo(1) + codigo(6) + preco(6) + validade(3) + descricao(50)
     * DD fixo "01" — departamentos só no MGV6/7.
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
     * Toledo MGV6/MGV7 — ITENSMGV (Versão 3) + DEPTO + INFNUTRI.
     *
     * ITENSMGV V3:
     * DD(2) T(1) CCCCCC(6) PPPPPP(6) VVV(3) D1(25) D2(25)
     * RRRRRR(6) FFFF(4) IIIIII(6) DV(1) DE(1) CF(4) L(12) G(11) Z(1)
     * CS(4) CT(4) FR(4) CE1(4) CE2(4) CON(4) EAN(12) GL(6)
     * |DA| D3(35) D4(35) CE3(6) CE4(6) MIDIA(6)
     *
     * @param  Collection<int, Product>  $products
     * @return array<string, string>
     */
    protected function buildToledoMgvBundle(Collection $products, ?int $empresaId, int $digitos = 6): array
    {
        $deptos = $this->resolveMgvDepartamentos();

        return [
            'ITENSMGV.TXT' => $this->buildItensMgv($products, $empresaId, $digitos, $deptos),
            'DEPTO.TXT' => $this->buildMgvDepto($deptos),
            'INFNUTRI.TXT' => $this->buildMgvInfNutri($products, $digitos),
        ];
    }

    /**
     * Toledo MGV6/MGV7 — ITENSMGV.TXT Versão 3 (manual Toledo).
     *
     * @param  Collection<int, Product>  $products
     * @param  array{by_code: array<string, string>, by_name: array<string, string>}  $deptos
     */
    protected function buildItensMgv(Collection $products, ?int $empresaId, int $digitos = 6, ?array $deptos = null): string
    {
        $deptos ??= $this->resolveMgvDepartamentos();
        $out = '';
        $codeWidth = max(6, $digitos);
        $descBlank35 = str_repeat(' ', 35);
        $zeroAssoc = '0000';
        $zeroAssoc6 = '000000';

        foreach ($products as $product) {
            $tipo = $this->mgvProductType($product);
            $validade = $this->validityDays($product, 3);
            $days = (int) $validade;
            $imprimeValidade = ($days >= 1 && $days <= 990) ? '1' : '0';
            $imprimeEmbalagem = $imprimeValidade;
            $infoNutri = $this->productHasNutrition($product)
                ? $this->nutritionCode($product, $codeWidth)
                : $zeroAssoc6;
            $depto = $this->departmentCodeForProduct($product, $deptos);

            $descricao = $this->sanitizeAscii($product->descricao ?? '');
            $d1 = $this->padRight(substr($descricao, 0, 25), 25);
            $d2 = $this->padRight(substr($descricao, 25, 25), 25);

            $linha = $depto
                .$tipo
                .$this->scaleCode($product, $codeWidth)
                .$this->digitsPrice($this->price($product, $empresaId), 6)
                .$validade
                .$d1
                .$d2
                .$zeroAssoc6 // R info extra
                .$zeroAssoc // F imagem
                .$infoNutri // I info nutricional
                .$imprimeValidade // DV
                .$imprimeEmbalagem // DE
                .$zeroAssoc // CF fornecedor
                .str_repeat(' ', 12) // L lote
                .$this->eanEspecial11($product) // G EAN-13 especial
                .'0' // Z versão preço
                .$zeroAssoc // CS som
                .$zeroAssoc // CT tara
                .$zeroAssoc // FR fracionador
                .$zeroAssoc // CE1
                .$zeroAssoc // CE2
                .$zeroAssoc // CON conservação
                .$this->ean12($product) // EAN fornecedor
                .$zeroAssoc6 // GL glaciamento
                .'||' // DA nenhum depto associado extra
                .$descBlank35 // D3
                .$descBlank35 // D4
                .$zeroAssoc6 // CE3
                .$zeroAssoc6 // CE4
                .$zeroAssoc6; // MIDIA

            $out .= $linha."\r\n";
        }

        return $out;
    }

    /**
     * Departamentos MGV: grupos com "Bal. marcado", ou 01GERAL se nenhum.
     *
     * @param  array{by_code: array<string, string>, by_name: array<string, string>}|null  $deptos
     */
    protected function buildMgvDepto(?array $deptos = null): string
    {
        $deptos ??= $this->resolveMgvDepartamentos();
        $out = '';

        foreach ($deptos['by_code'] as $code => $nome) {
            $out .= $code.$this->padRight(substr($nome, 0, 12), 12)."\r\n";
        }

        return $out;
    }

    /**
     * @return array{by_code: array<string, string>, by_name: array<string, string>}
     */
    protected function resolveMgvDepartamentos(): array
    {
        try {
            if (! Schema::hasTable('grupos') || ! Schema::hasColumn('grupos', 'balanca_marcado')) {
                return $this->defaultMgvDepartamento();
            }

            $grupos = Grupo::query()
                ->where('balanca_marcado', true)
                ->where(function ($query): void {
                    $query->where('ativo', true)->orWhereNull('ativo');
                })
                ->orderBy('id')
                ->limit(99)
                ->get(['id', 'nome']);
        } catch (Throwable) {
            return $this->defaultMgvDepartamento();
        }

        if ($grupos->isEmpty()) {
            return $this->defaultMgvDepartamento();
        }

        $byCode = [];
        $byName = [];
        $i = 1;

        foreach ($grupos as $grupo) {
            $code = $this->padLeft((string) $i, 2);
            $nome = Str::upper($this->sanitizeAscii((string) ($grupo->nome ?? '')));
            if ($nome === '') {
                $nome = 'DEPTO '.$code;
            }
            $byCode[$code] = $nome;
            $byName[$nome] = $code;
            $i++;
        }

        return [
            'by_code' => $byCode,
            'by_name' => $byName,
        ];
    }

    /**
     * @return array{by_code: array<string, string>, by_name: array<string, string>}
     */
    protected function defaultMgvDepartamento(): array
    {
        return [
            'by_code' => ['01' => 'GERAL'],
            'by_name' => ['GERAL' => '01'],
        ];
    }

    /**
     * @param  array{by_code: array<string, string>, by_name: array<string, string>}  $deptos
     */
    protected function departmentCodeForProduct(Product $product, array $deptos): string
    {
        $nome = Str::upper($this->sanitizeAscii((string) ($product->grupo ?? '')));

        if ($nome !== '' && isset($deptos['by_name'][$nome])) {
            return $deptos['by_name'][$nome];
        }

        return array_key_first($deptos['by_code']) ?: '01';
    }

    /**
     * Informação nutricional Versão 2 (manual Toledo) — uma linha por produto com cadastro.
     *
     * @param  Collection<int, Product>  $products
     */
    protected function buildMgvInfNutri(Collection $products, int $digitos = 6): string
    {
        $out = '';
        $seen = [];
        $codeWidth = max(6, $digitos);

        foreach ($products as $product) {
            if (! $this->productHasNutrition($product)) {
                continue;
            }

            $code = $this->nutritionCode($product, $codeWidth);
            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $out .= $this->formatInfNutriLine($product, $code)."\r\n";
        }

        if ($out === '') {
            return $this->emptyInfNutriStub();
        }

        return $out;
    }

    protected function emptyInfNutriStub(): string
    {
        return 'N000001'
            .'0'
            .'000'
            .'0'
            .'00'
            .'0'
            .'00'
            .'0000'
            .'0000'
            .'000'
            .'000'
            .'000'
            .'000'
            .'000'
            .'00000'
            ."\r\n";
    }

    protected function formatInfNutriLine(Product $product, string $code): string
    {
        $unidade = (string) ($product->nutri_porcao_unidade ?? '0');
        if (! in_array($unidade, ['0', '1', '2'], true)) {
            $unidade = '0';
        }

        $fracao = (string) ($product->nutri_medida_fracao ?? '0');
        if (! in_array($fracao, ['0', '1', '2', '3', '4', '5'], true)) {
            $fracao = '0';
        }

        $medidaTipo = preg_replace('/\D/', '', (string) ($product->nutri_medida_tipo ?? '00')) ?? '00';
        $medidaTipo = $this->padLeft(substr($medidaTipo, 0, 2), 2);

        return 'N'
            .$code
            .'0' // A reservado
            .$this->padLeft((string) max(0, min(999, (int) ($product->nutri_porcao_qtd ?? 0))), 3)
            .$unidade
            .$this->padLeft((string) max(0, min(99, (int) ($product->nutri_medida_inteiro ?? 0))), 2)
            .$fracao
            .$medidaTipo
            .$this->mgvFixedDecimal((float) ($product->nutri_valor_energetico ?? 0), 3, 1) // kcal
            .$this->mgvFixedDecimal((float) ($product->nutri_carboidratos ?? 0), 3, 1)
            .$this->mgvFixedDecimal((float) ($product->nutri_proteinas ?? 0), 2, 1)
            .$this->mgvFixedDecimal((float) ($product->nutri_gorduras_totais ?? 0), 2, 1)
            .$this->mgvFixedDecimal((float) ($product->nutri_gorduras_saturadas ?? 0), 2, 1)
            .$this->mgvFixedDecimal((float) ($product->nutri_gorduras_trans ?? 0), 2, 1)
            .$this->mgvFixedDecimal((float) ($product->nutri_fibra ?? 0), 2, 1)
            .$this->mgvFixedDecimal((float) ($product->nutri_sodio ?? 0), 4, 1);
    }

    protected function productHasNutrition(Product $product): bool
    {
        return (bool) ($product->tem_info_nutricional ?? false);
    }

    protected function nutritionCode(Product $product, int $digitos = 6): string
    {
        // 1:1 com o PLU do item na balança.
        return $this->scaleCode($product, $digitos);
    }

    /**
     * Codifica decimal MGV: parte inteira + parte decimal sem separador.
     * Ex.: 12,3 com 2+1 → "123"; 250,5 com 3+1 → "2505".
     */
    protected function mgvFixedDecimal(float $value, int $intDigits, int $decDigits = 1): string
    {
        $width = $intDigits + $decDigits;
        $factor = 10 ** $decDigits;
        $scaled = (int) round(max(0, $value) * $factor);
        $max = (10 ** $width) - 1;

        return $this->padLeft((string) min($scaled, $max), $width);
    }

    protected function ean12(Product $product): string
    {
        $ean = preg_replace('/\D/', '', (string) ($product->codigo_barras ?? '')) ?? '';
        if (strlen($ean) >= 13) {
            // EAN-13 → 12 bytes sem dígito verificador
            return substr($ean, 0, 12);
        }
        if (strlen($ean) >= 12) {
            return substr($ean, 0, 12);
        }
        if ($ean !== '') {
            return $this->padLeft($ean, 12);
        }

        return str_repeat('0', 12);
    }

    protected function eanEspecial11(Product $product): string
    {
        if (! $this->hasRealEan($product)) {
            return str_repeat('0', 11);
        }

        // Campo G do MGV: 11 bytes — usa os 11 últimos dígitos do EAN (sem DV).
        return substr($this->ean12($product), -11);
    }

    /**
     * Tipo MGV:
     * 0 peso | 1 unidade | 2 EAN-13 por peso | 5 EAN-13 por unidade
     */
    protected function mgvProductType(Product $product): string
    {
        $unit = $this->isUnitProduct($product);
        $hasEan = $this->hasRealEan($product);

        if ($unit && $hasEan) {
            return '5';
        }

        if (! $unit && $hasEan) {
            return '2';
        }

        return $unit ? '1' : '0';
    }

    protected function hasRealEan(Product $product): bool
    {
        $ean = preg_replace('/\D/', '', (string) ($product->codigo_barras ?? '')) ?? '';

        // GTIN/EAN típico (8+). PLU curto (ex.: 40) não conta como EAN-13.
        return strlen($ean) >= 8;
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
        // Campo PLU dos arquivos (Filizola/Toledo/Urano/MGV) é sempre 6 dígitos,
        // com zeros à esquerda (ex.: código 40 → 000040).
        $fileWidth = BalancaProductRules::PLU_DIGITOS;

        $raw = preg_replace('/\D/', '', (string) ($product->codigo ?? '')) ?? '';

        if ($raw === '') {
            $raw = '0';
        }

        // Remove prefixo EAN "2" se o cadastro guardou o código completo da etiqueta.
        $eanPrefix = BalancaEtiquetaLayout::DEFAULT_PREFIXO;
        if (str_starts_with($raw, $eanPrefix) && strlen($raw) > $fileWidth) {
            $raw = substr($raw, strlen($eanPrefix));
        }

        if (strlen($raw) > $fileWidth) {
            $raw = substr($raw, -$fileWidth);
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

    /**
     * Tipo na balança: unidade (true) ou peso (false).
     * Usa a unidade do produto — o flag "Balança" só indica se entra no export.
     */
    protected function isUnitProduct(Product $product): bool
    {
        $unidade = Str::upper(trim((string) ($product->unidade ?? '')));

        if ($unidade === '') {
            return true;
        }

        if (str_contains($unidade, 'KG') || in_array($unidade, ['G', 'GR', 'GRAMA', 'GRAMAS', 'KILO', 'QUILO'], true)) {
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
        $days = null;

        // Arquivo da balança usa dias (ex.: 030), não a data literal.
        // Converte a data de validade do produto → dias restantes a partir de hoje.
        $raw = $product->getAttributes()['validade'] ?? null;

        if ($raw instanceof \DateTimeInterface) {
            $days = $this->daysUntil($raw);
        } elseif (is_string($raw) && trim($raw) !== '') {
            $trimmed = trim($raw);
            if (ctype_digit($trimmed)) {
                $days = (int) $trimmed;
            } else {
                try {
                    $days = $this->daysUntil(Carbon::parse($trimmed));
                } catch (\Throwable) {
                    $days = null;
                }
            }
        } elseif (is_numeric($raw)) {
            $days = (int) $raw;
        } else {
            // Model com cast date (app bootstrapped): Carbon via accessor.
            $validade = $product->validade ?? null;
            if ($validade instanceof \DateTimeInterface) {
                $days = $this->daysUntil($validade);
            }
        }

        if ($days !== null && $days > 0 && $days <= 99999) {
            // MGV: 001–990 = dias; 998/999 são códigos especiais.
            $max = $width === 3 ? 990 : (int) str_repeat('9', $width);

            return $this->padLeft((string) min($days, $max), $width);
        }

        return str_repeat('0', $width);
    }

    protected function daysUntil(\DateTimeInterface $date): int
    {
        $remaining = (int) Carbon::now()->startOfDay()
            ->diffInDays(Carbon::instance($date)->startOfDay(), false);

        return max(0, $remaining);
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
