<?php
// CEIT FACULTY ADVISORY PROFILE & EXPERTISE MANAGEMENT
// University-grade styling aligned with CSU Cabadbaran CEIT portal standards

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    return;
}

$uid = (int)$_SESSION['user_id'];

// Fetch current adviser profile data
$prof_q = $conn->query("SELECT * FROM adviser_profile WHERE user_id = $uid LIMIT 1");
$profile = $prof_q ? $prof_q->fetch_assoc() : [];

$current_expertise = trim($profile['expertise'] ?? '');
$current_capacity = max(1, (int)($profile['capacity'] ?? 5));
$current_dept = trim($profile['department'] ?? 'Information Technology');
$current_degree = trim($profile['academic_degree'] ?? '');
$current_grad_year = trim($profile['graduation_year'] ?? '');
$current_scholar_url = trim($profile['google_scholar_url'] ?? '');
$current_pubs = trim($profile['published_papers'] ?? '');
$current_prev_titles = trim($profile['previous_advised_titles'] ?? '');
$current_last_synced = trim($profile['publications_last_synced'] ?? '');
$sys_advised_titles = get_adviser_supervised_titles_from_system($conn, $uid);
if (empty($current_prev_titles) && !empty($sys_advised_titles)) {
    $current_prev_titles = implode("\n", $sys_advised_titles);
}

