/**
 * notifications.js
 * Real-time push notifications via Server-Sent Events (SSE).
 *
 * Staff  → unread message count badges on the chat conversation list.
 * Admin  → unread badge on the Recovery Requests nav link +
 *          toast popup when a new recovery request arrives.
 *
 * Requires window.__API_BASE and window.__APP_BASE (set by header.php).
 * Reconnects automatically on connection drop (5 s back-off).
 */
(function () {
    'use strict';

    var isStaff = typeof window.__STAFF_USER_ID !== 'undefined';
    var isAdmin = typeof window.__ADMIN_USER_ID !== 'undefined';
    if (!isStaff && !isAdmin) return;

    var apiBase = window.__API_BASE || '/uthm-system/api';
    var appBase = window.__APP_BASE || '/uthm-system';

    var es             = null;
    var reconnectTimer = null;

    // ── SSE connection ────────────────────────────────────────

    function connect() {
        if (es) { es.close(); es = null; }

        es = new EventSource(apiBase + '/sse.php');

        if (isStaff) {
            es.addEventListener('unread', function (e) {
                try { applyUnreadBadges(JSON.parse(e.data).unread || {}); } catch (_) {}
            });
        }

        if (isAdmin) {
            es.addEventListener('recovery', function (e) {
                try { handleRecoveryEvent(JSON.parse(e.data)); } catch (_) {}
            });
        }

        es.onerror = function () {
            es.close();
            es = null;
            clearTimeout(reconnectTimer);
            reconnectTimer = setTimeout(connect, 5000);
        };
    }

    // ── Staff: unread badges ──────────────────────────────────

    function applyUnreadBadges(unread) {
        // Remove all existing chat-item notification badges
        document.querySelectorAll('.notif-badge').forEach(function (el) {
            el.remove();
        });

        var total = 0;

        Object.keys(unread).forEach(function (key) {
            var count = unread[key];
            if (!count) return;

            total += count;

            // key format: "personal_18"  or  "group_1"
            var sep  = key.indexOf('_');
            var type = key.slice(0, sep);   // "personal" | "group"
            var id   = key.slice(sep + 1);  // numeric string

            // Only badge links inside the chat-contacts panel, not the notification dropdown
            var link = document.querySelector('.chat-contacts a[href*="type=' + type + '&id=' + id + '"]');
            if (!link) return;

            var badge = document.createElement('span');
            badge.className = 'notif-badge badge bg-danger rounded-pill ms-auto';
            badge.style.cssText = 'font-size:10px;min-width:18px;text-align:center;';
            badge.textContent = count > 99 ? '99+' : String(count);
            link.appendChild(badge);
        });

        // Update topbar bell badge with total count
        var bellBadge = document.getElementById('notifBellBadge');
        if (bellBadge) {
            if (total > 0) {
                bellBadge.textContent = total > 99 ? '99+' : String(total);
                bellBadge.style.display = '';
            } else {
                bellBadge.style.display = 'none';
            }
        }
    }

    // ── Admin: recovery request notifications ─────────────────

    function handleRecoveryEvent(data) {
        var count = data.pending_count || 0;

        // Update badge on the Recovery Requests nav link
        var navLink = document.querySelector('a[href*="recovery_requests"]');
        if (navLink) {
            var existing = navLink.querySelector('.notif-badge');
            if (count > 0) {
                if (!existing) {
                    existing = document.createElement('span');
                    existing.className = 'notif-badge badge bg-danger rounded-pill ms-auto';
                    existing.style.cssText = 'font-size:10px;min-width:18px;text-align:center;';
                    navLink.appendChild(existing);
                }
                existing.textContent = String(count);
            } else if (existing) {
                existing.remove();
            }
        }
    }

    // ── Start ─────────────────────────────────────────────────
    connect();

})();
