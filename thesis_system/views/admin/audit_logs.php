<?php
// Ensure database connection and admin role check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit('Unauthorized access');
}

// ─── Filter Parameters ──────────────────────────────────────────────────────────
$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$category  = isset($_GET['category']) ? trim($_GET['category']) : '';
$role_f    = isset($_GET['user_role']) ? trim($_GET['user_role']) : '';
$status_f  = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_f    = isset($_GET['date_range']) ? trim($_GET['date_range']) : 'all';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to   = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$page      = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$limit     = isset($_GET['limit']) ? max(2, min(100, (int)$_GET['limit'])) : 4; // Default 4 records so multiple pages are active and functional
$offset    = ($page - 1) * $limit;

// ─── Dynamic System Filter Options ─────────────────────────────────────────────
// Fetch only categories that actually exist in the system's database
$sys_categories = [];
$cat_q = $conn->query("SELECT DISTINCT category FROM audit_logs WHERE category IS NOT NULL AND category != '' AND category != 'System' ORDER BY category ASC");
if ($cat_q) {
    while ($crow = $cat_q->fetch_row()) {
        $sys_categories[] = $crow[0];
    }
}
$default_system_cats = ['Authentication', 'User Management', 'Faculty Approvals', 'Capstone & Panel', 'Settings & Security'];
$sys_categories = array_values(array_unique(array_merge($sys_categories, $default_system_cats)));
sort($sys_categories);

// Only the official user roles defined in the system (user table)
$sys_roles = [
    'admin'   => 'Admin',
    'adviser' => 'Adviser',
    'student' => 'Student',
    'panel'   => 'Panel'
];

function format_role_label($role) {
    $map = [
        'admin'   => 'Admin',
        'adviser' => 'Adviser',
        'student' => 'Student',
        'panel'   => 'Panel'
    ];
    return $map[strtolower($role)] ?? ucfirst($role);
}

// ─── Build Query Conditions ───────────────────────────────────────────────────
$where = ["1=1"];

if ($search !== '') {
    $s_esc = $conn->real_escape_string($search);
    $where[] = "(user_name LIKE '%$s_esc%' OR action LIKE '%$s_esc%' OR description LIKE '%$s_esc%' OR ip_address LIKE '%$s_esc%')";
}
if ($category !== '' && $category !== 'all') {
    $c_esc = $conn->real_escape_string($category);
    $where[] = "category = '$c_esc'";
}
if ($role_f !== '' && $role_f !== 'all') {
    $r_esc = $conn->real_escape_string($role_f);
    $where[] = "user_role = '$r_esc'";
}
if ($status_f !== '' && $status_f !== 'all') {
    $st_esc = $conn->real_escape_string($status_f);
    $where[] = "status = '$st_esc'";
}

