<?php

namespace App\Support\Erp;

use App\Filament\Resources\EmpresaResource;
use App\Filament\Resources\RhFuncionarioResource;
use App\Filament\Resources\UserResource;
use App\Models\Empresa;
use Illuminate\Support\Facades\Cache;

/**
 * Primeiro acesso (instalação zerada):
 * só exige cadastro da empresa. Após salvar, o bootstrap cria estoque/caixa/operador
 * e libera venda não fiscal (Pedido no PDV).
 *
 * Os passos usuario/colaborador ficam só para instalações antigas que já estavam
 * no meio do fluxo forçado.
 */
final class ErpOnboarding
{
    public const STEP_EMPRESA = 'empresa';

    /** @deprecated Fluxo legado; novas instalações concluem após a empresa. */
    public const STEP_USUARIO = 'usuario';

    /** @deprecated Fluxo legado; novas instalações concluem após a empresa. */
    public const STEP_COLABORADOR = 'colaborador';

    public static function step(): ?string
    {
        if (! Cache::remember('erp.empresa.exists', 120, static fn (): bool => Empresa::query()->exists())) {
            return self::STEP_EMPRESA;
        }

        $stored = self::readStoredStep();

        if ($stored === null || $stored === '' || $stored === 'done') {
            return null;
        }

        if (in_array($stored, [self::STEP_USUARIO, self::STEP_COLABORADOR], true)) {
            return $stored;
        }

        return null;
    }

    public static function isPending(): bool
    {
        return self::step() !== null;
    }

    public static function beginUsuario(): void
    {
        self::writeStoredStep(self::STEP_USUARIO);
    }

    public static function advanceToColaborador(): void
    {
        self::writeStoredStep(self::STEP_COLABORADOR);
    }

    public static function complete(): void
    {
        self::writeStoredStep('done');
    }

    public static function urlForCurrentStep(): ?string
    {
        return match (self::step()) {
            self::STEP_EMPRESA => EmpresaResource::getUrl('create'),
            self::STEP_USUARIO => UserResource::getUrl('index'),
            self::STEP_COLABORADOR => RhFuncionarioResource::getUrl('index'),
            default => null,
        };
    }

    public static function screenTitle(): ?string
    {
        return match (self::step()) {
            self::STEP_EMPRESA => 'Primeiro acesso — Cadastro de Empresa',
            self::STEP_USUARIO => 'Primeiro acesso — Cadastro de Usuário',
            self::STEP_COLABORADOR => 'Primeiro acesso — Cadastro de Funcionário (aba Operador)',
            default => null,
        };
    }

    private static function storagePath(): string
    {
        return storage_path('app/erp-onboarding-step');
    }

    private static function readStoredStep(): ?string
    {
        $path = self::storagePath();

        if (! is_file($path)) {
            return null;
        }

        return trim((string) file_get_contents($path));
    }

    private static function writeStoredStep(string $step): void
    {
        $dir = dirname(self::storagePath());

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(self::storagePath(), $step);
    }
}
