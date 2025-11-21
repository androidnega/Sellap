// Simple JavaScript for SellApp - Essential functionality only

// Base path helper
const getBasePath = () => {
    if (typeof BASE !== 'undefined') return BASE;
    if (typeof window.BASE !== 'undefined') return window.BASE;
    if (typeof window.APP_BASE_PATH !== 'undefined') return window.APP_BASE_PATH;
    return '';
};

const basePath = getBasePath();
const apiBase = `${basePath}/api`;

// Helper functions
function getToken() {
    return localStorage.getItem('sellapp_token') || localStorage.getItem('token');
}

function checkAuth() {
    const token = getToken();
    if (!token && window.location.pathname.includes('dashboard')) {
        // Only redirect if we're not already on the login page
        if (!window.location.pathname.includes('/login') && !window.location.pathname.endsWith('/')) {
            window.location.href = `${basePath}/`;
        }
    }
    return token;
}

async function apiCall(endpoint, options = {}) {
    const token = getToken();
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };
    
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    
    const response = await fetch(`${apiBase}${endpoint}`, {
        ...options,
        headers,
        credentials: 'same-origin' // Include session cookies as fallback
    });
    
    // Only redirect on 401 if we're not already redirecting to prevent loops
    if (response.status === 401) {
        // Check if we're already on the login page to prevent redirect loops
        if (!window.location.pathname.endsWith('/') && !window.location.pathname.includes('/login')) {
            localStorage.removeItem('sellapp_token');
            localStorage.removeItem('token');
            localStorage.removeItem('sellapp_user');
            // Use replace instead of href to prevent back button issues
            window.location.replace(`${basePath}/`);
        }
        throw new Error('Unauthorized');
    }
    
    return response;
}

// Login form handling - wait for DOM to be ready
function setupLoginForm() {
    console.log('Setting up login form handler...');
    const loginForm = document.getElementById('loginForm');
    console.log('Login form element:', loginForm);

    if (loginForm) {
        console.log('Login form found, attaching event listener...');
        
        // Main login handler
        async function handleLogin(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const errorMessage = document.getElementById('errorMessage');
            const loginBtn = document.getElementById('loginBtn');
            
            // Validate inputs
            if (!username || !password) {
                errorMessage.textContent = 'Please enter both username and password.';
                errorMessage.classList.remove('hidden');
                return;
            }
            
            loginBtn.disabled = true;
            loginBtn.textContent = 'Logging in...';
            errorMessage.classList.add('hidden');

            try {
                const loginUrl = `${apiBase}/auth/login`;
                console.log('Attempting login to:', loginUrl);
                console.log('Base path:', basePath);
                console.log('API base:', apiBase);
                console.log('Full URL:', window.location.origin + loginUrl);
                
                const res = await fetch(loginUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ username, password }),
                    credentials: 'same-origin' // Include cookies
                });
                
                console.log('Login response status:', res.status);
                console.log('Login response headers:', Object.fromEntries(res.headers.entries()));
                
                // Check if response is JSON
                const contentType = res.headers.get('content-type');
                let data;
                
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await res.text();
                    console.error('Non-JSON response:', text);
                    console.error('Response status:', res.status);
                    console.error('Response URL:', res.url);
                    
                    // Try to parse as JSON anyway (sometimes servers don't set content-type correctly)
                    try {
                        data = JSON.parse(text);
                    } catch (parseErr) {
                        throw new Error(`Server returned invalid response (${res.status}). Response: ${text.substring(0, 200)}`);
                    }
                } else {
                    data = await res.json();
                }
                
                console.log('Login response data:', data);

                if (res.ok && data.success && data.data && data.data.token) {
                    localStorage.setItem('sellapp_token', data.data.token);
                    localStorage.setItem('token', data.data.token);
                    localStorage.setItem('sellapp_user', JSON.stringify(data.data.user));
                    
                    // Get redirect URL from query params or default to dashboard
                    const urlParams = new URLSearchParams(window.location.search);
                    const redirectUrl = urlParams.get('redirect') || data.redirect || '/dashboard';
                    
                    console.log('Login successful, redirecting to:', `${basePath}${redirectUrl}`);
                    window.location.href = `${basePath}${redirectUrl}`;
                } else {
                    const errorMsg = data.error || data.message || 'Login failed. Please check your credentials.';
                    console.error('Login failed:', errorMsg);
                    console.error('Full response:', data);
                    errorMessage.textContent = errorMsg;
                    errorMessage.classList.remove('hidden');
                    loginBtn.disabled = false;
                    loginBtn.textContent = 'Sign In';
                }
            } catch (err) {
                console.error('Login error:', err);
                console.error('Error details:', {
                    message: err.message,
                    stack: err.stack,
                    name: err.name
                });
                
                let errorMsg = err.message || 'Server error. Please check your connection and try again.';
                
                // Provide more helpful error messages
                if (err.message.includes('Failed to fetch') || err.message.includes('NetworkError')) {
                    errorMsg = 'Cannot connect to server. Please check your internet connection and try again.';
                } else if (err.message.includes('404')) {
                    errorMsg = 'Login endpoint not found. Please contact support.';
                } else if (err.message.includes('500')) {
                    errorMsg = 'Server error. Please try again later or contact support.';
                }
                
                errorMessage.textContent = errorMsg;
                errorMessage.classList.remove('hidden');
                loginBtn.disabled = false;
                loginBtn.textContent = 'Sign In';
            }
        }
        
        // Attach handler to form submit
        loginForm.addEventListener('submit', handleLogin);
        
        // Also handle button click as backup (in case form submit doesn't fire)
        const loginBtn = document.getElementById('loginBtn');
        if (loginBtn) {
            loginBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                handleLogin(e);
            });
        }
    } else {
        console.error('Login form not found on page!');
        console.log('Available elements:', {
            loginForm: document.getElementById('loginForm'),
            username: document.getElementById('username'),
            password: document.getElementById('password'),
            loginBtn: document.getElementById('loginBtn')
        });
        
        // Show error to user if form not found
        setTimeout(() => {
            const errorDiv = document.getElementById('errorMessage');
            if (errorDiv) {
                errorDiv.textContent = 'Login form initialization error. Please refresh the page.';
                errorDiv.classList.remove('hidden');
            }
        }, 1000);
    }
}

