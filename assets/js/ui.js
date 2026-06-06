/* assets/js/ui.js — Sidebar toggle + Notification panel */
(function () {
    'use strict';

    function init() {
        // ── Sidebar ────────────────────────────────────────────
        var sidebar  = document.getElementById('sidebar');
        var overlay  = document.getElementById('sidebarOverlay');
        var toggle   = document.getElementById('sidebarToggle');
        var closeBtn = document.getElementById('sidebarClose');

        if (sidebar) {
            function openSidebar() {
                sidebar.classList.add('is-open');
                if (overlay) overlay.classList.add('is-open');
            }
            function closeSidebar() {
                sidebar.classList.remove('is-open');
                if (overlay) overlay.classList.remove('is-open');
            }
            if (toggle)   toggle.addEventListener('click', openSidebar);
            if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
            if (overlay)  overlay.addEventListener('click', closeSidebar);
        }

        // ── Notification panel ─────────────────────────────────
        var notifBtn   = document.getElementById('notifBtn');
        var notifPanel = document.getElementById('notifPanel');
        var notifList  = document.getElementById('notifList');

        function _esc(str) {
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(String(str)));
            return d.innerHTML;
        }

        function renderNotifItems(items) {
            if (!notifList) return;
            if (!items || !items.length) {
                notifList.innerHTML =
                    '<div class="notif-empty">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" ' +
                    'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" ' +
                    'stroke-linejoin="round" style="opacity:.3"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>' +
                    '<polyline points="22 4 12 14.01 9 11.01"/></svg>' +
                    '<span>You\'re all caught up!</span></div>';
                return;
            }
            var html = '';
            items.forEach(function (n) {
                var initial   = _esc(n.chat_name.charAt(0).toUpperCase());
                var groupCls  = n.chat_type === 'group' ? ' notif-avatar--group' : '';
                var count     = parseInt(n.unread_count, 10);
                var countLbl  = count + ' unread message' + (count !== 1 ? 's' : '');
                var href      = (window.__APP_BASE || '') + '/staff/chat.php?type=' +
                                encodeURIComponent(n.chat_type) + '&id=' +
                                encodeURIComponent(n.chat_id);
                html +=
                    '<a href="' + href + '" class="notif-item">' +
                    '<div class="notif-avatar' + groupCls + '">' + initial + '</div>' +
                    '<div class="notif-content">' +
                    '<div class="notif-title">' + _esc(n.chat_name) + '</div>' +
                    '<div class="notif-sub">' + _esc(countLbl) + '</div>' +
                    '</div>' +
                    '<span class="notif-count-badge">' + count + '</span>' +
                    '</a>';
            });
            notifList.innerHTML = html;
        }

        function loadNotifications() {
            var apiBase = (window.__API_BASE || '/api');
            if (notifList) {
                notifList.innerHTML =
                    '<div class="notif-empty" style="padding:20px 16px;">' +
                    '<span style="color:var(--text-muted);font-size:12px;">Loading...</span></div>';
            }
            fetch(apiBase + '/get_notifications.php')
                .then(function (r) { return r.json(); })
                .then(function (d) { renderNotifItems(d.items || []); })
                .catch(function () {
                    if (notifList) notifList.innerHTML =
                        '<div class="notif-empty"><span>Could not load notifications.</span></div>';
                });
        }

        if (notifBtn && notifPanel) {
            notifBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var opening = !notifPanel.classList.contains('open');
                notifPanel.classList.toggle('open');
                if (opening) loadNotifications();
            });

            document.addEventListener('click', function (e) {
                if (notifPanel.classList.contains('open') &&
                    !notifPanel.contains(e.target) &&
                    e.target !== notifBtn) {
                    notifPanel.classList.remove('open');
                }
            });
        }

        // ── Escape closes sidebar and notification panel ───────
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (sidebar) {
                sidebar.classList.remove('is-open');
                if (overlay) overlay.classList.remove('is-open');
            }
            if (notifPanel) notifPanel.classList.remove('open');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
