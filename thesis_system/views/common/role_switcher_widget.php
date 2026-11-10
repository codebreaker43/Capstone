<?php
// University-grade Quick Role Switcher Widget for users with multiple academic roles (same email)
if (isset($_SESSION['user_id'])) {
    $cur_uid = (int)$_SESSION['user_id'];
    $cur_u_q = $conn->query("SELECT email FROM user WHERE id = $cur_uid");
    if ($cur_u_q && $cur_u_q->num_rows > 0) {
        $cur_email = $cur_u_q->fetch_assoc()['email'];
        if (!empty($cur_email)) {
            $cur_email_esc = $conn->real_escape_string($cur_email);
            $sibling_roles_res = $conn->query("SELECT id, role, name FROM user WHERE email = '$cur_email_esc' AND id != $cur_uid AND account_status = 'active' ORDER BY id ASC");
            if ($sibling_roles_res && $sibling_roles_res->num_rows > 0):
                $sibling_roles = $sibling_roles_res->fetch_all(MYSQLI_ASSOC);
?>
<div class="quick-role-switcher-wrap" style="display: inline-flex; align-items: center; margin-right: 0.65rem;">
    <?php if (count($sibling_roles) === 1): 
        $sib = $sibling_roles[0];
        $r_key = $sib['role'];
        $role_label = ucfirst($r_key);
        $icon = 'fa-user-tag';
        $badge_theme = 'background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE;';
        if ($r_key === 'adviser') {
            $role_label = 'Faculty Adviser';
            $icon = 'fa-chalkboard-user';
            $badge_theme = 'background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0;';
        } elseif ($r_key === 'panel') {
            $role_label = 'Panel Member';
            $icon = 'fa-users';
            $badge_theme = 'background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE;';
        } elseif ($r_key === 'student') {
            $role_label = 'Student';
            $icon = 'fa-graduation-cap';
            $badge_theme = 'background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A;';
        } elseif ($r_key === 'secretary') {
            $role_label = 'Secretariat';
            $icon = 'fa-clipboard-user';
            $badge_theme = 'background: #F5F3FF; color: #6D28D9; border: 1px solid #DDD6FE;';
        }
    ?>
        <a href="action.php?switch_role_to=<?= $sib['id'] ?>" 
           class="btn-topbar-role-switch" 
           style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.38rem 0.75rem; border-radius: 999px; font-size: 0.76rem; font-weight: 700; text-decoration: none; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.05); <?= $badge_theme ?>"
           title="Switch portal to <?= $role_label ?>">
            <i class="fa-solid fa-repeat"></i>
            <span>Switch to <?= $role_label ?></span>
        </a>
    <?php else: ?>
        <div class="role-switch-dropdown-container" style="position: relative;">
            <button type="button" 
                    class="btn-topbar-role-switch" 
                    onclick="toggleRoleSwitchDropdown(event)" 
                    style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.38rem 0.75rem; border-radius: 999px; font-size: 0.76rem; font-weight: 700; text-decoration: none; cursor: pointer; background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); font-family: inherit;">
                <i class="fa-solid fa-repeat"></i>
                <span>Switch Portal (<?= count($sibling_roles) ?>)</span>
                <i class="fa-solid fa-chevron-down" style="font-size: 0.65rem;"></i>
            </button>
            <div id="roleSwitchDropdownMenu" class="role-switch-menu-box" style="display: none; position: absolute; top: calc(100% + 6px); right: 0; min-width: 200px; background: white; border: 1px solid #E2E8F0; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1050; padding: 0.4rem; overflow: hidden;">
                <div style="padding: 0.45rem 0.75rem; font-size: 0.68rem; font-weight: 800; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #F1F5F9;">
                    AVAILABLE ROLES
                </div>
                <?php foreach ($sibling_roles as $sib): 
                    $r_key = $sib['role'];
                    $r_label = ucfirst($r_key);
                    $r_icon = 'fa-user-tag';
                    if ($r_key === 'adviser') { $r_label = 'Faculty Adviser'; $r_icon = 'fa-chalkboard-user'; }
                    elseif ($r_key === 'panel') { $r_label = 'Panel Member'; $r_icon = 'fa-users'; }
                    elseif ($r_key === 'student') { $r_label = 'Student'; $r_icon = 'fa-graduation-cap'; }
                    elseif ($r_key === 'secretary') { $r_label = 'Secretariat'; $r_icon = 'fa-clipboard-user'; }
                ?>
                    <a href="action.php?switch_role_to=<?= $sib['id'] ?>" 
                       style="display: flex; align-items: center; gap: 0.65rem; padding: 0.6rem 0.75rem; font-size: 0.82rem; font-weight: 600; color: #1E293B; text-decoration: none; border-radius: 6px; transition: background 0.15s;"
                       onmouseover="this.style.background='#F8FAFC'" 
                       onmouseout="this.style.background='transparent'">
                        <i class="fa-solid <?= $r_icon ?>" style="color: #059669; width: 16px;"></i>
                        <span><?= $r_label ?> Portal</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
            function toggleRoleSwitchDropdown(e) {
                if (e) e.stopPropagation();
                const m = document.getElementById('roleSwitchDropdownMenu');
                if (m) {
                    m.style.display = m.style.display === 'block' ? 'none' : 'block';
                }
            }
            window.addEventListener('click', function(e) {
                const m = document.getElementById('roleSwitchDropdownMenu');
                if (m && m.style.display === 'block') {
                    m.style.display = 'none';
                }
            });
        </script>
    <?php endif; ?>
</div>
<?php
            endif;
        }
    }
}
?>