// Setup login form when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupLoginForm);
} else {
    // DOM is already ready
    setupLoginForm();
}

// Dashboard stats loading
async function loadDashboardStats() {
    try {
        const response = await apiCall('/dashboard/stats');
        
        // Check if response is ok before parsing JSON
        if (!response.ok) {
            // If 401, let apiCall handle the redirect
            if (response.status === 401) {
                return; // apiCall will handle redirect
            }
            // For other errors, just log and return
            console.error('Failed to load dashboard stats:', response.status, response.statusText);
            return;
        }
        
        const data = await response.json();
        
        // Update elements safely
        const elements = [
            { id: 'total-revenue', value: '₵' + (data.total_revenue || 0).toFixed(2) },
            { id: 'gross-profit', value: '₵' + (data.gross_profit || 0).toFixed(2) },
            { id: 'profit-margin', value: (data.profit_margin || 0) + '% margin' },
            { id: 'inventory-value', value: '₵' + (data.inventory_value || 0).toFixed(2) },
            { id: 'total-products', value: (data.total_products || 0) + ' products' },
            { id: 'total-sales', value: data.total_sales || 0 },
            { id: 'average-sale', value: '₵' + (data.average_sale || 0).toFixed(2) + ' avg' },
            { id: 'today-revenue', value: '₵' + (data.today_revenue || 0).toFixed(2) },
            { id: 'today-sales', value: data.today_sales || 0 },
            { id: 'month-revenue', value: '₵' + (data.month_revenue || 0).toFixed(2) },
            { id: 'out-of-stock', value: data.out_of_stock_products || 0 },
            { id: 'in-stock', value: data.in_stock_products || 0 },
            { id: 'total-repairs', value: data.total_repairs || 0 },
            { id: 'repairs-revenue', value: '₵' + (data.repairs_revenue || 0).toFixed(2) + ' revenue' },
            { id: 'total-customers', value: data.total_customers || 0 },
            { id: 'team-members', value: (data.total_users || 0) + ' team members' }
        ];
        
        elements.forEach(element => {
            const el = document.getElementById(element.id);
            if (el && typeof el.textContent !== 'undefined') {
                el.textContent = element.value;
            }
        });
        
        // Update top products if container exists
        const topProductsContainer = document.getElementById('top-products-container');
        if (topProductsContainer) {
            updateTopProducts(data.top_products || []);
        }
        
    } catch (err) {
        console.error('Error loading stats:', err);
    }
}

function updateTopProducts(products) {
    const container = document.getElementById('top-products-container');
    if (!container) return;
    
    if (!products || products.length === 0) {
        container.innerHTML = '<div class="text-center text-gray-500 py-4">No sales data available</div>';
        return;
    }
    
    const productsHtml = products.map((product, index) => `
        <div class="flex items-center justify-between p-3 border rounded-lg mb-2">
            <div class="flex items-center">
                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-sm font-bold mr-3">
                    ${index + 1}
                </div>
                <div>
                    <h4 class="font-semibold text-gray-800">${product.name}</h4>
                    <p class="text-sm text-gray-600">${product.total_sold} units sold</p>
                </div>
            </div>
            <div class="text-right">
                <p class="font-bold text-green-600">₵${parseFloat(product.total_revenue || 0).toFixed(2)}</p>
                <p class="text-sm text-gray-500">revenue</p>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = productsHtml;
}

// Load stats when dashboard loads
document.addEventListener('DOMContentLoaded', function() {
    // Only check auth if we're on the main dashboard page
    // Don't check on sub-pages like /dashboard/companies, /dashboard/analytics, etc.
    // as those are already protected by server-side middleware
    const pathname = window.location.pathname;
    const basePath = getBasePath();
    
    // Remove base path from pathname for comparison
    let cleanPath = pathname;
    if (basePath && pathname.startsWith(basePath)) {
        cleanPath = pathname.substring(basePath.length);
    }
    
    // Normalize path - remove trailing slash
    cleanPath = cleanPath.replace(/\/$/, '') || '/';
    
    // Check if this is exactly the main dashboard page (no sub-paths)
    // Should be exactly '/dashboard' (after removing base path and trailing slash)
    const isMainDashboard = cleanPath === '/dashboard';
    
    if (isMainDashboard) {
        // Only check auth on main dashboard, let server-side handle other pages
        const token = checkAuth();
        
        // Only load stats if we have a token to prevent unnecessary API calls
        if (token) {
            // Load stats with error handling to prevent redirect loops
            loadDashboardStats().catch(err => {
                console.error('Error loading dashboard stats:', err);
                // Don't redirect on error - let server-side handle authentication
            });
        }
    }
    // For other dashboard sub-pages, don't call checkAuth()
    // Server-side middleware handles authentication and prevents unwanted redirects
});

