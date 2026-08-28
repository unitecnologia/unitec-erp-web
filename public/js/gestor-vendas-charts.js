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
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (!from || !to) {
            return points.filter((point) => {
                const date = parseIsoDate(point.date);
                return date && date <= today;
            });
        }

        const lo = from <= to ? from : to;
        const hi = from <= to ? to : from;

        return points.filter((point) => {
            const date = parseIsoDate(point.date);
            return date && date >= lo && date <= hi && date <= today;
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
            font: { size: 10, weight: '600' },
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

    const doughnutBase = (labels, values, colors, unit) => ({
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderColor: '#ffffff',
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
                            if (unit === 'count') {
                                return ` ${value.toLocaleString('pt-BR', { maximumFractionDigits: 0 })} doc.`;
                            }

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
    });

    const init = () => {
        if (typeof Chart === 'undefined') {
            return;
        }

        const charts = [];
        const salesPayload = readJson('gestor-sales-data');
        const salesCanvas = document.getElementById('gestor-sales-chart');
        const fromInput = document.querySelector('[data-gestor-sales-from]');
        const toInput = document.querySelector('[data-gestor-sales-to]');

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
                                autoSkip: true,
                                maxTicksLimit: 6,
                            },
                            grid: { display: false },
                        },
                    },
                },
            });

            charts.push(salesChart);
            fromInput?.addEventListener('change', applySalesFilter);
            toInput?.addEventListener('change', applySalesFilter);
            fromInput?.addEventListener('input', applySalesFilter);
            toInput?.addEventListener('input', applySalesFilter);
        }

        const mixData = readJson('gestor-mix-data');
        const mixCanvas = document.getElementById('gestor-mix-chart');
        if (mixCanvas && mixData?.labels?.length) {
            charts.push(new Chart(mixCanvas, doughnutBase(
                mixData.labels,
                mixData.values,
                mixData.colors ?? ['#1e5a9e', '#0d9488', '#d97706'],
                mixData.unit ?? 'money',
            )));
        }

        const fiscalData = readJson('gestor-fiscal-data');
        const fiscalCanvas = document.getElementById('gestor-fiscal-chart');
        if (fiscalCanvas && fiscalData?.labels?.length && !fiscalData.empty) {
            charts.push(new Chart(fiscalCanvas, doughnutBase(
                fiscalData.labels,
                fiscalData.values,
                fiscalData.colors ?? ['#1d4ed8', '#93c5fd', '#0f766e', '#f59e0b'],
                fiscalData.unit ?? 'count',
            )));
        }

        const paymentsData = readJson('gestor-payments-data');
        const paymentsCanvas = document.getElementById('gestor-payments-chart');
        if (paymentsCanvas && paymentsData?.labels?.length) {
            charts.push(new Chart(paymentsCanvas, doughnutBase(
                paymentsData.labels,
                paymentsData.values,
                paymentsData.colors ?? ['#db2777', '#6366f1', '#0f766e', '#1d4ed8'],
                paymentsData.unit ?? 'money',
            )));
        }

        const resizeCharts = () => charts.forEach((chart) => chart.resize());
        window.addEventListener('resize', resizeCharts);

        if (typeof ResizeObserver !== 'undefined') {
            const root = document.querySelector('.gestor-charts');
            if (root) {
                new ResizeObserver(resizeCharts).observe(root);
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
