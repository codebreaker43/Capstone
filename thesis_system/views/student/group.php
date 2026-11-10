<?php
// Group view for Student Portal
$dept = $profile['department'] ?? '';

// Fetch unassigned peers in same department
$unassigned_peers = [];
if (!empty($dept)) {
    $dept_esc = $conn->real_escape_string($dept);
    $peer_query = "
        SELECT u.id, u.name, u.email, sp.student_id as student_no 
        FROM user u 
        JOIN student_profile sp ON u.id = sp.user_id 
        WHERE u.role = 'student' 
          AND (sp.group_id IS NULL OR sp.group_id = 0)
          AND u.id != $uid
          AND (sp.department = '$dept_esc' OR sp.department LIKE '%$dept_esc%')
        ORDER BY u.name ASC
    ";
    $p_res = $conn->query($peer_query);
    if ($p_res) {
        while ($peer = $p_res->fetch_assoc()) {
            $unassigned_peers[] = $peer;
        }
    }
}

// Check if user is leader
$is_leader = false;
if ($my_group) {
    $is_leader = ((int)$my_group['leader_id'] === (int)$uid);
}
$member_count = count($my_group_members);
?>

<div class="banner">
    <h1><i class="fa-solid fa-users"></i> Capstone Group Management</h1>
    <p>CEIT Capstone Policy: Every capstone project requires a group of three (3) students.</p>
</div>

<!-- Autocomplete Datalist for fast student entry -->
<datalist id="unassigned_peer_names">
    <?php foreach($unassigned_peers as $p): ?>
        <option value="<?php echo htmlspecialchars($p['name']); ?>"><?php echo !empty($p['student_no']) ? htmlspecialchars($p['student_no']).' • ' : ''; ?><?php echo htmlspecialchars($p['email']); ?></option>
    <?php endforeach; ?>
</datalist>

