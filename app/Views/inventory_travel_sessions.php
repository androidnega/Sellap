<?php
/** @var string $title */
?>
<div class="w-full px-4 sm:px-6 lg:px-8 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Travel inventory sessions</h1>
            <p class="text-sm text-gray-600 mt-1">Snapshot stock when leaving and when returning; compare quantities for audit.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="btnStart" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded shadow text-sm font-medium">
                <i class="fas fa-play mr-2"></i>Start session
            </button>
            <button type="button" id="btnEnd" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded shadow text-sm font-medium hidden">
                <i class="fas fa-stop mr-2"></i>End session
            </button>
        </div>
    </div>

    <div id="activeBanner" class="mb-6 p-4 rounded border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm hidden"></div>
    <div id="msgError" class="mb-4 p-3 rounded border border-red-200 bg-red-50 text-red-800 text-sm hidden"></div>
    <div id="msgOk" class="mb-4 p-3 rounded border border-green-200 bg-green-50 text-green-800 text-sm hidden"></div>

    <div id="endPanel" class="mb-8 bg-white border rounded-lg shadow-sm p-4 sm:p-6 hidden">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">End session</h2>
        <p class="text-sm text-gray-600 mb-4">Who was involved while you were away?</p>
        <div class="space-y-3 mb-4">
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="staff_mode" value="all" checked class="text-emerald-600">
                All staff involved (no single assignee)
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="radio" name="staff_mode" value="single" class="text-emerald-600">
                Specific staff member
            </label>
        </div>
        <div id="staffSelectWrap" class="mb-4 hidden">
            <label class="block text-sm font-medium text-gray-700 mb-1">Staff</label>
            <select id="staffSelect" class="w-full max-w-md border rounded px-3 py-2 text-sm"></select>
        </div>
        <button type="button" id="btnConfirmEnd" class="px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white rounded text-sm font-medium">
            Save end snapshot &amp; close session
        </button>
    </div>

    <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50">
            <h2 class="text-base font-semibold text-gray-800">Past sessions</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">Started</th>
                        <th class="px-4 py-2">Ended</th>
                        <th class="px-4 py-2">Manager</th>
                        <th class="px-4 py-2">Staff</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody id="sessionsBody" class="divide-y text-gray-700"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="reportModal" class="fixed inset-0 z-[10050] hidden items-center justify-center bg-black/50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Session report</h3>
            <button type="button" id="reportClose" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div id="reportContent" class="text-sm space-y-6"></div>
    </div>
</div>