// Timeframe Filter Handling
if ($date_f === 'today') {
    $where[] = "DATE(created_at) = CURDATE()";
} elseif ($date_f === 'yesterday') {
    $where[] = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($date_f === '7days') {
    $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($date_f === 'this_month') {
    $where[] = "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
} elseif ($date_f === '30days') {
    $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($date_f === 'custom') {
    if (!empty($date_from)) {
        $df_esc = $conn->real_escape_string($date_from);
        $where[] = "DATE(created_at) >= '$df_esc'";
    }
    if (!empty($date_to)) {
        $dt_esc = $conn->real_escape_string($date_to);
        $where[] = "DATE(created_at) <= '$dt_esc'";
    }
}

$where_clause = implode(' AND ', $where);

// ─── KPI Metrics ──────────────────────────────────────────────────────────────
$total_logs = (int)($conn->query("SELECT COUNT(*) as c FROM audit_logs")->fetch_assoc()['c'] ?? 0);
$admin_logs = (int)($conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE user_role='admin'")->fetch_assoc()['c'] ?? 0);
$auth_logs  = (int)($conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE category='Authentication'")->fetch_assoc()['c'] ?? 0);
$crit_logs  = (int)($conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE status='failed' OR action LIKE '%DELETE%' OR action LIKE '%REJECT%' OR action LIKE '%ARCHIVE%'")->fetch_assoc()['c'] ?? 0);

// Filtered count
$filtered_count = (int)($conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE $where_clause")->fetch_assoc()['c'] ?? 0);
$total_pages    = max(1, (int)ceil($filtered_count / $limit));

// Fetch paginated results
$logs_res = $conn->query("SELECT * FROM audit_logs WHERE $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

// Helper for relative time with local timezone alignment
function time_ago_str($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . " min ago";
    if ($diff < 86400) return floor($diff / 3600) . " hr ago";
    if ($diff < 604800) return floor($diff / 86400) . " d ago";
    return date('M j, Y', $timestamp);
}
?>

<style>
/* ── Audit Logs Custom Styling ── */
.audit-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* ── Banner (Plastar & Clean) ── */
.audit-banner {
    background: linear-gradient(135deg, #064E3B 0%, #065F46 60%, #047857 100%);
    border-radius: 16px;
    padding: 1.35rem 1.85rem;
    color: #ffffff;
    box-shadow: 0 8px 24px rgba(6, 78, 59, 0.25);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.audit-banner::after {
    content: '';
    position: absolute;
    right: -30px;
    bottom: -40px;
    width: 180px;
    height: 180px;
    background: radial-gradient(circle, rgba(245, 158, 11, 0.2) 0%, transparent 70%);
    pointer-events: none;
}

.audit-banner-left {
    display: flex;
    align-items: center;
    gap: 1.15rem;
}

.audit-banner-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.22);
    flex-shrink: 0;
}

.audit-banner h1 {
    font-size: 1.4rem;
    font-weight: 800;
    margin: 0 0 0.25rem 0;
    color: #ffffff;
    line-height: 1.2;
}

.audit-banner p {
    font-size: 0.85rem;
    color: #A7F3D0;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}

.btn-print-audit-header {
    background: #ffffff;
    color: #064E3B;
    border: none;
    padding: 0.65rem 1.35rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    transition: all 0.2s ease;
    white-space: nowrap;
    flex-shrink: 0;
}

.btn-print-audit-header:hover {
    background: #F0FDF4;
    color: #047857;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
}

/* ── KPI Stats Grid ── */
.audit-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
}

.audit-stat-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    border: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.audit-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
}

.audit-stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.audit-stat-icon.total { background: #ECFDF5; color: #059669; }
.audit-stat-icon.admin { background: #FEF3C7; color: #D97706; }
.audit-stat-icon.auth  { background: #EFF6FF; color: #2563EB; }
.audit-stat-icon.crit  { background: #FEE2E2; color: #DC2626; }

.audit-stat-val {
    font-size: 1.85rem;
    font-weight: 800;
    color: #0F172A;
    line-height: 1;
    margin-bottom: 0.35rem;
}

.audit-stat-lbl {
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ── Filter Card ── */
.audit-filter-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    border: 1px solid #E2E8F0;
}

.audit-filter-form {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1.2fr auto;
    gap: 0.85rem;
    align-items: end;
}

@media (max-width: 1200px) {
    .audit-filter-form {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 640px) {
    .audit-filter-form {
        grid-template-columns: 1fr;
    }
}

.audit-filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.audit-filter-group label {
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.audit-filter-input,
.audit-filter-select {
    width: 100%;
    padding: 0.65rem 0.85rem;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    font-size: 0.88rem;
    color: #1E293B;
    background: #F8FAFC;
    transition: all 0.2s ease;
}

.audit-filter-input:focus,
.audit-filter-select:focus {
    background: #ffffff;
    border-color: #059669;
    outline: none;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
}

/* ── Table Card ── */
.audit-table-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    border: 1px solid #E2E8F0;
    overflow: hidden;
}

.audit-table-header {
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #E2E8F0;
    background: #FAFAFA;
}

.audit-table-header h2 {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0F172A;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin: 0;
}

.audit-data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}

.audit-data-table th {
    background: #F1F5F9;
    padding: 0.85rem 1rem;
    text-align: left;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.6px;
    border-bottom: 2px solid #E2E8F0;
}

.audit-data-table td {
    padding: 0.95rem 1rem;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: middle;
    color: #334155;
}

.audit-data-table tr:hover td {
    background-color: #F8FAFC;
}

/* User Pill */
.audit-user-cell {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.audit-user-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.audit-user-avatar.admin   { background: linear-gradient(135deg, #D97706 0%, #B45309 100%); }
.audit-user-avatar.adviser { background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); }
.audit-user-avatar.student { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
.audit-user-avatar.panel   { background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%); }
.audit-user-avatar.system  { background: linear-gradient(135deg, #475569 0%, #334155 100%); }

.audit-user-name {
    font-weight: 600;
    color: #0F172A;
    line-height: 1.2;
}

.audit-role-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 999px;
    text-transform: uppercase;
    display: inline-block;
    margin-top: 2px;
}

.audit-role-badge.admin   { background: #FEF3C7; color: #92400E; }
.audit-role-badge.adviser { background: #DBEAFE; color: #1E40AF; }
.audit-role-badge.student { background: #D1FAE5; color: #065F46; }
.audit-role-badge.panel   { background: #EDE9FE; color: #5B21B6; }
.audit-role-badge.guest,
.audit-role-badge.system  { background: #F1F5F9; color: #475569; }

/* Action Tag */
.audit-action-tag {
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 2px 8px;
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    color: #1E293B;
    display: inline-block;
}

.audit-cat-pill {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748B;
    display: block;
    margin-top: 4px;
}

/* Status Badges */
.audit-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: capitalize;
}

.audit-status-badge.success { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
.audit-status-badge.warning { background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; }
.audit-status-badge.failed  { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; }

/* Buttons */
.btn-audit-inspect {
    padding: 0.45rem 0.75rem;
    background: #F8FAFC;
    color: #334155;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.2s ease;
}

.btn-audit-inspect:hover {
    background: #059669;
    color: #ffffff;
    border-color: #059669;
}

/* Pagination */
.audit-pagination-wrap {
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #FAFAFA;
    border-top: 1px solid #E2E8F0;
    font-size: 0.85rem;
    color: #64748B;
}

.audit-page-links {
    display: flex;
    gap: 0.4rem;
}

.audit-page-btn {
    padding: 0.4rem 0.8rem;
    border: 1px solid #CBD5E1;
    background: #ffffff;
    color: #334155;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}

.audit-page-btn.active {
    background: #059669;
    color: #ffffff;
    border-color: #059669;
}

.audit-page-btn:hover:not(.active):not(.disabled) {
    background: #F1F5F9;
    border-color: #94A3B8;
}

.audit-page-btn.disabled {
    opacity: 0.5;
    pointer-events: none;
}

/* Modal styling */
.audit-modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
}

.audit-modal-content {
    background: #ffffff;
    border-radius: 16px;
    max-width: 650px;
    width: 90%;
    box-shadow: 0 20px 40px rgba(0,0,0,0.25);
    overflow: hidden;
    animation: modalSlideUp 0.3s ease-out;
}

@keyframes modalSlideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.audit-modal-header {
    background: linear-gradient(135deg, #064E3B 0%, #022C22 100%);
    color: #ffffff;
    padding: 1.25rem 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.audit-modal-header h3 {
    margin: 0;
    font-size: 1.15rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.audit-modal-close {
    background: none;
    border: none;
    color: #A7F3D0;
    font-size: 1.4rem;
    cursor: pointer;
    line-height: 1;
}

.audit-modal-close:hover {
    color: #ffffff;
}

.audit-modal-body {
    padding: 1.75rem;
    max-height: 70vh;
    overflow-y: auto;
}

.audit-modal-field {
    display: flex;
    margin-bottom: 0.85rem;
    border-bottom: 1px solid #F1F5F9;
    padding-bottom: 0.6rem;
}

.audit-modal-label {
    width: 140px;
    font-weight: 700;
    color: #64748B;
    font-size: 0.82rem;
    text-transform: uppercase;
    flex-shrink: 0;
}

.audit-modal-val {
    flex-grow: 1;
    color: #1E293B;
    font-size: 0.9rem;
    word-break: break-word;
}

.audit-modal-json {
    background: #0F172A;
    color: #38BDF8;
    padding: 1rem;
    border-radius: 8px;
    font-family: monospace;
    font-size: 0.82rem;
    overflow-x: auto;
    white-space: pre-wrap;
    margin-top: 0.5rem;
}

/* Print Only Styles */
@media print {
    body * {
        visibility: hidden;
    }
    #auditPrintArea, #auditPrintArea * {
        visibility: visible;
    }
    #auditPrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .audit-banner-right, .audit-filter-card, .audit-pagination-wrap, .btn-audit-inspect, .sidebar, .topbar {
        display: none !important;
    }
}
</style>

<div class="audit-container" id="auditPrintArea">
    
    <!-- Top Page Banner with Integrated Print Button -->
    <div class="audit-banner">
        <div class="audit-banner-left">
            <div class="audit-banner-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <h1>System Audit Logs</h1>
                <p><i class="fa-solid fa-shield-halved"></i> Comprehensive forensic activity tracking & compliance trail &bull; CSU Cabadbaran</p>
            </div>
        </div>
        <div class="audit-banner-right">
            <button type="button" class="btn-print-audit-header" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print Audit Report
            </button>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="audit-stats-grid">
        <div class="audit-stat-card">
            <div class="audit-stat-icon total">
                <i class="fa-solid fa-list-check"></i>
            </div>
            <div>
                <div class="audit-stat-val"><?= number_format($total_logs) ?></div>
                <div class="audit-stat-lbl">Total Activities Logged</div>
            </div>
        </div>

        <div class="audit-stat-card">
            <div class="audit-stat-icon admin">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <div>
                <div class="audit-stat-val"><?= number_format($admin_logs) ?></div>
                <div class="audit-stat-lbl">Admin Operations</div>
            </div>
        </div>

        <div class="audit-stat-card">
            <div class="audit-stat-icon auth">
                <i class="fa-solid fa-key"></i>
            </div>
            <div>
                <div class="audit-stat-val"><?= number_format($auth_logs) ?></div>
                <div class="audit-stat-lbl">Authentication Events</div>
            </div>
        </div>

        <div class="audit-stat-card">
            <div class="audit-stat-icon crit">
                <i class="fa-solid fa-triangle-exclamation" style="color: #DC2626;"></i>
            </div>
            <div>
                <div class="audit-stat-val"><?= number_format($crit_logs) ?></div>
                <div class="audit-stat-lbl">Security Alerts</div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar (Populated Dynamically from System) -->
    <div class="audit-filter-card">
        <form method="GET" action="" class="audit-filter-form">
            <input type="hidden" name="tab" value="audit_logs">

            <div class="audit-filter-group">
                <label><i class="fa-solid fa-magnifying-glass"></i> Search Logs</label>
                <input type="text" name="search" class="audit-filter-input" placeholder="Search user, action, description, IP..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="audit-filter-group">
                <label><i class="fa-solid fa-folder-tree"></i> Category</label>
                <select name="category" class="audit-filter-select">
                    <option value="all">All Categories</option>
                    <?php foreach ($sys_categories as $scat): ?>
                        <option value="<?= htmlspecialchars($scat) ?>" <?= $category === $scat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($scat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="audit-filter-group">
                <label><i class="fa-solid fa-user-tag"></i> User Role</label>
                <select name="user_role" class="audit-filter-select">
                    <option value="all">All Roles</option>
                    <?php foreach ($sys_roles as $srole_key => $srole_lbl): ?>
                        <option value="<?= htmlspecialchars($srole_key) ?>" <?= $role_f === $srole_key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($srole_lbl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="audit-filter-group">
                <label><i class="fa-solid fa-circle-check"></i> Status</label>
                <select name="status" class="audit-filter-select">
                    <option value="all">All Statuses</option>
                    <option value="success" <?= $status_f === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="warning" <?= $status_f === 'warning' ? 'selected' : '' ?>>Warning</option>
                    <option value="failed" <?= $status_f === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>

            <div class="audit-filter-group">
                <label><i class="fa-solid fa-calendar-days"></i> Timeframe</label>
                <select name="date_range" id="timeframeSelect" class="audit-filter-select" onchange="toggleCustomDates(this.value)">
                    <option value="all" <?= $date_f === 'all' ? 'selected' : '' ?>>All Time</option>
                    <option value="today" <?= $date_f === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="yesterday" <?= $date_f === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                    <option value="7days" <?= $date_f === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                    <option value="this_month" <?= $date_f === 'this_month' ? 'selected' : '' ?>>This Month</option>
                    <option value="30days" <?= $date_f === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                    <option value="custom" <?= $date_f === 'custom' ? 'selected' : '' ?>>Custom Range...</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn-primary" style="padding: 0.65rem 1.25rem;">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <?php if ($search !== '' || ($category !== '' && $category !== 'all') || ($role_f !== '' && $role_f !== 'all') || ($status_f !== '' && $status_f !== 'all') || $date_f !== 'all'): ?>
                    <a href="?tab=audit_logs" class="btn" style="padding: 0.65rem 0.9rem; background: #E2E8F0; color: #475569; border-radius: 8px;" title="Reset filters">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Custom Date Range Picker Inputs (Revealed when Custom Range is selected) -->
            <div id="customDateWrap" style="display: <?= $date_f === 'custom' ? 'flex' : 'none' ?>; gap: 1rem; grid-column: 1 / -1; margin-top: 0.5rem; padding-top: 0.75rem; border-top: 1px dashed #CBD5E1; align-items: center; flex-wrap: wrap;">
                <span style="font-size: 0.82rem; font-weight: 700; color: #065F46;">
                    <i class="fa-solid fa-calendar-range"></i> Specific Date Range:
                </span>
                <div style="display: flex; align-items: center; gap: 0.4rem;">
                    <label style="font-size: 0.8rem; color: #64748B; font-weight: 600; text-transform: none;">From Date:</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="audit-filter-input" style="width: auto; padding: 0.45rem 0.75rem;">
                </div>
                <div style="display: flex; align-items: center; gap: 0.4rem;">
                    <label style="font-size: 0.8rem; color: #64748B; font-weight: 600; text-transform: none;">To Date:</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="audit-filter-input" style="width: auto; padding: 0.45rem 0.75rem;">
                </div>
            </div>
        </form>
    </div>

    <!-- Audit Logs Table -->
    <div class="audit-table-card">
        <div class="audit-table-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
            <h2>
                <i class="fa-solid fa-table-list" style="color: #059669;"></i>
                Audit Trail Entries
                <span style="font-size: 0.85rem; font-weight: 500; color: #64748B; margin-left: 0.5rem;">(<?= number_format($filtered_count) ?> matching results)</span>
            </h2>
            <form method="GET" action="" style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; color: #64748B; margin: 0;">
                <input type="hidden" name="tab" value="audit_logs">
                <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>"><?php endif; ?>
                <?php if ($role_f !== ''): ?><input type="hidden" name="user_role" value="<?= htmlspecialchars($role_f) ?>"><?php endif; ?>
                <?php if ($date_f !== ''): ?><input type="hidden" name="date_range" value="<?= htmlspecialchars($date_f) ?>"><?php endif; ?>
                <span>Show:</span>
                <select name="limit" onchange="this.form.submit()" style="padding: 0.35rem 0.6rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem; background: #FFFFFF; color: #334155; cursor: pointer;">
                    <option value="4" <?= $limit == 4 ? 'selected' : '' ?>>4 per page</option>
                    <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10 per page</option>
                    <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25 per page</option>
                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 per page</option>
                </select>
            </form>
        </div>

        <div style="overflow-x: auto;">
            <table class="audit-data-table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Timestamp</th>
                        <th style="width: 220px;">Actor / User</th>
                        <th style="width: 170px;">Event Action</th>
                        <th>Description & Context</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 130px;">IP Address</th>
                        <th style="width: 80px; text-align: center;">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs_res && $logs_res->num_rows > 0): ?>
                        <?php while ($log = $logs_res->fetch_assoc()): ?>
                            <?php
                                $role_class = strtolower($log['user_role']);
                                $status_class = strtolower($log['status']);
                                $initial = strtoupper(substr($log['user_name'], 0, 1));
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: #0F172A; white-space: nowrap;">
                                        <?= date('M d, Y', strtotime($log['created_at'])) ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #64748B; white-space: nowrap;">
                                        <?= date('h:i:s A', strtotime($log['created_at'])) ?> &bull; <?= time_ago_str($log['created_at']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="audit-user-cell">
                                        <div class="audit-user-avatar <?= $role_class ?>">
                                            <?= htmlspecialchars($initial) ?>
                                        </div>
                                        <div>
                                            <div class="audit-user-name"><?= htmlspecialchars($log['user_name']) ?></div>
                                            <?php if (!empty($log['user_role'])): ?>
                                                <span class="audit-role-badge <?= $role_class ?>">
                                                    <?= htmlspecialchars(format_role_label($log['user_role'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="audit-action-tag"><?= htmlspecialchars($log['action']) ?></span>
                                    <span class="audit-cat-pill">
                                        <i class="fa-solid fa-tag" style="font-size: 0.65rem;"></i> <?= htmlspecialchars($log['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="line-height: 1.4; color: #1E293B;">
                                        <?= htmlspecialchars($log['description']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="audit-status-badge <?= $status_class ?>">
                                        <?php if ($status_class === 'success'): ?>
                                            <i class="fa-solid fa-circle-check"></i> Success
                                        <?php elseif ($status_class === 'warning'): ?>
                                            <i class="fa-solid fa-triangle-exclamation"></i> Warning
                                        <?php else: ?>
                                            <i class="fa-solid fa-circle-xmark"></i> Failed
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-family: monospace; font-size: 0.82rem; color: #475569; background: #F1F5F9; padding: 2px 6px; border-radius: 4px;">
                                        <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-audit-inspect" onclick='openAuditModal(<?= json_encode($log, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="View Full Event Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem; color: #94A3B8;">
                                <i class="fa-solid fa-clipboard-question fa-3x" style="margin-bottom: 0.75rem; color: #CBD5E1;"></i>
                                <div style="font-size: 1.1rem; font-weight: 600; color: #64748B;">No audit records found matching your filters</div>
                                <p style="font-size: 0.85rem; margin-top: 0.25rem;">Try adjusting your search criteria or resetting filters.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <?php if ($filtered_count > 0): ?>
            <div class="audit-pagination-wrap">
                <div>
                    Showing <strong><?= min($filtered_count, $offset + 1) ?></strong> to <strong><?= min($filtered_count, $offset + $logs_res->num_rows) ?></strong> of <strong><?= number_format($filtered_count) ?></strong> events
                </div>
                <div class="audit-page-links">
                    <?php
                        $query_params = $_GET;
                        // Prev page
                        $query_params['p'] = max(1, $page - 1);
                        $prev_url = '?' . http_build_query($query_params);
                    ?>
                    <a href="<?= $prev_url ?>" class="audit-page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                        <i class="fa-solid fa-chevron-left"></i> Previous
                    </a>

                    <?php
                        $start_p = max(1, $page - 2);
                        $end_p   = min($total_pages, $page + 2);
                        for ($i = $start_p; $i <= $end_p; $i++):
                            $query_params['p'] = $i;
                            $page_url = '?' . http_build_query($query_params);
                    ?>
                        <a href="<?= $page_url ?>" class="audit-page-btn <?= $page == $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php
                        // Next page
                        $query_params['p'] = min($total_pages, $page + 1);
                        $next_url = '?' . http_build_query($query_params);
                    ?>
                    <a href="<?= $next_url ?>" class="audit-page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Event Details Inspection -->
<div id="auditDetailModal" class="audit-modal">
    <div class="audit-modal-content">
        <div class="audit-modal-header">
            <h3><i class="fa-solid fa-file-shield"></i> Audit Event Forensic Details</h3>
            <button type="button" class="audit-modal-close" onclick="closeAuditModal()">&times;</button>
        </div>
        <div class="audit-modal-body">
            <div class="audit-modal-field">
                <div class="audit-modal-label">Log ID</div>
                <div class="audit-modal-val" id="modalLogId">#</div>
            </div>
            <div class="audit-modal-field">
                <div class="audit-modal-label">Timestamp</div>
                <div class="audit-modal-val" id="modalTimestamp">-</div>
            </div>
            <div class="audit-modal-field">
                <div class="audit-modal-label">Actor / User</div>
                <div class="audit-modal-val" id="modalUser">-</div>
            </div>
            <div class="audit-modal-field">
                <div class="audit-modal-label">User Role</div>
                <div class="audit-modal-val" id="modalRole">-</div>
            </div>
            <div class="audit-modal-field">
                <div class="audit-modal-label">Action Event</div>
                <div class="audit-modal-val" id="modalAction">-</div>
            </div>
            <div class="audit-modal-field">
                <div class="audit-modal-label">Category</div>
                <div class="audit-modal-val" id="modalCategory">-</div>
            </div>
            <div class="audit-modal-field">
                <div class="audit-modal-label">Status</div>
                <div class="audit-modal-val" id="modalStatus">-</div>
            </div>
            <div class="audit-modal-field">
                <div class="audit-modal-label">Description</div>
                <div class="audit-modal-val" id="modalDescription">-</div>
            </div>
            <div class="audit-modal-field">
                <div class="audit-modal-label">IP Address</div>
                <div class="audit-modal-val" id="modalIp">-</div>
            </div>
            <div class="audit-modal-field">
                <div class="audit-modal-label">User Agent</div>
                <div class="audit-modal-val" id="modalUserAgent" style="font-family: monospace; font-size: 0.8rem; color: #475569;">-</div>
            </div>
            <div id="modalDetailsBlock" style="margin-top: 1rem; display: none;">
                <div class="audit-modal-label" style="width: auto; margin-bottom: 0.35rem;">Additional Payload / Metadata</div>
                <pre class="audit-modal-json" id="modalDetails"></pre>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCustomDates(val) {
    const wrap = document.getElementById('customDateWrap');
    if (wrap) {
        wrap.style.display = (val === 'custom') ? 'flex' : 'none';
    }
}

function openAuditModal(log) {
    document.getElementById('modalLogId').innerText = '#' + log.id;
    document.getElementById('modalTimestamp').innerText = log.created_at;
    document.getElementById('modalUser').innerText = log.user_name + (log.user_id ? ' (User ID #' + log.user_id + ')' : '');
    document.getElementById('modalRole').innerText = log.user_role ? log.user_role.toUpperCase() : 'UNKNOWN';
    document.getElementById('modalAction').innerHTML = '<span class="audit-action-tag">' + (log.action || '') + '</span>';
    document.getElementById('modalCategory').innerText = log.category || 'General';
    document.getElementById('modalStatus').innerHTML = '<span class="audit-status-badge ' + (log.status || 'success') + '">' + (log.status || 'success') + '</span>';
    document.getElementById('modalDescription').innerText = log.description || '';
    document.getElementById('modalIp').innerText = log.ip_address || '127.0.0.1';
    document.getElementById('modalUserAgent').innerText = log.user_agent || 'Unknown';

    const detailsBlock = document.getElementById('modalDetailsBlock');
    const detailsPre = document.getElementById('modalDetails');
    if (log.details && log.details.trim() !== '') {
        try {
            const parsed = JSON.parse(log.details);
            detailsPre.innerText = JSON.stringify(parsed, null, 2);
        } catch (e) {
            detailsPre.innerText = log.details;
        }
        detailsBlock.style.display = 'block';
    } else {
        detailsBlock.style.display = 'none';
    }

    const modal = document.getElementById('auditDetailModal');
    modal.style.display = 'flex';
}

function closeAuditModal() {
    document.getElementById('auditDetailModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    const modal = document.getElementById('auditDetailModal');
    if (e.target === modal) {
        closeAuditModal();
    }
});
</script>
