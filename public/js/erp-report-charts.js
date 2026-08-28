/**
 * Gráficos leves nos relatórios tabulares (Chart.js sob demanda).
 * Um gráfico por relatório; animation off; dados já agregados no Laravel.
 */
(function (window, document) {
    'use strict';

    var chartInstance = null;
    var chartJsLoading = null;

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[data-erp-report-chart-src="' + src + '"]');
            if (existing) {
                if (window.Chart) {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function () { resolve(); });
                existing.addEventListener('error', reject);
                return;
            }

            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.dataset.erpReportChartSrc = src;
            script.onload = function () { resolve(); };
            script.onerror = function () { reject(new Error('Falha ao carregar ' + src)); };
            document.head.appendChild(script);
        });
    }

    function ensureChartJs(chartJsUrl) {
        if (window.Chart) {
            return Promise.resolve();
        }
        if (chartJsLoading) {
            return chartJsLoading;
        }
        chartJsLoading = loadScript(chartJsUrl);
        return chartJsLoading;
    }

    function moneyTick(value) {
        try {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL',
                maximumFractionDigits: 0,
            }).format(value);
        } catch (e) {
            return 'R$ ' + String(value);
        }
    }

    function destroyChart() {
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
    }

    function setCard(el, title, value, hint) {
        if (!el) {
            return;
        }
        var titleEl = el.querySelector('[data-card-title]');
        var valueEl = el.querySelector('[data-card-value]');
        var hintEl = el.querySelector('[data-card-hint]');
        if (titleEl) {
            titleEl.textContent = title || '';
        }
        if (valueEl) {
            valueEl.textContent = value || '—';
        }
        if (hintEl) {
            hintEl.textContent = hint || '';
            hintEl.hidden = !hint;
        }
    }

    function renderCards(cards) {
        var totalEl = document.getElementById('erp-report-chart-card-total');
        var bestEl = document.getElementById('erp-report-chart-card-best');
        var avgEl = document.getElementById('erp-report-chart-card-avg');
        var wrap = document.getElementById('erp-report-chart-cards');

        if (!cards) {
            if (wrap) {
                wrap.hidden = true;
            }
            return;
        }

        if (wrap) {
            wrap.hidden = false;
        }

        setCard(
            totalEl,
            (cards.total && cards.total.label) || 'Total vendido',
            (cards.total && cards.total.text) || '—'
        );
        var best = cards.best_day || cards.best_month || null;
        setCard(
            bestEl,
            (best && best.label) || 'Melhor período',
            (best && best.text) || '—',
            (best && (best.day || best.month)) || ''
        );
        setCard(
            avgEl,
            (cards.avg && cards.avg.label) || 'Média',
            (cards.avg && cards.avg.text) || '—'
        );
    }

    function chartPeriodSuffix(meta) {
        if (meta && meta.granularity === 'day') {
            return ' (máx. 31 dias)';
        }
        if (meta && meta.clamped_to_12_months) {
            return ' (máx. 12 meses)';
        }
        return '';
    }

    function renderChart(canvas, payload, chartType) {
        destroyChart();
        if (!canvas || !window.Chart || !payload) {
            return;
        }

        var type = chartType === 'bar' ? 'bar' : 'line';
        var isDaily = !!(payload.meta && payload.meta.granularity === 'day');

        chartInstance = new window.Chart(canvas, {
            type: type,
            data: {
                labels: payload.labels || [],
                datasets: payload.datasets || [],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                layout: {
                    padding: { top: 4, right: 8, bottom: 0, left: 0 },
                },
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'start',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 10,
                            font: { size: 11 },
                            usePointStyle: true,
                            pointStyle: 'circle',
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var v = ctx.parsed?.y ?? 0;
                                return (ctx.dataset?.label || '') + ': ' + moneyTick(v);
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        ticks: {
                            font: { size: 10 },
                            callback: moneyTick,
                            maxTicksLimit: 6,
                            padding: 4,
                        },
                        grid: { color: 'rgba(15, 52, 96, 0.06)' },
                    },
                    x: {
                        border: { display: false },
                        ticks: {
                            font: { size: 10 },
                            maxRotation: 0,
                            padding: 2,
                            maxTicksLimit: isDaily ? 12 : undefined,
                        },
                        grid: { display: false },
                    },
                },
            },
        });
    }

    function buildQuery(form, chartOpts) {
        var params = new URLSearchParams();
        if (form) {
            new FormData(form).forEach(function (value, key) {
                if (String(value).trim() !== '' && key !== 'cols[]') {
                    params.append(key, value);
                }
            });
        }
        if (chartOpts.granularity) {
            params.set('chart_granularity', chartOpts.granularity);
        }
        if (chartOpts.empresas) {
            params.set('chart_empresas', chartOpts.empresas);
        }
        if (chartOpts.yoy) {
            params.set('chart_yoy', '1');
        }
        return params.toString();
    }

    function init(config) {
        var modeTableBtn = document.getElementById('erp-report-mode-table');
        var modeChartBtn = document.getElementById('erp-report-mode-chart');
        var tableWrap = document.getElementById('erp-report-table-view');
        var chartWrap = document.getElementById('erp-report-chart-view');
        var canvas = document.getElementById('erp-report-chart-canvas');
        var statusEl = document.getElementById('erp-report-chart-status');
        var metaEl = document.getElementById('erp-report-chart-meta');
        var form = document.getElementById('report-filters-form');
        var empresaSelect = document.getElementById('erp-report-chart-empresas');
        var yoyCheck = document.getElementById('erp-report-chart-yoy');
        var granularitySelect = document.getElementById('erp-report-chart-granularity');
        var refreshBtn = document.getElementById('erp-report-chart-refresh');
        var chartLoadedOnce = false;
        var chartJsUrl = config.chartJsUrl || '/js/vendor/chart.umd.min.js';

        if (granularitySelect && config.defaultGranularity) {
            granularitySelect.value = config.defaultGranularity === 'day' ? 'day' : 'month';
        }

        function setMode(mode) {
            var isChart = mode === 'chart';
            if (tableWrap) {
                tableWrap.hidden = isChart;
            }
            if (chartWrap) {
                chartWrap.hidden = !isChart;
            }
            if (modeTableBtn) {
                modeTableBtn.classList.toggle('viewer__btn--active', !isChart);
            }
            if (modeChartBtn) {
                modeChartBtn.classList.toggle('viewer__btn--active', isChart);
            }
            if (isChart && !chartLoadedOnce) {
                chartLoadedOnce = true;
                loadAndRender();
            }
        }

        function loadAndRender() {
            if (!config.chartDataUrl || !canvas) {
                return;
            }
            if (statusEl) {
                statusEl.textContent = 'Carregando gráfico…';
            }

            var opts = {
                empresas: empresaSelect ? empresaSelect.value : 'todas',
                yoy: !!(yoyCheck && yoyCheck.checked),
                granularity: granularitySelect ? granularitySelect.value : (config.defaultGranularity || 'month'),
            };
            var qs = buildQuery(form, opts);
            var url = config.chartDataUrl + (qs ? ('?' + qs) : '');

            ensureChartJs(chartJsUrl)
                .then(function () {
                    return fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(function (payload) {
                    renderCards(payload.meta && payload.meta.cards ? payload.meta.cards : null);
                    renderChart(canvas, payload, config.chartType || 'line');
                    if (statusEl) {
                        statusEl.textContent = '';
                    }
                    if (metaEl && payload.meta && payload.meta.period_label) {
                        metaEl.textContent = 'Período: ' + payload.meta.period_label + chartPeriodSuffix(payload.meta);
                    }
                })
                .catch(function (err) {
                    destroyChart();
                    renderCards(null);
                    if (statusEl) {
                        statusEl.textContent = 'Não foi possível carregar o gráfico.';
                    }
                    console.error(err);
                });
        }

        modeTableBtn?.addEventListener('click', function () { setMode('table'); });
        modeChartBtn?.addEventListener('click', function () { setMode('chart'); });
        refreshBtn?.addEventListener('click', function () {
            chartLoadedOnce = true;
            loadAndRender();
        });
        empresaSelect?.addEventListener('change', function () {
            if (chartWrap && !chartWrap.hidden) {
                loadAndRender();
            }
        });
        yoyCheck?.addEventListener('change', function () {
            if (chartWrap && !chartWrap.hidden) {
                loadAndRender();
            }
        });
        granularitySelect?.addEventListener('change', function () {
            if (chartWrap && !chartWrap.hidden) {
                loadAndRender();
            }
        });

        setMode('table');
    }

    window.ErpReportCharts = { init: init };
})(window, document);
