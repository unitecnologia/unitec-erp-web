@php
    use App\Support\Erp\ErpMenu;

    /**
     * Fase 2 (corrigida):
     * - wire:navigate no Livewire 3.8 faz document.body.replaceWith(newBody).
     * - wire:ignore só age no morph de componente Livewire — NÃO preserva DOM na navegação SPA.
     * - O mecanismo correto é @persist → <div x-persist="..."> (SupportNavigate).
     * - Sem cache de ~129KB de HTML na sessão (custo de read/write em todo request).
     */
    $fase2 = ErpMenu::SHELL_MENU_FASE2;
    $fp = $fase2 ? ErpMenu::shellFingerprint() : 'legacy';
    $menus = ErpMenu::mainMenus();
@endphp

@if ($fase2)
    @persist('erp-menu-'.$fp)
        @include('filament.components.erp.menu-bar-nav', [
            'fase2' => false,
            'fp' => $fp,
            'menus' => $menus,
        ])
    @endpersist
@else
    @include('filament.components.erp.menu-bar-nav', [
        'fase2' => false,
        'fp' => $fp,
        'menus' => $menus,
    ])
@endif
