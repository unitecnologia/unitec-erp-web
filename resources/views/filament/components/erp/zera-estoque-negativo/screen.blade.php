<div class="erp-comando" wire:ignore.self>
    <div class="erp-comando__box">
        <p class="erp-comando__text">Este comando zera o saldo de estoque de todos os produtos com quantidade negativa.</p>
        <button type="button" wire:click="executar" class="erp-comando__btn">Executar</button>
    </div>
    <div class="erp-comando__footer">
        <button type="button" wire:click="closeScreen" class="erp-comando__exit">Fechar</button>
    </div>
</div>
