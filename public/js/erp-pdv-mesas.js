/**
 * Painel Mesas do PDV (só carrega em terminais com a flag "Mesas").
 *
 * - Desenha as mesas no navegador (o painel é wire:ignore): status nunca re-renderiza o PDV.
 * - Pulso leve a cada 15 s, só com a aba visível, em rota sem sessão (não disputa o cupom).
 *   O servidor só devolve a lista quando algo mudou (assinatura "s").
 * - Com mesa aberta, o pulso renova a reserva só se houve atividade real do operador
 *   (tecla/clique) ou se há operação em andamento (modal aberto, ex.: Finalizar).
 * - Inatividade (parâmetro da empresa): sem tecla/clique pelo tempo configurado, a mesa é
 *   salva, liberada e o PDV volta ao balcão. Controlado por um único timer, sem requisições.
 * - Ao sair/recarregar a página, libera a reserva via sendBeacon.
 */
(function () {
    'use strict';

    const POLL_MS = 15000;
    const LIVRE = 0;
    const OUTRO_TERMINAL = 2;
    const AGUARDANDO_FECHAMENTO = 1;
    const MODAIS_CONFIRMAR = {
        mesa_parcial_confirmar: 'erp-pdv-mesa-parcial',
        mesa_reabrir: 'erp-pdv-mesa-reabrir',
    };
    const STORAGE_RECOLHIDO = 'erp.pdv.mesas.recolhido';

    const moeda = (valor) => Number(valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const rotuloPadrao = (numero) => 'Mesa ' + String(numero).padStart(2, '0');

    function createPainel(root) {
        const grid = root.querySelector('[data-mesas-grid]');
        const countEl = root.querySelector('[data-mesas-count]');
        const numeroInput = root.querySelector('[data-mesas-numero]');
        const atualBox = root.querySelector('[data-mesas-atual]');
        const atualLabel = root.querySelector('[data-mesas-atual-label]');
        const atualStatus = root.querySelector('[data-mesas-atual-status]');
        const botoes = {
            pedido: root.querySelector('[data-mesas-pedido]'),
            parcial: root.querySelector('[data-mesas-parcial]'),
            reimprimir: root.querySelector('[data-mesas-reimprimir]'),
            reabrir: root.querySelector('[data-mesas-reabrir]'),
            transferir: root.querySelector('[data-mesas-transferir]'),
            balcao: root.querySelector('[data-mesas-balcao]'),
        };
        const toggleBtn = root.querySelector('[data-mesas-toggle]');
        const miniEl = root.querySelector('[data-mesas-mini]');
        const legendaEls = {
            livre: root.querySelector('[data-mesas-legenda="livre"]'),
            ocupada: root.querySelector('[data-mesas-legenda="ocupada"]'),
            aqui: root.querySelector('[data-mesas-legenda="aqui"]'),
            outro: root.querySelector('[data-mesas-legenda="outro"]'),
            aguardando: root.querySelector('[data-mesas-legenda="aguardando"]'),
        };

        let nomesMap = lerNomesMap(root);

        const rotulo = (numero) => {
            const custom = nomesMap[String(numero)] ?? nomesMap[numero];
            if (custom != null && String(custom).trim() !== '') {
                return String(custom).trim();
            }

            return rotuloPadrao(numero);
        };

        const painel = {
            quantidade: Math.max(1, parseInt(root.dataset.quantidade || '0', 10) || 20),
            url: root.dataset.url || '',
            credencial: root.dataset.credencial || '',
            mesa: null,
            mapa: new Map(),
            assinatura: '',
            atividade: false,
            emVoo: false,
            abrindo: false,
            saindo: false,
            inatividadeMs: Math.max(1, parseInt(root.dataset.inatividade || '1', 10) || 1) * 60000,
            ultimaAtividade: Date.now(),
            timerInatividade: null,
            timer: null,
            cursor: null,
            tiles: new Map(),
            parado: false,
        };

        const mesaNumero = parseInt(root.dataset.mesaNumero || '', 10);
        if (mesaNumero > 0 && root.dataset.mesaToken) {
            painel.mesa = {
                numero: mesaNumero,
                id: parseInt(root.dataset.mesaId || '0', 10),
                token: root.dataset.mesaToken,
                aguardando: root.dataset.mesaAguardando === '1',
            };
        }

        alternarRecolhido(window.localStorage?.getItem(STORAGE_RECOLHIDO) === '1');

        function component() {
            const host = root.closest('[wire\\:id]');

            return host && window.Livewire ? window.Livewire.find(host.getAttribute('wire:id')) : null;
        }

        function call(metodo, ...args) {
            const wire = component();

            return wire ? wire.call(metodo, ...args) : Promise.resolve(null);
        }

        function refocusSearch() {
            window.dispatchEvent(new CustomEvent('erp-pdv-refocus-search'));
        }

        function numerosVisiveis() {
            const numeros = [];
            for (let n = 1; n <= painel.quantidade; n++) {
                numeros.push(n);
            }
            painel.mapa.forEach((_, numero) => {
                if (numero > painel.quantidade) {
                    numeros.push(numero);
                }
            });

            return numeros;
        }

        function criarTile(numero) {
            const tile = document.createElement('button');
            tile.type = 'button';
            tile.className = 'erp-pdv-mesas__tile is-livre';
            tile.dataset.numero = String(numero);
            tile.setAttribute('role', 'option');
            tile.setAttribute('data-erp-pdv-clickable', '');
            tile.innerHTML =
                '<span class="erp-pdv-mesas__tile-id" aria-hidden="true"></span>' +
                '<span class="erp-pdv-mesas__tile-nome"></span>' +
                '<span class="erp-pdv-mesas__tile-info" hidden></span>';
            const rotuloMesa = rotulo(numero);
            const idEl = tile.querySelector('.erp-pdv-mesas__tile-id');
            idEl.textContent = String(numero).padStart(2, '0');
            idEl.hidden = false;
            tile.classList.toggle('has-nome-custom', rotuloMesa !== rotuloPadrao(numero));
            tile.querySelector('.erp-pdv-mesas__tile-nome').textContent = rotuloMesa;

            return tile;
        }

        function render() {
            const numeros = numerosVisiveis();
            const vistos = new Set(numeros);

            painel.tiles.forEach((tile, numero) => {
                if (! vistos.has(numero)) {
                    tile.remove();
                    painel.tiles.delete(numero);
                }
            });

            let ocupadas = 0;
            // Contagem exclusiva por situação visual (mesas exibidas; sem dupla contagem).
            let legLivre = 0;
            let legOcupada = 0;
            let legAqui = 0;
            let legOutro = 0;
            let legAguardando = 0;
            const fragment = document.createDocumentFragment();

            numeros.forEach((numero) => {
                let tile = painel.tiles.get(numero);
                if (! tile) {
                    tile = criarTile(numero);
                    painel.tiles.set(numero, tile);
                }
                fragment.appendChild(tile);

                const row = painel.mapa.get(numero);
                const qtd = row ? row[1] : 0;
                const total = row ? row[2] : 0;
                const reserva = row ? row[3] : LIVRE;
                const nome = row ? row[4] : '';
                const minha = painel.mesa !== null && painel.mesa.numero === numero;
                const bloqueada = ! minha && reserva === OUTRO_TERMINAL;
                const ocupada = qtd > 0;
                const aguardando = minha ? painel.mesa.aguardando : (ocupada && row[5] === AGUARDANDO_FECHAMENTO);

                if (ocupada) {
                    ocupadas++;
                }

                // Mesma ordem de prioridade das classes do tile (sem sobrepor contagens).
                if (aguardando) {
                    legAguardando++;
                } else if (bloqueada) {
                    legOutro++;
                } else if (minha) {
                    legAqui++;
                } else if (ocupada) {
                    legOcupada++;
                } else {
                    legLivre++;
                }

                // Cores: livre / ocupada(+aberta aqui) / outro terminal / aguardando.
                // "Aberta aqui" usa a mesma cor de ocupada; o selo vem da classe is-selecionada.
                tile.classList.toggle('is-livre', ! ocupada && ! minha && ! bloqueada && ! aguardando);
                tile.classList.toggle('is-ocupada', (ocupada || minha) && ! bloqueada && ! aguardando);
                tile.classList.toggle('is-selecionada', minha);
                tile.classList.toggle('is-bloqueada', bloqueada && ! aguardando);
                tile.classList.toggle('is-aguardando', aguardando);
                tile.classList.toggle('is-cursor', painel.cursor === numero);
                tile.setAttribute('aria-selected', minha ? 'true' : 'false');

                const rotuloMesa = rotulo(numero);
                const customNome = rotuloMesa !== rotuloPadrao(numero);
                const idEl = tile.querySelector('.erp-pdv-mesas__tile-id');
                const infoEl = tile.querySelector('.erp-pdv-mesas__tile-info');
                idEl.textContent = String(numero).padStart(2, '0');
                idEl.hidden = false;
                tile.classList.toggle('has-nome-custom', customNome);
                tile.querySelector('.erp-pdv-mesas__tile-nome').textContent = rotuloMesa;
                if (ocupada) {
                    infoEl.textContent = 'R$ ' + moeda(total);
                    infoEl.hidden = false;
                } else {
                    infoEl.textContent = '';
                    infoEl.hidden = true;
                }

                let titulo = rotuloMesa + (ocupada ? ' — ' + qtd + ' item(ns) · R$ ' + moeda(total) : ' — livre');
                if (aguardando) {
                    titulo += '\nAguardando fechamento (pré-conta impressa)';
                }
                if (bloqueada) {
                    titulo += '\nEm atendimento' + (nome ? ': ' + nome : ' em outro terminal');
                } else if (minha) {
                    titulo += '\nAberta neste terminal';
                } else {
                    titulo += '\nDuplo clique para abrir';
                }
                tile.title = titulo;
            });

            grid.appendChild(fragment);
            countEl.textContent = String(ocupadas);
            if (legendaEls.livre) {
                legendaEls.livre.textContent = String(legLivre);
            }
            if (legendaEls.ocupada) {
                legendaEls.ocupada.textContent = String(legOcupada);
            }
            if (legendaEls.aqui) {
                legendaEls.aqui.textContent = String(legAqui);
            }
            if (legendaEls.outro) {
                legendaEls.outro.textContent = String(legOutro);
            }
            if (legendaEls.aguardando) {
                legendaEls.aguardando.textContent = String(legAguardando);
            }

            if (painel.mesa) {
                const row = painel.mapa.get(painel.mesa.numero);
                const aguardando = painel.mesa.aguardando;
                atualLabel.textContent = rotulo(painel.mesa.numero) + (row && row[1] > 0 ? ' · R$ ' + moeda(row[2]) : '');
                atualBox.classList.toggle('is-aguardando', aguardando);
                atualStatus.hidden = ! aguardando;
                botoes.pedido.hidden = aguardando;
                botoes.parcial.hidden = aguardando;
                botoes.transferir.hidden = aguardando;
                botoes.reimprimir.hidden = ! aguardando;
                botoes.reabrir.hidden = ! aguardando;
                atualBox.hidden = false;
                miniEl.textContent = String(painel.mesa.numero).padStart(2, '0');
                miniEl.title = rotulo(painel.mesa.numero) + ' aberta — clique para expandir o painel';
                miniEl.classList.toggle('is-aguardando', aguardando);
                miniEl.hidden = false;
            } else {
                atualBox.hidden = true;
                miniEl.hidden = true;
                miniEl.classList.remove('is-aguardando');
            }
        }

        function moverCursor(numero) {
            if (painel.cursor === numero) {
                return;
            }
            painel.tiles.get(painel.cursor)?.classList.remove('is-cursor');
            painel.cursor = numero;
            painel.tiles.get(numero)?.classList.add('is-cursor');
        }

        function abrir(numero) {
            if (! (numero > 0) || painel.abrindo) {
                return;
            }
            moverCursor(numero);
            painel.abrindo = true;
            Promise.resolve(call('abrirMesa', numero))
                .catch(() => {})
                .finally(() => { painel.abrindo = false; });
        }

        function reservaPerdida() {
            if (! painel.mesa) {
                return;
            }
            painel.mesa = null;
            render();
            call('tratarReservaMesaPerdida');
        }

        function agendar(ms) {
            window.clearTimeout(painel.timer);
            if (! painel.parado) {
                painel.timer = window.setTimeout(pulso, ms);
            }
        }

        async function pulso() {
            if (painel.parado) {
                return;
            }
            if (! document.body.contains(root)) {
                parar();

                return;
            }
            if (document.visibilityState !== 'visible' || painel.emVoo || ! painel.url) {
                agendar(POLL_MS);

                return;
            }

            painel.emVoo = true;
            const mesaEnviada = painel.mesa;
            const corpo = new URLSearchParams({ c: painel.credencial, s: painel.assinatura });

            if (mesaEnviada) {
                corpo.set('m', String(mesaEnviada.id));
                corpo.set('k', mesaEnviada.token);
                if (painel.atividade || operacaoEmAndamento()) {
                    corpo.set('a', 'renovar');
                }
            }
            painel.atividade = false;
            let proximo = POLL_MS;

            try {
                const resposta = await fetch(painel.url, {
                    method: 'POST',
                    body: corpo,
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });

                if (resposta.status === 401) {
                    const nova = await call('renovarCredencialMesas');
                    if (typeof nova === 'string' && nova !== '') {
                        painel.credencial = nova;
                        painel.emVoo = false;
                        agendar(500);

                        return;
                    }
                } else if (resposta.status >= 500) {
                    proximo = POLL_MS * 4;
                } else if (resposta.ok) {
                    const dados = await resposta.json();
                    const mesmaMesa = painel.mesa !== null && mesaEnviada !== null && painel.mesa.token === mesaEnviada.token;

                    if (Array.isArray(dados.m)) {
                        painel.mapa = new Map(dados.m.map((row) => [row[0], row]));
                    }
                    if (typeof dados.s === 'string') {
                        painel.assinatura = dados.s;
                    }

                    const minhaRow = painel.mesa ? painel.mapa.get(painel.mesa.numero) : null;
                    if (mesmaMesa && (dados.p === true || (minhaRow && minhaRow[3] === OUTRO_TERMINAL))) {
                        reservaPerdida();
                    } else if (Array.isArray(dados.m)) {
                        render();
                    }
                }
            } catch (erro) {
                // Rede instável: tenta no próximo pulso.
            }

            painel.emVoo = false;
            agendar(proximo);
        }

        function liberarAoSair() {
            if (! painel.mesa || ! painel.url || ! navigator.sendBeacon) {
                return;
            }
            const dados = new FormData();
            dados.append('c', painel.credencial);
            dados.append('m', String(painel.mesa.id));
            dados.append('k', painel.mesa.token);
            dados.append('a', 'liberar');
            navigator.sendBeacon(painel.url, dados);
        }

        /** Só eventos reais do operador contam (consultas automáticas e eventos sintéticos não). */
        function marcarAtividade(event) {
            if (event && event.isTrusted === false) {
                return;
            }
            painel.atividade = true;
            painel.ultimaAtividade = Date.now();
            // Reinicia o temporizador na hora (não espera o timeout antigo disparar).
            if (painel.mesa && ! painel.parado) {
                agendarInatividade(painel.inatividadeMs);
            }
        }

        /** Overlay/modal realmente visível (ignora nós hidden / Sair fechado no DOM). */
        function elementoUiVisivel(el) {
            if (! el || el.hidden || el.getAttribute('aria-hidden') === 'true') {
                return false;
            }

            if (el.id === 'erp-pdv-sair-overlay' && ! el.classList.contains('is-open')) {
                return false;
            }

            const style = window.getComputedStyle(el);

            return style.display !== 'none'
                && style.visibility !== 'hidden'
                && style.opacity !== '0';
        }

        /** Modal/overlay aberto no PDV (Finalizar, confirmações, cadastros): não sair da mesa. */
        function operacaoEmAndamento() {
            if (painel.abrindo || painel.saindo) {
                return true;
            }

            if (typeof window.isPdvSairOverlayOpen === 'function' && window.isPdvSairOverlayOpen()) {
                return true;
            }

            const candidatos = document.querySelectorAll(
                '.erp-pdv .erp-pdv-modal, .erp-pdv .erp-pdv-overlay, .erp-pdv .erp-pdv-confirm-overlay, .erp-pdv .erp-pdv-fiscal-overlay, .erp-pdv [data-erp-pdv-fiscal-progress].is-visible',
            );

            for (const el of candidatos) {
                if (elementoUiVisivel(el)) {
                    return true;
                }
            }

            return false;
        }

        /** Um único timer: ao disparar, reagenda pelo tempo restante se houve atividade no meio. */
        function agendarInatividade(ms) {
            window.clearTimeout(painel.timerInatividade);
            painel.timerInatividade = null;
            if (painel.parado || ! painel.mesa) {
                return;
            }
            const resta = ms ?? (painel.ultimaAtividade + painel.inatividadeMs - Date.now());
            painel.timerInatividade = window.setTimeout(verificarInatividade, Math.max(1000, resta));
        }

        function verificarInatividade() {
            painel.timerInatividade = null;
            if (painel.parado || ! painel.mesa || painel.saindo) {
                return;
            }
            if (Date.now() - painel.ultimaAtividade < painel.inatividadeMs - 500) {
                agendarInatividade();

                return;
            }
            if (operacaoEmAndamento()) {
                agendarInatividade(5000);

                return;
            }

            const mesaToken = painel.mesa.token;
            painel.saindo = true;
            Promise.resolve(call('sairDaMesaPorInatividade'))
                .catch(() => {})
                .finally(() => {
                    painel.saindo = false;
                    // Servidor recusou (ex.: modal abriu no meio): tenta de novo mais tarde.
                    if (painel.mesa && painel.mesa.token === mesaToken) {
                        agendarInatividade(5000);
                    }
                });
        }

        function detalheEvento(event) {
            const raw = event && event.detail !== undefined ? event.detail : event;

            if (Array.isArray(raw)) {
                return raw[0] && typeof raw[0] === 'object' ? raw[0] : {};
            }

            return raw && typeof raw === 'object' ? raw : {};
        }

        function onContexto(event) {
            const tinhaMesa = !! painel.mesa;
            const d = detalheEvento(event);
            painel.mesa = d.numero && d.token
                ? { numero: Number(d.numero), id: Number(d.id), token: String(d.token), aguardando: d.aguardando === true }
                : null;
            painel.cursor = painel.mesa ? painel.mesa.numero : null;
            painel.assinatura = '';
            painel.ultimaAtividade = Date.now();
            render();
            agendar(150);
            agendarInatividade();

            // Saiu da mesa → balcão: cursor no Código (inatividade ou sair manual).
            if (tinhaMesa && ! painel.mesa) {
                refocusSearch();
                window.setTimeout(refocusSearch, 80);
                window.setTimeout(refocusSearch, 250);
                window.setTimeout(refocusSearch, 600);
            }
        }

        function onPoll() {
            painel.assinatura = '';
            agendar(250);
        }

        function onFocus() {
            alternarRecolhido(false);
            numeroInput.removeAttribute('readonly');
            numeroInput.focus();
            numeroInput.select();
        }

        function onVisibilidade() {
            if (document.visibilityState === 'visible') {
                agendar(200);
            }
        }

        /**
         * Só Enter (com número digitado) e Esc (com texto) do campo "Abrir nº" são tratados aqui.
         * Todos os atalhos do PDV (F-keys, Ctrl+…, Esc com campo vazio) seguem para o erp-pdv.js.
         */
        function onKeydownCapture(event) {
            if (event.target !== numeroInput) {
                return;
            }

            const digitado = numeroInput.value.replace(/\D/g, '');

            if (event.key === 'Enter' && digitado !== '') {
                event.preventDefault();
                event.stopImmediatePropagation();
                numeroInput.value = '';
                abrir(parseInt(digitado, 10));

                return;
            }

            if (event.key === 'Escape' && numeroInput.value !== '') {
                event.preventDefault();
                event.stopImmediatePropagation();
                numeroInput.value = '';
                refocusSearch();
            }
        }

        function parar() {
            painel.parado = true;
            window.clearTimeout(painel.timer);
            window.clearTimeout(painel.timerInatividade);
            window.removeEventListener('erp-pdv-mesa-contexto', onContexto);
            window.removeEventListener('erp-pdv-mesas-poll', onPoll);
            window.removeEventListener('erp-pdv-mesas-focus', onFocus);
            window.removeEventListener('erp-pdv-modal-opened', onModalAberto);
            window.removeEventListener('keydown', onModalTecla, true);
            window.removeEventListener('keydown', onKeydownCapture, true);
            window.removeEventListener('keydown', marcarAtividade, true);
            window.removeEventListener('pointerdown', marcarAtividade, true);
            window.removeEventListener('pagehide', liberarAoSair);
            document.removeEventListener('visibilitychange', onVisibilidade);
            document.removeEventListener('livewire:navigating', onNavigating);
            delete root.__erpPdvMesas;
        }

        function onNavigating() {
            liberarAoSair();
            parar();
        }

        grid.addEventListener('mousedown', (event) => {
            if (event.target.closest('.erp-pdv-mesas__tile')) {
                event.preventDefault();
            }
        });
        grid.addEventListener('click', (event) => {
            const tile = event.target.closest('.erp-pdv-mesas__tile');
            if (tile) {
                moverCursor(Number(tile.dataset.numero));
            }
        });
        grid.addEventListener('dblclick', (event) => {
            const tile = event.target.closest('.erp-pdv-mesas__tile');
            if (tile) {
                abrir(Number(tile.dataset.numero));
            }
        });

        numeroInput.addEventListener('input', () => {
            const limpo = numeroInput.value.replace(/\D/g, '').slice(0, 3);
            if (limpo !== numeroInput.value) {
                numeroInput.value = limpo;
            }
        });

        function alternarRecolhido(recolher) {
            const recolhido = root.classList.toggle('is-recolhido', recolher);
            toggleBtn.setAttribute('aria-expanded', recolhido ? 'false' : 'true');
            toggleBtn.title = recolhido ? 'Expandir painel de mesas' : 'Recolher painel de mesas';
            window.localStorage?.setItem(STORAGE_RECOLHIDO, recolhido ? '1' : '0');
        }

        [toggleBtn, miniEl].forEach((el) => el.addEventListener('mousedown', (event) => event.preventDefault()));
        toggleBtn.addEventListener('click', () => {
            alternarRecolhido(! root.classList.contains('is-recolhido'));
            refocusSearch();
        });
        miniEl.addEventListener('click', () => {
            alternarRecolhido(false);
            refocusSearch();
        });

        const acoes = {
            pedido: 'imprimirPedidoMesa',
            parcial: 'imprimirParcialMesa',
            reimprimir: 'imprimirParcialMesa',
            reabrir: 'openReabrirMesa',
            transferir: 'openTransferirMesa',
            balcao: 'sairDaMesa',
        };
        Object.keys(acoes).forEach((chave) => {
            const botao = botoes[chave];
            botao.addEventListener('mousedown', (event) => event.preventDefault());
            botao.addEventListener('click', () => {
                if (botao.disabled) {
                    return;
                }
                botao.disabled = true;
                Promise.resolve(call(acoes[chave]))
                    .catch(() => {})
                    .finally(() => { botao.disabled = false; });
            });
        });

        /** Modais Pré-conta / Reabrir: foco no "Sim" e teclas S/N (Enter/Esc seguem o padrão do PDV). */
        function onModalAberto(event) {
            const prefixo = MODAIS_CONFIRMAR[detalheEvento(event).modal];
            if (! prefixo) {
                return;
            }
            window.requestAnimationFrame(() => document.getElementById(prefixo + '-sim')?.focus());
        }

        function onModalTecla(event) {
            if (event.ctrlKey || event.altKey || event.metaKey) {
                return;
            }
            const tecla = (event.key || '').toLowerCase();
            if (tecla !== 's' && tecla !== 'n') {
                return;
            }
            const prefixo = Object.values(MODAIS_CONFIRMAR).find((p) => document.getElementById(p + '-title'));
            if (! prefixo) {
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            document.getElementById(prefixo + (tecla === 's' ? '-sim' : '-nao'))?.click();
        }

        window.addEventListener('erp-pdv-mesa-contexto', onContexto);
        window.addEventListener('erp-pdv-mesas-poll', onPoll);
        window.addEventListener('erp-pdv-mesas-focus', onFocus);
        window.addEventListener('erp-pdv-modal-opened', onModalAberto);
        window.addEventListener('keydown', marcarAtividade, true);
        window.addEventListener('keydown', onModalTecla, true);
        window.addEventListener('keydown', onKeydownCapture, true);
        window.addEventListener('pointerdown', marcarAtividade, true);
        window.addEventListener('pagehide', liberarAoSair);
        document.addEventListener('visibilitychange', onVisibilidade);
        document.addEventListener('livewire:navigating', onNavigating);

        render();
        agendar(0);
        agendarInatividade();

        return painel;
    }

    /**
     * Nomes vêm de Configurações → Mesas (pdv_mesas.nome), embutidos em base64
     * para não quebrar no atributo HTML / wire:ignore.
     */
    function lerNomesMap(root) {
        const b64 = root.getAttribute('data-nomes-b64') || '';
        if (b64) {
            try {
                const json = atob(b64);
                const parsed = JSON.parse(json);
                if (parsed && typeof parsed === 'object' && ! Array.isArray(parsed)) {
                    return parsed;
                }
            } catch (e) {
                // fallback abaixo
            }
        }

        const raw = root.getAttribute('data-nomes') || root.dataset.nomes || '';
        if (! raw) {
            return {};
        }

        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function init() {
        const root = document.querySelector('[data-erp-pdv-mesas]');
        if (! root) {
            return;
        }

        const rev = root.getAttribute('data-nomes-rev') || '';
        if (root.__erpPdvMesas && root.__erpPdvMesasRev === rev) {
            return;
        }

        root.__erpPdvMesas = createPainel(root);
        root.__erpPdvMesasRev = rev;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
    document.addEventListener('livewire:navigated', init);
    document.addEventListener('livewire:initialized', init);
})();
