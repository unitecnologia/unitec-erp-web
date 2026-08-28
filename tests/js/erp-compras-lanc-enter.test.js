/**
 * Teste do fluxo Enter no lançamento de compras (sem browser).
 * Simula o DOM mínimo e percorre Mg%/preço do 1º ao último.
 *
 * Rodar: node tests/js/erp-compras-lanc-enter.test.js
 */

function createFakeDom(rowCount) {
    const inputs = new Map();

    const cols = ['mg_venda', 'venda', 'mg_atacado', 'atacado', 'mg_especial', 'especial'];

    for (let i = 0; i < rowCount; i++) {
        for (const col of cols) {
            inputs.set(`${col}:${i}`, {
                disabled: false,
                readOnly: false,
                value: col.startsWith('mg_') ? '0,00' : '100,00',
                dataset: { mask: col.startsWith('mg_') ? 'percent-br' : 'money-br' },
                getAttribute(name) {
                    if (name === 'data-erp-lanc-enter') return col;
                    if (name === 'data-row-index') return String(i);
                    return null;
                },
                hasAttribute(name) {
                    return name === 'data-erp-lanc-enter';
                },
                focus() {},
                select() {},
                setSelectionRange() {},
                closest(sel) {
                    if (sel === '.erp-compras-lancamento-modal') return { querySelector: modalQuery };
                    if (sel === 'tr') return { classList: { add() {}, remove() {} } };
                    if (sel === 'tbody') {
                        return {
                            querySelectorAll() {
                                return [];
                            },
                        };
                    }
                    if (sel === '[wire\\:id]') return null;
                    return null;
                },
            });
        }
    }

    function modalQuery(selector) {
        const match = selector.match(
            /input\[data-erp-lanc-enter="([^"]+)"\]\[data-row-index="([^"]+)"\]/,
        );

        if (! match) {
            return null;
        }

        return inputs.get(`${match[1]}:${match[2]}`) ?? null;
    }

    return {
        querySelector(sel) {
            if (sel === '.erp-compras-lancamento-modal') {
                return { querySelector: modalQuery };
            }

            return null;
        },
        inputs,
    };
}

function proximoCampoFactory(findInput) {
    return function proximoCampo(col, rowIndex) {
        const existe = (c, i) => !! findInput(c, i);

        if (col === 'qtd') return { col: 'mg_venda', index: rowIndex };
        if (col === 'mg_venda') return { col: 'venda', index: rowIndex };
        if (col === 'venda') {
            if (existe('mg_venda', rowIndex + 1)) return { col: 'mg_venda', index: rowIndex + 1 };
            return existe('mg_atacado', 0) ? { col: 'mg_atacado', index: 0 } : null;
        }
        if (col === 'mg_atacado') return { col: 'atacado', index: rowIndex };
        if (col === 'atacado') {
            if (existe('mg_atacado', rowIndex + 1)) return { col: 'mg_atacado', index: rowIndex + 1 };
            return existe('mg_especial', 0) ? { col: 'mg_especial', index: 0 } : null;
        }
        if (col === 'mg_especial') return { col: 'especial', index: rowIndex };
        if (col === 'especial') {
            return existe('mg_especial', rowIndex + 1)
                ? { col: 'mg_especial', index: rowIndex + 1 }
                : null;
        }

        return null;
    };
}

function run(rowCount) {
    const dom = createFakeDom(rowCount);
    const findInput = (col, index) =>
        dom.querySelector('.erp-compras-lancamento-modal')
            .querySelector(`input[data-erp-lanc-enter="${col}"][data-row-index="${index}"]`);
    const proximoCampo = proximoCampoFactory(findInput);

    const steps = [];
    let col = 'mg_venda';
    let index = 0;
    let guard = 0;

    while (guard++ < 500) {
        const input = findInput(col, index);

        if (! input) {
            throw new Error(`Campo inexistente: ${col}@${index}`);
        }

        steps.push(`${col}@${index}`);
        const next = proximoCampo(col, index);

        if (! next) {
            break;
        }

        if (! findInput(next.col, next.index)) {
            throw new Error(`Próximo inexistente: ${next.col}@${next.index} (vindo de ${col}@${index})`);
        }

        col = next.col;
        index = next.index;
    }

    const expected = rowCount * 2 * 3; // 3 pares × 2 campos × N linhas

    if (steps.length !== expected) {
        throw new Error(`Esperado ${expected} passos, veio ${steps.length}`);
    }

    // Pares Mg → preço na mesma linha
    for (let i = 0; i < steps.length; i += 2) {
        const [c1, r1] = steps[i].split('@');
        const [c2, r2] = steps[i + 1].split('@');

        if (! c1.startsWith('mg_')) {
            throw new Error(`Passo par deveria ser Mg%: ${steps[i]}`);
        }

        const price = c1.replace('mg_', '');

        if (c2 !== price || r1 !== r2) {
            throw new Error(`Par inválido: ${steps[i]} -> ${steps[i + 1]}`);
        }
    }

    // Pontes entre blocos
    const lastVarejo = steps.indexOf(`venda@${rowCount - 1}`);
    const firstAtacado = steps.indexOf('mg_atacado@0');
    const lastAtacado = steps.indexOf(`atacado@${rowCount - 1}`);
    const firstEspecial = steps.indexOf('mg_especial@0');

    if (steps[lastVarejo + 1] !== 'mg_atacado@0') {
        throw new Error('Ponte Varejo → Atacado quebrada');
    }

    if (steps[lastAtacado + 1] !== 'mg_especial@0') {
        throw new Error('Ponte Atacado → Especial quebrada');
    }

    if (firstAtacado < 0 || firstEspecial < 0) {
        throw new Error('Blocos Atacado/Especial ausentes');
    }

    if (steps[steps.length - 1] !== `especial@${rowCount - 1}`) {
        throw new Error('Não terminou no último Especial');
    }

    return steps;
}

for (const n of [1, 3, 8]) {
    const steps = run(n);
    console.log(`OK ${n} linhas → ${steps.length} passos | início=${steps[0]} | fim=${steps[steps.length - 1]}`);
}

console.log('TODOS OS TESTES PASSARAM');
