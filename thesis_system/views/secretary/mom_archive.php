<?php
// SECRETARY - OFFICIAL MOM REGISTRY / ARCHIVE VIEW
// Displays all filed defense meeting minutes with verdict badges, grade, date, and print/view actions

$current_uid = (int)$_SESSION['user_id'];
$current_name = $_SESSION['name'] ?? '';

// Fetch all MOM records with related data
$moms_query = $conn->query("
    SELECT dmm.*, 
           dm.title as defense_title, dm.meeting_date, dm.start_time, dm.end_time,
           dm.venue, dm.defense_stage, dm.academic_year, dm.course_code, dm.defense_category,
           dm.panel_chair_id, dm.panel_members, dm.adviser_name, dm.adviser_id,
           dm.mom_documenter_id, dm.mom_documenter_name,
           u.name as student_name, u.email as student_email,
           sp.department as student_dept, sp.thesis_topic, sp.student_id as student_no,
           cg.group_name,
           chair.name as panel_chair_name,
           adv_u.name as adviser_user_name,
           (SELECT COUNT(*) FROM defense_transcripts dt WHERE dt.meeting_id = dm.id) as transcript_count
    FROM defense_meeting_minutes dmm
    JOIN defense_meetings dm ON dmm.meeting_id = dm.id
    JOIN user u ON dmm.student_id = u.id
    LEFT JOIN student_profile sp ON u.id = sp.user_id
    LEFT JOIN capstone_group cg ON dmm.group_id = cg.id
    LEFT JOIN user chair ON dm.panel_chair_id = chair.id
    LEFT JOIN user adv_u ON dm.adviser_id = adv_u.id
    ORDER BY dmm.updated_at DESC, dmm.created_at DESC
");

$all_moms = [];
$finalized_count = 0;
$draft_count = 0;
$total_count = 0;
$archived_count = 0;
$is_archive_view = (isset($_GET['tab']) && $_GET['tab'] === 'archive') || (isset($_GET['view']) && $_GET['view'] === 'archived');

if ($moms_query) {
    while ($row = $moms_query->fetch_assoc()) {
        $all_moms[] = $row;
        if (!empty($row['is_archived'])) {
            $archived_count++;
        } else {
            $total_count++;
            if ($row['status'] === 'finalized' || $row['status'] === 'signed_off') $finalized_count++;
            else $draft_count++;
        }
    }
}

// Verdict display mapping
function getVerdictStyle($verdict) {
    $styles = [
        'Approved without Revisions' => ['bg' => '#D1FAE5', 'color' => '#065F46', 'icon' => 'fa-circle-check', 'border' => '#A7F3D0'],
        'Approved with Minor Revisions' => ['bg' => '#ECFDF5', 'color' => '#047857', 'icon' => 'fa-check-double', 'border' => '#BBF7D0'],
        'Approved with Major Revisions' => ['bg' => '#FEF3C7', 'color' => '#92400E', 'icon' => 'fa-pen-to-square', 'border' => '#FDE68A'],
        'Re-defense Required' => ['bg' => '#FEE2E2', 'color' => '#991B1B', 'icon' => 'fa-rotate-right', 'border' => '#FECACA'],
        'Disapproved' => ['bg' => '#FEE2E2', 'color' => '#991B1B', 'icon' => 'fa-circle-xmark', 'border' => '#FECACA'],
        'Deferred' => ['bg' => '#F1F5F9', 'color' => '#475569', 'icon' => 'fa-clock', 'border' => '#E2E8F0'],
    ];
    return $styles[$verdict] ?? ['bg' => '#F1F5F9', 'color' => '#475569', 'icon' => 'fa-question', 'border' => '#E2E8F0'];
}

function getMomStatusStyle($status) {
    $styles = [
        'draft' => ['bg' => '#FEF3C7', 'color' => '#92400E', 'label' => 'Draft'],
        'under_review' => ['bg' => '#DBEAFE', 'color' => '#1E40AF', 'label' => 'Under Review'],
        'finalized' => ['bg' => '#D1FAE5', 'color' => '#065F46', 'label' => 'Finalized'],
        'signed_off' => ['bg' => '#ECFDF5', 'color' => '#047857', 'label' => 'Signed Off'],
    ];
    return $styles[$status] ?? ['bg' => '#F1F5F9', 'color' => '#475569', 'label' => ucfirst($status)];
}

// Compile complete MOM data dictionary for view-only modal and printing
$moms_data_json = [];
foreach ($all_moms as $m) {
    $is_prop = in_array($m['defense_stage'] ?? '', ['Title Proposal', 'Outline Defense', 'Pre-Oral Defense']);
    $course_code = !empty($m['course_code']) ? $m['course_code'] : ($is_prop ? 'IT 198' : 'IT 199');
    $vs = getVerdictStyle($m['decision_verdict'] ?? '');
    $ms = getMomStatusStyle($m['status'] ?? 'draft');

    $p_members = $m['panel_members'];
    if (empty($p_members) && !empty($m['panel_chair_name'])) {
        $p_members = $m['panel_chair_name'];
    }

    $moms_data_json[$m['id']] = [
        'id' => (int)$m['id'],
        'meeting_id' => (int)$m['meeting_id'],
        'student_id' => (int)$m['student_id'],
        'student_name' => $m['student_name'] ?? 'Candidate',
        'group_name' => $m['group_name'] ?? '',
        'group_display' => ($m['student_name'] ?? 'Candidate') . (!empty($m['group_name']) ? ' (' . $m['group_name'] . ')' : ''),
        'thesis_topic' => $m['thesis_topic'] ?: ($m['defense_title'] ?? 'Capstone Research'),
        'document_title' => $m['document_title'] ?: ($m['defense_title'] ?: 'Minutes of Oral Defense Proceedings'),
        'defense_stage' => $m['defense_stage'] ?: 'Final Oral Defense',
        'course_code' => $course_code,
        'meeting_date' => !empty($m['meeting_date']) ? date("F d, Y", strtotime($m['meeting_date'])) : 'N/A',
        'venue' => $m['venue'] ?: 'CEIT Defense Hall',
        'panel_members' => $p_members ?: 'Panel Examination Committee',
        'adviser_name' => $m['adviser_name'] ?: ($m['adviser_user_name'] ?: 'Engr. Assigned Adviser'),
        'panel_chair_name' => $m['panel_chair_name'] ?: 'Panel Committee Chair',
        'decision_verdict' => $m['decision_verdict'] ?: 'Deliberation Pending',
        'verdict_style' => $vs,
        'status_style' => $ms,
        'grade_average' => !empty($m['grade_average']) ? number_format((float)$m['grade_average'], 2) : '',
        'status' => $m['status'] ?: 'draft',
        'is_archived' => !empty($m['is_archived']) ? 1 : 0,
        'archived_at' => !empty($m['archived_at']) ? date('M d, Y g:i A', strtotime($m['archived_at'])) : null,
        'discussion_summary' => $m['discussion_summary'] ?: '',
        'recommendations' => $m['recommendations'] ?: '',
        'notes' => $m['notes'] ?: '',
        'action_items' => $m['action_items'] ?: '[]',
        'recorded_by_name' => $m['recorded_by_name'] ?: ($m['mom_documenter_name'] ?: 'Secretariat Documenter'),
        'approved_by_chair' => $m['approved_by_chair'] ?: ($m['panel_chair_name'] ?: 'Panel Committee Chair'),
        'created_at' => date('F d, Y g:i A', strtotime($m['created_at'])),
        'updated_at' => date('F d, Y g:i A', strtotime($m['updated_at'])),
    ];
}
?>

<style>
/* MOM Archive Styles */
.mom-archive-banner {
    background: linear-gradient(135deg, #065F46 0%, #047857 40%, #059669 100%);
    border-radius: 16px;
    padding: 2rem 2.5rem;
    margin: 1.5rem 2rem 1.25rem;
    color: white;
    position: relative;
    overflow: hidden;
}
.mom-archive-banner::before {
    content: '';
    position: absolute;
    top: -30%;
    right: -8%;
    width: 250px;
    height: 250px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
}
.mom-archive-banner::after {
    content: '';
    position: absolute;
    bottom: -40%;
    left: 5%;
    width: 180px;
    height: 180px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.mom-archive-banner h1 {
    font-size: 1.6rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.35rem;
}
.mom-archive-banner p {
    font-size: 0.9rem;
    opacity: 0.85;
    margin: 0;
}

.mom-archive-stats {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 1rem;
    margin: 0 2rem 1.25rem;
}
.mom-archive-stat {
    background: white;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 1.15rem 1.25rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.mom-archive-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.07);
    border-color: #CBD5E1;
}
.mom-archive-stat.active {
    border-color: #047857;
    background: #FFFFFF;
    box-shadow: 0 0 0 2px rgba(4,120,87,0.2), 0 4px 12px rgba(4,120,87,0.08);
}
.mom-archive-stat .stat-num {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0F172A;
    line-height: 1;
    margin-bottom: 0.35rem;
}
.mom-archive-stat .stat-lbl {
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

/* Search */
.mom-search-bar {
    margin: 0 2rem 1rem;
    position: relative;
}
.mom-search-bar input {
    width: 100%;
    padding: 0.7rem 1rem 0.7rem 2.5rem;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}
.mom-search-bar input:focus {
    outline: none;
    border-color: #047857;
    box-shadow: 0 0 0 3px rgba(4,120,87,0.1);
}
.mom-search-bar i {
    position: absolute;
    left: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94A3B8;
}

/* MOM Cards */
.mom-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin: 0 2rem 2rem;
}
.mom-record-card {
    background: white;
    border-radius: 14px;
    border: 1px solid #E2E8F0;
    overflow: hidden;
    transition: all 0.2s ease;
}
.mom-record-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.07);
}

