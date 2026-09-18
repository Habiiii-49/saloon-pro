/* ============================================================
   ELEGANCE SALON - RECEPTIONIST JS
   Part 3
   ============================================================ */
(function () {
    'use strict';

    var USER = {
        csrf: function () {
            var el = document.querySelector('meta[name="user-csrf"]');
            return el ? el.getAttribute('content') : '';
        },
        url: function (path) {
            var el = document.querySelector('meta[name="site-url"]');
            return (el ? el.getAttribute('content') : '') + '/' + path;
        },
        sidebarOpen: function () { document.body.classList.add('sidebar-open'); },
        sidebarClose: function () { document.body.classList.remove('sidebar-open'); },
        toggleCollapse: function () { document.body.classList.toggle('sidebar-collapsed'); },
        qs: function (s, c) { return (c || document).querySelector(s); },
        qsa: function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); }
    };
    window.USER = USER;

    /* Friendly fetch wrapper */
    function postJSON(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(data).toString()
        }).then(function (r) { return r.json(); });
    }

    function getJSON(url) {
        return fetch(url).then(function (r) { return r.json(); });
    }

    /* ============================================================
       SIDEBAR
       ============================================================ */
    document.addEventListener('DOMContentLoaded', function () {

        var toggle = document.querySelector('[data-user-toggle]');
        if (toggle) {
            toggle.addEventListener('click', function () {
                if (window.innerWidth <= 992) USER.sidebarOpen();
                else USER.toggleCollapse();
            });
        }

        var overlay = document.querySelector('.sidebar-overlay');
        if (overlay) overlay.addEventListener('click', USER.sidebarClose);

        USER.qsa('[data-user-close]').forEach(function (btn) {
            btn.addEventListener('click', USER.sidebarClose);
        });

        USER.qsa('.sidebar-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 992) USER.sidebarClose();
            });
        });

        /* ---------- Sidebar clock ---------- */
        var clock = document.getElementById('sidebarClock');
        if (clock) {
            function tick() {
                var d = new Date();
                var h = d.getHours() % 12 || 12;
                var m = d.getMinutes().toString().padStart(2, '0');
                var ap = d.getHours() >= 12 ? 'PM' : 'AM';
                clock.textContent = h + ':' + m + ' ' + ap;
            }
            tick();
            setInterval(tick, 30000);
        }

        /* ---------- Auto-dismiss alerts ---------- */
        USER.qsa('.alert-admin').forEach(function (alertEl) {
            var closeBtn = alertEl.querySelector('.alert-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () { alertEl.remove(); });
            }
            setTimeout(function () {
                if (alertEl && alertEl.parentNode) {
                    alertEl.style.transition = 'opacity 0.4s ease';
                    alertEl.style.opacity = '0';
                    setTimeout(function () { alertEl.remove(); }, 400);
                }
            }, 6000);
        });

        /* ---------- Count-up animation ---------- */
        USER.qsa('[data-count]').forEach(function (el) {
            var target = parseFloat(el.getAttribute('data-count')) || 0;
            var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
            var prefix = el.getAttribute('data-prefix') || '';
            var suffix = el.getAttribute('data-suffix') || '';
            var duration = 800;
            var start = null;
            function step(ts) {
                if (!start) start = ts;
                var p = Math.min((ts - start) / duration, 1);
                var v = target * (1 - Math.pow(1 - p, 3));
                el.textContent = prefix + v.toLocaleString('en-US', {
                    minimumFractionDigits: decimals, maximumFractionDigits: decimals
                }) + suffix;
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });

        /* ---------- Global search ---------- */
        var searchInput = document.getElementById('globalSearchInput');
        var searchBox = document.getElementById('globalSearchBox');
        if (searchInput && searchBox) {
            var searchTimer = null;
            var resultsBox = document.getElementById('globalSearchResults');
            var spinner = searchBox.querySelector('.search-spinner');

            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                var q = this.value.trim();
                if (q.length < 2) {
                    resultsBox.classList.remove('open');
                    return;
                }
                if (spinner) spinner.classList.remove('d-none');
                searchTimer = setTimeout(function () {
                    getJSON(USER.url('user/ajax/global-search.php?q=' + encodeURIComponent(q))).then(function (data) {
                        if (spinner) spinner.classList.add('d-none');
                        renderSearchResults(resultsBox, data, q);
                    }).catch(function () {
                        if (spinner) spinner.classList.add('d-none');
                        resultsBox.innerHTML = '<div class="search-result-empty">Search unavailable.</div>';
                        resultsBox.classList.add('open');
                    });
                }, 350);
            });

            searchInput.addEventListener('focus', function () {
                if (this.value.trim().length >= 2) resultsBox.classList.add('open');
                else resultsBox.classList.remove('open');
            });

            document.addEventListener('click', function (e) {
                if (!searchBox.contains(e.target)) resultsBox.classList.remove('open');
            });

            function renderSearchResults(box, data, q) {
                var html = '';
                var groups = [
                    { label: 'Clients', key: 'clients', icon: 'fa-users', base: 'user/client-view.php?client_id=' },
                    { label: 'Appointments', key: 'appointments', icon: 'fa-calendar-check', base: 'user/appointments.php?view=' },
                    { label: 'Invoices', key: 'invoices', icon: 'fa-file-invoice-dollar', base: 'user/invoice-view.php?invoice_id=' }
                ];
                var any = false;
                groups.forEach(function (g) {
                    var rows = data[g.key] || [];
                    if (!rows.length) return;
                    any = true;
                    html += '<div class="search-result-group">' + g.label + '</div>';
                    rows.forEach(function (r) {
                        html += '<a class="search-result-item" href="' + USER.url(g.base + r.id) + '">' +
                            '<i class="fas ' + g.icon + '"></i>' +
                            '<div>' + r.title + '<small>' + r.sub + '</small></div>' +
                            '</a>';
                    });
                });
                if (!any) {
                    html = '<div class="search-result-empty">No results for &ldquo;' +
                        (q || '').replace(/&/g, '&amp;').replace(/</g, '&lt;') + '&rdquo;</div>';
                }
                box.innerHTML = html;
                box.classList.add('open');
            }
        }

        /* ---------- Generic table search ---------- */
        USER.qsa('[data-table-search]').forEach(function (input) {
            var table = document.querySelector(input.getAttribute('data-table-search'));
            if (!table) return;
            input.addEventListener('input', function () {
                var q = this.value.toLowerCase();
                USER.qsa('tbody tr', table).forEach(function (row) {
                    row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
                });
            });
        });

        /* ---------- Confirmation dialog ---------- */
        USER.confirm = function (options) {
            return new Promise(function (resolve) {
                var title = options.title || 'Are you sure?';
                var message = options.message || 'This action cannot be undone.';
                var confirmText = options.confirmText || 'Confirm';
                var cancelText = options.cancelText || 'Cancel';
                var danger = options.danger !== false;

                var overlay = document.createElement('div');
                overlay.className = 'confirm-overlay';
                overlay.innerHTML =
                    '<div class="confirm-dialog" role="dialog" aria-modal="true">' +
                        '<div class="confirm-icon ' + (danger ? 'danger' : 'safe') + '"><i class="fas ' + (danger ? 'fa-triangle-exclamation' : 'fa-circle-check') + '"></i></div>' +
                        '<h5></h5><p></p>' +
                        '<div class="confirm-actions">' +
                            '<button type="button" class="btn-user btn-outline" data-role="cancel"></button>' +
                            '<button type="button" class="btn-user ' + (danger ? 'btn-danger-outline' : 'btn-cyan') + '" data-role="ok"></button>' +
                        '</div>' +
                    '</div>';
                overlay.querySelector('h5').textContent = title;
                overlay.querySelector('p').textContent = message;
                overlay.querySelector('[data-role="cancel"]').textContent = cancelText;
                overlay.querySelector('[data-role="ok"]').textContent = confirmText;

                function close(result) {
                    overlay.classList.remove('show');
                    document.body.classList.remove('scroll-lock');
                    setTimeout(function () { overlay.remove(); }, 200);
                    resolve(result);
                }
                overlay.addEventListener('click', function (e) { if (e.target === overlay) close(false); });
                overlay.querySelector('[data-role="cancel"]').addEventListener('click', function () { close(false); });
                overlay.querySelector('[data-role="ok"]').addEventListener('click', function () { close(true); });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(false); }, { once: true });

                document.body.appendChild(overlay);
                document.body.classList.add('scroll-lock');
                requestAnimationFrame(function () { overlay.classList.add('show'); });
            });
        };

        /* ---------- Auto-subscribe data-confirm buttons (POST forms) ---------- */
        USER.qsa('[data-confirm-post]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var cfg = {};
                try { cfg = JSON.parse(btn.getAttribute('data-confirm-post') || '{}'); } catch (err) { /* ignore */ }
                USER.confirm(cfg).then(function (ok) {
                    if (ok) {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = btn.getAttribute('data-action');
                        ['csrf_token', 'id'].forEach(function (n) {
                            var v = btn.getAttribute('data-' + n);
                            if (v !== null) {
                                var inp = document.createElement('input');
                                inp.type = 'hidden'; inp.name = n; inp.value = v;
                                form.appendChild(inp);
                            }
                        });
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });

        USER.qsa('[data-confirm-form]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var cfg = {};
                try { cfg = JSON.parse(btn.getAttribute('data-confirm-form') || '{}'); } catch (err) { /* ignore */ }
                cfg.danger = true;
                var form = btn.closest('form');
                if (!form) return;
                e.preventDefault();
                USER.confirm(cfg).then(function (ok) { if (ok) form.submit(); });
            });
        });

        /* ---------- Password visibility toggles ---------- */
        USER.qsa('[data-pw-toggle]').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                var input = document.getElementById(toggle.getAttribute('data-pw-toggle'));
                if (!input) return;
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.classList.toggle('fa-eye', show);
                toggle.classList.toggle('fa-eye-slash', !show);
            });
        });

        /* ---------- Client search (appointment wizard) ---------- */
        var clientSearchInput = document.getElementById('clientSearchInput');
        var clientResults = document.getElementById('clientResults');
        if (clientSearchInput && clientResults) {
            var cTimer = null;
            clientSearchInput.addEventListener('input', function () {
                clearTimeout(cTimer);
                var q = this.value.trim();
                if (q.length < 2) { clientResults.innerHTML = ''; return; }
                cTimer = setTimeout(function () {
                    getJSON(USER.url('user/ajax/search-clients.php?q=' + encodeURIComponent(q))).then(function (data) {
                        if (!data.clients.length) {
                            clientResults.innerHTML = '<div class="search-result-empty">No matching clients. <a href="' + USER.url('user/client-add.php') + '">+ Add New Client</a></div>';
                            return;
                        }
                        var html = '';
                        data.clients.forEach(function (c) {
                            html += '<div class="client-result" data-id="' + c.id + '" data-name="' +
                                (c.name || '').replace(/"/g, '&quot;') +
                                '" data-phone="' + (c.phone || '') + '" data-email="' + (c.email || '') + '">' +
                                '<span class="cr-avatar">' + (c.initial || '?') + '</span>' +
                                '<div class="cr-main"><div class="cr-name">' + c.name + '</div>' +
                                '<div class="cr-sub">' + (c.phone ? c.phone + ' &middot; ' : '') + (c.email || '') + '</div></div>' +
                                '<i class="fas fa-chevron-right ms-auto" style="color:var(--text-muted);font-size:.8rem"></i>' +
                                '</div>';
                        });
                        clientResults.innerHTML = html;
                        USER.qsa('.client-result', clientResults).forEach(function (row) {
                            row.addEventListener('click', function () {
                                selectWizardClient(row);
                                clientResults.innerHTML = '';
                                clientSearchInput.value = '';
                            });
                        });
                    }).catch(function () {
                        clientResults.innerHTML = '<div class="search-result-empty">Search unavailable.</div>';
                    });
                }, 300);
            });
        }

        function selectWizardClient(row) {
            var selectedBox = document.getElementById('selectedClientBox');
            var hidden = document.getElementById('wizardClientId');
            if (!selectedBox || !hidden) return;
            hidden.value = row.getAttribute('data-id');
            selectedBox.innerHTML =
                '<span class="selected-client-chip">' +
                '<span>' + (row.getAttribute('data-name') || '') + '</span>' +
                '<button type="button" class="btn-x" aria-label="Change client" title="Change client"><i class="fas fa-xmark"></i></button>' +
                '</span>';
            var xbtn = selectedBox.querySelector('.btn-x');
            if (xbtn) xbtn.addEventListener('click', clearWizardClient);
        }

        function clearWizardClient() {
            var hidden = document.getElementById('wizardClientId');
            var box = document.getElementById('selectedClientBox');
            if (hidden) hidden.value = '';
            if (box) box.innerHTML = '';
        }

        /* ---------- Available slots (AJAX) ---------- */
        var slotDeps = document.querySelectorAll('[data-slot-deps]');
        function loadSlots() {
            var svc = document.getElementById('slotServiceId');
            var stf = document.getElementById('slotStaffId');
            var dat = document.getElementById('slotDate');
            var box = document.getElementById('slotResults');
            var apptId = document.getElementById('slotAppointmentId');
            if (!box) return;
            var progressed = [svc, stf, dat].every(function (el) { return el && el.value !== '' && el.value !== null; });
            if (!progressed) {
                box.innerHTML = '<div class="slot-empty"><i class="fas fa-circle-info"></i> Select service, stylist and date to see available slots.</div>';
                return;
            }
            box.dataset.loading = '1';
            box.innerHTML = '<div class="slot-loading"><i class="fas fa-spinner fa-spin"></i> Checking availability...</div>';
            var params = [
                'service_id=' + encodeURIComponent(svc.value),
                'staff_id=' + encodeURIComponent(stf.value),
                'date=' + encodeURIComponent(dat.value)
            ];
            if (apptId && apptId.value) params.push('appointment_id=' + encodeURIComponent(apptId.value));
            getJSON(USER.url('user/ajax/available-slots.php?' + params.join('&'))).then(function (data) {
                delete box.dataset.loading;
                if (!data.slots.length) {
                    box.innerHTML = '<div class="slot-empty"><i class="fas fa-calendar-xmark"></i> No available slots for this stylist on the selected date.</div>';
                    return;
                }
                var html = '';
                data.slots.forEach(function (s) {
                    html += '<button type="button" class="slot-btn" data-slot="' + s + '" data-label="' + s2l(s) + '">' + s2l(s) + '</button>';
                });
                box.innerHTML = html;
                bindSlotButtons(box);
            }).catch(function () {
                delete box.dataset.loading;
                box.innerHTML = '<div class="slot-empty"><i class="fas fa-triangle-exclamation"></i> Could not load slots.</div>';
            });
        }
        function s2l(t) {
            var p = t.split(':');
            var h = parseInt(p[0], 10) % 12 || 12;
            var m = p[1];
            return h + ':' + m + (parseInt(p[0], 10) >= 12 ? ' PM' : ' AM');
        }
        function bindSlotButtons(box) {
            USER.qsa('.slot-btn', box).forEach(function (btn) {
                btn.addEventListener('click', function () {
                    USER.qsa('.slot-btn', box).forEach(function (b) { b.classList.remove('selected'); });
                    btn.classList.add('selected');
                    var hidden = document.getElementById('wizardTime');
                    if (hidden) hidden.value = btn.getAttribute('data-slot');
                });
            });
        }

        if (slotDeps.length) {
            USER.qsa('[data-slot-deps]').forEach(function (el) {
                el.addEventListener('change', loadSlots);
            });
            loadSlots();
        }

        /* ---------- Booking wizard (steps / selections / quick client) ---------- */
        var apptForm = document.getElementById('appointmentForm');
        if (apptForm) {
            var stepMax = 6;
            var paneEls = {};
            USER.qsa('.panel.wizard-pane').forEach(function (p) { paneEls[p.getAttribute('data-step')] = p; });
            var stepEls = {};
            USER.qsa('.wizard-step').forEach(function (s) { stepEls[s.getAttribute('data-step')] = s; });

            var currentStep = 1;
            var SEL = { '1': 'client_id', '2': 'service_id', '3': 'staff_id', '4': 'appointment_date', '5': 'appointment_time' };

            function setStep(n, skipAuto) {
                currentStep = n;
                for (var k in paneEls) { paneEls[k].classList.toggle('active', k === String(n)); }
                for (var k2 in stepEls) {
                    stepEls[k2].classList.toggle('active', k2 === String(n));
                    stepEls[k2].classList.toggle('done', parseInt(k2, 10) < n);
                }
                if (n === 6) renderConfirmSummary();
            }

            function requiredStepFilled(step) {
                var key = SEL[step];
                if (!key) return true;
                var el = document.getElementById(key === 'client_id' ? 'wizardClientId' : key === 'service_id' ? 'slotServiceId' : key === 'staff_id' ? 'slotStaffId' : key === 'appointment_date' ? 'slotDate' : 'wizardTime');
                return el && el.value !== '' && el.value !== null;
            }

            /* handle back buttons */
            USER.qsa('[data-wz-prev]').forEach(function (b) { b.addEventListener('click', function () { setStep(Math.max(1, currentStep - 1)); }); });

            /* advance to service once a valid client is selected */
            var realClientId = document.getElementById('wizardClientId');
            if (realClientId) {
                realClientId.addEventListener('change', function () { if (this.value) setStep(2); });
            }

            /* service selection */
            USER.qsa('.service-choice').forEach(function (b) {
                b.addEventListener('click', function () {
                    USER.qsa('.service-choice').forEach(function (x) { x.classList.remove('selected'); });
                    b.classList.add('selected');
                    document.getElementById('slotServiceId').value = b.getAttribute('data-service');
                    var box = document.getElementById('selectedServiceBox');
                    box.innerHTML = '<span class="selected-client-chip">' +
                        '<span>' + esc(b.getAttribute('data-svc-name')) + ' &middot; ' + esc(b.getAttribute('data-svc-price')) + ' &middot; ' + esc(b.getAttribute('data-svc-dur')) + ' min</span>' +
                        '</span>';
                    setStep(3);
                });
            });

            /* stylist selection */
            USER.qsa('.stylist-choice').forEach(function (b) {
                b.addEventListener('click', function () {
                    if (b.disabled) return;
                    USER.qsa('.stylist-choice').forEach(function (x) { x.classList.remove('selected'); });
                    b.classList.add('selected');
                    document.getElementById('slotStaffId').value = b.getAttribute('data-staff');
                    var box = document.getElementById('selectedStylistBox');
                    box.innerHTML = '<span class="selected-client-chip"><span>' + esc(b.getAttribute('data-stf-name')) + ' &middot; ' + esc(b.getAttribute('data-stf-specialty')) + '</span></span>';
                    setStep(4);
                });
            });

            /* date -> load slots -> when slots appear, step 5; once slot chosen, allow confirm */
            document.getElementById('slotDate').addEventListener('change', function () {
                if (this.value) setStep(5);
            });

            /* clicks inside slot results advance to confirm when a slot is picked */
            var slotResultsBox = document.getElementById('slotResults');
            if (slotResultsBox) {
                slotResultsBox.addEventListener('click', function (e) {
                    if (e.target.closest('.slot-btn')) setStep(6);
                });
            }

            /* confirm summary */
            function renderConfirmSummary() {
                var clientName = (function () {
                    var c = document.getElementById('selectedClientBox');
                    if (c && c.textContent) return c.textContent.trim().replace(/\s*✕.*/, '');
                    return '—';
                })();
                var svc = document.getElementById('selectedServiceBox');
                var stf = document.getElementById('selectedStylistBox');
                var dateEl = document.getElementById('slotDate');
                var timeEl = document.getElementById('wizardTime');
                var notesEl = document.getElementById('notes');

                var timeLabel = timeEl && timeEl.value ? slotLabel(timeEl.value) : '—';
                var box = document.getElementById('confirmSummary');
                box.innerHTML =
                    '<div class="detail-item"><div class="dl-label">Client</div><div class="dl-value">' + (clientName || '—') + '</div></div>' +
                    '<div class="detail-item"><div class="dl-label">Service</div><div class="dl-value">' + (svc ? svc.textContent.trim().replace(/\s*✕.*/, '') : '—') + '</div></div>' +
                    '<div class="detail-item"><div class="dl-label">Stylist</div><div class="dl-value">' + (stf ? stf.textContent.trim().replace(/\s*✕.*/, '') : '—') + '</div></div>' +
                    '<div class="detail-item"><div class="dl-label">Date</div><div class="dl-value">' + (dateEl && dateEl.value ? prettyDate(dateEl.value) : '—') + '</div></div>' +
                    '<div class="detail-item"><div class="dl-label">Time</div><div class="dl-value">' + timeLabel + '</div></div>';
            }

            function prettyDate(d) {
                var dt = new Date(d + 'T00:00:00');
                return isNaN(dt) ? d : dt.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            }
            function slotLabel(t) {
                var p = t.split(':');
                var h = parseInt(p[0], 10) % 12 || 12;
                return h + ':' + p[1] + (parseInt(p[0], 10) >= 12 ? ' PM' : ' AM');
            }

            /* quick add walk-in client */
            var quickBtn = document.getElementById('quickAddClientBtn');
            if (quickBtn) {
                quickBtn.addEventListener('click', function () {
                    var f = document.getElementById('quickNewFirstName');
                    var l = document.getElementById('quickNewLastName');
                    var ph = document.getElementById('quickNewPhone');
                    if (!f || !f.value.trim()) { f && f.focus(); return; }
                    postJSON(USER.url('user/ajax/quick-client.php'), {
                        first_name: f.value.trim(),
                        last_name: (l ? l.value.trim() : ''),
                        phone: (ph ? ph.value.trim() : ''),
                        csrf_token: apptForm.querySelector('[name=csrf_token]').value
                    }).then(function (res) {
                        if (res.ok) {
                            document.getElementById('wizardClientId').value = res.client_id;
                            var box = document.getElementById('selectedClientBox');
                            box.innerHTML = '<span class="selected-client-chip"><span>' + esc(res.name) + '</span></span>';
                            setStep(2);
                            if (f) f.value = ''; if (l) l.value = ''; if (ph) ph.value = '';
                        } else {
                            alert(res.error || 'Could not add the client.');
                        }
                    }).catch(function () { alert('Could not add the client.'); });
                });
            }
        }

        /* ---------- Appointment status change (AJAX) ---------- */
        USER.qsa('[data-status-select]').forEach(function (sel) {
            sel.addEventListener('change', function () {
                var url = this.getAttribute('data-status-url') || USER.url('user/ajax/appointment-status.php');
                var id = this.getAttribute('data-id');
                var token = this.getAttribute('data-csrf');
                var loader = this.closest('.cell-main') || this;
                postJSON(url, { id: id, status: this.value, csrf_token: token }).then(function (res) {
                    if (res.ok) {
                        window.location.reload();
                    } else {
                        alert(res.error || 'Status update failed.');
                        window.location.reload();
                    }
                }).catch(function () {
                    alert('Status update failed. Please try again.');
                    window.location.reload();
                });
            });
        });

        /* ---------- Notification mark-as-read ---------- */
        USER.qsa('[data-mark-read]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-mark-read');
                var token = btn.getAttribute('data-csrf');
                postJSON(USER.url('user/ajax/notifications.php'), { action: 'mark_read', id: id, csrf_token: token }).then(function () {
                    if (btn.classList.contains('notif-unsaved')) { /* noop */ }
                    btn.closest('.notif-page-item')?.classList.remove('unread');
                });
            });
        });

        USER.qsa('[data-mark-all]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var token = btn.getAttribute('data-csrf');
                postJSON(USER.url('user/ajax/notifications.php'), { action: 'mark_all', csrf_token: token }).then(function () {
                    var dot = document.getElementById('notifDotTop');
                    if (dot) dot.remove();
                    USER.qsa('[data-mark-read]').forEach(function (b) {
                        b.closest('.notif-page-item') && b.closest('.notif-page-item').classList.remove('unread');
                    });
                });
            });
        });

        /* ---------- Calendar navigation + loading ---------- */
        var calGridEl = document.getElementById('calendarGrid');
        if (calGridEl) {
            window.EleganceCalendar = {
                month: parseInt(calGridEl.getAttribute('data-month') || '0', 10),
                year: parseInt(calGridEl.getAttribute('data-year') || '0', 10)
            };
        }

        USER.qsa('[data-cal-nav]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var dir = parseInt(btn.getAttribute('data-cal-nav'), 10);
                if (!window.EleganceCalendar) return;
                var m = window.EleganceCalendar.month + dir;
                var y = window.EleganceCalendar.year;
                if (m < 0) { m = 11; y--; }
                if (m > 11) { m = 0; y++; }
                var grid = document.getElementById('calendarGrid');
                if (!grid) return;
                grid.classList.add('slot-loading');
                grid.innerHTML = '<div class="cal-legend"><span><i class="fas fa-spinner fa-spin"></i> Loading calendar...</span></div>';
                getJSON(USER.url('user/ajax/calendar-data.php?month=' + m + '&year=' + y)).then(function (data) {
                    window.EleganceCalendar.month = m;
                    window.EleganceCalendar.year = y;
                    document.getElementById('calendarTitle').textContent = data.title;
                    grid.innerHTML = data.html;
                    bindCalendarEvents();
                }).catch(function () {
                    grid.innerHTML = '<div class="table-empty"><i class="fas fa-triangle-exclamation"></i><p>Could not load calendar.</p></div>';
                });
            });
        });

        function bindCalendarEvents() {
            USER.qsa('[data-cal-event]').forEach(function (el) {
                el.addEventListener('click', function () {
                    window.location.href = USER.url('user/appointments.php?view=' + this.getAttribute('data-cal-event'));
                });
            });
        }
        if (window.EleganceCalendar) bindCalendarEvents();

        /* ---------- Print invoice ---------- */
        USER.qsa('[data-print-invoice]').forEach(function (btn) {
            btn.addEventListener('click', function () { window.print(); });
        });

        /* ---------- Appointment detail modal ---------- */
        var apptModalHost = document.getElementById('appointmentModal');
        USER.appointmentFromData = function (d) {
            if (!apptModalHost) return;
            var body = document.getElementById('appointmentModalBody');
            var footer = document.getElementById('appointmentModalFooter');
            if (!body || !footer) return;

            body.innerHTML =
                '<div class="detail-list">' +
                    '<div class="detail-item"><div class="dl-label">Client</div><div class="dl-value">' + esc(d.client) + '</div></div>' +
                    '<div class="detail-item"><div class="dl-label">Service</div><div class="dl-value">' + esc(d.service) + '</div></div>' +
                    '<div class="detail-item"><div class="dl-label">Stylist</div><div class="dl-value">' + esc(d.stylist) + '</div></div>' +
                    '<div class="detail-item"><div class="dl-label">Date</div><div class="dl-value">' + esc(d.date) + '</div></div>' +
                    '<div class="detail-item"><div class="dl-label">Time</div><div class="dl-value">' + esc(d.time) + '</div></div>' +
                    '<div class="detail-item"><div class="dl-label">Amount</div><div class="dl-value">' + esc(d.amount) + '</div></div>' +
                    '<div class="detail-item"><div class="dl-label">Status</div><div class="dl-value">' + esc(d.status) + '</div></div>' +
                    '<div class="detail-item"><div class="dl-label">Payment</div><div class="dl-value">' + esc(d.payment || '—') + '</div></div>' +
                '</div>' +
                '<div style="margin-top:1.2rem"><div class="dl-label" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.1em;color:var(--text-muted);margin-bottom:.3rem">Notes</div>' +
                '<div style="color:var(--text-secondary);font-size:.86rem">' + esc(d.notes || '—') + '</div></div>';

            footer.innerHTML = '';
            if (d.id) {
                footer.innerHTML += '<a href="' + USER.url('user/appointment-edit.php?id=' + d.id) + '" class="btn-user btn-outline"><i class="fas fa-pen-to-square"></i> Edit / Reschedule</a>';
            }
            footer.innerHTML += '<button type="button" class="btn-user btn-ghost" data-bs-dismiss="modal">Close</button>';

            var modal = bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(apptModalHost) : new bootstrap.Modal(apptModalHost);
            modal.show();
        };

        function esc(s) {
            return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        USER.qsa('[data-open-appointment]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                USER.appointmentFromData({
                    id: btn.getAttribute('data-id'),
                    client: btn.getAttribute('data-client'),
                    service: btn.getAttribute('data-service'),
                    stylist: btn.getAttribute('data-stylist'),
                    date: btn.getAttribute('data-date'),
                    time: btn.getAttribute('data-time'),
                    amount: btn.getAttribute('data-amount'),
                    status: btn.getAttribute('data-status'),
                    notes: btn.getAttribute('data-notes'),
                    payment: btn.getAttribute('data-payment')
                });
            });
        });
    });

})();