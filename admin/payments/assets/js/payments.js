/* ===================================================================
   Elegance Salon - PART 6 / Billing module JS
   Client & invoice search dropdowns, line-item editor, revenue charts.
   =================================================================== */
(function () {
    'use strict';

    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    /* --------------------------------------------------------------
       Small POST helper (returns Promise of parsed JSON)
       -------------------------------------------------------------- */
    function finPost(url, data) {
        var body = new URLSearchParams();
        var key;
        Object.keys(data).forEach(function (k) {
            if (Array.isArray(data[k])) {
                data[k].forEach(function (v) { body.append(k, v); });
            } else {
                body.append(k, data[k] == null ? '' : data[k]);
            }
        });
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: body.toString()
        }).then(function (res) {
            return res.json().then(function (json) {
                if (!res.ok) { throw new Error(json.error || ('Request failed (' + res.status + ')')); }
                return json;
            });
        });
    }

    /* --------------------------------------------------------------
       Generic debounce
       -------------------------------------------------------------- */
    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, ms);
        };
    }

    /* --------------------------------------------------------------
       Client search dropdown
       Expects: [data-fin-client-search], hidden target [data-fin-client-id],
       output element [data-fin-client-selection]
       -------------------------------------------------------------- */
    function bindClientSearch(root) {
        var input = root.querySelector('[data-fin-client-search]');
        var hidden = root.querySelector('[data-fin-client-id]');
        var list = root.querySelector('.fin-search-results');
        var selection = root.querySelector('[data-fin-client-selection]');
        if (!input) { return; }

        function renderSelection() {
            if (!selection) return;
            selection.textContent = hidden.value ? (input.value || 'Client #' + hidden.value) : 'No client selected';
        }

        input.addEventListener('input', debounce(function () {
            var q = input.value.trim();
            if (!list) return;
            finPost('ajax/client-search.php', { q: q }).then(function (json) {
                list.innerHTML = '';
                if (!json.clients.length) {
                    list.innerHTML = '<div class="result-item"><span class="fin-muted">No clients found.</span></div>';
                }
                json.clients.forEach(function (c) {
                    var div = document.createElement('div');
                    div.className = 'result-item';
                    div.innerHTML = '<span><strong>' + esc(c.name) + '</strong>' +
                        '<div><small>' + esc(c.phone || '') + (c.email ? ' &middot; ' + esc(c.email) : '') + '</small></div></span>' +
                        '<span class="fin-muted">#' + c.client_id + '</span>';
                    div.addEventListener('click', function () {
                        input.value = c.name;
                        hidden.value = c.client_id;
                        list.classList.remove('show');
                        renderSelection();
                        root.dispatchEvent(new CustomEvent('fin:client-changed', { detail: { client_id: c.client_id } }));
                    });
                    list.appendChild(div);
                });
                list.classList.add('show');
            }).catch(function () {});
        }, 250));

        input.addEventListener('click', function () { if (list) list.classList.add('show'); });
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) { if (list) list.classList.remove('show'); }
        });
        renderSelection();
    }

    /* --------------------------------------------------------------
       Invoice search dropdown (amount, balance shown)
       Expects: [data-fin-invoice-search], helpers [data-fin-invoice-id],
       [data-fin-invoice-balance], [data-fin-invoice-total]
       -------------------------------------------------------------- */
    function bindInvoiceSearch(root) {
        var input = root.querySelector('[data-fin-invoice-search]');
        var hidden = root.querySelector('[data-fin-invoice-id]');
        var list = root.querySelector('.fin-search-results');
        var balanceEl = root.querySelector('[data-fin-invoice-balance]');
        var balanceMax = root.querySelector('[data-fin-invoice-balance-max]');
        var filters = [];
        Array.prototype.forEach.call(root.querySelectorAll('[data-fin-invoice-filter]'), function (el) {
            filters.push({ name: el.name, value: el.value });
        });
        if (!input) { return; }

        function renderInfo() {
            if (!hidden || !hidden.value) { return; }
            if (balanceMax && balanceEl) {
                balanceEl.value = balanceMax.value;
            }
            var ev = new CustomEvent('fin:invoice-selected', { detail: { invoice_id: hidden.value, balance: balanceMax ? balanceMax.value : '' } });
            root.dispatchEvent(ev);
        }

        input.addEventListener('input', debounce(function () {
            var q = input.value.trim();
            if (!list) return;
            var params = { q: q };
            filters.forEach(function (f) { params[f.name] = f.value; });
            finPost('ajax/invoice-search.php', params).then(function (json) {
                list.innerHTML = '';
                if (!json.invoices.length) {
                    list.innerHTML = '<div class="result-item"><span class="fin-muted">No invoices found.</span></div>';
                }
                json.invoices.forEach(function (inv) {
                    var badge = inv.payment_status === 'paid' ? 'Paid' :
                            inv.payment_status === 'partially_paid' ? 'Partial' : 'Unpaid';
                    var div = document.createElement('div');
                    div.className = 'result-item';
                    div.innerHTML = '<span><strong>' + esc(inv.invoice_number) + '</strong>' +
                        '<div><small>' + esc(inv.client_name) + ' &middot; ' + badge + '</small></div></span>' +
                        '<span class="fin-muted">Balance ' + esc(inv.balance) + '</span>';
                    div.addEventListener('click', function () {
                        input.value = inv.invoice_number;
                        hidden.value = inv.invoice_id;
                        if (balanceMax) balanceMax.value = inv.balance;
                        if (balanceEl) balanceEl.value = inv.balance;
                        list.classList.remove('show');
                        renderInfo();
                    });
                    list.appendChild(div);
                });
                list.classList.add('show');
            }).catch(function () {});
        }, 250));

        input.addEventListener('focus', function () { if (list) list.classList.add('show'); });
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) { if (list) list.classList.remove('show'); }
        });
    }

    /* --------------------------------------------------------------
       Autocomplete service rows (unit price snapshots from DB)
       -------------------------------------------------------------- */
    function bindServiceRow(row) {
        var serviceSelect = row.querySelector('[data-fin-service]');
        var priceInput = row.querySelector('[data-fin-price]');
        var qtyInput = row.querySelector('[data-fin-qty]');
        var discountInput = row.querySelector('[data-fin-line-discount]');
        if (!serviceSelect) return;

        serviceSelect.addEventListener('change', function () {
            var opt = serviceSelect.selectedOptions[0];
            if (opt && opt.dataset && opt.dataset.price && priceInput) {
                priceInput.value = parseFloat(opt.dataset.price).toFixed(2);
            }
        });
        if (priceInput) {
            priceInput.addEventListener('input', bindServiceRow.recalc);
        }
        if (qtyInput) qtyInput.addEventListener('input', bindServiceRow.recalc);
        if (discountInput) discountInput.addEventListener('input', bindServiceRow.recalc);
    }
    bindServiceRow.recalc = function () {
        var rows = document.querySelectorAll('.fin-item-row');
        var subtotal = 0; var discount = 0; var tax = 0;
        var taxRate = parseFloat((document.querySelector('[data-fin-tax-rate]') || {}).value || 0);
        var invoiceDiscount = parseFloat((document.querySelector('[data-fin-invoice-discount]') || {}).value || 0);

        rows.forEach(function (row) {
            var price = parseFloat((row.querySelector('[data-fin-price]') || {}).value || 0);
            var qty = parseInt((row.querySelector('[data-fin-qty]') || {}).value || 1, 10);
            var disc = parseFloat((row.querySelector('[data-fin-line-discount]') || {}).value || 0);
            if (isNaN(price) || price < 0) price = 0;
            if (isNaN(qty) || qty < 1) qty = 1;
            if (isNaN(disc) || disc < 0) disc = 0;
            var raw = price * qty;
            var lineDisc = Math.min(disc, raw);
            var taxable = Math.max(0, raw - lineDisc);
            var lineTax = taxable * (taxRate / 100);
            var lineTotal = raw - lineDisc + lineTax;
            subtotal += raw; discount += lineDisc; tax += lineTax;
            var totalEl = row.querySelector('[data-fin-line-total]');
            if (totalEl) totalEl.value = lineTotal.toFixed(2);
        });

        var disc = Math.min(invoiceDiscount, subtotal);
        var total = subtotal - disc + tax;
        setText('[data-fin-sum-subtotal]', subtotal.toFixed(2));
        setText('[data-fin-sum-discount]', disc.toFixed(2));
        setText('[data-fin-sum-tax]', tax.toFixed(2));
        setText('[data-fin-sum-total]', total.toFixed(2));
    };
    function setText(sel, v) {
        var el = document.querySelector(sel);
        if (el) el.textContent = v;
    }
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    /* --------------------------------------------------------------
       Bind everything on load
       -------------------------------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-fin-client-search-root]').forEach(bindClientSearch);
        document.querySelectorAll('[data-fin-invoice-search-root]').forEach(bindInvoiceSearch);
        document.querySelectorAll('[data-fin-add-row]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var template = document.querySelector('[data-fin-row-template]');
                var container = document.querySelector('[data-fin-rows]');
                if (!template || !container) return;
                var frag = template.content.cloneNode(true);
                container.appendChild(frag);
                var rows = container.querySelectorAll('.fin-item-row');
                var last = rows[rows.length - 1];
                if (last) bindServiceRow(last);
                bindServiceRow.recalc();
            });
        });
        document.querySelectorAll('[data-fin-remove-row]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var row = btn.closest('.fin-item-row');
                if (row && row.parentElement.querySelectorAll('.fin-item-row').length > 1) {
                    row.remove();
                    bindServiceRow.recalc();
                }
            });
        });
        bindServiceRow.recalc();
    });
})();