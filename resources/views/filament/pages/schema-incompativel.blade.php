<x-filament-panels::page>
    <div x-data>
        <template x-teleport="body">
            <div
                class="erp-schema-block"
                style="position:fixed;inset:0;z-index:2147483000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.7rem;padding:1rem;box-sizing:border-box;overflow:hidden;background:radial-gradient(900px 420px at 50% 12%,rgba(245,158,11,.18),transparent 60%),linear-gradient(165deg,#0b1a2e 0%,#13233f 42%,#1e3a5f 100%);"
            >
                <div style="color:rgba(226,232,240,.92);font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;text-align:center;">
                    {{ config('unitec.app_name') }}
                </div>

                <div style="width:min(26rem,calc(100vw - 1.5rem));max-height:calc(100dvh - 3.2rem);overflow:auto;padding:1.1rem 1.1rem 1rem;border-radius:16px;background:#fff;color:#0f172a;box-shadow:0 24px 48px rgba(2,6,23,.45);box-sizing:border-box;">
                    <div style="display:inline-flex;margin-bottom:.35rem;padding:.14rem .5rem;border-radius:999px;background:#ffedd5;color:#c2410c;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;">
                        Banco de dados
                    </div>
                    <h1 style="margin:0;font-size:1.2rem;font-weight:800;line-height:1.2;color:#0f172a;">
                        Sistema bloqueado — schema incompatível
                    </h1>
                    <p style="margin:.45rem 0 0;font-size:.8rem;line-height:1.4;color:#64748b;">
                        {{ $this->mensagem }}
                    </p>

                    @if ($this->pending > 0)
                        <p style="margin:.65rem 0 0;padding:.45rem .6rem;border-radius:8px;background:#fff7ed;border:1px solid #fed7aa;font-size:.76rem;font-weight:700;color:#9a3412;">
                            Migrations pendentes: {{ $this->pending }}
                        </p>
                    @endif

                    <ol style="margin:.85rem 0 0;padding:.65rem .85rem .65rem 1.35rem;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;font-size:.78rem;line-height:1.45;color:#334155;">
                        @if ($this->atualizacaoPendente && ! session('erp_update_choice_skipped'))
                            <li style="margin-bottom:.35rem;">
                                Use <strong>Ir ao login</strong> e aceite a atualização
                                @if (filled($this->versaoPendente))
                                    (versão {{ $this->versaoPendente }})
                                @endif
                                — o atualizador aplica o migrate e só conclui com zero pendências.
                            </li>
                        @endif
                        <li style="margin-bottom:.35rem;">
                            Feche o ERP e execute <strong>Reparar Sistema.bat</strong> na pasta de instalação
                            (aplica <code>migrate --force</code> sem apagar dados).
                        </li>
                        <li>
                            Depois abra o sistema de novo. Se o problema continuar, contate o suporte Unitec.
                        </li>
                    </ol>

                    <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem;">
                        <button
                            type="button"
                            wire:click="irParaLogin"
                            style="flex:1;min-width:8rem;padding:.55rem .75rem;border:0;border-radius:10px;background:#2563eb;color:#fff;font-size:.8rem;font-weight:700;cursor:pointer;"
                        >
                            Ir ao login
                        </button>
                        <button
                            type="button"
                            wire:click="sair"
                            style="flex:1;min-width:8rem;padding:.55rem .75rem;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#334155;font-size:.8rem;font-weight:700;cursor:pointer;"
                        >
                            Sair
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-filament-panels::page>
