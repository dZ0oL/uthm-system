<?php
// ===================
// Includes/footer.php
// ===================
?>
<?php if (isset($_SESSION['user_id'])): ?>
    </main><!-- /.main-content -->
  </div><!-- /.main-wrapper -->
</div><!-- /.app-layout -->
<?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php if (isset($_SESSION['user_id'])): ?>
    <script src="<?php echo $base; ?>assets/js/ui.js"></script>
    <script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
<?php endif; ?>

<!-- Custom Confirm Dialog -->
<div id="appConfirmBackdrop" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.4);z-index:10000;align-items:center;justify-content:center;">
    <div id="appConfirmCard" style="background:#fff;border-radius:20px;padding:24px 24px 16px;max-width:300px;width:88%;box-shadow:0 12px 40px rgba(0,0,0,.18);font-family:inherit;">
        <div id="appConfirmTitle" style="font-size:15px;font-weight:700;color:#0f172a;margin-bottom:6px;"></div>
        <div id="appConfirmMsg" style="font-size:13px;color:#64748b;line-height:1.55;margin-bottom:20px;"></div>
        <div style="display:flex;justify-content:flex-end;gap:4px;">
            <button id="appConfirmDismiss" style="background:none;border:none;padding:9px 16px;font-size:13px;font-weight:500;color:#94a3b8;cursor:pointer;border-radius:10px;transition:background .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">Cancel</button>
            <button id="appConfirmOk" style="background:none;border:none;padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;border-radius:10px;transition:background .15s;"></button>
        </div>
    </div>
</div>
<script>
function appToast(message, type) {
    var color  = type === 'success' ? '#166534' : type === 'danger' ? '#b91c1c' : '#1e40af';
    var icon   = type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle';
    var toast  = document.createElement('div');
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10001;background:#fff;border-radius:12px;padding:14px 18px;box-shadow:0 8px 32px rgba(0,0,0,.15);font-size:14px;display:flex;align-items:center;gap:10px;max-width:360px;border-left:4px solid ' + color + ';transition:opacity .3s;';
    toast.innerHTML = '<i class="fas fa-' + icon + '" style="color:' + color + ';flex-shrink:0;"></i><span style="color:#0f172a;">' + message + '</span>';
    document.body.appendChild(toast);
    setTimeout(function () {
        toast.style.opacity = '0';
        setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 300);
    }, 3500);
}

function appConfirm(title, message, type, affirmLabel, onConfirm) {
    var backdrop = document.getElementById('appConfirmBackdrop');
    document.getElementById('appConfirmTitle').textContent = title;
    document.getElementById('appConfirmMsg').textContent = message;
    var okBtn = document.getElementById('appConfirmOk');
    var color = type === 'danger' ? '#b91c1c' : type === 'success' ? '#166534' : '#1e40af';
    var hoverBg = type === 'danger' ? '#fee2e2' : type === 'success' ? '#dcfce7' : '#dbeafe';
    okBtn.textContent = affirmLabel || 'Confirm';
    okBtn.style.color = color;
    okBtn.onmouseover = function(){ this.style.background = hoverBg; };
    okBtn.onmouseout  = function(){ this.style.background = 'none'; };
    backdrop.style.display = 'flex';
    function close() { backdrop.style.display = 'none'; }
    document.getElementById('appConfirmDismiss').onclick = close;
    okBtn.onclick = function() { close(); onConfirm(); };
    backdrop.onclick = function(e) { if (e.target === backdrop) close(); };
}
</script>
</body>
</html>
