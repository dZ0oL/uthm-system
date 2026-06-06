<?php
/**
 * staff/chat.php
 * Real E2E encrypted chat using ECDH + AES-GCM
 * All encryption/decryption happens in the browser.
 * Server only stores and routes ciphertext.
 * Supports text messages and encrypted file sharing.
 */
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../index.php');
    exit;
}

$user_id           = $_SESSION['user_id'];
$current_chat_type = isset($_GET['type']) ? $_GET['type'] : 'group';
$current_chat_id   = isset($_GET['id']) ? intval($_GET['id']) : null;

// Get user's groups
$stmt = $pdo->prepare("
    SELECT g.*, COUNT(gm.user_id) as member_count
    FROM `groups` g
    JOIN group_members gm ON g.group_id = gm.group_id
    WHERE gm.user_id = ?
    GROUP BY g.group_id
    ORDER BY g.group_name
");
$stmt->execute([$user_id]);
$user_groups = $stmt->fetchAll();

// Get contacts
$stmt = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.name, u.email, u.staff_id,
                    u.department, u.ecdh_public_key, u.ik_dh_public
    FROM users u
    WHERE u.role = 'staff'
      AND u.status = 'active'
      AND u.user_id != ?
    ORDER BY u.name
");
$stmt->execute([$user_id]);
$contacts = $stmt->fetchAll();

// Get current user's public key
$stmt = $pdo->prepare("SELECT ecdh_public_key FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_public_key = $stmt->fetchColumn();

// Get current chat info + group members if group chat
$chat_info = null;
$group_members = [];
if ($current_chat_id) {
    if ($current_chat_type === 'group') {
        $stmt = $pdo->prepare("SELECT * FROM `groups` WHERE group_id = ?");
        $stmt->execute([$current_chat_id]);
        $chat_info = $stmt->fetch();

        // Fetch members for this group
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.name, u.department, u.staff_id
            FROM group_members gm
            JOIN users u ON gm.user_id = u.user_id
            WHERE gm.group_id = ?
            ORDER BY u.name
        ");
        $stmt->execute([$current_chat_id]);
        $group_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("
            SELECT user_id, name, department, ecdh_public_key
            FROM users WHERE user_id = ?
        ");
        $stmt->execute([$current_chat_id]);
        $chat_info = $stmt->fetch();
    }
}

$page_title = 'Secure Chat';
$main_no_padding = true;
include '../includes/header.php';
?>

<style>
/* ── File message styles ── */
.file-bubble {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.15);
    border-radius: 8px;
    padding: 8px 12px;
    margin-top: 4px;
    cursor: pointer;
    transition: background 0.2s;
}
.file-bubble:hover { background: rgba(255,255,255,0.25); }
.file-bubble .file-icon { font-size: 20px; }
.file-bubble .file-info { flex: 1; min-width: 0; }
.file-bubble .file-name {
    font-weight: 500;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.file-bubble .file-size { font-size: 11px; opacity: 0.75; }
.file-bubble .file-download { font-size: 16px; }
.message-received .file-bubble { background: rgba(0,0,0,0.05); }
.message-received .file-bubble:hover { background: rgba(0,0,0,0.1); }

/* ── Upload progress ── */
#upload-progress-wrap {
    display: none;
    margin-top: 6px;
}
#upload-progress-bar {
    transition: width 0.3s;
}

/* ── File input button ── */
#file-btn {
    border-radius: 0;
    border-left: none;
    border-right: none;
}

