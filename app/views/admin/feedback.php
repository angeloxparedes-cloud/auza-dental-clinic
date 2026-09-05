<?php $currentPage = 'admin_feedback'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Feedback – Auza Dental Clinic</title>
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php require __DIR__ . '/../shared/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">Patient Feedback</div>
    </div>
    <div class="page-content">
      <?php require_once __DIR__ . '/../shared/helpers.php'; showFlash(); ?>

      <!-- Summary -->
      <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
        <div class="stat-card">
          <div class="stat-icon yellow">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
          </div>
          <div><div class="stat-value"><?= $avgRating ?> / 5</div><div class="stat-label">Average Rating</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
          </div>
          <div><div class="stat-value"><?= $totalFeedback ?></div><div class="stat-label">Total Feedback</div></div>
        </div>
        <div class="stat-card" style="grid-column: span 1;">
          <div style="width:100%;">
            <?php foreach ([5,4,3,2,1] as $star): ?>
              <div style="display:flex;align-items:center;gap:8px;font-size:0.78rem;margin-bottom:2px;">
                <span style="width:32px;color:var(--gray);"><?= $star ?> star</span>
                <div style="flex:1;background:#f0f0f0;border-radius:6px;height:6px;overflow:hidden;">
                  <div style="width:<?= $totalFeedback > 0 ? round(($ratingCounts[$star] / $totalFeedback) * 100) : 0 ?>%;background:#F59E0B;height:100%;"></div>
                </div>
                <span style="width:20px;text-align:right;color:var(--gray);"><?= $ratingCounts[$star] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
          <div class="card-title">All Feedback <span style="font-size:.85rem;font-weight:400;color:var(--gray);margin-left:8px;">(<?= $totalFeedback ?>)</span></div>
          <input type="text" id="searchInput" placeholder="Search by patient name..." onkeyup="searchFeedback()"
            style="padding:8px 14px;border:1.5px solid var(--border);border-radius:8px;font-family:var(--font-body);font-size:0.88rem;outline:none;width:240px;">
        </div>
        <div class="card-body">
          <?php if (empty($allFeedback)): ?>
            <div class="empty-state">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
              <h3>No Feedback Yet</h3>
              <p>Patient feedback will appear here once submitted</p>
            </div>
          <?php else: ?>
            <div id="feedbackList">
            <?php foreach ($allFeedback as $f): ?>
            <div class="feedback-entry" data-patient="<?= strtolower(htmlspecialchars($f['patient_name'])) ?>"
                 style="border:1px solid <?= $f['is_read'] ? 'var(--border, #e2e2e2)' : '#F59E0B' ?>; background:<?= $f['is_read'] ? 'transparent' : '#FFFBEB' ?>; border-radius:10px; padding:14px 16px; margin-bottom:12px;">
              <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px; flex-wrap:wrap; gap:8px;">
                <div style="display:flex; align-items:center; gap:10px;">
                  <?php if (!empty($f['patient_avatar'])): ?>
                    <img src="<?= APP_URL ?>/public/uploads/avatars/<?= htmlspecialchars($f['patient_avatar']) ?>"
                         style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                  <?php else: ?>
                    <div class="user-avatar" style="width:38px;height:38px;font-size:0.8rem;background:var(--primary);flex-shrink:0;">
                      <?= strtoupper(substr($f['patient_first_name'], 0, 1) . substr($f['patient_last_name'], 0, 1)) ?>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div style="font-weight:600;display:flex;align-items:center;gap:8px;">
                      <?= htmlspecialchars($f['patient_name']) ?>
                      <?php if (!$f['is_read']): ?>
                        <span style="background:#e74c3c;color:#fff;font-size:9px;font-weight:700;padding:2px 7px;border-radius:20px;letter-spacing:0.03em;">NEW</span>
                      <?php endif; ?>
                    </div>
                    <div style="font-size:0.8rem; color:var(--gray, #888);">
                      <?= htmlspecialchars($f['service_name']) ?> with Dr. <?= htmlspecialchars($f['dentist_name']) ?> &middot; <?= formatDate($f['appointment_date']) ?>
                    </div>
                  </div>
                </div>
                <div style="text-align:right;">
                  <div style="color:#F59E0B; font-size:0.95rem;">
                    <?php for ($i = 1; $i <= 5; $i++): ?><?= $i <= $f['rating'] ? '★' : '☆' ?><?php endfor; ?>
                  </div>
                  <div style="font-size:0.72rem; color:var(--gray, #888);"><?= formatDate($f['created_at']) ?></div>
                </div>
              </div>
              <?php if (!empty($f['comment'])): ?>
                <div style="font-size:0.88rem; color:var(--text, #333); margin-top:6px;"><?= htmlspecialchars($f['comment']) ?></div>
              <?php else: ?>
                <div style="font-size:0.85rem; color:var(--gray, #aaa); font-style:italic; margin-top:6px;">No comment left.</div>
              <?php endif; ?>
              <?php if (!$f['is_read']): ?>
                <div style="margin-top:10px;">
                  <a href="?page=admin_mark_feedback_read&id=<?= $f['id'] ?>"
                     style="display:inline-flex;align-items:center;gap:6px;font-size:0.8rem;font-weight:600;color:var(--primary);text-decoration:none;background:var(--primary-light, #EEF6FF);padding:6px 12px;border-radius:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Mark as Read
                  </a>
                </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>
            <div id="noSearchResults" style="display:none;text-align:center;color:var(--gray);padding:24px 0;font-size:0.9rem;">
              No feedback found for that patient.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
function searchFeedback() {
  const input   = document.getElementById('searchInput').value.toLowerCase();
  const entries = document.querySelectorAll('.feedback-entry');
  let visibleCount = 0;
  entries.forEach(el => {
    const match = el.dataset.patient.includes(input);
    el.style.display = match ? '' : 'none';
    if (match) visibleCount++;
  });
  document.getElementById('noSearchResults').style.display = visibleCount === 0 ? 'block' : 'none';
}
</script>
</body>
</html>