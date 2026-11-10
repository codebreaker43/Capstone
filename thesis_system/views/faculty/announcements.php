<?php
// ── Handle POST actions ────────────────────────────────────────────────────
$ann_msg   = '';
$ann_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($conn->real_escape_string($_POST['ann_title'] ?? ''));
    $body     = trim($conn->real_escape_string($_POST['ann_body']  ?? ''));
    $audience = 'advisees';

    if (isset($_POST['ann_post'])) {
        // ── Create
        if (empty($title) || empty($body)) {
            $ann_error = 'Title and message are required.';
        } else {
            $conn->query("INSERT INTO announcements (adviser_id, title, body, audience) VALUES ($uid, '$title', '$body', '$audience')");
            $ann_msg = 'Announcement posted to your advisees!';
        }

    } elseif (isset($_POST['ann_edit'])) {
        // ── Edit
        $ann_id = (int)($_POST['ann_id'] ?? 0);
        if (empty($title) || empty($body) || $ann_id <= 0) {
            $ann_error = 'Invalid edit submission.';
        } else {
            $conn->query("UPDATE announcements SET title='$title', body='$body' WHERE id=$ann_id AND adviser_id=$uid");
            $ann_msg = 'Announcement updated.';
        }

    } elseif (isset($_POST['ann_delete'])) {
        // ── Delete
        $ann_id = (int)($_POST['ann_id'] ?? 0);
        if ($ann_id > 0) {
            $conn->query("DELETE FROM announcements WHERE id=$ann_id AND adviser_id=$uid");
            $ann_msg = 'Announcement deleted.';
        }
    }
}

// ── Fetch all announcements by this adviser ────────────────────────────────
$ann_res = $conn->query("SELECT * FROM announcements WHERE adviser_id=$uid ORDER BY created_at DESC");
$ann_count = $ann_res ? $ann_res->num_rows : 0;
?>