/* ── Emoji picker ── */
#emoji-btn {
    border-radius: 0;
    border-left: none;
    border-right: none;
}
#emoji-picker {
    display: none;
    position: absolute;
    bottom: 56px;
    left: 0;
    width: 320px;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    z-index: 1000;
    overflow: hidden;
}
#emoji-picker.open { display: block; }
.emoji-tabs {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
.emoji-tab {
    flex: 1;
    border: none;
    background: none;
    padding: 8px 4px;
    font-size: 18px;
    cursor: pointer;
    opacity: 0.5;
    transition: opacity 0.15s, background 0.15s;
}
.emoji-tab:hover { opacity: 0.8; background: rgba(0,0,0,0.05); }
.emoji-tab.active { opacity: 1; background: #fff; border-bottom: 2px solid #667eea; }
.emoji-grid {
    display: none;
    padding: 8px;
    max-height: 200px;
    overflow-y: auto;
    flex-wrap: wrap;
}
.emoji-grid.active { display: flex; flex-wrap: wrap; }
.emoji-item {
    font-size: 22px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 6px;
    transition: background 0.1s;
    user-select: none;
}
.emoji-item:hover { background: #f0f0f0; }
</style>

<div class="chat-layout <?php echo $current_chat_id ? 'has-active-chat' : ''; ?>"
     id="chatLayout">

    <!-- Chat contacts panel -->
    <div class="chat-contacts" id="chatContactsPanel">
        <div class="chat-contacts-header">
            <i data-lucide="message-square"></i> Chats
        </div>
        <div class="chat-contacts-search">
            <input type="text" id="chat-search" class="form-control form-control-sm"
                   placeholder="Search chats..." autocomplete="off">
        </div>
        <div class="chat-contacts-list">

            <!-- Groups -->
            <div id="chat-group-section">
                <span class="chat-section-label">Groups</span>
                <?php foreach ($user_groups as $group): ?>
                    <a href="chat.php?type=group&id=<?php echo $group['group_id']; ?>"
                       class="chat-item <?php echo ($current_chat_type==='group' && $current_chat_id==$group['group_id']) ? 'active-chat' : ''; ?>"
                       data-name="<?php echo strtolower(htmlspecialchars($group['group_name'])); ?>">
                        <div class="chat-item-avatar chat-item-avatar--group">
                            <?php echo mb_strtoupper(mb_substr($group['group_name'], 0, 1)); ?>
                        </div>
                        <span><?php echo htmlspecialchars($group['group_name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Direct messages -->
            <div id="chat-dm-section">
                <span class="chat-section-label">Direct Messages</span>
                <?php foreach ($contacts as $contact): ?>
                    <a href="chat.php?type=personal&id=<?php echo $contact['user_id']; ?>"
                       class="chat-item <?php echo ($current_chat_type==='personal' && $current_chat_id==$contact['user_id']) ? 'active-chat' : ''; ?>"
                       data-name="<?php echo strtolower(htmlspecialchars($contact['name'])); ?>">
                        <div class="chat-item-avatar">
                            <?php echo mb_strtoupper(mb_substr($contact['name'], 0, 1)); ?>
                        </div>
                        <span style="flex:1;min-width:0;"><?php echo htmlspecialchars($contact['name']); ?></span>
                        <?php if (empty($contact['ik_dh_public']) && empty($contact['ecdh_public_key'])): ?>
                            <i data-lucide="alert-triangle" style="width:13px;height:13px;color:var(--warning);flex-shrink:0;"
                               title="No encryption key — user has not logged in yet"></i>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

        </div><!-- /.chat-contacts-list -->
    </div><!-- /.chat-contacts -->

    <script>
    document.getElementById('chat-search').addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.chat-item').forEach(el => {
            el.style.display = (!q || el.dataset.name.includes(q)) ? '' : 'none';
        });
        ['chat-group-section', 'chat-dm-section'].forEach(id => {
            const section = document.getElementById(id);
            const visible = section.querySelectorAll('.chat-item:not([style*="display: none"])').length;
            section.querySelector('.chat-section-label').style.display = visible ? '' : 'none';
        });
    });
    </script>

    <!-- Main chat thread -->
    <div class="chat-thread">

        <?php if ($chat_info && $current_chat_id): ?>

        <!-- Chat header -->
        <div class="chat-thread-header">
            <div class="d-flex align-items-center gap-2">
                <a href="chat.php" class="mobile-back-btn" title="Back to chats">
                    <i data-lucide="arrow-left"></i>
                </a>
                <?php if ($current_chat_type === 'group'): ?>
                    <div class="chat-item-avatar chat-item-avatar--group" style="width:32px;height:32px;font-size:13px;">
                        <?php echo mb_strtoupper(mb_substr($chat_info['group_name'], 0, 1)); ?>
                    </div>
                    <span class="chat-thread-name"><?php echo htmlspecialchars($chat_info['group_name']); ?></span>
                    <button type="button"
                            onclick="document.getElementById('groupMembersModal').classList.toggle('show-members-modal')"
                            style="background:none;border:none;display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;color:#475569;cursor:pointer;background:#f1f5f9;margin-left:4px;"
                            title="View group members">
                        <i data-lucide="users" style="width:13px;height:13px;"></i>
                        <?php echo count($group_members); ?>
                    </button>
                <?php else: ?>
                    <div class="chat-item-avatar" style="width:32px;height:32px;font-size:13px;">
                        <?php echo mb_strtoupper(mb_substr($chat_info['name'], 0, 1)); ?>
                    </div>
                    <span class="chat-thread-name"><?php echo htmlspecialchars($chat_info['name']); ?></span>
                <?php endif; ?>
                <span class="enc-badge enc-badge--signal ms-1" style="font-size:10px;">
                    <i data-lucide="lock" style="width:10px;height:10px;"></i> E2E Encrypted
                </span>
            </div>
            <small id="key-status" class="text-muted">Checking encryption...</small>
        </div>

        <!-- Messages area -->
        <div id="chat-box" class="chat-messages">
            <div id="messages-container">
                <div class="text-center text-muted py-4" id="loading-msg">
                    <i class="fas fa-spinner fa-spin"></i> Loading messages...
                </div>
            </div>
        </div>

        <!-- Message input -->
        <div class="chat-input-bar">
            <div id="no-key-warning" class="alert alert-warning py-2 mb-2" style="display:none;">
                <i class="fas fa-exclamation-triangle"></i>
                Encryption not ready — please
                <a href="../index.php" class="alert-link">re-login</a> to unlock your key.
            </div>

            <!-- Upload progress -->
            <div id="upload-progress-wrap" class="mb-2">
                <div class="d-flex justify-content-between mb-1">
                    <small id="upload-status" class="text-muted">Encrypting file...</small>
                    <small id="upload-pct" class="text-muted">0%</small>
                </div>
                <div class="progress" style="height:6px;">
                    <div id="upload-progress-bar"
                         class="progress-bar bg-success progress-bar-striped progress-bar-animated"
                         style="width:0%"></div>
                </div>
            </div>

            <!-- Input row -->
            <div class="input-group position-relative">
                <!-- Hidden file input -->
                <input type="file" id="file-input" style="display:none;"
                       accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png">

                <!-- Attach file button -->
                <button id="file-btn" class="btn btn-outline-secondary" disabled
                        title="Attach file (PDF, DOCX, XLSX, JPG, PNG — max 5MB)">
                    <i class="fas fa-paperclip"></i>
                </button>

                <!-- Emoji button -->
                <button id="emoji-btn" class="btn btn-outline-secondary" disabled
                        title="Insert emoji" type="button">
                    😊
                </button>

                <!-- Emoji picker panel -->
                <div id="emoji-picker">
                    <div class="emoji-tabs">
                        <button class="emoji-tab active" data-cat="0" title="Smileys">😀</button>
                        <button class="emoji-tab" data-cat="1" title="Gestures">👍</button>
                        <button class="emoji-tab" data-cat="2" title="Hearts">❤️</button>
                        <button class="emoji-tab" data-cat="3" title="Celebration">🎉</button>
                        <button class="emoji-tab" data-cat="4" title="Nature">🌟</button>
                    </div>
                    <div class="emoji-grid active" data-cat="0">
                        <?php foreach (['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','😊','😇','🥰','😍','🤩','😘','😋','😛','😜','😝','🤑','🤗','🤔','🤐','😐','😑','😶','😏','😒','🙄','😬','😌','😔','😪','😴','😷','🤒','🤕','😎','🤓','🧐','😕','😟','🙁','☹️','😮','😲','😳','🥺','😢','😭','😱','😤','😡','😠','🤬','😈'] as $e): ?>
                        <span class="emoji-item"><?= $e ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="emoji-grid" data-cat="1">
                        <?php foreach (['👋','🤚','✋','🖖','👌','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','👇','👍','👎','✊','👊','🤛','🤜','👏','🙌','🤲','🙏','💪','🦾','🖐️','☝️','👐','🤝'] as $e): ?>
                        <span class="emoji-item"><?= $e ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="emoji-grid" data-cat="2">
                        <?php foreach (['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💕','💞','💓','💗','💖','💘','💝','💟','❣️','💔','❤️‍🔥','❤️‍🩹','😻','💏','💑','💌'] as $e): ?>
                        <span class="emoji-item"><?= $e ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="emoji-grid" data-cat="3">
                        <?php foreach (['🎉','🎊','🎈','🎁','🎀','🏆','🥇','🥈','🥉','🎖️','🏅','🎗️','🎟️','🎪','✨','🌟','⭐','🌠','🎆','🎇','🧨','🎯','🎮','🕹️','🎲','🃏','🀄','🎴','🎭','🎬'] as $e): ?>
                        <span class="emoji-item"><?= $e ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="emoji-grid" data-cat="4">
                        <?php foreach (['🌟','⚡','🔥','💫','🌈','☀️','🌤️','⛅','🌦️','🌧️','⛈️','🌩️','🌨️','❄️','💨','🌊','🌙','⭐','🌺','🌸','🌼','🌻','🌹','🍀','🌿','🌱','🌴','🍁','🍂','🍃'] as $e): ?>
                        <span class="emoji-item"><?= $e ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Text input -->
                <input type="text" id="message-input" class="form-control"
                       placeholder="Type an encrypted message..."
                       autocomplete="off" disabled>

                <!-- Send button -->
                <button id="send-btn" class="btn btn-gradient" disabled>
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </div>

            <!-- Selected file preview -->
            <div id="file-preview" style="display:none;" class="mt-2">
                <div class="d-flex align-items-center gap-2 p-2 bg-light rounded border">
                    <i id="file-preview-icon" class="fas fa-file fa-lg text-secondary"></i>
                    <div class="flex-grow-1 min-width-0">
                        <div id="file-preview-name" class="small fw-500 text-truncate"></div>
                        <div id="file-preview-size" class="small text-muted"></div>
                    </div>
                    <button id="file-cancel-btn" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <small class="text-muted mt-1 d-block">
                <i class="fas fa-shield-alt text-success"></i>
                Messages are encrypted in your browser. The server cannot read them.
            </small>
        </div>

        <?php else: ?>
        <!-- No chat selected -->
        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
            <div class="text-center">
                <i data-lucide="message-square" style="width:56px;height:56px;opacity:.25;margin-bottom:16px;color:var(--primary);"></i>
                <h5 style="font-size:15px;font-weight:600;">Select a chat to start messaging</h5>
                <p class="small">All messages are end-to-end encrypted</p>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.chat-thread -->
</div><!-- /.chat-layout -->

<?php if ($chat_info && $current_chat_id): ?>
<script>
// ── Chat configuration ────────────────────────────────────────
const CHAT_CONFIG = {
    myUserId:    <?php echo intval($user_id); ?>,
    myPublicKey: <?php echo json_encode($my_public_key); ?>,
    chatType:    <?php echo json_encode($current_chat_type); ?>,
    chatId:      <?php echo intval($current_chat_id); ?>,
    apiBase:     window.__API_BASE || '/api',
    // Peer's legacy ECDH public key — used as fallback when Signal session is lost
    peerEcdhKey: <?php echo json_encode(
        $current_chat_type === 'personal' && $chat_info
            ? ($chat_info['ecdh_public_key'] ?? null)
            : null
    ); ?>
};

// Allowed file types and max size
const ALLOWED_TYPES = {
    'application/pdf': { icon: 'fa-file-pdf', color: '#dc3545' },
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
        { icon: 'fa-file-word', color: '#0d6efd' },
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
        { icon: 'fa-file-excel', color: '#198754' },
    'image/jpeg': { icon: 'fa-file-image', color: '#fd7e14' },
    'image/png':  { icon: 'fa-file-image', color: '#fd7e14' }
};
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

let _chatInitialised = false;
let _selectedFile    = null; // currently selected file
const _fileMessages  = {};   // message_id → file metadata, avoids embedding JSON in onclick attrs
const _decryptFailed = new Set(); // message IDs that permanently failed this session

// Per-user bubble colours — assigned by sender_id % palette length
const MEMBER_PALETTE = [
    { bg: '#e1effe', name: '#1a56db' }, // blue
    { bg: '#def7ec', name: '#057a55' }, // green
    { bg: '#feecdc', name: '#b43403' }, // orange
    { bg: '#fce8f3', name: '#9b1c6c' }, // pink
    { bg: '#ccfbf1', name: '#115e59' }, // teal
    { bg: '#fef3c7', name: '#92400e' }, // amber
    { bg: '#ede9fe', name: '#5b21b6' }, // violet
    { bg: '#fee2e2', name: '#991b1b' }, // red
];

// ── initChat — called by DOMContentLoaded or session.js ──────
async function initChat() {
    if (_chatInitialised) return;

    const keyStatus = document.getElementById('key-status');
    const input     = document.getElementById('message-input');
    const sendBtn   = document.getElementById('send-btn');
    const fileBtn   = document.getElementById('file-btn');
    const noKeyWarn = document.getElementById('no-key-warning');
    const privateKey= UTHMCrypto.getSessionKey();

    if (!privateKey && !Signal.hasIdentitySession()) {
        keyStatus.textContent   = '⚠ Key not loaded';
        keyStatus.className     = 'text-danger small';
        noKeyWarn.style.display = 'block';
        const loadingMsg = document.getElementById('loading-msg');
        if (loadingMsg) loadingMsg.textContent = 'Encryption key not available. Please re-login.';
        return;
    }

    const emojiBtn    = document.getElementById('emoji-btn');
    const emojiPicker = document.getElementById('emoji-picker');

    _chatInitialised        = true;
    keyStatus.textContent   = '🔒 Encrypted';
    keyStatus.className     = 'text-success small';
    input.disabled          = false;
    sendBtn.disabled        = false;
    fileBtn.disabled        = false;
    emojiBtn.disabled       = false;
    noKeyWarn.style.display = 'none';

    await loadMessages();
    setInterval(loadMessages, 5000);

    // Text message events
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            emojiPicker.classList.remove('open');
            if (_selectedFile) sendFile(); else sendMessage();
        }
    });
    sendBtn.addEventListener('click', () => {
        emojiPicker.classList.remove('open');
        if (_selectedFile) sendFile(); else sendMessage();
    });

    // File picker events
    fileBtn.addEventListener('click', () => {
        emojiPicker.classList.remove('open');
        document.getElementById('file-input').click();
    });
    document.getElementById('file-input').addEventListener('change', handleFileSelect);
    document.getElementById('file-cancel-btn').addEventListener('click', clearFileSelection);

    // ── Emoji picker ──────────────────────────────────────────────
    emojiBtn.addEventListener('click', e => {
        e.stopPropagation();
        emojiPicker.classList.toggle('open');
    });

    // Tab switching
    emojiPicker.querySelectorAll('.emoji-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const cat = tab.dataset.cat;
            emojiPicker.querySelectorAll('.emoji-tab').forEach(t => t.classList.remove('active'));
            emojiPicker.querySelectorAll('.emoji-grid').forEach(g => g.classList.remove('active'));
            tab.classList.add('active');
            emojiPicker.querySelector(`.emoji-grid[data-cat="${cat}"]`).classList.add('active');
        });
    });

    // Insert emoji at cursor
    emojiPicker.querySelectorAll('.emoji-item').forEach(item => {
        item.addEventListener('click', () => {
            const emoji = item.textContent;
            const start = input.selectionStart;
            const end   = input.selectionEnd;
            input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
            input.selectionStart = input.selectionEnd = start + emoji.length;
            input.focus();
        });
    });

    // Close picker when clicking outside
    document.addEventListener('click', e => {
        if (!emojiPicker.contains(e.target) && e.target !== emojiBtn) {
            emojiPicker.classList.remove('open');
        }
    });
}

