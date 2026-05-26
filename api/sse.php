<?php
// ============================================================
// api/sse.php
// Server-Sent Events endpoint.
// Staff  → streams unread message counts per conversation.
// Admin  → streams pending recovery-request count + latest.
// ============================================================
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../config/database.php';
ob_end_clean(); // Must end buffering before SSE can stream

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$user_id      = (int) $_SESSION['user_id'];
$role         = $_SESSION['role'];
$is_head_admin = !empty($_SESSION['is_head_admin']);

// Release the session file lock so other tabs can make requests
session_write_close();

// Kill any remaining output-buffer layers
while (ob_get_level()) ob_end_clean();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Tells Nginx not to buffer SSE

set_time_limit(0);
ignore_user_abort(true);

// ── Helpers ───────────────────────────────────────────────────

function sse_event(string $event, array $payload): bool
{
    echo "event: {$event}\n";
    echo 'data: ' . json_encode($payload) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
    return !connection_aborted();
}

function sse_heartbeat(): bool
{
    echo ": heartbeat\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
    return !connection_aborted();
}

// ── Event loop (3 s cadence) ──────────────────────────────────

$tick = 0;

while (true) {
    if (connection_aborted()) break;

    $tick++;

    // Heartbeat every 24 s (8 × 3 s) to prevent proxy closing idle connection
    if ($tick % 8 === 0) {
        if (!sse_heartbeat()) break;
        sleep(3);
        continue;
    }

    try {
        $pdo->query('SELECT 1'); // keep MySQL connection alive

        if ($role === 'staff') {
            // ── Unread personal messages ──────────────────────
            $stmt = $pdo->prepare("
                SELECT m.sender_id AS chat_id, COUNT(*) AS cnt
                FROM messages m
                LEFT JOIN conversation_reads cr
                       ON cr.user_id   = ?
                      AND cr.chat_type = 'personal'
                      AND cr.chat_id   = m.sender_id
                WHERE m.receiver_id = ?
                  AND m.message_type IN ('personal', 'personal_file')
                  AND (cr.last_read_at IS NULL OR m.timestamp > cr.last_read_at)
                GROUP BY m.sender_id
            ");
            $stmt->execute([$user_id, $user_id]);
            $personal = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [sender_id => count]

            // ── Unread group messages ─────────────────────────
            $stmt = $pdo->prepare("
                SELECT m.group_id AS chat_id, COUNT(*) AS cnt
                FROM messages m
                JOIN group_members gm ON gm.group_id = m.group_id AND gm.user_id = ?
                LEFT JOIN conversation_reads cr
                       ON cr.user_id   = ?
                      AND cr.chat_type = 'group'
                      AND cr.chat_id   = m.group_id
                WHERE m.sender_id != ?
                  AND m.message_type IN ('group', 'group_file')
                  AND (cr.last_read_at IS NULL OR m.timestamp > cr.last_read_at)
                GROUP BY m.group_id
            ");
            $stmt->execute([$user_id, $user_id, $user_id]);
            $groups = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $unread = [];
            foreach ($personal as $id => $cnt) {
                $unread["personal_{$id}"] = (int) $cnt;
            }
            foreach ($groups as $id => $cnt) {
                $unread["group_{$id}"] = (int) $cnt;
            }

            if (!sse_event('unread', ['unread' => $unread])) break;

        } elseif ($role === 'admin') {
            // ── Pending staff recovery requests ──────────────
            $stmt = $pdo->query("
                SELECT COUNT(*) FROM recovery_requests WHERE status = 'pending'
            ");
            $staff_pending = (int) $stmt->fetchColumn();

            // ── Pending admin reset requests (HOA only) ───────
            $admin_pending = 0;
            if ($is_head_admin) {
                $stmt = $pdo->query("
                    SELECT COUNT(*) FROM admin_reset_requests WHERE status = 'pending'
                ");
                $admin_pending = (int) $stmt->fetchColumn();
            }

            $pending = $staff_pending + $admin_pending;

            $latest = null;
            if ($staff_pending > 0) {
                $stmt = $pdo->query("
                    SELECT rr.request_id, u.name, u.email, rr.reason, rr.request_date
                    FROM recovery_requests rr
                    JOIN users u ON u.user_id = rr.user_id
                    WHERE rr.status = 'pending'
                    ORDER BY rr.request_date DESC
                    LIMIT 1
                ");
                $latest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if (!sse_event('recovery', [
                'pending_count' => $pending,
                'latest'        => $latest
            ])) break;
        }

    } catch (PDOException $e) {
        error_log('sse.php loop error: ' . $e->getMessage());
        // Keep looping — DB may be briefly unavailable
    }

    sleep(3);
}