// Compute live active advisee workload
$adv_count_q = $conn->query("
    SELECT COUNT(DISTINCT COALESCE(NULLIF(group_id, 0), student_id)) as c 
    FROM match_request 
    WHERE adviser_id = $uid AND status = 'accepted'
");
$active_groups = (int)($adv_count_q ? $adv_count_q->fetch_assoc()['c'] : 0);
$available_slots = max(0, $current_capacity - $active_groups);
$usage_pct = min(100, round(($active_groups / $current_capacity) * 100));

// Determine workload status badge
if ($available_slots == 0) {
    $status_badge = '<span class="status-pill status-full"><i class="fa-solid fa-ban"></i> At Max Capacity</span>';
    $bar_color = '#EF4444';
} elseif ($available_slots <= 1) {
    $status_badge = '<span class="status-pill status-limited"><i class="fa-solid fa-triangle-exclamation"></i> 1 Slot Remaining</span>';
    $bar_color = '#F59E0B';
} else {
    $status_badge = '<span class="status-pill status-open"><i class="fa-solid fa-circle-check"></i> Accepting Advisees</span>';
    $bar_color = '#10B981';
}
?>

<div class="expertise-univ-wrapper">
    <!-- ── Official University Hero Banner ── -->
    <div class="univ-banner">
        <div class="univ-banner-content">
            <div class="univ-banner-badge-row">
                <span class="badge-campus"><i class="fa-solid fa-building-columns"></i> CSU Cabadbaran</span>
                <span class="badge-dept"><i class="fa-solid fa-microchip"></i> College of Engineering & IT</span>
                <span class="badge-ai"><i class="fa-solid fa-robot"></i> CEIT AI Matchmaking</span>
            </div>
            <h1 class="univ-banner-title">
                <i class="fa-solid fa-tags" style="color: #F59E0B;"></i>
                Faculty Advisory Profile & Research Capacity
            </h1>
            <p class="univ-banner-desc">
                Configure your research specializations, technical tags, and student capstone group mentorship quota. The CEIT AI Matchmaker analyzes these parameters to accurately pair your expertise with compatible student capstone proposals.
            </p>
        </div>
        <div class="univ-banner-watermark">
            <i class="fa-solid fa-brain"></i>
        </div>
    </div>

    <!-- ── Capacity & Mentorship Metrics ── -->
    <div class="stats-row">
        <div class="metric-card">
            <div class="metric-icon icon-emerald">
                <i class="fa-solid fa-users-gear"></i>
            </div>
            <div class="metric-info">
                <div class="metric-value"><?php echo $active_groups; ?> <span class="metric-unit">Active Groups</span></div>
                <div class="metric-label">Current Mentorship Load</div>
                <div class="metric-subtext">Accepted capstone teams</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon icon-amber">
                <i class="fa-solid fa-gauge-high"></i>
            </div>
            <div class="metric-info">
                <div class="metric-value"><?php echo $current_capacity; ?> <span class="metric-unit">Groups Max</span></div>
                <div class="metric-label">Semester Advisory Quota</div>
                <div class="metric-subtext">Standard: 3 to 6 groups</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon icon-blue">
                <i class="fa-solid fa-door-open"></i>
            </div>
            <div class="metric-info">
                <div class="metric-value"><?php echo $available_slots; ?> <span class="metric-unit">Slots Free</span></div>
                <div class="metric-label">Matchmaking Availability</div>
                <div class="metric-subtext"><?php echo $status_badge; ?></div>
            </div>
        </div>
    </div>

    <!-- ── Live Quota Meter Bar ── -->
    <div class="quota-progress-card">
        <div class="quota-progress-header">
            <span class="quota-progress-title">
                <i class="fa-solid fa-chart-pie" style="color: #047857;"></i> Mentorship Capacity Allocation:
                <strong><?php echo $active_groups; ?> of <?php echo $current_capacity; ?> Groups (<?php echo $usage_pct; ?>%)</strong>
            </span>
            <span class="quota-slots-badge">
                <?php if ($available_slots > 0): ?>
                    <i class="fa-solid fa-circle-check" style="color: #059669;"></i> <?php echo $available_slots; ?> More Group Slot<?php echo $available_slots > 1 ? 's' : ''; ?> Available
                <?php else: ?>
                    <i class="fa-solid fa-triangle-exclamation" style="color: #DC2626;"></i> Quota Reached — No New Auto-Matches
                <?php endif; ?>
            </span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="capacityProgressBar" style="width: <?php echo $usage_pct; ?>%; background: <?php echo $bar_color; ?>;"></div>
        </div>
    </div>

    <!-- ── Main Grid Layout: Form (Left) & AI Engine Insights (Right) ── -->
    <div class="univ-form-grid">
        
        <!-- Left: Form Controls Card -->
        <div class="card-form-container">
            <form method="POST" action="action.php" id="expertiseForm">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="card-form-header">
                    <div class="card-form-header-title">
                        <i class="fa-solid fa-sliders" style="color: #047857;"></i>
                        <span>Specializations & Quota Settings</span>
                    </div>
                    <span class="card-form-badge">
                        <i class="fa-solid fa-shield-halved"></i> Official CSU Academic Record
                    </span>
                </div>

                <div class="card-form-body">
                    
                    <!-- Expertise Tags Section -->
                    <div class="field-section">
                        <div class="field-label-row">
                            <label class="field-label" for="expertiseInput">
                                <i class="fa-solid fa-tags" style="color: #059669;"></i>
                                Research & Technical Expertise Domains
                                <span class="required-star">*</span>
                            </label>
                            <span class="tag-counter" id="tagCounterBadge">0 Tags Active</span>
                        </div>
                        
                        <p class="field-hint">
                            Enter your technical fields, methodologies, and capstone areas separated by commas. These keywords are utilized by the CEIT AI engine to evaluate proposal similarity with student abstracts.
                        </p>

                        <!-- Live Interactive Tag Cloud -->
                        <div class="interactive-tags-box" id="tagsPillContainer">
                            <!-- Populated dynamically via JS -->
                        </div>

                        <!-- Real Textarea (Bound to Backend) -->
                        <div class="textarea-wrapper">
                            <textarea 
                                name="expertise" 
                                id="expertiseInput" 
                                rows="3" 
                                class="univ-textarea" 
                                placeholder="e.g., Machine Learning, Internet of Things, Embedded Systems, Smart Agriculture, Computer Vision"
                                required><?php echo htmlspecialchars($current_expertise); ?></textarea>
                        </div>
                        
                        <!-- Quick Add University Tags -->
                        <div class="quick-add-container">
                            <div class="quick-add-label">
                                <i class="fa-solid fa-wand-magic-sparkles" style="color: #D97706;"></i>
                                Click to Quick-Add CEIT Specializations:
                            </div>
                            <div class="quick-tags-wrap">
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Machine Learning')">+ Machine Learning</button>
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Internet of Things')">+ IoT</button>
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Computer Vision')">+ Computer Vision</button>
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Smart Agriculture')">+ Smart Agriculture</button>
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Embedded Systems')">+ Embedded Systems</button>
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Robotics & Automation')">+ Robotics</button>
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Cybersecurity')">+ Cybersecurity</button>
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Mobile & Web Applications')">+ Mobile/Web Apps</button>
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Data Science & Analytics')">+ Data Analytics</button>
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Renewable Energy Optimization')">+ Solar/Renewable Energy</button>
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Cloud Computing & DevOps')">+ Cloud Computing</button>
                                <button type="button" class="quick-tag-btn" onclick="addQuickTag('Blockchain')">+ Blockchain</button>
                            </div>
                        </div>
                    </div>

                    <div class="divider-line"></div>

                    <!-- Google Scholar Integration & Published Research Papers Section -->
                    <div class="field-section" style="background: #EFF6FF; border: 1.5px solid #BFDBFE; border-radius: 12px; padding: 1.15rem 1.25rem;">
                        <div class="field-label-row" style="flex-wrap: wrap; gap: 0.4rem;">
                            <label class="field-label" for="googleScholarInput" style="color: #1E3A8A;">
                                <i class="fa-solid fa-graduation-cap" style="color: #2563EB;"></i>
                                Google Scholar Integration &amp; Publication Retrieval
                            </label>
                            <?php if(!empty($current_last_synced)): ?>
                                <span style="background: #DBEAFE; color: #1E40AF; border: 1px solid #93C5FD; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 999px;">
                                    <i class="fa-solid fa-circle-check"></i> Last Synced: <?php echo date('M d, Y h:i A', strtotime($current_last_synced)); ?>
                                </span>
                            <?php else: ?>
                                <span style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-size: 0.72rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 999px;">
                                    <i class="fa-solid fa-bolt"></i> No CSV Required — Direct Scholar Sync
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="field-hint" style="color: #334155;">
                            Link your <strong>Google Scholar Profile URL or Author ID</strong> to automatically retrieve your published research papers and identify your research specializations—eliminating the need to submit manual CSV files.
                        </p>
                        <div style="display: flex; gap: 0.55rem; flex-wrap: wrap;">
                            <input type="text" name="google_scholar_url" id="googleScholarInput" value="<?php echo htmlspecialchars($current_scholar_url); ?>" placeholder="e.g., https://scholar.google.com/citations?user=XXXXXXXX or Author Name" class="univ-select" style="flex: 1; min-width: 240px; border-color: #93C5FD;">
                            <button type="button" id="btnFacultyScholarSync" onclick="syncFacultyGoogleScholar()" style="background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); color: #FFFFFF; border: none; border-radius: 10px; padding: 0.7rem 1.15rem; font-size: 0.84rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; white-space: nowrap; box-shadow: 0 3px 10px rgba(37,99,235,0.25);">
                                <i class="fa-solid fa-cloud-arrow-down"></i> Sync from Google Scholar
                            </button>
                        </div>
                        <div id="facultyScholarSyncStatus" style="display: none; font-size: 0.78rem; margin-top: 0.35rem; font-weight: 600;"></div>
                    </div>

                    <!-- Published Research Papers -->
                    <div class="field-section">
                        <div class="field-label-row" style="flex-wrap: wrap; gap: 0.4rem;">
                            <label class="field-label" for="publishedPapersInput">
                                <i class="fa-solid fa-book-journal-whills" style="color: #059669;"></i>
                                Published Research Papers &amp; Journal Articles
                            </label>
                            <button type="button" onclick="autoIdentifyFacultySpecializations()" style="background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; border-radius: 8px; padding: 0.3rem 0.75rem; font-size: 0.76rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> Identify Specializations from Publications
                            </button>
                        </div>
                        <p class="field-hint">
                            Your published research papers are analyzed by the CEIT AI engine to verify your research specializations and improve student-adviser recommendations.
                        </p>
                        <textarea name="published_papers" id="publishedPapersInput" rows="4" class="univ-textarea" placeholder="1. Title of Published Research Paper (Year) — Journal / Conference&#10;2. Title of Second Research Paper (Year) — Venue"><?php echo htmlspecialchars($current_pubs); ?></textarea>
                        <label style="display: inline-flex; align-items: center; gap: 0.45rem; font-size: 0.78rem; color: #334155; font-weight: 600; cursor: pointer; margin-top: 0.15rem;">
                            <input type="checkbox" name="auto_identify_specializations" value="1" checked style="accent-color: #047857;">
                            Automatically update and enrich my Research Specialization tags from my publications &amp; supervised titles upon saving
                        </label>
                    </div>

                    <!-- Previous Research Titles Supervised / Advised -->
                    <div class="field-section">
                        <div class="field-label-row" style="flex-wrap: wrap; gap: 0.4rem;">
                            <label class="field-label" for="previousAdvisedTitlesInput">
                                <i class="fa-solid fa-folder-tree" style="color: #D97706;"></i>
                                Previous Research Titles Supervised / Advised
                            </label>
                            <?php if(!empty($sys_advised_titles)): ?>
                                <button type="button" onclick="importSystemAdvisedTitles()" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; border-radius: 8px; padding: 0.28rem 0.7rem; font-size: 0.74rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <i class="fa-solid fa-file-import"></i> Import Advisee Titles from System (<?php echo count($sys_advised_titles); ?>)
                                </button>
                            <?php endif; ?>
                        </div>
                        <p class="field-hint">
                            Record previous capstone titles you have supervised or advised (one title per line). These titles help students see your advisory track record and support AI adviser matching.
                        </p>
                        <textarea name="previous_advised_titles" id="previousAdvisedTitlesInput" rows="4" class="univ-textarea" placeholder="e.g.&#10;IoT-Based Smart Farm Irrigation and Soil Nutrient Monitoring System (2024)&#10;AI-Powered Student Academic Performance Early Warning Portal (2023)"><?php echo htmlspecialchars($current_prev_titles); ?></textarea>
                    </div>

                    <div class="divider-line"></div>

                    <!-- Academic Degree & Graduation Year -->
                    <div class="fields-two-col">
                        <div class="field-item">
                            <label class="field-label" for="academicDegreeInput">
                                <i class="fa-solid fa-user-graduate" style="color: #059669;"></i>
                                Highest Academic Degree / Title
                            </label>
                            <input type="text" name="academic_degree" id="academicDegreeInput" value="<?php echo htmlspecialchars($current_degree); ?>" placeholder="e.g., MIT, MSIT, MSCS, PhD in IT" class="univ-select">
                            <span class="field-sub-note">Displayed on your faculty advisory profile</span>
                        </div>
                        <div class="field-item">
                            <label class="field-label" for="graduationYearInput">
                                <i class="fa-solid fa-calendar-check" style="color: #059669;"></i>
                                Faculty Graduation Year
                            </label>
                            <input type="text" name="graduation_year" id="graduationYearInput" value="<?php echo htmlspecialchars($current_grad_year); ?>" placeholder="e.g., 2019" class="univ-select">
                            <span class="field-sub-note">Year graduated from highest degree (if required)</span>
                        </div>
                    </div>

                    <!-- Department & Capacity Section -->
                    <div class="fields-two-col">
                        
                        <!-- Department -->
                        <div class="field-item">
                            <label class="field-label" for="departmentSelect">
                                <i class="fa-solid fa-building-user" style="color: #059669;"></i>
                                Academic Department
                            </label>
                            <select name="department" id="departmentSelect" class="univ-select">
                                <option value="Information Technology" <?php echo $current_dept === 'Information Technology' ? 'selected' : ''; ?>>Information Technology</option>
                                <option value="Computer Engineering" <?php echo $current_dept === 'Computer Engineering' ? 'selected' : ''; ?>>Computer Engineering</option>
                                <option value="Electrical Engineering" <?php echo $current_dept === 'Electrical Engineering' ? 'selected' : ''; ?>>Electrical Engineering</option>
                            </select>
                            <span class="field-sub-note">Designated collegiate academic branch</span>
                        </div>

                        <!-- Capacity Stepper -->
                        <div class="field-item">
                            <label class="field-label" for="capacityInput">
                                <i class="fa-solid fa-user-group" style="color: #059669;"></i>
                                Maximum Group Capacity
                                <span class="required-star">*</span>
                            </label>
                            
                            <div class="capacity-stepper-box">
                                <button type="button" class="stepper-btn" onclick="adjustCapacity(-1)">
                                    <i class="fa-solid fa-minus"></i>
                                </button>
                                <input 
                                    type="number" 
                                    name="capacity" 
                                    id="capacityInput" 
                                    value="<?php echo $current_capacity; ?>" 
                                    min="1" 
                                    max="15" 
                                    class="stepper-input" 
                                    oninput="onCapacityChange()"
                                    required>
                                <button type="button" class="stepper-btn" onclick="adjustCapacity(1)">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                            <span class="field-sub-note">CSU standard policy recommends 3 to 6 groups</span>
                        </div>

                    </div>

                    <!-- Action Bar -->
                    <div class="form-action-row">
                        <div class="last-saved-note">
                            <i class="fa-regular fa-clock"></i> Changes update your matchmaking score immediately
                        </div>
                        <button type="submit" class="btn-save-univ">
                            <i class="fa-solid fa-floppy-disk"></i> Save & Update Profile
                        </button>
                    </div>

                </div>
            </form>
        </div>

        <!-- Right: AI Matchmaking Insights & Guidelines Panel -->
        <div class="side-insights-panel">
            
            <!-- Card 1: AI Matchmaking Insight -->
            <div class="insight-card">
                <div class="insight-card-header">
                    <div class="insight-icon-bubble ai-bubble">
                        <i class="fa-solid fa-brain"></i>
                    </div>
                    <div>
                        <h3 class="insight-title">CEIT AI Matchmaking</h3>
                        <div class="insight-sub">How student pairing operates</div>
                    </div>
                </div>
                <div class="insight-card-body">
                    <div class="insight-step">
                        <div class="step-num">1</div>
                        <div class="step-text">
                            <strong>Proposal Extraction:</strong> Students submit their title, abstract, and technical keywords during Title Defense registration.
                        </div>
                    </div>
                    <div class="insight-step">
                        <div class="step-num">2</div>
                        <div class="step-text">
                            <strong>Semantic Compatibility:</strong> The AI calculates weighted overlap with your active specialization tags.
                        </div>
                    </div>
                    <div class="insight-step">
                        <div class="step-num">3</div>
                        <div class="step-text">
                            <strong>Quota Balancing:</strong> If your active load equals your maximum capacity, new automated requests are held to prevent overload.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Faculty Advisory Standards -->
            <div class="insight-card">
                <div class="insight-card-header">
                    <div class="insight-icon-bubble univ-bubble">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </div>
                    <div>
                        <h3 class="insight-title">Faculty Advisory Charter</h3>
                        <div class="insight-sub">CSU Cabadbaran Guidelines</div>
                    </div>
                </div>
                <div class="insight-card-body">
                    <ul class="guidelines-list">
                        <li>
                            <i class="fa-solid fa-check-circle"></i>
                            Conduct regular milestone consultations and review draft manuscripts.
                        </li>
                        <li>
                            <i class="fa-solid fa-check-circle"></i>
                            Monitor Oral Defense readiness, committee question compliance, and MOM revisions.
                        </li>
                        <li>
                            <i class="fa-solid fa-check-circle"></i>
                            Maintain accurate capacity so college committees can balance faculty advisory assignments.
                        </li>
                    </ul>
                </div>
                <div class="insight-card-footer">
                    <a href="faculty_dashboard.php?tab=advisees" class="quick-link-btn">
                        <i class="fa-solid fa-arrow-right"></i> View Assigned Advisees (<?php echo $active_groups; ?>)
                    </a>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
// Live Tag Chip Management
const rawInput = document.getElementById('expertiseInput');
const container = document.getElementById('tagsPillContainer');
const counterBadge = document.getElementById('tagCounterBadge');
const capacityInput = document.getElementById('capacityInput');
const capacityBar = document.getElementById('capacityProgressBar');
const activeGroupsCount = <?php echo $active_groups; ?>;

function parseTags(text) {
    if (!text) return [];
    return text.split(',')
        .map(t => t.trim())
        .filter(t => t.length > 0);
}

function renderTags() {
    if (!rawInput || !container) return;
    const tags = parseTags(rawInput.value);
    container.innerHTML = '';

    if (tags.length === 0) {
        container.innerHTML = '<span class="empty-tags-hint"><i class="fa-regular fa-lightbulb"></i> No tags added yet. Type below or click the quick-add buttons.</span>';
        if (counterBadge) counterBadge.innerText = '0 Tags Active';
        return;
    }

    if (counterBadge) {
        counterBadge.innerText = `${tags.length} Tag${tags.length > 1 ? 's' : ''} Active`;
    }

    tags.forEach((tag, idx) => {
        const chip = document.createElement('span');
        chip.className = 'univ-tag-chip';
        chip.innerHTML = `
            <i class="fa-solid fa-tag tag-chip-icon"></i>
            <span class="tag-chip-text">${escapeHtml(tag)}</span>
            <button type="button" class="tag-chip-remove" onclick="removeTag(${idx})" title="Remove tag">&times;</button>
        `;
        container.appendChild(chip);
    });
}

function escapeHtml(str) {
    return str.replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

function removeTag(indexToRemove) {
    const tags = parseTags(rawInput.value);
    if (indexToRemove >= 0 && indexToRemove < tags.length) {
        tags.splice(indexToRemove, 1);
        rawInput.value = tags.join(', ');
        renderTags();
    }
}

function addQuickTag(tagName) {
    let tags = parseTags(rawInput.value);
    // Case-insensitive check
    const exists = tags.some(t => t.toLowerCase() === tagName.toLowerCase());
    if (!exists) {
        tags.push(tagName);
        rawInput.value = tags.join(', ');
        renderTags();
        rawInput.focus();
    }
}

function adjustCapacity(delta) {
    if (!capacityInput) return;
    let val = parseInt(capacityInput.value, 10) || 5;
    val = Math.max(1, Math.min(15, val + delta));
    capacityInput.value = val;
    onCapacityChange();
}

function onCapacityChange() {
    if (!capacityInput || !capacityBar) return;
    let cap = parseInt(capacityInput.value, 10) || 1;
    if (cap < 1) cap = 1;
    const pct = Math.min(100, Math.round((activeGroupsCount / cap) * 100));
    capacityBar.style.width = pct + '%';
    if (pct >= 100) {
        capacityBar.style.background = '#EF4444';
    } else if (pct >= 80) {
        capacityBar.style.background = '#F59E0B';
    } else {
        capacityBar.style.background = '#10B981';
    }
}

// Attach listener to textarea
if (rawInput) {
    rawInput.addEventListener('input', renderTags);
}

const systemSupervisedTitles = <?php echo json_encode(array_values($sys_advised_titles ?? [])); ?>;

function syncFacultyGoogleScholar() {
    const scholarInput = document.getElementById('googleScholarInput');
    const pubsInput = document.getElementById('publishedPapersInput');
    const deptSelect = document.getElementById('departmentSelect');
    const btn = document.getElementById('btnFacultyScholarSync');
    const statusEl = document.getElementById('facultyScholarSyncStatus');

    const scholarVal = scholarInput ? scholarInput.value.trim() : '';
    const facName = <?php echo json_encode($_SESSION['name'] ?? ''); ?>;

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Syncing Google Scholar...';
    }
    if (statusEl) {
        statusEl.style.display = 'block';
        statusEl.style.color = '#1E40AF';
        statusEl.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Retrieving publications from Google Scholar &amp; identifying research specializations...';
    }

    const fd = new FormData();
    fd.append('action', 'sync_google_scholar');
    fd.append('google_scholar_url', scholarVal);
    fd.append('faculty_name', facName);
    fd.append('department', deptSelect ? deptSelect.value : '');
    fd.append('existing_expertise', rawInput ? rawInput.value : '');
    fd.append('user_id', '<?php echo (int)$uid; ?>');
    fd.append('save_to_profile', '1');

    fetch('api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i> Sync from Google Scholar';
            }
            if (data && data.ok) {
                if (pubsInput && data.formatted_publications) {
                    pubsInput.value = data.formatted_publications;
                }
                if (rawInput && data.merged_specializations) {
                    rawInput.value = data.merged_specializations;
                    renderTags();
                }
                if (scholarInput && !scholarInput.value.trim() && data.google_scholar_url) {
                    scholarInput.value = data.google_scholar_url;
                }
                if (statusEl) {
                    const count = Array.isArray(data.publications) ? data.publications.length : 0;
                    const idTags = Array.isArray(data.identified_specializations) && data.identified_specializations.length > 0
                        ? data.identified_specializations.join(', ')
                        : 'Updated specialization tags';
                    statusEl.style.color = '#065F46';
                    statusEl.innerHTML = `<i class="fa-solid fa-circle-check" style="color:#059669;"></i> Synced <strong>${count} publication(s)</strong> via ${data.source || 'Google Scholar'} &amp; identified specializations: <strong>${idTags}</strong>`;
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
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i> Sync from Google Scholar';
            }
            if (statusEl) {
                statusEl.style.color = '#DC2626';
                statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Network error while syncing Google Scholar.';
            }
        });
}