<?php if (!$my_group): ?>
    <!-- NO GROUP: Form a 3-member group with DIRECT INPUT -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <i class="fa-solid fa-user-plus"></i>
                    <h2>Create Your Capstone Group</h2>
                </div>
                <span class="badge badge-warning"><i class="fa-solid fa-triangle-exclamation"></i> Required for Defense</span>
            </div>
            <div class="card-body">
                <p style="color: #64748B; font-size: 0.9rem; margin-bottom: 1.25rem;">
                    As the group creator, you will become the <strong>Group Leader</strong>. You can simply input or add your 2 group members directly below.
                </p>

                <form method="POST" action="action.php">
                    <input type="hidden" name="create_group" value="1">
                    
                    <div class="form-group">
                        <label>Capstone Title <span style="color:#EF4444;">*</span></label>
                        <input type="text" name="group_name" placeholder="e.g., IoT and AI-Driven Smart Agriculture Monitoring and Solar Optimization" required class="form-control" style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px;">
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" value="<?php echo htmlspecialchars($dept ?: 'CEIT Department'); ?>" readonly class="form-control" style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid #E2E8F0; background: #F8FAFC; border-radius: 8px; color: #64748B;">
                    </div>

                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.25rem;">
                        <h4 style="margin: 0 0 0.85rem 0; color: #0F172A; font-size: 0.92rem; font-weight: 700;">
                            <i class="fa-solid fa-users" style="color: var(--csu-green);"></i> Group Members Roster (3 or 4 Students)
                        </h4>

                        <!-- Member 1: Leader -->
                        <div style="display: flex; align-items: center; justify-content: space-between; background: white; border: 1px solid #CBD5E1; padding: 0.65rem 0.85rem; border-radius: 8px; margin-bottom: 0.85rem;">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <span style="background: #FEF3C7; color: #B45309; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem;"><i class="fa-solid fa-crown"></i></span>
                                <div>
                                    <strong style="font-size: 0.88rem; color: #0F172A;"><?php echo htmlspecialchars($name); ?></strong>
                                    <span style="font-size: 0.75rem; color: #64748B; margin-left: 0.35rem;">(You - Group Leader)</span>
                                </div>
                            </div>
                            <span class="badge badge-success" style="font-size: 0.75rem;"><i class="fa-solid fa-shield"></i> Leader</span>
                        </div>

                        <!-- Direct Entry Callout -->
                        <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px; padding: 0.65rem 0.85rem; margin-bottom: 0.85rem; font-size: 0.82rem; color: #065F46; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-circle-check" style="color: #059669; font-size: 1rem;"></i>
                            <span><strong>Direct Member Entry:</strong> Simply type your members' names directly. You don't have to wait for them to register first!</span>
                        </div>

                        <!-- Member 2 Direct Inputs -->
                        <div style="background: white; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.85rem; margin-bottom: 0.85rem;">
                            <label style="font-size: 0.85rem; font-weight: 700; color: #047857; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.45rem;">
                                <i class="fa-solid fa-user-plus"></i> Member 2 (Second Student)
                            </label>
                            <div style="margin-bottom: 0.45rem;">
                                <input type="text" name="member_2_name" list="unassigned_peer_names" placeholder="Enter Full Name (e.g., Juan Dela Cruz)" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.88rem;">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <input type="text" name="member_2_id" id="member_2_id" placeholder="Student ID *" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem;">
                                <input type="email" name="member_2_email" id="member_2_email" placeholder="Email Address *" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem;">
                            </div>
                        </div>

                        <!-- Member 3 Direct Inputs -->
                        <div style="background: white; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.85rem; margin-bottom: 0.5rem;">
                            <label style="font-size: 0.85rem; font-weight: 700; color: #047857; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.45rem;">
                                <i class="fa-solid fa-user-plus"></i> Member 3 (Third Student)
                            </label>
                            <div style="margin-bottom: 0.45rem;">
                                <input type="text" name="member_3_name" list="unassigned_peer_names" placeholder="Enter Full Name (e.g., Maria Clara Santos)" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.88rem;">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <input type="text" name="member_3_id" id="member_3_id" placeholder="Student ID *" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem;">
                                <input type="email" name="member_3_email" id="member_3_email" placeholder="Email Address *" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem;">
                            </div>
                        </div>

                        <!-- Add 4th Member Button (Optional) -->
                        <div id="add_member_4_btn_container" style="margin-top: 0.75rem; margin-bottom: 0.25rem;">
                            <button type="button" onclick="showMember4Slot()" class="btn" style="width: 100%; padding: 0.75rem; border: 2px dashed #059669; background: #ECFDF5; color: #065F46; border-radius: 8px; font-weight: 700; font-size: 0.88rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: all 0.2s ease;">
                                <i class="fa-solid fa-circle-plus" style="font-size: 1.15rem; color: #059669;"></i>
                                <span>+ Add 4th Member (Optional · In case of 4 members)</span>
                            </button>
                        </div>

                        <!-- Member 4 Direct Inputs (Hidden by default until '+' clicked) -->
                        <div id="member_4_wrapper" style="display: none; background: #F0FDF4; border: 1.5px solid #86EFAC; border-radius: 8px; padding: 0.85rem; margin-top: 0.75rem; margin-bottom: 0.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                                <label style="font-size: 0.85rem; font-weight: 700; color: #047857; display: flex; align-items: center; gap: 0.4rem; margin: 0;">
                                    <i class="fa-solid fa-user-plus"></i> Member 4 (Fourth Student · Optional)
                                </label>
                                <button type="button" onclick="hideMember4Slot()" style="background: none; border: none; color: #DC2626; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;">
                                    <i class="fa-solid fa-trash-can"></i> Remove
                                </button>
                            </div>
                            <div style="margin-bottom: 0.45rem;">
                                <input type="text" name="member_4_name" id="member_4_name" list="unassigned_peer_names" placeholder="Enter Full Name (e.g., Pedro Penduko)" class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.88rem; background: white;">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <input type="text" name="member_4_id" id="member_4_id" placeholder="Student ID *" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem; background: white;">
                                <input type="email" name="member_4_email" id="member_4_email" placeholder="Email Address *" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.82rem; background: white;">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="padding: 0.75rem 1.5rem; font-size: 0.95rem; background: var(--csu-green);">
                        <i class="fa-solid fa-users"></i> Create Capstone Group
                    </button>
                </form>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div class="card" style="border-left: 4px solid var(--csu-gold);">
                <div class="card-header">
                    <i class="fa-solid fa-lightbulb" style="color: #D97706;"></i>
                    <h2 style="font-size: 1rem;">Guidelines for Grouping</h2>
                </div>
                <div class="card-body" style="font-size: 0.85rem; color: #475569; line-height: 1.5;">
                    <ul style="padding-left: 1.2rem; margin: 0;">
                        <li style="margin-bottom: 0.5rem;"><strong>Direct Member Entry:</strong> Simply type your group members' names. Autocomplete suggestions will assist you if they are already in the system.</li>
                        <li style="margin-bottom: 0.5rem;"><strong>3 to 4 Students:</strong> Standard capstone teams consist of 3 students (Leader + 2 members). Click the <strong>"+ Add 4th Member"</strong> button if your team has 4 students.</li>
                        <li style="margin-bottom: 0.5rem;"><strong>Adviser & Admin Sync:</strong> Your member roster is immediately visible to all faculty advisers and department admins.</li>
                        <li><strong>Editable Anytime:</strong> You can edit or update your group members directly whenever needed.</li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fa-solid fa-user-clock"></i>
                    <h2 style="font-size: 1rem;">Waiting for a Leader?</h2>
                </div>
                <div class="card-body" style="font-size: 0.85rem; color: #475569; line-height: 1.5;">
                    If your group leader has already formed a group and entered your name or account, refresh this page to view your active group dashboard.
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ACTIVE GROUP: View 3 Members, Direct Edit, Direct Add -->
    <div class="card" style="border-top: 4px solid var(--csu-green); margin-bottom: 1.5rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div class="card-header-left" style="display: flex; align-items: center; gap: 0.65rem;">
                <i class="fa-solid fa-users" style="color: var(--csu-green); font-size: 1.25rem;"></i>
                <div>
                    <h2 style="margin: 0; font-size: 1.2rem;"><?php echo htmlspecialchars($my_group['group_name']); ?></h2>
                    <span style="font-size: 0.8rem; color: #64748B;">
                        <?php echo htmlspecialchars($my_group['department'] ?: $dept); ?> • Formed <?php echo date('M d, Y', strtotime($my_group['created_at'])); ?>
                    </span>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <?php if ($member_count >= 4): ?>
                    <span class="badge badge-success" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">
                        <i class="fa-solid fa-circle-check"></i> Complete Team (4 / 4 Members)
                    </span>
                <?php elseif ($member_count == 3): ?>
                    <span class="badge badge-success" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">
                        <i class="fa-solid fa-circle-check"></i> Complete Trio (3 Members)
                    </span>
                <?php else: ?>
                    <span class="badge badge-warning" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Incomplete (<?php echo $member_count; ?> / 3-4 Members)
                    </span>
                <?php endif; ?>

                <?php if ($is_leader): ?>
                    <button type="button" onclick="document.getElementById('editGroupMembersModal').style.display='block'" class="btn" style="padding: 0.4rem 0.85rem; font-size: 0.82rem; background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.35rem;">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Members Directly
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body">
            <h3 style="font-size: 1rem; color: #0F172A; margin: 0 0 1rem 0; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-id-badge" style="color: var(--primary-blue);"></i> Group Members
            </h3>

            <!-- SLOTS GRID (Supports 3 or 4 Members) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                <?php 
                // Render up to max(3, count($my_group_members))
                $total_slots = max(3, count($my_group_members));
                for ($slot = 1; $slot <= $total_slots; $slot++): 
                    $m = isset($my_group_members[$slot - 1]) ? $my_group_members[$slot - 1] : null;
                ?>
                    <?php if ($m): ?>
                        <!-- OCCUPIED MEMBER CARD -->
                        <div style="background: white; border: 1px solid <?php echo $m['is_leader'] ? '#FDE68A' : '#E2E8F0'; ?>; border-radius: 12px; padding: 1.25rem; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                            <?php if ($m['is_leader']): ?>
                                <div style="position: absolute; top: -10px; right: 12px; background: #F59E0B; color: white; padding: 2px 10px; border-radius: 12px; font-size: 0.72rem; font-weight: 800; display: flex; align-items: center; gap: 0.3rem;">
                                    <i class="fa-solid fa-crown"></i> LEADER
                                </div>
                            <?php else: ?>
                                <div style="position: absolute; top: -10px; right: 12px; background: #047857; color: white; padding: 2px 10px; border-radius: 12px; font-size: 0.72rem; font-weight: 800; display: flex; align-items: center; gap: 0.3rem;">
                                    <i class="fa-solid fa-user"></i> MEMBER <?php echo $slot; ?>
                                </div>
                            <?php endif; ?>

                            <div>
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; margin-top: 0.25rem;">
                                    <div style="width: 44px; height: 44px; border-radius: 50%; background: <?php echo $m['is_leader'] ? '#FEF3C7' : '#E0E7FF'; ?>; color: <?php echo $m['is_leader'] ? '#B45309' : '#4338CA'; ?>; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.15rem; flex-shrink: 0;">
                                        <?php echo strtoupper(substr($m['name'], 0, 1)); ?>
                                    </div>
                                    <div style="overflow: hidden;">
                                        <h4 style="margin: 0; font-size: 0.98rem; font-weight: 700; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($m['name']); ?>
                                            <?php if((int)($m['student_id'] ?? 0) === (int)$uid): ?>
                                                <span style="font-size: 0.75rem; color: var(--csu-green); font-weight: 600;">(You)</span>
                                            <?php endif; ?>
                                        </h4>
                                        <div style="font-size: 0.78rem; color: #64748B;">
                                            ID: <?php echo htmlspecialchars(!empty($m['student_no']) ? $m['student_no'] : 'N/A'); ?>
                                        </div>
                                    </div>
                                </div>

                                <div style="font-size: 0.8rem; color: #475569; background: #F8FAFC; padding: 0.65rem 0.85rem; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 0.75rem;">
                                    <div><i class="fa-solid fa-envelope" style="color: #94A3B8; width: 16px;"></i> <?php echo htmlspecialchars(!empty($m['email']) ? $m['email'] : 'No email provided'); ?></div>
                                    <div style="margin-top: 0.25rem;"><i class="fa-solid fa-building-columns" style="color: #94A3B8; width: 16px;"></i> <?php echo htmlspecialchars($my_group['department'] ?: $dept); ?></div>
                                </div>
                            </div>

                            <?php if ($is_leader && !$m['is_leader']): ?>
                                <form method="POST" action="action.php" onsubmit="return confirm('Remove <?php echo addslashes($m['name']); ?> from this group?');">
                                    <input type="hidden" name="remove_group_member" value="1">
                                    <input type="hidden" name="group_id" value="<?php echo $my_group['id']; ?>">
                                    <input type="hidden" name="slot" value="<?php echo $slot; ?>">
                                    <input type="hidden" name="member_id" value="<?php echo (int)($m['student_id'] ?? 0); ?>">
                                    <button type="submit" class="btn" style="width: 100%; padding: 0.45rem; font-size: 0.8rem; background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 6px; cursor: pointer;">
                                        <i class="fa-solid fa-user-minus"></i> Remove Member
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- VACANT MEMBER SLOT -->
                        <div style="background: #FAFAFA; border: 2px dashed #CBD5E1; border-radius: 12px; padding: 1.5rem 1.25rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; min-height: 180px;">
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: #F1F5F9; color: #94A3B8; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; margin-bottom: 0.75rem;">
                                <i class="fa-solid fa-user-plus"></i>
                            </div>
                            <h4 style="margin: 0 0 0.25rem 0; font-size: 0.95rem; font-weight: 700; color: #475569;">Vacant Slot <?php echo $slot; ?></h4>
                            <p style="margin: 0 0 0.85rem 0; font-size: 0.78rem; color: #94A3B8;">Each group consists of 3 or 4 students.</p>

                            <?php if ($is_leader): ?>
                                <button type="button" onclick="document.getElementById('addMemberSection').scrollIntoView({behavior: 'smooth'})" class="btn-primary" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; background: var(--csu-green);">
                                    <i class="fa-solid fa-plus"></i> Add Student Directly
                                </button>
                            <?php else: ?>
                                <span style="font-size: 0.78rem; color: #94A3B8; font-style: italic;">Only group leader can add members</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>

            <!-- DIRECT ADD MEMBER SECTION (If < 4 and user is leader) -->
            <?php if ($is_leader && $member_count < 4): ?>
                <div id="addMemberSection" style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <h4 style="margin: 0 0 0.4rem 0; color: #166534; font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 0.45rem;">
                        <i class="fa-solid fa-user-plus"></i> <?php echo ($member_count >= 3) ? 'Add 4th Teammate (Optional · Slot 4)' : 'Add Teammate Directly (Slot ' . ($member_count + 1) . ' of 3 or 4)'; ?>
                    </h4>
                    <p style="color: #15803D; font-size: 0.82rem; margin-bottom: 0.85rem;">
                        <?php echo ($member_count >= 3) 
                            ? 'Your group already has 3 members. If your team is permitted to have 4 students, enter the 4th member below.' 
                            : 'Simply type your group member\'s name directly. Autocomplete suggestions will assist if they are already registered.'; ?>
                    </p>

                    <form method="POST" action="action.php" style="display: flex; gap: 0.65rem; flex-wrap: wrap; align-items: flex-end;">
                        <input type="hidden" name="add_group_member" value="1">
                        <input type="hidden" name="group_id" value="<?php echo $my_group['id']; ?>">
                        
                        <div style="flex: 2; min-width: 220px;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #166534; display: block; margin-bottom: 0.25rem;">Full Name <span style="color:#DC2626;">*</span></label>
                            <input type="text" name="member_name" list="unassigned_peer_names" required placeholder="Type student's full name..." class="form-control" style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #86EFAC; border-radius: 6px; background: white; font-size: 0.85rem;">
                        </div>

                        <div style="flex: 1.2; min-width: 140px;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #166534; display: block; margin-bottom: 0.25rem;">Student ID <span style="color:#DC2626;">*</span></label>
                            <input type="text" name="student_no" required placeholder="Student ID" class="form-control" style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #86EFAC; border-radius: 6px; background: white; font-size: 0.85rem;">
                        </div>

                        <div style="flex: 1.5; min-width: 160px;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #166534; display: block; margin-bottom: 0.25rem;">Email Address <span style="color:#DC2626;">*</span></label>
                            <input type="email" name="email" required placeholder="firstname.lastname@csucc.edu.ph" class="form-control" style="width: 100%; padding: 0.55rem 0.75rem; border: 1px solid #86EFAC; border-radius: 6px; background: white; font-size: 0.85rem;">
                        </div>

                        <div>
                            <button type="submit" class="btn-primary" style="padding: 0.58rem 1.15rem; font-size: 0.85rem; background: #059669; white-space: nowrap; height: 37px;">
                                <i class="fa-solid fa-user-plus"></i> Add Member Directly
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- GROUP ACTIONS & VISIBILITY NOTE -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #E2E8F0; padding-top: 1rem; flex-wrap: wrap; gap: 1rem;">
                <div style="font-size: 0.82rem; color: #64748B; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-eye" style="color: var(--primary-blue);"></i>
                    <span>This 3-member roster is synchronized with your <strong>Assigned Faculty Adviser</strong> and <strong>CEIT Department Admin</strong>.</span>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <?php if ($is_leader): ?>
                        <form method="POST" action="action.php" onsubmit="return confirm('WARNING: Are you sure you want to disband this group? All 3 members will be unlinked.');">
                            <input type="hidden" name="disband_group" value="1">
                            <input type="hidden" name="group_id" value="<?php echo $my_group['id']; ?>">
                            <button type="submit" class="btn" style="padding: 0.5rem 1rem; background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 8px; font-size: 0.82rem; font-weight: 600; cursor: pointer;">
                                <i class="fa-solid fa-trash-can"></i> Disband Group
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="action.php" onsubmit="return confirm('Are you sure you want to leave this capstone group?');">
                            <input type="hidden" name="leave_group" value="1">
                            <button type="submit" class="btn" style="padding: 0.5rem 1rem; background: #FFF7ED; color: #EA580C; border: 1px solid #FED7AA; border-radius: 8px; font-size: 0.82rem; font-weight: 600; cursor: pointer;">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i> Leave Group
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: EDIT GROUP MEMBERS DIRECTLY (LEADER) -->
    <?php if ($is_leader): ?>
        <div id="editGroupMembersModal" class="modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);">
            <div style="background: white; border-radius: 12px; max-width: 540px; margin: 4% auto; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); position: relative;">
                <span onclick="document.getElementById('editGroupMembersModal').style.display='none'" style="position: absolute; right: 1.25rem; top: 1rem; font-size: 1.5rem; cursor: pointer; color: #64748B;">&times;</span>
                <h3 style="margin: 0 0 0.4rem 0; font-size: 1.15rem; color: #0F172A; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-users-gear" style="color: var(--csu-green);"></i> Edit Capstone Group Roster
                </h3>
                <p style="font-size: 0.82rem; color: #64748B; margin-bottom: 1.25rem;">
                    Directly update your group name and teammate details for your 3 or 4 student capstone team.
                </p>

                <form method="POST" action="action.php">
                    <input type="hidden" name="update_group_members" value="1">
                    <input type="hidden" name="group_id" value="<?php echo $my_group['id']; ?>">

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label style="font-size: 0.82rem; font-weight: 600; color: #334155; display: block; margin-bottom: 0.35rem;">Capstone Title <span style="color:#DC2626;">*</span></label>
                        <input type="text" name="group_name" value="<?php echo htmlspecialchars($my_group['group_name']); ?>" required class="form-control" style="width: 100%; padding: 0.6rem 0.85rem; border: 1px solid #CBD5E1; border-radius: 8px;">
                    </div>

                    <!-- Member 1: Leader (Fixed) -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.65rem 0.85rem; border-radius: 8px; margin-bottom: 0.85rem; display: flex; align-items: center; justify-content: space-between;">
                        <div style="font-size: 0.85rem; color: #0F172A;">
                            <i class="fa-solid fa-crown" style="color: #D97706; margin-right: 0.35rem;"></i>
                            <strong><?php echo htmlspecialchars($name); ?></strong> <span style="color: #64748B; font-size: 0.78rem;">(Group Leader)</span>
                        </div>
                        <span class="badge badge-success" style="font-size: 0.72rem;">Fixed Leader</span>
                    </div>

                    <!-- Member 2 Direct Inputs -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.85rem; border-radius: 8px; margin-bottom: 0.85rem;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: #047857; display: block; margin-bottom: 0.4rem;">
                            <i class="fa-solid fa-user"></i> Member 2 (Second Student)
                        </label>
                        <input type="text" name="member_2_name" value="<?php echo htmlspecialchars($my_group['member2_name'] ?? ''); ?>" list="unassigned_peer_names" placeholder="Full Name (Leave blank if vacant)" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; margin-bottom: 0.4rem; font-size: 0.85rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <input type="text" name="member_2_id" id="edit_member_2_id" value="<?php echo htmlspecialchars($my_group['member2_student_id'] ?? ''); ?>" placeholder="Student ID *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem;">
                            <input type="email" name="member_2_email" id="edit_member_2_email" value="<?php echo htmlspecialchars($my_group['member2_email'] ?? ''); ?>" placeholder="Email Address *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem;">
                        </div>
                    </div>

                    <!-- Member 3 Direct Inputs -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.85rem; border-radius: 8px; margin-bottom: 0.85rem;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: #047857; display: block; margin-bottom: 0.4rem;">
                            <i class="fa-solid fa-user"></i> Member 3 (Third Student)
                        </label>
                        <input type="text" name="member_3_name" value="<?php echo htmlspecialchars($my_group['member3_name'] ?? ''); ?>" list="unassigned_peer_names" placeholder="Full Name (Leave blank if vacant)" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; margin-bottom: 0.4rem; font-size: 0.85rem;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <input type="text" name="member_3_id" id="edit_member_3_id" value="<?php echo htmlspecialchars($my_group['member3_student_id'] ?? ''); ?>" placeholder="Student ID *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem;">
                            <input type="email" name="member_3_email" id="edit_member_3_email" value="<?php echo htmlspecialchars($my_group['member3_email'] ?? ''); ?>" placeholder="Email Address *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem;">
                        </div>
                    </div>

                    <!-- Member 4 Direct Inputs (Optional) -->
                    <?php $has_edit_m4 = !empty($my_group['member4_name']); ?>
                    <div id="edit_add_m4_btn_container" style="<?php echo $has_edit_m4 ? 'display: none;' : ''; ?>margin-bottom: 1rem;">
                        <button type="button" onclick="showEditMember4Slot()" class="btn" style="width: 100%; padding: 0.65rem; border: 2px dashed #059669; background: #ECFDF5; color: #065F46; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.45rem;">
                            <i class="fa-solid fa-circle-plus" style="font-size: 1.1rem; color: #059669;"></i>
                            <span>+ Add 4th Member (Optional · In case of 4 members)</span>
                        </button>
                    </div>

                    <div id="edit_member_4_wrapper" style="<?php echo $has_edit_m4 ? '' : 'display: none; '; ?>background: #F0FDF4; border: 1.5px solid #86EFAC; padding: 0.85rem; border-radius: 8px; margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                            <label style="font-size: 0.82rem; font-weight: 700; color: #047857; margin: 0; display: flex; align-items: center; gap: 0.4rem;">
                                <i class="fa-solid fa-user"></i> Member 4 (Fourth Student · Optional)
                            </label>
                            <button type="button" onclick="hideEditMember4Slot()" style="background: none; border: none; color: #DC2626; font-size: 0.78rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;">
                                <i class="fa-solid fa-trash-can"></i> Remove
                            </button>
                        </div>
                        <input type="text" name="member_4_name" id="edit_member_4_name" value="<?php echo htmlspecialchars($my_group['member4_name'] ?? ''); ?>" list="unassigned_peer_names" placeholder="Full Name (Leave blank if vacant)" class="form-control" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #CBD5E1; border-radius: 6px; margin-bottom: 0.4rem; font-size: 0.85rem; background: white;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <input type="text" name="member_4_id" id="edit_member_4_id" value="<?php echo htmlspecialchars($my_group['member4_student_id'] ?? ''); ?>" placeholder="Student ID *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem; background: white;">
                            <input type="email" name="member_4_email" id="edit_member_4_email" value="<?php echo htmlspecialchars($my_group['member4_email'] ?? ''); ?>" placeholder="Email Address *" class="form-control" style="width: 100%; padding: 0.45rem 0.65rem; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 0.8rem; background: white;">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                        <button type="button" onclick="document.getElementById('editGroupMembersModal').style.display='none'" class="btn" style="padding: 0.6rem 1rem; border: 1px solid #CBD5E1; background: white; border-radius: 6px; font-size: 0.85rem; cursor: pointer;">Cancel</button>
                        <button type="submit" class="btn-primary" style="padding: 0.6rem 1.25rem; font-size: 0.85rem; background: var(--csu-green);">
                            <i class="fa-solid fa-check"></i> Save Members Directly
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
function showMember4Slot() {
    const wrap = document.getElementById('member_4_wrapper');
    const btn = document.getElementById('add_member_4_btn_container');
    if (wrap) wrap.style.display = 'block';
    if (btn) btn.style.display = 'none';
    const m4Input = document.getElementById('member_4_name');
    if (m4Input) m4Input.focus();
}

