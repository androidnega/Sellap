<?php
$migrationNeeded = $migrationNeeded ?? true;
$base = defined('BASE_URL_PATH') ? rtrim((string)BASE_URL_PATH, '/') : '';
$cronHttpExample = $cronHttpExample ?? ($base . '/api/cron/holiday-sms?token=YOUR_SECRET');
$cronHttpDisplay = (defined('APP_URL') ? rtrim((string)APP_URL, '/') : '') . $cronHttpExample;
?>
<div class="w-full min-w-0 max-w-6xl mx-auto">
    <div class="rounded-2xl overflow-hidden border border-slate-200/80 bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-900 text-white shadow-lg mb-6">
        <div class="px-4 sm:px-6 py-5 sm:py-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-indigo-200 text-xs font-semibold uppercase tracking-wider">Customer messaging</p>
                    <h1 class="text-2xl sm:text-3xl font-bold text-white mt-1">Holiday &amp; broadcast SMS</h1>
                    <p class="text-slate-300 text-sm sm:text-base mt-2 max-w-2xl leading-relaxed">
                        Plan seasonal texts and one-off blasts. Every send uses <span class="text-white font-medium">your company’s SMS balance</span> — not the platform account.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:flex-shrink-0">
                    <div class="inline-flex items-center rounded-full bg-white/10 backdrop-blur px-3 py-1.5 text-sm border border-white/10">
                        <i class="fas fa-coins text-amber-300 mr-2"></i>
                        <span class="text-slate-200">Balance</span>
                        <strong class="ml-1.5 text-white" id="sms-balance-broadcast">—</strong>
                    </div>
                    <a href="<?= htmlspecialchars($base) ?>/dashboard/sms/purchase" class="inline-flex items-center justify-center rounded-full bg-white text-slate-900 text-sm font-semibold px-4 py-2 shadow hover:bg-slate-100 transition">
                        <i class="fas fa-shopping-cart mr-2 text-xs"></i> Top up
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($migrationNeeded)): ?>
    <div class="mb-6 rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-amber-950 text-sm max-w-3xl">
        <i class="fas fa-database mr-1.5 text-amber-600"></i>
        <strong>Database setup required.</strong> A system admin can run
        <a href="<?= htmlspecialchars($base) ?>/dashboard/tools" class="font-semibold text-amber-900 underline decoration-amber-400/80">Migration tools</a>
        → <em>Holiday &amp; broadcast SMS (tables)</em>, or use the CLI migration script.
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 lg:gap-6 mb-8">
        <div class="lg:col-span-2 rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wide flex items-center gap-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 text-xs">1</span>
                How automatic sends work
            </h2>
            <ol class="mt-3 space-y-2.5 text-sm text-slate-600 list-decimal list-inside pl-0.5">
                <li><span class="text-slate-800 font-medium">You add dates and messages</span> here (annual = same month &amp; day every year).</li>
                <li><span class="text-slate-800 font-medium">Something must run the job once per day</span> on the server — the app does <em>not</em> create a Linux/cPanel cron by itself when you pick a date.</li>
                <li>On the matching calendar day, the job sends to customers with a phone on file, once per holiday per day.</li>
            </ol>
        </div>
        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/50 p-4 sm:p-5">
            <h2 class="text-sm font-bold text-emerald-800 flex items-center gap-2">
                <i class="fas fa-link text-emerald-600"></i> No SSH cron?
            </h2>
            <p class="text-xs text-emerald-900/90 mt-2 leading-relaxed">
                Use a free HTTP monitor (e.g. UptimeRobot) to request this URL <strong>once per day</strong> with your secret token — same result as a server cron.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 py-3 border-b border-slate-100 bg-slate-50/80">
                <h2 class="text-lg font-semibold text-slate-900">Scheduled holidays</h2>
                <p class="text-xs text-slate-500 mt-0.5">Label, date, message. Toggle <strong>Annual</strong> for yearly repeats.</p>
            </div>
            <div class="p-4 sm:p-5">
                <form id="form-holiday" class="space-y-3 max-w-md">
                    <input type="hidden" name="id" id="holiday-id" value="" />
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide">Label</label>
                        <input name="label" id="h-label" required class="mt-1 w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400" placeholder="e.g. Independence Day" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</label>
                        <input name="event_date" id="h-date" type="date" required class="mt-1 w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400" />
                    </div>
                    <div class="flex items-center gap-2">
                        <input name="is_annual" id="h-annual" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" checked />
                        <label for="h-annual" class="text-sm text-slate-700">Repeat every year (month &amp; day only)</label>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide">Message</label>
                        <textarea name="message_body" id="h-body" required rows="4" class="mt-1 w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400" placeholder="Your SMS text…"></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input name="is_active" id="h-active" type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" checked />
                        <label for="h-active" class="text-sm text-slate-700">Enabled for automatic send</label>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="submit" class="px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition shadow-sm">Save</button>
                        <button type="button" id="btn-holiday-clear" class="px-4 py-2.5 border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50">New / clear</button>
                    </div>
                </form>
            </div>
            <div class="px-0 border-t border-slate-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50/90 text-left text-xs font-bold uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-2.5">Label</th>
                                <th class="px-4 py-2.5">Date</th>
                                <th class="px-4 py-2.5">Annual</th>
                                <th class="px-4 py-2.5">Active</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="holidays-tbody" class="divide-y divide-slate-100 text-slate-800">
                            <tr id="holidays-empty"><td colspan="5" class="px-4 py-5 text-slate-500">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm p-4 sm:p-5">
                <h2 class="text-lg font-semibold text-slate-900">Manual broadcast</h2>
                <p class="text-xs text-slate-500 mt-1">Send now — all customers with a phone, or a custom selection.</p>
                <form id="form-broadcast" class="mt-4 space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide">Message</label>
                        <textarea name="message" id="b-msg" required rows="4" class="mt-1 w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:ring-2 focus:ring-teal-500/30 focus:border-teal-500"></textarea>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Recipients</span>
                        <label class="flex items-center gap-2 text-sm text-slate-700 py-0.5">
                            <input type="radio" name="b-scope" value="all" class="b-scope text-teal-600 focus:ring-teal-500" checked />
                            All customers (with phone)
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 py-0.5">
                            <input type="radio" name="b-scope" value="selected" class="b-scope text-teal-600 focus:ring-teal-500" />
                            Selected only
                        </label>
                    </div>
                    <div id="b-customer-wrap" class="hidden max-h-48 overflow-y-auto border border-slate-200 rounded-lg p-2 bg-slate-50/50" aria-label="Select customers">
                        <div class="text-xs text-slate-500 py-1" id="b-customer-loading">Loading…</div>
                    </div>
                    <button type="submit" class="w-full sm:w-auto px-4 py-2.5 bg-teal-600 text-white text-sm font-semibold rounded-lg hover:bg-teal-700 transition shadow-sm" id="btn-broadcast">Send</button>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5 text-sm text-slate-700">
                <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                    <i class="fas fa-clock text-indigo-500"></i> Schedule the daily job
                </h3>
                <p class="mt-2 text-xs text-slate-600 leading-relaxed">
                    <strong>SSH / cPanel cron</strong> (recommended one line per day):
                </p>
                <code class="block mt-2 text-xs bg-slate-900 text-slate-100 p-3 rounded-lg overflow-x-auto font-mono">0 8 * * * /usr/bin/php /path/to/Sellap/cron/run_holiday_sms.php</code>
                <p class="mt-3 text-xs text-slate-600"><strong>HTTP + token</strong> (good if you cannot use SSH; call once daily):</p>
                <code class="block mt-1.5 text-xs bg-white border border-slate-200 p-2.5 rounded-lg overflow-x-auto break-all font-mono text-slate-800"><?= htmlspecialchars($cronHttpDisplay !== '' ? $cronHttpDisplay : $cronHttpExample) ?></code>
                <p class="mt-1 text-[11px] text-slate-500">Replace host with your domain; set a strong <code class="bg-slate-200/80 px-1 rounded">HOLIDAY_SMS_CRON_TOKEN</code> on the server (defaults exist for dev only). Header <code class="bg-slate-200/80 px-1">X-SELLAP-CRON-TOKEN</code> is also supported.</p>
            </section>
        </div>
    </div>
