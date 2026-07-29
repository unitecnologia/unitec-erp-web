<div class="erp-nfe__total erp-nfe__total--notas-fornecedores">
    <div class="erp-nfe__chave-group erp-nfe__chave-group--footer">
        <label class="erp-nfe__chave-label">
            <span class="erp-nfe__chave-label-text">CHAVE NF-e</span>
            <input
                type="text"
                readonly
                class="erp-nfe__input erp-nfe__chave-input erp-nfe__chave-input--readonly"
                value="<?php echo e($this->selectedChave); ?>"
                tabindex="-1"
            >
        </label>
    </div>

    <div class="erp-nfe__total-summary">
        <span class="erp-nfe__total-label">TOTAL DE NF-E</span>
        <span class="erp-nfe__total-value">
            R$ <?php echo e(number_format($this->filteredTotal, 2, ',', '.')); ?>

        </span>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/notas-fornecedores/footer-total.blade.php ENDPATH**/ ?>