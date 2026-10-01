@if ($this->productImageSearchOpen)
    <div class="erp-lookup-modal erp-img-search-modal" wire:keydown.escape="closeProductImageSearch">
        <div class="erp-lookup-modal__backdrop" wire:click="closeProductImageSearch"></div>

        <div class="erp-lookup-modal__window erp-img-search-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-img-search-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-img-search-title">Pesquisar imagem do produto</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeProductImageSearch" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-img-search-modal__body">
                <div class="erp-img-search__bar">
                    <input
                        id="erp-img-search-query"
                        type="text"
                        wire:model="productImageSearchQuery"
                        wire:keydown.enter.prevent="searchProductImagesOnline"
                        class="erp-pcad-form__input"
                        placeholder="Descrição do produto"
                        maxlength="200"
                        autocomplete="off"
                    >
                    <button
                        type="button"
                        class="erp-pcad-form__btn"
                        wire:click="searchProductImagesOnline"
                        wire:loading.attr="disabled"
                        wire:target="searchProductImagesOnline,confirmProductImageSearchUse"
                    >
                        <span wire:loading.remove wire:target="searchProductImagesOnline">Pesquisar</span>
                        <span wire:loading wire:target="searchProductImagesOnline">Pesquisando...</span>
                    </button>
                    <button
                        type="button"
                        class="erp-pcad-form__btn"
                        wire:click="closeProductImageSearch"
                        wire:loading.attr="disabled"
                        wire:target="confirmProductImageSearchUse"
                    >Cancelar</button>
                </div>

                <p class="erp-img-search__status" wire:loading wire:target="confirmProductImageSearchUse">Baixando imagem...</p>

                @if (filled($this->productImageSearchMessage))
                    <p class="erp-img-search__message">{{ $this->productImageSearchMessage }}</p>
                @endif

                @if ($this->productImageSearchResults !== [])
                    <div class="erp-img-search__grid">
                        @foreach ($this->productImageSearchResults as $index => $item)
                            <button
                                type="button"
                                class="erp-img-search__item @if ($this->productImageSearchConfirmIndex === $index) is-selected @endif"
                                wire:click="askProductImageSearchUse({{ $index }})"
                                wire:loading.attr="disabled"
                                wire:target="searchProductImagesOnline,confirmProductImageSearchUse"
                                title="{{ $item['title'] !== '' ? $item['title'] : 'Usar esta imagem' }}"
                            >
                                <img
                                    src="{{ $item['thumbnail'] }}"
                                    alt="{{ $item['title'] !== '' ? $item['title'] : 'Miniatura' }}"
                                    class="erp-img-search__thumb"
                                    loading="lazy"
                                    decoding="async"
                                    referrerpolicy="no-referrer"
                                >
                                @if ($item['title'] !== '')
                                    <span class="erp-img-search__title">{{ $item['title'] }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @endif

                @if ($this->productImageSearchConfirmIndex !== null)
                    <div class="erp-img-search__confirm">
                        <p>Usar esta imagem no produto?</p>
                        <button
                            type="button"
                            class="erp-pcad-form__btn"
                            wire:click="confirmProductImageSearchUse"
                            wire:loading.attr="disabled"
                            wire:target="confirmProductImageSearchUse"
                        >Usar</button>
                        <button
                            type="button"
                            class="erp-pcad-form__btn"
                            wire:click="cancelProductImageSearchUse"
                            wire:loading.attr="disabled"
                            wire:target="confirmProductImageSearchUse"
                        >Cancelar</button>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
