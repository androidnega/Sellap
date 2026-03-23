<?php
if (!defined('BASE_URL_PATH')) {
    http_response_code(500);
    exit('Configuration error');
}
$base = rtrim(BASE_URL_PATH, '/');
?><!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>SellApp Scanner</title>
    <link rel="manifest" href="<?php echo htmlspecialchars($base . '/manifest.json', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($base . '/assets/images/favicon.svg', ENT_QUOTES, 'UTF-8'); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full min-h-[100dvh] flex flex-col">
    <header class="shrink-0 flex items-center justify-between px-4 py-3 border-b border-slate-800 bg-slate-950/90 backdrop-blur">
        <div>
            <h1 class="text-lg font-semibold">Scanner</h1>
            <p id="roleLabel" class="text-xs text-slate-500"></p>
        </div>
        <button type="button" id="logoutBtn" class="text-sm text-slate-400 px-2 py-1 rounded-lg hover:bg-slate-800">Log out</button>
    </header>

    <main class="flex-1 flex flex-col min-h-0">
        <div class="relative bg-black aspect-[4/3] max-h-[45vh] w-full">
            <video id="video" class="w-full h-full object-cover" playsinline muted></video>
            <div class="absolute inset-x-0 bottom-0 p-3 bg-gradient-to-t from-black/80 to-transparent">
                <p id="scanHint" class="text-xs text-slate-300 text-center">Point the camera at a barcode</p>
            </div>
        </div>

        <!-- Sales -->
        <div id="salesPanel" class="hidden flex-1 flex flex-col p-4 gap-3 overflow-hidden">
            <div id="lastProduct" class="rounded-xl border border-slate-800 bg-slate-900/80 p-3 text-sm min-h-[4rem]">
                <p class="text-slate-500 text-xs mb-1">Last scan</p>
                <p id="lastProductText" class="text-slate-400">—</p>
            </div>
            <div class="flex-1 overflow-y-auto rounded-xl border border-slate-800 bg-slate-900/50">
                <div class="flex items-center justify-between px-3 py-2 border-b border-slate-800">
                    <span class="text-sm font-medium">Cart</span>
                    <span id="cartCount" class="text-xs text-slate-500">0 items</span>
                </div>
                <ul id="cartList" class="divide-y divide-slate-800 text-sm"></ul>
                <div class="px-3 py-2 flex justify-between text-sm border-t border-slate-800">
                    <span class="text-slate-400">Total</span>
                    <span id="cartTotal" class="font-semibold tabular-nums">0.00</span>
                </div>
            </div>
            <button type="button" id="checkoutBtn"
                class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3.5 text-base disabled:opacity-40 disabled:pointer-events-none">
                Checkout
            </button>
        </div>

        <!-- Stock -->
        <div id="stockPanel" class="hidden flex-1 flex flex-col p-4 gap-3 overflow-hidden">
            <div id="stockProduct" class="rounded-xl border border-slate-800 bg-slate-900/80 p-3 text-sm min-h-[5rem]">
                <p class="text-slate-500 text-xs mb-1">Product</p>
                <p id="stockProductText" class="text-slate-300">Scan a barcode to select a product</p>
            </div>
            <label class="block text-xs text-slate-400">Quantity to add</label>
            <input type="number" id="stockQty" min="1" value="1"
                class="w-full rounded-xl bg-slate-900 border border-slate-700 px-4 py-3 text-base">
            <button type="button" id="addStockBtn"
                class="w-full rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold py-3.5 text-base disabled:opacity-40 disabled:pointer-events-none">
                Add to inventory
            </button>
        </div>
    </main>

    <div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 px-4 py-2 rounded-lg bg-slate-800 text-sm shadow-lg opacity-0 pointer-events-none transition-opacity z-50 max-w-[90vw] text-center"></div>

    <script type="module">
    const BASE = <?php echo json_encode($base, JSON_UNESCAPED_SLASHES); ?>;
    const api = (p) => (BASE ? BASE + '/' : '') + p.replace(/^\//, '');
    const CART_KEY = 'sellapp_pwa_cart';
    const PRODUCTS_CACHE = 'sellapp_pwa_products_cache_v1';

    const token = localStorage.getItem('sellapp_token');
    let user = null;
    try { user = JSON.parse(localStorage.getItem('sellapp_user') || 'null'); } catch (e) {}
    if (!token || !user) {
        window.location.href = api('/pwa-login');
        throw new Error('redirect');
    }

    const isStockMode = ['manager', 'admin', 'system_admin'].includes(user.role);

    document.getElementById('roleLabel').textContent =
        isStockMode ? 'Restock mode' : 'Point of sale';

    function toast(msg, ok = true) {
        const el = document.getElementById('toast');
        el.textContent = msg;
        el.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 px-4 py-2 rounded-lg text-sm shadow-lg z-50 max-w-[90vw] text-center ' +
            (ok ? 'bg-emerald-900 text-emerald-100' : 'bg-red-900 text-red-100');
        el.style.opacity = '1';
        clearTimeout(toast._t);
        toast._t = setTimeout(() => { el.style.opacity = '0'; }, 2600);
    }

    function beep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.connect(g);
            g.connect(ctx.destination);
            o.frequency.value = 880;
            g.gain.value = 0.08;
            o.start();
            o.stop(ctx.currentTime + 0.06);
        } catch (e) {}
    }

    async function fetchAuth(url, opts = {}) {
        const headers = Object.assign({
            'Accept': 'application/json',
            'Authorization': 'Bearer ' + token
        }, opts.headers || {});
        if (opts.body && typeof opts.body === 'string' && !headers['Content-Type']) {
            headers['Content-Type'] = 'application/json';
        }
        return fetch(url, Object.assign({}, opts, { headers }));
    }

    const valid = await fetchAuth(api('/api/auth/validate'));
    const vj = await valid.json();
    if (!valid.ok || !vj.success) {
        localStorage.removeItem('sellapp_token');
        localStorage.removeItem('sellapp_user');
        window.location.href = api('/pwa-login');
    } else {

    // --- Sales cart ---
    function loadCart() {
        try {
            const raw = localStorage.getItem(CART_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) { return []; }
    }
    function saveCart(items) {
        localStorage.setItem(CART_KEY, JSON.stringify(items));
        renderCart();
    }
    function renderCart() {
        const items = loadCart();
        const ul = document.getElementById('cartList');
        ul.innerHTML = '';
        let total = 0;
        items.forEach((line, idx) => {
            total += line.total_price;
            const li = document.createElement('li');
            li.className = 'px-3 py-2 flex items-center justify-between gap-2';
            li.innerHTML = `<div class="flex-1 min-w-0"><span class="truncate block">${escapeHtml(line.name)} × ${line.quantity}</span></div>` +
                `<span class="tabular-nums shrink-0">${line.total_price.toFixed(2)}</span>` +
                `<button type="button" data-i="${idx}" class="text-red-400 text-xs px-2 py-1 rounded hover:bg-slate-800 remove-line">✕</button>`;
            ul.appendChild(li);
        });
        ul.querySelectorAll('.remove-line').forEach((b) => {
            b.addEventListener('click', () => {
                const i = +b.getAttribute('data-i');
                const next = loadCart();
                next.splice(i, 1);
                saveCart(next);
            });
        });
        document.getElementById('cartCount').textContent = items.length + ' line(s)';
        document.getElementById('cartTotal').textContent = total.toFixed(2);
        document.getElementById('checkoutBtn').disabled = items.length === 0;
    }

    async function lookupProduct(code) {
        const q = encodeURIComponent(code);
        const res = await fetchAuth(api('/api/products/find-by-barcode?code=' + q));
        const j = await res.json();
        if (!res.ok || !j.success || !j.product) {
            throw new Error(j.error || 'Product not found');
        }
        return j.product;
    }

    let lastScan = '';
    let lastScanAt = 0;
    function dedupe(code) {
        const now = Date.now();
        if (code === lastScan && now - lastScanAt < 1200) return false;
        lastScan = code;
        lastScanAt = now;
        return true;
    }

    // --- ZXing (@zxing/browser) ---
    let reader;
    try {
        const z = await import('https://cdn.jsdelivr.net/npm/@zxing/browser@0.1.5/+esm');
        reader = new z.BrowserMultiFormatReader();
    } catch (e) {
        document.getElementById('scanHint').textContent = 'Could not load scanner library. Check your connection.';
    }

    let selectedProduct = null;

    async function onBarcode(text) {
        const code = (text || '').trim();
        if (!code || !dedupe(code)) return;
        try {
            const p = await lookupProduct(code);
            beep();
            if (isStockMode) {
                selectedProduct = p;
                document.getElementById('stockProductText').innerHTML =
                    `<strong>${escapeHtml(p.name)}</strong><br><span class="text-slate-500">Stock: ${p.quantity} · Price: ${Number(p.price).toFixed(2)}</span>`;
                toast('Product loaded — set quantity', true);
            } else {
                document.getElementById('lastProductText').innerHTML =
                    `<strong>${escapeHtml(p.name)}</strong><br><span class="text-slate-500">${p.quantity} in stock · ${Number(p.price).toFixed(2)} each</span>`;
                const cart = loadCart();
                const ix = cart.findIndex((c) => c.product_id === p.id);
                const unit = Number(p.price);
                if (ix >= 0) {
                    cart[ix].quantity += 1;
                    cart[ix].total_price = cart[ix].quantity * unit;
                    cart[ix].unit_price = unit;
                } else {
                    cart.push({
                        product_id: p.id,
                        name: p.name,
                        quantity: 1,
                        unit_price: unit,
                        total_price: unit,
                        category_name: p.category_name || 'General'
                    });
                }
                saveCart(cart);
                toast('Added to cart', true);
            }
        } catch (e) {
            toast(e.message || 'Scan failed', false);
        }
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    if (reader) {
        reader.decodeFromVideoDevice(null, 'video', (result, err) => {
            if (result) {
                onBarcode(result.getText());
            }
        });
    }

    // Panels
    if (isStockMode) {
        document.getElementById('stockPanel').classList.remove('hidden');
    } else {
        document.getElementById('salesPanel').classList.remove('hidden');
        renderCart();
    }

    document.getElementById('logoutBtn').addEventListener('click', () => {
        localStorage.removeItem('sellapp_token');
        localStorage.removeItem('sellapp_user');
        localStorage.removeItem(CART_KEY);
        window.location.href = api('/pwa-login');
    });

    document.getElementById('checkoutBtn')?.addEventListener('click', async () => {
        const cart = loadCart();
        if (!cart.length) return;
        const items = cart.map((c) => ({
            product_id: c.product_id,
            name: c.name,
            quantity: c.quantity,
            unit_price: c.unit_price,
            total_price: c.total_price,
            category_name: c.category_name || 'General'
        }));
        try {
            const res = await fetchAuth(api('/api/pos'), {
                method: 'POST',
                body: JSON.stringify({
                    items,
                    payment_method: 'cash',
                    discount: 0,
                    tax: 0
                })
            });
            const j = await res.json();
            if (!res.ok || !j.success) {
                throw new Error(j.message || j.error || 'Checkout failed');
            }
            localStorage.removeItem(CART_KEY);
            renderCart();
            toast('Sale recorded', true);
            document.getElementById('lastProductText').textContent = '—';
        } catch (e) {
            toast(e.message || 'Checkout failed', false);
        }
    });

    document.getElementById('addStockBtn')?.addEventListener('click', async () => {
        if (!selectedProduct) {
            toast('Scan a product first', false);
            return;
        }
        const qty = Math.max(1, parseInt(document.getElementById('stockQty').value, 10) || 1);
        try {
            const res = await fetchAuth(api('/api/inventory/add'), {
                method: 'POST',
                body: JSON.stringify({
                    product_id: selectedProduct.id,
                    quantity_to_add: qty,
                    notes: 'PWA scanner restock'
                })
            });
            const j = await res.json();
            if (!res.ok || !j.success) {
                throw new Error(j.error || 'Update failed');
            }
            selectedProduct = j.product || selectedProduct;
            if (j.product) {
                document.getElementById('stockProductText').innerHTML =
                    `<strong>${escapeHtml(j.product.name)}</strong><br><span class="text-slate-500">Stock: ${j.product.quantity} · Price: ${Number(j.product.price).toFixed(2)}</span>`;
            }
            beep();
            toast('Stock updated', true);
        } catch (e) {
            toast(e.message || 'Failed', false);
        }
    });

    // Optional: warm product cache for offline (best-effort)
    fetchAuth(api('/api/pos/products')).then((r) => r.json()).then((j) => {
        if (j && j.success && j.data) {
            try { localStorage.setItem(PRODUCTS_CACHE, JSON.stringify(j.data)); } catch (e) {}
        }
    }).catch(() => {});

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(api('/sw.js')).catch(() => {});
    }
    }
    </script>
</body>
</html>
