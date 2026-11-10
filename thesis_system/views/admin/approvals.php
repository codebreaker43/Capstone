<div class="banner">
    <h1><i class="fa-solid fa-user-check"></i> Pending Account Approvals</h1>
    <p>Review and verify user registrations prior to granting capstone portal access.</p>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-header-left">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <h2>Pending Registration Queue</h2>
        </div>
        <span class="badge badge-pending"><?php echo $pending_count; ?> Pending</span>
    </div>
    <div class="card-body">
        <?php if($pending_count == 0): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3rem 1rem;">
                <i class="fa-solid fa-circle-check fa-3x" style="color: #10B981; margin-bottom: 1rem; display: block;"></i>
                <h3 style="color: #0F172A; font-size: 1.1rem; margin-bottom: 0.35rem;">All Caught Up!</h3>
                <p style="color: #64748B; font-size: 0.88rem; margin: 0;">There are no registrations awaiting approval.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
                <?php while($u = $pending_users->fetch_assoc()): 
                    $role = $u['role'] ?? 'user';
                    $avatar_bg = '#047857';
                    $role_badge = '<span class="badge badge-secondary">' . htmlspecialchars(ucfirst($role)) . '</span>';
                    
                    if($role === 'adviser') {
                        $avatar_bg = '#047857';
                        $role_badge = '<span class="badge badge-info"><i class="fa-solid fa-chalkboard-user"></i> Faculty Adviser</span>';
                    } elseif($role === 'panel') {
                        $avatar_bg = '#D97706';
                        $role_badge = '<span class="badge" style="background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A;"><i class="fa-solid fa-users"></i> Panel Member</span>';
                    } elseif($role === 'secretary') {
                        $avatar_bg = '#4F46E5';
                        $role_badge = '<span class="badge" style="background: #EEF2FF; color: #4338CA; border: 1px solid #C7D2FE;"><i class="fa-solid fa-file-signature"></i> Defense Secretariat</span>';
                    } elseif($role === 'student') {
                        $avatar_bg = '#059669';
                        $role_badge = '<span class="badge" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0;"><i class="fa-solid fa-user-graduate"></i> Student</span>';
                    }
                ?>
                    <div style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; gap: 1rem; align-items: flex-start;">
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: <?php echo $avatar_bg; ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; flex-shrink: 0;">
                                <?php echo strtoupper(substr($u['name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; flex-wrap: wrap;">
                                    <h3 style="margin: 0; color: #0F172A; font-size: 1.05rem; font-weight: 700;">
                                        <?php echo htmlspecialchars($u['name']); ?>
                                    </h3>
                                    <span class="badge badge-pending"><i class="fa-regular fa-clock"></i> Pending Approval</span>
                                    <?php echo $role_badge; ?>
                                </div>
                                <div style="color: #64748B; font-size: 0.82rem; margin-bottom: 0.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                                    <span><i class="fa-solid fa-envelope" style="color: #94A3B8;"></i> <?php echo htmlspecialchars($u['email']); ?></span>
                                    <?php if(!empty($u['phone'])): ?>
                                        <span><i class="fa-solid fa-phone" style="color: #94A3B8;"></i> <?php echo htmlspecialchars($u['phone']); ?></span>
                                    <?php endif; ?>
                                    <span><i class="fa-solid fa-building" style="color: #94A3B8;"></i> <?php echo htmlspecialchars($u['department'] ?: 'General / CEIT'); ?></span>
                                </div>

                                <div style="font-size: 0.82rem; color: #334155;">
                                    <?php if($role === 'adviser'): ?>
                                        <strong>Expertise:</strong> <?php echo htmlspecialchars($u['expertise'] ?: 'General'); ?> &bull; 
                                        <strong>Capacity:</strong> <?php echo htmlspecialchars($u['capacity'] ?: '5'); ?> students
                                    <?php elseif($role === 'panel'): ?>
                                        <strong>Expertise:</strong> <?php echo htmlspecialchars($u['expertise'] ?: 'General'); ?>
                                    <?php elseif($role === 'secretary'): ?>
                                        <strong>Assigned Office:</strong> <?php echo htmlspecialchars($u['office'] ?: 'CEIT Defense Secretariat Office'); ?>
                                    <?php elseif($role === 'student'): ?>
                                        <?php if(!empty($u['student_id'])): ?>
                                            <strong>School ID:</strong> <?php echo htmlspecialchars($u['student_id']); ?> &bull; 
                                        <?php endif; ?>
                                        <strong>Capstone Topic:</strong> <?php echo htmlspecialchars($u['thesis_topic'] ?: 'Not assigned yet'); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem; align-self: center;">
                            <form method="POST" action="action.php" style="margin:0;">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="approve_faculty" class="btn-primary" style="padding: 0.45rem 1rem; font-size: 0.82rem;"><i class="fa-solid fa-check"></i> Approve</button>
                            </form>
                            <form method="POST" action="action.php" style="margin:0;">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" name="reject_faculty" class="btn btn-outline" style="color: #EF4444; border-color: #FECACA; padding: 0.45rem 0.85rem; font-size: 0.82rem;" onclick="return confirm('Reject this registration for <?php echo addslashes(htmlspecialchars($u['name'])); ?>?')"><i class="fa-solid fa-xmark"></i> Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