// ── File selection handler ────────────────────────────────────
function handleFileSelect(e) {
    const file = e.target.files[0];
    if (!file) return;

    // Validate type
    if (!ALLOWED_TYPES[file.type]) {
        alert('File type not allowed.\nAllowed: PDF, DOCX, XLSX, JPG, PNG');
        e.target.value = '';
        return;
    }

    // Validate size
    if (file.size > MAX_FILE_SIZE) {
        alert('File is too large. Maximum size is 5MB.');
        e.target.value = '';
        return;
    }

    _selectedFile = file;
    showFilePreview(file);
}

function showFilePreview(file) {
    const info    = ALLOWED_TYPES[file.type] || { icon: 'fa-file', color: '#6c757d' };
    const preview = document.getElementById('file-preview');
    const icon    = document.getElementById('file-preview-icon');
    const name    = document.getElementById('file-preview-name');
    const size    = document.getElementById('file-preview-size');

    icon.className = `fas ${info.icon} fa-lg`;
    icon.style.color = info.color;
    name.textContent = file.name;
    size.textContent = formatFileSize(file.size);
    preview.style.display = 'block';

    // Update send button label
    document.getElementById('send-btn').innerHTML =
        '<i class="fas fa-paper-plane"></i> Send File';
    document.getElementById('message-input').placeholder =
        'Add a message (optional)...';
}