.mom-card-top {
    display: flex;
    gap: 0;
}
.mom-verdict-strip {
    width: 8px;
    min-height: 100%;
}
.mom-card-body {
    flex: 1;
    padding: 1.25rem 1.5rem;
}
.mom-card-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}
.mom-doc-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1E293B;
    margin: 0 0 0.3rem;
}
.mom-badges-row {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
    align-items: center;
}
.mom-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.mom-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.5rem 1.5rem;
    font-size: 0.82rem;
    color: #475569;
    margin-bottom: 0.75rem;
}
.mom-meta-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.mom-meta-item i {
    color: #94A3B8;
    font-size: 0.75rem;
    width: 14px;
    text-align: center;
}
.mom-meta-item strong {
    color: #1E293B;
}

/* Summary / Recommendations truncated display */
.mom-summary-preview {
    background: #F8FAFC;
    border-radius: 8px;
    padding: 0.65rem 1rem;
    font-size: 0.82rem;
    color: #475569;
    line-height: 1.5;
    margin-bottom: 0.75rem;
    max-height: 60px;
    overflow: hidden;
    position: relative;
}
.mom-summary-preview::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 25px;
    background: linear-gradient(transparent, #F8FAFC);
}

/* Footer Actions */
.mom-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 1.5rem;
    background: #FAFBFC;
    border-top: 1px solid #F1F5F9;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.mom-footer-meta {
    font-size: 0.78rem;
    color: #64748B;
    display: flex;
    gap: 0.75rem;
    align-items: center;
}
.mom-footer-actions {
    display: flex;
    gap: 0.5rem;
}
.mom-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 700;
    border: 1px solid #E2E8F0;
    background: white;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
    text-decoration: none;
}
.mom-action-btn:hover {
    background: #F8FAFC;
    transform: translateY(-1px);
}
.mom-action-btn.btn-print {
    background: #047857;
    color: white;
    border-color: #047857;
}
.mom-action-btn.btn-print:hover {
    background: #065F46;
}
.mom-action-btn.btn-edit, .mom-action-btn.btn-view {
    background: #ECFDF5;
    color: #047857;
    border-color: #A7F3D0;
}
.mom-action-btn.btn-view:hover {
    background: #D1FAE5;
    color: #065F46;
}
.mom-action-btn.btn-archive {
    background: #FFFBEB;
    color: #B45309;
    border-color: #FDE68A;
}
.mom-action-btn.btn-archive:hover {
    background: #F59E0B;
    color: #FFFFFF;
    border-color: #F59E0B;
    transform: translateY(-1px);
}
.mom-action-btn.btn-delete {
    background: #FEF2F2;
    color: #DC2626;
    border-color: #FECACA;
}
.mom-action-btn.btn-delete:hover {
    background: #DC2626;
    color: #FFFFFF;
    border-color: #DC2626;
    transform: translateY(-1px);
}
.mom-action-btn.btn-restore {
    background: #ECFDF5;
    color: #047857;
    border-color: #A7F3D0;
}
.mom-action-btn.btn-restore:hover {
    background: #047857;
    color: #FFFFFF;
    border-color: #047857;
    transform: translateY(-1px);
}
.mom-action-btn.btn-purge {
    background: #FEF2F2;
    color: #991B1B;
    border-color: #FECACA;
}
.mom-action-btn.btn-purge:hover {
    background: #991B1B;
    color: #FFFFFF;
    border-color: #991B1B;
    transform: translateY(-1px);
}

/* Segmented View Switch Tabs */
.mom-view-switch-tabs {
    display: flex;
    gap: 0.5rem;
    margin: 0 2rem 1.25rem;
    border-bottom: 2px solid #E2E8F0;
    padding-bottom: 0.5rem;
}
.view-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-size: 0.88rem;
    font-weight: 700;
    border: none;
    background: transparent;
    color: #64748B;
    cursor: pointer;
    transition: all 0.2s ease;
}
.view-tab-btn:hover {
    background: #F1F5F9;
    color: #1E293B;
}
.view-tab-btn.active {
    background: #047857;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(4, 120, 87, 0.25);
}
.view-tab-btn.active .tab-badge-pill {
    background: rgba(255, 255, 255, 0.25);
    color: #FFFFFF;
}
.tab-badge-pill {
    background: #E2E8F0;
    color: #475569;
    padding: 2px 7px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
}

.archived-info-banner {
    background: #FFFBEB;
    border: 1px solid #FDE68A;
    border-left: 4px solid #F59E0B;
    padding: 0.85rem 1.25rem;
    border-radius: 10px;
    margin: 0 2rem 1.25rem;
    color: #92400E;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}

.mom-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #94A3B8;
}
.mom-empty-state i {
    font-size: 3rem;
    color: #CBD5E1;
    display: block;
    margin-bottom: 1rem;
}
.mom-empty-state h3 {
    color: #475569;
    margin-bottom: 0.5rem;
}

/* View MOM Modal Styles */
.view-mom-modal-content {
    max-width: 880px !important;
    width: 94% !important;
    margin: 2rem auto !important;
    padding: 0 !important;
    border-radius: 16px !important;
    overflow: hidden;
    background: #FFFFFF !important;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.45) !important;
    border: 1px solid #CBD5E1 !important;
}

.view-mom-modal-header {
    background: #F8FAFC;
    border-bottom: 1px solid #E2E8F0;
    padding: 0.85rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 10;
}

.view-mom-sheet {
    max-height: calc(88vh - 120px);
    overflow-y: auto;
    padding: 2.25rem 2.75rem;
    background: #FFFFFF;
    color: #1E293B;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* CSU Official Letterhead in Modal */
.view-mom-letterhead {
    text-align: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid #047857;
}

.view-mom-letterhead .univ-rep {
    font-size: 0.8rem;
    color: #64748B;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.view-mom-letterhead .univ-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0F172A;
    margin: 0.15rem 0;
    letter-spacing: 0.3px;
}

.view-mom-letterhead .college-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #047857;
}

.view-mom-letterhead .campus-loc {
    font-size: 0.75rem;
    color: #64748B;
    margin-top: 0.1rem;
}

.view-mom-letterhead .doc-heading {
    margin-top: 1rem;
    font-size: 1.05rem;
    font-weight: 800;
    color: #064E3B;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* View Sections */
.view-mom-section {
    margin-bottom: 1.5rem;
}

.view-mom-section-title {
    font-size: 0.92rem;
    font-weight: 800;
    color: #0F172A;
    margin-bottom: 0.6rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 0.35rem;
    border-bottom: 1px solid #E2E8F0;
}

.view-mom-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 0.75rem 1.25rem;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.25rem;
}

.view-mom-grid-item .label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748B;
    letter-spacing: 0.3px;
    margin-bottom: 0.2rem;
}

.view-mom-grid-item .value {
    font-size: 0.88rem;
    font-weight: 600;
    color: #0F172A;
}

.view-mom-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    margin-top: 0.5rem;
}

.view-mom-table th {
    background: #F1F5F9;
    color: #334155;
    font-weight: 700;
    padding: 0.6rem 0.85rem;
    text-align: left;
    border-bottom: 1px solid #CBD5E1;
}

.view-mom-table td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid #E2E8F0;
    color: #1E293B;
}

.view-mom-modal-footer {
    background: #F8FAFC;
    border-top: 1px solid #E2E8F0;
    padding: 0.85rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    bottom: 0;
    z-index: 10;
}

/* Print Styles for Official MOM */
@media print {
    body * {
        visibility: hidden !important;
    }
    #viewMomModal,
    #viewMomModal .view-mom-modal-content,
    #viewMomModal .view-mom-sheet,
    #viewMomModal .view-mom-sheet * {
        visibility: visible !important;
    }
    #viewMomModal {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        overflow: visible !important;
        box-shadow: none !important;
        border: none !important;
        backdrop-filter: none !important;
    }
    #viewMomModal .view-mom-modal-content {
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
    }
    #viewMomModal .view-mom-sheet {
        max-height: none !important;
        overflow: visible !important;
        padding: 0.4in 0.4in !important;
    }
    .view-mom-modal-header,
    .view-mom-modal-footer,
    .mom-archive-banner, .mom-archive-stats, .mom-search-bar,
    .sidebar, .topbar, .no-print, .mom-card-footer {
        display: none !important;
    }
    .print-signature-section {
        display: flex !important;
        page-break-inside: avoid;
    }
}

@media (max-width: 992px) {
    .mom-archive-stats { grid-template-columns: repeat(2, 1fr) !important; }
}
@media (max-width: 576px) {
    .mom-archive-stats { grid-template-columns: 1fr !important; }
}
@media (max-width: 768px) {
    .mom-archive-banner { margin: 1rem; padding: 1.5rem; }
    .mom-grid { margin: 0 1rem 1.5rem; }
    .mom-meta-grid { grid-template-columns: 1fr; }
    .view-mom-sheet { padding: 1.5rem; }
}

/* University Modals - Compact & Refined Sizing */
.univ-modal-compact {
    max-width: 390px !important;
    border-radius: 16px !important;
    margin: 8vh auto !important;
    box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(0, 0, 0, 0.05) !important;
}

.univ-modal-compact .logout-modal-header {
    padding: 1.1rem 1.25rem 0.9rem 1.25rem !important;
}

.univ-modal-compact .logout-seal-wrapper {
    width: 46px !important;
    height: 46px !important;
    padding: 3px !important;
    margin: 0 auto 0.4rem auto !important;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2), 0 0 0 2px rgba(255, 255, 255, 0.85) !important;
}

.univ-modal-compact .logout-univ-subtitle {
    font-size: 0.63rem !important;
    letter-spacing: 1.2px !important;
    margin-bottom: 0.15rem !important;
}

.univ-modal-compact .logout-college-subtitle {
    font-size: 0.68rem !important;
    margin-bottom: 0.35rem !important;
}

.univ-modal-compact .logout-modal-header h2 {
    font-size: 1.05rem !important;
    letter-spacing: -0.2px !important;
}

.univ-modal-compact .logout-modal-body {
    padding: 1.1rem 1.25rem 1.15rem 1.25rem !important;
}

.univ-modal-compact .logout-user-card {
    padding: 0.55rem 0.75rem !important;
    margin-bottom: 0.85rem !important;
    gap: 0.65rem !important;
    border-radius: 10px !important;
}

