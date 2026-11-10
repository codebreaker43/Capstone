<?php
$dept = isset($_GET['dept']) ? $_GET['dept'] : 'Computer Engineering';

if (!function_exists('isDeptActive')) {
    function isDeptActive($current, $target) {
        if ($target === 'all') {
            return ($current === 'all' || empty($current)) ? 'active' : '';
        }
        return $current === $target ? 'active' : '';
    }
}

$dept_clean = $conn->real_escape_string(preg_replace('/\s*\(.*?\)/', '', $dept));
$dept_escaped = $conn->real_escape_string($dept);

$faculty_res = $conn->query("SELECT u.*, a.expertise as specialization, a.hire_date, a.academic_degree, a.graduation_year, a.google_scholar_url, a.google_scholar_id, a.published_papers, a.previous_advised_titles, a.publications_last_synced FROM user u JOIN adviser_profile a ON u.id = a.user_id WHERE u.role='adviser' AND u.account_status != 'archived' AND (a.department='$dept_escaped' OR a.department='$dept_clean' OR a.department LIKE '%$dept_clean%') ORDER BY u.name");
$students_res = $conn->query("SELECT u.*, s.student_id, s.department, s.group_id, s.is_group_leader, s.thesis_topic, cg.group_name, cg.thesis_topic as group_topic FROM user u JOIN student_profile s ON u.id = s.user_id LEFT JOIN capstone_group cg ON s.group_id = cg.id WHERE u.role='student' AND u.account_status != 'archived' AND (s.department='$dept_escaped' OR s.department='$dept_clean' OR s.department LIKE '%$dept_clean%') ORDER BY u.name");
$panel_res = $conn->query("SELECT u.*, p.expertise as specialization, p.academic_degree, p.graduation_year, p.google_scholar_url, p.google_scholar_id, p.published_papers, p.previous_advised_titles, p.publications_last_synced FROM user u JOIN panel_profile p ON u.id = p.user_id WHERE u.role='panel' AND u.account_status != 'archived' AND (p.department='$dept_escaped' OR p.department='$dept_clean' OR p.department LIKE '%$dept_clean%') ORDER BY u.name");
$secretary_res = $conn->query("SELECT u.*, s.office, s.department FROM user u LEFT JOIN secretary_profile s ON u.id = s.user_id WHERE u.role='secretary' AND u.account_status != 'archived' ORDER BY u.name");
?>

<style>
.table-pagination-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: #F8FAFC;
    border-top: 1px solid #E2E8F0;
    font-size: 0.8rem;
    color: #64748B;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.table-pagination-btn {
    padding: 0.28rem 0.6rem;
    border: 1px solid #CBD5E1;
    background: #FFFFFF;
    color: #334155;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.table-pagination-btn:hover:not(:disabled) {
    background: #059669;
    color: #ffffff;
    border-color: #059669;
}
.table-pagination-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.table-pagination-btn.active {
    background: #059669;
    color: #ffffff;
    border-color: #059669;
}
</style>