function hideMember4Slot() {
    const wrap = document.getElementById('member_4_wrapper');
    const btn = document.getElementById('add_member_4_btn_container');
    if (wrap) {
        wrap.style.display = 'none';
        const nameInput = document.getElementById('member_4_name');
        const idInput = document.getElementById('member_4_id');
        const emailInput = document.getElementById('member_4_email');
        if (nameInput) nameInput.value = '';
        if (idInput) idInput.value = '';
        if (emailInput) emailInput.value = '';
    }
    if (btn) btn.style.display = 'block';
}

function showEditMember4Slot() {
    const wrap = document.getElementById('edit_member_4_wrapper');
    const btn = document.getElementById('edit_add_m4_btn_container');
    if (wrap) wrap.style.display = 'block';
    if (btn) btn.style.display = 'none';
    const m4Input = document.getElementById('edit_member_4_name');
    if (m4Input) m4Input.focus();
}

function hideEditMember4Slot() {
    const wrap = document.getElementById('edit_member_4_wrapper');
    const btn = document.getElementById('edit_add_m4_btn_container');
    if (wrap) {
        wrap.style.display = 'none';
        const nameInput = document.getElementById('edit_member_4_name');
        const idInput = document.getElementById('edit_member_4_id');
        const emailInput = document.getElementById('edit_member_4_email');
        if (nameInput) nameInput.value = '';
        if (idInput) idInput.value = '';
        if (emailInput) emailInput.value = '';
    }
    if (btn) btn.style.display = 'block';
}

