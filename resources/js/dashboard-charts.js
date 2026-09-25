import {
    Chart, ArcElement, BarElement, LineElement, PointElement, CategoryScale, LinearScale,
    DoughnutController, BarController, LineController, Tooltip, Legend,
} from 'chart.js';

Chart.register(
    ArcElement, BarElement, LineElement, PointElement, CategoryScale, LinearScale,
    DoughnutController, BarController, LineController, Tooltip, Legend
);

/**
 * Small, self-contained KPI-card charts on the Dashboard. Each <canvas
 * data-chart> element carries its own type/labels/values/colors as data
 * attributes (set by the x-dashboard-stat/x-dashboard-donut Blade
 * components), so this stays a single generic initializer rather than one
 * script per chart.
 */
function initDashboardCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        if (canvas.dataset.chartInitialized) {
            return;
        }

        const type = canvas.dataset.chartType || 'doughnut';
        const labels = JSON.parse(canvas.dataset.chartLabels || '[]');
        const values = JSON.parse(canvas.dataset.chartValues || '[]');
        const colors = JSON.parse(canvas.dataset.chartColors || '[]');
        const showLegend = canvas.dataset.chartLegend === 'true';

        if (type === 'combo') {
            const secondaryValues = JSON.parse(canvas.dataset.chartSecondaryValues || '[]');
            const barLabel = canvas.dataset.chartBarLabel || 'Bars';
            const lineLabel = canvas.dataset.chartLineLabel || 'Line';

            new Chart(canvas, {
                data: {
                    labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: barLabel,
                            data: values,
                            backgroundColor: '#01ADEF',
                            borderRadius: 3,
                            yAxisID: 'y',
                        },
                        {
                            type: 'line',
                            label: lineLabel,
                            data: secondaryValues,
                            borderColor: '#2F7532',
                            backgroundColor: '#2F7532',
                            pointRadius: 3,
                            tension: 0.35,
                            yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: true, position: 'top', labels: { boxWidth: 8, font: { size: 10 }, padding: 8 } },
                        tooltip: { enabled: true },
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                        y: { position: 'left', beginAtZero: true, ticks: { font: { size: 9 } }, grid: { color: '#f1f1ef' } },
                        y1: { position: 'right', beginAtZero: true, ticks: { font: { size: 9 } }, grid: { display: false } },
                    },
                },
            });

            canvas.dataset.chartInitialized = 'true';

            return;
        }

        if (type === 'sparkline') {
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        borderColor: colors[0] || '#01ADEF',
                        backgroundColor: 'transparent',
                        borderWidth: 1.5,
                        pointRadius: 0,
                        tension: 0.35,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: { x: { display: false }, y: { display: false } },
                    elements: { line: { borderJoinStyle: 'round' } },
                },
            });

            canvas.dataset.chartInitialized = 'true';

            return;
        }

        const isVerticalBar = type === 'bar-vertical';
        const chartType = isVerticalBar ? 'bar' : type;

        new Chart(canvas, {
            type: chartType,
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: type === 'doughnut' ? 2 : 0,
                    borderColor: '#ffffff',
                    borderRadius: chartType === 'bar' ? 4 : 0,
                    barThickness: type === 'bar' ? 14 : undefined,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: type === 'doughnut' ? '65%' : undefined,
                indexAxis: type === 'bar' ? 'y' : 'x',
                layout: {
                    padding: type === 'bar' ? { left: 4, right: 8 } : 0,
                },
                plugins: {
                    legend: {
                        display: showLegend,
                        position: 'bottom',
                        labels: { boxWidth: 8, font: { size: 10 }, padding: 6 },
                    },
                    tooltip: { enabled: true },
                },
                scales: chartType === 'bar' ? {
                    x: { display: !isVerticalBar, beginAtZero: true, ticks: { font: { size: 9 } }, grid: { color: '#f1f1ef' } },
                    y: {
                        display: true,
                        beginAtZero: isVerticalBar,
                        ticks: { font: { size: 9 }, autoSkip: false },
                        grid: { display: !isVerticalBar ? false : true, color: '#f1f1ef' },
                    },
                } : undefined,
            },
        });

        canvas.dataset.chartInitialized = 'true';
    });
}

document.addEventListener('DOMContentLoaded', initDashboardCharts);
