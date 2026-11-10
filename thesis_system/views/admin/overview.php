<?php
// Compute comprehensive real-time metrics for the Executive System Overview
$total_active_schedules = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meetings WHERE status IN ('scheduled', 'in_progress')")->fetch_assoc()['c'] ?? 0);
$total_mom_records = (int)($conn->query("SELECT COUNT(*) as c FROM defense_meeting_minutes WHERE is_archived = 0")->fetch_assoc()['c'] ?? 0);
$total_groups = (int)($conn->query("SELECT COUNT(*) as c FROM capstone_group")->fetch_assoc()['c'] ?? 0);
$total_panels = (int)($conn->query("SELECT COUNT(*) as c FROM user WHERE role='panel' AND account_status='active'")->fetch_assoc()['c'] ?? 0);
$total_consultations = (int)($conn->query("SELECT COUNT(*) as c FROM adviser_consultations")->fetch_assoc()['c'] ?? 0);
$accepted_matches = (int)($conn->query("SELECT COUNT(*) as c FROM match_request WHERE status='accepted'")->fetch_assoc()['c'] ?? 0);
$pending_matches = (int)($conn->query("SELECT COUNT(*) as c FROM match_request WHERE status='pending'")->fetch_assoc()['c'] ?? 0);

// Fetch Faculty Advisers with Workload, Specializations & Publications for Executive Overview
$adv_overview_list = [];
$adv_ov_q = $conn->query("
    SELECT u.id, u.name, u.email, ap.department, ap.expertise, ap.capacity, ap.academic_degree, ap.graduation_year, ap.google_scholar_url, ap.published_papers,
           (SELECT COUNT(DISTINCT COALESCE(m.group_id, m.student_id)) FROM match_request m WHERE m.adviser_id = u.id AND m.status = 'accepted') as active_load,
           (SELECT COUNT(*) FROM adviser_consultations ac WHERE ac.adviser_id = u.id) as consultation_count
    FROM user u
    JOIN adviser_profile ap ON u.id = ap.user_id
    WHERE u.role = 'adviser' AND u.account_status = 'active'
    ORDER BY active_load DESC, u.name ASC
    LIMIT 6
");
if ($adv_ov_q) {
    while ($r = $adv_ov_q->fetch_assoc()) {
        $adv_overview_list[] = $r;
    }
}

// Fetch Department Breakdown of Students
$dept_stats = [];
$dept_q = $conn->query("
    SELECT COALESCE(NULLIF(sp.department, ''), 'Unspecified / General CEIT') as dept_name, COUNT(*) as cnt
    FROM user u
    LEFT JOIN student_profile sp ON u.id = sp.user_id
    WHERE u.role = 'student' AND u.account_status = 'active'
    GROUP BY dept_name
    ORDER BY cnt DESC
");
if ($dept_q) {
    while ($dr = $dept_q->fetch_assoc()) {
        $dept_stats[] = $dr;
    }
}

// Fetch Recent Audit Logs (latest 6)
$recent_logs = [];
$log_q = @$conn->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 6");
if ($log_q) {
    while ($lr = $log_q->fetch_assoc()) {
        $recent_logs[] = $lr;
    }
}
?>

<!-- EXECUTIVE HERO BANNER -->
<div class="banner" style="background: linear-gradient(135deg, #022C22 0%, #064E3B 52%, #047857 100%); border-radius: 14px; padding: 1.35rem 1.75rem; color: white; margin-bottom: 1.35rem; box-shadow: 0 10px 25px -5px rgba(4, 120, 87, 0.25); position: relative; overflow: hidden; border: 1px solid rgba(167, 243, 208, 0.2);">
    <div style="position: absolute; right: -20px; bottom: -25px; opacity: 0.07; font-size: 8rem; pointer-events: none;">
        <i class="fa-solid fa-building-columns"></i>
    </div>
    <div style="position: relative; z-index: 1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.45rem;">
                <span style="background: rgba(245, 158, 11, 0.22); color: #FCD34D; border: 1px solid rgba(245, 158, 11, 0.4); padding: 0.22rem 0.65rem; border-radius: 999px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-shield-halved"></i> CEIT Executive Command Center
                </span>
                <span style="background: rgba(16, 185, 129, 0.2); color: #A7F3D0; border: 1px solid rgba(16, 185, 129, 0.35); padding: 0.22rem 0.65rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                    <i class="fa-solid fa-scale-balanced"></i> MCDA + Google Scholar Sync Active
                </span>
            </div>
            <h1 style="font-size: 1.45rem; font-weight: 800; margin: 0 0 0.25rem 0; color: #FFFFFF; letter-spacing: -0.02em;">
                College System Administration
            </h1>
            <p style="margin: 0; color: #D1FAE5; font-size: 0.85rem; opacity: 0.95;">
                Welcome back, <strong><?php echo htmlspecialchars($name); ?></strong> &bull; Caraga State University Cabadbaran Campus (CEIT Capstone &amp; Panel Management System)
            </p>
        </div>
        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
            <a href="?tab=users" style="background: #F59E0B; color: #022C22; font-weight: 800; font-size: 0.8rem; padding: 0.55rem 1rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 4px 10px rgba(0,0,0,0.18);">
                <i class="fa-solid fa-user-plus"></i> Manage Faculty &amp; Users
            </a>
            <a href="?tab=reports" style="background: rgba(255,255,255,0.14); color: #FFFFFF; border: 1px solid rgba(255,255,255,0.28); font-weight: 700; font-size: 0.8rem; padding: 0.55rem 1rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                <i class="fa-solid fa-chart-pie"></i> System Reports
            </a>
        </div>
    </div>
</div>

<!-- 6 EXECUTIVE KPI STAT CARDS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(195px, 1fr)); gap: 1rem; margin-bottom: 1.35rem;">
    <!-- Students -->
    <a href="?tab=users&role_filter=student" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="background: white; border: 1px solid #E2E8F0; border-top: 3px solid #047857; border-radius: 12px; padding: 1.1rem 1.2rem; box-shadow: 0 2px 6px rgba(15,23,42,0.03); transition: transform 0.18s ease, box-shadow 0.18s ease; height: 100%;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 16px rgba(15,23,42,0.07)'" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(15,23,42,0.03)'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.55rem;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #ECFDF5; color: #047857; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
                <span style="font-size: 0.68rem; font-weight: 700; color: #047857; background: #D1FAE5; padding: 2px 7px; border-radius: 999px;">
                    <?php echo $total_groups; ?> Groups
                </span>
            </div>
            <div style="font-size: 1.65rem; font-weight: 800; color: #0F172A; line-height: 1.1; margin-bottom: 0.2rem;"><?php echo (int)$total_students; ?></div>
            <div style="font-size: 0.78rem; font-weight: 600; color: #64748B;">Registered Students</div>
        </div>
    </a>

    <!-- Faculty Advisers -->
    <a href="?tab=users&role_filter=adviser" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="background: white; border: 1px solid #E2E8F0; border-top: 3px solid #2563EB; border-radius: 12px; padding: 1.1rem 1.2rem; box-shadow: 0 2px 6px rgba(15,23,42,0.03); transition: transform 0.18s ease, box-shadow 0.18s ease; height: 100%;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 16px rgba(15,23,42,0.07)'" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(15,23,42,0.03)'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.55rem;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #EFF6FF; color: #2563EB; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </div>
                <span style="font-size: 0.68rem; font-weight: 700; color: #1D4ED8; background: #DBEAFE; padding: 2px 7px; border-radius: 999px;">
                    +<?php echo $total_panels; ?> Panelists
                </span>
            </div>
            <div style="font-size: 1.65rem; font-weight: 800; color: #0F172A; line-height: 1.1; margin-bottom: 0.2rem;"><?php echo (int)$total_advisers; ?></div>
            <div style="font-size: 0.78rem; font-weight: 600; color: #64748B;">Active Faculty Advisers</div>
        </div>
    </a>

    <!-- Capstone Topics Submitted -->
    <a href="?tab=theses" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="background: white; border: 1px solid #E2E8F0; border-top: 3px solid #059669; border-radius: 12px; padding: 1.1rem 1.2rem; box-shadow: 0 2px 6px rgba(15,23,42,0.03); transition: transform 0.18s ease, box-shadow 0.18s ease; height: 100%;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 16px rgba(15,23,42,0.07)'" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(15,23,42,0.03)'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.55rem;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #ECFDF5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-book-bookmark"></i>
                </div>
                <span style="font-size: 0.68rem; font-weight: 700; color: #065F46; background: #D1FAE5; padding: 2px 7px; border-radius: 999px;">
                    <?php echo $accepted_matches; ?> Matched
                </span>
            </div>
            <div style="font-size: 1.65rem; font-weight: 800; color: #0F172A; line-height: 1.1; margin-bottom: 0.2rem;"><?php echo (int)$total_theses; ?></div>
            <div style="font-size: 0.78rem; font-weight: 600; color: #64748B;">Capstone Topics Submitted</div>
        </div>
    </a>

    <!-- Active Defense Schedules -->
    <a href="?tab=schedules" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="background: white; border: 1px solid #E2E8F0; border-top: 3px solid #7C3AED; border-radius: 12px; padding: 1.1rem 1.2rem; box-shadow: 0 2px 6px rgba(15,23,42,0.03); transition: transform 0.18s ease, box-shadow 0.18s ease; height: 100%;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 16px rgba(15,23,42,0.07)'" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(15,23,42,0.03)'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.55rem;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #F5F3FF; color: #7C3AED; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <span style="font-size: 0.68rem; font-weight: 700; color: #5B21B6; background: #EDE9FE; padding: 2px 7px; border-radius: 999px;">
                    <?php echo $total_consultations; ?> Consults
                </span>
            </div>
            <div style="font-size: 1.65rem; font-weight: 800; color: #0F172A; line-height: 1.1; margin-bottom: 0.2rem;"><?php echo $total_active_schedules; ?></div>
            <div style="font-size: 0.78rem; font-weight: 600; color: #64748B;">Active Defense Schedules</div>
        </div>
    </a>

    <!-- Official MOM Records -->
    <a href="?tab=mom_archive" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="background: white; border: 1px solid #E2E8F0; border-top: 3px solid #0D9488; border-radius: 12px; padding: 1.1rem 1.2rem; box-shadow: 0 2px 6px rgba(15,23,42,0.03); transition: transform 0.18s ease, box-shadow 0.18s ease; height: 100%;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 16px rgba(15,23,42,0.07)'" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(15,23,42,0.03)'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.55rem;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #F0FDFA; color: #0D9488; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-file-shield"></i>
                </div>
                <span style="font-size: 0.68rem; font-weight: 700; color: #0F766E; background: #CCFBF1; padding: 2px 7px; border-radius: 999px;">
                    Archived Ready
                </span>
            </div>
            <div style="font-size: 1.65rem; font-weight: 800; color: #0F172A; line-height: 1.1; margin-bottom: 0.2rem;"><?php echo $total_mom_records; ?></div>
            <div style="font-size: 0.78rem; font-weight: 600; color: #64748B;">Official MOM Records</div>
        </div>
    </a>

    <!-- Pending Approvals -->
    <a href="?tab=approvals" style="text-decoration: none; color: inherit;">
        <div class="stat-card" style="background: white; border: 1px solid <?php echo $pending_count > 0 ? '#FDE68A' : '#E2E8F0'; ?>; border-top: 3px solid #F59E0B; border-radius: 12px; padding: 1.1rem 1.2rem; box-shadow: 0 2px 6px rgba(15,23,42,0.03); transition: transform 0.18s ease, box-shadow 0.18s ease; height: 100%;" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 16px rgba(15,23,42,0.07)'" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 6px rgba(15,23,42,0.03)'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.55rem;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-user-clock"></i>
                </div>
                <span style="font-size: 0.68rem; font-weight: 700; color: <?php echo $pending_count > 0 ? '#92400E' : '#065F46'; ?>; background: <?php echo $pending_count > 0 ? '#FEF3C7' : '#D1FAE5'; ?>; padding: 2px 7px; border-radius: 999px;">
                    <?php echo $pending_count > 0 ? 'Action Needed' : 'All Clear'; ?>
                </span>
            </div>
            <div style="font-size: 1.65rem; font-weight: 800; color: <?php echo $pending_count > 0 ? '#D97706' : '#0F172A'; ?>; line-height: 1.1; margin-bottom: 0.2rem;"><?php echo (int)$pending_count; ?></div>
            <div style="font-size: 0.78rem; font-weight: 600; color: #64748B;">Pending Account Approvals</div>
        </div>
    </a>
</div>

<!-- ADMINISTRATIVE QUICK ACTIONS (UNIFORM MODERN ACTION TILES) -->
<div class="card" style="border-radius: 12px; border: 1px solid #E2E8F0; margin-bottom: 1.35rem; box-shadow: 0 2px 8px rgba(15,23,42,0.03);">
    <div class="card-header" style="padding: 0.9rem 1.25rem; border-bottom: 1px solid #F1F5F9; display: flex; justify-content: space-between; align-items: center;">
        <div class="card-header-left" style="display: flex; align-items: center; gap: 0.5rem;">
            <div style="width: 30px; height: 30px; border-radius: 8px; background: #ECFDF5; color: #047857; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <h2 style="font-size: 0.98rem; font-weight: 800; color: #0F172A; margin: 0;">Administrative Quick Actions</h2>
        </div>
        <span style="font-size: 0.74rem; color: #64748B; font-weight: 600;">Direct shortcuts to core CEIT management modules</span>
    </div>
    <div class="card-body" style="padding: 1.1rem 1.25rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.85rem;">
            <a href="?tab=schedules" style="text-decoration: none; display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 0.95rem; border-radius: 10px; background: #F0FDF4; border: 1px solid #A7F3D0; color: #065F46; transition: all 0.18s ease;" onmouseover="this.style.background='#DCFCE7'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#F0FDF4'; this.style.transform='none'">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #047857; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div>
                    <div style="font-size: 0.82rem; font-weight: 800; color: #064E3B;">Defense Schedules</div>
                    <div style="font-size: 0.7rem; color: #047857;">Calendar &amp; Oral Defenses</div>
                </div>
            </a>

            <a href="?tab=mom_archive" style="text-decoration: none; display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 0.95rem; border-radius: 10px; background: #F8FAFC; border: 1px solid #CBD5E1; color: #0F172A; transition: all 0.18s ease;" onmouseover="this.style.background='#F1F5F9'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#F8FAFC'; this.style.transform='none'">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #0D9488; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
                <div>
                    <div style="font-size: 0.82rem; font-weight: 800; color: #0F172A;">Official MOM Registry</div>
                    <div style="font-size: 0.7rem; color: #64748B;"><?php echo $total_mom_records; ?> Minutes Recorded</div>
                </div>
            </a>

            <a href="?tab=users" style="text-decoration: none; display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 0.95rem; border-radius: 10px; background: #EFF6FF; border: 1px solid #BFDBFE; color: #1E3A8A; transition: all 0.18s ease;" onmouseover="this.style.background='#DBEAFE'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#EFF6FF'; this.style.transform='none'">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #2563EB; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
                <div>
                    <div style="font-size: 0.82rem; font-weight: 800; color: #1E3A8A;">Manage All Users</div>
                    <div style="font-size: 0.7rem; color: #1D4ED8;">Faculty, Scholar &amp; Students</div>
                </div>
            </a>

            <a href="?tab=theses" style="text-decoration: none; display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 0.95rem; border-radius: 10px; background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; transition: all 0.18s ease;" onmouseover="this.style.background='#D1FAE5'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#ECFDF5'; this.style.transform='none'">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #059669; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                    <i class="fa-solid fa-book-open-reader"></i>
                </div>
                <div>
                    <div style="font-size: 0.82rem; font-weight: 800; color: #064E3B;">Capstone &amp; Panels</div>
                    <div style="font-size: 0.7rem; color: #047857;">Assign Defense Panelists</div>
                </div>
            </a>

            <a href="?tab=approvals" style="text-decoration: none; display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 0.95rem; border-radius: 10px; background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; transition: all 0.18s ease;" onmouseover="this.style.background='#FEF3C7'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#FFFBEB'; this.style.transform='none'">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #D97706; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                    <i class="fa-solid fa-user-check"></i>
                </div>
                <div>
                    <div style="font-size: 0.82rem; font-weight: 800; color: #92400E;">Account Approvals (<?php echo (int)$pending_count; ?>)</div>
                    <div style="font-size: 0.7rem; color: #B45309;">Verify New Registrations</div>
                </div>
            </a>

            <a href="?tab=reports" style="text-decoration: none; display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 0.95rem; border-radius: 10px; background: #F5F3FF; border: 1px solid #DDD6FE; color: #5B21B6; transition: all 0.18s ease;" onmouseover="this.style.background='#EDE9FE'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#F5F3FF'; this.style.transform='none'">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #7C3AED; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                    <i class="fa-solid fa-file-pdf"></i>
                </div>
                <div>
                    <div style="font-size: 0.82rem; font-weight: 800; color: #4C1D95;">Generate Reports</div>
                    <div style="font-size: 0.7rem; color: #6D28D9;">Export Official Summaries</div>
                </div>
            </a>

            <a href="?tab=audit_logs" style="text-decoration: none; display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 0.95rem; border-radius: 10px; background: #F8FAFC; border: 1px solid #CBD5E1; color: #334155; transition: all 0.18s ease;" onmouseover="this.style.background='#F1F5F9'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#F8FAFC'; this.style.transform='none'">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: #334155; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <div style="font-size: 0.82rem; font-weight: 800; color: #0F172A;">System Audit Logs</div>
                    <div style="font-size: 0.7rem; color: #64748B;">Security &amp; Activity Trail</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- TWO-COLUMN EXECUTIVE INSIGHTS: FACULTY MCDA CAPACITY & DEPARTMENTAL PIPELINE -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.25rem;">

    <!-- LEFT COLUMN: FACULTY ADVISER SPECIALIZATIONS, PUBLICATIONS & WORKLOAD CAPACITY -->
    <div class="card" style="border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 2px 8px rgba(15,23,42,0.03); margin-bottom: 0;">
        <div class="card-header" style="padding: 0.95rem 1.25rem; border-bottom: 1px solid #F1F5F9; display: flex; justify-content: space-between; align-items: center;">
            <div class="card-header-left" style="display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 30px; height: 30px; border-radius: 8px; background: #EFF6FF; color: #2563EB; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <h2 style="font-size: 0.95rem; font-weight: 800; color: #0F172A; margin: 0;">Faculty Advisers &bull; MCDA &amp; Capacity Status</h2>
            </div>
            <a href="?tab=users&role_filter=adviser" style="font-size: 0.75rem; font-weight: 700; color: #047857; text-decoration: none;">
                View All <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <div class="card-body" style="padding: 1rem 1.25rem;">
            <?php if(!empty($adv_overview_list)): ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach($adv_overview_list as $adv_item):
                        $cap = max(1, (int)($adv_item['capacity'] ?? 5));
                        $load = (int)($adv_item['active_load'] ?? 0);
                        $pct = min(100, round(($load / $cap) * 100));
                        $pub_cnt = count(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $adv_item['published_papers'] ?? ''))));
                        $bar_col = ($load >= $cap) ? '#DC2626' : (($pct >= 60) ? '#D97706' : '#059669');
                    ?>
                        <div style="border: 1px solid #E2E8F0; border-radius: 10px; padding: 0.75rem 0.9rem; background: #FAFAF9;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.35rem;">
                                <div>
                                    <div style="font-weight: 800; font-size: 0.86rem; color: #0F172A; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                        <?php echo htmlspecialchars($adv_item['name']); ?>
                                        <?php if(!empty($adv_item['academic_degree'])): ?>
                                            <span style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-size: 0.65rem; font-weight: 700; padding: 1px 5px; border-radius: 4px;"><?php echo htmlspecialchars($adv_item['academic_degree']); ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($adv_item['graduation_year'])): ?>
                                            <span style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-size: 0.65rem; font-weight: 700; padding: 1px 5px; border-radius: 4px;">Grad <?php echo htmlspecialchars($adv_item['graduation_year']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.73rem; color: #64748B; margin-top: 2px;">
                                        <i class="fa-solid fa-tags" style="color: #059669;"></i> <?php echo htmlspecialchars(mb_strimwidth($adv_item['expertise'] ?: 'General CEIT Specialization', 0, 75, '...')); ?>
                                    </div>
                                </div>
                                <div style="text-align: right; flex-shrink: 0;">
                                    <span style="font-size: 0.7rem; font-weight: 800; color: <?php echo $bar_col; ?>; background: white; border: 1px solid #CBD5E1; padding: 2px 7px; border-radius: 999px;">
                                        <?php echo $load; ?>/<?php echo $cap; ?> Advisees
                                    </span>
                                    <div style="font-size: 0.68rem; color: #047857; font-weight: 700; margin-top: 3px;">
                                        <i class="fa-solid fa-book-journal-whills"></i> <?php echo $pub_cnt; ?> Papers
                                    </div>
                                </div>
                            </div>
                            <div style="height: 5px; background: #E2E8F0; border-radius: 999px; overflow: hidden;">
                                <div style="width: <?php echo max(6, $pct); ?>%; height: 100%; background: <?php echo $bar_col; ?>; border-radius: 999px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: #64748B; font-size: 0.85rem;">No active faculty advisers found.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT COLUMN: CAPSTONE PIPELINE & RECENT SYSTEM AUDIT STREAM -->
    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        <!-- Departmental & Capstone Pipeline Summary -->
        <div class="card" style="border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 2px 8px rgba(15,23,42,0.03); margin-bottom: 0;">
            <div class="card-header" style="padding: 0.95rem 1.25rem; border-bottom: 1px solid #F1F5F9; display: flex; justify-content: space-between; align-items: center;">
                <div class="card-header-left" style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 30px; height: 30px; border-radius: 8px; background: #ECFDF5; color: #047857; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>
                    <h2 style="font-size: 0.95rem; font-weight: 800; color: #0F172A; margin: 0;">CEIT Capstone &amp; Consultation Pipeline</h2>
                </div>
                <a href="?tab=groups" style="font-size: 0.75rem; font-weight: 700; color: #047857; text-decoration: none;">
                    Capstone Groups <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <div class="card-body" style="padding: 1rem 1.25rem;">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.65rem; margin-bottom: 0.9rem;">
                    <div style="background: #F0FDF4; border: 1px solid #A7F3D0; border-radius: 8px; padding: 0.65rem 0.8rem;">
                        <div style="font-size: 0.7rem; font-weight: 700; color: #047857; text-transform: uppercase;">Adviser Consultations</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #064E3B; margin-top: 2px;"><?php echo $total_consultations; ?> <span style="font-size: 0.72rem; font-weight: 600; color: #047857;">logged sessions</span></div>
                    </div>
                    <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 0.65rem 0.8rem;">
                        <div style="font-size: 0.7rem; font-weight: 700; color: #1D4ED8; text-transform: uppercase;">Adviser Match Requests</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: #1E3A8A; margin-top: 2px;"><?php echo $accepted_matches; ?> <span style="font-size: 0.72rem; font-weight: 600; color: #2563EB;">accepted (<?php echo $pending_matches; ?> pending)</span></div>
                    </div>
                </div>
                <?php if(!empty($dept_stats)): ?>
                    <div style="font-size: 0.72rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 0.45rem;">Student Enrollment by Program / Department</div>
                    <?php foreach($dept_stats as $ds):
                        $dpct = $total_students > 0 ? round(((int)$ds['cnt'] / $total_students) * 100) : 0;
                    ?>
                        <div style="margin-bottom: 0.4rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; font-weight: 600; color: #1E293B; margin-bottom: 2px;">
                                <span><?php echo htmlspecialchars($ds['dept_name']); ?></span>
                                <span style="font-weight: 800; color: #047857;"><?php echo (int)$ds['cnt']; ?> (<?php echo $dpct; ?>%)</span>
                            </div>
                            <div style="height: 6px; background: #E2E8F0; border-radius: 999px; overflow: hidden;">
                                <div style="width: <?php echo max(5, $dpct); ?>%; height: 100%; background: linear-gradient(90deg, #047857, #10B981); border-radius: 999px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent System Audit Activity -->
        <div class="card" style="border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 2px 8px rgba(15,23,42,0.03); margin-bottom: 0;">
            <div class="card-header" style="padding: 0.9rem 1.25rem; border-bottom: 1px solid #F1F5F9; display: flex; justify-content: space-between; align-items: center;">
                <div class="card-header-left" style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 30px; height: 30px; border-radius: 8px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 0.85rem;">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <h2 style="font-size: 0.95rem; font-weight: 800; color: #0F172A; margin: 0;">Recent System Activity</h2>
                </div>
                <a href="?tab=audit_logs" style="font-size: 0.75rem; font-weight: 700; color: #047857; text-decoration: none;">
                    Full Audit Trail <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            <div class="card-body" style="padding: 0.85rem 1.25rem;">
                <?php if(!empty($recent_logs)): ?>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach($recent_logs as $lg): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.45rem 0; border-bottom: 1px dashed #E2E8F0; font-size: 0.76rem;">
                                <div style="overflow: hidden;">
                                    <strong style="color: #0F172A;"><?php echo htmlspecialchars($lg['user_name'] ?? 'System'); ?></strong>
                                    <span style="color: #475569;"> &bull; <?php echo htmlspecialchars(mb_strimwidth($lg['details'] ?? $lg['action_type'] ?? '', 0, 62, '...')); ?></span>
                                </div>
                                <span style="font-size: 0.68rem; color: #64748B; white-space: nowrap; flex-shrink: 0;">
                                    <?php echo !empty($lg['created_at']) ? date('M d, h:i A', strtotime($lg['created_at'])) : ''; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 1rem; color: #64748B; font-size: 0.8rem;">No recent system logs recorded.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
