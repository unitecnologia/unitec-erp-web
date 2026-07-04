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

    const init = () => {
        if (typeof Chart === 'undefined') {
            return;
        }

        const charts = [];
        const salesPayload = readJson('erp-dash-sales-data');
        const cashflowData = readJson('erp-dash-cashflow-data');
        const salesCanvas = document.getElementById('erp-dash-sales-chart');
        const cashflowCanvas = document.getElementById('erp-dash-cashflow-chart');
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
                        backgroundColor: 'rgba(30, 90, 158, 0.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: (value) => `R$ ${Number(value).toLocaleString('pt-BR')}`,
                            },
                            grid: { color: 'rgba(15, 52, 96, 0.08)' },
                        },
                        x: {
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
                            backgroundColor: 'rgba(22, 163, 74, 0.75)',
                            borderRadius: 6,
                        },
                        {
                            label: 'Saídas',
                            data: cashflowData.saidas,
                            backgroundColor: 'rgba(220, 38, 38, 0.72)',
                            borderRadius: 6,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 12, boxHeight: 12 },
                        },
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: (value) => `R$ ${Number(value).toLocaleString('pt-BR')}`,
                            },
                            grid: { color: 'rgba(15, 52, 96, 0.08)' },
                        },
                        x: {
                            grid: { display: false },
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
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