<script>
(function() {
    const base = typeof BASE !== 'undefined' ? BASE : (window.APP_BASE_PATH || '');

    function show(el, on) {
        if (!el) return;
        if (on) el.classList.remove('hidden'); else el.classList.add('hidden');
    }

    function flashErr(msg) {
        const e = document.getElementById('msgError');
        e.textContent = msg;
        show(e, true);
        setTimeout(function() { show(e, false); }, 8000);
    }

    function flashOk(msg) {
        const e = document.getElementById('msgOk');
        e.textContent = msg;
        show(e, true);
        setTimeout(function() { show(e, false); }, 5000);
    }

    let openSession = null;
    let staffLoaded = false;

    async function fetchJson(url, opts) {
        const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts || {}));
        const j = await r.json().catch(function() { return {}; });
        if (!r.ok || j.success === false) {
            throw new Error(j.error || j.message || ('HTTP ' + r.status));
        }
        return j;
    }

    async function loadStaff() {
        if (staffLoaded) return;
        const j = await fetchJson(base + '/api/staff/list', {});
        const sel = document.getElementById('staffSelect');
        sel.innerHTML = '<option value="">— Select —</option>';
        (j.staff || []).forEach(function(s) {
            const o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.full_name || s.username || ('#' + s.id);
            sel.appendChild(o);
        });
        staffLoaded = true;
    }

    function renderSessions(rows) {
        const tb = document.getElementById('sessionsBody');
        if (!rows.length) {
            tb.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No sessions yet.</td></tr>';
            return;
        }
        tb.innerHTML = rows.map(function(r) {
            const staff = r.staff_mode === 'all' ? 'All staff' : (r.staff_name ? r.staff_name : '—');
            const btn = r.status === 'closed'
                ? '<button type="button" class="text-emerald-700 hover:underline view-report" data-id="' + r.id + '">Report</button>'
                : '';
            return '<tr><td class="px-4 py-2">#' + r.id + '</td><td class="px-4 py-2">' + (r.started_at || '') + '</td><td class="px-4 py-2">' + (r.ended_at || '—') + '</td><td class="px-4 py-2">' + (r.manager_name || '') + '</td><td class="px-4 py-2">' + staff + '</td><td class="px-4 py-2">' + r.status + '</td><td class="px-4 py-2">' + btn + '</td></tr>';
        }).join('');
        tb.querySelectorAll('.view-report').forEach(function(b) {
            b.addEventListener('click', function() {
                openReport(parseInt(b.getAttribute('data-id'), 10));
            });
        });
    }

    async function refreshList() {
        const j = await fetchJson(base + '/api/inventory/travel-sessions', {});
        renderSessions(j.data || []);
    }

    async function refreshActive() {
        const j = await fetchJson(base + '/api/inventory/travel-sessions/active', {});
        openSession = j.data;
        const banner = document.getElementById('activeBanner');
        const btnEnd = document.getElementById('btnEnd');
        const btnStart = document.getElementById('btnStart');
        const endPanel = document.getElementById('endPanel');
        if (openSession) {
            banner.textContent = 'Open session #' + openSession.id + ' started at ' + openSession.started_at + '.';
            show(banner, true);
            show(btnEnd, true);
            show(btnStart, false);
            show(endPanel, false);
        } else {
            show(banner, false);
            show(btnEnd, false);
            show(btnStart, true);
            show(endPanel, false);
        }
    }

    document.getElementById('btnStart').addEventListener('click', async function() {
        try {
            await fetchJson(base + '/api/inventory/travel-sessions/start', { method: 'POST' });
            flashOk('Session started. START snapshot saved.');
            await refreshActive();
            await refreshList();
        } catch (e) { flashErr(e.message); }
    });

    document.getElementById('btnEnd').addEventListener('click', async function() {
        show(document.getElementById('endPanel'), true);
        await loadStaff();
    });

    document.querySelectorAll('input[name="staff_mode"]').forEach(function(r) {
        r.addEventListener('change', function() {
            const single = document.querySelector('input[name="staff_mode"]:checked').value === 'single';
            show(document.getElementById('staffSelectWrap'), single);
        });
    });

    document.getElementById('btnConfirmEnd').addEventListener('click', async function() {
        if (!openSession) return;
        const mode = document.querySelector('input[name="staff_mode"]:checked').value;
        const body = { staff_mode: mode };
        if (mode === 'single') {
            const sid = document.getElementById('staffSelect').value;
            if (!sid) { flashErr('Select a staff member.'); return; }
            body.staff_user_id = parseInt(sid, 10);
        }
        try {
            await fetchJson(base + '/api/inventory/travel-sessions/' + openSession.id + '/end', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            flashOk('Session closed. END snapshot saved.');
            show(document.getElementById('endPanel'), false);
            await refreshActive();
            await refreshList();
        } catch (e) { flashErr(e.message); }
    });

    async function openReport(id) {
        try {
            const j = await fetchJson(base + '/api/inventory/travel-sessions/' + id + '/report', {});
            const d = j.data;
            const s = d.session || {};
            let html = '<div class="text-gray-600 mb-4">Session #' + s.id + ' · ' + (s.started_at || '') + ' → ' + (s.ended_at || '') + '</div>';
            function table(title, rows, tone) {
                if (!rows.length) return '';
                let h = '<div><h4 class="font-semibold mb-2 ' + tone + '">' + title + '</h4><table class="w-full text-xs border"><thead><tr class="bg-gray-100"><th class="text-left p-2">Item</th><th class="text-right p-2">Start</th><th class="text-right p-2">End</th><th class="text-right p-2">Diff</th></tr></thead><tbody>';
                rows.forEach(function(x) {
                    h += '<tr class="border-t"><td class="p-2">' + (x.name || '') + '</td><td class="p-2 text-right">' + x.start_quantity + '</td><td class="p-2 text-right">' + x.end_quantity + '</td><td class="p-2 text-right font-medium">' + x.difference + '</td></tr>';
                });
                h += '</tbody></table></div>';
                return h;
            }
            html += table('Reduced (sold / missing)', d.reduced || [], 'text-red-700');
            html += table('Increased (restocked)', d.increased || [], 'text-emerald-700');
            html += '<div><h4 class="font-semibold mb-2 text-gray-800">All items</h4><table class="w-full text-xs border"><thead><tr class="bg-gray-100"><th class="text-left p-2">Item</th><th class="text-right p-2">Start</th><th class="text-right p-2">End</th><th class="text-right p-2">Diff</th></tr></thead><tbody>';
            (d.items || []).forEach(function(x) {
                html += '<tr class="border-t"><td class="p-2">' + (x.name || '') + '</td><td class="p-2 text-right">' + x.start_quantity + '</td><td class="p-2 text-right">' + x.end_quantity + '</td><td class="p-2 text-right">' + x.difference + '</td></tr>';
            });
            html += '</tbody></table></div>';
            document.getElementById('reportContent').innerHTML = html;
            document.getElementById('reportModal').classList.remove('hidden');
            document.getElementById('reportModal').classList.add('flex');
        } catch (e) { flashErr(e.message); }
    }

    document.getElementById('reportClose').addEventListener('click', function() {
        var m = document.getElementById('reportModal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    });

    refreshActive().then(refreshList).catch(function(e) { flashErr(e.message); });
})();
</script>
