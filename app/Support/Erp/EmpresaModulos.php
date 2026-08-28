<?php

namespace App\Support\Erp;

use App\Models\Empresa;

final class EmpresaModulos
{
    /**
     * @return array<string, string>
     */
    public static function permissionModuleMap(): array
    {
        return [
            'pdv' => 'param_modulo_pdv',
            'ordens_servico' => 'param_modulo_ordens_servico',
            'logistica' => 'param_modulo_logistica',
            'transportadoras' => 'param_modulo_logistica',
            'veiculos' => 'param_modulo_logistica',
            'tomadores_servico' => 'param_modulo_logistica',
            'logistica_destinatarios' => 'param_modulo_logistica',
            'logistica_remetentes' => 'param_modulo_logistica',
            'rh.dashboard' => 'param_modulo_rh',
            'rh.funcionarios' => 'param_modulo_rh',
            'rh.cargos' => 'param_modulo_rh',
            'rh.departamentos' => 'param_modulo_rh',
            'mercado_livre' => 'param_modulo_mercado_livre',
        ];
    }

    public static function enabled(?Empresa $empresa, string $catalogModule): bool
    {
        $field = static::permissionModuleMap()[$catalogModule] ?? null;

        if ($field === null) {
            return true;
        }

        if ($empresa === null) {
            return true;
        }

        return (bool) $empresa->getAttribute($field);
    }

    public static function enabledForPermission(?Empresa $empresa, string $permission): bool
    {
        return static::enabled($empresa, static::catalogModuleForPermission($permission));
    }

    public static function catalogModuleForPermission(string $permission): string
    {
        foreach (array_keys(ErpPermissionCatalog::modules()) as $module) {
            if (str_starts_with($permission, $module.'.')) {
                return $module;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    public static function disabledCatalogModules(?Empresa $empresa): array
    {
        return array_values(array_filter(
            array_keys(static::permissionModuleMap()),
            fn (string $module): bool => ! static::enabled($empresa, $module),
        ));
    }
}