function clearFileSelection() {
    _selectedFile = null;
    document.getElementById('file-input').value = '';
    document.getElementById('file-preview').style.display = 'none';
    document.getElementById('send-btn').innerHTML =
        '<i class="fas fa-paper-plane"></i> Send';
    document.getElementById('message-input').placeholder =
        'Type an encrypted message...';
}

// ── Load and decrypt messages ────────────────────────────────
async function loadMessages() {
    try {
        const response = await fetch(
            `${CHAT_CONFIG.apiBase}/get_messages.php?type=${CHAT_CONFIG.chatType}&id=${CHAT_CONFIG.chatId}`
        );
        const data = await response.json();
        if (!data.success) return;

        const container  = document.getElementById('messages-container');
        const loadingMsg = document.getElementById('loading-msg');
        if (loadingMsg) loadingMsg.remove();

        const privateKey = UTHMCrypto.getSessionKey();
        const rendered   = [];

        for (const msg of data.messages) {
            const isMine    = msg.sender_id == CHAT_CONFIG.myUserId;
            const isFile    = msg.message_type === 'personal_file' ||
                              msg.message_type === 'group_file';
            const isSignal  = !!msg.signal_header;
            let   text      = '';

            // Personal message failures are permanent (session loss can't self-heal).
            // Group message failures are skipped here and retried every poll —
            // the sender key may become available after a race condition or redistribution.
            const isGroup = CHAT_CONFIG.chatType === 'group';
            if (_decryptFailed.has(msg.message_id) && !isGroup) {
                rendered.push({ msg, text: '[Decryption error]', isMine, isFile });
                continue;
            }

            try {
                if (isFile) {
                    text = null; // file bubble — decrypt lazily on download
                    // Pre-cache the file key so download works without re-decrypting the chain
                    if (isSignal && !isMine && Signal.hasIdentitySession()) {
                        if (CHAT_CONFIG.chatType === 'personal') {
                            await Signal.cachePersonalFileKey(
                                CHAT_CONFIG.myUserId, CHAT_CONFIG.chatId, msg
                            );
                        } else {
                            await Signal.cacheGroupFileKey(
                                CHAT_CONFIG.myUserId, CHAT_CONFIG.chatId,
                                msg.sender_id, msg.sender_ik_dh_public, msg, CHAT_CONFIG.apiBase
                            );
                        }
                    }

                } else if (!msg.iv || !msg.auth_tag) {
                    text = '[Legacy message — encrypted with old system]';

                } else if (!isSignal) {
                    // ── Legacy ECDH decryption ─────────────────────
                    if (CHAT_CONFIG.chatType === 'personal') {
                        const otherKey = isMine
                            ? data.members[0]?.ecdh_public_key
                            : msg.sender_public_key;
                        text = otherKey && privateKey
                            ? await UTHMCrypto.decryptMessage(msg.message_content, msg.iv, msg.auth_tag, privateKey, otherKey)
                            : '[Cannot decrypt — missing legacy key]';
                    } else {
                        const encKeys  = msg.encrypted_aes_key ? JSON.parse(msg.encrypted_aes_key) : null;
                        const myEncKey = encKeys ? encKeys[CHAT_CONFIG.myUserId] : null;
                        text = (myEncKey && privateKey)
                            ? await UTHMCrypto.decryptGroupMessage(msg.message_content, msg.iv, msg.auth_tag, myEncKey, privateKey, msg.sender_public_key)
                            : '[Cannot decrypt — no legacy key for you]';
                    }

                } else if (!Signal.hasIdentitySession()) {
                    text = '[Signal keys not ready — please re-login]';

                } else if (isMine) {
                    // ── My own Signal message — IDB cache first, ECDH fallback second ──
                    const cached = await Signal.getCachedDecrypted(CHAT_CONFIG.myUserId, msg.message_id);
                    if (cached) {
                        text = cached.text;
                    } else if (msg.ecdh_content && msg.ecdh_iv && msg.ecdh_auth_tag
                               && privateKey && CHAT_CONFIG.peerEcdhKey) {
                        try {
                            text = await UTHMCrypto.decryptMessage(
                                msg.ecdh_content, msg.ecdh_iv, msg.ecdh_auth_tag,
                                privateKey, CHAT_CONFIG.peerEcdhKey
                            );
                            // Repopulate cache so future polls are instant
                            await Signal.cacheDecrypted(CHAT_CONFIG.myUserId, msg.message_id, text);
                        } catch {
                            text = '[Sent message]';
                        }
                    } else {
                        text = '[Sent message]';
                    }

                } else if (CHAT_CONFIG.chatType === 'personal') {
                    // ── Signal personal message from peer — Signal first, ECDH fallback ──
                    try {
                        text = await Signal.decryptPersonal(
                            CHAT_CONFIG.myUserId, CHAT_CONFIG.chatId, msg
                        );
                    } catch {
                        // Signal session lost — try ECDH fallback (sender_public_key is the peer's ECDH key)
                        if (msg.ecdh_content && msg.ecdh_iv && msg.ecdh_auth_tag
                            && privateKey && msg.sender_public_key) {
                            try {
                                text = await UTHMCrypto.decryptMessage(
                                    msg.ecdh_content, msg.ecdh_iv, msg.ecdh_auth_tag,
                                    privateKey, msg.sender_public_key
                                );
                                await Signal.cacheDecrypted(CHAT_CONFIG.myUserId, msg.message_id, text);
                            } catch {
                                text = '[Decryption error]';
                            }
                        } else {
                            text = '[Decryption error]';
                        }
                    }

                } else {
                    // ── Signal group message from another member ───
                    text = await Signal.decryptGroup(
                        CHAT_CONFIG.myUserId, CHAT_CONFIG.chatId,
                        msg.sender_id, msg.sender_ik_dh_public, msg, CHAT_CONFIG.apiBase
                    );
                }
            } catch (e) {
                text = '[Decryption error]';
                _decryptFailed.add(msg.message_id);
                console.error('Decrypt error for message', msg.message_id, e);
            }

            rendered.push({ msg, text, isMine, isFile });
        }

        if (container.children.length !== rendered.length) {
            container.innerHTML = '';
            let prevSenderId = null;
            for (const { msg, text, isMine, isFile } of rendered) {
                const isFirstInRow = msg.sender_id !== prevSenderId;
                container.appendChild(buildBubble(msg, text, isMine, isFile, isFirstInRow));
                prevSenderId = msg.sender_id;
            }
            const chatBox = document.getElementById('chat-box');
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        markRead(); // tell the server this conversation has been seen

    } catch (err) {
        console.error('loadMessages error:', err);
    }
}

// ── Build message bubble ──────────────────────────────────────
function buildBubble(msg, text, isMine, isFile, isFirstInRow = true) {
    const wrapper     = document.createElement('div');
    wrapper.className = `d-flex ${isFirstInRow ? 'mt-2' : 'mt-1'} ${isMine ? 'justify-content-end' : 'justify-content-start'}`;

    const time = new Date(msg.timestamp).toLocaleTimeString([], {
        hour: '2-digit', minute: '2-digit'
    });

    const memberColor = !isMine
        ? MEMBER_PALETTE[msg.sender_id % MEMBER_PALETTE.length]
        : null;

    let contentHtml = '';

    if (isFile) {
        // File bubble
        const info = ALLOWED_TYPES[msg.file_type] || { icon: 'fa-file', color: '#6c757d' };
        _fileMessages[msg.message_id] = {
            fileName:            msg.file_name         || '',
            fileType:            msg.file_type          || '',
            iv:                  msg.iv                 || '',
            authTag:             msg.auth_tag           || '',
            isMine,
            senderPublicKey:     msg.sender_public_key  || '',
            senderIKPub:         msg.sender_ik_dh_public || '',
            senderId:            msg.sender_id,
            encryptedAesKeyJson: msg.encrypted_aes_key || '',
            signal_header:       msg.signal_header      || null,
            signal_prekey_data:  msg.signal_prekey_data || null,
            message_id:          msg.message_id,
            message_content:     msg.message_content || '',
        };
        contentHtml = `
            <div class="file-bubble" onclick="downloadFile(${msg.message_id})">
                <i class="fas ${info.icon} file-icon" style="color:${info.color}"></i>
                <div class="file-info">
                    <div class="file-name">${escHtml(msg.file_name)}</div>
                    <div class="file-size">${formatFileSize(msg.file_size)} &bull; Click to download</div>
                </div>
                <i class="fas fa-download file-download"></i>
            </div>
        `;
    } else {
        contentHtml = `<span>${escHtml(text)}</span>`;
    }

    const bubbleStyle = memberColor
        ? `max-width:70%;background:${memberColor.bg};`
        : 'max-width:70%;';

    wrapper.innerHTML = `
        <div class="message-bubble ${isMine ? 'message-sent' : 'message-received'}"
             style="${bubbleStyle}">
            ${!isMine && isFirstInRow
                ? `<small class="d-block fw-bold mb-1" style="font-size:11px;color:${memberColor.name};">${escHtml(msg.sender_name)}</small>`
                : ''}
            ${contentHtml}
            <div class="d-flex align-items-center mt-1 gap-1"
                 style="font-size:10px; opacity:0.7; justify-content:flex-end;">
                <span>${time}</span>
                <i class="fas fa-lock" title="End-to-end encrypted"></i>
            </div>
        </div>
    `;
    return wrapper;
}

// ── Shared: build payload, post to send_message.php, cache ───
async function _sendEncryptedText(plaintext) {
    const privateKey = UTHMCrypto.getSessionKey();
    let payload = { message_type: CHAT_CONFIG.chatType };

    if (CHAT_CONFIG.chatType === 'personal') {
        if (Signal.hasIdentitySession()) {
            const bundleRes = await fetch(`${CHAT_CONFIG.apiBase}/signal_get_key_bundle.php?user_id=${CHAT_CONFIG.chatId}`);
            const bundle    = await bundleRes.json();
            if (!bundle.success) throw new Error('Recipient has no Signal keys — they must log in first.');
            const enc = await Signal.encryptPersonal(CHAT_CONFIG.myUserId, CHAT_CONFIG.chatId, plaintext, bundle);
            payload.receiver_id        = CHAT_CONFIG.chatId;
            payload.message_content    = enc.message_content;
            payload.iv                 = enc.iv;
            payload.auth_tag           = enc.auth_tag;
            payload.signal_header      = enc.signal_header;
            payload.signal_prekey_data = enc.signal_prekey_data;

            // ECDH fallback: also encrypt with static keys so either party can
            // recover if Signal session state (IDB) is lost or cleared.
            if (privateKey && CHAT_CONFIG.peerEcdhKey) {
                const ecdhEnc = await UTHMCrypto.encryptMessage(plaintext, privateKey, CHAT_CONFIG.peerEcdhKey);
                payload.ecdh_content  = ecdhEnc.ciphertext;
                payload.ecdh_iv       = ecdhEnc.iv;
                payload.ecdh_auth_tag = ecdhEnc.authTag;
            }
        } else {
            const pkRes  = await fetch(`${CHAT_CONFIG.apiBase}/get_public_key.php?user_id=${CHAT_CONFIG.chatId}`);
            const pkData = await pkRes.json();
            if (!pkData.success || !pkData.public_key) throw new Error('Recipient has no encryption key yet.');
            const encrypted = await UTHMCrypto.encryptMessage(plaintext, privateKey, pkData.public_key);
            payload.receiver_id     = CHAT_CONFIG.chatId;
            payload.message_content = encrypted.ciphertext;
            payload.iv              = encrypted.iv;
            payload.auth_tag        = encrypted.authTag;
        }
    } else {
        const membersRes  = await fetch(`${CHAT_CONFIG.apiBase}/get_messages.php?type=group&id=${CHAT_CONFIG.chatId}`);
        const membersData = await membersRes.json();
        if (Signal.hasIdentitySession()) {
            const members = (membersData.members || []).map(m => ({
                userId: m.user_id, ik_dh_public: m.ik_dh_public
            }));
            const enc = await Signal.encryptGroup(
                CHAT_CONFIG.myUserId, CHAT_CONFIG.chatId, plaintext, members, CHAT_CONFIG.apiBase
            );
            payload.group_id        = CHAT_CONFIG.chatId;
            payload.message_content = enc.message_content;
            payload.iv              = enc.iv;
            payload.auth_tag        = enc.auth_tag;
            payload.signal_header   = enc.signal_header;
        } else {
            const members = (membersData.members || [])
                .filter(m => m.ecdh_public_key)
                .map(m => ({ userId: m.user_id, publicKeyJwk: m.ecdh_public_key }));
            if (members.length === 0) throw new Error('No group members have encryption keys yet.');
            const encrypted = await UTHMCrypto.encryptGroupMessage(plaintext, privateKey, members);
            payload.group_id        = CHAT_CONFIG.chatId;
            payload.message_content = encrypted.ciphertext;
            payload.iv              = encrypted.iv;
            payload.auth_tag        = encrypted.authTag;
            payload.encrypted_keys  = encrypted.encryptedKeys;
        }
    }

    const res    = await fetch(`${CHAT_CONFIG.apiBase}/send_message.php`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload)
    });
    const result = await res.json();
    if (!result.success) throw new Error(result.error || 'Send failed');

    if (result.message_id && payload.signal_header) {
        await Signal.cacheDecrypted(CHAT_CONFIG.myUserId, result.message_id, plaintext);
    }
}