.univ-modal-compact .logout-user-avatar {
    width: 36px !important;
    height: 36px !important;
    font-size: 0.92rem !important;
}

.univ-modal-compact .logout-user-name {
    font-size: 0.86rem !important;
}

.univ-modal-compact #univ_delete_doc_title,
.univ-modal-compact #univ_archive_doc_title,
.univ-modal-compact #univ_restore_doc_title {
    font-size: 0.73rem !important;
    margin-top: 0.15rem !important;
}

.univ-modal-compact .logout-user-role {
    font-size: 0.68rem !important;
    margin-top: 0.25rem !important;
}

.univ-modal-compact .logout-prompt-title {
    font-size: 0.92rem !important;
    margin-bottom: 0.25rem !important;
}

.univ-modal-compact .logout-prompt-desc {
    font-size: 0.78rem !important;
    line-height: 1.4 !important;
    margin: 0 0 0.95rem 0 !important;
}

.univ-modal-compact .logout-actions {
    gap: 0.6rem !important;
}

.univ-modal-compact .logout-actions button {
    padding: 0.55rem 0.85rem !important;
    font-size: 0.8rem !important;
    border-radius: 8px !important;
}

.univ-modal-compact .logout-modal-footer-note {
    margin-top: 0.75rem !important;
    font-size: 0.68rem !important;
}
</style>

<!-- Banner -->
<div class="mom-archive-banner">
    <h1>
        <i class="fa-solid <?php echo $is_archive_view ? 'fa-box-archive' : 'fa-file-signature'; ?>" id="momBannerIcon"></i>
        <span id="momBannerTitle"><?php echo $is_archive_view ? 'MOM Archive' : 'Official MOM Registry'; ?></span>
    </h1>
    <p id="momBannerSubtitle">
        <?php echo $is_archive_view ? 'All soft-deleted and safely preserved defense meeting minutes and deliberations.' : 'All filed defense meeting minutes, deliberation verdicts, and official records.'; ?>
    </p>
</div>

<!-- Info banner for Archived View -->
<div class="archived-info-banner no-print" id="archivedInfoBanner" style="<?php echo ($is_archive_view) ? 'display: flex;' : 'display: none;'; ?>">
    <div style="display: flex; align-items: center; gap: 0.65rem; flex: 1; min-width: 250px;">
        <i class="fa-solid fa-box-archive" style="font-size: 1.25rem; flex-shrink: 0;"></i>
        <div>
            <strong>Archived MOM Vault:</strong> Soft-deleted records are safely preserved here and excluded from active defense schedules. You can view complete minutes, restore records, or permanently delete them.
        </div>
    </div>
    <div class="archived-bulk-actions" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
        <button type="button" class="mom-action-btn btn-restore" id="btnRestoreAll" onclick="restoreAllMomRecords()" <?php echo ($archived_count === 0) ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : ''; ?> title="Restore all archived records to the active registry">
            <i class="fa-solid fa-rotate-left"></i> Restore All (<span id="bulkRestoreCount"><?php echo $archived_count; ?></span>)
        </button>
        <button type="button" class="mom-action-btn btn-delete" id="btnDeleteAll" onclick="purgeAllMomRecords()" <?php echo ($archived_count === 0) ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : ''; ?> title="Permanently delete all archived records">
            <i class="fa-solid fa-trash-can"></i> Delete All
        </button>
    </div>
</div>

<!-- Stats Cards (for Active View) -->
<div class="mom-archive-stats no-print" id="momStatsRow" style="<?php echo ($is_archive_view) ? 'display: none !important;' : ''; ?>">
    <div class="mom-archive-stat active" onclick="filterMomArchive('all', this)">
        <div class="stat-num" id="statActiveNum"><?php echo $total_count; ?></div>
        <div class="stat-lbl"><i class="fa-solid fa-folder-open" style="color: #047857;"></i> Active Records</div>
    </div>
    <div class="mom-archive-stat" onclick="filterMomArchive('finalized', this)">
        <div class="stat-num"><?php echo $finalized_count; ?></div>
        <div class="stat-lbl"><i class="fa-solid fa-circle-check" style="color: #10B981;"></i> Finalized</div>
    </div>
    <div class="mom-archive-stat" onclick="filterMomArchive('draft', this)">
        <div class="stat-num"><?php echo $draft_count; ?></div>
        <div class="stat-lbl"><i class="fa-solid fa-pen-to-square" style="color: #F59E0B;"></i> Drafts</div>
    </div>
</div>

<!-- Search -->
<div class="mom-search-bar no-print">
    <i class="fa-solid fa-search"></i>
    <input type="text" id="momArchiveSearch" placeholder="Search by candidate, title, verdict, documenter..." onkeyup="searchMomArchive(this.value)">
</div>

