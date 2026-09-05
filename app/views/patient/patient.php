<?php $currentPage = 'patient_feedback'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Feedback – Auza Dental Clinic</title>
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/style.css">
</head>
<body>
<div class="app-layout">
  <?php require __DIR__ . '/../shared/sidebar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">Feedback</div>
    </div>

    <div class="page-content">
      <?php require_once __DIR__ . '/../shared/helpers.php'; showFlash(); ?>

      <!-- Completed visits: leave feedback or see that it's already been submitted -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            Share Your Feedback
            <span style="font-size:0.85rem;font-weight:400;color:var(--gray);margin-left:8px;">(<?= count($completedAppointments) ?> completed visit<?= count($completedAppointments) === 1 ? '' : 's' ?>)</span>
          </div>
        </div>
        <div class="card-body">
          <?php if (empty($completedAppointments)): ?>
            <div class="empty-state">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
              <h3>No Completed Visits Yet</h3>
              <p>Once you complete an appointment, you'll be able to leave feedback here</p>
            </div>
          <?php else: ?>
            <div class="appt-grid">
              <?php foreach ($completedAppointments as $appt): ?>
              <div class="appt-card completed">
                <div class="appt-card-header">
                  <div class="appt-service"><?= htmlspecialchars($appt['service_name']) ?></div>
                </div>
                <div class="appt-meta">
                  <div class="appt-meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <?= formatDate($appt['appointment_date']) ?>
                  </div>
                  <div class="appt-meta-item">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Dr. <?= htmlspecialchars($appt['dentist_name']) ?>
                  </div>
                </div>
                <?php if ($appt['feedback_id']): ?>
                  <div style="display:flex;align-items:center;justify-content:center;gap:6px;width:100%;margin-top:8px;padding:8px;background:#EAF3DE;color:#27500A;border-radius:8px;font-size:0.82rem;font-weight:600;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Feedback Submitted
                    <span style="color:#F59E0B;letter-spacing:1px;margin-left:2px;">
                      <?php for ($i = 1; $i <= 5; $i++): ?><?= $i <= $appt['given_rating'] ? '★' : '☆' ?><?php endfor; ?>
                    </span>
                  </div>
                <?php else: ?>
                  <button class="btn btn-primary btn-sm" style="width:100%;margin-top:8px;"
                    onclick="openFeedbackModal(<?= $appt['id'] ?>, '<?= htmlspecialchars($appt['service_name'], ENT_QUOTES) ?>')">
                    Leave Feedback
                  </button>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Previously submitted feedback -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Your Past Feedback</div>
        </div>
        <div class="card-body">
          <?php if (empty($myFeedback)): ?>
            <div class="empty-state">
              <p>You haven't submitted any feedback yet.</p>
            </div>
          <?php else: ?>
            <?php foreach ($myFeedback as $f): ?>
            <div style="border:1px solid var(--border, #e2e2e2); border-radius:10px; padding:14px 16px; margin-bottom:12px;">
              <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                <div>
                  <div style="font-weight:600;"><?= htmlspecialchars($f['service_name']) ?></div>
                  <div style="font-size:0.8rem; color:var(--gray, #888);">Dr. <?= htmlspecialchars($f['dentist_name']) ?> &middot; <?= formatDate($f['appointment_date']) ?></div>
                </div>
                <div style="color:#F59E0B; font-size:0.95rem; white-space:nowrap;">
                  <?php for ($i = 1; $i <= 5; $i++): ?><?= $i <= $f['rating'] ? '★' : '☆' ?><?php endfor; ?>
                </div>
              </div>
              <?php if (!empty($f['comment'])): ?>
                <div style="font-size:0.88rem; color:var(--text, #333); margin-top:6px;"><?= htmlspecialchars($f['comment']) ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Feedback Modal -->
<div class="modal-overlay" id="feedbackModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Leave Feedback</div>
      <button class="modal-close" onclick="closeModal('feedbackModal')">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form method="POST" action="?page=submit_feedback">
      <div class="modal-body">
        <input type="hidden" name="appointment_id" id="feedback_appointment_id">
        <p style="font-size:0.9rem; color:var(--gray, #888); margin:0 0 16px;">
          For: <strong id="feedback_service_label" style="color:var(--text, #333);"></strong>
        </p>

        <div class="form-group">
          <label>Rating <span style="color:red">*</span></label>
          <input type="hidden" name="rating" id="rating_input" required>
          <div id="star_picker" style="font-size:2rem; color:#e0e0e0; cursor:pointer; letter-spacing:4px;">
            <span data-star="1">★</span><span data-star="2">★</span><span data-star="3">★</span><span data-star="4">★</span><span data-star="5">★</span>
          </div>
        </div>

        <div class="form-group">
          <label>Comments</label>
          <div class="input-wrap">
            <svg class="icon" style="top:14px;transform:none;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            <textarea name="comment" placeholder="Tell us about your experience (optional)..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('feedbackModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" onclick="return validateFeedback()">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Submit Feedback
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
});

function openFeedbackModal(appointmentId, serviceName) {
  document.getElementById('feedback_appointment_id').value = appointmentId;
  document.getElementById('feedback_service_label').textContent = serviceName;
  document.getElementById('rating_input').value = '';
  paintStars(0);
  openModal('feedbackModal');
}

const stars = document.querySelectorAll('#star_picker span');
function paintStars(count) {
  stars.forEach(s => {
    s.style.color = parseInt(s.dataset.star) <= count ? '#F59E0B' : '#e0e0e0';
  });
}
stars.forEach(s => {
  s.addEventListener('mouseenter', () => paintStars(parseInt(s.dataset.star)));
  s.addEventListener('click', () => {
    document.getElementById('rating_input').value = s.dataset.star;
  });
});
document.getElementById('star_picker').addEventListener('mouseleave', () => {
  paintStars(parseInt(document.getElementById('rating_input').value || 0));
});

function validateFeedback() {
  if (!document.getElementById('rating_input').value) {
    alert('Please select a star rating before submitting.');
    return false;
  }
  return true;
}
</script>
</body>
</html>