<div class="admin-users-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div class="dept-tabs" style="margin-bottom: 0;">
            <button class="dept-tab <?= isDeptActive($dept, 'Computer Engineering') ?>" onclick="window.location.href='?tab=users&dept=Computer Engineering'">
                <i class="fa-solid fa-gear"></i> Computer Engineering (ComEng)
            </button>
            <button class="dept-tab <?= isDeptActive($dept, 'Information Technology') ?>" onclick="window.location.href='?tab=users&dept=Information Technology'">
                <i class="fa-solid fa-globe"></i> Information Technology (IT)
            </button>
            <button class="dept-tab <?= isDeptActive($dept, 'Electrical Engineering') ?>" onclick="window.location.href='?tab=users&dept=Electrical Engineering'">
                <i class="fa-solid fa-plug"></i> Electrical Engineering (EE)
            </button>
        </div>
        <button class="btn-primary" onclick="openAddUserModal()" style="padding: 0.8rem 1.5rem;"><i class="fa-solid fa-plus"></i> Add New User</button>
    </div>

    <div class="tables-container">
        <!-- Advisers Table -->
        <div class="colored-table-card faculty">
            <div class="colored-header">
                <h3><i class="fa-solid fa-chalkboard-user"></i> ADVISERS &amp; RESEARCH SPECIALIZATIONS</h3>
                <span class="table-count-badge"><?= $faculty_res->num_rows ?> Active</span>
            </div>
            <div class="colored-table-body">
                <table class="colored-table">
                    <tr>
                        <th>Faculty Info &amp; Graduation Year</th>
                        <th>Research Specializations</th>
                        <th>Published Research Papers &amp; Google Scholar</th>
                        <th>Previously Supervised Research Titles</th>
                        <th>Actions</th>
                    </tr>
                    <?php if($faculty_res->num_rows > 0): while($f = $faculty_res->fetch_assoc()): 
                        $f_pubs = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $f['published_papers'] ?? ''))));
                        $f_prev_db = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $f['previous_advised_titles'] ?? ''))));
                        $f_sys_titles = get_adviser_supervised_titles_from_system($conn, (int)$f['id']);
                        $f_all_prev = array_values(array_unique(array_merge($f_prev_db, $f_sys_titles)));
                        $f_tags = array_values(array_filter(array_map('trim', explode(',', $f['specialization'] ?? ''))));
                    ?>
                    <tr>
                        <td style="vertical-align: top;">
                            <div style="font-weight: 800; color: #0F172A; font-size: 0.92rem; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                <span><?= htmlspecialchars($f['name']) ?></span>
                                <?php if(!empty($f['academic_degree'])): ?>
                                    <span style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 999px;">
                                        <?= htmlspecialchars($f['academic_degree']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="color: #64748B; font-size: 0.78rem; margin-top: 2px;"><?= htmlspecialchars($f['email']) ?></div>
                            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.35rem;">
                                <?php if(!empty($f['graduation_year'])): ?>
                                    <span style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.25rem;">
                                        <i class="fa-solid fa-graduation-cap"></i> Graduated: <?= htmlspecialchars($f['graduation_year']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if(!empty($f['hire_date']) && $f['hire_date'] !== '0000-00-00'): ?>
                                    <span style="background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; font-size: 0.68rem; font-weight: 600; padding: 2px 6px; border-radius: 6px;">
                                        Hired: <?= htmlspecialchars($f['hire_date']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="vertical-align: top; max-width: 240px;">
                            <?php if(!empty($f_tags)): ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 0.3rem;">
                                    <?php foreach(array_slice($f_tags, 0, 6) as $ftag): ?>
                                        <span style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.72rem; font-weight: 600; padding: 2px 7px; border-radius: 6px;">
                                            <?= htmlspecialchars($ftag) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if(count($f_tags) > 6): ?>
                                        <span style="font-size: 0.7rem; color: #64748B; font-weight: 700;">+<?= count($f_tags) - 6 ?> more</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #94A3B8; font-size: 0.78rem; font-style: italic;">No specialization tags</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: top; max-width: 300px;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 0.3rem;">
                                <?php 
                                    $f_scholar_link = format_google_scholar_link($f['google_scholar_url'] ?? '', $f['name'] ?? ''); 
                                ?>
                                <?php if(!empty($f_scholar_link)): ?>
                                    <a href="<?= htmlspecialchars($f_scholar_link) ?>" target="_blank" rel="noopener noreferrer" style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 999px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                                        <i class="fa-solid fa-graduation-cap"></i> Google Scholar Linked
                                    </a>
                                <?php endif; ?>
                                <span style="background: <?= count($f_pubs) > 0 ? '#F0FDF4' : '#F8FAFC' ?>; color: <?= count($f_pubs) > 0 ? '#065F46' : '#64748B' ?>; border: 1px solid <?= count($f_pubs) > 0 ? '#A7F3D0' : '#E2E8F0' ?>; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 999px;">
                                    <i class="fa-solid fa-book-journal-whills"></i> <?= count($f_pubs) ?> Paper<?= count($f_pubs) === 1 ? '' : 's' ?>
                                </span>
                            </div>
                            <?php if(!empty($f_pubs)): ?>
                                <div style="font-size: 0.75rem; color: #334155; line-height: 1.35;">
                                    <?php foreach(array_slice($f_pubs, 0, 2) as $fp_line): ?>
                                        <div style="margin-bottom: 0.2rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            • <?= htmlspecialchars(preg_replace('/^\d+[\.\)]\s*/', '', $fp_line)) ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if(count($f_pubs) > 2): ?>
                                        <div style="color: #059669; font-weight: 700; font-size: 0.7rem;">+<?= count($f_pubs) - 2 ?> more publication(s)</div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #94A3B8; font-size: 0.75rem; font-style: italic;">No publications synced yet</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: top; max-width: 270px;">
                            <?php if(!empty($f_all_prev)): ?>
                                <div style="margin-bottom: 0.25rem;">
                                    <span style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 999px;">
                                        <i class="fa-solid fa-folder-tree"></i> <?= count($f_all_prev) ?> Supervised Title<?= count($f_all_prev) === 1 ? '' : 's' ?>
                                    </span>
                                </div>
                                <div style="font-size: 0.75rem; color: #334155; line-height: 1.35;">
                                    <?php foreach(array_slice($f_all_prev, 0, 2) as $f_pt): ?>
                                        <div style="margin-bottom: 0.2rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            • <?= htmlspecialchars(preg_replace('/^[\-\*\d\.\)\s]+/', '', $f_pt)) ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if(count($f_all_prev) > 2): ?>
                                        <div style="color: #B45309; font-weight: 700; font-size: 0.7rem;">+<?= count($f_all_prev) - 2 ?> more advised title(s)</div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #94A3B8; font-size: 0.75rem; font-style: italic;">None recorded yet</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap; vertical-align: top;">
                            <button type="button" class="btn" style="padding: 0.4rem 0.6rem; background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 4px; cursor: pointer;"
                                data-id="<?= (int)$f['id'] ?>"
                                data-fname="<?= htmlspecialchars($f['firstname'] ?? '', ENT_QUOTES) ?>"
                                data-lname="<?= htmlspecialchars($f['lastname'] ?? '', ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($f['email'] ?? '', ENT_QUOTES) ?>"
                                data-role="adviser"
                                data-spec="<?= htmlspecialchars($f['specialization'] ?? '', ENT_QUOTES) ?>"
                                data-hire="<?= htmlspecialchars($f['hire_date'] ?? '', ENT_QUOTES) ?>"
                                data-degree="<?= htmlspecialchars($f['academic_degree'] ?? '', ENT_QUOTES) ?>"
                                data-gradyear="<?= htmlspecialchars($f['graduation_year'] ?? '', ENT_QUOTES) ?>"
                                data-scholar="<?= htmlspecialchars($f_scholar_link ?? '', ENT_QUOTES) ?>"
                                data-pubs="<?= htmlspecialchars($f['published_papers'] ?? '', ENT_QUOTES) ?>"
                                data-prevtitles="<?= htmlspecialchars(implode("\n", $f_all_prev), ENT_QUOTES) ?>"
                                onclick="openEditUserFromDataset(this)" title="Edit Faculty Info & Research Publications"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button type="button" class="btn" style="padding: 0.4rem 0.6rem; background: #FFF7ED; color: #EA580C; border: 1px solid #FED7AA; border-radius: 4px; cursor: pointer; margin-left: 0.2rem;" onclick="archiveUser(<?= $f['id'] ?>, '<?= addslashes(htmlspecialchars($f['name'])) ?>', 'Adviser')" title="Archive"><i class="fa-solid fa-box-archive"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding: 2rem; color: #64748B;">No advisers found.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Students Table -->
        <div class="colored-table-card student">
            <div class="colored-header">
                <h3><i class="fa-solid fa-user-graduate"></i> STUDENTS</h3>
                <span class="table-count-badge"><?= $students_res->num_rows ?> Active</span>
            </div>
            <div class="colored-table-body">
                <table class="colored-table">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Capstone Title</th>
                        <th>Actions</th>
                    </tr>
                    <?php if($students_res->num_rows > 0): while($s = $students_res->fetch_assoc()): ?>
                    <tr>
                        <td style="vertical-align: top;">
                            <div style="font-weight: 700; color: #0F172A; font-size: 0.92rem; display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
                                <span><?= htmlspecialchars($s['name']) ?></span>
                                <?php if(!empty($s['is_group_leader'])): ?>
                                    <span class="badge" style="background: #FEF3C7; color: #B45309; border: 1px solid #FCD34D; font-size: 0.68rem; padding: 0.1rem 0.4rem; font-weight: 700;">
                                        <i class="fa-solid fa-crown"></i> Leader
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if(!empty($s['student_id'])): ?>
                                <div style="margin-top: 0.2rem; font-size: 0.74rem; color: #059669; font-weight: 600; display: flex; align-items: center; gap: 0.3rem;">
                                    <i class="fa-solid fa-id-card"></i> ID: <?= htmlspecialchars($s['student_id']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if(!empty($s['group_name']) && !empty($s['group_id'])): 
                                $st_members = get_group_members_roster($conn, (int)$s['group_id']);
                                if(!empty($st_members)):
                            ?>
                                <div style="margin-top: 0.45rem; padding: 0.35rem 0.55rem; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; font-size: 0.74rem; max-width: 250px;">
                                    <div style="font-size: 0.68rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 0.25rem; display: flex; align-items: center; justify-content: space-between;">
                                        <span><i class="fa-solid fa-users" style="color: var(--csu-green);"></i> Group Members:</span>
                                        <span style="color: var(--csu-green); font-weight: 700;"><?= count($st_members) ?>/3</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                        <?php for($i = 0; $i < 3; $i++): $stm = $st_members[$i] ?? null; ?>
                                            <?php if($stm): ?>
                                                <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; justify-content: space-between; gap: 0.35rem;">
                                                    <span style="color: #1E293B; font-weight: <?= $stm['is_leader'] ? '700' : '500' ?>; overflow: hidden; text-overflow: ellipsis; max-width: 175px;">
                                                        <i class="fa-solid <?= $stm['is_leader'] ? 'fa-crown' : 'fa-user' ?>" style="color: <?= $stm['is_leader'] ? '#D97706' : '#047857' ?>; font-size: 0.65rem; margin-right: 0.25rem;"></i>
                                                        <?= htmlspecialchars($stm['name']) ?>
                                                    </span>
                                                    <span style="color: #64748B; font-size: 0.65rem; font-weight: 600;">
                                                        <?= $stm['is_leader'] ? 'Leader' : 'M'.($i+1) ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div style="color: #94A3B8; font-style: italic; font-size: 0.68rem;">
                                                    <i class="fa-regular fa-circle" style="font-size: 0.55rem; margin-right: 0.25rem;"></i> Slot <?= $i+1 ?>: Vacant
                                                </div>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endif; endif; ?>
                        </td>
                        <td style="vertical-align: top; color: #475569;"><?= htmlspecialchars($s['email']) ?></td>
                        <td style="vertical-align: top;">
                            <?php 
                            $capstone_title = !empty($s['thesis_topic']) ? $s['thesis_topic'] : ($s['group_topic'] ?? '');
                            if(!empty($capstone_title)): 
                            ?>
                                <div style="font-weight: 600; color: #1E293B; font-size: 0.85rem; line-height: 1.4; max-width: 320px;">
                                    <i class="fa-solid fa-book" style="color: var(--primary-blue); font-size: 0.78rem; margin-right: 0.25rem;"></i>
                                    <?= htmlspecialchars($capstone_title) ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #94A3B8; font-size: 0.78rem; font-style: italic;">No title submitted</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;">
                            <button class="btn" style="padding: 0.4rem 0.6rem; background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 4px; cursor: pointer;" onclick="openEditModal(<?= $s['id'] ?>, '<?= addslashes($s['firstname']) ?>', '<?= addslashes($s['lastname']) ?>', '<?= addslashes($s['email']) ?>', 'student', '', '', '<?= addslashes($s['department']) ?>', '<?= addslashes($s['student_id'] ?? '') ?>')"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button class="btn" style="padding: 0.4rem 0.6rem; background: #FFF7ED; color: #EA580C; border: 1px solid #FED7AA; border-radius: 4px; cursor: pointer; margin-left: 0.2rem;" onclick="archiveUser(<?= $s['id'] ?>, '<?= addslashes(htmlspecialchars($s['name'])) ?>', 'Student Candidate')" title="Archive"><i class="fa-solid fa-box-archive"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" style="text-align:center; padding: 2rem; color: #64748B;">No students found.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <!-- Panel Members Table -->
        <div class="colored-table-card panel">
            <div class="colored-header">
                <h3><i class="fa-solid fa-users"></i> PANEL MEMBERS &amp; RESEARCH SPECIALIZATIONS</h3>
                <span class="table-count-badge"><?= $panel_res->num_rows ?> Active</span>
            </div>
            <div class="colored-table-body">
                <table class="colored-table">
                    <tr>
                        <th>Faculty Info &amp; Graduation Year</th>
                        <th>Research Specializations</th>
                        <th>Published Research Papers &amp; Google Scholar</th>
                        <th>Actions</th>
                    </tr>
                    <?php if($panel_res->num_rows > 0): while($p = $panel_res->fetch_assoc()): 
                        $p_pubs = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $p['published_papers'] ?? ''))));
                        $p_tags = array_values(array_filter(array_map('trim', explode(',', $p['specialization'] ?? ''))));
                    ?>
                    <tr>
                        <td style="vertical-align: top;">
                            <div style="font-weight: 800; color: #0F172A; font-size: 0.92rem; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                <span><?= htmlspecialchars($p['name']) ?></span>
                                <?php if(!empty($p['academic_degree'])): ?>
                                    <span style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 999px;">
                                        <?= htmlspecialchars($p['academic_degree']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="color: #64748B; font-size: 0.78rem; margin-top: 2px;"><?= htmlspecialchars($p['email']) ?></div>
                            <?php if(!empty($p['graduation_year'])): ?>
                                <div style="margin-top: 0.3rem;">
                                    <span style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.25rem;">
                                        <i class="fa-solid fa-graduation-cap"></i> Graduated: <?= htmlspecialchars($p['graduation_year']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: top; max-width: 260px;">
                            <?php if(!empty($p_tags)): ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 0.3rem;">
                                    <?php foreach(array_slice($p_tags, 0, 6) as $ptag): ?>
                                        <span style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.72rem; font-weight: 600; padding: 2px 7px; border-radius: 6px;">
                                            <?= htmlspecialchars($ptag) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #94A3B8; font-size: 0.78rem; font-style: italic;">No specialization tags</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: top; max-width: 320px;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 0.3rem;">
                                <?php 
                                    $p_scholar_link = format_google_scholar_link($p['google_scholar_url'] ?? '', $p['name'] ?? ''); 
                                ?>
                                <?php if(!empty($p_scholar_link)): ?>
                                    <a href="<?= htmlspecialchars($p_scholar_link) ?>" target="_blank" rel="noopener noreferrer" style="background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 999px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                                        <i class="fa-solid fa-graduation-cap"></i> Google Scholar
                                    </a>
                                <?php endif; ?>
                                <span style="background: <?= count($p_pubs) > 0 ? '#F0FDF4' : '#F8FAFC' ?>; color: <?= count($p_pubs) > 0 ? '#065F46' : '#64748B' ?>; border: 1px solid <?= count($p_pubs) > 0 ? '#A7F3D0' : '#E2E8F0' ?>; font-size: 0.7rem; font-weight: 700; padding: 2px 7px; border-radius: 999px;">
                                    <i class="fa-solid fa-book-journal-whills"></i> <?= count($p_pubs) ?> Paper<?= count($p_pubs) === 1 ? '' : 's' ?>
                                </span>
                            </div>
                            <?php if(!empty($p_pubs)): ?>
                                <div style="font-size: 0.75rem; color: #334155; line-height: 1.35;">
                                    <?php foreach(array_slice($p_pubs, 0, 2) as $pp_line): ?>
                                        <div style="margin-bottom: 0.2rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            • <?= htmlspecialchars(preg_replace('/^\d+[\.\)]\s*/', '', $pp_line)) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #94A3B8; font-size: 0.75rem; font-style: italic;">No publications synced yet</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap; vertical-align: top;">
                            <button type="button" class="btn" style="padding: 0.4rem 0.6rem; background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 4px; cursor: pointer;"
                                data-id="<?= (int)$p['id'] ?>"
                                data-fname="<?= htmlspecialchars($p['firstname'] ?? '', ENT_QUOTES) ?>"
                                data-lname="<?= htmlspecialchars($p['lastname'] ?? '', ENT_QUOTES) ?>"
                                data-email="<?= htmlspecialchars($p['email'] ?? '', ENT_QUOTES) ?>"
                                data-role="panel"
                                data-spec="<?= htmlspecialchars($p['specialization'] ?? '', ENT_QUOTES) ?>"
                                data-hire=""
                                data-degree="<?= htmlspecialchars($p['academic_degree'] ?? '', ENT_QUOTES) ?>"
                                data-gradyear="<?= htmlspecialchars($p['graduation_year'] ?? '', ENT_QUOTES) ?>"
                                data-scholar="<?= htmlspecialchars($p_scholar_link ?? '', ENT_QUOTES) ?>"
                                data-pubs="<?= htmlspecialchars($p['published_papers'] ?? '', ENT_QUOTES) ?>"
                                data-prevtitles="<?= htmlspecialchars($p['previous_advised_titles'] ?? '', ENT_QUOTES) ?>"
                                onclick="openEditUserFromDataset(this)" title="Edit Panelist Info & Publications"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button type="button" class="btn" style="padding: 0.4rem 0.6rem; background: #FFF7ED; color: #EA580C; border: 1px solid #FED7AA; border-radius: 4px; cursor: pointer; margin-left: 0.2rem;" onclick="archiveUser(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>', 'Panel Member')" title="Archive"><i class="fa-solid fa-box-archive"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" style="text-align:center; padding: 2rem; color: #64748B;">No panel members found.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        <!-- Defense Secretariat Table -->
        <div class="colored-table-card secretary" style="margin-top: 1.5rem;">
            <div class="colored-header">
                <h3><i class="fa-solid fa-file-signature"></i> DEFENSE SECRETARIAT</h3>
                <span class="table-count-badge"><?= $secretary_res->num_rows ?> Active</span>
            </div>
            <div class="colored-table-body">
                <table class="colored-table">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Assigned Office</th>
                        <th>Actions</th>
                    </tr>
                    <?php if($secretary_res->num_rows > 0): while($sec = $secretary_res->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($sec['name']) ?></strong></td>
                        <td><?= htmlspecialchars($sec['email']) ?></td>
                        <td><?= htmlspecialchars($sec['office'] ?: 'CEIT Defense Secretariat Office') ?></td>
                        <td style="white-space: nowrap;">
                            <button class="btn" style="padding: 0.4rem 0.6rem; background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 4px; cursor: pointer;" onclick="openEditModal(<?= $sec['id'] ?>, '<?= addslashes($sec['firstname']) ?>', '<?= addslashes($sec['lastname']) ?>', '<?= addslashes($sec['email']) ?>', 'secretary', '<?= addslashes($sec['office'] ?: '') ?>', '')"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button class="btn" style="padding: 0.4rem 0.6rem; background: #FFF7ED; color: #EA580C; border: 1px solid #FED7AA; border-radius: 4px; cursor: pointer; margin-left: 0.2rem;" onclick="archiveUser(<?= $sec['id'] ?>, '<?= addslashes(htmlspecialchars($sec['name'])) ?>', 'Defense Secretariat')" title="Archive"><i class="fa-solid fa-box-archive"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" style="text-align:center; padding: 2rem; color: #64748B;">No secretariat members found.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    

</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content" style="max-width: 720px; max-height: 90vh; overflow-y: auto;">
        <span class="close" onclick="document.getElementById('addUserModal').style.display='none'">&times;</span>
        <h2 style="margin-bottom: 1.15rem; color: var(--primary-blue);"><i class="fa-solid fa-user-plus"></i> Add New User &amp; Faculty Research Profile</h2>
        <form action="action.php" method="POST">
            <input type="hidden" name="admin_add_user" value="1">
            <input type="hidden" name="department" id="form_department" value="<?= htmlspecialchars($dept) ?>">
            <input type="hidden" name="role" id="form_role" value="adviser">

            <div class="grid-form" style="margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label class="input-label">First Name <span>*</span></label>
                    <input type="text" name="firstname" id="add_firstname" class="form-control" placeholder="Enter first name" required style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #E2E8F0;" oninput="autoGenerateAddUserEmail()">
                </div>
                <div class="form-group">
                    <label class="input-label">Last Name <span>*</span></label>
                    <input type="text" name="lastname" id="add_lastname" class="form-control" placeholder="Enter last name" required style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #E2E8F0;" oninput="autoGenerateAddUserEmail()">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                        <label class="input-label" style="margin-bottom: 0;">Email <span>*</span></label>
                        <span style="font-size: 0.75rem; color: #059669; font-weight: 700;">Format: firstname.lastname@csucc.edu.ph</span>
                    </div>
                    <input type="email" name="email" id="add_email" class="form-control" placeholder="firstname.lastname@csucc.edu.ph" required style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #E2E8F0;">
                </div>
                
                <div class="form-group" style="grid-column: span 2;">
                    <label class="input-label">Password <span>*</span></label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" name="password" id="add_user_password" class="form-control" placeholder="Enter password (e.g. password123)" required style="width: 100%; padding: 0.75rem 2.5rem 0.75rem 0.75rem; border-radius: 8px; border: 1px solid #E2E8F0;">
                        <i class="fa-regular fa-eye" id="toggleAddUserPwdIcon" onclick="toggleAddUserPassword()" style="position: absolute; right: 12px; cursor: pointer; color: #64748B; font-size: 1rem; padding: 4px;" title="Show/Hide Password"></i>
                    </div>
                </div>
                
                <div class="form-group" style="grid-column: span 2;">
                    <label class="input-label">User Role <span>*</span></label>
                    <div class="role-toggle-group">
                        <button type="button" class="role-btn active-faculty" id="btn-faculty" onclick="selectRole('adviser')"><i class="fa-solid fa-chalkboard-user"></i> ADVISER</button>
                        <button type="button" class="role-btn" id="btn-panel" onclick="selectRole('panel')"><i class="fa-solid fa-users"></i> PANEL</button>
                        <button type="button" class="role-btn" id="btn-student" onclick="selectRole('student')"><i class="fa-solid fa-user-graduate"></i> STUDENT</button>
                    </div>
                </div>

                <!-- 3 Courses Option (Shown when Adviser or Student is selected) -->
                <div class="form-group" id="course_group" style="grid-column: span 2; display: none;">
                    <label class="input-label" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Select <span id="course_role_label" style="color: inherit;">Student</span> Course <span>*</span></span>
                        <span style="font-size: 0.75rem; color: #64748B; font-weight: normal;"><i class="fa-solid fa-building-columns"></i> CSU Cabadbaran CEIT</span>
                    </label>
                    <div class="course-options-grid">
                        <div class="course-card <?= $dept == 'Computer Engineering' ? 'active-course-card' : '' ?>" id="card_comeng" onclick="selectCourse('Computer Engineering')">
                            <input type="radio" name="student_course" value="Computer Engineering" id="course_comeng" <?= $dept == 'Computer Engineering' ? 'checked' : '' ?>>
                            <div class="course-icon" style="background: #EFF6FF; color: #2563EB;">
                                <i class="fa-solid fa-gear"></i>
                            </div>
                            <div class="course-info">
                                <span class="course-code">BSCpE</span>
                                <span class="course-title">Computer Engineering</span>
                            </div>
                            <i class="fa-solid fa-circle-check course-check"></i>
                        </div>

                        <div class="course-card <?= $dept == 'Information Technology' ? 'active-course-card' : '' ?>" id="card_it" onclick="selectCourse('Information Technology')">
                            <input type="radio" name="student_course" value="Information Technology" id="course_it" <?= $dept == 'Information Technology' ? 'checked' : '' ?>>
                            <div class="course-icon" style="background: #F0FDF4; color: #16A34A;">
                                <i class="fa-solid fa-globe"></i>
                            </div>
                            <div class="course-info">
                                <span class="course-code">BSIT</span>
                                <span class="course-title">Information Technology</span>
                            </div>
                            <i class="fa-solid fa-circle-check course-check"></i>
                        </div>

                        <div class="course-card <?= $dept == 'Electrical Engineering' ? 'active-course-card' : '' ?>" id="card_ee" onclick="selectCourse('Electrical Engineering')">
                            <input type="radio" name="student_course" value="Electrical Engineering" id="course_ee" <?= $dept == 'Electrical Engineering' ? 'checked' : '' ?>>
                            <div class="course-icon" style="background: #FFFBEB; color: #D97706;">
                                <i class="fa-solid fa-plug"></i>
                            </div>
                            <div class="course-info">
                                <span class="course-code">BSEE</span>
                                <span class="course-title">Electrical Engineering</span>
                            </div>
                            <i class="fa-solid fa-circle-check course-check"></i>
                        </div>
                    </div>
                </div>

                <div class="form-group" id="student_id_group" style="grid-column: span 2; display: none;">
                    <label class="input-label">School ID Number <small style="color: #64748B; font-weight: normal;">(Optional for Admin Entry)</small></label>
                    <input type="text" name="school_id" id="form_school_id" class="form-control" placeholder="e.g. 2021-00123" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #E2E8F0;">
                </div>

                <!-- FACULTY INFORMATION, GOOGLE SCHOLAR INTEGRATION & RESEARCH SPECIALIZATIONS -->
                <div id="specialization_group" style="grid-column: span 2; background: #F8FAFC; border: 1.5px solid #CBD5E1; border-radius: 10px; padding: 1rem 1.15rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.4rem;">
                        <div style="font-size: 0.85rem; font-weight: 800; color: #065F46; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fa-solid fa-microscope"></i> Faculty Information, Google Scholar &amp; Research Specializations
                        </div>
                        <span style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.68rem; font-weight: 700; padding: 2px 7px; border-radius: 999px;">
                            <i class="fa-solid fa-robot"></i> Powers AI Adviser Matching
                        </span>
                    </div>

                    <!-- Google Scholar Integration Box -->
                    <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 0.75rem 0.9rem; margin-bottom: 0.9rem;">
                        <label class="input-label" style="color: #1E3A8A; font-size: 0.78rem; margin-bottom: 0.3rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                            <span><i class="fa-solid fa-graduation-cap" style="color: #2563EB;"></i> Google Scholar Profile URL or Author ID <small style="font-weight: normal; color: #475569;">(Retrieves publications automatically — no CSV needed)</small></span>
                        </label>
                        <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                            <input type="text" name="google_scholar_url" id="form_google_scholar_url" class="form-control" placeholder="e.g. https://scholar.google.com/citations?user=XXXXXXXX or Author Name" style="flex: 1; min-width: 220px; padding: 0.55rem 0.75rem; border-radius: 7px; border: 1px solid #93C5FD; font-size: 0.82rem; background: #FFFFFF;">
                            <button type="button" onclick="syncGoogleScholarIntoForm('add')" id="btn_sync_scholar_add" style="background: #2563EB; color: #FFFFFF; border: none; border-radius: 7px; padding: 0.55rem 0.9rem; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap;">
                                <i class="fa-solid fa-cloud-arrow-down"></i> Retrieve from Google Scholar
                            </button>
                        </div>
                        <div id="scholar_sync_status_add" style="font-size: 0.73rem; color: #1E40AF; margin-top: 0.35rem; display: none;"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 0.85rem;">
                        <div>
                            <label class="input-label" style="font-size: 0.78rem;">Highest Academic Degree</label>
                            <input type="text" name="academic_degree" id="form_academic_degree" class="form-control" placeholder="e.g., MIT, MSIT, MSCS, PhD" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.82rem;">
                        </div>
                        <div>
                            <label class="input-label" style="font-size: 0.78rem;">Graduation Year <small style="color:#64748B;font-weight:normal;">(If required)</small></label>
                            <input type="text" name="graduation_year" id="form_graduation_year" class="form-control" placeholder="e.g., 2019" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.82rem;">
                        </div>
                        <div id="hiredate_group">
                            <label class="input-label" style="font-size: 0.78rem;">Hire Date</label>
                            <input type="date" name="hire_date" id="form_hire_date" class="form-control" style="width: 100%; padding: 0.58rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.82rem;">
                        </div>
                    </div>

                    <div style="margin-bottom: 0.85rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; flex-wrap: wrap; gap: 0.35rem;">
                            <label class="input-label" style="font-size: 0.8rem; margin-bottom: 0;">Research Specializations <span>*</span></label>
                            <button type="button" onclick="autoIdentifySpecializationsFromPubs('add')" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 6px; padding: 2px 8px; font-size: 0.72rem; font-weight: 700; cursor: pointer;">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Identify from Publications &amp; Supervised Titles
                            </button>
                        </div>
                        <input type="text" name="specialization" id="form_specialization" class="form-control" placeholder="e.g., Machine Learning, Internet of Things (IoT), Web Application Development, Cybersecurity" style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.84rem;">
                    </div>

                    <div style="margin-bottom: 0.85rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; flex-wrap: wrap;">
                            <label class="input-label" style="font-size: 0.8rem; margin-bottom: 0;"><i class="fa-solid fa-book-journal-whills" style="color: #047857;"></i> Published Research Papers <small style="color: #64748B; font-weight: normal;">(One paper per line — synced from Google Scholar or entered manually)</small></label>
                        </div>
                        <textarea name="published_papers" id="form_published_papers" rows="3" class="form-control" placeholder="1. Title of Published Research Paper (Year) — Journal / Conference&#10;2. Title of Second Research Paper (Year) — Venue" style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.82rem; font-family: inherit; resize: vertical;"></textarea>
                    </div>

                    <div id="add_prev_advised_wrap">
                        <label class="input-label" style="font-size: 0.8rem; margin-bottom: 0.3rem; display: block;"><i class="fa-solid fa-folder-tree" style="color: #D97706;"></i> Previous Research Titles Supervised / Advised <small style="color: #64748B; font-weight: normal;">(One capstone title per line)</small></label>
                        <textarea name="previous_advised_titles" id="form_previous_advised_titles" rows="3" class="form-control" placeholder="e.g.&#10;IoT-Based Smart Farm Irrigation and Soil Nutrient Monitoring System (2024)&#10;AI-Powered Student Academic Performance Early Warning Portal (2023)" style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.82rem; font-family: inherit; resize: vertical;"></textarea>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding: 0.85rem;"><i class="fa-solid fa-plus"></i> Create User Account</button>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content" style="max-width: 680px; max-height: 90vh; overflow-y: auto;">
        <span class="close" onclick="document.getElementById('editUserModal').style.display='none'">&times;</span>
        <h2 style="margin-bottom: 1.25rem; color: var(--primary-blue);"><i class="fa-solid fa-user-pen"></i> Edit User &amp; Faculty Research Information</h2>
        <form action="action.php" method="POST">
            <input type="hidden" name="admin_edit_user" value="1">
            <input type="hidden" name="edit_user_id" id="edit_user_id">
            <input type="hidden" name="role" id="edit_role">
            <input type="hidden" name="department" id="edit_department" value="<?= htmlspecialchars($dept) ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                <div class="form-group">
                    <label class="input-label">First Name</label>
                    <input type="text" name="firstname" id="edit_firstname" class="form-control" required style="width: 100%; padding: 0.7rem; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 0.75rem;">
                </div>
                <div class="form-group">
                    <label class="input-label">Last Name</label>
                    <input type="text" name="lastname" id="edit_lastname" class="form-control" required style="width: 100%; padding: 0.7rem; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 0.75rem;">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                <div class="form-group">
                    <label class="input-label">Email <small style="color: #059669; font-weight: normal;">(@csucc.edu.ph)</small></label>
                    <input type="email" name="email" id="edit_email" class="form-control" placeholder="firstname.lastname@csucc.edu.ph" required style="width: 100%; padding: 0.7rem; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 0.85rem;">
                </div>
                <div class="form-group">
                    <label class="input-label">New Password <small style="color: #64748B; font-weight: normal;">(Optional)</small></label>
                    <div style="position: relative; display: flex; align-items: center; margin-bottom: 0.85rem;">
                        <input type="password" name="password" id="edit_user_password" class="form-control" placeholder="Leave blank to keep unchanged" style="width: 100%; padding: 0.7rem 2.5rem 0.7rem 0.7rem; border-radius: 8px; border: 1px solid #E2E8F0;">
                        <i class="fa-regular fa-eye" id="toggleEditUserPwdIcon" onclick="toggleEditUserPassword()" style="position: absolute; right: 12px; cursor: pointer; color: #64748B; font-size: 1rem; padding: 4px;" title="Show/Hide Password"></i>
                    </div>
                </div>
            </div>
            
            <!-- Edit Student Course and School ID -->
            <div id="edit_student_fields" style="display: none;">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="input-label">School ID Number</label>
                    <input type="text" name="school_id" id="edit_school_id" class="form-control" placeholder="e.g. 2021-00123" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #E2E8F0;">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="input-label">Course / Program</label>
                    <select name="student_course" id="edit_student_course" class="form-control" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #E2E8F0;">
                        <option value="Computer Engineering">BS Computer Engineering (CpE)</option>
                        <option value="Information Technology">BS Information Technology (IT)</option>
                        <option value="Electrical Engineering">BS Electrical Engineering (EE)</option>
                    </select>
                </div>
            </div>

            <div id="edit_faculty_fields" style="display: none; background: #F8FAFC; border: 1.5px solid #CBD5E1; border-radius: 10px; padding: 1rem 1.15rem; margin-bottom: 1.15rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.4rem;">
                    <div style="font-size: 0.85rem; font-weight: 800; color: #065F46;">
                        <i class="fa-solid fa-microscope"></i> Faculty Information, Publications &amp; Research Specializations
                    </div>
                    <span style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; font-size: 0.68rem; font-weight: 700; padding: 2px 7px; border-radius: 999px;">
                        <i class="fa-solid fa-check-double"></i> Up-to-Date for AI Matching
                    </span>
                </div>

                <!-- Google Scholar Integration -->
                <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 0.7rem 0.85rem; margin-bottom: 0.85rem;">
                    <label class="input-label" style="color: #1E3A8A; font-size: 0.78rem; margin-bottom: 0.3rem; display: block;">
                        <i class="fa-solid fa-graduation-cap" style="color: #2563EB;"></i> Google Scholar Profile URL or ID <small style="font-weight: normal; color: #475569;">(Retrieves publications directly without needing CSV files)</small>
                    </label>
                    <div style="display: flex; gap: 0.45rem; flex-wrap: wrap;">
                        <input type="text" name="google_scholar_url" id="edit_google_scholar_url" class="form-control" placeholder="https://scholar.google.com/citations?user=..." style="flex: 1; min-width: 200px; padding: 0.55rem 0.75rem; border-radius: 7px; border: 1px solid #93C5FD; font-size: 0.82rem; background: #FFFFFF;">
                        <button type="button" onclick="syncGoogleScholarIntoForm('edit')" id="btn_sync_scholar_edit" style="background: #2563EB; color: #FFFFFF; border: none; border-radius: 7px; padding: 0.55rem 0.85rem; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap;">
                            <i class="fa-solid fa-rotate"></i> Sync from Google Scholar
                        </button>
                    </div>
                    <div id="scholar_sync_status_edit" style="font-size: 0.73rem; color: #1E40AF; margin-top: 0.35rem; display: none;"></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 0.85rem;">
                    <div>
                        <label class="input-label" style="font-size: 0.78rem;">Highest Degree</label>
                        <input type="text" name="academic_degree" id="edit_academic_degree" class="form-control" placeholder="e.g., MIT, MSIT, PhD" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.82rem;">
                    </div>
                    <div>
                        <label class="input-label" style="font-size: 0.78rem;">Graduation Year</label>
                        <input type="text" name="graduation_year" id="edit_graduation_year" class="form-control" placeholder="e.g., 2018" style="width: 100%; padding: 0.6rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.82rem;">
                    </div>
                    <div id="edit_hiredate_wrap">
                        <label class="input-label" style="font-size: 0.78rem;">Hire Date</label>
                        <input type="date" name="hire_date" id="edit_hire_date" class="form-control" style="width: 100%; padding: 0.58rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.82rem;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0.85rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem; flex-wrap: wrap; gap: 0.35rem;">
                        <label class="input-label" style="font-size: 0.8rem; margin-bottom: 0;">Research Specializations</label>
                        <button type="button" onclick="autoIdentifySpecializationsFromPubs('edit')" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 6px; padding: 2px 8px; font-size: 0.72rem; font-weight: 700; cursor: pointer;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Identify from Publications
                        </button>
                    </div>
                    <input type="text" name="specialization" id="edit_specialization" class="form-control" style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.84rem;">
                </div>

                <div class="form-group" style="margin-bottom: 0.85rem;">
                    <label class="input-label" style="font-size: 0.8rem; margin-bottom: 0.3rem; display: block;"><i class="fa-solid fa-book-journal-whills" style="color: #047857;"></i> Published Research Papers</label>
                    <textarea name="published_papers" id="edit_published_papers" rows="3" class="form-control" placeholder="List published research papers (one per line)..." style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.82rem; font-family: inherit; resize: vertical;"></textarea>
                </div>

                <div class="form-group" id="edit_prev_advised_wrap">
                    <label class="input-label" style="font-size: 0.8rem; margin-bottom: 0.3rem; display: block;"><i class="fa-solid fa-folder-tree" style="color: #D97706;"></i> Previous Research Titles Supervised / Advised</label>
                    <textarea name="previous_advised_titles" id="edit_previous_advised_titles" rows="3" class="form-control" placeholder="List previous capstone research titles supervised by this adviser (one per line)..." style="width: 100%; padding: 0.65rem 0.75rem; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 0.82rem; font-family: inherit; resize: vertical;"></textarea>
                </div>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%; padding: 0.85rem;"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
        </form>
    </div>
</div>

<style>
/* Tables Container & Cards */
.tables-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.colored-table-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.colored-header {
    background: linear-gradient(135deg, #064E3B 0%, #065F46 50%, #047857 100%) !important;
    padding: 0.95rem 1.35rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    border-bottom: 3px solid #F59E0B !important; /* CSU Gold accent line */
}

.colored-header h3 {
    color: #FFFFFF !important;
    margin: 0 !important;
    font-size: 0.95rem !important;
    font-weight: 800 !important;
    letter-spacing: 0.5px !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.65rem !important;
    text-transform: uppercase !important;
    line-height: 1.2 !important;
}

.colored-header h3 i {
    color: #FCD34D !important; /* CSU Gold icon */
    font-size: 1.05rem !important;
}

.table-count-badge {
    background: rgba(255, 255, 255, 0.16) !important;
    color: #FFFFFF !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    padding: 0.25rem 0.75rem !important;
    border-radius: 999px !important;
    letter-spacing: 0.2px !important;
}

.colored-table-body {
    padding: 0;
    overflow-x: auto;
}

.colored-table {
    width: 100%;
    border-collapse: collapse;
}

.colored-table th {
    background: #F8FAFC;
    color: #475569;
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 0.85rem 1.35rem;
    border-bottom: 1px solid #E2E8F0;
    text-align: left;
}

.colored-table td {
    padding: 0.95rem 1.35rem;
    border-bottom: 1px solid #F1F5F9;
    font-size: 0.88rem;
    color: #1E293B;
}

.colored-table tr:last-child td {
    border-bottom: none;
}

.colored-table tr:hover td {
    background: #F8FAFC;
}

/* Course / Department Navigation Tabs */
.dept-tabs {
    display: inline-flex;
    gap: 0.5rem;
    background: #F1F5F9;
    padding: 0.35rem;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.dept-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.65rem 1.15rem;
    border-radius: 9px;
    border: 1.5px solid transparent;
    background: #FFFFFF;
    color: #475569;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    font-family: inherit;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    user-select: none;
}
.dept-tab i {
    font-size: 0.95rem;
    color: #64748B;
    transition: all 0.2s ease;
}
.dept-tab:hover:not(.active) {
    background: #ECFDF5;
    color: #047857;
    border-color: #A7F3D0;
}
.dept-tab:hover:not(.active) i {
    color: #047857;
    transform: scale(1.1);
}
/* Selected / Active Course Tab: CSU CEIT Green */
.dept-tab.active {
    background: linear-gradient(135deg, #065F46 0%, #047857 100%) !important;
    color: #FFFFFF !important;
    border-color: #047857 !important;
    box-shadow: 0 4px 14px rgba(4, 120, 87, 0.35) !important;
    font-weight: 700 !important;
}
.dept-tab.active i {
    color: #FCD34D !important; /* CSU Gold icon */
}

.role-toggle-group {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.35rem;
}
.role-btn {
    flex: 1;
    padding: 0.65rem 0.75rem;
    border: 1px solid #CBD5E1;
    background: #F8FAFC;
    color: #475569;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}
.role-btn:hover {
    background: #F1F5F9;
    border-color: #94A3B8;
}
.role-btn.active-faculty {
    background: #047857 !important;
    color: #FFFFFF !important;
    border-color: #047857 !important;
    box-shadow: 0 2px 6px rgba(4, 120, 87, 0.25);
}
.role-btn.active-student {
    background: #065F46 !important;
    color: #FFFFFF !important;
    border-color: #065F46 !important;
    box-shadow: 0 2px 6px rgba(6, 95, 70, 0.25);
}

/* 3 Courses Card Styles */
.course-options-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.65rem;
    margin-top: 0.35rem;
}
.course-card {
    border: 2px solid #E2E8F0;
    background: #FFFFFF;
    border-radius: 10px;
    padding: 0.85rem 0.6rem;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.45rem;
    position: relative;
    transition: all 0.2s ease;
    user-select: none;
}
.course-card:hover {
    border-color: #A7F3D0;
    background: #F0FDF4;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.course-card.active-course-card {
    border-color: #047857 !important;
    background: #ECFDF5 !important;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.2);
}
.course-card input[type="radio"] {
    display: none;
}
.course-card .course-icon {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
}
.course-card .course-info {
    display: flex;
    flex-direction: column;
}
.course-card .course-code {
    font-weight: 700;
    font-size: 0.88rem;
    color: #1E293B;
}
.course-card .course-title {
    font-size: 0.72rem;
    color: #64748B;
    line-height: 1.25;
    margin-top: 2px;
}
.course-card .course-check {
    position: absolute;
    top: 7px;
    right: 7px;
    font-size: 0.85rem;
    color: #047857;
    display: none;
}
.course-card.active-course-card .course-check {
    display: block;
}
@media (max-width: 540px) {
    .course-options-grid {
        grid-template-columns: 1fr;
    }
    .course-card {
        flex-direction: row;
        text-align: left;
        padding: 0.65rem 0.85rem;
        gap: 0.8rem;
    }
}
</style>

<script>
function toggleAddUserPassword() {
    const pwd = document.getElementById('add_user_password');
    const icon = document.getElementById('toggleAddUserPwdIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function toggleEditUserPassword() {
    const pwd = document.getElementById('edit_user_password');
    const icon = document.getElementById('toggleEditUserPwdIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        pwd.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function openAddUserModal() {
    const role = document.getElementById('form_role').value || 'adviser';
    selectRole(role);
    document.getElementById('addUserModal').style.display = 'block';
}

function selectCourse(course) {
    document.getElementById('form_department').value = course;
    
    document.querySelectorAll('.course-card').forEach(c => c.classList.remove('active-course-card'));
    
    if (course === 'Computer Engineering') {
        document.getElementById('card_comeng').classList.add('active-course-card');
        document.getElementById('course_comeng').checked = true;
    } else if (course === 'Information Technology') {
        document.getElementById('card_it').classList.add('active-course-card');
        document.getElementById('course_it').checked = true;
    } else if (course === 'Electrical Engineering') {
        document.getElementById('card_ee').classList.add('active-course-card');
        document.getElementById('course_ee').checked = true;
    }
}

function autoGenerateAddUserEmail() {
    const fElem = document.getElementById('add_firstname');
    const lElem = document.getElementById('add_lastname');
    const emailElem = document.getElementById('add_email');
    if (!fElem || !lElem || !emailElem) return;
    const fname = fElem.value.toLowerCase().trim().replace(/\s+/g, '').replace(/[^a-z0-9]/g, '');
    const lname = lElem.value.toLowerCase().trim().replace(/\s+/g, '').replace(/[^a-z0-9]/g, '');
    if (fname && lname) {
        emailElem.value = `${fname}.${lname}@csucc.edu.ph`;
    }
}

function selectRole(role) {
    document.getElementById('form_role').value = role;
    const btnFac = document.getElementById('btn-faculty');
    const btnPan = document.getElementById('btn-panel');
    const btnStu = document.getElementById('btn-student');
    const specGrp = document.getElementById('specialization_group');
    const hireGrp = document.getElementById('hiredate_group');
    const courseGrp = document.getElementById('course_group');
    const studentIdGrp = document.getElementById('student_id_group');
    const prevAdvWrap = document.getElementById('add_prev_advised_wrap');

    // Reset styles
    btnFac.classList.remove('active-faculty');
    btnPan.classList.remove('active-faculty');
    btnStu.classList.remove('active-student');

    if (role === 'adviser') {
        btnFac.classList.add('active-faculty');
        specGrp.style.display = 'block';
        if (hireGrp) hireGrp.style.display = 'block';
        if (prevAdvWrap) prevAdvWrap.style.display = 'block';
        if (studentIdGrp) studentIdGrp.style.display = 'none';
        if (courseGrp) {
            courseGrp.style.display = 'block';
            document.getElementById('course_role_label').textContent = 'Adviser';
            const curDept = document.getElementById('form_department').value || 'Computer Engineering';
            selectCourse(curDept);
        }
    } else if (role === 'panel') {
        btnPan.classList.add('active-faculty');
        specGrp.style.display = 'block';
        if (hireGrp) hireGrp.style.display = 'none';
        if (prevAdvWrap) prevAdvWrap.style.display = 'block';
        if (courseGrp) courseGrp.style.display = 'none';
        if (studentIdGrp) studentIdGrp.style.display = 'none';
    } else {
        btnStu.classList.add('active-student');
        specGrp.style.display = 'none';
        if (hireGrp) hireGrp.style.display = 'none';
        if (studentIdGrp) studentIdGrp.style.display = 'block';
        if (courseGrp) {
            courseGrp.style.display = 'block';
            document.getElementById('course_role_label').textContent = 'Student';
            const curDept = document.getElementById('form_department').value || 'Computer Engineering';
            selectCourse(curDept);
        }
    }
}

function openEditUserFromDataset(btn) {
    if (!btn) return;
    const d = btn.dataset;
    openEditModal(
        d.id || '',
        d.fname || '',
        d.lname || '',
        d.email || '',
        d.role || 'adviser',
        d.spec || '',
        d.hire || '',
        '',
        '',
        d.degree || '',
        d.gradyear || '',
        d.scholar || '',
        d.pubs || '',
        d.prevtitles || ''
    );
}

function openEditModal(id, fname, lname, email, role, spec, hireDate, dept, schoolId, degree = '', gradYear = '', scholarUrl = '', pubs = '', prevTitles = '') {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_firstname').value = fname;
    document.getElementById('edit_lastname').value = lname;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_department').value = dept || '<?= htmlspecialchars($dept, ENT_QUOTES) ?>';
    document.getElementById('edit_user_password').value = '';
    const stEdit = document.getElementById('scholar_sync_status_edit');
    if (stEdit) stEdit.style.display = 'none';
    
    if(role === 'adviser' || role === 'panel') {
        document.getElementById('edit_faculty_fields').style.display = 'block';
        document.getElementById('edit_student_fields').style.display = 'none';
        document.getElementById('edit_specialization').value = spec || '';
        document.getElementById('edit_hire_date').value = hireDate || '';
        document.getElementById('edit_academic_degree').value = degree || '';
        document.getElementById('edit_graduation_year').value = gradYear || '';
        document.getElementById('edit_google_scholar_url').value = scholarUrl || '';
        document.getElementById('edit_published_papers').value = pubs || '';
        document.getElementById('edit_previous_advised_titles').value = prevTitles || '';
        const hdWrap = document.getElementById('edit_hiredate_wrap');
        if (hdWrap) hdWrap.style.display = (role === 'adviser') ? 'block' : 'none';
    } else if(role === 'student') {
        document.getElementById('edit_faculty_fields').style.display = 'none';
        document.getElementById('edit_student_fields').style.display = 'block';
        if (dept) {
            document.getElementById('edit_student_course').value = dept;
        }
        document.getElementById('edit_school_id').value = schoolId || '';
    } else {
        document.getElementById('edit_faculty_fields').style.display = 'none';
        document.getElementById('edit_student_fields').style.display = 'none';
    }
    
    document.getElementById('editUserModal').style.display = 'block';
}

function syncGoogleScholarIntoForm(mode) {
    const isEdit = (mode === 'edit');
    const urlInput = document.getElementById(isEdit ? 'edit_google_scholar_url' : 'form_google_scholar_url');
    const fnameInput = document.getElementById(isEdit ? 'edit_firstname' : 'add_firstname');
    const lnameInput = document.getElementById(isEdit ? 'edit_lastname' : 'add_lastname');
    const specInput = document.getElementById(isEdit ? 'edit_specialization' : 'form_specialization');
    const pubsInput = document.getElementById(isEdit ? 'edit_published_papers' : 'form_published_papers');
    const deptInput = document.getElementById(isEdit ? 'edit_department' : 'form_department');
    const btn = document.getElementById(isEdit ? 'btn_sync_scholar_edit' : 'btn_sync_scholar_add');
    const statusEl = document.getElementById(isEdit ? 'scholar_sync_status_edit' : 'scholar_sync_status_add');

    const fullName = ((fnameInput ? fnameInput.value.trim() : '') + ' ' + (lnameInput ? lnameInput.value.trim() : '')).trim();
    const scholarVal = urlInput ? urlInput.value.trim() : '';

    if (!scholarVal && !fullName) {
        alert('Please enter the faculty member\'s Google Scholar Profile URL/ID or First & Last Name first.');
        if (urlInput) urlInput.focus();
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Retrieving from Google Scholar...';
    }
    if (statusEl) {
        statusEl.style.display = 'block';
        statusEl.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Connecting to Google Scholar to retrieve publications & identify research specializations...';
    }

    const fd = new FormData();
    fd.append('action', 'sync_google_scholar');
    fd.append('google_scholar_url', scholarVal);
    fd.append('faculty_name', fullName);
    fd.append('department', deptInput ? deptInput.value : '');
    fd.append('existing_expertise', specInput ? specInput.value : '');

    fetch('api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i> Retrieve from Google Scholar';
            }
            if (data && data.ok) {
                if (pubsInput && data.formatted_publications) {
                    pubsInput.value = data.formatted_publications;
                }
                if (specInput && data.merged_specializations) {
                    specInput.value = data.merged_specializations;
                }
                if (urlInput && !urlInput.value.trim() && data.google_scholar_url) {
                    urlInput.value = data.google_scholar_url;
                }
                if (statusEl) {
                    const count = Array.isArray(data.publications) ? data.publications.length : 0;
                    const idTags = Array.isArray(data.identified_specializations) && data.identified_specializations.length > 0
                        ? data.identified_specializations.join(', ')
                        : 'Merged with specialization profile';
                    statusEl.style.color = '#065F46';
                    statusEl.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#059669;"></i> Retrieved <strong>${count} publication(s)</strong> via ${data.source || 'Google Scholar'} &amp; identified specializations: <strong>${idTags}</strong>`;
                }
            } else {
                if (statusEl) {
                    statusEl.style.color = '#DC2626';
                    statusEl.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> ${data.error || 'Unable to retrieve publications.'}`;
                }
            }
        })
        .catch(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i> Retrieve from Google Scholar';
            }
            if (statusEl) {
                statusEl.style.color = '#DC2626';
                statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Network error while syncing Google Scholar.';
            }
        });
}

function autoIdentifySpecializationsFromPubs(mode) {
    const isEdit = (mode === 'edit');
    const specInput = document.getElementById(isEdit ? 'edit_specialization' : 'form_specialization');
    const pubsInput = document.getElementById(isEdit ? 'edit_published_papers' : 'form_published_papers');
    const prevInput = document.getElementById(isEdit ? 'edit_previous_advised_titles' : 'form_previous_advised_titles');
    const statusEl = document.getElementById(isEdit ? 'scholar_sync_status_edit' : 'scholar_sync_status_add');

    const pubs = pubsInput ? pubsInput.value.trim() : '';
    const prev = prevInput ? prevInput.value.trim() : '';
    if (!pubs && !prev) {
        alert('Please enter or sync Published Research Papers or Previously Supervised Research Titles first so the system can identify specializations.');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'extract_specializations');
    fd.append('published_papers', pubs);
    fd.append('previous_advised_titles', prev);
    fd.append('existing_expertise', specInput ? specInput.value : '');

    fetch('api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.ok) {
                if (specInput && data.merged_expertise) {
                    specInput.value = data.merged_expertise;
                }
                if (statusEl) {
                    statusEl.style.display = 'block';
                    statusEl.style.color = '#065F46';
                    const tags = Array.isArray(data.identified_tags) && data.identified_tags.length > 0
                        ? data.identified_tags.join(', ')
                        : 'Updated specialization tags';
                    statusEl.innerHTML = `<i class="fa-solid fa-wand-magic-sparkles" style="color:#059669;"></i> Identified specializations from publications &amp; supervised titles: <strong>${tags}</strong>`;
                }
            }
        });
}

let targetArchiveUserId = null;

function archiveUser(id, name, role) {
    targetArchiveUserId = id;
    const userName = name || 'Selected User';
    const userRole = role || 'CEIT Academic Member';
    const initial = userName.trim().charAt(0).toUpperCase() || 'U';

    document.getElementById('archive_user_name').textContent = userName;
    document.getElementById('archive_user_avatar').textContent = initial;
    document.getElementById('archive_user_role').textContent = userRole;

    document.getElementById('archiveModal').style.display = 'block';
}

function closeArchiveModal() {
    document.getElementById('archiveModal').style.display = 'none';
    targetArchiveUserId = null;
}

function confirmArchiveUser() {
    if (targetArchiveUserId) {
        window.location.href = 'action.php?archive_user=' + targetArchiveUserId + '&dept=<?= urlencode($dept) ?>';
    }
}

// Close archive modal when clicking backdrop
window.addEventListener('click', function(e) {
    const m = document.getElementById('archiveModal');
    if (e.target === m) closeArchiveModal();
});

// Table Pagination Controller
const tablePaginationState = {};

function initTablePagination(cardSelector, defaultPageSize) {
    const card = document.querySelector(cardSelector);
    if (!card) return;
    const table = card.querySelector('.colored-table');
    if (!table) return;
    
    // Get all rows that are data rows (exclude header with th)
    const allRows = Array.from(table.querySelectorAll('tr')).filter(r => r.querySelectorAll('th').length === 0);
    const totalRows = allRows.length;
    if (totalRows === 0) return;
    if (totalRows === 1 && allRows[0].querySelector('td[colspan]')) return;

    let footer = card.querySelector('.table-pagination-footer');
    if (!footer) {
        footer = document.createElement('div');
        footer.className = 'table-pagination-footer';
        card.appendChild(footer);
    }

    const stateKey = cardSelector;
    tablePaginationState[stateKey] = {
        cardSelector: cardSelector,
        allRows: allRows,
        totalRows: totalRows,
        pageSize: defaultPageSize,
        currentPage: 1
    };

    function render() {
        const state = tablePaginationState[stateKey];
        const { pageSize, totalRows, allRows } = state;
        let currentPage = state.currentPage;
        const totalPages = Math.max(1, Math.ceil(totalRows / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;
        state.currentPage = currentPage;

        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, totalRows);

        allRows.forEach((row, index) => {
            if (index >= start && index < end) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        let pageButtonsHtml = '';
        const startBtn = Math.max(1, currentPage - 1);
        const endBtn = Math.min(totalPages, currentPage + 1);
        for (let i = startBtn; i <= endBtn; i++) {
            pageButtonsHtml += `
                <button type="button" 
                        class="table-pagination-btn ${i === currentPage ? 'active' : ''}" 
                        onclick="goToTablePage('${cardSelector}', ${i})">${i}</button>
            `;
        }

        footer.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                <div>Showing <strong>${totalRows === 0 ? 0 : start + 1}</strong> to <strong>${end}</strong> of <strong>${totalRows}</strong> records</div>
            </div>
            <div style="display: flex; gap: 0.3rem; align-items: center;">
                <button type="button" 
                        class="table-pagination-btn" 
                        ${currentPage <= 1 ? 'disabled' : ''} 
                        onclick="goToTablePage('${cardSelector}', ${currentPage - 1})"
                        title="Previous Page">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                ${pageButtonsHtml}
                <button type="button" 
                        class="table-pagination-btn" 
                        ${currentPage >= totalPages ? 'disabled' : ''} 
                        onclick="goToTablePage('${cardSelector}', ${currentPage + 1})"
                        title="Next Page">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        `;
    }

    tablePaginationState[stateKey].render = render;
    render();
}

function goToTablePage(cardSelector, page) {
    if (tablePaginationState[cardSelector]) {
        tablePaginationState[cardSelector].currentPage = page;
        tablePaginationState[cardSelector].render();
    }
}

function setTablePageSize(cardSelector, newSize) {
    if (tablePaginationState[cardSelector]) {
        tablePaginationState[cardSelector].pageSize = parseInt(newSize, 10);
        tablePaginationState[cardSelector].currentPage = 1;
        tablePaginationState[cardSelector].render();
    }
}

// Auto-initialize pagination on load with interactive default sizes
document.addEventListener('DOMContentLoaded', function() {
    initTablePagination('.colored-table-card.faculty', 5);
    initTablePagination('.colored-table-card.student', 5);
    initTablePagination('.colored-table-card.panel', 5);
    initTablePagination('.colored-table-card.secretary', 5);
});
</script>

<!-- Global University Archive Confirmation Modal -->
<div id="archiveModal" class="modal">
    <div class="modal-content logout-modal-content" style="max-width: 460px;">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%); border-bottom: 3px solid #F59E0B;">
            <div class="logout-seal-wrapper">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle">Caraga State University</div>
            <div class="logout-college-subtitle">College of Engineering and Information Technology</div>
            <h2>Archive User Record</h2>
        </div>
        <div class="logout-modal-body">
            <div class="logout-user-card">
                <div class="logout-user-avatar" id="archive_user_avatar">
                    U
                </div>
                <div class="logout-user-details">
                    <div class="logout-user-name" id="archive_user_name">User Name</div>
                    <div class="logout-user-role">
                        <span class="logout-session-dot" style="background: #F59E0B; box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25);"></span>
                        <span id="archive_user_role">Faculty Member</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title">Move Account to Academic Archive?</div>
            <p class="logout-prompt-desc">
                This account will be deactivated from active rosters. All research data, evaluations, and capstone credentials will be safely preserved in the <strong>Archived Records</strong> and can be restored at any time by the administrator.
            </p>

            <div class="logout-actions">
                <button type="button" class="btn-logout-cancel" onclick="closeArchiveModal()">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </button>
                <button type="button" class="btn-archive-confirm" onclick="confirmArchiveUser()" style="flex: 1.15; padding: 0.65rem 1.1rem; border-radius: 10px; background: linear-gradient(135deg, #EA580C 0%, #C2410C 100%); color: white; border: none; font-weight: 700; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-box-archive"></i> Archive Record
                </button>
            </div>

            <div class="logout-modal-footer-note">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>
