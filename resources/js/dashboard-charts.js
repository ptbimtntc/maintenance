import { Chart, ArcElement, BarElement, CategoryScale, LinearScale, DoughnutController, BarController, Tooltip, Legend } from 'chart.js';

Chart.register(ArcElement, BarElement, CategoryScale, LinearScale, DoughnutController, BarController, Tooltip, Legend);

/**
 * Small, self-contained KPI-card charts on the Dashboard. Each <canvas
 * data-chart> element carries its own type/labels/values/colors as data
 * attributes (set by the x-dashboard-stat Blade component), so this stays
 * a single generic initializer rather than one script per chart.
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

        new Chart(canvas, {
            type,
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: type === 'doughnut' ? 2 : 0,
                    borderColor: '#ffffff',
                    borderRadius: type === 'bar' ? 4 : 0,
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
                scales: type === 'bar' ? {
                    x: { display: false, beginAtZero: true },
                    y: { display: true, ticks: { font: { size: 9 }, autoSkip: false }, grid: { display: false } },
                } : undefined,
            },
        });

        canvas.dataset.chartInitialized = 'true';
    });
}

document.addEventListener('DOMContentLoaded', initDashboardCharts);
