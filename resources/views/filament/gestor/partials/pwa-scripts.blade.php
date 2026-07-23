{{-- Persistência offline do snapshot + status de rede + install PWA --}}
<script>
(function () {
    const KEY = 'gestor_snapshot_v1';
    const TEMA_KEY = 'gestor_tema';

    window.UnitecGestorPwa = {
        saveSnapshot(snapshot) {
            try {
                if (!snapshot || typeof snapshot !== 'object') return;
                localStorage.setItem(KEY, JSON.stringify(snapshot));
                localStorage.setItem(KEY + '_saved_at', new Date().toISOString());
            } catch (e) {}
        },
        saveTema(tema) {
            try { localStorage.setItem(TEMA_KEY, tema); } catch (e) {}
        },
        isOnline() {
            return navigator.onLine !== false;
        }
    };

    function syncBanner() {
        let el = document.getElementById('gestor-offline-banner');
        if (!window.UnitecGestorPwa.isOnline()) {
            if (!el) {
                el = document.createElement('div');
                el.id = 'gestor-offline-banner';
                el.className = 'gestor-offline-banner';
                el.textContent = 'Sem conexão — consultas usam o último pulso salvo';
                const shell = document.querySelector('.gestor-shell__inner');
                if (shell) shell.prepend(el);
            }
        } else if (el) {
            el.remove();
        }
    }

    window.addEventListener('online', syncBanner);
    window.addEventListener('offline', syncBanner);
    document.addEventListener('DOMContentLoaded', syncBanner);
    document.addEventListener('livewire:navigated', function () {
        syncBanner();
        const shell = document.querySelector('.gestor-shell');
        if (shell && window.UnitecGestorPwa) {
            window.UnitecGestorPwa.saveTema(shell.getAttribute('data-theme') || 'light');
        }
    });

    // Favorito/atalho no navegador: reaproveita a aba quando possível (não abre várias).
    // Instalação PWA desativada por enquanto — uso normal via http://IP:8000/gestor/
    window.UnitecGestorInstall = function () {
        alert('Abra o Executivo no navegador em /gestor/. Favoritar a página evita abrir várias vezes.');
    };

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base64);
        const out = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    window.UnitecGestorPush = {
        async toggle(wire) {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                alert('Este navegador não suporta Web Push.');
                return;
            }

            try {
                const reg = await navigator.serviceWorker.ready;
                const existing = await reg.pushManager.getSubscription();

                if (existing) {
                    const endpoint = existing.endpoint;
                    await existing.unsubscribe();
                    await wire.desativarPush(endpoint);
                    return;
                }

                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    alert('Permissão de notificação negada.');
                    return;
                }

                const vapid = await wire.vapidPublicKey();
                if (!vapid) {
                    alert('VAPID não configurado no servidor.');
                    return;
                }

                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapid),
                });

                await wire.salvarPushSubscription(sub.toJSON());
            } catch (e) {
                alert('Não foi possível ativar push: ' + (e && e.message ? e.message : e));
            }
        }
    };
})();
</script>
