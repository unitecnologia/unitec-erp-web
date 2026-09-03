/**
 * DIAGNÓSTICO TEMPORÁRIO — colar no DevTools do PDV interno (F12 → Console).
 * NÃO muda regra de negócio. Só observa o fluxo atual.
 *
 * Mede T0..T8, o valor do input no Enter, e se o próximo bip entra no mesmo buffer.
 *
 * Depois de 20 bips: __erpPdvScanDiag.dump()
 */
(function installPdvScannerDiag() {
    if (window.__erpPdvScanDiag?.installed) {
        console.warn('[PDV-DIAG] já instalado — use __erpPdvScanDiag.dump()');
        return window.__erpPdvScanDiag;
    }

    const input = () => document.getElementById('erp-pdv-search');
    const state = {
        installed: true,
        scans: [],
        events: [],
        current: null,
        scanSeq: 0,
        lastEnterAt: 0,
    };

    function now() {
        return performance.now();
    }

    function mark(name, extra = {}) {
        const row = { t: now(), name, ...extra };
        state.events.push(row);
        console.log(`[PDV-DIAG] ${name}`, extra);
        return row;
    }

    function currentOrNewFromDigit(digit) {
        if (! state.current || state.current.entered) {
            state.scanSeq += 1;
            state.current = {
                scan: state.scanSeq,
                t0: now(),
                digits: '',
                inputBeforeFirstDigit: input()?.value ?? '',
                t1: null,
                t2: null,
                t3: null,
                t4: null,
                t5: null,
                t6: null,
                t7: null,
                t8: null,
                captured: '',
                enqueued: [],
                queueLengthAtEnqueue: null,
                processingAtEnqueue: null,
                inputAtEnter: '',
                inputAfterEnqueue: '',
                inputClearedBeforeRequest: null,
                concatenated: false,
                entered: false,
            };
            mark('T0', {
                scan: state.current.scan,
                digit,
                inputBefore: state.current.inputBeforeFirstDigit,
                leftoverFromPrevious: state.current.inputBeforeFirstDigit !== '',
            });
        }

        return state.current;
    }

    document.addEventListener('keydown', (event) => {
        const el = input();

        if (! el || event.target !== el) {
            return;
        }

        if (event.key === 'Enter') {
            const cur = state.current;
            if (! cur) {
                return;
            }

            cur.t1 = now();
            cur.inputAtEnter = el.value;
            cur.captured = el.value;
            cur.concatenated = String(el.value || '').length > 14;
            cur.entered = true;
            state.lastEnterAt = cur.t1;
            mark('T1', {
                scan: cur.scan,
                inputAtEnter: el.value,
                length: el.value.length,
                leftoverBeforeThisScan: cur.inputBeforeFirstDigit,
            });
            return;
        }

        if (event.key.length === 1 && ! event.ctrlKey && ! event.altKey && ! event.metaKey) {
            const cur = currentOrNewFromDigit(event.key);
            cur.digits += event.key;
        }
    }, true);

    const origEnqueue = window.enqueuePdvScan;

    if (typeof origEnqueue === 'function') {
        window.enqueuePdvScan = function enqueuePdvScanDiag(raw, options) {
            const cur = state.current;
            const before = input()?.value ?? '';
            const t2 = now();

            if (cur) {
                cur.t2 = t2;
                cur.queueLengthAtEnqueue = (function peekLength() {
                    const busy = window.__erpPdvScanQueueBusy?.();
                    return { busy };
                }());
                cur.processingAtEnqueue = window.__erpPdvScanQueueProcessing?.() ?? null;
            }

            mark('T2', {
                scan: cur?.scan,
                raw,
                rawLength: String(raw ?? '').length,
                inputBeforeEnqueue: before,
                processing: window.__erpPdvScanQueueProcessing?.() ?? null,
            });

            const result = origEnqueue.apply(this, arguments);

            const after = input()?.value ?? '';
            const cleared = after === '';

            if (cur) {
                cur.t3 = now();
                cur.inputAfterEnqueue = after;
                cur.inputClearedBeforeRequest = cleared;
                cur.enqueued.push(String(raw ?? ''));
            }

            mark('T3', {
                scan: cur?.scan,
                inputAfterEnqueue: after,
                inputClearedBeforeRequest: cleared,
                sameBufferStillFilled: ! cleared,
            });

            if (cur) {
                state.scans.push(cur);
            }

            return result;
        };
    } else {
        console.warn('[PDV-DIAG] enqueuePdvScan ausente');
    }

    const hook = window.Livewire?.hook;

    if (typeof hook === 'function') {
        hook('request', ({ respond }) => {
            const cur = state.scans[state.scans.length - 1];
            if (cur && cur.t5 == null) {
                cur.t5 = now();
                mark('T5', { scan: cur.scan });
            }

            respond(({ status }) => {
                const last = state.scans[state.scans.length - 1];
                if (last && last.t6 == null) {
                    last.t6 = now();
                    mark('T6', { scan: last.scan, status });
                }
            });
        });

        hook('morph.updated', ({ el }) => {
            const last = state.scans[state.scans.length - 1];
            if (! last || last.t7 != null) {
                return;
            }

            if (el?.querySelector?.('.erp-pdv__grid--cupom') || el?.classList?.contains('erp-pdv__grid-row')) {
                last.t7 = now();
                mark('T7', { scan: last.scan });
            }
        });
    }

    const origProcessFlag = Object.getOwnPropertyDescriptor(window, '__erpPdvScanQueueProcessing');

    setInterval(() => {
        const processing = window.__erpPdvScanQueueProcessing?.();
        const last = state.scans[state.scans.length - 1];

        if (processing && last && last.t4 == null) {
            last.t4 = now();
            mark('T4', { scan: last.scan });
        }
    }, 5);

    function rowMs(a, b) {
        if (a == null || b == null) {
            return null;
        }

        return Math.round(b - a);
    }

    window.__erpPdvScanDiag = {
        installed: true,
        state,
        dump() {
            const table = state.scans.map((scan, idx) => {
                const next = state.scans[idx + 1];
                if (next && scan.t8 == null && next.t4 != null) {
                    scan.t8 = next.t4;
                }

                return {
                    SCAN: scan.scan,
                    CAPTURADO: scan.captured,
                    LEN: scan.captured?.length ?? 0,
                    CONCAT: scan.concatenated ? 'SIM' : 'não',
                    'LEFTOVER ANTES': scan.inputBeforeFirstDigit,
                    'INPUT APÓS ENQUEUE': scan.inputAfterEnqueue,
                    'LIMPO ANTES REQUEST': scan.inputClearedBeforeRequest ? 'SIM' : 'NÃO',
                    'ENTER→ENQUEUE': rowMs(scan.t1, scan.t2),
                    'ENQUEUE→REQUEST': rowMs(scan.t2, scan.t5),
                    REQUEST: rowMs(scan.t5, scan.t6),
                    MORPH: rowMs(scan.t6, scan.t7),
                    TOTAL: rowMs(scan.t0, scan.t7 ?? scan.t6),
                };
            });

            console.table(table);

            const captured = state.scans.length;
            const concatenated = state.scans.filter((scan) => scan.concatenated).length;
            const leftoverStarts = state.scans.filter((scan) => scan.inputBeforeFirstDigit).length;

            const summary = {
                capturadas: captured,
                concatenadas: concatenated,
                comecaramComBufferSujo: leftoverStarts,
                inputLimpaAntesDoRequest: state.scans.every((scan) => scan.inputClearedBeforeRequest) ? 'SIM' : 'NÃO',
                proximoCodigoEntraNoMesmoBuffer: leftoverStarts > 0 || state.scans.some((scan) => scan.inputAfterEnqueue !== ''),
            };

            console.log('[PDV-DIAG] RESUMO', summary);
            return { table, summary, scans: state.scans };
        },
        reset() {
            state.scans = [];
            state.events = [];
            state.current = null;
            state.scanSeq = 0;
        },
    };

    console.log('[PDV-DIAG] instalado. Passe 20 códigos e rode __erpPdvScanDiag.dump()');
    return window.__erpPdvScanDiag;
}());
