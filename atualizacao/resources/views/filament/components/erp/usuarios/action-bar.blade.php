<div class="erp-usuarios-actions">
    @if (erp_can('acesso.usuarios.create'))
        <button type="button" wire:click="createUser" class="erp-usuarios-actions__btn" data-erp-key="F2">
            <span class="erp-usuarios-actions__icon erp-usuarios-actions__icon--new">+</span>
            <span class="erp-usuarios-actions__label"><kbd>F2</kbd> | Novo</span>
        </button>
    @endif
    @if (erp_can('acesso.usuarios.update'))
        <button type="button" wire:click="editUser" class="erp-usuarios-actions__btn" data-erp-key="F3">
            <span class="erp-usuarios-actions__icon">✎</span>
            <span class="erp-usuarios-actions__label"><kbd>F3</kbd> | Alterar</span>
        </button>
    @endif
    @if (erp_can('acesso.usuarios.delete'))
        <button type="button" wire:click="deleteUser" class="erp-usuarios-actions__btn" data-erp-key="Delete">
            <span class="erp-usuarios-actions__icon erp-usuarios-actions__icon--cancel">✕</span>
            <span class="erp-usuarios-actions__label"><kbd>Del</kbd> | Excluir</span>
        </button>
    @endif
    @if (erp_can('acesso.permissoes.manage'))
        <button type="button" wire:click="openUserPermissions" class="erp-usuarios-actions__btn" data-erp-key="F4">
            <span class="erp-usuarios-actions__icon">🛡</span>
            <span class="erp-usuarios-actions__label"><kbd>F4</kbd> | Permissões</span>
        </button>
    @endif
    <button type="button" wire:click="refreshTable" class="erp-usuarios-actions__btn" data-erp-key="F5">
        <span class="erp-usuarios-actions__icon">↻</span>
        <span class="erp-usuarios-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-usuarios-actions__btn erp-usuarios-actions__btn--close">
        <span class="erp-usuarios-actions__icon erp-usuarios-actions__icon--close">✕</span>
        <span class="erp-usuarios-actions__label">Fechar</span>
    </button>
</div>
