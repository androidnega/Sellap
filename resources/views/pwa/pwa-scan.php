<?php
if (!defined('BASE_URL_PATH')) {
    http_response_code(500);
    exit('Configuration error');
}
$base = rtrim(BASE_URL_PATH, '/');
$assetBase = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
?><!DOCTYPE html>
<html lang="en" class="h-full bg-white text-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>SellApp Scanner</title>
    <link rel="manifest" href="<?php echo htmlspecialchars($assetBase . '/pwa/manifest.webmanifest', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($assetBase . '/assets/images/favicon.svg', ENT_QUOTES, 'UTF-8'); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full min-h-[100dvh] flex flex-col bg-white">
    <header class="shrink-0 flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-white">
        <div>
            <h1 class="text-lg font-semibold text-gray-900">Scanner</h1>
            <p id="roleLabel" class="text-xs text-gray-500"></p>
        </div>
        <button type="button" id="logoutBtn" class="text-sm text-gray-700 px-2 py-1 rounded-lg hover:bg-gray-100">Log out</button>
    </header>

    <main class="flex-1 flex flex-col min-h-0 bg-white">
        <div class="relative bg-black w-full h-40 max-h-[42vh] sm:h-48 shrink-0 overflow-hidden border-b border-gray-200">
            <video id="video" class="w-full h-full object-cover" playsinline muted autoplay></video>
            <div class="absolute top-1 left-1 right-1 flex items-center gap-2 z-10">
                <label class="sr-only" for="cameraSelect">Camera</label>
                <select id="cameraSelect" class="text-xs flex-1 min-w-0 rounded-md border border-gray-300 bg-white/95 px-2 py-1.5 text-gray-800 shadow-sm max-w-[85%]"></select>
            </div>
            <div class="absolute inset-x-0 bottom-0 p-2 bg-white/95 backdrop-blur-sm border-t border-gray-100 space-y-2">
                <p id="scanHint" class="text-xs text-gray-700 text-center">Point the camera at a barcode — use the menu above if the image is blurry</p>
                <div class="flex gap-2 items-center max-w-md mx-auto">
                    <input type="text" id="manualBarcode" inputmode="numeric" autocomplete="off" placeholder="Or type SKU / barcode"
                        class="flex-1 min-w-0 text-xs rounded-md border border-gray-300 bg-white px-2 py-1.5 text-gray-900">
                    <button type="button" id="manualLookupBtn"
                        class="shrink-0 text-xs rounded-md bg-gray-800 text-white px-3 py-1.5 font-medium">Look up</button>
                </div>
            </div>
        </div>

        <!-- Sales -->
        <div id="salesPanel" class="hidden flex-1 flex flex-col p-4 gap-3 overflow-hidden bg-white">
            <div id="lastProduct" class="rounded-xl border border-gray-200 bg-white p-3 text-sm min-h-[4rem]">
                <p class="text-gray-500 text-xs mb-1">Last scan</p>
                <p id="lastProductText" class="text-gray-800">—</p>
            </div>
            <div class="flex-1 overflow-y-auto rounded-xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200">
                    <span class="text-sm font-medium text-gray-900">Cart</span>
                    <span id="cartCount" class="text-xs text-gray-500">0 items</span>
                </div>
                <ul id="cartList" class="divide-y divide-gray-100 text-sm text-gray-800"></ul>
                <div class="px-3 py-2 flex justify-between text-sm border-t border-gray-200">
                    <span class="text-gray-600">Total</span>
                    <span id="cartTotal" class="font-semibold tabular-nums text-gray-900">0.00</span>
                </div>
            </div>
            <button type="button" id="checkoutBtn"
                class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3.5 text-base disabled:opacity-40 disabled:pointer-events-none">
                Checkout
            </button>
        </div>

        <!-- Stock -->
        <div id="stockPanel" class="hidden flex-1 flex flex-col p-4 gap-3 overflow-hidden bg-white">
            <div id="stockProduct" class="rounded-xl border border-gray-200 bg-white p-3 text-sm min-h-[5rem]">
                <p class="text-gray-500 text-xs mb-1">Product</p>
                <p id="stockProductText" class="text-gray-800">Scan a barcode to select a product</p>
            </div>
            <label class="block text-xs text-gray-600">Quantity to add</label>
            <input type="number" id="stockQty" min="1" value="1"
                class="w-full rounded-xl bg-white border border-gray-200 px-4 py-3 text-base text-gray-900 focus:ring-2 focus:ring-amber-500 focus:border-amber-400 outline-none">
            <button type="button" id="addStockBtn"
                class="w-full rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold py-3.5 text-base disabled:opacity-40 disabled:pointer-events-none">
                Add to inventory
            </button>
        </div>
    </main>

    <div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 px-4 py-2 rounded-lg text-sm opacity-0 pointer-events-none transition-opacity z-50 max-w-[90vw] text-center border border-gray-200 bg-white text-gray-900 shadow-lg"></div>

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
        el.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 px-4 py-2 rounded-lg text-sm z-50 max-w-[90vw] text-center border shadow-lg bg-white text-gray-900 ' +
            (ok ? 'border-emerald-300' : 'border-red-300');
        el.style.opacity = '1';
        clearTimeout(toast._t);
        toast._t = setTimeout(() => { el.style.opacity = '0'; }, 2600);
    }

    /** Shared context so mobile can play after unlock(); short high = scan read, lower = success */
    let audioCtx = null;
    function unlockAudio() {
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
        } catch (e) {}
    }
    ['click', 'touchstart', 'pointerdown'].forEach((ev) => {
        document.addEventListener(ev, unlockAudio, { passive: true, once: false });
    });

    function beepTone(freq, durSec, gain) {
        try {
            unlockAudio();
            const ctx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            if (!audioCtx) audioCtx = ctx;
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.connect(g);
            g.connect(ctx.destination);
            o.frequency.value = freq;
            g.gain.value = gain;
            o.start();
            o.stop(ctx.currentTime + durSec);
        } catch (e) {}
    }
    function beepScan() {
        beepTone(880, 0.06, 0.08);
    }
    function beep() {
        beepTone(660, 0.08, 0.09);
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

    async function readJsonSafe(res) {
        const text = await res.text();
        try {
            return text ? JSON.parse(text) : {};
        } catch (e) {
            const snippet = text.slice(0, 120).replace(/\s+/g, ' ');
            throw new Error(res.ok ? 'Invalid JSON from server' : ('Server error: ' + snippet));
        }
    }

    const valid = await fetchAuth(api('/api/auth/validate'));
    const vj = await readJsonSafe(valid);
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
                `<button type="button" data-i="${idx}" class="text-red-600 text-xs px-2 py-1 rounded hover:bg-gray-50 remove-line">✕</button>`;
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
        const j = await readJsonSafe(res);
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

    // --- ZXing: HD stream, rear camera default, multi-camera switch ---
    let reader = null;
    const videoEl = document.getElementById('video');
    const cameraSelect = document.getElementById('cameraSelect');
    const scanHintEl = document.getElementById('scanHint');

    function pickDefaultDeviceIndex(devices) {
        if (!devices || !devices.length) return 0;
        const labels = devices.map((d, i) => ({ i, label: (d.label || '').toLowerCase() }));
        const hit = labels.find(({ label }) =>
            /back|rear|environment|wide|ultra|tele|photo|main|world/.test(label));
        if (hit) return hit.i;
        return Math.max(0, devices.length - 1);
    }

    async function ensureCameraPermission() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Camera not supported in this browser');
        }
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1920 },
                height: { ideal: 1080 },
            },
            audio: false,
        });
        stream.getTracks().forEach((t) => t.stop());
    }

    async function initScanner() {
        try {
            const { BrowserMultiFormatReader, BrowserCodeReader } = await import('https://cdn.jsdelivr.net/npm/@zxing/browser@0.1.5/+esm');
            reader = new BrowserMultiFormatReader();

            await ensureCameraPermission();

            let devices = await BrowserCodeReader.listVideoInputDevices();
            if (!devices.length) {
                await new Promise((r) => setTimeout(r, 400));
                devices = await BrowserCodeReader.listVideoInputDevices();
            }
            if (!devices.length) {
                scanHintEl.textContent = 'No camera found. Allow camera access and reload.';
                return;
            }

            cameraSelect.innerHTML = '';
            devices.forEach((d, idx) => {
                const opt = document.createElement('option');
                opt.value = d.deviceId;
                opt.textContent = (d.label && d.label.trim()) ? d.label : ('Camera ' + (idx + 1));
                cameraSelect.appendChild(opt);
            });

            const defIdx = pickDefaultDeviceIndex(devices);
            cameraSelect.selectedIndex = defIdx;

            const decodeCallback = (result, err) => {
                if (result) {
                    let text = '';
                    try {
                        if (typeof result.getText === 'function') {
                            text = result.getText();
                        } else if (result.text != null) {
                            text = String(result.text);
                        } else {
                            text = String(result);
                        }
                    } catch (e) {
                        text = '';
                    }
                    text = (text || '').trim();
                    if (!text) return;
                    try {
                        if (navigator.vibrate) navigator.vibrate(40);
                    } catch (e) {}
                    beepScan();
                    scanHintEl.textContent = 'Read code: ' + text + ' — looking up…';
                    onBarcode(text, { fromCamera: true });
                    return;
                }
                if (err) {
                    const n = err.name || (err.constructor && err.constructor.name) || '';
                    if (n === 'NotFoundException') return;
                }
            };

            const startWithDevice = async (deviceId) => {
                if (!reader || !deviceId) return;
                try {
                    BrowserCodeReader.releaseAllStreams();
                } catch (e) { /* noop */ }
                scanHintEl.textContent = 'Starting camera…';

                const strictConstraints = {
                    video: {
                        deviceId: { exact: deviceId },
                        width: { ideal: 1920, min: 640 },
                        height: { ideal: 1080, min: 480 },
                        frameRate: { ideal: 30, max: 60 },
                    },
                    audio: false,
                };
                const looseConstraints = {
                    video: { deviceId: { exact: deviceId } },
                    audio: false,
                };

                try {
                    if (typeof reader.decodeFromConstraints === 'function') {
                        try {
                            await reader.decodeFromConstraints(strictConstraints, videoEl, decodeCallback);
                        } catch (e0) {
                            await reader.decodeFromConstraints(looseConstraints, videoEl, decodeCallback);
                        }
                    } else {
                        await reader.decodeFromVideoDevice(deviceId, videoEl, decodeCallback);
                    }
                } catch (e1) {
                    try {
                        await reader.decodeFromVideoDevice(deviceId, videoEl, decodeCallback);
                    } catch (e2) {
                        scanHintEl.textContent = 'Camera error: ' + (e2.message || e1.message || 'unknown');
                        throw e2;
                    }
                }
                scanHintEl.textContent = 'Point the camera at a barcode — switch camera above if blurry';
            };

            cameraSelect.addEventListener('change', () => {
                const id = cameraSelect.value;
                if (id) startWithDevice(id);
            });

            await startWithDevice(devices[defIdx].deviceId);
        } catch (e) {
            console.error(e);
            scanHintEl.textContent = e.message
                ? ('Camera: ' + e.message)
                : 'Could not start scanner. Allow camera access and reload.';
        }
    }

    let selectedProduct = null;

    async function onBarcode(text, opts = {}) {
        const fromCamera = !!opts.fromCamera;
        const code = (text || '').trim();
        if (!code || !dedupe(code)) return;
        scanHintEl.textContent = 'Looking up: ' + code;
        try {
            const p = await lookupProduct(code);
            if (!fromCamera) beep();
            if (isStockMode) {
                selectedProduct = p;
                document.getElementById('stockProductText').innerHTML =
                    `<strong>${escapeHtml(p.name)}</strong><br><span class="text-gray-500">Stock: ${p.quantity} · Price: ${Number(p.price).toFixed(2)}</span>`;
                toast('Product loaded — set quantity', true);
            } else {
                document.getElementById('lastProductText').innerHTML =
                    `<strong>${escapeHtml(p.name)}</strong><br><span class="text-gray-500">${p.quantity} in stock · ${Number(p.price).toFixed(2)} each</span>`;
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
        } finally {
            scanHintEl.textContent = 'Point the camera at a barcode — switch camera above if blurry';
        }
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    await initScanner();

    document.getElementById('manualLookupBtn').addEventListener('click', () => {
        const raw = document.getElementById('manualBarcode').value || '';
        onBarcode(raw, { fromCamera: false });
    });
    document.getElementById('manualBarcode').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            onBarcode(e.target.value || '', { fromCamera: false });
        }
    });

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
                    `<strong>${escapeHtml(j.product.name)}</strong><br><span class="text-gray-500">Stock: ${j.product.quantity} · Price: ${Number(j.product.price).toFixed(2)}</span>`;
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