<!-- MOM Records Grid -->
<div class="mom-grid" id="momArchiveList">
    <!-- Empty Active State -->
    <div class="mom-empty-state" id="emptyActiveMsg" style="<?php echo (!$is_archive_view && $total_count === 0) ? 'display:block;' : 'display:none;'; ?>">
        <i class="fa-solid fa-file-circle-question"></i>
        <h3>No Active Minutes of the Meeting</h3>
        <p>Once defense MOM documents are created and saved, they will appear here.</p>
    </div>

    <!-- Empty Archived State -->
    <div class="mom-empty-state" id="emptyArchivedMsg" style="<?php echo ($is_archive_view && $archived_count === 0) ? 'display:block;' : 'display:none;'; ?>">
        <i class="fa-solid fa-box-open"></i>
        <h3>Archived Vault is Empty</h3>
        <p>No Minutes of the Meeting have been archived.</p>
    </div>

    <?php if (!empty($all_moms)): ?>
        <?php foreach ($all_moms as $mom): ?>
            <?php
            $is_archived = !empty($mom['is_archived']);
            $vs = getVerdictStyle($mom['decision_verdict'] ?? '');
            $ms = getMomStatusStyle($mom['status'] ?? 'draft');
            $is_prop = in_array($mom['defense_stage'] ?? '', ['Title Proposal', 'Outline Defense', 'Pre-Oral Defense']);
            $course_code = !empty($mom['course_code']) ? $mom['course_code'] : ($is_prop ? 'IT 198' : 'IT 199');
            $panel_names = [];
            if (!empty($mom['panel_members'])) {
                $decoded = json_decode($mom['panel_members'], true);
                $panel_names = is_array($decoded) ? $decoded : array_map('trim', explode(',', $mom['panel_members']));
            }
            ?>
            <div class="mom-record-card"
                 id="mom-card-<?php echo $mom['id']; ?>"
                 data-mom-id="<?php echo $mom['id']; ?>"
                 data-mom-status="<?php echo htmlspecialchars($mom['status']); ?>"
                 data-mom-archived="<?php echo $is_archived ? '1' : '0'; ?>"
                 style="<?php echo ($is_archived === $is_archive_view) ? '' : 'display:none;'; ?>"
                 data-search-text="<?php echo strtolower(htmlspecialchars(
                     ($mom['document_title'] ?? '') . ' ' . $mom['student_name'] . ' ' . ($mom['group_name'] ?? '') . ' ' .
                     ($mom['decision_verdict'] ?? '') . ' ' . ($mom['recorded_by_name'] ?? '') . ' ' . ($mom['panel_chair_name'] ?? '') . ' ' .
                     ($mom['defense_title'] ?? '') . ' ' . ($mom['thesis_topic'] ?? '')
                 )); ?>">
                <div class="mom-card-top">
                    <div class="mom-verdict-strip" style="background: <?php echo $is_archived ? '#94A3B8' : $vs['color']; ?>;"></div>
                    <div class="mom-card-body">
                        <!-- Header Row: Title + Badges -->
                        <div class="mom-card-header-row">
                            <div>
                                <h3 class="mom-doc-title"><?php echo htmlspecialchars($mom['document_title'] ?? $mom['defense_title'] ?? 'Defense Meeting Minutes'); ?></h3>
                                <div class="mom-badges-row">
                                    <!-- Archived Badge (if archived) -->
                                    <?php if ($is_archived): ?>
                                        <span class="mom-badge" style="background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA;">
                                            <i class="fa-solid fa-box-archive"></i> Archived<?php if(!empty($mom['archived_at'])) echo ' • ' . date('M d, Y', strtotime($mom['archived_at'])); ?>
                                        </span>
                                    <?php endif; ?>

                                    <!-- Verdict Badge -->
                                    <?php if (!empty($mom['decision_verdict'])): ?>
                                        <span class="mom-badge" style="background: <?php echo $vs['bg']; ?>; color: <?php echo $vs['color']; ?>; border: 1px solid <?php echo $vs['border']; ?>;">
                                            <i class="fa-solid <?php echo $vs['icon']; ?>"></i> <?php echo htmlspecialchars($mom['decision_verdict']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <!-- Status Badge -->
                                    <span class="mom-badge" style="background: <?php echo $ms['bg']; ?>; color: <?php echo $ms['color']; ?>;">
                                        <i class="fa-solid fa-tag"></i> <?php echo $ms['label']; ?>
                                    </span>
                                    <!-- Course Code -->
                                    <span class="mom-badge" style="background: <?php echo $is_prop ? '#FEF3C7' : '#EDE9FE'; ?>; color: <?php echo $is_prop ? '#92400E' : '#6D28D9'; ?>;">
                                        <i class="fa-solid fa-code-branch"></i> <?php echo htmlspecialchars($course_code); ?>
                                    </span>
                                    <!-- Grade -->
                                    <?php if (!empty($mom['grade_average'])): ?>
                                        <span class="mom-badge" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0;">
                                            <i class="fa-solid fa-star"></i> Grade: <?php echo number_format((float)$mom['grade_average'], 2); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Metadata Grid -->
                        <div class="mom-meta-grid">
                            <div class="mom-meta-item">
                                <i class="fa-solid fa-graduation-cap"></i>
                                <span><strong><?php echo htmlspecialchars($mom['student_name']); ?></strong></span>
                            </div>
                            <?php if (!empty($mom['group_name'])): ?>
                                <div class="mom-meta-item">
                                    <i class="fa-solid fa-users"></i>
                                    <span>Group: <strong><?php echo htmlspecialchars($mom['group_name']); ?></strong></span>
                                </div>
                            <?php endif; ?>
                            <div class="mom-meta-item">
                                <i class="fa-solid fa-calendar-days"></i>
                                <span>Defense: <strong><?php echo !empty($mom['meeting_date']) ? date('M d, Y', strtotime($mom['meeting_date'])) : 'N/A'; ?></strong></span>
                            </div>
                            <div class="mom-meta-item">
                                <i class="fa-solid fa-bookmark"></i>
                                <span>Stage: <strong><?php echo htmlspecialchars($mom['defense_stage'] ?? 'N/A'); ?></strong></span>
                            </div>
                            <?php if (!empty($mom['panel_chair_name'])): ?>
                                <div class="mom-meta-item">
                                    <i class="fa-solid fa-crown"></i>
                                    <span>Chair: <strong><?php echo htmlspecialchars($mom['panel_chair_name']); ?></strong></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($mom['recorded_by_name'])): ?>
                                <div class="mom-meta-item">
                                    <i class="fa-solid fa-pen-nib"></i>
                                    <span>Documented by: <strong style="color: #047857;"><?php echo htmlspecialchars($mom['recorded_by_name']); ?></strong></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($mom['approved_by_chair'])): ?>
                                <div class="mom-meta-item">
                                    <i class="fa-solid fa-signature"></i>
                                    <span>Approved: <strong><?php echo htmlspecialchars($mom['approved_by_chair']); ?></strong></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Summary Preview -->
                        <?php if (!empty($mom['discussion_summary'])): ?>
                            <div class="mom-summary-preview">
                                <strong style="color: #1E293B;">Summary:</strong> <?php echo htmlspecialchars(mb_strimwidth($mom['discussion_summary'], 0, 200, '...')); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Recommendations Preview -->
                        <?php if (!empty($mom['recommendations'])): ?>
                            <div class="mom-summary-preview" style="background: #FFFBEB;">
                                <strong style="color: #92400E;">Recommendations:</strong> <?php echo htmlspecialchars(mb_strimwidth($mom['recommendations'], 0, 150, '...')); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mom-card-footer">
                    <div class="mom-footer-meta">
                        <span><i class="fa-solid fa-microphone-lines"></i> <?php echo (int)$mom['transcript_count']; ?> transcripts</span>
                        <span>•</span>
                        <span>Filed: <?php echo date('M d, Y g:i A', strtotime($mom['created_at'])); ?></span>
                        <?php if ($mom['updated_at'] !== $mom['created_at']): ?>
                            <span>• Updated: <?php echo date('M d, Y', strtotime($mom['updated_at'])); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mom-footer-actions">
                        <button type="button" class="mom-action-btn btn-view" onclick="openViewMomModal(<?php echo $mom['id']; ?>)" title="View Official Minutes of the Meeting">
                            <i class="fa-solid fa-file-lines"></i> Open MOM
                        </button>
                        <button type="button" class="mom-action-btn btn-print" onclick="openAndPrintMomModal(<?php echo $mom['id']; ?>)" title="Print Official MOM Format">
                            <i class="fa-solid fa-print"></i> Print
                        </button>
                        <?php if($is_archived): ?>
                        <button type="button" class="mom-action-btn btn-restore" onclick="restoreMomRecord(<?php echo $mom['id']; ?>)" title="Restore to Active MOM Registry">
                            <i class="fa-solid fa-rotate-left"></i> Restore
                        </button>
                        <button type="button" class="mom-action-btn btn-delete" onclick="purgeMomRecord(<?php echo $mom['id']; ?>)" title="Permanently delete from database">
                            <i class="fa-solid fa-trash-can"></i> Delete
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- View-Only Official MOM Modal -->
<div id="viewMomModal" class="modal" style="display:none;">
    <div class="modal-content view-mom-modal-content">
        <!-- Sticky Modal Header -->
        <div class="view-mom-modal-header no-print">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: #ECFDF5; color: #047857; display: flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                    <i class="fa-solid fa-file-shield"></i>
                </div>
                <div>
                    <h2 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #0F172A;">Official Minutes of the Meeting (MOM)</h2>
                    <div style="font-size: 0.75rem; color: #64748B;">
                        Record <strong style="color:#047857;">#MOM-<span id="vm_record_id">--</span></strong> • Certified Final Approved Document
                    </div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button type="button" onclick="printViewMomDocument()" class="mom-action-btn btn-print" style="padding: 0.4rem 0.9rem; font-size: 0.8rem;">
                    <i class="fa-solid fa-print"></i> Print Document
                </button>
                <button type="button" onclick="closeViewMomModal()" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #94A3B8; padding: 0.2rem 0.5rem; line-height: 1;">&times;</button>
            </div>
        </div>

        <!-- Scrollable Document Sheet -->
        <div class="view-mom-sheet" id="viewMomSheet">
            
            <!-- CSU Official Letterhead -->
            <div class="view-mom-letterhead">
                <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: 0.5rem;">
                    <img src="logo.png" alt="CSU Seal" style="width: 60px; height: auto;">
                    <div>
                        <div class="univ-rep">Republic of the Philippines</div>
                        <div class="univ-title">CARAGA STATE UNIVERSITY CABADBARAN CAMPUS</div>
                        <div class="college-title">College of Engineering and Information Technology</div>
                        <div class="campus-loc">T. Curato St., Cabadbaran City, 8605 Agusan del Norte, Philippines</div>
                    </div>
                </div>
                <div class="doc-heading">
                    MINUTES OF THE ORAL DEFENSE PROCEEDINGS
                </div>
            </div>

            <!-- Session & Committee Information Grid -->
            <div class="view-mom-grid">
                <div class="view-mom-grid-item" style="grid-column: 1 / -1;">
                    <div class="label"><i class="fa-solid fa-file-signature"></i> Document Title / Defense Topic</div>
                    <div class="value" id="vm_doc_title" style="font-size: 0.98rem; color: #047857; font-weight: 800;">--</div>
                </div>

                <div class="view-mom-grid-item">
                    <div class="label"><i class="fa-solid fa-user-graduate"></i> Candidate / Group</div>
                    <div class="value" id="vm_candidate_group">--</div>
                </div>

                <div class="view-mom-grid-item">
                    <div class="label"><i class="fa-solid fa-bookmark"></i> Defense Stage & Course</div>
                    <div class="value" id="vm_defense_stage">--</div>
                </div>

                <div class="view-mom-grid-item">
                    <div class="label"><i class="fa-solid fa-calendar-day"></i> Meeting Date</div>
                    <div class="value" id="vm_meeting_date">--</div>
                </div>

                <div class="view-mom-grid-item">
                    <div class="label"><i class="fa-solid fa-location-dot"></i> Defense Venue</div>
                    <div class="value" id="vm_venue">--</div>
                </div>

                <div class="view-mom-grid-item">
                    <div class="label"><i class="fa-solid fa-gavel"></i> Committee Verdict</div>
                    <div class="value">
                        <span id="vm_verdict_badge" class="mom-badge" style="font-size: 0.78rem; padding: 4px 10px;">--</span>
                    </div>
                </div>

                <div class="view-mom-grid-item" id="vm_grade_item">
                    <div class="label"><i class="fa-solid fa-calculator"></i> Committee Average Grade</div>
                    <div class="value" id="vm_grade_val" style="color: #047857; font-weight: 800;">--</div>
                </div>

                <div class="view-mom-grid-item" style="grid-column: 1 / -1;">
                    <div class="label"><i class="fa-solid fa-users"></i> Panel Committee Members Present</div>
                    <div class="value" id="vm_panel_members">--</div>
                </div>

                <div class="view-mom-grid-item" style="grid-column: 1 / -1;">
                    <div class="label"><i class="fa-solid fa-chalkboard-user"></i> Capstone Adviser Present</div>
                    <div class="value" id="vm_adviser">--</div>
                </div>
            </div>

            <!-- Section I: Presentation Highlights & Discussion Points -->
            <div class="view-mom-section">
                <div class="view-mom-section-title">
                    <i class="fa-solid fa-list-check" style="color: #047857;"></i> I. Presentation Highlights & Discussion Points
                </div>
                <div id="vm_discussions">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Section II: Required Revisions & Recommendations -->
            <div class="view-mom-section">
                <div class="view-mom-section-title">
                    <i class="fa-solid fa-list-ol" style="color: #D97706;"></i> II. Committee Required Revisions & Recommendations
                </div>
                <div id="vm_recommendations">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Section III: Remarks / Additional Agreements (Optional) -->
            <div class="view-mom-section" id="vm_notes_section" style="display:none;">
                <div class="view-mom-section-title">
                    <i class="fa-solid fa-clipboard-check" style="color: #2563EB;"></i> III. Remarks / Additional Committee Agreements
                </div>
                <div id="vm_notes">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Section IV: Action Items & Compliance Table -->
            <div class="view-mom-section">
                <div class="view-mom-section-title">
                    <i class="fa-solid fa-tasks" style="color: #0F172A;"></i> IV. Action Items & Next Steps
                </div>
                <table class="view-mom-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Action Item / Task</th>
                            <th style="width: 28%;">Responsible Person</th>
                            <th style="width: 22%;">Target Deadline</th>
                        </tr>
                    </thead>
                    <tbody id="vm_action_items_body">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>
            </div>

            <!-- Section V: Certification & Signatories -->
            <div class="view-mom-section" style="margin-top: 2rem;">
                <div class="view-mom-section-title">
                    <i class="fa-solid fa-stamp" style="color: #059669;"></i> V. Documentation Certification & Endorsement
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1rem;">
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1rem 1.25rem; border-radius: 8px;">
                        <div style="font-size: 0.72rem; color: #64748B; text-transform: uppercase; font-weight: 700; margin-bottom: 0.35rem;">
                            <i class="fa-solid fa-pen-nib" style="color: #059669;"></i> Documented / Recorded By:
                        </div>
                        <div id="vm_recorded_by" style="font-weight: 700; font-size: 0.95rem; color: #0F172A;">--</div>
                        <div style="font-size: 0.75rem; color: #047857; margin-top: 0.2rem;">Committee Secretary / Documenter</div>
                    </div>

                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 1rem 1.25rem; border-radius: 8px;">
                        <div style="font-size: 0.72rem; color: #64748B; text-transform: uppercase; font-weight: 700; margin-bottom: 0.35rem;">
                            <i class="fa-solid fa-crown" style="color: #D97706;"></i> Approved & Certified By:
                        </div>
                        <div id="vm_approved_chair" style="font-weight: 700; font-size: 0.95rem; color: #0F172A;">--</div>
                        <div style="font-size: 0.75rem; color: #64748B; margin-top: 0.2rem;">Panel Committee Chair</div>
                    </div>
                </div>

                <!-- Print Signature Lines (Only visible on print) -->
                <div style="display: none; margin-top: 3.5rem; justify-content: space-between; page-break-inside: avoid;" class="print-signature-section">
                    <div style="text-align: center; width: 42%;">
                        <div style="border-bottom: 1.5px solid #000; padding-bottom: 0.25rem; font-weight: bold; font-size: 10pt;" id="vm_print_rec_by">--</div>
                        <div style="font-size: 8.5pt; margin-top: 4px; color: #333;">Documented By / Committee Secretary</div>
                    </div>
                    <div style="text-align: center; width: 42%;">
                        <div style="border-bottom: 1.5px solid #000; padding-bottom: 0.25rem; font-weight: bold; font-size: 10pt;" id="vm_print_chair">--</div>
                        <div style="font-size: 8.5pt; margin-top: 4px; color: #333;">Certified & Approved / Panel Chair</div>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 1.25rem; font-size: 0.72rem; color: #94A3B8;" id="vm_filing_stamp">
                    Official CSU CEIT Defense Minutes
                </div>
            </div>

        </div>

        <!-- Sticky Modal Footer -->
        <div class="view-mom-modal-footer no-print">
            <span style="font-size: 0.78rem; color: #64748B;">
                <i class="fa-solid fa-lock" style="color: #047857;"></i> View-Only Mode: Live form editing is disabled to preserve official archive integrity.
            </span>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button type="button" onclick="closeViewMomModal()" class="mom-action-btn">
                    <i class="fa-solid fa-xmark"></i> Close
                </button>
                <button type="button" onclick="printViewMomDocument()" class="mom-action-btn btn-print">
                    <i class="fa-solid fa-print"></i> Print Official Document
                </button>
                <!-- Archived mode: Restore & Delete -->
                <button type="button" id="vm_btn_restore" onclick="restoreCurrentViewMom()" class="mom-action-btn btn-restore" style="display:none;" title="Restore this MOM to active registry">
                    <i class="fa-solid fa-rotate-left"></i> Restore to Active
                </button>
                <button type="button" id="vm_btn_purge" onclick="purgeCurrentViewMom()" class="mom-action-btn btn-delete" style="display:none;" title="Permanently delete from database">
                    <i class="fa-solid fa-trash-can"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================================
     UNIVERSITY-GRADE CONFIRMATION MODALS (CSU CEIT ACADEMIC THEME)
     ========================================================================== -->

