<?php
if (!defined('BASE_URL_PATH')) {
    http_response_code(500);
    exit('Configuration error');
}
$base = rtrim(BASE_URL_PATH, '/');
$assetBase = defined('BASE_URL_PATH') ? BASE_URL_PATH : '';
?><!DOCTYPE html>
<html lang="en" class="pwa-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>SellApp Scanner</title>
    <link rel="manifest" href="<?php echo htmlspecialchars($assetBase . '/api/pwa/manifest.webmanifest', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($assetBase . '/assets/images/favicon.svg', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetBase . '/assets/css/pwa.css', ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="pwa-body pwa-body--scan">
    <header class="pwa-header pwa-header--barcode">
        <h1 class="pwa-header__title">Barcode</h1>
        <button type="button" id="logoutBtn" class="pwa-btn-text" aria-label="Log out">Out</button>
    </header>

    <main class="pwa-main">
        <div class="pwa-camera">
            <video id="video" class="pwa-video" playsinline muted autoplay></video>
            <div class="pwa-scan-sweep" aria-hidden="true"></div>
            <div class="pwa-camera__toolbar">
                <label class="pwa-sr-only" for="cameraSelect">Camera</label>
                <select id="cameraSelect" class="pwa-select-cam" title="Camera"></select>
            </div>
            <div class="pwa-camera__bar">
                <input type="text" id="manualBarcode" inputmode="numeric" autocomplete="off" placeholder="Code" enterkeyhint="go">
                <button type="button" id="manualLookupBtn" class="pwa-btn-small">Go</button>
            </div>
        </div>

        <div id="salesPanel" class="pwa-panel">
            <div class="pwa-cart">
                <div class="pwa-cart__head">
                    <span>Cart</span>
                    <span id="cartCount" class="pwa-cart__count">0</span>
                </div>
                <ul id="cartList" class="pwa-cart__list"></ul>
                <div class="pwa-cart__total">
                    <span>Total</span>
                    <strong id="cartTotal">0.00</strong>
                </div>
            </div>
            <button type="button" id="checkoutBtn" class="pwa-btn pwa-btn--emerald">Pay</button>
        </div>

        <div id="stockPanel" class="pwa-panel">
            <div id="stockProduct" class="pwa-card pwa-card--stock">
                <p id="stockProductText" class="pwa-card__body">—</p>
            </div>
            <input type="number" id="stockQty" min="1" value="1" class="pwa-input pwa-input--amber" aria-label="Qty">
            <button type="button" id="addStockBtn" class="pwa-btn pwa-btn--amber">Add stock</button>
        </div>
    </main>

    <div id="toast" class="pwa-toast" role="status" aria-live="polite"></div>

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

    function toast(msg, ok = true) {
        const el = document.getElementById('toast');
        el.textContent = msg;
        el.className = 'pwa-toast is-visible ' + (ok ? 'pwa-toast--ok' : 'pwa-toast--err');
        clearTimeout(toast._t);
        toast._t = setTimeout(() => { el.classList.remove('is-visible'); }, 2600);
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
        beepTone(880, 0.06, 0.07);
    }

    /** One beep per scan burst — avoids stacked beeps from repeated decode frames */
    let lastBeepAt = 0;
    const BEEP_MIN_GAP_MS = 2200;
    function maybeBeep() {
        const t = Date.now();
        if (t - lastBeepAt < BEEP_MIN_GAP_MS) return;
        lastBeepAt = t;
        beepScan();
        try {
            if (navigator.vibrate) navigator.vibrate(35);
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
            li.className = 'pwa-cart-line';
            li.innerHTML = `<div class="pwa-cart-line__name"><span>${escapeHtml(line.name)} × ${line.quantity}</span></div>` +
                `<span class="pwa-cart-line__price">${line.total_price.toFixed(2)}</span>` +
                `<button type="button" data-i="${idx}" class="pwa-cart-line__remove remove-line">✕</button>`;
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
        document.getElementById('cartCount').textContent = String(items.length);
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
        if (code === lastScan && now - lastScanAt < 1800) return false;
        lastScan = code;
        lastScanAt = now;
        return true;
    }

    // --- ZXing: 1D barcodes only, primary rear camera, TRY_HARDER ---
    let reader = null;
    const videoEl = document.getElementById('video');
    const cameraSelect = document.getElementById('cameraSelect');

    /** Prefer main/primary/wide back camera; avoid ultra-wide, tele, macro, front */
    function pickPrimaryCameraIndex(devices) {
        if (!devices || !devices.length) return 0;
        const scored = devices.map((d, i) => {
            const label = (d.label || '').toLowerCase();
            let s = 0;
            if (/face|front|selfie|user|portrait/.test(label)) s -= 200;
            if (/ultra|telephoto|tele\b|macro|depth|virtual|lid|narrow|fisheye/.test(label)) s -= 40;
            if (/primary|main\b|wide angle(?!.*ultra)|back camera|rear|environment/.test(label)) s += 50;
            if (/\bwide\b/.test(label) && !/ultra/.test(label)) s += 30;
            if (/back|rear|environment|world/.test(label)) s += 15;
            return { i, s };
        });
        scored.sort((a, b) => b.s - a.s);
        if (scored[0].s > -80) return scored[0].i;
        return Math.max(0, devices.length - 1);
    }

    function applyVideoEnhancements() {
        try {
            const stream = videoEl.srcObject;
            if (!stream) return;
            const track = stream.getVideoTracks()[0];
            if (!track || !track.applyConstraints) return;
            const caps = track.getCapabilities ? track.getCapabilities() : {};
            const advanced = [];
            if (caps.focusMode && Array.isArray(caps.focusMode) && caps.focusMode.includes('continuous')) {
                advanced.push({ focusMode: 'continuous' });
            }
            if (advanced.length) {
                track.applyConstraints({ advanced }).catch(() => {});
            }
        } catch (e) { /* noop */ }
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
            const [{ BrowserMultiFormatReader, BrowserCodeReader }, lib] = await Promise.all([
                import('https://cdn.jsdelivr.net/npm/@zxing/browser@0.1.5/+esm'),
                import('https://cdn.jsdelivr.net/npm/@zxing/library@0.20.0/+esm'),
            ]);
            const { BarcodeFormat, DecodeHintType } = lib;

            const FORMATS_1D = [
                BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A, BarcodeFormat.UPC_E,
                BarcodeFormat.CODE_128, BarcodeFormat.CODE_39, BarcodeFormat.ITF, BarcodeFormat.CODABAR,
            ];

            function buildReader() {
                const hints = new Map();
                hints.set(DecodeHintType.TRY_HARDER, true);
                hints.set(DecodeHintType.POSSIBLE_FORMATS, FORMATS_1D);
                return new BrowserMultiFormatReader(hints);
            }

            reader = buildReader();

            await ensureCameraPermission();

            let devices = await BrowserCodeReader.listVideoInputDevices();
            if (!devices.length) {
                await new Promise((r) => setTimeout(r, 400));
                devices = await BrowserCodeReader.listVideoInputDevices();
            }
            if (!devices.length) {
                toast('No camera — allow access and reload', false);
                return;
            }

            if (!cameraSelect.options.length) {
                cameraSelect.innerHTML = '';
                devices.forEach((d, idx) => {
                    const opt = document.createElement('option');
                    opt.value = d.deviceId;
                    opt.textContent = (d.label && d.label.trim()) ? d.label : ('Camera ' + (idx + 1));
                    cameraSelect.appendChild(opt);
                });
                const defIdx = pickPrimaryCameraIndex(devices);
                cameraSelect.selectedIndex = defIdx;
                cameraSelect.addEventListener('change', () => {
                    const id = cameraSelect.value;
                    if (id) startWithDevice(id);
                });
            }

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
                    onBarcode(text);
                    return;
                }
                if (err) {
                    const n = err.name || (err.constructor && err.constructor.name) || '';
                    if (n === 'NotFoundException') return;
                }
            };

            const startWithDevice = async (deviceId) => {
                if (!deviceId) return;
                reader = buildReader();
                try {
                    BrowserCodeReader.releaseAllStreams();
                } catch (e) { /* noop */ }
                const strictConstraints = {
                    video: {
                        deviceId: { exact: deviceId },
                        width: { ideal: 1920, min: 1280 },
                        height: { ideal: 1080, min: 720 },
                        frameRate: { ideal: 30, max: 60 },
                    },
                    audio: false,
                };
                const looseConstraints = {
                    video: {
                        deviceId: { exact: deviceId },
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                    },
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
                        toast('Camera: ' + (e2.message || e1.message || 'error'), false);
                        throw e2;
                    }
                }
                setTimeout(applyVideoEnhancements, 300);
            };

            await startWithDevice(cameraSelect.value || devices[pickPrimaryCameraIndex(devices)].deviceId);
        } catch (e) {
            console.error(e);
            toast(e.message ? ('Camera: ' + e.message) : 'Camera unavailable', false);
        }
    }

    let selectedProduct = null;

    async function onBarcode(text) {
        const code = (text || '').trim();
        if (!code || !dedupe(code)) return;
        try {
            const p = await lookupProduct(code);
            maybeBeep();
            if (isStockMode) {
                selectedProduct = p;
                document.getElementById('stockProductText').innerHTML =
                    `<strong>${escapeHtml(p.name)}</strong><br><span class="pwa-muted">${p.quantity} · ${Number(p.price).toFixed(2)}</span>`;
                toast('OK', true);
            } else {
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
                toast('Added', true);
            }
        } catch (e) {
            toast(e.message || 'Not found', false);
        }
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // Show cart / stock immediately so the page never looks blank while the camera initializes
    if (isStockMode) {
        document.getElementById('stockPanel').classList.add('is-visible');
    } else {
        document.getElementById('salesPanel').classList.add('is-visible');
        renderCart();
    }

    await initScanner();

    document.getElementById('manualLookupBtn').addEventListener('click', () => {
        const raw = document.getElementById('manualBarcode').value || '';
        onBarcode(raw);
    });
    document.getElementById('manualBarcode').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            onBarcode(e.target.value || '');
        }
    });

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
            toast('Done', true);
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
                    `<strong>${escapeHtml(j.product.name)}</strong><br><span class="pwa-muted">Stock: ${j.product.quantity} · Price: ${Number(j.product.price).toFixed(2)}</span>`;
            }
            maybeBeep();
            toast('Updated', true);
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
