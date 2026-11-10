<?php
// ── Fetch the student's or group's assigned adviser (if any) ─────────────────────────
$ann_adv_where = (!empty($group_id)) ? "(m.group_id = $group_id OR m.student_id IN ($group_student_ids_str))" : "m.student_id = $uid";
$accepted_req = $conn->query("
    SELECT m.adviser_id, u.name AS adviser_name, ap.department
    FROM match_request m
    JOIN user u ON m.adviser_id = u.id
    LEFT JOIN adviser_profile ap ON ap.user_id = m.adviser_id
    WHERE $ann_adv_where AND m.status = 'accepted'
    LIMIT 1
")->fetch_assoc();

$my_adviser_id = $accepted_req['adviser_id'] ?? 0;
$adviser_name  = $accepted_req['adviser_name'] ?? '';
$department    = $accepted_req['department'] ?? '';

// ── Fetch announcements ONLY from this student's assigned adviser ──────────
if ($my_adviser_id > 0) {
    $ann_query = "
        SELECT a.*, u.name AS adviser_name
        FROM announcements a
        JOIN user u ON a.adviser_id = u.id
        WHERE a.adviser_id = $my_adviser_id
        ORDER BY a.created_at DESC
    ";
    $ann_res   = $conn->query($ann_query);
    $ann_total = $ann_res ? $ann_res->num_rows : 0;
} else {
    $ann_res   = false;
    $ann_total = 0;
}

// ── Count new announcements (last 24h) ────────────────────────────────────
$new_count = 0;
if ($ann_res && $ann_total > 0) {
    $ann_res->data_seek(0);
    while ($r = $ann_res->fetch_assoc()) {
        if (strtotime($r['created_at']) >= strtotime('-24 hours')) $new_count++;
    }
    $ann_res->data_seek(0);
}

// ── Filter ('all' or 'new') ───────────────────────────────────────────────
$filter = $_GET['ann_filter'] ?? 'all';
?>

<style>
/* ─── Student Announcements (CSU CEIT Green Theme) ────────────── */
.sans-banner {
    background: linear-gradient(135deg, #064e3b 0%, #065f46 60%, #047857 100%);
    border-radius: 16px;
    padding: 1.5rem 2rem;
    margin-bottom: 1.5rem;
    display: flex; align-items: center; justify-content: space-between;
    gap: 1rem;
    box-shadow: 0 8px 24px rgba(6,78,59,0.22);
    position: relative; overflow: hidden;
}
.sans-banner::after {
    content: '';
    position: absolute; right: -40px; top: -40px;
    width: 180px; height: 180px; border-radius: 50%;
    background: rgba(255,255,255,0.06);
    pointer-events: none;
}
.sans-banner-left { display: flex; align-items: center; gap: 1rem; }
.sans-banner-icon {
    width: 52px; height: 52px; border-radius: 14px;
    background: rgba(255,255,255,0.14);
    border: 1px solid rgba(255,255,255,0.22);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem; color: #fff; flex-shrink: 0;
}
.sans-banner-title {
    font-family: 'Outfit', 'Inter', sans-serif;
    font-size: 1.4rem; font-weight: 800;
    color: #fff; line-height: 1.1; letter-spacing: -0.3px;
}
.sans-banner-sub { font-size: 0.82rem; color: #a7f3d0; margin-top: 0.2rem; }
.sans-new-badge {
    background: #f59e0b; color: #022c22;
    font-size: 0.75rem; font-weight: 800;
    padding: 0.35rem 0.85rem; border-radius: 999px;
    white-space: nowrap;
    animation: badgePop 0.4s cubic-bezier(0.16,1,0.3,1);
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(245,158,11,0.3);
}
@keyframes badgePop {
    from { transform: scale(0); }
    to   { transform: scale(1); }
}

/* ─── Filter pills ─── */
.sans-filters {
    display: flex; gap: 0.6rem; flex-wrap: wrap;
    margin-bottom: 1.25rem;
}
.sans-filter-pill {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.42rem 1rem; border-radius: 999px;
    font-size: 0.78rem; font-weight: 700;
    text-decoration: none; transition: all 0.2s;
    border: 1.5px solid transparent;
}
.sans-filter-pill.active-pill {
    background: linear-gradient(135deg, #059669, #047857);
    color: #fff;
    box-shadow: 0 3px 10px rgba(5,150,105,0.3);
}
.sans-filter-pill:not(.active-pill) {
    background: #fff; color: #475569;
    border-color: #e2e8f0;
}
.sans-filter-pill:not(.active-pill):hover {
    border-color: #a7f3d0; color: #047857; background: #ecfdf5;
}

/* ─── Card list ─── */
.sans-card {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 16px; overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    margin-bottom: 0;
}

/* ─── Announcement item ─── */
.sans-item {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
    position: relative;
}
.sans-item:last-child { border-bottom: none; }
.sans-item:hover { background: #f0fdf4; }
.sans-item.is-new::before {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #059669, #10b981);
    border-radius: 4px 0 0 4px;
}

.sans-item-head {
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 0.75rem;
    margin-bottom: 0.5rem;
}
.sans-item-title {
    font-weight: 800; color: #0f172a;
    font-size: 0.98rem; line-height: 1.3;
}
.sans-item-title-wrap { display: flex; align-items: center; gap: 0.55rem; flex-wrap: wrap; }
.sans-new-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #059669; flex-shrink: 0;
    box-shadow: 0 0 0 3px rgba(5,150,105,0.2);
}

.sans-item-body {
    font-size: 0.88rem; color: #334155;
    line-height: 1.65; white-space: pre-wrap;
    margin-bottom: 0.75rem;
}

.sans-item-meta {
    display: flex; align-items: center; gap: 0.75rem;
    flex-wrap: wrap;
}
.sans-adviser-badge {
    display: inline-flex; align-items: center; gap: 0.35rem;
    background: #ecfdf5; color: #059669;
    font-size: 0.72rem; font-weight: 700;
    padding: 0.22rem 0.65rem; border-radius: 8px;
    border: 1px solid #a7f3d0;
}
.sans-audience-tag {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.7rem; font-weight: 700;
    padding: 0.2rem 0.6rem; border-radius: 6px;
    background: #f0fdf4; color: #065f46; border: 1px solid #bbf7d0;
}
.sans-item-date { font-size: 0.72rem; color: #94a3b8; }

/* ─── Empty ─── */
.sans-empty {
    text-align: center; padding: 3.5rem 2rem;
    color: #94a3b8;
}
.sans-empty i { font-size: 2.8rem; display: block; margin-bottom: 0.85rem; color: #cbd5e1; }
.sans-empty h3 { font-size: 1rem; font-weight: 700; color: #64748b; margin: 0 0 0.35rem; }
.sans-empty p  { font-size: 0.855rem; margin: 0; line-height: 1.5; }

/* ─── No adviser notice ─── */
.sans-notice {
    background: #fffbeb; border: 1px solid #fde68a;
    border-left: 4px solid #f59e0b;
    border-radius: 12px; padding: 1rem 1.25rem;
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    font-size: 0.865rem; color: #92400e;
    margin-bottom: 1.25rem;
    font-weight: 500;
    flex-wrap: wrap;
}
.sans-notice-link {
    display: inline-flex; align-items: center; gap: 0.4rem;
    background: #047857; color: #fff;
    padding: 0.45rem 1rem; border-radius: 8px;
    font-size: 0.78rem; font-weight: 700;
    text-decoration: none; transition: background 0.18s;
}
.sans-notice-link:hover { background: #065f46; }
</style>

<!-- ═══ BANNER ═══════════════════════════════════════════════════════════ -->
<div class="sans-banner">
    <div class="sans-banner-left">
        <div class="sans-banner-icon"><i class="fa-solid fa-bullhorn"></i></div>
        <div>
            <div class="sans-banner-title">Adviser Announcements</div>
            <div class="sans-banner-sub">
                <?php if ($my_adviser_id > 0): ?>
                    Exclusive announcements from your adviser: <strong><?= htmlspecialchars($adviser_name) ?></strong>
                <?php else: ?>
                    Announcements from your assigned capstone adviser
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($new_count > 0): ?>
    <span class="sans-new-badge"><i class="fa-solid fa-bell" style="margin-right:0.35rem;"></i><?= $new_count ?> New</span>
    <?php endif; ?>
</div>

<!-- ═══ NO ADVISER NOTICE ═══════════════════════════════════════════════ -->
<?php if ($my_adviser_id === 0): ?>
<div class="sans-notice">
    <div style="display:flex; align-items:center; gap:0.65rem;">
        <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;font-size:1.15rem;flex-shrink:0;"></i>
        <div>
            <strong>No assigned adviser yet.</strong><br>
            Announcements are exclusive to advisees. Once an adviser accepts your match request, their announcements will appear here.
        </div>
    </div>
    <a href="?tab=matching" class="sans-notice-link"><i class="fa-solid fa-magnifying-glass"></i> Find Adviser</a>
</div>
<?php endif; ?>

<!-- ═══ FILTER PILLS (Only if student has an adviser) ════════════════════ -->
<?php if ($my_adviser_id > 0): ?>
<div class="sans-filters">
    <a href="?tab=announcements&ann_filter=all" class="sans-filter-pill <?= $filter==='all' ? 'active-pill' : '' ?>">
        <i class="fa-solid fa-layer-group"></i> All (<?= $ann_total ?>)
    </a>
    <a href="?tab=announcements&ann_filter=new" class="sans-filter-pill <?= $filter==='new' ? 'active-pill' : '' ?>">
        <i class="fa-solid fa-fire"></i> New (<?= $new_count ?>)
    </a>
</div>
<?php endif; ?>

<!-- ═══ ANNOUNCEMENTS LIST ═══════════════════════════════════════════════ -->
<div class="sans-card">
<?php
if ($my_adviser_id > 0 && $ann_res):
    $ann_res->data_seek(0);
    $shown = 0;
    while ($ann = $ann_res->fetch_assoc()):
        $is_new = strtotime($ann['created_at']) >= strtotime('-24 hours');

        // Apply filter
        if ($filter === 'new' && !$is_new) continue;

        $shown++;
?>
<div class="sans-item <?= $is_new ? 'is-new' : '' ?>">
    <div class="sans-item-head">
        <div class="sans-item-title-wrap">
            <?php if ($is_new): ?><span class="sans-new-dot" title="Posted in last 24h"></span><?php endif; ?>
            <div class="sans-item-title"><?= htmlspecialchars($ann['title']) ?></div>
        </div>
    </div>

    <div class="sans-item-body"><?= htmlspecialchars($ann['body']) ?></div>

    <div class="sans-item-meta">
        <span class="sans-adviser-badge">
            <i class="fa-solid fa-chalkboard-user" style="font-size:0.65rem;"></i>
            Adviser: <?= htmlspecialchars($ann['adviser_name']) ?>
        </span>
        <span class="sans-audience-tag">
            <i class="fa-solid fa-user-graduate" style="font-size:0.65rem;"></i> Advisees Only
        </span>
        <span class="sans-item-date">
            <i class="fa-regular fa-clock" style="font-size:0.65rem;"></i>
            <?= date('M j, Y • g:i A', strtotime($ann['created_at'])) ?>
        </span>
    </div>
</div>
<?php endwhile; ?>

<?php if ($shown === 0): ?>
<div class="sans-empty">
    <i class="fa-regular fa-bell-slash"></i>
    <h3>No announcements yet</h3>
    <p>
        <?php if ($filter === 'new'): ?>
            No new announcements in the last 24 hours. <a href="?tab=announcements" style="color:#047857;font-weight:700;">View all</a>
        <?php else: ?>
            Your adviser (<strong><?= htmlspecialchars($adviser_name) ?></strong>) hasn't posted any announcements yet.
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<?php else: ?>
<div class="sans-empty">
    <i class="fa-solid fa-user-xmark"></i>
    <h3>No Adviser Assigned</h3>
    <p>Announcements are sent directly from your adviser to their accepted students.<br>Please match with an adviser to see announcements here.</p>
</div>
<?php endif; ?>
</div>
