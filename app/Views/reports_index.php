<?php
// Salesperson Reports Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user = $_SESSION['user'] ?? null;
$userName = $user['username'] ?? 'User';
?>

<div class="p-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Sales Reports</h1>
        <p class="text-gray-600 text-sm">Generate and export your sales reports</p>
    </div>
    
    <!-- Report Period Selection -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Select Report Period</h2>
        
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <button onclick="selectPeriod('today')" 
                    class="period-btn bg-gradient-to-br from-blue-50 to-blue-100 border-2 border-blue-200 rounded-lg p-4 hover:border-blue-300 transition-all duration-200 text-center" 
                    data-period="today">
                <div class="text-2xl font-bold text-blue-700 mb-1">Today</div>
                <div class="text-xs text-blue-600"><?= date('M d, Y') ?></div>
            </button>
            
            <button onclick="selectPeriod('week')" 
                    class="period-btn bg-gradient-to-br from-green-50 to-green-100 border-2 border-green-200 rounded-lg p-4 hover:border-green-300 transition-all duration-200 text-center" 
                    data-period="week">
                <div class="text-2xl font-bold text-green-700 mb-1">This Week</div>
                <div class="text-xs text-green-600">Last 7 days</div>
            </button>
            
            <button onclick="selectPeriod('month')" 
                    class="period-btn bg-gradient-to-br from-purple-50 to-purple-100 border-2 border-purple-200 rounded-lg p-4 hover:border-purple-300 transition-all duration-200 text-center" 
                    data-period="month">
                <div class="text-2xl font-bold text-purple-700 mb-1">This Month</div>
                <div class="text-xs text-purple-600"><?= date('F Y') ?></div>
            </button>
            
            <button onclick="selectPeriod('year')" 
                    class="period-btn bg-gradient-to-br from-orange-50 to-orange-100 border-2 border-orange-200 rounded-lg p-4 hover:border-orange-300 transition-all duration-200 text-center" 
                    data-period="year">
                <div class="text-2xl font-bold text-orange-700 mb-1">This Year</div>
                <div class="text-xs text-orange-600"><?= date('Y') ?></div>
            </button>
        </div>
        
        <div class="mb-4">
            <button onclick="selectPeriod('custom')" 
                    class="period-btn bg-gradient-to-br from-gray-50 to-gray-100 border-2 border-gray-200 rounded-lg px-4 py-2 hover:border-gray-300 transition-all duration-200 text-sm font-medium text-gray-700" 
                    data-period="custom">
                <i class="fas fa-calendar-alt mr-2"></i>Custom Date Range
            </button>
        </div>
        
        <!-- Custom Date Range -->
        <div id="customDateRange" class="hidden mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                    <input type="date" id="dateFrom" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input type="date" id="dateTo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-1 focus:ring-blue-500 focus:border-blue-500" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
        </div>
        
        <!-- Export Buttons -->
        <div class="flex flex-wrap gap-3">
            <button onclick="exportReport('pdf')" 
                    class="flex items-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors">
                <i class="fas fa-file-pdf mr-2"></i>
                Export as PDF
            </button>
            
            <button onclick="exportReport('xlsx')" 
                    class="flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors">
                <i class="fas fa-file-excel mr-2"></i>
                Export as Excel
            </button>
        </div>
    </div>
    
    <!-- Quick Stats Preview -->
    <div id="reportPreview" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Report Preview</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <div class="text-sm text-blue-700 mb-1">Total Sales</div>
                <div class="text-2xl font-bold text-blue-900" id="previewSales">0</div>
            </div>
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <div class="text-sm text-green-700 mb-1">Total Revenue</div>
                <div class="text-2xl font-bold text-green-900" id="previewRevenue">₵0.00</div>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <div class="text-sm text-purple-700 mb-1">Period</div>
                <div class="text-lg font-semibold text-purple-900" id="previewPeriod">Select period</div>
            </div>
            <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
                <div class="text-sm text-orange-700 mb-1">Date Range</div>
                <div class="text-xs font-medium text-orange-900" id="previewDateRange">-</div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedPeriod = 'today';
let customDateFrom = '';
let customDateTo = '';

function selectPeriod(period) {
    selectedPeriod = period;
    
    // Update UI
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('ring-2', 'ring-blue-500');
    });
    
    const btn = document.querySelector(`[data-period="${period}"]`);
    if (btn) {
        btn.classList.add('ring-2', 'ring-blue-500');
    }
    
    // Show/hide custom date range
    const customRange = document.getElementById('customDateRange');
    if (period === 'custom') {
        customRange.classList.remove('hidden');
    } else {
        customRange.classList.add('hidden');
    }
    
    // Update preview
    updatePreview();
}

function updatePreview() {
    let dateFrom = '';
    let dateTo = '<?= date('Y-m-d') ?>';
    let periodLabel = '';
    
    switch (selectedPeriod) {
        case 'today':
            dateFrom = '<?= date('Y-m-d') ?>';
            dateTo = '<?= date('Y-m-d') ?>';
            periodLabel = 'Today';
            break;
        case 'week':
            dateFrom = '<?= date('Y-m-d', strtotime('-7 days')) ?>';
            periodLabel = 'This Week';
            break;
        case 'month':
            dateFrom = '<?= date('Y-m-01') ?>';
            periodLabel = 'This Month';
            break;
        case 'year':
            dateFrom = '<?= date('Y-01-01') ?>';
            periodLabel = 'This Year';
            break;
        case 'custom':
            dateFrom = document.getElementById('dateFrom').value;
            dateTo = document.getElementById('dateTo').value;
            periodLabel = 'Custom Range';
            break;
    }
    
    document.getElementById('previewPeriod').textContent = periodLabel;
    document.getElementById('previewDateRange').textContent = dateFrom + ' to ' + dateTo;
    
    // Load preview stats
    loadPreviewStats(dateFrom, dateTo);
}

function loadPreviewStats(dateFrom, dateTo) {
    const base = (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
    
    fetch(`${base}/api/reports/preview?date_from=${dateFrom}&date_to=${dateTo}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('previewSales').textContent = data.stats.total_sales || 0;
                document.getElementById('previewRevenue').textContent = '₵' + parseFloat(data.stats.total_revenue || 0).toFixed(2);
            }
        })
        .catch(error => {
            console.error('Error loading preview:', error);
        });
}

function exportReport(format) {
    const base = (typeof BASE_URL_PATH !== 'undefined') ? BASE_URL_PATH : (window.APP_BASE_PATH || '');
    let url = `${base}/dashboard/reports/export?format=${format}&period=${selectedPeriod}`;
    
    if (selectedPeriod === 'custom') {
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        
        if (!dateFrom || !dateTo) {
            alert('Please select both from and to dates for custom range');
            return;
        }
        
        url += `&date_from=${dateFrom}&date_to=${dateTo}`;
    }
    
    // Open in new window to trigger download
    window.open(url, '_blank');
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    selectPeriod('today');
    
    // Custom date range change handlers
    document.getElementById('dateFrom')?.addEventListener('change', updatePreview);
    document.getElementById('dateTo')?.addEventListener('change', updatePreview);
});
</script>