<style>
/* ─── Announcements Page ─────────────────────────────── */
.ann-banner {
    background: linear-gradient(135deg, #064e3b 0%, #065f46 60%, #047857 100%);
    border-radius: 16px;
    padding: 1.5rem 2rem;
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.5rem;
    box-shadow: 0 8px 24px rgba(6,78,59,0.2);
    gap: 1rem;
}
.ann-banner-left { display: flex; align-items: center; gap: 1rem; }
.ann-banner-icon {
    width: 50px; height: 50px; border-radius: 14px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.18);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; color: #fff; flex-shrink: 0;
}
.ann-banner-title {
    font-family: 'Outfit', 'Inter', sans-serif;
    font-size: 1.4rem; font-weight: 800; color: #fff;
    letter-spacing: -0.3px; line-height: 1.1;
}
.ann-banner-sub { font-size: 0.82rem; color: #a7f3d0; margin-top: 0.15rem; }
.ann-count-badge {
    background: rgba(255,255,255,0.15);
    color: #fff; border: 1px solid rgba(255,255,255,0.25);
    font-size: 0.8rem; font-weight: 700;
    padding: 0.35rem 0.9rem; border-radius: 999px;
    white-space: nowrap;
}

/* ─── Layout grid ─── */
.ann-layout {
    display: grid;
    grid-template-columns: 1fr 1.65fr;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 860px) { .ann-layout { grid-template-columns: 1fr; } }

/* ─── Form Card ─── */
.ann-form-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    position: sticky; top: 1rem;
}
.ann-form-head {
    padding: 1.1rem 1.35rem;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    display: flex; align-items: center; gap: 0.7rem;
}
.ann-form-head-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: #ecfdf5; color: #059669;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.88rem;
}
.ann-form-head h2 { font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0; }
.ann-form-head p  { font-size: 0.72rem; color: #64748b; margin: 0; }
.ann-form-body { padding: 1.25rem 1.35rem; }

.ann-label {
    display: block;
    font-size: 0.72rem; font-weight: 700;
    color: #475569; text-transform: uppercase;
    letter-spacing: 0.3px; margin-bottom: 0.3rem;
}
.ann-input, .ann-textarea, .ann-select {
    width: 100%; box-sizing: border-box;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.6rem 0.85rem;
    font-size: 0.875rem; color: #0f172a;
    background: #f8fafc;
    font-family: inherit;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.ann-input:focus, .ann-textarea:focus, .ann-select:focus {
    outline: none;
    border-color: #059669;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
}
.ann-textarea {
    resize: vertical; min-height: 120px; line-height: 1.55;
}
.ann-select { cursor: pointer; appearance: none; -webkit-appearance: none; }
.ann-form-group { margin-bottom: 0.9rem; }

.ann-audience-row {
    display: flex; gap: 0.65rem; margin-bottom: 0.9rem;
}
.ann-audience-opt {
    flex: 1; border: 1.5px solid #e2e8f0;
    border-radius: 10px; padding: 0.7rem 0.75rem;
    cursor: pointer; text-align: center;
    transition: all 0.2s;
    background: #f8fafc;
}
.ann-audience-opt:hover { border-color: #059669; background: #f0fdf4; }
.ann-audience-opt input[type=radio] { display: none; }
.ann-audience-opt.selected {
    border-color: #059669; background: #ecfdf5;
}
.ann-audience-opt .ao-icon { font-size: 1.15rem; margin-bottom: 0.2rem; }
.ann-audience-opt .ao-label { font-size: 0.75rem; font-weight: 700; color: #334155; }
.ann-audience-opt .ao-sub  { font-size: 0.67rem; color: #64748b; }
.ann-audience-opt.selected .ao-label { color: #059669; }

.ann-submit-btn {
    width: 100%;
    background: linear-gradient(135deg, #059669, #047857);
    color: #fff; border: none;
    border-radius: 11px; padding: 0.75rem 1rem;
    font-size: 0.9rem; font-weight: 700;
    cursor: pointer; font-family: inherit;
    display: flex; align-items: center; justify-content: center; gap: 0.55rem;
    transition: all 0.22s;
    box-shadow: 0 4px 12px rgba(5,150,105,0.3);
}
.ann-submit-btn:hover {
    background: linear-gradient(135deg, #10b981, #059669);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(5,150,105,0.4);
}
.ann-char-hint {
    font-size: 0.68rem; color: #94a3b8;
    text-align: right; margin-top: 0.2rem;
}

/* ─── List Card ─── */
.ann-list-card {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 16px; overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.ann-list-head {
    padding: 1.1rem 1.35rem;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    display: flex; align-items: center; justify-content: space-between;
    gap: 0.75rem;
}
.ann-list-head-left { display: flex; align-items: center; gap: 0.7rem; }
.ann-list-head-icon {
    width: 36px; height: 36px; border-radius: 10px;
    background: #ecfdf5; color: #059669;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.88rem;
}
.ann-list-head h2 { font-size: 0.95rem; font-weight: 800; color: #0f172a; margin: 0; }
.ann-list-count {
    background: #ecfdf5; color: #059669;
    font-size: 0.72rem; font-weight: 700;
    padding: 0.22rem 0.65rem; border-radius: 999px;
    border: 1px solid #a7f3d0;
}

/* ─── Announcement Item ─── */
.ann-item {
    padding: 1.1rem 1.35rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
}
.ann-item:last-child { border-bottom: none; }
.ann-item:hover { background: #fafcff; }

.ann-item-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 0.75rem; margin-bottom: 0.45rem;
}
.ann-item-title {
    font-weight: 800; color: #0f172a; font-size: 0.92rem;
    line-height: 1.3;
}
.ann-item-actions { display: flex; gap: 0.45rem; flex-shrink: 0; }
.ann-btn-edit, .ann-btn-del {
    width: 30px; height: 30px; border-radius: 8px;
    border: 1.5px solid; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; transition: all 0.18s;
    background: transparent;
}
.ann-btn-edit {
    border-color: #a7f3d0; color: #059669;
}
.ann-btn-edit:hover { background: #ecfdf5; border-color: #34d399; }
.ann-btn-del {
    border-color: #fecaca; color: #ef4444;
}
.ann-btn-del:hover { background: #fef2f2; border-color: #f87171; }

.ann-item-body {
    font-size: 0.855rem; color: #475569;
    line-height: 1.6; white-space: pre-wrap;
    margin-bottom: 0.6rem;
}
.ann-item-meta {
    display: flex; align-items: center; gap: 0.75rem;
    flex-wrap: wrap;
}
.ann-audience-tag {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.7rem; font-weight: 700;
    padding: 0.2rem 0.6rem; border-radius: 6px;
}
.ann-audience-tag.all     { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; }
.ann-audience-tag.advisees{ background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.ann-item-date { font-size: 0.72rem; color: #94a3b8; }

/* ─── Empty state ─── */
.ann-empty {
    text-align: center; padding: 3rem 1.5rem; color: #94a3b8;
}
.ann-empty i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; color: #cbd5e1; }
.ann-empty p { font-size: 0.875rem; margin: 0; }

/* ─── Alerts ─── */
.ann-alert {
    padding: 0.85rem 1.1rem; border-radius: 12px;
    font-size: 0.855rem; font-weight: 500;
    display: flex; align-items: center; gap: 0.65rem;
    margin-bottom: 1rem;
    animation: annSlide 0.3s cubic-bezier(0.16,1,0.3,1);
}
@keyframes annSlide {
    from { opacity:0; transform: translateY(-8px); }
    to   { opacity:1; transform: translateY(0); }
}
.ann-alert.success {
    background: #ecfdf5; color: #065f46;
    border: 1px solid #a7f3d0; border-left: 4px solid #10b981;
}
.ann-alert.error {
    background: #fef2f2; color: #991b1b;
    border: 1px solid #fecaca; border-left: 4px solid #ef4444;
}

/* ─── Edit modal overlay ─── */
.ann-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,0.55);
    backdrop-filter: blur(4px);
    z-index: 9000;
    align-items: center; justify-content: center;
}
.ann-modal-overlay.open { display: flex; }
.ann-modal {
    background: #fff; border-radius: 18px;
    width: 100%; max-width: 520px; margin: 1rem;
    box-shadow: 0 24px 60px rgba(0,0,0,0.3);
    animation: annModalIn 0.28s cubic-bezier(0.16,1,0.3,1);
}
@keyframes annModalIn {
    from { opacity:0; transform: scale(0.94) translateY(20px); }
    to   { opacity:1; transform: scale(1) translateY(0); }
}
.ann-modal-head {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
}
.ann-modal-head h3 { font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; }
.ann-modal-close {
    width: 32px; height: 32px; border-radius: 8px;
    border: 1.5px solid #e2e8f0; background: transparent;
    cursor: pointer; font-size: 0.85rem; color: #64748b;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.18s;
}
.ann-modal-close:hover { background: #f1f5f9; color: #0f172a; }
.ann-modal-body { padding: 1.35rem 1.5rem; }
.ann-modal-foot {
    padding: 1rem 1.5rem;
    border-top: 1px solid #f1f5f9;
    display: flex; gap: 0.75rem; justify-content: flex-end;
}
.ann-modal-cancel {
    padding: 0.6rem 1.2rem; border-radius: 10px;
    border: 1.5px solid #e2e8f0; background: transparent;
    font-size: 0.855rem; font-weight: 600; color: #475569;
    cursor: pointer; font-family: inherit; transition: all 0.18s;
}
.ann-modal-cancel:hover { background: #f1f5f9; }
.ann-modal-save {
    padding: 0.6rem 1.35rem; border-radius: 10px;
    background: linear-gradient(135deg, #059669, #047857);
    border: none; color: #fff;
    font-size: 0.855rem; font-weight: 700;
    cursor: pointer; font-family: inherit;
    box-shadow: 0 3px 10px rgba(5,150,105,0.3);
    transition: all 0.2s;
}
.ann-modal-save:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(5,150,105,0.4);
}
</style>

<!-- ═══ BANNER ═══════════════════════════════════════════════════════════ -->
<div class="ann-banner">
    <div class="ann-banner-left">
        <div class="ann-banner-icon"><i class="fa-solid fa-bullhorn"></i></div>
        <div>
            <div class="ann-banner-title">Announcements</div>
            <div class="ann-banner-sub">Post updates for your accepted advisees</div>
        </div>
    </div>
    <span class="ann-count-badge"><i class="fa-solid fa-megaphone" style="margin-right:0.35rem;"></i><?= $ann_count ?> Posted</span>
</div>

<!-- ═══ ALERTS ═══════════════════════════════════════════════════════════ -->
<?php if ($ann_msg):   ?><div class="ann-alert success"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($ann_msg) ?></div><?php endif; ?>
<?php if ($ann_error): ?><div class="ann-alert error"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($ann_error) ?></div><?php endif; ?>

<!-- ═══ LAYOUT ═══════════════════════════════════════════════════════════ -->
<div class="ann-layout">

    <!-- LEFT: POST FORM -->
    <div class="ann-form-card">
        <div class="ann-form-head">
            <div class="ann-form-head-icon"><i class="fa-solid fa-pen-to-square"></i></div>
            <div>
                <h2>Post Announcement</h2>
                <p>Broadcast a notice to your advisees</p>
            </div>
        </div>
        <div class="ann-form-body">
            <form method="POST" action="?tab=announcements" id="postForm">
                <div class="ann-form-group">
                    <label class="ann-label">Title *</label>
                    <input type="text" name="ann_title" class="ann-input" placeholder="e.g. Schedule Change on Friday" maxlength="200" required id="annTitleInput">
                </div>

                <div class="ann-form-group">
                    <label class="ann-label">Message *</label>
                    <textarea name="ann_body" class="ann-textarea" placeholder="Write your announcement here..." maxlength="2000" required id="annBodyInput" oninput="updateCharCount(this)"></textarea>
                    <div class="ann-char-hint"><span id="annCharCount">0</span> / 2000</div>
                </div>

                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:0.65rem 0.85rem; font-size:0.78rem; color:#166534; display:flex; align-items:center; gap:0.55rem; margin-bottom:1rem;">
                    <i class="fa-solid fa-user-graduate" style="color:#059669; font-size:0.95rem;"></i>
                    <span>This announcement will be seen <strong>only by your accepted advisees</strong>.</span>
                </div>

                <button type="submit" name="ann_post" class="ann-submit-btn">
                    <i class="fa-solid fa-paper-plane"></i> Publish to Advisees
                </button>
            </form>
        </div>
    </div>

    <!-- RIGHT: ANNOUNCEMENTS LIST -->
    <div class="ann-list-card">
        <div class="ann-list-head">
            <div class="ann-list-head-left">
                <div class="ann-list-head-icon"><i class="fa-solid fa-list-ul"></i></div>
                <div>
                    <h2>Your Announcements</h2>
                </div>
            </div>
            <span class="ann-list-count"><?= $ann_count ?> total</span>
        </div>

        <?php if ($ann_count === 0): ?>
        <div class="ann-empty">
            <i class="fa-regular fa-bell-slash"></i>
            <p>No announcements yet.<br>Post your first one using the form!</p>
        </div>
        <?php else: ?>
        <?php $ann_res->data_seek(0); while ($ann = $ann_res->fetch_assoc()): ?>
        <div class="ann-item">
            <div class="ann-item-head">
                <div class="ann-item-title"><?= htmlspecialchars($ann['title']) ?></div>
                <div class="ann-item-actions">
                    <button class="ann-btn-edit" title="Edit"
                        onclick="openEditModal(<?= $ann['id'] ?>, <?= htmlspecialchars(json_encode($ann['title'])) ?>, <?= htmlspecialchars(json_encode($ann['body'])) ?>)">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <form method="POST" action="?tab=announcements" style="display:inline;"
                        onsubmit="return confirm('Delete this announcement?')">
                        <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
                        <button type="submit" name="ann_delete" class="ann-btn-del" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="ann-item-body"><?= htmlspecialchars($ann['body']) ?></div>

            <div class="ann-item-meta">
                <span class="ann-audience-tag advisees">
                    <i class="fa-solid fa-user-graduate" style="font-size:0.65rem;"></i> My Advisees
                </span>
                <span class="ann-item-date">
                    <i class="fa-regular fa-clock" style="font-size:0.65rem;"></i>
                    <?= date('M j, Y • g:i A', strtotime($ann['created_at'])) ?>
                </span>
                <?php if ($ann['updated_at'] !== $ann['created_at']): ?>
                <span class="ann-item-date" style="color:#94a3b8;">(edited)</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ═══ EDIT MODAL ════════════════════════════════════════════════════════ -->
<div class="ann-modal-overlay" id="editModal">
    <div class="ann-modal">
        <div class="ann-modal-head">
            <h3><i class="fa-solid fa-pen" style="color:#059669;margin-right:0.5rem;"></i> Edit Announcement</h3>
            <button class="ann-modal-close" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="?tab=announcements">
            <input type="hidden" name="ann_id" id="editAnnId">
            <div class="ann-modal-body">
                <div class="ann-form-group">
                    <label class="ann-label">Title *</label>
                    <input type="text" name="ann_title" id="editTitle" class="ann-input" maxlength="200" required>
                </div>
                <div class="ann-form-group">
                    <label class="ann-label">Message *</label>
                    <textarea name="ann_body" id="editBody" class="ann-textarea" maxlength="2000" required></textarea>
                </div>
            </div>
            <div class="ann-modal-foot">
                <button type="button" class="ann-modal-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="ann_edit" class="ann-modal-save">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function updateCharCount(el) {
    document.getElementById('annCharCount').textContent = el.value.length;
}

function openEditModal(id, title, body) {
    document.getElementById('editAnnId').value = id;
    document.getElementById('editTitle').value = title;
    document.getElementById('editBody').value  = body;
    document.getElementById('editModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}

// Close modal on backdrop click
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEditModal();
});
</script>