// ── Send encrypted text message ───────────────────────────────
async function sendMessage() {
    const input     = document.getElementById('message-input');
    const sendBtn   = document.getElementById('send-btn');
    const plaintext = input.value.trim();
    if (!plaintext) return;

    if (!UTHMCrypto.getSessionKey() && !Signal.hasIdentitySession()) {
        alert('Encryption key not available. Please re-login.');
        return;
    }

    const emojiBtn = document.getElementById('emoji-btn');
    input.disabled    = true;
    sendBtn.disabled  = true;
    emojiBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        await _sendEncryptedText(plaintext);
        input.value = '';
        await loadMessages();
    } catch (err) {
        console.error('sendMessage error:', err);
        alert('Failed to send: ' + err.message);
    } finally {
        input.disabled    = false;
        sendBtn.disabled  = false;
        emojiBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
        input.focus();
    }
}

// ── Send encrypted file ───────────────────────────────────────
async function sendFile() {
    if (!_selectedFile) return;

    const privateKey = UTHMCrypto.getSessionKey();
    if (!privateKey) {
        alert('Encryption key not available. Please re-login.');
        return;
    }

    const input    = document.getElementById('message-input');
    const sendBtn  = document.getElementById('send-btn');
    const fileBtn  = document.getElementById('file-btn');
    const progress = document.getElementById('upload-progress-wrap');
    const progBar  = document.getElementById('upload-progress-bar');
    const progText = document.getElementById('upload-status');
    const progPct  = document.getElementById('upload-pct');

    const emojiBtn2 = document.getElementById('emoji-btn');
    input.disabled    = true;
    sendBtn.disabled  = true;
    fileBtn.disabled  = true;
    emojiBtn2.disabled = true;
    document.getElementById('emoji-picker').classList.remove('open');
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    progress.style.display = 'block';

    const caption = input.value.trim(); // capture before disabling

    try {
        // Step 1 — Read file bytes
        progText.textContent = 'Reading file...';
        progBar.style.width  = '10%';
        progPct.textContent  = '10%';

        const fileBuffer = await _selectedFile.arrayBuffer();

        // Step 2 — Encrypt file in browser
        progText.textContent = 'Encrypting file...';
        progBar.style.width  = '30%';
        progPct.textContent  = '30%';

        const messageType = CHAT_CONFIG.chatType === 'personal' ? 'personal_file' : 'group_file';
        let encryptedBuffer, signalPayload = null, legacyIv, legacyAuthTag, encryptedKeys = null;
        let _sendFileKeyData = null; // file key to cache after server responds with message_id

        if (Signal.hasIdentitySession()) {
            // ── Signal file encryption ────────────────────────────
            if (CHAT_CONFIG.chatType === 'personal') {
                const bundleRes = await fetch(`${CHAT_CONFIG.apiBase}/signal_get_key_bundle.php?user_id=${CHAT_CONFIG.chatId}`);
                const bundle    = await bundleRes.json();
                if (!bundle.success) throw new Error('Recipient has no Signal keys yet.');

                const result     = await Signal.encryptPersonalFile(CHAT_CONFIG.myUserId, CHAT_CONFIG.chatId, fileBuffer, bundle);
                encryptedBuffer  = result.encryptedBuffer;
                signalPayload    = result.payload;
                _sendFileKeyData = result.fileKeyData;
            } else {
                const membersRes  = await fetch(`${CHAT_CONFIG.apiBase}/get_messages.php?type=group&id=${CHAT_CONFIG.chatId}`);
                const membersData = await membersRes.json();
                const members     = (membersData.members || []).map(m => ({ userId: m.user_id, ik_dh_public: m.ik_dh_public }));

                const result     = await Signal.encryptGroupFile(CHAT_CONFIG.myUserId, CHAT_CONFIG.chatId, fileBuffer, members, CHAT_CONFIG.apiBase);
                encryptedBuffer  = result.encryptedBuffer;
                signalPayload    = result.payload;
                _sendFileKeyData = result.fileKeyData;
            }
        } else {
            // ── Legacy ECDH file encryption ───────────────────────
            if (CHAT_CONFIG.chatType === 'personal') {
                const pkRes  = await fetch(`${CHAT_CONFIG.apiBase}/get_public_key.php?user_id=${CHAT_CONFIG.chatId}`);
                const pkData = await pkRes.json();
                if (!pkData.success || !pkData.public_key) throw new Error('Recipient has no encryption key yet.');
                const result    = await UTHMCrypto.encryptFile(fileBuffer, privateKey, pkData.public_key);
                encryptedBuffer = result.encryptedBuffer;
                legacyIv        = result.iv;
                legacyAuthTag   = result.authTag;
            } else {
                const membersRes  = await fetch(`${CHAT_CONFIG.apiBase}/get_messages.php?type=group&id=${CHAT_CONFIG.chatId}`);
                const membersData = await membersRes.json();
                const members     = (membersData.members || []).filter(m => m.ecdh_public_key).map(m => ({ userId: m.user_id, publicKeyJwk: m.ecdh_public_key }));
                if (members.length === 0) throw new Error('No group members have encryption keys yet.');
                const result    = await UTHMCrypto.encryptFileGroup(fileBuffer, privateKey, members);
                encryptedBuffer = result.encryptedBuffer;
                legacyIv        = result.iv;
                legacyAuthTag   = result.authTag;
                encryptedKeys   = result.encryptedKeys;
            }
        }

        // Step 3 — Upload encrypted blob
        progText.textContent = 'Uploading...';
        progBar.style.width  = '60%';
        progPct.textContent  = '60%';

        const formData = new FormData();
        formData.append('message_type', messageType);
        formData.append('file_type',    _selectedFile.type);

        if (signalPayload) {
            formData.append('message_content',    signalPayload.message_content);
            formData.append('iv',                 signalPayload.iv);
            formData.append('auth_tag',           signalPayload.auth_tag);
            formData.append('signal_header',      signalPayload.signal_header);
            if (signalPayload.signal_prekey_data) {
                formData.append('signal_prekey_data', signalPayload.signal_prekey_data);
            }
            if (signalPayload.ecdh_file_key) {
                formData.append('encrypted_aes_key', signalPayload.ecdh_file_key);
            }
        } else {
            formData.append('iv',       legacyIv);
            formData.append('auth_tag', legacyAuthTag);
            if (encryptedKeys) formData.append('encrypted_aes_key', JSON.stringify(encryptedKeys));
        }

        if (CHAT_CONFIG.chatType === 'personal') {
            formData.append('receiver_id', CHAT_CONFIG.chatId);
        } else {
            formData.append('group_id', CHAT_CONFIG.chatId);
        }

        formData.append('encrypted_file', new Blob([encryptedBuffer]), _selectedFile.name);

        const res    = await fetch(`${CHAT_CONFIG.apiBase}/send_file.php`, { method: 'POST', body: formData });
        const result = await res.json();
        if (!result.success) throw new Error(result.error || 'Upload failed');

        // Cache the file key so my own sent file can be downloaded without re-decrypting the chain
        if (result.message_id && _sendFileKeyData) {
            await Signal.cacheFileKey(CHAT_CONFIG.myUserId, result.message_id, _sendFileKeyData);
        }

        progText.textContent = 'Done!';
        progBar.style.width  = '100%';
        progPct.textContent  = '100%';

        clearFileSelection();
        input.value = '';

        // Send caption as a separate text message if the user typed one
        if (caption) {
            await _sendEncryptedText(caption);
        }

        await loadMessages();

    } catch (err) {
        console.error('sendFile error:', err);
        alert('Failed to send file: ' + err.message);
    } finally {
        setTimeout(() => { progress.style.display = 'none'; progBar.style.width = '0%'; }, 1000);
        input.disabled     = false;
        sendBtn.disabled   = false;
        fileBtn.disabled   = false;
        emojiBtn2.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
        input.focus();
    }
}

