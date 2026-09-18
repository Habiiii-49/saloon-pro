/* ============================================================
   ELEGANCE SALON - ADMIN JS
   Part 2
   ============================================================ */
(function () {
    'use strict';

    var AdminApp = window.AdminApp = {
        sidebarOpen: function () {
            document.body.classList.add('sidebar-open');
        },
        sidebarClose: function () {
            document.body.classList.remove('sidebar-open');
        },
        toggleCollapse: function () {
            document.body.classList.toggle('sidebar-collapsed');
        }
    };

    document.addEventListener('DOMContentLoaded', function () {

        /* ---------- Collapsible submenu toggles ---------- */
        document.querySelectorAll('.sidebar-has-sub .sub-parent').forEach(function (parent) {
            parent.addEventListener('click', function (e) {
                if (e.target.closest('a')) return;
                parent.parentElement.classList.toggle('open');
            });
            parent.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    parent.parentElement.classList.toggle('open');
                }
            });
        });
        // Keep an active inventory submenu expanded on load.
        document.querySelectorAll('.sidebar-has-sub.open').forEach(function (li) {
            li.classList.add('open');
        });

        /* ---------- Sidebar toggles ---------- */
        var toggleBtn = document.querySelector('[data-admin-toggle]');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                if (window.innerWidth <= 992) {
                    AdminApp.sidebarOpen();
                } else {
                    AdminApp.toggleCollapse();
                }
            });
        }

        var overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.addEventListener('click', AdminApp.sidebarClose);
        }

        /* ---------- Close mobile sidebar when a nav link is tapped ---------- */
        document.querySelectorAll('.sidebar-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 992) {
                    AdminApp.sidebarClose();
                }
            });
        });

        /* ---------- Auto-dismiss alerts ---------- */
        document.querySelectorAll('.alert-admin').forEach(function (alertEl) {
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

        /* ---------- Count-up animation for stat values ---------- */
        document.querySelectorAll('[data-count]').forEach(function (el) {
            var target = parseFloat(el.getAttribute('data-count')) || 0;
            var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
            var prefix = el.getAttribute('data-prefix') || '';
            var suffix = el.getAttribute('data-suffix') || '';
            var duration = 900;
            var start = null;

            function step(ts) {
                if (!start) start = ts;
                var progress = Math.min((ts - start) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                var val = target * eased;
                el.textContent = prefix + val.toLocaleString('en-US', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                }) + suffix;
                if (progress < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });

        /* ---------- Charts ---------- */
        var chartEl = document.querySelector('#appointmentsChart');
        if (chartEl && window.Chart) {
            var labels = JSON.parse(chartEl.getAttribute('data-labels') || '[]');
            var values = JSON.parse(chartEl.getAttribute('data-values') || '[]');
            var colors = ['#00C2D9', '#f59e0b', '#22c55e', '#ef4444'];

            new Chart(chartEl, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderColor: '#0d2638',
                        borderWidth: 3,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0d2638',
                            borderColor: 'rgba(0,194,217,0.35)',
                            borderWidth: 1,
                            titleColor: '#fff',
                            bodyColor: '#c3cfe0'
                        }
                    }
                }
            });
        }

        var revenueEl = document.querySelector('#revenueChart');
        if (revenueEl && window.Chart) {
            var months = JSON.parse(revenueEl.getAttribute('data-months') || '[]');
            var revenues = JSON.parse(revenueEl.getAttribute('data-revenues') || '[]');

            new Chart(revenueEl, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: revenues,
                        fill: true,
                        backgroundColor: function (ctx) {
                            var chart = ctx.chart;
                            var g = chart.ctx.createLinearGradient(0, 0, 0, 300);
                            g.addColorStop(0, 'rgba(0,194,217,0.35)');
                            g.addColorStop(1, 'rgba(0,194,217,0)');
                            return g;
                        },
                        borderColor: '#00C2D9',
                        borderWidth: 3,
                        pointBackgroundColor: '#08D9E8',
                        pointBorderColor: '#0d2638',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0d2638',
                            borderColor: 'rgba(0,194,217,0.35)',
                            borderWidth: 1,
                            titleColor: '#fff',
                            bodyColor: '#c3cfe0',
                            callbacks: {
                                label: function (ctx) {
                                    return ' $' + Number(ctx.parsed.y).toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(255,255,255,0.04)' },
                            ticks: { color: '#7d8da1' }
                        },
                        y: {
                            grid: { color: 'rgba(255,255,255,0.04)' },
                            ticks: {
                                color: '#7d8da1',
                                callback: function (val) {
                                    return '$' + val.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        /* ---------- Custom confirm dialog ---------- */
        AdminApp.confirm = function (options) {
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
                        '<div class="confirm-icon ' + (danger ? 'danger' : 'safe') + '">' +
                            '<i class="fas fa-triangle-exclamation"></i>' +
                        '</div>' +
                        '<h5>' + document.createTextNode(title).textContent + '</h5>' +
                        '<p></p>' +
                        '<div class="confirm-actions">' +
                            '<button type="button" class="btn-admin btn-outline" data-role="cancel">' + cancelText + '</button>' +
                            '<button type="button" class="btn-admin ' + (danger ? 'btn-danger-outline' : 'btn-cyan') + '" data-role="ok">' + confirmText + '</button>' +
                        '</div>' +
                    '</div>';
                overlay.querySelector('p').textContent = message;

                overlay.addEventListener('click', function (e) {
                    if (e.target === overlay) close(false);
                });
                overlay.querySelector('[data-role="cancel"]').addEventListener('click', function () { close(false); });
                overlay.querySelector('[data-role="ok"]').addEventListener('click', function () { close(true); });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') close(false);
                }, { once: true });

                document.body.appendChild(overlay);
                document.body.classList.add('scroll-lock');
                requestAnimationFrame(function () { overlay.classList.add('show'); });

                function close(result) {
                    overlay.classList.remove('show');
                    document.body.classList.remove('scroll-lock');
                    setTimeout(function () { overlay.remove(); }, 250);
                    resolve(result);
                }
            });
        };

        /* ---------- Auto-subscribe delete/confirm buttons ---------- */
        document.querySelectorAll('[data-confirm]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var params = btn.getAttribute('data-confirm');
                try {
                    var cfg = JSON.parse(params);
                    cfg.danger = true;
                    e.preventDefault();
                    AdminApp.confirm(cfg).then(function (ok) {
                        if (ok) {
                            var form = document.createElement('form');
                            form.method = 'POST';
                            form.action = btn.getAttribute('href') || btn.getAttribute('data-action');
                            if (btn.getAttribute('data-token')) {
                                var input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'csrf_token';
                                input.value = btn.getAttribute('data-token');
                                form.appendChild(input);
                            }
                            if (btn.getAttribute('data-id')) {
                                var idInput = document.createElement('input');
                                idInput.type = 'hidden';
                                idInput.name = 'id';
                                idInput.value = btn.getAttribute('data-id');
                                form.appendChild(idInput);
                            }
                            if (form.action) {
                                document.body.appendChild(form);
                                form.submit();
                            }
                        }
                    });
                } catch (err) {
                    // configuration error - let default anchor behavior proceed
                }
            });
        });

        /* Confirm before submitting an existing (inline) form. */
        document.querySelectorAll('[data-confirm-form]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var cfg = JSON.parse(btn.getAttribute('data-confirm-form') || '{}');
                cfg.danger = true;
                var form = btn.closest('form');
                if (!form) return;
                e.preventDefault();
                AdminApp.confirm(cfg).then(function (ok) {
                    if (ok) form.submit();
                });
            });
        });

        /* ---------- Password visibility toggles ---------- */
        document.querySelectorAll('[data-pw-toggle]').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                var targetId = toggle.getAttribute('data-pw-toggle');
                var input = document.getElementById(targetId);
                if (input) {
                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    toggle.classList.toggle('fa-eye', show);
                    toggle.classList.toggle('fa-eye-slash', !show);
                }
            });
        });

        /* ---------- Simple table search (client-side) ---------- */
        document.querySelectorAll('[data-table-search]').forEach(function (input) {
            var tableSelector = input.getAttribute('data-table-search');
            var table = document.querySelector(tableSelector);
            if (!table) return;
            input.addEventListener('input', function () {
                var q = this.value.toLowerCase();
                table.querySelectorAll('tbody tr').forEach(function (row) {
                    row.style.display = row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
                });
            });
        });
    });

})();