<!-- 1. University Permanent Delete Confirmation Modal -->
<div id="univDeleteMomModal" class="modal" style="display:none;">
    <div class="modal-content logout-modal-content univ-modal-compact">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #7F1D1D 0%, #991B1B 55%, #450A0A 100%); border-bottom: 3px solid #EF4444;">
            <div class="logout-seal-wrapper" style="box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25), 0 0 0 2.5px rgba(239, 68, 68, 0.7);">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle" style="color: #FCA5A5;">Caraga State University</div>
            <div class="logout-college-subtitle" style="color: #FEE2E2;">College of Engineering and Information Technology</div>
            <h2>Permanently Erase MOM Record</h2>
        </div>
        <div class="logout-modal-body" style="background: #FFFFFF;">
            <div class="logout-user-card" style="display: flex; align-items: flex-start; background: #FEF2F2; border: 1px solid #FECACA; text-align: left;">
                <div class="logout-user-avatar" id="univ_delete_avatar" style="background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);">
                    <i class="fa-solid fa-trash-can"></i>
                </div>
                <div class="logout-user-details" style="flex: 1; min-width: 0;">
                    <div class="logout-user-name" id="univ_delete_cand_name" style="font-weight: 700; color: #991B1B; line-height: 1.3;">Candidate / Group</div>
                    <div id="univ_delete_doc_title" style="color: #7F1D1D; font-style: italic; line-height: 1.35;">"Minutes of Oral Defense"</div>
                    <div class="logout-user-role" style="font-weight: 700; color: #DC2626; text-transform: uppercase; letter-spacing: 0.3px; display: flex; align-items: center; gap: 0.35rem;">
                        <span class="logout-session-dot" style="width: 6px; height: 6px; background: #DC2626; border-radius: 50%; display: inline-block; box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.25);"></span>
                        <span>Irreversible Database Purge</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-weight: 700; color: #991B1B;">Official Record Deletion Warning</div>
            <p class="logout-prompt-desc" style="color: #64748B;">
                Are you sure you want to permanently delete this official Minutes of the Meeting record? This action <strong style="color: #DC2626;">cannot be undone</strong> and will completely wipe deliberation verdicts, committee evaluations, and official certificates from the registry.
            </p>

            <div class="logout-actions" style="display: flex;">
                <button type="button" class="btn-logout-cancel" onclick="closeUnivDeleteModal()" style="flex: 1; background: #F1F5F9; color: #475569; border: 1.5px solid #CBD5E1; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </button>
                <button type="button" id="univ_delete_confirm_btn" onclick="confirmUnivDelete()" style="flex: 1.25; background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); color: white; border: none; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-trash-can"></i> Yes, Delete Permanently
                </button>
            </div>

            <div class="logout-modal-footer-note" style="color: #94A3B8; display: flex; align-items: center; justify-content: center; gap: 0.4rem; letter-spacing: 0.2px;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<!-- 2. University Archive Confirmation Modal -->
<div id="univArchiveMomModal" class="modal" style="display:none;">
    <div class="modal-content logout-modal-content univ-modal-compact">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #78350F 0%, #B45309 55%, #451A03 100%); border-bottom: 3px solid #F59E0B;">
            <div class="logout-seal-wrapper" style="box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25), 0 0 0 2.5px rgba(245, 158, 11, 0.7);">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle" style="color: #FDE68A;">Caraga State University</div>
            <div class="logout-college-subtitle" style="color: #FEF3C7;">College of Engineering and Information Technology</div>
            <h2>Archive Defense MOM</h2>
        </div>
        <div class="logout-modal-body" style="background: #FFFFFF;">
            <div class="logout-user-card" style="display: flex; align-items: flex-start; background: #FFFBEB; border: 1px solid #FDE68A; text-align: left;">
                <div class="logout-user-avatar" style="background: linear-gradient(135deg, #D97706 0%, #B45309 100%); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(217, 119, 6, 0.25);">
                    <i class="fa-solid fa-box-archive"></i>
                </div>
                <div class="logout-user-details" style="flex: 1; min-width: 0;">
                    <div class="logout-user-name" id="univ_archive_cand_name" style="font-weight: 700; color: #92400E; line-height: 1.3;">Candidate / Group</div>
                    <div id="univ_archive_doc_title" style="color: #B45309; font-style: italic; line-height: 1.35;">"Minutes of Oral Defense"</div>
                    <div class="logout-user-role" style="font-weight: 700; color: #B45309; text-transform: uppercase; letter-spacing: 0.3px; display: flex; align-items: center; gap: 0.35rem;">
                        <span class="logout-session-dot" style="width: 6px; height: 6px; background: #F59E0B; border-radius: 50%; display: inline-block; box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25);"></span>
                        <span>Safe Vault Archival</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-weight: 700; color: #0F172A;">Move Record to Archive Vault?</div>
            <p class="logout-prompt-desc" style="color: #64748B;">
                This defense minutes document will be excluded from the active registry and safely stored in the <strong style="color: #92400E;">Archived MOMs Vault</strong>. You can inspect or restore it to the live registry at any time.
            </p>

            <div class="logout-actions" style="display: flex;">
                <button type="button" class="btn-logout-cancel" onclick="closeUnivArchiveModal()" style="flex: 1; background: #F1F5F9; color: #475569; border: 1.5px solid #CBD5E1; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </button>
                <button type="button" id="univ_archive_confirm_btn" onclick="confirmUnivArchive()" style="flex: 1.25; background: linear-gradient(135deg, #D97706 0%, #B45309 100%); color: white; border: none; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-box-archive"></i> Yes, Move to Archive
                </button>
            </div>

            <div class="logout-modal-footer-note" style="color: #94A3B8; display: flex; align-items: center; justify-content: center; gap: 0.4rem; letter-spacing: 0.2px;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<!-- 3. University Restore Confirmation Modal -->