// ── Download and decrypt file ─────────────────────────────────
async function downloadFile(messageId) {
    const meta = _fileMessages[messageId];
    if (!meta) {
        alert('File metadata not found. Please refresh the page.');
        return;
    }
    const { fileName, fileType, iv, authTag, isMine, senderPublicKey, encryptedAesKeyJson } = meta;

    if (!Signal.hasIdentitySession() && !UTHMCrypto.getSessionKey()) {
        alert('Encryption keys not loaded. Please re-login.');
        return;
    }

    try {
        // Fetch encrypted file bytes from server
        const res = await fetch(`${CHAT_CONFIG.apiBase}/get_file.php?message_id=${messageId}`);
        if (!res.ok) throw new Error('Failed to fetch file from server');

        const encryptedBuffer = await res.arrayBuffer();
        let decryptedBuffer;

        const isSignal = !!(meta.signal_header);

        if (isSignal && Signal.hasIdentitySession()) {
            // ── Signal file decryption ────────────────────────────
            if (CHAT_CONFIG.chatType === 'personal') {
                decryptedBuffer = await Signal.decryptPersonalFile(
                    CHAT_CONFIG.myUserId, CHAT_CONFIG.chatId, meta, encryptedBuffer
                );
            } else {
                decryptedBuffer = await Signal.decryptGroupFile(
                    CHAT_CONFIG.myUserId, CHAT_CONFIG.chatId,
                    meta.senderId, meta.senderIKPub, meta, encryptedBuffer, CHAT_CONFIG.apiBase
                );
            }

        } else {
            // ── Legacy ECDH file decryption ───────────────────────
            const privateKey = UTHMCrypto.getSessionKey();
            if (!privateKey) throw new Error('Legacy key not loaded — re-login required');

            if (CHAT_CONFIG.chatType === 'personal') {
                const otherPublicKey = isMine
                    ? (await fetchPublicKey(CHAT_CONFIG.chatId))
                    : senderPublicKey;
                if (!otherPublicKey) throw new Error('Cannot decrypt — missing public key');
                decryptedBuffer = await UTHMCrypto.decryptFile(
                    encryptedBuffer, iv, authTag, privateKey, otherPublicKey
                );
            } else {
                const encKeys  = encryptedAesKeyJson ? JSON.parse(encryptedAesKeyJson) : null;
                const myEncKey = encKeys ? encKeys[CHAT_CONFIG.myUserId] : null;
                if (!myEncKey) throw new Error('Cannot decrypt — no key for this file');
                decryptedBuffer = await UTHMCrypto.decryptFileGroup(
                    encryptedBuffer, iv, authTag, myEncKey, privateKey, senderPublicKey
                );
            }
        }

        // Trigger browser download
        const blob = new Blob([decryptedBuffer], { type: fileType });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = fileName;
        a.click();
        URL.revokeObjectURL(url);

    } catch (err) {
        console.error('downloadFile error:', err);
        alert('Failed to download file: ' + err.message);
    }
}

