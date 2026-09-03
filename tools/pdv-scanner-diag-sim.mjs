#!/usr/bin/env node
/**
 * DIAGNÓSTICO TEMPORÁRIO — não altera o PDV.
 *
 * Replica o onEnter() atual de 6.4.1.177:
 *   const termo = this.$refs.codigo.value;
 *   this.q = termo;                    // NÃO zera
 *   window.enqueuePdvScan(termo);
 *
 * e as funções reais de public/js/erp-pdv.js:
 *   expandPdvScanCode / trySplitConcatenatedEan13
 *
 * Uso: node tools/pdv-scanner-diag-sim.mjs
 */

function isValidEan13(code) {
    if (! /^\d{13}$/.test(code)) {
        return false;
    }

    let sum = 0;

    for (let i = 0; i < 12; i++) {
        sum += Number(code[i]) * (i % 2 === 0 ? 1 : 3);
    }

    return ((10 - (sum % 10)) % 10) === Number(code[12]);
}

function trySplitConcatenatedEan13(code) {
    if (code.length !== 26 || ! /^\d{26}$/.test(code)) {
        return null;
    }

    const first = code.slice(0, 13);
    const second = code.slice(13, 26);

    if (! isValidEan13(first) || ! isValidEan13(second)) {
        return null;
    }

    return [first, second];
}

function expandPdvScanCode(code) {
    if (! /^\d+$/.test(code)) {
        return [code];
    }

    return trySplitConcatenatedEan13(code) || [code];
}

function normalizePdvScanCode(raw) {
    return String(raw ?? '').trim().toUpperCase();
}

function makeEan13(base12) {
    const digits = String(base12).padStart(12, '0').slice(0, 12);
    let sum = 0;

    for (let i = 0; i < 12; i++) {
        sum += Number(digits[i]) * (i % 2 === 0 ? 1 : 3);
    }

    return digits + String((10 - (sum % 10)) % 10);
}

function enqueueFromInput(raw, queue) {
    const normalized = normalizePdvScanCode(raw);

    if (! normalized) {
        return [];
    }

    const parts = expandPdvScanCode(normalized);
    const pushed = [];

    for (const code of parts) {
        if (! code) {
            continue;
        }

        queue.push(code);
        pushed.push({
            code,
            length: code.length,
            queueLength: queue.length,
            concatenated: code.length > 14,
            split: parts.length > 1,
        });
    }

    return pushed;
}

/**
 * Modelo do campo atual.
 * @param {string[]} physicalCodes
 * @param {{ clearOnEnter?: boolean, livewireClearBeforeNext?: boolean }} opts
 */
function runBurst(physicalCodes, opts = {}) {
    const { clearOnEnter = false, livewireClearBeforeNext = false } = opts;
    let q = '';
    const queue = [];
    const scans = [];
    const enqueued = [];

    physicalCodes.forEach((physical, index) => {
        const leftoverBefore = q;
        q += physical;

        const termo = q;
        q = termo;

        const pushed = enqueueFromInput(termo, queue);

        let clearedAtEnter = false;

        if (clearOnEnter) {
            q = '';
            clearedAtEnter = true;
        } else if (livewireClearBeforeNext) {
            q = '';
        }

        enqueued.push(...pushed.map((row) => ({ scan: index + 1, leftoverBefore, captured: termo, ...row })));
        scans.push({
            scan: index + 1,
            physical,
            leftoverBefore,
            captured: termo,
            capturedLen: termo.length,
            concatenatedCapture: termo.length > 14 || termo !== physical,
            inputClearedAtEnter: clearedAtEnter,
            inputAfterEnter: q,
            queuedFromThisEnter: pushed.map((row) => row.code),
            'ENTER→ENQUEUE': 0,
            'ENQUEUE→REQUEST': 'fila JS (0ms) + espera do item anterior',
            REQUEST: 'Livewire set+call (não medido neste VM)',
            MORPH: 'após resposta (não medido neste VM)',
            TOTAL: 'captura 0ms; visual = request+morph',
        });
    });

    return {
        physical: physicalCodes.length,
        enqueued: enqueued.length,
        processed: enqueued.length,
        concatenatedCaptures: scans.filter((row) => row.concatenatedCapture).length,
        concatenatedQueued: enqueued.filter((row) => row.concatenated).length,
        lostPhysicalVsShortCodes: physicalCodes.length - enqueued.filter((row) => row.length <= 14).length,
        queuedCodes: enqueued.map((row) => row.code),
        queuedLens: enqueued.map((row) => row.length),
        orderOk: null,
        scans,
        enqueued,
        inputClearedBeforeRequest: scans.every((row) => row.inputClearedAtEnter),
    };
}

const SAME = '7622210571540';
const A = makeEan13('111111111111');
const B = makeEan13('222222222222');
const C = makeEan13('333333333333');
const abc = [A, B, C, A, B, C];