<div id="univRestoreMomModal" class="modal" style="display:none;">
    <div class="modal-content logout-modal-content univ-modal-compact">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%); border-bottom: 3px solid #10B981;">
            <div class="logout-seal-wrapper" style="box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25), 0 0 0 2.5px rgba(16, 185, 129, 0.7);">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle" style="color: #A7F3D0;">Caraga State University</div>
            <div class="logout-college-subtitle" style="color: #D1FAE5;">College of Engineering and Information Technology</div>
            <h2>Restore Defense MOM</h2>
        </div>
        <div class="logout-modal-body" style="background: #FFFFFF;">
            <div class="logout-user-card" style="display: flex; align-items: flex-start; background: #ECFDF5; border: 1px solid #A7F3D0; text-align: left;">
                <div class="logout-user-avatar" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);">
                    <i class="fa-solid fa-rotate-left"></i>
                </div>
                <div class="logout-user-details" style="flex: 1; min-width: 0;">
                    <div class="logout-user-name" id="univ_restore_cand_name" style="font-weight: 700; color: #065F46; line-height: 1.3;">Candidate / Group</div>
                    <div id="univ_restore_doc_title" style="color: #047857; font-style: italic; line-height: 1.35;">"Minutes of Oral Defense"</div>
                    <div class="logout-user-role" style="font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 0.3px; display: flex; align-items: center; gap: 0.35rem;">
                        <span class="logout-session-dot" style="width: 6px; height: 6px; background: #10B981; border-radius: 50%; display: inline-block; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);"></span>
                        <span>Reactivate to Active Registry</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-weight: 700; color: #0F172A;">Restore Record to Active Registry?</div>
            <p class="logout-prompt-desc" style="color: #64748B;">
                This official Minutes of the Meeting will be returned immediately to the live <strong style="color: #047857;">Official MOM Registry</strong>, reinstating defense verification and print access.
            </p>

            <div class="logout-actions" style="display: flex;">
                <button type="button" class="btn-logout-cancel" onclick="closeUnivRestoreModal()" style="flex: 1; background: #F1F5F9; color: #475569; border: 1.5px solid #CBD5E1; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </button>
                <button type="button" id="univ_restore_confirm_btn" onclick="confirmUnivRestore()" style="flex: 1.25; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; border: none; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-rotate-left"></i> Yes, Restore Record
                </button>
            </div>

            <div class="logout-modal-footer-note" style="color: #94A3B8; display: flex; align-items: center; justify-content: center; gap: 0.4rem; letter-spacing: 0.2px;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<!-- 4. University Restore All Confirmation Modal -->
<div id="univRestoreAllModal" class="modal" style="display:none;">
    <div class="modal-content logout-modal-content univ-modal-compact">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #064E3B 0%, #047857 55%, #022C22 100%); border-bottom: 3px solid #10B981;">
            <div class="logout-seal-wrapper" style="box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25), 0 0 0 2.5px rgba(16, 185, 129, 0.7);">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle" style="color: #A7F3D0;">Caraga State University</div>
            <div class="logout-college-subtitle" style="color: #D1FAE5;">College of Engineering and Information Technology</div>
            <h2>Restore All Archived MOMs</h2>
        </div>
        <div class="logout-modal-body" style="background: #FFFFFF;">
            <div class="logout-user-card" style="display: flex; align-items: center; background: #ECFDF5; border: 1px solid #A7F3D0; text-align: left;">
                <div class="logout-user-avatar" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(5, 150, 105, 0.25);">
                    <i class="fa-solid fa-rotate-left"></i>
                </div>
                <div class="logout-user-details" style="flex: 1; min-width: 0;">
                    <div style="font-size: 0.92rem; font-weight: 700; color: #065F46; line-height: 1.2;">Batch Registry Reactivation</div>
                    <div style="font-weight: 600; color: #059669; text-transform: uppercase; letter-spacing: 0.3px; display: flex; align-items: center; gap: 0.35rem;">
                        <span style="width: 6px; height: 6px; background: #10B981; border-radius: 50%; display: inline-block; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.25);"></span>
                        <span id="univ_restore_all_count_badge"><?php echo $archived_count; ?> Archived Documents</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-weight: 700; color: #0F172A;">Restore All Records to Active Registry?</div>
            <p class="logout-prompt-desc" style="color: #64748B;">
                Are you sure you want to restore all <strong id="univ_restore_all_count_desc" style="color: #047857;"><?php echo $archived_count; ?></strong> archived Minutes of the Meeting records? All defense proceedings will be immediately reinstated in the active registry.
            </p>

            <div class="logout-actions" style="display: flex;">
                <button type="button" class="btn-logout-cancel" onclick="closeUnivRestoreAllModal()" style="flex: 1; background: #F1F5F9; color: #475569; border: 1.5px solid #CBD5E1; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </button>
                <button type="button" id="univ_restore_all_confirm_btn" onclick="confirmUnivRestoreAll()" style="flex: 1.25; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; border: none; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-rotate-left"></i> Yes, Restore All
                </button>
            </div>

            <div class="logout-modal-footer-note" style="color: #94A3B8; display: flex; align-items: center; justify-content: center; gap: 0.4rem; letter-spacing: 0.2px;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<!-- 5. University Delete All Confirmation Modal -->
<div id="univDeleteAllModal" class="modal" style="display:none;">
    <div class="modal-content logout-modal-content univ-modal-compact">
        <div class="logout-modal-header" style="background: linear-gradient(135deg, #7F1D1D 0%, #991B1B 55%, #450A0A 100%); border-bottom: 3px solid #EF4444;">
            <div class="logout-seal-wrapper" style="box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25), 0 0 0 2.5px rgba(239, 68, 68, 0.7);">
                <img src="logo.png" alt="CSU Seal">
            </div>
            <div class="logout-univ-subtitle" style="color: #FCA5A5;">Caraga State University</div>
            <div class="logout-college-subtitle" style="color: #FEE2E2;">College of Engineering and Information Technology</div>
            <h2>Permanently Erase All Archives</h2>
        </div>
        <div class="logout-modal-body" style="background: #FFFFFF;">
            <div class="logout-user-card" style="display: flex; align-items: center; background: #FEF2F2; border: 1px solid #FECACA; text-align: left;">
                <div class="logout-user-avatar" style="background: linear-gradient(135deg, #DC2626 0%, #991B1B 100%); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.25);">
                    <i class="fa-solid fa-trash-can"></i>
                </div>
                <div class="logout-user-details" style="flex: 1; min-width: 0;">
                    <div style="font-size: 0.92rem; font-weight: 700; color: #991B1B; line-height: 1.2;">Permanent Mass Deletion</div>
                    <div style="font-weight: 600; color: #DC2626; text-transform: uppercase; letter-spacing: 0.3px; display: flex; align-items: center; gap: 0.35rem;">
                        <span style="width: 6px; height: 6px; background: #DC2626; border-radius: 50%; display: inline-block; box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.25);"></span>
                        <span id="univ_delete_all_count_badge"><?php echo $archived_count; ?> Archived Records</span>
                    </div>
                </div>
            </div>

            <div class="logout-prompt-title" style="font-weight: 700; color: #991B1B;">DANGER: Irreversible Vault Wipe</div>
            <p class="logout-prompt-desc" style="color: #64748B;">
                Are you sure you want to permanently delete all <strong id="univ_delete_all_count_desc" style="color: #DC2626;"><?php echo $archived_count; ?></strong> archived MOM records? This action <strong style="color: #DC2626;">CANNOT BE UNDONE</strong>. All committee deliberations, evaluation verdicts, and certifications will be wiped permanently from the database.
            </p>

            <div class="logout-actions" style="display: flex;">
                <button type="button" class="btn-logout-cancel" onclick="closeUnivDeleteAllModal()" style="flex: 1; background: #F1F5F9; color: #475569; border: 1.5px solid #CBD5E1; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </button>
                <button type="button" id="univ_delete_all_confirm_btn" onclick="confirmUnivDeleteAll()" style="flex: 1.3; background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); color: white; border: none; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); font-family: inherit;">
                    <i class="fa-solid fa-trash-can"></i> Yes, Delete All Permanently
                </button>
            </div>

            <div class="logout-modal-footer-note" style="color: #94A3B8; display: flex; align-items: center; justify-content: center; gap: 0.4rem; letter-spacing: 0.2px;">
                <i class="fa-solid fa-shield-halved"></i> Secured CEIT Academic Portal &bull; CSU Cabadbaran
            </div>
        </div>
    </div>
</div>