// Helper — fetch a user's public key
async function fetchPublicKey(userId) {
    const res  = await fetch(`${CHAT_CONFIG.apiBase}/get_public_key.php?user_id=${userId}`);
    const data = await res.json();
    return data.success ? data.public_key : null;
}

// Helper — record that the current user has read this conversation (fire-and-forget)
async function markRead() {
    if (!CHAT_CONFIG.chatId) return;
    try {
        await fetch(`${CHAT_CONFIG.apiBase}/mark_read.php`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                chat_type: CHAT_CONFIG.chatType,
                chat_id:   CHAT_CONFIG.chatId
            })
        });
    } catch (_) {}
}

// ── Utility helpers ───────────────────────────────────────────
function escHtml(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    if (bytes < 1024)        return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function getFileIcon(mimeType) {
    const info = ALLOWED_TYPES[mimeType];
    return info ? info.icon : 'fa-file';
}

// ── Trigger on page load ──────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (UTHMCrypto.getSessionKey()) {
        initChat();
    }
    // If key not ready, session.js calls initChat() after unlocking
});
</script>
<?php endif; ?>

<?php if ($current_chat_type === 'group' && !empty($group_members)): ?>
<!-- Group Members Panel -->
<div id="groupMembersModal"
     style="position:fixed;inset:0;z-index:9998;align-items:flex-start;justify-content:flex-end;padding:70px 16px 16px;">
    <div style="background:#fff;border-radius:16px;width:280px;max-height:70vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.18);">
        <div style="padding:16px 18px 12px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:13px;font-weight:700;color:#0f172a;">
                <i data-lucide="users" style="width:14px;height:14px;margin-right:5px;color:#1e40af;"></i>
                Group Members (<?php echo count($group_members); ?>)
            </span>
            <button onclick="document.getElementById('groupMembersModal').classList.remove('show-members-modal')"
                    style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:16px;line-height:1;padding:0;">×</button>
        </div>
        <div style="overflow-y:auto;padding:10px 0;">
            <?php foreach ($group_members as $m): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:8px 18px;">
                <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;">
                    <?php echo mb_strtoupper(mb_substr($m['name'], 0, 1)); ?>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:13px;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?php echo htmlspecialchars($m['name']); ?>
                        <?php if ((int)$m['user_id'] === (int)$_SESSION['user_id']): ?>
                            <span style="font-size:10px;font-weight:600;background:#dbeafe;color:#1e40af;padding:1px 6px;border-radius:10px;margin-left:4px;">You</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:11px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?php echo htmlspecialchars($m['department'] ?? ''); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<style>
#groupMembersModal { display: none; }
#groupMembersModal.show-members-modal { display: flex; }
</style>
<script>
/* Close panel when clicking outside */
document.getElementById('groupMembersModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('show-members-modal');
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
