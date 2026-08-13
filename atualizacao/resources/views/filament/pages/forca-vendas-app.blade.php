<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            O aplicativo <strong>Unitec Força de Vendas</strong> (Android) conecta-se a este servidor
            pela rede da loja. O vendedor não precisa de QR Code: o app procura o servidor
            automaticamente (ou o IP é digitado), e o aparelho aparece aqui no ERP para
            <strong>autorização</strong>.
        </p>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Coluna: endereço do servidor --}}
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" for="fv-ip">IP do servidor na rede</label>
                    <input
                        id="fv-ip"
                        type="text"
                        wire:model.live.debounce.600ms="ipServidor"
                        class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700"
                        placeholder="192.168.0.52"
                    >
                    <p class="mt-1 text-xs text-gray-500">
                        Detectado automaticamente. Ajuste se o servidor tiver outro IP fixo na rede.
                    </p>
                </div>

                <div class="rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-800/50">
                    <div class="flex justify-between gap-2">
                        <span class="text-gray-500">Endereço:</span>
                        <span class="font-mono text-gray-800 dark:text-gray-200">{{ $this->baseUrl }}</span>
                    </div>
                    <div class="mt-2 flex justify-between gap-2">
                        <span class="text-gray-500">Porta:</span>
                        <span class="font-mono text-gray-800 dark:text-gray-200">{{ $this->porta() }}</span>
                    </div>
                </div>
            </div>

            {{-- Coluna: passos --}}
            <div class="rounded-lg border border-dashed border-gray-300 p-6 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Como o vendedor conecta</h3>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-gray-600 dark:text-gray-400">
                    <li>Abrir o app e tocar em <strong>“Procurar servidor na rede”</strong> (ou digitar o IP acima).</li>
                    <li>O app mostra um <strong>código de autorização</strong> e o nome do aparelho.</li>
                    <li>No ERP, abrir <strong>Força de Vendas &rarr; Aparelhos</strong>, conferir o código e pressionar <kbd>F2</kbd> para autorizar.</li>
                    <li>O vendedor entra com o usuário e a <strong>senha do app</strong> (Usuários &rarr; “Senha App Força de Vendas”).</li>
                </ol>
            </div>
        </div>
    </div>
</x-filament-panels::page>
