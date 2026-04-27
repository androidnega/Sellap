<?php
$migrationNeeded = $migrationNeeded ?? true;
$base = defined('BASE_URL_PATH') ? rtrim((string)BASE_URL_PATH, '/') : '';
?>
<div class="w-full min-w-0 max-w-6xl mx-auto px-3 sm:px-4 md:px-6 py-6 md:py-8">
    <h1 class="text-2xl font-bold text-gray-900">Holiday &amp; broadcast SMS</h1>
    <p class="text-gray-600 mt-2 max-w-3xl">Schedule holiday messages (sent automatically when the date matches) or send a one-time message to selected customers or to everyone. All sends use <strong>your company’s SMS balance</strong>, not the platform account.</p>

    <?php if (!empty($migrationNeeded)): ?>
        <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-4 text-amber-900 text-sm max-w-3xl">
            <strong>Database setup required.</strong> A system administrator can create the tables in one step:
            <a href="<?= htmlspecialchars($base) ?>/dashboard/tools" class="font-medium text-amber-950 underline hover:no-underline">Migration tools</a>
            → <span class="font-medium">Holiday &amp; broadcast SMS (tables)</span> → Run migration. You can also run
            <code class="bg-white px-1 rounded">php database/migrations/run_company_holiday_sms_migration.php</code> on the server.
        </div>
    <?php endif; ?>

    <div class="mt-6 flex flex-wrap gap-4 items-center">
        <div class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm">
            <span class="text-gray-600">SMS balance:</span>
            <strong class="ml-1" id="sms-balance-broadcast">—</strong>
        </div>
        <a href="<?= htmlspecialchars($base) ?>/dashboard/sms/purchase" class="text-sm text-teal-700 font-medium hover:underline">Buy credits</a>
    </div>

    <div class="mt-10">
        <h2 class="text-lg font-semibold text-gray-900">Scheduled holidays &amp; events</h2>
        <p class="text-sm text-gray-600 mt-1">Set a date and message. <strong>Annual</strong> repeats every year (month &amp; day). The server sends once per day when the date matches (see cron below).</p>

        <form id="form-holiday" class="mt-4 space-y-3 max-w-lg rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <input type="hidden" name="id" id="holiday-id" value="" />
            <div>
                <label class="block text-sm font-medium text-gray-700">Label</label>
                <input name="label" id="h-label" required class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="e.g. Christmas" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Date</label>
                <input name="event_date" id="h-date" type="date" required class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2 text-sm" />
            </div>
            <div class="flex items-center gap-2">
                <input name="is_annual" id="h-annual" type="checkbox" class="rounded border-gray-300" checked />
                <label for="h-annual" class="text-sm text-gray-700">Repeat every year (uses month and day only)</label>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Message</label>
                <textarea name="message_body" id="h-body" required rows="4" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="Your greeting…"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input name="is_active" id="h-active" type="checkbox" class="rounded border-gray-300" checked />
                <label for="h-active" class="text-sm text-gray-700">Enabled for automatic send</label>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-md hover:bg-teal-700">Save</button>
                <button type="button" id="btn-holiday-clear" class="px-4 py-2 border border-gray-300 text-sm rounded-md hover:bg-gray-50">New / clear</button>
            </div>
        </form>

        <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600">
                    <tr>
                        <th class="px-3 py-2">Label</th>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Annual</th>
                        <th class="px-3 py-2">Active</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody id="holidays-tbody" class="divide-y divide-gray-100">
                    <tr id="holidays-empty"><td colspan="5" class="px-3 py-4 text-gray-500">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-12">
        <h2 class="text-lg font-semibold text-gray-900">Manual broadcast</h2>
        <p class="text-sm text-gray-600 mt-1">Send a custom message now. Choose <strong>all customers with a phone</strong> or select specific people.</p>

        <form id="form-broadcast" class="mt-4 max-w-2xl space-y-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div>
                <label class="block text-sm font-medium text-gray-700">Message</label>
                <textarea name="message" id="b-msg" required rows="4" class="mt-1 w-full border border-gray-300 rounded-md px-3 py-2 text-sm"></textarea>
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-700">Recipients</span>
                <label class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="b-scope" value="all" class="b-scope" checked />
                    All customers (with phone number on file)
                </label>
                <label class="mt-1 flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="b-scope" value="selected" class="b-scope" />
                    Selected customers only
                </label>
            </div>
            <div id="b-customer-wrap" class="hidden max-h-56 overflow-y-auto border border-gray-200 rounded-md p-2" aria-label="Select customers">
                <div class="text-xs text-gray-500 py-2" id="b-customer-loading">Loading…</div>
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700" id="btn-broadcast">Send</button>
            </div>
        </form>
    </div>

    <div class="mt-10 rounded-md bg-slate-50 border border-slate-200 p-4 text-sm text-slate-700">
        <h3 class="font-semibold text-slate-900">Automatic daily sends (cron)</h3>
        <p class="mt-1">Add a daily job on the server, for example:</p>
        <code class="block mt-2 text-xs bg-white p-2 rounded border border-slate-200 overflow-x-auto">0 8 * * * /usr/bin/php /path/to/Sellap/cron/run_holiday_sms.php</code>
        <p class="mt-2">Uses the server’s calendar date. Each holiday fires at most once per calendar day for all matching customers with phone numbers.</p>
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
          balanceEl.textContent = (d.balance.sms_remaining != null ? d.balance.sms_remaining : '0') + ' remaining';
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
        tbody.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-red-600">Could not load holidays</td></tr>';
        return;
      }
      const list = d.holidays || [];
      if (list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-gray-500">No holidays yet. Add one above.</td></tr>';
        return;
      }
      tbody.innerHTML = list.map(function(h) {
        const a = h.is_annual == 1 || h.is_annual === '1' ? 'Yes' : 'No';
        const act = h.is_active == 1 || h.is_active === '1' ? 'Yes' : 'No';
        return '<tr class="hover:bg-gray-50">'
          + '<td class="px-3 py-2">' + escapeHtml(h.label) + '</td>'
          + '<td class="px-3 py-2 whitespace-nowrap">' + escapeHtml((h.event_date || '').toString().slice(0, 10)) + '</td>'
          + '<td class="px-3 py-2">' + a + '</td>'
          + '<td class="px-3 py-2">' + act + '</td>'
          + '<td class="px-3 py-2 text-right space-x-2">'
          + '<button type="button" class="text-indigo-600 text-xs hover:underline" data-h="' + h.id + '">Edit</button>'
          + '<button type="button" class="text-red-600 text-xs hover:underline" data-d="' + h.id + '">Delete</button>'
          + '<button type="button" class="text-teal-700 text-xs font-medium hover:underline" data-s="' + h.id + '">Send now to all</button>'
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
      if (!confirm('Send this holiday message to ALL customers with a phone now? SMS will be taken from your company balance.')) return;
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
          formH.querySelector('#btn-holiday-clear').click();
          renderHolidays();
        } else { alert(res.error || 'Save failed'); }
      });
  });

  document.getElementById('btn-holiday-clear').addEventListener('click', function() {
    document.getElementById('holiday-id').value = '';
    document.getElementById('h-label').value = '';
    const d = new Date();
    const iso = d.toISOString().slice(0, 10);
    document.getElementById('h-date').value = iso;
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
          w.innerHTML = '<div class="text-red-600 text-sm">Failed to load customers</div>';
          return;
        }
        allCustomers = d.customers || [];
        w.innerHTML = allCustomers
          .filter(c => c.phone_number && c.phone_number.trim() !== '')
          .map(c => '<label class="flex items-center gap-2 py-1 text-sm">'
            + '<input type="checkbox" class="b-cb" data-id="' + c.id + '" /> '
            + escapeHtml(c.full_name) + ' <span class="text-gray-500">' + escapeHtml(c.phone_number) + '</span></label>')
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
