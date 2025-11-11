<?php
require_once __DIR__ . '/../Helpers/DashboardAuth.php';

$title = 'Cross-Company Benchmarks';
$GLOBALS['pageTitle'] = $title;
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Cross-Company Benchmarks</h1>
    <p class="text-gray-600 mb-6">Compare anonymized performance metrics across all companies</p>

    <!-- Date Range Filter -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input type="date" id="benchmarkDateFrom" value="<?= date('Y-m-01') ?>" class="border border-gray-300 rounded px-4 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="date" id="benchmarkDateTo" value="<?= date('Y-m-d') ?>" class="border border-gray-300 rounded px-4 py-2">
            </div>
            <button id="btnLoadBenchmarks" class="bg-blue-600 hover:bg-blue-700 text-white rounded px-6 py-2">
                <i class="fas fa-chart-bar mr-1"></i> Load Benchmarks
            </button>
        </div>
    </div>

    <!-- Sales Benchmarks -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Sales Performance</h2>
        <canvas id="salesBenchmarkChart" height="100"></canvas>
        <div id="salesBenchmarkStats" class="mt-4 grid grid-cols-3 gap-4">
            <!-- Stats will be loaded here -->
        </div>
    </div>

    <!-- Profit Benchmarks -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Profit Performance</h2>
        <canvas id="profitBenchmarkChart" height="100"></canvas>
        <div id="profitBenchmarkStats" class="mt-4">
            <!-- Stats will be loaded here -->
        </div>
    </div>

    <!-- Top Performers Table -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Top Performers</h2>
        <div id="topPerformersTable">
            <!-- Table will be loaded here -->
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const BASE = '<?= BASE_URL_PATH ?>';
    let salesBenchmarkChart = null;
    let profitBenchmarkChart = null;

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('btnLoadBenchmarks').addEventListener('click', loadBenchmarks);
        loadBenchmarks();
    });

    async function loadBenchmarks() {
        const dateFrom = document.getElementById('benchmarkDateFrom').value;
        const dateTo = document.getElementById('benchmarkDateTo').value;

        try {
            const response = await fetch(`${BASE}/api/admin/benchmarks?metric=all&date_from=${dateFrom}&date_to=${dateTo}`);
            const data = await response.json();

            if (data.success && data.benchmarks) {
                displaySalesBenchmarks(data.benchmarks.sales);
                displayProfitBenchmarks(data.benchmarks.profit);
                displayTopPerformers(data.benchmarks);
            }
        } catch (error) {
            console.error('Error loading benchmarks:', error);
        }
    }

    function displaySalesBenchmarks(salesData) {
        if (!salesData || !salesData.top_performers) return;

        const ctx = document.getElementById('salesBenchmarkChart').getContext('2d');
        
        if (salesBenchmarkChart) {
            salesBenchmarkChart.destroy();
        }

        const labels = salesData.top_performers.slice(0, 10).map(p => p.company_label);
        const revenues = salesData.top_performers.slice(0, 10).map(p => parseFloat(p.revenue));

        salesBenchmarkChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (₵)',
                    data: revenues,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₵' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Display stats
        document.getElementById('salesBenchmarkStats').innerHTML = `
            <div class="text-center">
                <div class="text-sm text-gray-600">Average</div>
                <div class="text-xl font-semibold">₵${salesData.average.toLocaleString()}</div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-600">Median</div>
                <div class="text-xl font-semibold">₵${salesData.median.toLocaleString()}</div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-600">Total Companies</div>
                <div class="text-xl font-semibold">${salesData.total_companies}</div>
            </div>
        `;
    }

    function displayProfitBenchmarks(profitData) {
        if (!profitData || !profitData.top_performers) return;

        const ctx = document.getElementById('profitBenchmarkChart').getContext('2d');
        
        if (profitBenchmarkChart) {
            profitBenchmarkChart.destroy();
        }

        const labels = profitData.top_performers.slice(0, 10).map(p => p.company_label);
        const profits = profitData.top_performers.slice(0, 10).map(p => parseFloat(p.profit));

        profitBenchmarkChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Profit (₵)',
                    data: profits,
                    backgroundColor: 'rgba(34, 197, 94, 0.5)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₵' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        document.getElementById('profitBenchmarkStats').innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center bg-gray-50 rounded p-4">
                    <div class="text-sm text-gray-600">Average Profit</div>
                    <div class="text-xl font-semibold">₵${profitData.average.toLocaleString()}</div>
                </div>
                <div class="text-center bg-gray-50 rounded p-4">
                    <div class="text-sm text-gray-600">Average Margin</div>
                    <div class="text-xl font-semibold">${profitData.average_margin.toFixed(2)}%</div>
                </div>
            </div>
        `;
    }

    function displayTopPerformers(benchmarks) {
        const panel = document.getElementById('topPerformersTable');
        
        let html = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">';
        html += '<thead class="bg-gray-50"><tr>';
        html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sales Revenue</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Profit</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Repairs</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Swaps</th>';
        html += '</tr></thead><tbody class="bg-white divide-y divide-gray-200">';

        // Merge top performers from different metrics
        const topSales = benchmarks.sales?.top_performers?.slice(0, 5) || [];
        
        topSales.forEach((company, index) => {
            html += `<tr class="${index < 3 ? 'bg-yellow-50' : ''}">`;
            html += `<td class="px-4 py-3 text-sm font-semibold">${company.company_label}</td>`;
            html += `<td class="px-4 py-3 text-sm">₵${parseFloat(company.revenue).toLocaleString()}</td>`;
            html += `<td class="px-4 py-3 text-sm">-</td>`;
            html += `<td class="px-4 py-3 text-sm">-</td>`;
            html += `<td class="px-4 py-3 text-sm">-</td>`;
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        panel.innerHTML = html;
    }
</script>

<?php
// Render layout at the end
require __DIR__ . '/simple_layout.php';
?>

