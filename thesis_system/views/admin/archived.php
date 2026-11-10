<?php
// ─── Archived Users Search & Pagination ────────────────────────────────────────
$search_archived = isset($_GET['search_archived']) ? trim($_GET['search_archived']) : '';
$a_page          = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$a_limit         = 10; // 10 users per page
$a_offset        = ($a_page - 1) * $a_limit;

// Search condition
$a_where = ["u.account_status = 'archived'"];
if ($search_archived !== '') {
    $sa_esc = $conn->real_escape_string($search_archived);
    $a_where[] = "(u.name LIKE '%$sa_esc%' OR u.email LIKE '%$sa_esc%' OR u.role LIKE '%$sa_esc%' OR a.department LIKE '%$sa_esc%' OR s.department LIKE '%$sa_esc%' OR p.department LIKE '%$sa_esc%')";
}
$a_where_clause = implode(' AND ', $a_where);

// Total count
$count_query = "
    SELECT COUNT(*) as c 
    FROM user u
    LEFT JOIN adviser_profile a ON u.id = a.user_id
    LEFT JOIN student_profile s ON u.id = s.user_id
    LEFT JOIN panel_profile p ON u.id = p.user_id
    WHERE $a_where_clause
";
$total_archived_count = (int)($conn->query($count_query)->fetch_assoc()['c'] ?? 0);
$a_total_pages        = max(1, (int)ceil($total_archived_count / $a_limit));

