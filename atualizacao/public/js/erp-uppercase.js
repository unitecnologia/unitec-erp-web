window.ErpUppercase = {
    shouldSkip(input) {
        if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) {
            return true;
        }
        if (input.disabled) {
            return true;
        }
        if (input.hasAttribute("data-erp-preserve-case") || input.dataset.erpPreserveCase === "1") {
            return true;
        }
        if (input.readOnly && input.dataset.erpNoAutofill !== "1") {
            return true;
        }
        const type = (input.type || "text").toLowerCase();
        if (["password","number","date","datetime-local","time","month","week","file","checkbox","radio","hidden","range","color"].includes(type)) {
            return true;
        }
        if (input.hasAttribute("data-mask") || input.dataset.erpPasswordMask) {
            return true;
        }
        if (input.hasAttribute("data-erp-date") || input.classList.contains("erp-date-input")) {
            return true;
        }
        if (input.classList.contains("erp-pcad-form__input--password")) {
            return true;
        }
        if (input.classList.contains("erp-pdv-form__input--money")) {
            return true;
        }
        if (input.classList.contains("erp-pcad-form__input--comissao") || input.inputMode === "decimal") {
            return true;
        }
        if (input.classList.contains("erp-fpgto-field__input--num")) {
            return true;
        }
        return false;
    },
    applyUppercase(input) {
        const upper = input.value.toLocaleUpperCase("pt-BR");
        if (input.value === upper) {
            return;
        }
        const start = input.selectionStart;
        const end = input.selectionEnd;
        input.value = upper;
        if (document.activeElement === input && start !== null && end !== null) {
            try { input.setSelectionRange(start, end); } catch (_) {}
        }
        input.dispatchEvent(new Event("input", { bubbles: true }));
    },
    bindInput(input) {
        if (input.dataset.erpUppercaseBound === "1" || this.shouldSkip(input)) {
            return;
        }
        input.dataset.erpUppercaseBound = "1";
        input.setAttribute("data-erp-uppercase", "");
        input.setAttribute("autocapitalize", "characters");
        input.style.textTransform = "uppercase";
        const onType = () => this.applyUppercase(input);
        input.addEventListener("input", onType);
        input.addEventListener("change", onType);
        input.addEventListener("blur", onType);
        input.addEventListener("animationstart", onType);
    },
    bind(root = document) {
        if (!root || !root.querySelectorAll) {
            return;
        }
        root.querySelectorAll("input[type=\"text\"], input[type=\"search\"], input[type=\"tel\"], input[type=\"email\"], input[type=\"url\"], input:not([type]), textarea").forEach((input) => this.bindInput(input));
        root.querySelectorAll("[data-erp-uppercase]").forEach((input) => this.bindInput(input));
    },
};
function initErpUppercase() {
    window.ErpUppercase?.bind(document);
}
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initErpUppercase);
} else {
    initErpUppercase();
}
document.addEventListener("livewire:navigated", initErpUppercase);
if (window.Livewire) {
    window.Livewire.hook("morph.updated", ({ el }) => { window.ErpUppercase?.bind(el); });
} else {
    document.addEventListener("livewire:init", () => {
        window.Livewire.hook("morph.updated", ({ el }) => { window.ErpUppercase?.bind(el); });
    });
}