/* ============================================================
   ELEGANCE SALON - INVENTORY MODULE JS (PART 5)
   ============================================================ */
(function () {
    'use strict';

    var INV = window.InvApp = {};

    /** Grab CSRF token from the meta tag or a .csrf-value input. */
    INV.csrf = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : (window.INV_CSRF || '');
    };
    var setCsrf = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', window.INV_CSRF || meta.getAttribute('content'));
    };

    /** Small fetch wrapper returning a parsed JSON object. */
    INV.post = function (url, data) {
        var body = data || {};
        var fd = new FormData();
        Object.keys(body).forEach(function (k) { fd.append(k, body[k]); });
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (res) {
            return res.json().catch(function () { return { ok: false, error: 'Invalid server response.' }; });
        });
    };

    function money(n) {
        var v = parseFloat(n) || 0;
        return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /* ============================================================
       1. QUICK STOCK MODAL (products list / product view)
       ============================================================ */
    function openQuickStock(btn) {
        var modal = document.getElementById('quickStockModal');
        if (!modal) return;
        modal.querySelector('[name="product_id"]').value = btn.getAttribute('data-id');
        modal.querySelector('[name="action"]').value = btn.getAttribute('data-action') || 'stock_in';
        modal.querySelector('.quick-stock-title').textContent = btn.getAttribute('data-action') === 'stock_out'
            ? 'Stock Out' : 'Stock In';
        var nameEl = modal.querySelector('.quick-stock-product');
        if (nameEl) nameEl.textContent = btn.getAttribute('data-name') || '';
        var stockEl = modal.querySelector('.quick-stock-current');
        if (stockEl) stockEl.textContent = 'Current stock: ' + (btn.getAttribute('data-stock') || '0');
        var result = modal.querySelector('.quick-stock-result');
        if (result) result.innerHTML = '';
        var bootstrapModal = bootstrap.Modal.getOrCreateInstance(modal);
        bootstrapModal.show();
    }

    function submitQuickStock() {
        var modal = document.getElementById('quickStockModal');
        if (!modal) return;
        var fd = {};
        modal.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.name) fd[el.name] = el.value;
        });
        fd.csrf_token = INV.csrf();
        var result = modal.querySelector('.quick-stock-result');
        if (result) {
            result.innerHTML = '<div class="alert-admin info"><i class="fas fa-circle-info"></i><div>Updating stock...</div></div>';
        }
        var btn = modal.querySelector('[data-submit-stock]');
        var ctrl = INV.post('ajax/stock.php', fd).then(function (r) {
            if (r.ok) {
                if (result) result.innerHTML = '<div class="alert-admin success"><i class="fas fa-circle-check"></i><div>' + (r.message || 'Stock updated') + '</div></div>';
                setTimeout(function () { window.location.reload(); }, 900);
            } else {
                if (result) result.innerHTML = '<div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div>' + (r.error || r.message || 'Update failed.') + '</div></div>';
                if (btn) btn.disabled = false;
            }
        }).catch(function () {
            if (result) result.innerHTML = '<div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div>Unable to reach server.</div></div>';
            if (btn) btn.disabled = false;
        });
        if (ctrl && btn) btn.disabled = true;
    }

    /* ============================================================
       2. PURCHASE ORDER BUILDER (purchase-order-add / edit)
       ============================================================ */
    function poRowHTML(product) {
        return '' +
            '<tr class="po-item-row">' +
            '<td class="po-item-name">' +
            '  <input type="text" class="form-control-admin po-product-search" placeholder="Type to search product..." autocomplete="off" value="' + (product ? product.item_name : '') + '">' +
            '  <input type="hidden" name="product_id[]" class="po-product-id" value="' + (product ? product.inventory_id : '') + '">' +
            '  <div class="po-search-results admin-card" style="display:none;"></div>' +
            '</td>' +
            '<td><input type="number" class="form-control-admin po-qty" name="quantity[]" min="1" value="1"></td>' +
            '<td><input type="number" class="form-control-admin po-cost" name="unit_cost[]" min="0" step="0.01" value="0.00"></td>' +
            '<td class="inv-value po-line-total">$0.00</td>' +
            '<td><button type="button" class="po-remove-row" title="Remove line"><i class="fas fa-xmark"></i></button></td>' +
            '</tr>';
    }

    function recalcPOTotals() {
        var rowTotal = 0;
        document.querySelectorAll('#poItemsTable tbody .po-item-row').forEach(function (row) {
            var qty = parseFloat(row.querySelector('.po-qty').value) || 0;
            var cost = parseFloat(row.querySelector('.po-cost').value) || 0;
            var total = qty * cost;
            var cell = row.querySelector('.po-line-total');
            if (cell) cell.textContent = '$' + money(total);
            rowTotal += total;
        });
        var tax = parseFloat(document.querySelector('[name="tax"]').value) || 0;
        var discount = parseFloat(document.querySelector('[name="discount"]').value) || 0;
        var grand = rowTotal + tax - discount;
        if (grand < 0) grand = 0;
        var subtotalEl = document.querySelector('.po-subtotal');
        var grandEl = document.querySelector('.po-grand-total');
        if (subtotalEl) subtotalEl.textContent = '$' + money(rowTotal);
        if (grandEl) grandEl.textContent = '$' + money(grand);
    }

    function attachPOBuilder() {
        var table = document.getElementById('poItemsTable');
        if (!table) return;

        var hiddenProductCache = {};

        function attachRow(row) {
            var search = row.querySelector('.po-product-search');
            var idInput = row.querySelector('.po-product-id');
            var resultsBox = row.querySelector('.po-search-results');
            var timer = null;

            search.addEventListener('input', function () {
                var q = search.value.trim();
                clearTimeout(timer);
                if (q.length < 2) { resultsBox.style.display = 'none'; return; }
                timer = setTimeout(function () {
                    INV.post('ajax/product-search.php', { q: q, csrf_token: INV.csrf() }).then(function (r) {
                        if (!r.ok) { resultsBox.style.display = 'none'; return; }
                        var items = r.data || [];
                        if (!items.length) {
                            resultsBox.innerHTML = '<div class="empty-state" style="padding:0.8rem;"><i class="fas fa-box-open"></i><p style="margin:0;">No products found.</p></div>';
                            resultsBox.style.display = 'block';
                            return;
                        }
                        var html = items.map(function (p) {
                            var name = p.item_name.replace(/"/g, '&quot;');
                            return '<a href="#" class="po-search-item" data-id="' + p.inventory_id + '" data-name="' + name + '" data-stock="' + p.quantity + '">' +
                                   '<i class="fas fa-box"></i> ' + name + ' <small>SKU: ' + (p.sku || '—') + ' · Stock: ' + p.quantity + '</small></a>';
                        }).join('');
                        resultsBox.innerHTML = '<div class="po-search-list">' + html + '</div>';
                        resultsBox.style.display = 'block';
                    });
                }, 300);
            });

            row.addEventListener('click', function (e) {
                var item = e.target.closest('.po-search-item');
                if (!item) return;
                e.preventDefault();
                search.value = item.getAttribute('data-name');
                idInput.value = item.getAttribute('data-id');
                hiddenProductCache[item.getAttribute('data-id')] = item.getAttribute('data-name');
                resultsBox.style.display = 'none';
            });

            row.querySelector('.po-qty').addEventListener('input', recalcPOTotals);
            row.querySelector('.po-cost').addEventListener('input', recalcPOTotals);

            row.querySelector('.po-remove-row').addEventListener('click', function () {
                var allRows = table.querySelectorAll('tbody .po-item-row');
                if (allRows.length <= 1) {
                    row.querySelector('.po-qty').value = 1;
                    row.querySelector('.po-cost').value = '0.00';
                    search.value = '';
                    idInput.value = '';
                    recalcPOTotals();
                    return;
                }
                row.remove();
                recalcPOTotals();
            });

            document.addEventListener('click', function (e) {
                if (!row.contains(e.target)) resultsBox.style.display = 'none';
            });
        }

        if (table.querySelectorAll('tbody .po-item-row').length === 0) {
            var tbody = table.querySelector('tbody');
            tbody.insertAdjacentHTML('beforeend', poRowHTML(null));
        }
        table.querySelectorAll('tbody .po-item-row').forEach(attachRow);

        var addBtn = document.querySelector('[data-add-po-line]');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var tbody = table.querySelector('tbody');
                tbody.insertAdjacentHTML('beforeend', poRowHTML(null));
                attachRow(tbody.lastElementChild);
                recalcPOTotals();
            });
        }

        ['[name="tax"]', '[name="discount"]'].forEach(function (sel) {
            var el = document.querySelector(sel);
            if (el) el.addEventListener('input', recalcPOTotals);
        });
        recalcPOTotals();
    }

    /* ============================================================
       3. INVENTORY NOTIFICATIONS (mark read / mark all)
       ============================================================ */
    function attachInvNotifications() {
        document.querySelectorAll('[data-inv-mark-read]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-inv-mark-read');
                INV.post('ajax/notifications.php', { action: 'read', id: id, csrf_token: INV.csrf() }).then(function (r) {
                    if (r.ok) {
                        var row = btn.closest('.notif-item');
                        if (row) row.classList.remove('unread');
                        btn.remove();
                    }
                });
            });
        });
        var markAll = document.querySelector('[data-inv-mark-all]');
        if (markAll) {
            markAll.addEventListener('click', function () {
                INV.post('ajax/notifications.php', { action: 'read_all', csrf_token: INV.csrf() }).then(function (r) {
                    if (r.ok) {
                        document.querySelectorAll('.inventory-notif-list .notif-item').forEach(function (row) {
                            row.classList.remove('unread');
                        });
                        document.querySelectorAll('.inventory-notif-list [data-inv-mark-read]').forEach(function (b) { b.remove(); });
                    }
                });
            });
        }
    }

    /* ============================================================
       4. SUPPLIER + PRODUCT QUICK-PICK (used in filters)
       ============================================================ */
    function attachAutoSubmitFilters() {
        document.querySelectorAll('[data-filter-autosubmit]').forEach(function (select) {
            select.addEventListener('change', function () {
                var url = new URL(window.location.href);
                url.searchParams.set(select.name, select.value);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            });
        });
    }

    /* ============================================================
       INIT
       ============================================================ */
    document.addEventListener('DOMContentLoaded', function () {
        if (window.INV_CSRF) setCsrf();

        // Quick stock buttons
        document.querySelectorAll('[data-quick-stock]').forEach(function (btn) {
            btn.addEventListener('click', function (e) { e.preventDefault(); openQuickStock(btn); });
        });
        var submitStock = document.querySelector('[data-submit-stock]');
        if (submitStock) submitStock.addEventListener('click', submitQuickStock);

        attachPOBuilder();
        attachInvNotifications();
        attachAutoSubmitFilters();

        // Print purchase order
        document.querySelectorAll('[data-print-doc]').forEach(function (btn) {
            btn.addEventListener('click', function () { window.print(); });
        });

        // Confirm-driven NAV (inventory notifications page uses plain POST forms mostly)
        document.querySelectorAll('select[data-bs-toggle]').forEach(function () { /* no-op placeholder */ });
    });
})();