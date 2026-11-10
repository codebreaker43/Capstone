<?php
// REUSABLE TOPBAR NOTIFICATIONS WIDGET FOR ALL PORTALS
// Displays live system alerts, defense schedule notifications, and updates

if (!isset($_SESSION['user_id'])) return;

$widget_uid = (int)$_SESSION['user_id'];
$widget_notifs = get_user_notifications($conn, $widget_uid, 8);
$widget_unread_count = count_unread_notifications($conn, $widget_uid);
?>

<div class="topbar-notif-container" id="notifContainer">
    <button type="button" class="topbar-notif-btn" onclick="toggleNotifDropdown(event)" title="View Notifications">
        <i class="fa-regular fa-bell"></i>
        <?php if($widget_unread_count > 0): ?>
            <span class="notif-badge" id="notifBadgeCount"><?php echo $widget_unread_count > 9 ? '9+' : $widget_unread_count; ?></span>
        <?php endif; ?>
    </button>

    <div class="notif-dropdown-menu" id="notifDropdownMenu" style="display: none;">
        <div class="notif-dropdown-header">
            <div style="display: flex; align-items: center; gap: 0.45rem;">
                <i class="fa-solid fa-bell" style="color: #047857;"></i>
                <strong>Notifications</strong>
                <?php if($widget_unread_count > 0): ?>
                    <span class="notif-unread-count-pill" id="unreadPill"><?php echo $widget_unread_count; ?> New</span>
                <?php endif; ?>
            </div>
            <?php if($widget_unread_count > 0): ?>
                <button type="button" class="btn-mark-all-read" onclick="markAllNotificationsRead(event)">
                    <i class="fa-solid fa-check-double"></i> Mark all read
                </button>
            <?php endif; ?>
        </div>

        <div class="notif-list-scroll" id="notifListScroll">
            <?php if(empty($widget_notifs)): ?>
                <div class="notif-empty-box">
                    <i class="fa-regular fa-bell-slash"></i>
                    <p>No new notifications at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach($widget_notifs as $n): 
                    $is_unread = ($n['is_read'] == 0);
                    $icon_class = 'fa-solid fa-calendar-check';
                    $icon_bg = '#ECFDF5';
                    $icon_color = '#047857';

                    if ($n['type'] === 'announcement') {
                        $icon_class = 'fa-solid fa-bullhorn';
                        $icon_bg = '#EFF6FF';
                        $icon_color = '#1D4ED8';
                    } elseif ($n['type'] === 'comment') {
                        $icon_class = 'fa-solid fa-comment-dots';
                        $icon_bg = '#FEF3C7';
                        $icon_color = '#B45309';
                    }

                    // Relative time
                    $time_diff = time() - strtotime($n['created_at']);
                    $time_ago = 'Just now';
                    if ($time_diff >= 86400) {
                        $time_ago = floor($time_diff / 86400) . 'd ago';
                    } elseif ($time_diff >= 3600) {
                        $time_ago = floor($time_diff / 3600) . 'h ago';
                    } elseif ($time_diff >= 60) {
                        $time_ago = floor($time_diff / 60) . 'm ago';
                    }
                ?>
                    <a href="<?php echo htmlspecialchars($n['link'] ?? '#'); ?>" 
                       class="notif-item <?php echo $is_unread ? 'unread' : ''; ?>"
                       data-unread="<?php echo $is_unread ? '1' : '0'; ?>"
                       onclick="onNotifClick(event, <?php echo $n['id']; ?>, '<?php echo htmlspecialchars($n['link'] ?? ''); ?>', this)">
                        <div class="notif-icon-circle" style="background: <?php echo $icon_bg; ?>; color: <?php echo $icon_color; ?>;">
                            <i class="<?php echo $icon_class; ?>"></i>
                        </div>
                        <div class="notif-content-text">
                            <div class="notif-title-row">
                                <span class="notif-title"><?php echo htmlspecialchars($n['title']); ?></span>
                                <span class="notif-time"><?php echo $time_ago; ?></span>
                            </div>
                            <div class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                        </div>
                        <?php if($is_unread): ?>
                            <span class="unread-dot"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function clearNotifBadgeUI() {
    const badge = document.getElementById('notifBadgeCount');
    if (badge) {
        badge.style.display = 'none';
        badge.remove();
    }
    const pill = document.getElementById('unreadPill');
    if (pill) pill.remove();
    document.querySelectorAll('.unread-dot').forEach(dot => dot.remove());
    document.querySelectorAll('.notif-item.unread').forEach(item => item.classList.remove('unread'));
    const markAllBtn = document.querySelector('.btn-mark-all-read');
    if (markAllBtn) markAllBtn.remove();
}

function sendMarkReadRequest(notifId) {
    const fd = new FormData();
    fd.append('action', 'mark_notifications_read');
    if (notifId) {
        fd.append('notif_id', notifId);
    }
    if (navigator.sendBeacon) {
        navigator.sendBeacon('api.php', fd);
    } else {
        fetch('api.php', { method: 'POST', body: fd, keepalive: true }).catch(err => console.error(err));
    }
}