document.addEventListener('DOMContentLoaded', function() {
    // 1. Create Group Validation
    const createForm = document.querySelector('form input[name="create_group"]')?.closest('form');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            const m2Name = this.querySelector('input[name="member_2_name"]')?.value.trim();
            const m2Id = this.querySelector('input[name="member_2_id"]')?.value.trim();
            const m2Email = this.querySelector('input[name="member_2_email"]')?.value.trim();
            const m3Name = this.querySelector('input[name="member_3_name"]')?.value.trim();
            const m3Id = this.querySelector('input[name="member_3_id"]')?.value.trim();
            const m3Email = this.querySelector('input[name="member_3_email"]')?.value.trim();
            const m4Name = this.querySelector('input[name="member_4_name"]')?.value.trim();
            const m4Id = this.querySelector('input[name="member_4_id"]')?.value.trim();
            const m4Email = this.querySelector('input[name="member_4_email"]')?.value.trim();

            if (m2Name && !m2Id) {
                e.preventDefault();
                alert('Student ID Required for Member 2 (' + m2Name + ').');
                this.querySelector('input[name="member_2_id"]')?.focus();
                return false;
            }
            if (m2Name && !m2Email) {
                e.preventDefault();
                alert('Email Address Required for Member 2 (' + m2Name + ').');
                this.querySelector('input[name="member_2_email"]')?.focus();
                return false;
            }

            if (m3Name && !m3Id) {
                e.preventDefault();
                alert('Student ID Required for Member 3 (' + m3Name + ').');
                this.querySelector('input[name="member_3_id"]')?.focus();
                return false;
            }
            if (m3Name && !m3Email) {
                e.preventDefault();
                alert('Email Address Required for Member 3 (' + m3Name + ').');
                this.querySelector('input[name="member_3_email"]')?.focus();
                return false;
            }

            if (m4Name && !m4Id) {
                e.preventDefault();
                alert('Student ID Required for Member 4 (' + m4Name + ').');
                this.querySelector('input[name="member_4_id"]')?.focus();
                return false;
            }
            if (m4Name && !m4Email) {
                e.preventDefault();
                alert('Email Address Required for Member 4 (' + m4Name + ').');
                this.querySelector('input[name="member_4_email"]')?.focus();
                return false;
            }
        });
    }

    // 2. Edit Group Validation
    const editForm = document.querySelector('form input[name="update_group_members"]')?.closest('form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            const m2Name = this.querySelector('input[name="member_2_name"]')?.value.trim();
            const m2Id = this.querySelector('input[name="member_2_id"]')?.value.trim();
            const m2Email = this.querySelector('input[name="member_2_email"]')?.value.trim();
            const m3Name = this.querySelector('input[name="member_3_name"]')?.value.trim();
            const m3Id = this.querySelector('input[name="member_3_id"]')?.value.trim();
            const m3Email = this.querySelector('input[name="member_3_email"]')?.value.trim();
            const m4Name = this.querySelector('input[name="member_4_name"]')?.value.trim();
            const m4Id = this.querySelector('input[name="member_4_id"]')?.value.trim();
            const m4Email = this.querySelector('input[name="member_4_email"]')?.value.trim();

            if (m2Name && !m2Id) {
                e.preventDefault();
                alert('Student ID Required for Member 2 (' + m2Name + ').');
                this.querySelector('input[name="member_2_id"]')?.focus();
                return false;
            }
            if (m2Name && !m2Email) {
                e.preventDefault();
                alert('Email Address Required for Member 2 (' + m2Name + ').');
                this.querySelector('input[name="member_2_email"]')?.focus();
                return false;
            }

            if (m3Name && !m3Id) {
                e.preventDefault();
                alert('Student ID Required for Member 3 (' + m3Name + ').');
                this.querySelector('input[name="member_3_id"]')?.focus();
                return false;
            }
            if (m3Name && !m3Email) {
                e.preventDefault();
                alert('Email Address Required for Member 3 (' + m3Name + ').');
                this.querySelector('input[name="member_3_email"]')?.focus();
                return false;
            }

            if (m4Name && !m4Id) {
                e.preventDefault();
                alert('Student ID Required for Member 4 (' + m4Name + ').');
                this.querySelector('input[name="member_4_id"]')?.focus();
                return false;
            }
            if (m4Name && !m4Email) {
                e.preventDefault();
                alert('Email Address Required for Member 4 (' + m4Name + ').');
                this.querySelector('input[name="member_4_email"]')?.focus();
                return false;
            }
        });
    }
});
</script>
