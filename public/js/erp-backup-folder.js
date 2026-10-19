/**
 * Seletor de pasta da tela Backup — UI Helper em 127.0.0.1:9331.
 * Carregado no head do admin (navegação Livewire não reinsere scripts de módulo).
 */
(function () {
    'use strict';

    if (window.__erpBackupFolderBound) {
        return;
    }
    window.__erpBackupFolderBound = true;

    var DEFAULT_BASE = 'http://127.0.0.1:9331';
    var busyBtn = null;
    var abortCtrl = null;

    function uiBase(el) {
        var root = el.closest('[data-erp-ui-helper-base]');
        var base = (root && root.getAttribute('data-erp-ui-helper-base')) || DEFAULT_BASE;
        return String(base).replace(/\/$/, '');
    }

    function findWire(el) {
        var node = el.closest('[wire\\:id]');
        if (!node || !window.Livewire || typeof window.Livewire.find !== 'function') {
            return null;
        }
        try {
            return window.Livewire.find(node.getAttribute('wire:id'));
        } catch (e) {
            return null;
        }
    }

    function setHint(root, text) {
        var hint = root && root.querySelector('[data-erp-backup-pick-hint]');
        if (!hint) return;
        hint.textContent = text || '';
        hint.hidden = !text;
    }

    function setButtonIdle(btn) {
        if (!btn) return;
        btn.dataset.erpBusy = '0';
        btn.textContent = btn.dataset.erpLabelIdle || 'Escolher…';
        btn.removeAttribute('disabled');
    }

    function setButtonBusy(btn) {
        if (!btn) return;
        btn.dataset.erpBusy = '1';
        btn.textContent = btn.dataset.erpLabelBusy || 'Cancelar';
        btn.removeAttribute('disabled');
    }

    function fetchJson(url, options, timeoutMs, externalAbort) {
        var ctrl = externalAbort || new AbortController();
        var timer = setTimeout(function () {
            try { ctrl.abort(); } catch (e) {}
        }, timeoutMs || 8000);

        var opts = Object.assign({}, options || {}, {
            signal: ctrl.signal,
            mode: 'cors',
            cache: 'no-store',
        });

        return fetch(url, opts)
            .then(function (res) {
                return res.text().then(function (text) {
                    var data = null;
                    try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
                    return { ok: res.ok, status: res.status, data: data, aborted: false };
                });
            })
            .catch(function (err) {
                var aborted = !!(err && (err.name === 'AbortError' || ctrl.signal.aborted));
                return { ok: false, status: 0, data: null, aborted: aborted, error: err };
            })
            .finally(function () {
                clearTimeout(timer);
            });
    }

    function cancelPick(base) {
        if (abortCtrl) {
            try { abortCtrl.abort(); } catch (e) {}
            abortCtrl = null;
        }
        return fetchJson(base + '/api/cancel-pick', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: '{}',
        }, 2000);
    }

    function ensureOnline(base, wire) {
        return fetchJson(base + '/api/status', {
            method: 'GET',
            headers: { Accept: 'application/json' },
        }, 2000).then(function (status) {
            if (status.ok) {
                return true;
            }
            if (!wire || typeof wire.call !== 'function' && typeof wire.prepararUiHelper !== 'function') {
                return false;
            }
            var prep = typeof wire.prepararUiHelper === 'function'
                ? wire.prepararUiHelper()
                : wire.call('prepararUiHelper');
            return Promise.race([
                Promise.resolve(prep),
                new Promise(function (resolve) { setTimeout(function () { resolve(null); }, 8000); }),
            ]).then(function () {
                return fetchJson(base + '/api/status', {
                    method: 'GET',
                    headers: { Accept: 'application/json' },
                }, 2000).then(function (again) {
                    return !!again.ok;
                });
            }).catch(function () {
                return false;
            });
        });
    }

    function pickOnce(base, initialPath, description) {
        abortCtrl = new AbortController();
        return fetchJson(base + '/api/pick-folder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                initialPath: initialPath || '',
                description: description || 'Selecione a pasta',
            }),
        }, 120000, abortCtrl).then(function (res) {
            abortCtrl = null;
            return res;
        });
    }

    function pickFolder(base, initialPath, description) {
        return pickOnce(base, initialPath, description).then(function (picked) {
            if (picked.status !== 409) {
                return picked;
            }
            return cancelPick(base).then(function () {
                return new Promise(function (resolve) { setTimeout(resolve, 350); });
            }).then(function () {
                return pickOnce(base, initialPath, description);
            });
        });
    }

    function finishBusy(btn, root) {
        if (busyBtn === btn) {
            busyBtn = null;
        }
        setButtonIdle(btn);
        setHint(root, '');
    }

    function warn(wire, message) {
        if (wire && typeof wire.avisarSeletorPasta === 'function') {
            try { wire.avisarSeletorPasta(message); return; } catch (e) {}
        }
        if (wire && typeof wire.call === 'function') {
            try { wire.call('avisarSeletorPasta', message); return; } catch (e) {}
        }
        window.alert(message);
    }

    function onPickClick(btn) {
        var root = btn.closest('.erp-backup') || document;
        var base = uiBase(btn);
        var wire = findWire(btn);
        var mode = btn.getAttribute('data-erp-backup-pick') || 'destino';

        if (btn.dataset.erpBusy === '1') {
            cancelPick(base).finally(function () {
                finishBusy(btn, root);
            });
            return;
        }

        if (busyBtn) {
            return;
        }

        if (wire && (wire.running || wire.progressActive)) {
            return;
        }

        busyBtn = btn;
        setButtonBusy(btn);
        setHint(root, 'Abrindo seletor de pasta…');

        ensureOnline(base, wire).then(function (online) {
            if (!online) {
                // Fallback: Livewire + PowerShell (só sessão interativa).
                if (wire) {
                    setHint(root, 'UI Helper offline — tentando seletor do Windows…');
                    var method = mode === 'restore' ? 'selecionarPastaRestore' : 'selecionarPasta';
                    var call = typeof wire[method] === 'function'
                        ? wire[method]()
                        : (typeof wire.call === 'function' ? wire.call(method) : null);
                    return Promise.resolve(call).finally(function () {
                        finishBusy(btn, root);
                    });
                }
                warn(wire, 'Seletor indisponível (UI Helper offline). Digite o caminho no campo ou reinicie o Unitec ERP.');
                finishBusy(btn, root);
                return null;
            }

            setHint(root, 'Se a janela não aparecer: Alt+Tab → “Unitec ERP — selecione a pasta”. Clique em Cancelar para abortar.');

            var initial = '';
            var description = 'Selecione a pasta para salvar os backups';
            if (wire) {
                if (mode === 'restore') {
                    initial = wire.pastaRestore || wire.pastaDestino || '';
                    description = 'Selecione a pasta onde está o backup';
                } else {
                    initial = wire.pastaDestino || '';
                }
            }

            return pickFolder(base, initial, description).then(function (picked) {
                if (!picked) return;

                if (picked.aborted) {
                    return cancelPick(base);
                }

                if (!picked.data) {
                    warn(wire, 'Seletor sem resposta. Tente de novo (Alt+Tab) ou digite o caminho.');
                    return;
                }

                if (picked.data.cancelled) {
                    return;
                }

                if (picked.status === 409) {
                    warn(wire, 'Seletor ocupado. Clique de novo em alguns segundos.');
                    return;
                }

                if (picked.data.ok && picked.data.path && wire) {
                    var path = String(picked.data.path);
                    if (mode === 'restore' && typeof wire.aplicarPastaRestore === 'function') {
                        return wire.aplicarPastaRestore(path);
                    }
                    if (typeof wire.aplicarPastaDestino === 'function') {
                        return wire.aplicarPastaDestino(path);
                    }
                    if (typeof wire.call === 'function') {
                        return wire.call(mode === 'restore' ? 'aplicarPastaRestore' : 'aplicarPastaDestino', path);
                    }
                }

                warn(wire, (picked.data && picked.data.message) || 'Não foi possível selecionar a pasta.');
            });
        }).catch(function (err) {
            warn(wire, (err && err.message) ? err.message : 'Falha ao abrir o seletor de pasta.');
        }).finally(function () {
            finishBusy(btn, root);
        });
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t) return;
        var btn = (t.closest && t.closest('[data-erp-backup-pick]')) || null;
        if (!btn) return;
        ev.preventDefault();
        ev.stopPropagation();
        onPickClick(btn);
    }, true);

    // Chamada direta do onclick do botão (fallback se o listener não pegar).
    window.__erpBackupPick = function (btn) {
        if (!btn) return false;
        onPickClick(btn);
        return false;
    };

    // Diagnóstico: no console → __erpBackupFolderPing().then(console.log)
    window.__erpBackupFolderPing = function () {
        return fetchJson(DEFAULT_BASE + '/api/status', { method: 'GET', headers: { Accept: 'application/json' } }, 2000);
    };
})();