function autoIdentifyFacultySpecializations() {
    const pubsInput = document.getElementById('publishedPapersInput');
    const prevInput = document.getElementById('previousAdvisedTitlesInput');
    const statusEl = document.getElementById('facultyScholarSyncStatus');
    const pubs = pubsInput ? pubsInput.value.trim() : '';
    const prev = prevInput ? prevInput.value.trim() : '';

    if (!pubs && !prev) {
        alert('Please enter or sync Published Research Papers or Previously Supervised Research Titles first.');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'extract_specializations');
    fd.append('published_papers', pubs);
    fd.append('previous_advised_titles', prev);
    fd.append('existing_expertise', rawInput ? rawInput.value : '');

    fetch('api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.ok) {
                if (rawInput && data.merged_expertise) {
                    rawInput.value = data.merged_expertise;
                    renderTags();
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

function importSystemAdvisedTitles() {
    const prevInput = document.getElementById('previousAdvisedTitlesInput');
    if (!prevInput || !Array.isArray(systemSupervisedTitles) || systemSupervisedTitles.length === 0) return;
    const existingLines = prevInput.value.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    systemSupervisedTitles.forEach(t => {
        if (!existingLines.some(e => e.toLowerCase() === t.toLowerCase())) {
            existingLines.push(t);
        }
    });
    prevInput.value = existingLines.join('\n');
    autoIdentifyFacultySpecializations();
}

// Initial render
document.addEventListener('DOMContentLoaded', function() {
    renderTags();
});
renderTags();
</script>

<style>
/* ── University Faculty Advisory Profile Styles ── */
.expertise-univ-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Hero Banner ── */
.univ-banner {
    background: linear-gradient(135deg, #065F46 0%, #047857 55%, #022C22 100%);
    border-radius: 14px;
    padding: 1.5rem 2rem;
    color: #FFFFFF;
    box-shadow: 0 10px 25px -5px rgba(4, 120, 87, 0.35);
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.univ-banner-content {
    position: relative;
    z-index: 2;
    max-width: 820px;
}

.univ-banner-badge-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.65rem;
}

.badge-campus, .badge-dept, .badge-ai {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.25rem 0.65rem;
    border-radius: 999px;
    letter-spacing: 0.2px;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.badge-campus {
    background: rgba(255, 255, 255, 0.15);
    color: #FFFFFF;
    border: 1px solid rgba(255, 255, 255, 0.25);
}

.badge-dept {
    background: rgba(245, 158, 11, 0.2);
    color: #FDE68A;
    border: 1px solid rgba(245, 158, 11, 0.4);
}

.badge-ai {
    background: rgba(16, 185, 129, 0.25);
    color: #A7F3D0;
    border: 1px solid rgba(16, 185, 129, 0.4);
}

.univ-banner-title {
    font-size: 1.45rem;
    font-weight: 800;
    color: #FFFFFF;
    margin: 0 0 0.45rem 0;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    letter-spacing: -0.3px;
}

.univ-banner-desc {
    font-size: 0.88rem;
    color: #D1FAE5;
    line-height: 1.5;
    margin: 0;
    font-weight: 400;
}

.univ-banner-watermark {
    position: absolute;
    right: 1.5rem;
    bottom: -1.5rem;
    font-size: 8rem;
    color: rgba(255, 255, 255, 0.05);
    pointer-events: none;
    z-index: 1;
}

/* ── Metric Cards ── */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.metric-card {
    background: #FFFFFF;
    border-radius: 12px;
    padding: 1.15rem 1.25rem;
    border: 1.5px solid #E2E8F0;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
    transition: all 0.2s ease;
}

.metric-card:hover {
    border-color: #CBD5E1;
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(0, 0, 0, 0.06);
}

.metric-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.icon-emerald {
    background: #ECFDF5;
    color: #047857;
}

.icon-amber {
    background: #FFFBEB;
    color: #D97706;
}

.icon-blue {
    background: #EFF6FF;
    color: #1D4ED8;
}

.metric-info {
    flex: 1;
}

.metric-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #0F172A;
    line-height: 1.1;
    margin-bottom: 0.2rem;
}

.metric-unit {
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748B;
}

.metric-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.metric-subtext {
    font-size: 0.72rem;
    color: #64748B;
    margin-top: 0.15rem;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
}

.status-open {
    background: #ECFDF5;
    color: #047857;
    border: 1px solid #A7F3D0;
}

.status-limited {
    background: #FFFBEB;
    color: #B45309;
    border: 1px solid #FDE68A;
}

.status-full {
    background: #FEF2F2;
    color: #B91C1C;
    border: 1px solid #FECACA;
}

/* ── Live Quota Meter ── */
.quota-progress-card {
    background: #FFFFFF;
    border-radius: 12px;
    padding: 1rem 1.35rem;
    border: 1.5px solid #E2E8F0;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
}

.quota-progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.65rem;
    font-size: 0.85rem;
    color: #1E293B;
}

.quota-progress-title {
    font-weight: 600;
}

.quota-slots-badge {
    font-size: 0.76rem;
    font-weight: 700;
    color: #475569;
}

.progress-track {
    width: 100%;
    height: 10px;
    background: #E2E8F0;
    border-radius: 999px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.4s ease, background 0.3s ease;
}

/* ── Grid Layout ── */
.univ-form-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 1.25rem;
    align-items: start;
}

/* ── Form Card ── */
.card-form-container {
    background: #FFFFFF;
    border-radius: 14px;
    border: 1.5px solid #E2E8F0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.card-form-header {
    background: #F8FAFC;
    padding: 1rem 1.5rem;
    border-bottom: 1.5px solid #E2E8F0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-form-header-title {
    font-size: 1rem;
    font-weight: 700;
    color: #0F172A;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-form-badge {
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748B;
    background: #EDF2F7;
    padding: 0.25rem 0.55rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.card-form-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.35rem;
}

.field-section {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.field-label-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.field-label {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1E293B;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.required-star {
    color: #EF4444;
}

.tag-counter {
    font-size: 0.72rem;
    font-weight: 700;
    color: #047857;
    background: #ECFDF5;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    border: 1px solid #A7F3D0;
}

.field-hint {
    font-size: 0.8rem;
    color: #64748B;
    margin: 0;
    line-height: 1.45;
}

/* ── Interactive Tags Box ── */
.interactive-tags-box {
    min-height: 48px;
    background: #F8FAFC;
    border: 1.5px dashed #CBD5E1;
    border-radius: 10px;
    padding: 0.6rem 0.8rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
    transition: all 0.2s ease;
}

.interactive-tags-box:hover {
    border-color: #047857;
}

.empty-tags-hint {
    color: #94A3B8;
    font-size: 0.8rem;
    font-style: italic;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.univ-tag-chip {
    background: #FFFFFF;
    color: #047857;
    border: 1.5px solid #A7F3D0;
    border-radius: 8px;
    padding: 0.3rem 0.65rem;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    box-shadow: 0 1px 3px rgba(4, 120, 87, 0.08);
    animation: popIn 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes popIn {
    from { opacity: 0; transform: scale(0.85); }
    to { opacity: 1; transform: scale(1); }
}

.tag-chip-icon {
    font-size: 0.72rem;
    color: #059669;
}

.tag-chip-text {
    letter-spacing: 0.2px;
}

.tag-chip-remove {
    background: transparent;
    border: none;
    color: #94A3B8;
    font-size: 1.1rem;
    line-height: 1;
    cursor: pointer;
    padding: 0;
    margin-left: 0.15rem;
    display: flex;
    align-items: center;
    transition: color 0.15s ease;
}

.tag-chip-remove:hover {
    color: #EF4444;
}

/* ── Textarea ── */
.univ-textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1.5px solid #CBD5E1;
    border-radius: 10px;
    padding: 0.75rem 0.95rem;
    font-family: inherit;
    font-size: 0.88rem;
    color: #1E293B;
    line-height: 1.5;
    outline: none;
    transition: all 0.2s ease;
    resize: vertical;
}

.univ-textarea:focus {
    border-color: #047857;
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
}

/* ── Quick Add Tags ── */
.quick-add-container {
    background: #F8FAFC;
    border-radius: 10px;
    padding: 0.85rem 1rem;
    border: 1px solid #E2E8F0;
}

.quick-add-label {
    font-size: 0.76rem;
    font-weight: 700;
    color: #475569;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.quick-tags-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.quick-tag-btn {
    background: #FFFFFF;
    color: #334155;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    padding: 0.28rem 0.55rem;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.15s ease;
}

.quick-tag-btn:hover {
    background: #ECFDF5;
    color: #047857;
    border-color: #059669;
    transform: translateY(-1px);
}

.divider-line {
    height: 1px;
    background: #E2E8F0;
    margin: 0.25rem 0;
}

/* ── Two Column Inputs ── */
.fields-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.field-item {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.univ-select {
    width: 100%;
    box-sizing: border-box;
    border: 1.5px solid #CBD5E1;
    border-radius: 10px;
    padding: 0.7rem 0.9rem;
    font-family: inherit;
    font-size: 0.88rem;
    color: #1E293B;
    background: #FFFFFF;
    outline: none;
    transition: all 0.2s ease;
}

.univ-select:focus {
    border-color: #047857;
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
}

/* ── Stepper ── */
.capacity-stepper-box {
    display: flex;
    align-items: center;
    border: 1.5px solid #CBD5E1;
    border-radius: 10px;
    background: #FFFFFF;
    overflow: hidden;
    height: 44px;
    transition: border-color 0.2s ease;
}

.capacity-stepper-box:focus-within {
    border-color: #047857;
    box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.12);
}

.stepper-btn {
    width: 44px;
    height: 100%;
    background: #F1F5F9;
    border: none;
    color: #475569;
    font-size: 0.85rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.stepper-btn:hover {
    background: #047857;
    color: #FFFFFF;
}

.stepper-input {
    flex: 1;
    border: none;
    text-align: center;
    font-size: 1.05rem;
    font-weight: 800;
    color: #0F172A;
    font-family: inherit;
    outline: none;
    -moz-appearance: textfield;
}

.stepper-input::-webkit-outer-spin-button,
.stepper-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.field-sub-note {
    font-size: 0.72rem;
    color: #64748B;
    margin-top: 0.1rem;
}

/* ── Action Row ── */
.form-action-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
    padding-top: 1rem;
    border-top: 1.5px solid #F1F5F9;
}

.last-saved-note {
    font-size: 0.78rem;
    color: #64748B;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.btn-save-univ {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #FFFFFF;
    border: none;
    border-radius: 10px;
    padding: 0.75rem 1.65rem;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 14px rgba(4, 120, 87, 0.3);
    transition: all 0.2s ease;
}

.btn-save-univ:hover {
    background: linear-gradient(135deg, #047857 0%, #065F46 100%);
    box-shadow: 0 6px 18px rgba(4, 120, 87, 0.4);
    transform: translateY(-1px);
}

/* ── Side Insights ── */
.side-insights-panel {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.insight-card {
    background: #FFFFFF;
    border-radius: 14px;
    border: 1.5px solid #E2E8F0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.insight-card-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #E2E8F0;
    background: #F8FAFC;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.insight-icon-bubble {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.ai-bubble {
    background: #F3E8FF;
    color: #7E22CE;
}

.univ-bubble {
    background: #ECFDF5;
    color: #047857;
}

.insight-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0F172A;
    margin: 0;
}

.insight-sub {
    font-size: 0.72rem;
    color: #64748B;
}

.insight-card-body {
    padding: 1.15rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}

.insight-step {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
}

.step-num {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #ECFDF5;
    color: #047857;
    font-size: 0.72rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 0.15rem;
    border: 1px solid #A7F3D0;
}

.step-text {
    font-size: 0.78rem;
    color: #475569;
    line-height: 1.45;
}

.step-text strong {
    color: #0F172A;
}

.guidelines-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.guidelines-list li {
    font-size: 0.78rem;
    color: #475569;
    line-height: 1.45;
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
}

.guidelines-list li i {
    color: #059669;
    font-size: 0.85rem;
    margin-top: 0.15rem;
    flex-shrink: 0;
}

.insight-card-footer {
    padding: 0.85rem 1.25rem;
    background: #F8FAFC;
    border-top: 1px solid #E2E8F0;
}

.quick-link-btn {
    font-size: 0.78rem;
    font-weight: 700;
    color: #047857;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.15s ease;
}

.quick-link-btn:hover {
    color: #065F46;
    transform: translateX(3px);
}

/* ── Responsive ── */
@media (max-width: 1024px) {
    .univ-form-grid {
        grid-template-columns: 1fr;
    }
    .stats-row {
        grid-template-columns: 1fr;
    }
}
</style>