<script>
// JSON dictionary of all MOM records for instant view-only rendering
const momsData = <?php echo json_encode($moms_data_json, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
let currentOpenMomId = null;
let currentViewMode = '<?php echo $is_archive_view ? "archived" : "active"; ?>';
let currentStatusFilter = 'all'; // 'all', 'finalized', 'draft'

// Switch between Active MOM Registry and Archived MOMs Vault
function switchMomView(mode, updateUrl = true) {
    currentViewMode = mode;

    const btnActive = document.getElementById('btnTabActive');
    const btnArchived = document.getElementById('btnTabArchived');
    const statsRow = document.getElementById('momStatsRow');
    const archivedBanner = document.getElementById('archivedInfoBanner');
    const bannerTitle = document.getElementById('momBannerTitle');
    const bannerSubtitle = document.getElementById('momBannerSubtitle');
    const bannerIcon = document.getElementById('momBannerIcon');

    if (mode === 'archived') {
        if (btnActive) btnActive.classList.remove('active');
        if (btnArchived) btnArchived.classList.add('active');
        if (statsRow) statsRow.style.setProperty('display', 'none', 'important');
        if (archivedBanner) archivedBanner.style.display = 'flex';
        if (bannerTitle) bannerTitle.textContent = 'MOM Archive';
        if (bannerSubtitle) bannerSubtitle.textContent = 'All soft-deleted and safely preserved defense meeting minutes and deliberations.';
        if (bannerIcon) bannerIcon.className = 'fa-solid fa-box-archive';
    } else {
        if (btnActive) btnActive.classList.add('active');
        if (btnArchived) btnArchived.classList.remove('active');
        if (statsRow) statsRow.style.removeProperty('display');
        if (archivedBanner) archivedBanner.style.display = 'none';
        if (bannerTitle) bannerTitle.textContent = 'Official MOM Registry';
        if (bannerSubtitle) bannerSubtitle.textContent = 'All filed defense meeting minutes, deliberation verdicts, and official records.';
        if (bannerIcon) bannerIcon.className = 'fa-solid fa-file-signature';
    }

    // Update URL quietly so bookmarking / refreshing maintains view
    if (updateUrl && window.history && window.history.replaceState) {
        const u = new URL(window.location.href);
        if (mode === 'archived') {
            u.searchParams.set('tab', 'archive');
            u.searchParams.delete('view');
        } else {
            u.searchParams.set('tab', 'mom_archive');
            u.searchParams.delete('view');
        }
        window.history.replaceState({}, '', u.toString());
    }

    // Sync sidebar navigation active state
    document.querySelectorAll('.sidebar-nav a.nav-item').forEach(item => {
        const href = item.getAttribute('href') || '';
        if (href.includes('tab=archive')) {
            item.classList.toggle('active', mode === 'archived');
        } else if (href.includes('tab=mom_archive')) {
            item.classList.toggle('active', mode === 'active');
        }
    });

    applyMomFilters();
}

function filterMomArchive(filter, el) {
    currentStatusFilter = filter;
    document.querySelectorAll('.mom-archive-stat').forEach(c => c.classList.remove('active'));
    if (el) el.classList.add('active');
    applyMomFilters();
}

function searchMomArchive(query) {
    applyMomFilters();
}

// Master filter evaluator that accounts for view mode (active vs archived), status, and search query
function applyMomFilters() {
    const searchInput = document.getElementById('momArchiveSearch');
    const q = (searchInput && searchInput.value) ? searchInput.value.toLowerCase().trim() : '';
    let visibleCount = 0;

    document.querySelectorAll('.mom-record-card').forEach(card => {
        const isArch = card.getAttribute('data-mom-archived') === '1';
        const status = card.getAttribute('data-mom-status');
        const text = (card.getAttribute('data-search-text') || '').toLowerCase();

        // View mode check
        const matchesMode = (currentViewMode === 'archived') ? isArch : !isArch;

        // Status filter (only applied on active view)
        let matchesStatus = true;
        if (currentViewMode === 'active') {
            if (currentStatusFilter === 'finalized') {
                matchesStatus = (status === 'finalized' || status === 'signed_off');
            } else if (currentStatusFilter === 'draft') {
                matchesStatus = (status === 'draft' || status === 'under_review');
            }
        }

        // Search query match
        const matchesSearch = !q || text.includes(q);

        if (matchesMode && matchesStatus && matchesSearch) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Update empty state banners
    const emptyActive = document.getElementById('emptyActiveMsg');
    const emptyArchived = document.getElementById('emptyArchivedMsg');

    if (currentViewMode === 'active') {
        if (emptyArchived) emptyArchived.style.display = 'none';
        if (emptyActive) emptyActive.style.display = (visibleCount === 0) ? 'block' : 'none';
    } else {
        if (emptyActive) emptyActive.style.display = 'none';
        if (emptyArchived) emptyArchived.style.display = (visibleCount === 0) ? 'block' : 'none';
    }
}

// Open View-Only Official MOM Modal
function openViewMomModal(momId) {
    const c = momsData[momId];
    if (!c) {
        alert("Minutes of the Meeting record not found.");
        return;
    }

    currentOpenMomId = momId;

    // Toggle Modal Action buttons based on archive status
    const isArchived = Boolean(parseInt(c.is_archived, 10));
    const btnArchive = document.getElementById('vm_btn_archive');
    const btnRestore = document.getElementById('vm_btn_restore');
    const btnPurge = document.getElementById('vm_btn_purge');

    if (btnArchive) btnArchive.style.display = isArchived ? 'none' : 'inline-flex';
    if (btnRestore) btnRestore.style.display = isArchived ? 'inline-flex' : 'none';
    if (btnPurge) btnPurge.style.display = isArchived ? 'inline-flex' : 'none';

    // Populate metadata
    document.getElementById('vm_record_id').textContent = c.id;
    document.getElementById('vm_doc_title').textContent = c.document_title;
    document.getElementById('vm_candidate_group').textContent = c.group_display;
    document.getElementById('vm_defense_stage').textContent = c.defense_stage + ' (' + c.course_code + ')';
    document.getElementById('vm_meeting_date').textContent = c.meeting_date;
    document.getElementById('vm_venue').textContent = c.venue;
    document.getElementById('vm_panel_members').textContent = c.panel_members;
    document.getElementById('vm_adviser').textContent = c.adviser_name;

    // Verdict & Grade
    const vBadge = document.getElementById('vm_verdict_badge');
    if (vBadge) {
        vBadge.style.background = c.verdict_style.bg;
        vBadge.style.color = c.verdict_style.color;
        vBadge.style.border = '1px solid ' + c.verdict_style.border;
        vBadge.innerHTML = `<i class="fa-solid ${c.verdict_style.icon}"></i> ${escapeHtml(c.decision_verdict)}`;
    }

    const gradeRow = document.getElementById('vm_grade_item');
    if (gradeRow) {
        if (c.grade_average) {
            gradeRow.style.display = 'block';
            document.getElementById('vm_grade_val').textContent = c.grade_average + '%';
        } else {
            gradeRow.style.display = 'none';
        }
    }

    // Section I: Discussion Points
    const discEl = document.getElementById('vm_discussions');
    if (c.discussion_summary && c.discussion_summary.trim()) {
        discEl.innerHTML = `<div style="line-height: 1.6; color: #1E293B; font-size: 0.9rem; white-space: pre-wrap;">${escapeHtml(c.discussion_summary)}</div>`;
    } else {
        discEl.innerHTML = `<p style="color: #94A3B8; font-style: italic; margin: 0;">No discussion summary recorded.</p>`;
    }

    // Section II: Required Revisions & Recommendations
    const recEl = document.getElementById('vm_recommendations');
    if (c.recommendations && c.recommendations.trim()) {
        recEl.innerHTML = `<div style="background: #FFFBEB; border: 1px solid #FDE68A; border-left: 4px solid #F59E0B; padding: 1rem 1.25rem; border-radius: 8px; line-height: 1.6; color: #92400E; font-size: 0.9rem; font-weight: 500; white-space: pre-wrap;">${escapeHtml(c.recommendations)}</div>`;
    } else {
        recEl.innerHTML = `<p style="color: #059669; font-weight: 600; margin: 0;"><i class="fa-solid fa-circle-check"></i> No manuscript corrections required. Candidate fully passed without revisions.</p>`;
    }

    // Section III: Remarks / Notes
    const notesSec = document.getElementById('vm_notes_section');
    const notesEl = document.getElementById('vm_notes');
    if (c.notes && c.notes.trim()) {
        notesSec.style.display = 'block';
        notesEl.innerHTML = `<div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 0.85rem 1.15rem; border-radius: 8px; line-height: 1.6; color: #334155; font-size: 0.88rem; white-space: pre-wrap;">${escapeHtml(c.notes)}</div>`;
    } else {
        notesSec.style.display = 'none';
    }

    // Section IV: Action Items
    const aiTbody = document.getElementById('vm_action_items_body');
    let aiList = [];
    try {
        if (c.action_items && typeof c.action_items === 'string' && c.action_items.trim().startsWith('[')) {
            aiList = JSON.parse(c.action_items);
        } else if (Array.isArray(c.action_items)) {
            aiList = c.action_items;
        }
    } catch(e) {
        aiList = [];
    }

    if (Array.isArray(aiList) && aiList.length > 0) {
        aiTbody.innerHTML = aiList.map(item => `
            <tr>
                <td style="font-weight: 600; color: #0F172A;">${escapeHtml(item.task || '')}</td>
                <td style="color: #475569;">${escapeHtml(item.assigned || 'Candidate / Group')}</td>
                <td>
                    <span style="background: #F1F5F9; color: #0F172A; padding: 0.2rem 0.55rem; border-radius: 4px; font-weight: 600; font-size: 0.78rem;">
                        <i class="fa-regular fa-calendar"></i> ${escapeHtml(item.deadline || 'N/A')}
                    </span>
                </td>
            </tr>
        `).join('');
    } else {
        aiTbody.innerHTML = `<tr><td colspan="3" style="text-align: center; color: #94A3B8; padding: 1rem; font-size: 0.85rem;"><i class="fa-solid fa-circle-info" style="margin-right: 0.35rem;"></i>No specific action items itemized. Refer to Section II for required manuscript revisions.</td></tr>`;
    }

    // Section V: Signatories
    document.getElementById('vm_recorded_by').textContent = c.recorded_by_name;
    document.getElementById('vm_approved_chair').textContent = c.approved_by_chair;
    document.getElementById('vm_print_rec_by').textContent = c.recorded_by_name;
    document.getElementById('vm_print_chair').textContent = c.approved_by_chair;
    
    let stampText = `Official CSU CEIT Defense Minutes • Filed on ${c.created_at}`;
    if (isArchived && c.archived_at) {
        stampText += ` • [Archived on ${c.archived_at}]`;
    }
    document.getElementById('vm_filing_stamp').textContent = stampText;

    // Show modal
    const modal = document.getElementById('viewMomModal');
    modal.style.display = 'block';

    // Sync URL parameter quietly
    if (window.history && window.history.pushState) {
        const u = new URL(window.location.href);
        u.searchParams.set('view_mom_id', momId);
        window.history.pushState({}, '', u.toString());
    }
}

function closeViewMomModal() {
    currentOpenMomId = null;
    const modal = document.getElementById('viewMomModal');
    if (modal) modal.style.display = 'none';
    if (window.history && window.history.pushState) {
        const u = new URL(window.location.href);
        u.searchParams.delete('view_mom_id');
        window.history.pushState({}, '', u.toString());
    }
}

function openAndPrintMomModal(momId) {
    openViewMomModal(momId);
    setTimeout(() => {
        window.print();
    }, 350);
}

function printViewMomDocument() {
    window.print();
}

function printMomRecord(momId) {
    openAndPrintMomModal(momId);
}

// Current open record triggers
function archiveCurrentViewMom() {
    if (currentOpenMomId) archiveMomRecord(currentOpenMomId);
}

function restoreCurrentViewMom() {
    if (currentOpenMomId) restoreMomRecord(currentOpenMomId);
}

// Action target tracking
let targetActionMomId = null;

// ==========================================================================
// UNIVERSITY MODAL CONTROLLERS & ACTIONS
// ==========================================================================

// 1. Single Record Deletion (Permanent Purge)
function purgeCurrentViewMom() {
    if (currentOpenMomId) purgeMomRecord(currentOpenMomId);
}

function purgeMomRecord(momId) {
    targetActionMomId = momId;
    const c = momsData[momId];
    const candName = c ? (c.student_name + (c.group_name ? ' (' + c.group_name + ')' : '')) : 'Defense Candidate';
    const docTitle = c ? c.document_title : 'Minutes of the Meeting';

    const nameEl = document.getElementById('univ_delete_cand_name');
    const titleEl = document.getElementById('univ_delete_doc_title');
    if (nameEl) nameEl.textContent = candName;
    if (titleEl) titleEl.textContent = '"' + docTitle + '"';

    closeViewMomModal();
    const modal = document.getElementById('univDeleteMomModal');
    if (modal) modal.style.display = 'block';
}

function closeUnivDeleteModal() {
    const modal = document.getElementById('univDeleteMomModal');
    if (modal) modal.style.display = 'none';
    targetActionMomId = null;
    const btn = document.getElementById('univ_delete_confirm_btn');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Yes, Delete Permanently';
    }
}

function confirmUnivDelete() {
    if (!targetActionMomId) return;

    const btn = document.getElementById('univ_delete_confirm_btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Erasing Record...';
    }

    const formData = new FormData();
    formData.append('api_action', 'purge_meeting_minutes');
    formData.append('mom_id', targetActionMomId);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(async r => {
        const text = await r.text();
        try {
            return JSON.parse(text);
        } catch(e) {
            console.error('Non-JSON server response:', text);
            throw new Error(text || 'Invalid server response.');
        }
    })
    .then(data => {
        if (data.success) {
            closeUnivDeleteModal();
            window.location.reload();
        } else {
            alert('Delete failed: ' + (data.error || 'Could not permanently delete record.'));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Yes, Delete Permanently';
            }
        }
    })
    .catch(err => {
        console.error(err);
        alert('Connection error: ' + (err.message || 'Unable to delete record.'));
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Yes, Delete Permanently';
        }
    });
}