function decrementNotifBadgeUI(itemEl) {
    const wasUnread = itemEl && (itemEl.classList.contains('unread') || itemEl.getAttribute('data-unread') === '1');
    if (itemEl) {
        itemEl.classList.remove('unread');
        itemEl.setAttribute('data-unread', '0');
        const dot = itemEl.querySelector('.unread-dot');
        if (dot) dot.remove();
    }
    if (!wasUnread) return;

    const badge = document.getElementById('notifBadgeCount');
    if (badge) {
        let cur = parseInt(badge.textContent.replace('+', '').trim(), 10);
        if (isNaN(cur)) cur = 0;
        const next = Math.max(0, cur - 1);
        if (next <= 0) {
            badge.style.display = 'none';
            badge.remove();
        } else {
            badge.textContent = next > 9 ? '9+' : String(next);
        }
    }

    const pill = document.getElementById('unreadPill');
    if (pill) {
        let curP = parseInt(pill.textContent.trim(), 10);
        if (isNaN(curP)) curP = 0;
        const nextP = Math.max(0, curP - 1);
        if (nextP <= 0) {
            pill.remove();
            const markAllBtn = document.querySelector('.btn-mark-all-read');
            if (markAllBtn) markAllBtn.remove();
        } else {
            pill.textContent = nextP + ' New';
        }
    }
}

function toggleNotifDropdown(e) {
    if(e) e.stopPropagation();
    const menu = document.getElementById('notifDropdownMenu');
    if (!menu) return;
    const isShowing = menu.style.display === 'block';
    menu.style.display = isShowing ? 'none' : 'block';
}

function markAllNotificationsRead(e) {
    if(e) e.stopPropagation();
    clearNotifBadgeUI();
    sendMarkReadRequest();
}

function onNotifClick(e, notifId, link, itemEl) {
    if(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Decrement badge counter by 1 for this single clicked item (and remove badge when it reaches 0)
    decrementNotifBadgeUI(itemEl);

    // Persist read status in database for this single notification
    sendMarkReadRequest(notifId);

    if (link && link !== '#' && link !== '') {
        setTimeout(function() {
            window.location.href = link;
        }, 100);
    }
}

// Close when clicking outside
window.addEventListener('click', function(e) {
    const container = document.getElementById('notifContainer');
    const menu = document.getElementById('notifDropdownMenu');
    if (menu && container && !container.contains(e.target)) {
        menu.style.display = 'none';
    }
});
</script>

<style>
/* ── Topbar Notification Dropdown Styles ── */
.topbar-notif-container {
    position: relative;
    display: inline-block;
}

.topbar-notif-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #FFFFFF;
    border: 1.5px solid #CBD5E1;
    color: #334155;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    transition: all 0.2s;
}

.topbar-notif-btn:hover {
    background: #F1F5F9;
    border-color: #047857;
    color: #047857;
}

.notif-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #EF4444;
    color: #FFFFFF;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 1px 5px;
    border-radius: 999px;
    border: 2px solid #FFFFFF;
    line-height: 1.2;
    animation: notifPulse 2s infinite;
}

.notif-dropdown-menu {
    position: absolute;
    top: 48px;
    right: 0;
    width: 360px;
    background: #FFFFFF;
    border: 1px solid #CBD5E1;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
    z-index: 99999;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.notif-dropdown-header {
    background: #F8FAFC;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.88rem;
    color: #0F172A;
}

.notif-unread-count-pill {
    background: #ECFDF5;
    color: #047857;
    border: 1px solid #A7F3D0;
    font-size: 0.7rem;
    font-weight: 800;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
}

.btn-mark-all-read {
    background: transparent;
    border: none;
    color: #047857;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    padding: 0;
}

.btn-mark-all-read:hover {
    text-decoration: underline;
}

.notif-list-scroll {
    max-height: 380px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.notif-empty-box {
    padding: 2.5rem 1rem;
    text-align: center;
    color: #94A3B8;
}

.notif-empty-box i {
    font-size: 2.2rem;
    margin-bottom: 0.5rem;
    display: block;
}

.notif-empty-box p {
    margin: 0;
    font-size: 0.84rem;
}

.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #F1F5F9;
    text-decoration: none;
    color: inherit;
    transition: background 0.15s;
    position: relative;
}

.notif-item:hover {
    background: #F8FAFC;
}

.notif-item.unread {
    background: #F0FDF4;
}

.notif-icon-circle {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.notif-content-text {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.notif-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.notif-title {
    font-size: 0.84rem;
    font-weight: 800;
    color: #0F172A;
}

.notif-time {
    font-size: 0.72rem;
    color: #94A3B8;
    white-space: nowrap;
}

.notif-msg {
    font-size: 0.78rem;
    color: #475569;
    line-height: 1.4;
}

.unread-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10B981;
    flex-shrink: 0;
    margin-top: 0.4rem;
}

@keyframes notifPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.15); }
    100% { transform: scale(1); }
}
</style>