const current20 = runBurst(Array(20).fill(SAME), { clearOnEnter: false });
const homolog20 = runBurst(Array(20).fill(SAME), { clearOnEnter: true });
const raceWin20 = runBurst(Array(20).fill(SAME), { livewireClearBeforeNext: true });
const currentAbc = runBurst(abc, { clearOnEnter: false });
const homologAbc = runBurst(abc, { clearOnEnter: true });

currentAbc.orderOk = currentAbc.queuedCodes.join(',') === abc.join(',');
homologAbc.orderOk = homologAbc.queuedCodes.join(',') === abc.join(',');

function print(title, value) {
    console.log('\n' + '='.repeat(72));
    console.log(title);
    console.log('='.repeat(72));
    console.log(typeof value === 'string' ? value : JSON.stringify(value, null, 2));
}

print('EANS', {
    SAME,
    sameValid: isValidEan13(SAME),
    A,
    B,
    C,
    aValid: isValidEan13(A),
    bValid: isValidEan13(B),
    cValid: isValidEan13(C),
});

print('ATUAL 20x mesmo EAN — input NÃO limpo no Enter (6.4.1.177)', {
    capturadas: current20.physical,
    enfileiradas: current20.enqueued,
    processadas: current20.processed,
    capturasConcatenadas: current20.concatenatedCaptures,
    itensFilaConcatenados: current20.concatenatedQueued,
    perdidasOuFundidas: current20.lostPhysicalVsShortCodes,
    inputLimpaAntesDoRequest: current20.inputClearedBeforeRequest,
    primeiros8Capturados: current20.scans.slice(0, 8).map((row) => ({
        scan: row.scan,
        leftover: row.leftoverBefore,
        captured: row.captured,
        len: row.capturedLen,
        queued: row.queuedFromThisEnter,
    })),
    filaCompleta: current20.queuedCodes.map((code, i) => `${i + 1}:${code}(${code.length})`),
});

print('HOMOLOGADO 20x mesmo EAN — limpa o input no Enter', {
    capturadas: homolog20.physical,
    enfileiradas: homolog20.enqueued,
    processadas: homolog20.processed,
    capturasConcatenadas: homolog20.concatenatedCaptures,
    itensFilaConcatenados: homolog20.concatenatedQueued,
    inputLimpaAntesDoRequest: homolog20.inputClearedBeforeRequest,
    filaCompleta: homolog20.queuedCodes,
});

print('SE o Livewire zerasse ANTES do próximo bip (corrida ganhando)', {
    capturadas: raceWin20.physical,
    enfileiradas: raceWin20.enqueued,
    capturasConcatenadas: raceWin20.concatenatedCaptures,
    nota: 'Só deixa de concatenar se a resposta Livewire chegar antes do próximo scanner. No caixa rápido isso perde.',
});

print('ATUAL A B C A B C — input NÃO limpo', {
    esperado: abc,
    fila: currentAbc.queuedCodes,
    ordemIgual: currentAbc.orderOk,
    capturas: currentAbc.scans.map((row) => ({
        scan: row.scan,
        leftover: row.leftoverBefore,
        captured: row.captured,
        queued: row.queuedFromThisEnter,
    })),
});

print('HOMOLOGADO A B C A B C — limpa no Enter', {
    esperado: abc,
    fila: homologAbc.queuedCodes,
    ordemIgual: homologAbc.orderOk,
});

const table = current20.scans.map((row) => ({
    SCAN: row.scan,
    CAPTURADO: row.captured,
    LEN: row.capturedLen,
    'ENTER→ENQUEUE': '0ms',
    'ENQUEUE→REQUEST': '0ms JS + espera fila',
    REQUEST: 'não medido (Livewire)',
    MORPH: 'não medido',
    TOTAL: 'visual = Livewire+morph',
    LEFTOVER: row.leftoverBefore || '(vazio)',
}));

print('TABELA 20 LEITURAS (modelo atual)', table);

print('VEREDICTO', {
    CONCATENACAO_ACONTECE_EM: 'captura/input ANTES de enqueuePdvScan',
    PROXIMO_CODIGO_ENTRA_NO_MESMO_BUFFER: true,
    INPUT_LIMPA_ANTES_DO_REQUEST: false,
    FILA_NAO_INVENTA_DIGITOS: true,
    LIVEWIRE_NAO_E_A_ORIGEM_DA_CONCAT: true,
    MODAL_NAO_E_A_ORIGEM_DA_CONCAT: true,
    CAUSA: 'onEnter() copia o valor para this.q e NÃO zera #erp-pdv-search. O próximo bip digita em cima do leftover. $wire.entangle(pdvSearch).live ainda pode repor o valor antigo depois do morph.',
    DELAY: 'processPdvScanQueue espera component.set + component.call (2 roundtrips Livewire) + morph da grade. O bip físico já terminou; o item só aparece depois dessa espera sequencial.',
    FRASE: 'O código concatena porque o próximo bip entra no mesmo input que ainda guarda o código anterior (onEnter não zera q antes do Livewire) e o atraso para aparecer na grade ocorre porque cada item da fila espera dois commits Livewire (set pdvSearch + handlePdvSearchEnter) e o morph do cupom.',
});