// 2. Single Record Archival (Soft Delete)
function archiveCurrentViewMom() {
    if (currentOpenMomId) archiveMomRecord(currentOpenMomId);
}

function archiveMomRecord(momId) {
    targetActionMomId = momId;
    const c = momsData[momId];
    const candName = c ? (c.student_name + (c.group_name ? ' (' + c.group_name + ')' : '')) : 'Defense Candidate';
    const docTitle = c ? c.document_title : 'Minutes of the Meeting';

    const nameEl = document.getElementById('univ_archive_cand_name');
    const titleEl = document.getElementById('univ_archive_doc_title');
    if (nameEl) nameEl.textContent = candName;
    if (titleEl) titleEl.textContent = '"' + docTitle + '"';

    closeViewMomModal();
    const modal = document.getElementById('univArchiveMomModal');
    if (modal) modal.style.display = 'block';
}

function closeUnivArchiveModal() {
    const modal = document.getElementById('univArchiveMomModal');
    if (modal) modal.style.display = 'none';
    targetActionMomId = null;
    const btn = document.getElementById('univ_archive_confirm_btn');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-box-archive"></i> Yes, Move to Archive';
    }
}

function confirmUnivArchive() {
    if (!targetActionMomId) return;

    const btn = document.getElementById('univ_archive_confirm_btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Archiving...';
    }

    const formData = new FormData();
    formData.append('api_action', 'archive_meeting_minutes');
    formData.append('mom_id', targetActionMomId);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(async r => {
        const text = await r.text();
        try {
            return JSON.parse(text);
        } catch(e) {
            console.error('Non-JSON server response:', text);
            throw new Error(text || 'Invalid server response.');
        }
    })
    .then(data => {
        if (data.success) {
            closeUnivArchiveModal();
            window.location.reload();
        } else {
            alert('Archive failed: ' + (data.error || 'Could not archive record.'));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-box-archive"></i> Yes, Move to Archive';
            }
        }
    })
    .catch(err => {
        console.error(err);
        alert('Connection error: ' + (err.message || 'Unable to archive record.'));
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-box-archive"></i> Yes, Move to Archive';
        }
    });
}

// 3. Single Record Restoration
function restoreCurrentViewMom() {
    if (currentOpenMomId) restoreMomRecord(currentOpenMomId);
}

function restoreMomRecord(momId) {
    targetActionMomId = momId;
    const c = momsData[momId];
    const candName = c ? (c.student_name + (c.group_name ? ' (' + c.group_name + ')' : '')) : 'Defense Candidate';
    const docTitle = c ? c.document_title : 'Minutes of the Meeting';

    const nameEl = document.getElementById('univ_restore_cand_name');
    const titleEl = document.getElementById('univ_restore_doc_title');
    if (nameEl) nameEl.textContent = candName;
    if (titleEl) titleEl.textContent = '"' + docTitle + '"';

    closeViewMomModal();
    const modal = document.getElementById('univRestoreMomModal');
    if (modal) modal.style.display = 'block';
}

function closeUnivRestoreModal() {
    const modal = document.getElementById('univRestoreMomModal');
    if (modal) modal.style.display = 'none';
    targetActionMomId = null;
    const btn = document.getElementById('univ_restore_confirm_btn');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Yes, Restore Record';
    }
}

function confirmUnivRestore() {
    if (!targetActionMomId) return;

    const btn = document.getElementById('univ_restore_confirm_btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Restoring...';
    }

    const formData = new FormData();
    formData.append('api_action', 'restore_meeting_minutes');
    formData.append('mom_id', targetActionMomId);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(async r => {
        const text = await r.text();
        try {
            return JSON.parse(text);
        } catch(e) {
            console.error('Non-JSON server response:', text);
            throw new Error(text || 'Invalid server response.');
        }
    })
    .then(data => {
        if (data.success) {
            closeUnivRestoreModal();
            window.location.reload();
        } else {
            alert('Restore failed: ' + (data.error || 'Could not restore record.'));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Yes, Restore Record';
            }
        }
    })
    .catch(err => {
        console.error(err);
        alert('Connection error: ' + (err.message || 'Unable to restore record.'));
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Yes, Restore Record';
        }
    });
}

// 4. Batch Restore All Archived Records
function restoreAllMomRecords() {
    closeViewMomModal();
    const modal = document.getElementById('univRestoreAllModal');
    if (modal) modal.style.display = 'block';
}

function closeUnivRestoreAllModal() {
    const modal = document.getElementById('univRestoreAllModal');
    if (modal) modal.style.display = 'none';
    const btn = document.getElementById('univ_restore_all_confirm_btn');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Yes, Restore All';
    }
}

function confirmUnivRestoreAll() {
    const btn = document.getElementById('univ_restore_all_confirm_btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Restoring All Records...';
    }

    const formData = new FormData();
    formData.append('api_action', 'restore_all_meeting_minutes');

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(async r => {
        const text = await r.text();
        try {
            return JSON.parse(text);
        } catch(e) {
            console.error('Non-JSON server response:', text);
            throw new Error(text || 'Invalid server response.');
        }
    })
    .then(data => {
        if (data.success) {
            closeUnivRestoreAllModal();
            window.location.reload();
        } else {
            alert('Restore All failed: ' + (data.error || 'Could not restore records.'));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Yes, Restore All';
            }
        }
    })
    .catch(err => {
        console.error(err);
        alert('Connection error: ' + (err.message || 'Unable to restore records.'));
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Yes, Restore All';
        }
    });
}

// 5. Batch Delete All Archived Records (Mass Wipe)
function purgeAllMomRecords() {
    closeViewMomModal();
    const modal = document.getElementById('univDeleteAllModal');
    if (modal) modal.style.display = 'block';
}

function closeUnivDeleteAllModal() {
    const modal = document.getElementById('univDeleteAllModal');
    if (modal) modal.style.display = 'none';
    const btn = document.getElementById('univ_delete_all_confirm_btn');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Yes, Delete All Permanently';
    }
}

function confirmUnivDeleteAll() {
    const btn = document.getElementById('univ_delete_all_confirm_btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Permanently Wiping Vault...';
    }

    const formData = new FormData();
    formData.append('api_action', 'purge_all_meeting_minutes');

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(async r => {
        const text = await r.text();
        try {
            return JSON.parse(text);
        } catch(e) {
            console.error('Non-JSON server response:', text);
            throw new Error(text || 'Invalid server response.');
        }
    })
    .then(data => {
        if (data.success) {
            closeUnivDeleteAllModal();
            window.location.reload();
        } else {
            alert('Delete All failed: ' + (data.error || 'Could not wipe records.'));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Yes, Delete All Permanently';
            }
        }
    })
    .catch(err => {
        console.error(err);
        alert('Connection error: ' + (err.message || 'Unable to delete records.'));
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-trash-can"></i> Yes, Delete All Permanently';
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// Close modals on outside backdrop click
window.addEventListener('click', function(e) {
    const viewModal = document.getElementById('viewMomModal');
    if (e.target === viewModal) closeViewMomModal();

    const delModal = document.getElementById('univDeleteMomModal');
    if (e.target === delModal) closeUnivDeleteModal();

    const arcModal = document.getElementById('univArchiveMomModal');
    if (e.target === arcModal) closeUnivArchiveModal();

    const resModal = document.getElementById('univRestoreMomModal');
    if (e.target === resModal) closeUnivRestoreModal();

    const resAllModal = document.getElementById('univRestoreAllModal');
    if (e.target === resAllModal) closeUnivRestoreAllModal();

    const delAllModal = document.getElementById('univDeleteAllModal');
    if (e.target === delAllModal) closeUnivDeleteAllModal();
});

// Auto-open modal if view_mom_id is present in URL, and check view mode
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    const viewParam = params.get('view');
    if (tabParam === 'archive' || viewParam === 'archived' || params.get('vault') === 'archived') {
        switchMomView('archived', false);
    } else {
        switchMomView('active', false);
    }

    const viewId = params.get('view_mom_id');
    if (viewId && momsData[viewId]) {
        openViewMomModal(parseInt(viewId, 10));
    }
});
</script>
