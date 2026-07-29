(() => {
    const readJson = (id) => {
        const node = document.getElementById(id);
        if (!node) {
            return null;
        }

        try {
            return JSON.parse(node.textContent || '');
        } catch {
            return null;
        }
    };

    const parseIsoDate = (value) => {
        if (!value) {
            return null;
        }

        const parts = value.split('-').map(Number);
        if (parts.length !== 3) {
            return null;
        }

        return new Date(parts[0], parts[1] - 1, parts[2]);
    };

    const filterSalesPoints = (payload, fromValue, toValue) => {
        const points = payload?.points ?? [];
        const from = parseIsoDate(fromValue);
        const to = parseIsoDate(toValue);

        if (!from || !to) {
            return points;
        }

        if (from > to) {
            return points.filter((point) => {
                const date = parseIsoDate(point.date);
                return date && date >= to && date <= from;
            });
        }

        return points.filter((point) => {
            const date = parseIsoDate(point.date);
            return date && date >= from && date <= to;
        });
    };

    const pointsToChartData = (points) => ({
        labels: points.map((point) => point.label),
        values: points.map((point) => point.value),
    });

    const moneyTick = (value) => `R$ ${Number(value).toLocaleString('pt-BR')}`;

    const axisDefaults = {
        border: { display: false },
        ticks: {
            color: '#475569',
            font: { size: 11, weight: '600' },
        },
    };

    const tooltipDefaults = {
        backgroundColor: '#0f3460',
        titleColor: '#fff',
        bodyColor: '#e2e8f0',
        padding: 10,
        cornerRadius: 6,
        displayColors: false,
    };

    const init = () => {
        if (typeof Chart === 'undefined') {
            return;
        }

        const charts = [];
        const salesPayload = readJson('erp-dash-sales-data');
        const cashflowData = readJson('erp-dash-cashflow-data');
        const mixData = readJson('erp-dash-mix-data');
        const salesCanvas = document.getElementById('erp-dash-sales-chart');
        const cashflowCanvas = document.getElementById('erp-dash-cashflow-chart');
        const mixCanvas = document.getElementById('erp-dash-mix-chart');
        const fromInput = document.querySelector('[data-erp-sales-from]');
        const toInput = document.querySelector('[data-erp-sales-to]');

        let salesChart = null;

        const applySalesFilter = () => {
            if (!salesChart || !salesPayload) {
                return;
            }

            const filtered = filterSalesPoints(
                salesPayload,
                fromInput?.value ?? salesPayload.defaultFrom,
                toInput?.value ?? salesPayload.defaultTo,
            );
            const chartData = pointsToChartData(filtered);

            salesChart.data.labels = chartData.labels;
            salesChart.data.datasets[0].data = chartData.values;
            salesChart.update();
        };

        if (salesCanvas && salesPayload) {
            const initial = filterSalesPoints(
                salesPayload,
                fromInput?.value ?? salesPayload.defaultFrom,
                toInput?.value ?? salesPayload.defaultTo,
            );
            const initialData = pointsToChartData(initial);

            salesChart = new Chart(salesCanvas, {
                type: 'line',
                data: {
                    labels: initialData.labels,
                    datasets: [{
                        label: 'Vendas (R$)',
                        data: initialData.values,
                        borderColor: '#1e5a9e',
                        backgroundColor: (ctx) => {
                            const { ctx: c, chartArea } = ctx.chart;
                            if (!chartArea) {
                                return 'rgba(30, 90, 158, 0.14)';
                            }
                            const gradient = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            gradient.addColorStop(0, 'rgba(30, 90, 158, 0.28)');
                            gradient.addColorStop(1, 'rgba(30, 90, 158, 0.03)');
                            return gradient;
                        },
                        fill: true,
                        tension: 0.3,
                        borderWidth: 2.5,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#1e5a9e',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 1.5,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: tooltipDefaults,
                    },
                    scales: {
                        y: {
                            ...axisDefaults,
                            ticks: {
                                ...axisDefaults.ticks,
                                callback: moneyTick,
                            },
                            grid: { color: 'rgba(15, 52, 96, 0.08)' },
                        },
                        x: {
                            ...axisDefaults,
                            ticks: {
                                ...axisDefaults.ticks,
                                maxRotation: 0,
                            },
                            grid: { display: false },
                        },
                    },
                },
            });

            charts.push(salesChart);

            const onDateChange = () => applySalesFilter();

            fromInput?.addEventListener('change', onDateChange);
            toInput?.addEventListener('change', onDateChange);
            fromInput?.addEventListener('input', onDateChange);
            toInput?.addEventListener('input', onDateChange);
        }

        if (cashflowCanvas && cashflowData) {
            charts.push(new Chart(cashflowCanvas, {
                type: 'bar',
                data: {
                    labels: cashflowData.labels,
                    datasets: [
                        {
                            label: 'Entradas',
                            data: cashflowData.entradas,
                            backgroundColor: '#15803d',
                            hoverBackgroundColor: '#166534',
                            borderRadius: 3,
                            borderSkipped: false,
                            maxBarThickness: 52,
                            categoryPercentage: 0.72,
                            barPercentage: 0.9,
                        },
                        {
                            label: 'Saídas',
                            data: cashflowData.saidas,
                            backgroundColor: '#b91c1c',
                            hoverBackgroundColor: '#991b1b',
                            borderRadius: 3,
                            borderSkipped: false,
                            maxBarThickness: 52,
                            categoryPercentage: 0.72,
                            barPercentage: 0.9,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    datasets: {
                        bar: {
                            categoryPercentage: 0.7,
                            barPercentage: 0.88,
                        },
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                boxHeight: 12,
                                color: '#334155',
                                font: { size: 11, weight: '700' },
                                padding: 12,
                            },
                        },
                        tooltip: {
                            ...tooltipDefaults,
                            displayColors: true,
                        },
                    },
                    scales: {
                        y: {
                            ...axisDefaults,
                            ticks: {
                                ...axisDefaults.ticks,
                                callback: moneyTick,
                            },
                            grid: { color: 'rgba(15, 52, 96, 0.08)' },
                        },
                        x: {
                            ...axisDefaults,
                            grid: { display: false },
                        },
                    },
                },
            }));
        }

        if (mixCanvas && mixData?.labels?.length) {
            charts.push(new Chart(mixCanvas, {
                type: 'doughnut',
                data: {
                    labels: mixData.labels,
                    datasets: [{
                        data: mixData.values,
                        backgroundColor: mixData.colors ?? ['#1e5a9e', '#0d9488', '#d97706'],
                        borderColor: '#f1f5f9',
                        borderWidth: 3,
                        hoverOffset: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 11,
                                boxHeight: 11,
                                color: '#334155',
                                font: { size: 11, weight: '700' },
                                padding: 10,
                            },
                        },
                        tooltip: {
                            ...tooltipDefaults,
                            displayColors: true,
                            callbacks: {
                                title: (items) => items?.[0]?.label ?? '',
                                label: (ctx) => {
                                    const value = Number(ctx.raw || 0);
                                    return ` R$ ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                                },
                                afterLabel: (ctx) => {
                                    const value = Number(ctx.raw || 0);
                                    const total = (ctx.dataset.data || []).reduce((sum, item) => sum + Number(item || 0), 0);
                                    const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return ` ${pct}% do total`;
                                },
                            },
                        },
                    },
                },
            }));
        }

        const fiscalCanvas = document.getElementById('erp-dash-fiscal-chart');
        const fiscalData = readJson('erp-dash-fiscal-data');

        if (fiscalCanvas && fiscalData?.labels?.length) {
            charts.push(new Chart(fiscalCanvas, {
                type: 'doughnut',
                data: {
                    labels: fiscalData.labels,
                    datasets: [{
                        data: fiscalData.values,
                        backgroundColor: fiscalData.colors ?? ['#1d4ed8', '#93c5fd', '#0f766e', '#f59e0b'],
                        borderColor: '#f1f5f9',
                        borderWidth: 3,
                        hoverOffset: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                color: '#334155',
                                font: { size: 10, weight: '700' },
                                padding: 8,
                            },
                        },
                        tooltip: {
                            ...tooltipDefaults,
                            displayColors: true,
                            callbacks: {
                                title: (items) => items?.[0]?.label ?? '',
                                label: (ctx) => {
                                    const value = Number(ctx.raw || 0);
                                    const qty = value.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
                                    return ` ${qty} doc.`;
                                },
                                afterLabel: (ctx) => {
                                    const value = Number(ctx.raw || 0);
                                    const total = (ctx.dataset.data || []).reduce((sum, item) => sum + Number(item || 0), 0);
                                    const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return ` ${pct}% do total`;
                                },
                            },
                        },
                    },
                },
            }));
        }

        const paymentsCanvas = document.getElementById('erp-dash-payments-chart');
        const paymentsData = readJson('erp-dash-payments-data');

        if (paymentsCanvas && paymentsData?.labels?.length) {
            charts.push(new Chart(paymentsCanvas, {
                type: 'doughnut',
                data: {
                    labels: paymentsData.labels,
                    datasets: [{
                        data: paymentsData.values,
                        backgroundColor: paymentsData.colors ?? ['#1d4ed8', '#0f766e', '#7c3aed', '#6366f1'],
                        borderColor: '#f1f5f9',
                        borderWidth: 3,
                        hoverOffset: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                color: '#334155',
                                font: { size: 10, weight: '700' },
                                padding: 8,
                            },
                        },
                        tooltip: {
                            ...tooltipDefaults,
                            displayColors: true,
                            callbacks: {
                                title: (items) => items?.[0]?.label ?? '',
                                label: (ctx) => {
                                    const value = Number(ctx.raw || 0);
                                    return ` R$ ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
                                },
                                afterLabel: (ctx) => {
                                    const value = Number(ctx.raw || 0);
                                    const total = (ctx.dataset.data || []).reduce((sum, item) => sum + Number(item || 0), 0);
                                    const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return ` ${pct}% do total`;
                                },
                            },
                        },
                    },
                },
            }));
        }

        const resizeCharts = () => charts.forEach((chart) => chart.resize());

        window.addEventListener('resize', resizeCharts);

        if (typeof ResizeObserver !== 'undefined') {
            const dash = document.querySelector('.erp-dash__charts');
            if (dash) {
                new ResizeObserver(resizeCharts).observe(dash);
            }
        }

        initGauges();
    };

    const toneColor = (tone) => {
        switch (tone) {
            case 'red':
                return '#ef4444';
            case 'orange':
                return '#f97316';
            case 'yellow':
                return '#eab308';
            case 'lime':
                return '#84cc16';
            case 'green':
                return '#16a34a';
            default:
                return '#64748b';
        }
    };

    const needleAngle = (percent) => {
        const start = Math.PI;
        const span = Math.PI;
        const capped = Math.max(0, Number(percent) || 0);

        if (capped <= 100) {
            return start + (span * (capped / 100));
        }

        // leve extrapolação além de 100% (estilo velocímetro moderno)
        const over = Math.min(0.1, ((capped - 100) / 100) * 0.35);

        return start + span + (span * over);
    };

    const drawArrowNeedle = (ctx, cx, cy, angle, length, color) => {
        const tipX = cx + Math.cos(angle) * length;
        const tipY = cy + Math.sin(angle) * length;
        const head = Math.max(6, length * 0.18);
        const shaft = Math.max(2, length * 0.055);
        const headAngle = Math.PI / 7;

        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.lineTo(
            tipX - Math.cos(angle) * (head * 0.55),
            tipY - Math.sin(angle) * (head * 0.55),
        );
        ctx.strokeStyle = color;
        ctx.lineWidth = shaft;
        ctx.lineCap = 'round';
        ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(tipX, tipY);
        ctx.lineTo(
            tipX - head * Math.cos(angle - headAngle),
            tipY - head * Math.sin(angle - headAngle),
        );
        ctx.lineTo(
            tipX - head * Math.cos(angle + headAngle),
            tipY - head * Math.sin(angle + headAngle),
        );
        ctx.closePath();
        ctx.fillStyle = color;
        ctx.fill();

        const hub = Math.max(3.2, length * 0.09);
        ctx.beginPath();
        ctx.arc(cx, cy, hub, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();

        ctx.beginPath();
        ctx.arc(cx, cy, hub * 0.42, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();
    };

    const drawGauge = (canvas) => {
        if (!canvas?.getContext) {
            return;
        }

        const percent = Number(canvas.dataset.percent || 0);
        const compact = canvas.dataset.compact === '1';
        const tone = canvas.dataset.tone || 'slate';
        const needleColor = toneColor(tone);
        const dpr = window.devicePixelRatio || 1;
        const cssWidth = canvas.clientWidth || (compact ? 96 : 280);
        const cssHeight = canvas.clientHeight || (compact ? 64 : 180);

        canvas.width = Math.round(cssWidth * dpr);
        canvas.height = Math.round(cssHeight * dpr);

        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, cssWidth, cssHeight);

        // Hub perto da base: aproveita a altura da caixa (compacta o painel sem cortar o arco)
        const padTop = compact ? 2 : 6;
        const approxR = cssWidth * (compact ? 0.4 : 0.38);
        const approxTrack = Math.max(compact ? 5 : 8, approxR * (compact ? 0.2 : 0.22));
        const hubPad = compact
            ? Math.ceil(approxTrack / 2 + 4)
            : Math.ceil(approxTrack / 2 + 7);
        const cx = cssWidth / 2;
        const cy = Math.max(hubPad, cssHeight - hubPad);
        // Reserva meia espessura do trilho em cima — senão o topo do arco é cortado
        const radius = Math.min(
            approxR,
            Math.max(12, cy - padTop - (approxTrack / 2)),
        );
        const start = Math.PI;
        const trackWidth = Math.max(compact ? 5 : 8, radius * (compact ? 0.2 : 0.22));

        // trilho interno
        ctx.beginPath();
        ctx.arc(cx, cy, radius, start, Math.PI * 2);
        ctx.strokeStyle = '#d6dee9';
        ctx.lineWidth = trackWidth + 3;
        ctx.lineCap = 'butt';
        ctx.stroke();

        // só as cores: 0–20 / 20–40 / 40–60 / 60–80 / 80–100
        const zones = [
            { from: 0, to: 0.2, color: '#ef4444' },
            { from: 0.2, to: 0.4, color: '#f97316' },
            { from: 0.4, to: 0.6, color: '#eab308' },
            { from: 0.6, to: 0.8, color: '#84cc16' },
            { from: 0.8, to: 1, color: '#16a34a' },
        ];

        zones.forEach((zone) => {
            ctx.beginPath();
            ctx.arc(cx, cy, radius, start + (Math.PI * zone.from), start + (Math.PI * zone.to));
            ctx.strokeStyle = zone.color;
            ctx.lineWidth = trackWidth;
            ctx.lineCap = 'butt';
            ctx.stroke();
        });

        const needleLength = (radius * 0.7) + (compact ? 0 : (0.5 * 96) / 25.4); // +0,50mm só na empresa
        drawArrowNeedle(ctx, cx, cy, needleAngle(percent), needleLength, needleColor);
    };

    const initGauges = () => {
        const canvases = document.querySelectorAll('[data-erp-gauge-canvas]');
        canvases.forEach((canvas) => drawGauge(canvas));

        if (typeof ResizeObserver !== 'undefined') {
            const row = document.querySelector('.erp-dash__gauges-row') || document.querySelector('.erp-dash__gauges');
            if (row && !row.dataset.erpGaugeObserved) {
                row.dataset.erpGaugeObserved = '1';
                new ResizeObserver(() => {
                    row.querySelectorAll('[data-erp-gauge-canvas]').forEach((canvas) => drawGauge(canvas));
                }).observe(row);
            }

            document.querySelectorAll('.erp-dash-sellers-modal__body').forEach((body) => {
                if (body.dataset.erpGaugeObserved) {
                    return;
                }
                body.dataset.erpGaugeObserved = '1';
                new ResizeObserver(() => {
                    body.querySelectorAll('[data-erp-gauge-canvas]').forEach((canvas) => drawGauge(canvas));
                }).observe(body);
            });
        }
    };

    window.erpDashRefreshGauges = initGauges;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