// Fetch paginated archived records
$archived_res = $conn->query("
    SELECT u.*, 
           COALESCE(a.department, s.department, p.department, 'General') AS department,
           COALESCE(a.expertise, p.expertise, '') AS specialization
    FROM user u
    LEFT JOIN adviser_profile a ON u.id = a.user_id
    LEFT JOIN student_profile s ON u.id = s.user_id
    LEFT JOIN panel_profile p ON u.id = p.user_id
    WHERE $a_where_clause
    ORDER BY u.role, u.name
    LIMIT $a_limit OFFSET $a_offset
");
$archived_count = $archived_res ? $archived_res->num_rows : 0;
?>

<div class="banner">
    <h1><i class="fa-solid fa-box-archive"></i> System Archive</h1>
    <p>Manage, restore, or permanently remove archived user accounts.</p>
</div>

<div class="card">
    <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
        <div class="card-header-left" style="display: flex; align-items: center; gap: 0.65rem;">
            <i class="fa-solid fa-users-slash"></i>
            <h2>Archived User Accounts</h2>
            <span class="badge" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; margin-left: 0.35rem;">
                <i class="fa-solid fa-archive"></i> <?= $total_archived_count ?> Archived
            </span>
        </div>

        <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            <!-- Search Form -->
            <form method="GET" action="" style="display: flex; gap: 0.35rem; align-items: center; margin: 0;">
                <input type="hidden" name="tab" value="archived">
                <div style="position: relative;">
                    <input type="text" 
                           name="search_archived" 
                           value="<?= htmlspecialchars($search_archived) ?>" 
                           placeholder="Search archived users..." 
                           style="padding: 0.42rem 0.85rem 0.42rem 2rem; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 0.84rem; width: 210px; background: #F8FAFC;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.7rem; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 0.8rem;"></i>
                </div>
                <button type="submit" class="btn-primary" style="padding: 0.42rem 0.85rem; font-size: 0.82rem;">Filter</button>
                <?php if ($search_archived !== ''): ?>
                    <a href="?tab=archived" class="btn" style="padding: 0.42rem 0.7rem; background: #E2E8F0; color: #475569; border-radius: 8px; font-size: 0.82rem;" title="Clear search">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </form>
        <?php if($total_archived_count > 0): ?>
        <div class="card-header-actions" style="display: flex; align-items: center; gap: 0.65rem;">
            <button type="button" 
                    class="btn" 
                    onclick="openRestoreAllModal(<?= $archived_count ?>)"
                    style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #FFFFFF; font-weight: 700; border-radius: 8px; padding: 0.45rem 0.95rem; font-size: 0.84rem; display: inline-flex; align-items: center; gap: 0.45rem; box-shadow: 0 2px 6px rgba(5,150,105,0.25); cursor: pointer; border: none; transition: all 0.2s ease;"
                    onmouseover="this.style.boxShadow='0 4px 12px rgba(5,150,105,0.35)'; this.style.transform='translateY(-1px)';"
                    onmouseout="this.style.boxShadow='0 2px 6px rgba(5,150,105,0.25)'; this.style.transform='translateY(0)';"
                    title="Restore all archived accounts back to live system">
                <i class="fa-solid fa-rotate-left"></i> Restore All
            </button>
            <button type="button" 
                    class="btn" 
                    onclick="openDeleteAllModal(<?= $archived_count ?>)"
                    style="background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); color: #FFFFFF; font-weight: 700; border-radius: 8px; padding: 0.45rem 0.95rem; font-size: 0.84rem; display: inline-flex; align-items: center; gap: 0.45rem; box-shadow: 0 2px 6px rgba(220,38,38,0.25); cursor: pointer; border: none; transition: all 0.2s ease;"
                    onmouseover="this.style.boxShadow='0 4px 12px rgba(220,38,38,0.35)'; this.style.transform='translateY(-1px)';"
                    onmouseout="this.style.boxShadow='0 2px 6px rgba(220,38,38,0.25)'; this.style.transform='translateY(0)';"
                    title="Permanently erase all archived records">
                <i class="fa-solid fa-trash-can"></i> Delete All
            </button>
        </div>
        <?php endif; ?>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if($archived_count == 0): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3.5rem 1.5rem;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: #F1F5F9; color: #94A3B8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 1rem;">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <h3 style="color: #0F172A; font-size: 1.15rem; font-weight: 700; margin-bottom: 0.35rem;">No Archived Users</h3>
                <p style="color: #64748B; font-size: 0.88rem; max-width: 420px; margin: 0 auto;">There are currently no archived user accounts in the system. All active accounts are visible in User Management.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="padding-left: 1.5rem;">User</th>
                            <th>Email Address</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th style="text-align: right; padding-right: 1.5rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($a = $archived_res->fetch_assoc()): 
                            $role = strtolower($a['role']);
                            $badgeStyle = 'background: #EEF2FF; color: #4338CA; border: 1px solid #C7D2FE;';
                            $roleIcon = 'fa-user';
                            if ($role === 'adviser') {
                                $badgeStyle = 'background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0;';
                                $roleIcon = 'fa-chalkboard-user';
                            } elseif ($role === 'student') {
                                $badgeStyle = 'background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE;';
                                $roleIcon = 'fa-user-graduate';
                            } elseif ($role === 'panel') {
                                $badgeStyle = 'background: #FAF5FF; color: #6B21A8; border: 1px solid #E9D5FF;';
                                $roleIcon = 'fa-user-shield';
                            } elseif ($role === 'admin') {
                                $badgeStyle = 'background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A;';
                                $roleIcon = 'fa-shield-halved';
                            }
                            $initial = strtoupper(substr($a['name'], 0, 1));
                        ?>
                        <tr style="transition: background-color 0.15s ease;">
                            <td style="padding-left: 1.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.85rem;">
                                    <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; flex-shrink: 0; box-shadow: 0 2px 4px rgba(4,120,87,0.15);">
                                        <?= $initial ?>
                                    </div>
                                    <div>
                                        <strong style="color: #0F172A; font-size: 0.92rem; display: block;"><?= htmlspecialchars($a['name']) ?></strong>
                                        <span style="font-size: 0.78rem; color: #94A3B8;">User ID: #<?= $a['id'] ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="color: #475569; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 0.45rem;">
                                    <i class="fa-regular fa-envelope" style="color: #94A3B8;"></i>
                                    <?= htmlspecialchars($a['email']) ?>
                                </span>
                            </td>
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.28rem 0.65rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; <?= $badgeStyle ?>">
                                    <i class="fa-solid <?= $roleIcon ?>"></i>
                                    <?= htmlspecialchars($a['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: #334155; font-size: 0.85rem; font-weight: 500;">
                                    <?= htmlspecialchars($a['department']) ?>
                                </span>
                            </td>
                            <td style="text-align: right; padding-right: 1.5rem; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                    <button type="button" 
                                            class="btn"
                                            style="padding: 0.42rem 0.85rem; background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; border-radius: 6px; cursor: pointer; font-size: 0.82rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.2s ease;"
                                            onmouseover="this.style.background='#059669'; this.style.color='#FFFFFF';"
                                            onmouseout="this.style.background='#ECFDF5'; this.style.color='#059669';"
                                            onclick="restoreUser(<?= $a['id'] ?>, '<?= addslashes(htmlspecialchars($a['name'])) ?>', '<?= ucfirst($role) ?> (<?= addslashes($a['department']) ?>)')">
                                        <i class="fa-solid fa-rotate-left"></i> Restore Account
                                    </button>
                                    <button type="button" 
                                            class="btn"
                                            style="padding: 0.42rem 0.85rem; background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 6px; cursor: pointer; font-size: 0.82rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.2s ease;"
                                            onmouseover="this.style.background='#DC2626'; this.style.color='#FFFFFF';"
                                            onmouseout="this.style.background='#FEF2F2'; this.style.color='#DC2626';"
                                            onclick="deleteUser(<?= $a['id'] ?>, '<?= addslashes(htmlspecialchars($a['name'])) ?>', '<?= ucfirst($role) ?> (<?= addslashes($a['department']) ?>)')">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Archived Pagination Bar -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1.15rem 1.5rem; border-top: 1px solid #E2E8F0; background: #FAFAFA; flex-wrap: wrap; gap: 0.75rem; font-size: 0.85rem; color: #64748B;">
                <div>
                    Showing <strong><?= min($total_archived_count, $a_offset + 1) ?></strong> to <strong><?= min($total_archived_count, $a_offset + $archived_count) ?></strong> of <strong><?= number_format($total_archived_count) ?></strong> archived accounts
                </div>
                
                <div style="display: flex; gap: 0.35rem; align-items: center;">
                    <?php
                        $a_params = $_GET;
                        // Prev
                        $a_params['p'] = max(1, $a_page - 1);
                        $prev_a_url = '?' . http_build_query($a_params);
                    ?>
                    <a href="<?= $prev_a_url ?>" 
                       style="padding: 0.38rem 0.75rem; border: 1px solid #CBD5E1; background: #FFFFFF; color: #334155; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.82rem; <?= $a_page <= 1 ? 'opacity: 0.5; pointer-events: none;' : '' ?>">
                        <i class="fa-solid fa-chevron-left"></i> Prev
                    </a>

                    <?php
                        $a_start = max(1, $a_page - 2);
                        $a_end   = min($a_total_pages, $a_page + 2);
                        for ($i = $a_start; $i <= $a_end; $i++):
                            $a_params['p'] = $i;
                            $a_url = '?' . http_build_query($a_params);
                    ?>
                        <a href="<?= $a_url ?>" 
                           style="padding: 0.38rem 0.75rem; border: 1px solid <?= $a_page == $i ? '#059669' : '#CBD5E1' ?>; background: <?= $a_page == $i ? '#059669' : '#FFFFFF' ?>; color: <?= $a_page == $i ? '#FFFFFF' : '#334155' ?>; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.82rem;">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php
                        // Next
                        $a_params['p'] = min($a_total_pages, $a_page + 1);
                        $next_a_url = '?' . http_build_query($a_params);
                    ?>
                    <a href="<?= $next_a_url ?>" 
                       style="padding: 0.38rem 0.75rem; border: 1px solid #CBD5E1; background: #FFFFFF; color: #334155; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.82rem; <?= $a_page >= $a_total_pages ? 'opacity: 0.5; pointer-events: none;' : '' ?>">
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let targetRestoreId = null;
let targetDeleteId = null;

function restoreUser(id, name, role) {
    targetRestoreId = id;
    const userName = name || 'Archived User';
    const userRole = role || 'CEIT Academic Member';
    const initial = userName.trim().charAt(0).toUpperCase() || 'U';

    document.getElementById('restore_user_name').textContent = userName;
    document.getElementById('restore_user_avatar').textContent = initial;
    document.getElementById('restore_user_role').textContent = userRole;

    document.getElementById('restoreModal').style.display = 'block';
}

function closeRestoreModal() {
    document.getElementById('restoreModal').style.display = 'none';
    targetRestoreId = null;
}

function confirmRestoreUser() {
    if (targetRestoreId) {
        window.location.href = 'action.php?restore_user=' + targetRestoreId;
    }
}

function deleteUser(id, name, role) {
    targetDeleteId = id;
    const userName = name || 'Archived User';
    const userRole = role || 'CEIT Academic Member';
    const initial = userName.trim().charAt(0).toUpperCase() || 'U';

    document.getElementById('delete_user_name').textContent = userName;
    document.getElementById('delete_user_avatar').textContent = initial;
    document.getElementById('delete_user_role').textContent = userRole;

    document.getElementById('deleteModal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    targetDeleteId = null;
}

function confirmDeleteUser() {
    if (targetDeleteId) {
        window.location.href = 'action.php?delete_user=' + targetDeleteId;
    }
}

function openRestoreAllModal(count) {
    const badge = document.getElementById('restore_all_count_badge');
    const desc = document.getElementById('restore_all_count_desc');
    if (badge) badge.textContent = count + ' Accounts to Reactivate';
    if (desc) desc.textContent = count;
    document.getElementById('restoreAllModal').style.display = 'block';
}

function closeRestoreAllModal() {
    document.getElementById('restoreAllModal').style.display = 'none';
}

function confirmRestoreAll() {
    window.location.href = 'action.php?restore_all_archived=1';
}

function openDeleteAllModal(count) {
    const badge = document.getElementById('delete_all_count_badge');
    const desc = document.getElementById('delete_all_count_desc');
    if (badge) badge.textContent = count + ' Accounts to be Erased';
    if (desc) desc.textContent = count;
    document.getElementById('deleteAllModal').style.display = 'block';
}

function closeDeleteAllModal() {
    document.getElementById('deleteAllModal').style.display = 'none';
}

function confirmDeleteAll() {
    window.location.href = 'action.php?delete_all_archived=1';
}

window.addEventListener('click', function(e) {
    const rm = document.getElementById('restoreModal');
    const dm = document.getElementById('deleteModal');
    const ram = document.getElementById('restoreAllModal');
    const dam = document.getElementById('deleteAllModal');
    if (e.target === rm) closeRestoreModal();
    if (e.target === dm) closeDeleteModal();
    if (e.target === ram) closeRestoreAllModal();
    if (e.target === dam) closeDeleteAllModal();
});
</script>

<!-- University Restore Confirmation Modal -->
<div id="restoreModal" class="modal">
    <div class="modal-content logout-modal-content" style="max-width: 460px;">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%); border-bottom: 3px solid #10B981;">
            <div class="logout-seal-wrapper">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle">Caraga State University</div>
            <div class="logout-college-subtitle">College of Engineering and Information Technology</div>
            <h2>Restore Academic User</h2>
        </div>
        <div class="logout-modal-body" style="padding: 1.5rem; text-align: center; background: #FFFFFF;">
            <div class="logout-user-card" style="display: flex; align-items: center; gap: 0.85rem; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 0.75rem 1rem; margin-bottom: 1.15rem; text-align: left;">
                <div class="logout-user-avatar" id="restore_user_avatar" style="width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #047857 0%, #065F46 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; flex-shrink: 0; box-shadow: 0 2px 6px rgba(4, 120, 87, 0.25);">
                    U
                </div>
                <div class="logout-user-details" style="flex: 1; min-width: 0;">
                    <div class="logout-user-name" id="restore_user_name" style="font-size: 0.95rem; font-weight: 700; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;">User Name</div>
                    <div class="logout-user-role" style="font-size: 0.75rem; font-weight: 600; color: #059669; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 0.2rem; display: flex; align-items: center; gap: 0.35rem;">
                        <span class="logout-session-dot" style="width: 7px; height: 7px; background: #10B981; border-radius: 50%; display: inline-block; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);"></span>
                        <span id="restore_user_role">Active Status</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-size: 1rem; font-weight: 700; color: #0F172A; margin-bottom: 0.35rem;">Reactivate Account into Live System?</div>
            <p class="logout-prompt-desc" style="font-size: 0.82rem; color: #64748B; line-height: 1.45; margin: 0 0 1.35rem 0;">
                This account will be restored to active status immediately. The user will be able to log in and all capstone advisory and review records will become available.
            </p>

            <div class="logout-actions" style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn-logout-cancel" onclick="closeRestoreModal()" style="flex: 1; padding: 0.65rem 1rem; border-radius: 10px; background: #F1F5F9; color: #475569; border: 1.5px solid #CBD5E1; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </button>
                <button type="button" onclick="confirmRestoreUser()" style="flex: 1.15; padding: 0.65rem 1.1rem; border-radius: 10px; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; border: none; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-rotate-left"></i> Restore User
                </button>
            </div>

            <div class="logout-modal-footer-note" style="margin-top: 1.1rem; font-size: 0.72rem; color: #94A3B8; display: flex; align-items: center; justify-content: center; gap: 0.4rem; letter-spacing: 0.2px;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<!-- University Permanent Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content logout-modal-content" style="max-width: 460px;">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #7F1D1D 0%, #991B1B 55%, #450A0A 100%); border-bottom: 3px solid #EF4444;">
            <div class="logout-seal-wrapper" style="box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25), 0 0 0 3px rgba(239, 68, 68, 0.7);">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle" style="color: #FCA5A5;">Caraga State University</div>
            <div class="logout-college-subtitle" style="color: #FEE2E2;">College of Engineering and Information Technology</div>
            <h2>Permanently Erase Record</h2>
        </div>
        <div class="logout-modal-body" style="padding: 1.5rem; text-align: center; background: #FFFFFF;">
            <div class="logout-user-card" style="display: flex; align-items: center; gap: 0.85rem; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 12px; padding: 0.75rem 1rem; margin-bottom: 1.15rem; text-align: left;">
                <div class="logout-user-avatar" id="delete_user_avatar" style="width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; flex-shrink: 0; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);">
                    U
                </div>
                <div class="logout-user-details" style="flex: 1; min-width: 0;">
                    <div class="logout-user-name" id="delete_user_name" style="font-size: 0.95rem; font-weight: 700; color: #991B1B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;">User Name</div>
                    <div class="logout-user-role" style="font-size: 0.75rem; font-weight: 600; color: #DC2626; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 0.2rem; display: flex; align-items: center; gap: 0.35rem;">
                        <span class="logout-session-dot" style="width: 7px; height: 7px; background: #DC2626; border-radius: 50%; display: inline-block; box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.25);"></span>
                        <span id="delete_user_role">Irreversible Removal</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-size: 1rem; font-weight: 700; color: #991B1B; margin-bottom: 0.35rem;">Permanent Deletion Warning</div>
            <p class="logout-prompt-desc" style="font-size: 0.82rem; color: #64748B; line-height: 1.45; margin: 0 0 1.35rem 0;">
                Are you sure you want to permanently delete this user? This action <strong style="color: #DC2626;">cannot be undone</strong>. All associated profile, matching, and evaluation records will be permanently erased.
            </p>

            <div class="logout-actions" style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn-logout-cancel" onclick="closeDeleteModal()" style="flex: 1; padding: 0.65rem 1rem; border-radius: 10px; background: #F1F5F9; color: #475569; border: 1.5px solid #CBD5E1; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </button>
                <button type="button" onclick="confirmDeleteUser()" style="flex: 1.15; padding: 0.65rem 1.1rem; border-radius: 10px; background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); color: white; border: none; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-trash"></i> Delete Permanently
                </button>
            </div>

            <div class="logout-modal-footer-note" style="margin-top: 1.1rem; font-size: 0.72rem; color: #94A3B8; display: flex; align-items: center; justify-content: center; gap: 0.4rem; letter-spacing: 0.2px;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<!-- University Restore All Confirmation Modal -->
<div id="restoreAllModal" class="modal">
    <div class="modal-content logout-modal-content" style="max-width: 480px;">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%); border-bottom: 3px solid #10B981;">
            <div class="logout-seal-wrapper">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle">Caraga State University</div>
            <div class="logout-college-subtitle">College of Engineering and Information Technology</div>
            <h2>Restore All Archived Users</h2>
        </div>
        <div class="logout-modal-body" style="padding: 1.5rem; text-align: center; background: #FFFFFF;">
            <div class="logout-user-card" style="display: flex; align-items: center; gap: 0.85rem; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.15rem; text-align: left;">
                <div class="logout-user-avatar" style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);">
                    <i class="fa-solid fa-rotate-left"></i>
                </div>
                <div class="logout-user-details" style="flex: 1; min-width: 0;">
                    <div style="font-size: 0.98rem; font-weight: 700; color: #065F46; line-height: 1.2;">Reactivate All Accounts</div>
                    <div style="font-size: 0.76rem; font-weight: 600; color: #059669; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 0.25rem; display: flex; align-items: center; gap: 0.35rem;">
                        <span style="width: 7px; height: 7px; background: #10B981; border-radius: 50%; display: inline-block; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);"></span>
                        <span id="restore_all_count_badge">All Archived Accounts</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-size: 1.05rem; font-weight: 700; color: #0F172A; margin-bottom: 0.35rem;">Restore All Accounts to Live System?</div>
            <p class="logout-prompt-desc" style="font-size: 0.83rem; color: #64748B; line-height: 1.5; margin: 0 0 1.35rem 0;">
                Are you sure you want to restore all <strong id="restore_all_count_desc" style="color: #047857;"></strong> archived user accounts? All accounts will regain active status immediately, allowing students, advisers, and panels to sign in and access their records.
            </p>

            <div class="logout-actions" style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn-logout-cancel" onclick="closeRestoreAllModal()" style="flex: 1; padding: 0.65rem 1rem; border-radius: 10px; background: #F1F5F9; color: #475569; border: 1.5px solid #CBD5E1; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </button>
                <button type="button" onclick="confirmRestoreAll()" style="flex: 1.25; padding: 0.65rem 1.1rem; border-radius: 10px; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; border: none; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-rotate-left"></i> Yes, Restore All
                </button>
            </div>

            <div class="logout-modal-footer-note" style="margin-top: 1.1rem; font-size: 0.72rem; color: #94A3B8; display: flex; align-items: center; justify-content: center; gap: 0.4rem; letter-spacing: 0.2px;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<!-- University Delete All Confirmation Modal -->
<div id="deleteAllModal" class="modal">
    <div class="modal-content logout-modal-content" style="max-width: 480px;">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #7F1D1D 0%, #991B1B 55%, #450A0A 100%); border-bottom: 3px solid #EF4444;">
            <div class="logout-seal-wrapper" style="box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25), 0 0 0 3px rgba(239, 68, 68, 0.7);">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle" style="color: #FCA5A5;">Caraga State University</div>
            <div class="logout-college-subtitle" style="color: #FEE2E2;">College of Engineering and Information Technology</div>
            <h2>Permanently Erase All Archives</h2>
        </div>
        <div class="logout-modal-body" style="padding: 1.5rem; text-align: center; background: #FFFFFF;">
            <div class="logout-user-card" style="display: flex; align-items: center; gap: 0.85rem; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.15rem; text-align: left;">
                <div class="logout-user-avatar" style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);">
                    <i class="fa-solid fa-trash-can"></i>
                </div>
                <div class="logout-user-details" style="flex: 1; min-width: 0;">
                    <div style="font-size: 0.98rem; font-weight: 700; color: #991B1B; line-height: 1.2;">Permanent Mass Deletion</div>
                    <div style="font-size: 0.76rem; font-weight: 600; color: #DC2626; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 0.25rem; display: flex; align-items: center; gap: 0.35rem;">
                        <span style="width: 7px; height: 7px; background: #DC2626; border-radius: 50%; display: inline-block; box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.25);"></span>
                        <span id="delete_all_count_badge">All Archived Records</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-size: 1.05rem; font-weight: 700; color: #991B1B; margin-bottom: 0.35rem;">DANGER: Irreversible Mass Deletion</div>
            <p class="logout-prompt-desc" style="font-size: 0.83rem; color: #64748B; line-height: 1.5; margin: 0 0 1.35rem 0;">
                Are you sure you want to permanently delete all <strong id="delete_all_count_desc" style="color: #DC2626;"></strong> archived user accounts? This action <strong style="color: #DC2626;">CANNOT BE UNDONE</strong>. All profiles, match requests, manuscripts, and evaluation histories will be wiped permanently from the database.
            </p>

            <div class="logout-actions" style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn-logout-cancel" onclick="closeDeleteAllModal()" style="flex: 1; padding: 0.65rem 1rem; border-radius: 10px; background: #F1F5F9; color: #475569; border: 1.5px solid #CBD5E1; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </button>
                <button type="button" onclick="confirmDeleteAll()" style="flex: 1.3; padding: 0.65rem 1.1rem; border-radius: 10px; background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); color: white; border: none; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-trash-can"></i> Yes, Delete All
                </button>
            </div>

            <div class="logout-modal-footer-note" style="margin-top: 1.1rem; font-size: 0.72rem; color: #94A3B8; display: flex; align-items: center; justify-content: center; gap: 0.4rem; letter-spacing: 0.2px;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>