</div>
<script>
(function() {
  const B = (typeof window.APP_BASE_PATH === 'string' && window.APP_BASE_PATH) || '';
  function api(path) { return B + path; }
  const tbody = document.getElementById('holidays-tbody');
  const formH = document.getElementById('form-holiday');
  const formB = document.getElementById('form-broadcast');
  const balanceEl = document.getElementById('sms-balance-broadcast');
  let allCustomers = [];

  function showBalance() {
    fetch(api('/api/company/sms-balance'), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        if (d.success && d.balance) {
          balanceEl.textContent = (d.balance.sms_remaining != null ? d.balance.sms_remaining : '0') + ' left';
        } else {
          balanceEl.textContent = 'n/a';
        }
      })
      .catch(function() { balanceEl.textContent = 'n/a'; });
  }
  showBalance();

  function loadHolidays() {
    return fetch(api('/api/company/sms-holidays'), { credentials: 'same-origin' })
      .then(r => r.json());
  }

  function renderHolidays() {
    loadHolidays().then(function(d) {
      if (!d.success) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-4 text-rose-600">Could not load holidays</td></tr>';
        return;
      }
      const list = d.holidays || [];
      if (list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-5 text-slate-500">No holidays yet. Add one in the form.</td></tr>';
        return;
      }
      tbody.innerHTML = list.map(function(h) {
        const a = h.is_annual == 1 || h.is_annual === '1' ? 'Yes' : 'No';
        const act = h.is_active == 1 || h.is_active === '1' ? 'Yes' : 'No';
        return '<tr class="hover:bg-slate-50/80">'
          + '<td class="px-4 py-2.5 font-medium text-slate-800">' + escapeHtml(h.label) + '</td>'
          + '<td class="px-4 py-2.5 whitespace-nowrap text-slate-600">' + escapeHtml((h.event_date || '').toString().slice(0, 10)) + '</td>'
          + '<td class="px-4 py-2.5 text-slate-600">' + a + '</td>'
          + '<td class="px-4 py-2.5 text-slate-600">' + act + '</td>'
          + '<td class="px-4 py-2.5 text-right space-x-2">'
          + '<button type="button" class="text-indigo-600 text-xs font-medium hover:underline" data-h="' + h.id + '">Edit</button>'
          + '<button type="button" class="text-rose-600 text-xs font-medium hover:underline" data-d="' + h.id + '">Delete</button>'
          + '<button type="button" class="text-teal-700 text-xs font-semibold hover:underline" data-s="' + h.id + '">Send now</button>'
          + '</td></tr>';
      }).join('');
    });
  }

  function escapeHtml(s) {
    if (!s) return '';
    const t = document.createTextNode('' + s);
    const x = document.createElement('div');
    x.appendChild(t);
    return x.innerHTML;
  }

  tbody.addEventListener('click', function(ev) {
    const e = ev.target;
    if (e.matches('[data-d]')) {
      if (!confirm('Delete this entry?')) return;
      const id = parseInt(e.getAttribute('data-d'), 10);
      fetch(api('/api/company/sms-holidays/delete'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
      }).then(r => r.json()).then(function() { renderHolidays(); showBalance(); });
    }
    if (e.matches('[data-h]')) {
      const id = parseInt(e.getAttribute('data-h'), 10);
      loadHolidays().then(function(d) {
        const h = (d.holidays || []).find(x => x.id == id);
        if (!h) return;
        document.getElementById('holiday-id').value = h.id;
        document.getElementById('h-label').value = h.label;
        document.getElementById('h-date').value = (h.event_date || '').toString().slice(0, 10);
        document.getElementById('h-annual').checked = h.is_annual == 1 || h.is_annual === '1';
        document.getElementById('h-body').value = h.message_body || '';
        document.getElementById('h-active').checked = h.is_active == 1 || h.is_active === '1';
      });
    }
    if (e.matches('[data-s]')) {
      const id = e.getAttribute('data-s');
      if (!confirm('Send this holiday message to ALL customers with a phone now? Uses your company SMS balance.')) return;
      fetch(api('/api/company/sms-holidays/' + id + '/send-now'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' }
      })
        .then(r => r.json())
        .then(function(res) {
          if (res.success) {
            alert('Sent: ' + res.sent + (res.failed ? ', failed: ' + res.failed : ''));
            showBalance();
          } else {
            alert(res.error || 'Send failed');
          }
        });
    }
  });

  formH.addEventListener('submit', function(ev) {
    ev.preventDefault();
    const payload = {
      id: (document.getElementById('holiday-id').value || '') ? parseInt(document.getElementById('holiday-id').value, 10) : undefined,
      label: document.getElementById('h-label').value,
      event_date: document.getElementById('h-date').value,
      is_annual: document.getElementById('h-annual').checked,
      message_body: document.getElementById('h-body').value,
      is_active: document.getElementById('h-active').checked
    };
    if (isNaN(payload.id)) { delete payload.id; }
    fetch(api('/api/company/sms-holidays/save'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(r => r.json())
      .then(function(res) {
        if (res.success) {
          document.getElementById('btn-holiday-clear').click();
          renderHolidays();
        } else { alert(res.error || 'Save failed'); }
      });
  });

  document.getElementById('btn-holiday-clear').addEventListener('click', function() {
    document.getElementById('holiday-id').value = '';
    document.getElementById('h-label').value = '';
    const d = new Date();
    document.getElementById('h-date').value = d.toISOString().slice(0, 10);
    document.getElementById('h-annual').checked = true;
    document.getElementById('h-body').value = '';
    document.getElementById('h-active').checked = true;
  });
  (function() {
    const d = new Date();
    if (!document.getElementById('h-date').value) {
      document.getElementById('h-date').value = d.toISOString().slice(0, 10);
    }
  })();

  function loadCustomers() {
    const w = document.getElementById('b-customer-wrap');
    return fetch(api('/api/company/sms-broadcast/customers'), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(function(d) {
        if (!d.success) {
          w.innerHTML = '<div class="text-rose-600 text-sm">Failed to load customers</div>';
          return;
        }
        allCustomers = d.customers || [];
        w.innerHTML = allCustomers
          .filter(c => c.phone_number && c.phone_number.trim() !== '')
          .map(c => '<label class="flex items-center gap-2 py-1 text-sm">'
            + '<input type="checkbox" class="b-cb rounded text-teal-600" data-id="' + c.id + '" /> '
            + escapeHtml(c.full_name) + ' <span class="text-slate-500">' + escapeHtml(c.phone_number) + '</span></label>')
          .join('');
        if (w.querySelectorAll('.b-cb').length === 0) {
          w.innerHTML = '<p class="text-sm text-amber-800">No customers with a phone on file yet.</p>';
        }
      });
  }

  document.querySelectorAll('.b-scope').forEach(function(r) {
    r.addEventListener('change', function() {
      const on = document.querySelector('.b-scope[value="selected"]').checked;
      document.getElementById('b-customer-wrap').classList.toggle('hidden', !on);
      if (on && allCustomers.length === 0) loadCustomers();
    });
  });

  formB.addEventListener('submit', function(ev) {
    ev.preventDefault();
    const selectedMode = document.querySelector('.b-scope[value="selected"]').checked;
    let ids = [];
    if (selectedMode) {
      formB.querySelectorAll('.b-cb:checked').forEach(function(cb) {
        ids.push(parseInt(cb.getAttribute('data-id'), 10));
      });
      if (ids.length === 0) { alert('Select at least one customer, or use “All customers”.'); return; }
    }
    const message = (document.getElementById('b-msg').value || '').trim();
    if (!message) { alert('Enter a message'); return; }
    if (!confirm('This will use your company SMS balance. Continue?')) return;
    const btn = document.getElementById('btn-broadcast');
    btn.disabled = true;
    fetch(api('/api/company/sms-broadcast/send'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: message, scope: selectedMode ? 'selected' : 'all', customer_ids: ids })
    })
      .then(r => r.json())
      .then(function(res) {
        btn.disabled = false;
        if (res.success) { alert('Sent: ' + res.sent + (res.failed ? ', failed: ' + res.failed : '')); formB.reset(); showBalance(); }
        else { alert(res.error || 'Failed'); }
      })
      .catch(function() { btn.disabled = false; });
  });

  renderHolidays();
})();
</script>
