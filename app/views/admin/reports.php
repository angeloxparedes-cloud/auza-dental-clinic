<?php $currentPage = 'admin_reports'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports – Auza Dental Clinic</title>
<link rel="stylesheet" href="<?= APP_URL ?>/public/css/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
  /* Print-only header, hidden on screen */
  .print-header { display: none; }

  .filter-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    background: #fff;
    border: 1px solid #E5E9EB;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 20px;
  }
  .filter-bar .filter-label {
    font-size: 13px;
    font-weight: 600;
    color: #555;
    margin-right: 4px;
  }
  .filter-btn {
    padding: 7px 14px;
    border-radius: 20px;
    border: 1px solid #D8DEE1;
    background: #fff;
    color: #333;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
  }
  .filter-btn:hover { border-color: #1D9E75; color: #0F6E56; }
  .filter-btn.active {
    background: #1D9E75;
    border-color: #1D9E75;
    color: #fff;
  }
  .filter-bar form.custom-range {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: auto;
  }
  .filter-bar form.custom-range input[type="date"] {
    padding: 6px 8px;
    border: 1px solid #D8DEE1;
    border-radius: 6px;
    font-size: 13px;
  }
  .filter-bar form.custom-range button {
    padding: 7px 14px;
    border-radius: 20px;
    border: none;
    background: #333;
    color: #fff;
    font-size: 13px;
    cursor: pointer;
  }
  .range-caption {
    font-size: 13px;
    color: #667;
    margin: -12px 0 20px;
  }

  @media print {
    /* Hide everything that shouldn't be on paper */
    .sidebar, .topbar, .btn-print, .app-layout > .sidebar, .filter-bar { display: none !important; }

    body { background: #fff !important; }
    .main-content { margin: 0 !important; width: 100% !important; }
    .page-content { padding: 0 !important; }

    .print-header {
      display: block;
      text-align: center;
      margin-bottom: 20px;
      border-bottom: 2px solid #1D9E75;
      padding-bottom: 12px;
    }
    .print-header h1 { margin: 0; font-size: 22px; color: #0F6E56; }
    .print-header p { margin: 4px 0 0; font-size: 12px; color: #555; }
    .print-header .print-range {
      display: inline-block;
      margin-top: 6px;
      padding: 4px 12px;
      border: 1px solid #1D9E75;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      color: #0F6E56;
    }

    /* Avoid cutting a card in half across a page break */
    .stat-card, .card { break-inside: avoid; }

    /* Stack the two-column chart grids into one column for print */
    div[style*="grid-template-columns:1fr 1fr"] {
      grid-template-columns: 1fr !important;
    }

    canvas { max-width: 100% !important; }
  }
</style>
</head>
<body>
<div class="app-layout">
  <?php require __DIR__ . '/../shared/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">Reports & Analytics</div>
      <button type="button" class="btn-print" onclick="window.print()" style="display:flex;align-items:center;gap:8px;padding:8px 16px;background:#1D9E75;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
        Print Report
      </button>
    </div>
    <div class="page-content">
      <?php require_once __DIR__ . '/../shared/helpers.php'; ?>
      <?php
        $db = getDB();

        // ---------------------------------------------------------------
        // Date-range filter (Today / This Week / This Month / All Time / Custom)
        // ---------------------------------------------------------------
        $range = $_GET['range'] ?? 'all';
        $validRanges = ['today', 'week', 'month', 'all', 'custom'];
        if (!in_array($range, $validRanges, true)) {
            $range = 'all';
        }

        // Validate custom dates strictly (YYYY-MM-DD) to avoid bad input reaching SQL
        $isValidDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
        $customStart = $isValidDate($_GET['start'] ?? '') ? $_GET['start'] : '';
        $customEnd   = $isValidDate($_GET['end'] ?? '')   ? $_GET['end']   : '';
        if ($range === 'custom' && (!$customStart || !$customEnd)) {
            // Incomplete custom range submitted — fall back to All Time
            $range = 'all';
        }

        $dateStart = null;
        $dateEnd   = null;
        $rangeLabel = 'All Time';

        switch ($range) {
            case 'today':
                $dateStart = $dateEnd = date('Y-m-d');
                $rangeLabel = 'Today — ' . date('F j, Y');
                break;
            case 'week':
                $dateStart = date('Y-m-d', strtotime('monday this week'));
                $dateEnd   = date('Y-m-d', strtotime('sunday this week'));
                $rangeLabel = 'This Week — ' . date('M j', strtotime($dateStart)) . ' to ' . date('M j, Y', strtotime($dateEnd));
                break;
            case 'month':
                $dateStart = date('Y-m-01');
                $dateEnd   = date('Y-m-t');
                $rangeLabel = 'This Month — ' . date('F Y');
                break;
            case 'custom':
                $dateStart = min($customStart, $customEnd);
                $dateEnd   = max($customStart, $customEnd);
                $rangeLabel = 'Custom Range — ' . date('M j, Y', strtotime($dateStart)) . ' to ' . date('M j, Y', strtotime($dateEnd));
                break;
            case 'all':
            default:
                $range = 'all';
                $rangeLabel = 'All Time';
                break;
        }

        $isFiltered = $range !== 'all';

        // Small helper so we don't repeat prepare/bind/execute everywhere
        $fetchAll = function (string $sql, string $types = '', array $params = []) use ($db) {
            if ($params) {
                $stmt = $db->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                return $rows;
            }
            return $db->query($sql)->fetch_all(MYSQLI_ASSOC);
        };

        // Appointments by status (filtered)
        $byStatus = $isFiltered
            ? $fetchAll("SELECT status, COUNT(*) as cnt FROM appointments WHERE appointment_date BETWEEN ? AND ? GROUP BY status", 'ss', [$dateStart, $dateEnd])
            : $fetchAll("SELECT status, COUNT(*) as cnt FROM appointments GROUP BY status");

        // Appointments over time + Revenue over time
        // All Time -> last 6 months grouped by month (original behavior)
        // Today/Week/Month/Custom -> grouped by individual day within the range
        if ($isFiltered) {
            $byPeriod = $fetchAll("
                SELECT DATE_FORMAT(appointment_date,'%b %d') as period,
                       appointment_date as sort_key,
                       COUNT(*) as cnt
                FROM appointments
                WHERE appointment_date BETWEEN ? AND ?
                GROUP BY appointment_date
                ORDER BY appointment_date ASC
            ", 'ss', [$dateStart, $dateEnd]);

            $revenueByPeriod = $fetchAll("
                SELECT DATE_FORMAT(a.appointment_date,'%b %d') as period,
                       a.appointment_date as sort_key,
                       SUM(s.price) as revenue
                FROM appointments a
                JOIN services s ON a.service_id = s.id
                WHERE a.status = 'completed'
                  AND a.appointment_date BETWEEN ? AND ?
                GROUP BY a.appointment_date
                ORDER BY a.appointment_date ASC
            ", 'ss', [$dateStart, $dateEnd]);

            $periodChartTitle  = 'Appointments (' . $rangeLabel . ')';
            $revenueChartTitle = 'Revenue (' . $rangeLabel . ')';
        } else {
            $byPeriod = $fetchAll("
                SELECT DATE_FORMAT(appointment_date,'%b %Y') as period,
                       DATE_FORMAT(appointment_date,'%Y-%m') as sort_key,
                       COUNT(*) as cnt
                FROM appointments
                WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY sort_key, period
                ORDER BY sort_key ASC
            ");

            $revenueByPeriod = $fetchAll("
                SELECT DATE_FORMAT(a.appointment_date,'%b %Y') as period,
                       DATE_FORMAT(a.appointment_date,'%Y-%m') as sort_key,
                       SUM(s.price) as revenue
                FROM appointments a
                JOIN services s ON a.service_id = s.id
                WHERE a.status = 'completed'
                  AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY sort_key, period ORDER BY sort_key ASC
            ");

            $periodChartTitle  = 'Appointments (Last 6 Months)';
            $revenueChartTitle = 'Revenue (Last 6 Months)';
        }

        // Top services (filtered)
        $topServices = $isFiltered
            ? $fetchAll("
                SELECT s.name, COUNT(*) as cnt
                FROM appointments a
                JOIN services s ON a.service_id = s.id
                WHERE a.appointment_date BETWEEN ? AND ?
                GROUP BY s.name ORDER BY cnt DESC LIMIT 5
              ", 'ss', [$dateStart, $dateEnd])
            : $fetchAll("
                SELECT s.name, COUNT(*) as cnt
                FROM appointments a
                JOIN services s ON a.service_id = s.id
                GROUP BY s.name ORDER BY cnt DESC LIMIT 5
              ");

        // Summary stats (filtered)
        if ($isFiltered) {
            $totalAppts     = $fetchAll("SELECT COUNT(*) as total FROM appointments WHERE appointment_date BETWEEN ? AND ?", 'ss', [$dateStart, $dateEnd])[0]['total'];
            $totalRevenue   = $fetchAll("SELECT COALESCE(SUM(s.price),0) as rev FROM appointments a JOIN services s ON a.service_id=s.id WHERE a.status='completed' AND a.appointment_date BETWEEN ? AND ?", 'ss', [$dateStart, $dateEnd])[0]['rev'];
            $totalPatients  = $fetchAll("SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE appointment_date BETWEEN ? AND ?", 'ss', [$dateStart, $dateEnd])[0]['total'];
            $totalCompleted = $fetchAll("SELECT COUNT(*) as total FROM appointments WHERE status='completed' AND appointment_date BETWEEN ? AND ?", 'ss', [$dateStart, $dateEnd])[0]['total'];
            $patientsLabel  = 'Patients in Range';
        } else {
            $totalAppts     = $fetchAll("SELECT COUNT(*) as total FROM appointments")[0]['total'];
            $totalRevenue   = $fetchAll("SELECT COALESCE(SUM(s.price),0) as rev FROM appointments a JOIN services s ON a.service_id=s.id WHERE a.status='completed'")[0]['rev'];
            $totalPatients  = $fetchAll("SELECT COUNT(*) as total FROM users WHERE role='patient'")[0]['total'];
            $totalCompleted = $fetchAll("SELECT COUNT(*) as total FROM appointments WHERE status='completed'")[0]['total'];
            $patientsLabel  = 'Total Patients';
        }

        // Keep old variable names alive in case anything else on the page expects them
        $byMonth = $byPeriod;
        $revenueByMonth = $revenueByPeriod;
      ?>

      <!-- Shows only when printing -->
      <div class="print-header">
        <h1>Auza Dental Clinic — Reports & Analytics</h1>
        <p>Generated on <?= date('F j, Y g:i A') ?></p>
        <div class="print-range">Range: <?= htmlspecialchars($rangeLabel) ?></div>
      </div>

      <!-- Date range filter (hidden on print) -->
      <div class="filter-bar">
        <span class="filter-label">Show:</span>
        <a href="?page=admin_reports&range=all" class="filter-btn <?= $range === 'all' ? 'active' : '' ?>">All Time</a>
        <a href="?page=admin_reports&range=today" class="filter-btn <?= $range === 'today' ? 'active' : '' ?>">Today</a>
        <a href="?page=admin_reports&range=week" class="filter-btn <?= $range === 'week' ? 'active' : '' ?>">This Week</a>
        <a href="?page=admin_reports&range=month" class="filter-btn <?= $range === 'month' ? 'active' : '' ?>">This Month</a>

        <form class="custom-range" method="get" action="">
          <input type="hidden" name="page" value="admin_reports">
          <input type="hidden" name="range" value="custom">
          <input type="date" name="start" value="<?= htmlspecialchars($range === 'custom' ? $dateStart : '') ?>" required>
          <span style="font-size:13px;color:#888;">to</span>
          <input type="date" name="end" value="<?= htmlspecialchars($range === 'custom' ? $dateEnd : '') ?>" required>
          <button type="submit">Apply</button>
        </form>
      </div>
      <div class="range-caption">Currently showing: <strong><?= htmlspecialchars($rangeLabel) ?></strong> — "Print Report" will only print this range.</div>

      <!-- Summary Cards -->
      <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat-card">
          <div class="stat-icon blue"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></div>
          <div><div class="stat-value"><?= $totalAppts ?></div><div class="stat-label">Total Appointments</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
          <div><div class="stat-value"><?= $totalCompleted ?></div><div class="stat-label">Completed</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
          <div><div class="stat-value"><?= $totalPatients ?></div><div class="stat-label"><?= $patientsLabel ?></div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon teal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg></div>
          <div><div class="stat-value">₱<?= number_format($totalRevenue, 0) ?></div><div class="stat-label">Total Revenue</div></div>
        </div>
      </div>

      <!-- Charts Row -->
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
        <!-- Appointments over the selected period -->
        <div class="card" style="margin-bottom:0;">
          <div class="card-header"><div class="card-title"><?= htmlspecialchars($periodChartTitle) ?></div></div>
          <div class="card-body"><canvas id="chartMonthly" height="200"></canvas></div>
        </div>
        <!-- Status breakdown -->
        <div class="card" style="margin-bottom:0;">
          <div class="card-header"><div class="card-title">Appointments by Status</div></div>
          <div class="card-body"><canvas id="chartStatus" height="200"></canvas></div>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <!-- Revenue over the selected period -->
        <div class="card" style="margin-bottom:0;">
          <div class="card-header"><div class="card-title"><?= htmlspecialchars($revenueChartTitle) ?></div></div>
          <div class="card-body"><canvas id="chartRevenue" height="200"></canvas></div>
        </div>
        <!-- Top services -->
        <div class="card" style="margin-bottom:0;">
          <div class="card-header"><div class="card-title">Top Services</div></div>
          <div class="card-body"><canvas id="chartServices" height="200"></canvas></div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
const monthLabels   = <?= json_encode(array_column($byPeriod, 'period')) ?>;
const monthData     = <?= json_encode(array_map('intval', array_column($byPeriod, 'cnt'))) ?>;
const statusLabels  = <?= json_encode(array_column($byStatus, 'status')) ?>;
const statusData    = <?= json_encode(array_map('intval', array_column($byStatus, 'cnt'))) ?>;
const serviceLabels = <?= json_encode(array_column($topServices, 'name')) ?>;
const serviceData   = <?= json_encode(array_map('intval', array_column($topServices, 'cnt'))) ?>;
const revLabels     = <?= json_encode(array_column($revenueByPeriod, 'period')) ?>;
const revData       = <?= json_encode(array_map('floatval', array_column($revenueByPeriod, 'revenue'))) ?>;

new Chart(document.getElementById('chartMonthly'), {
  type: 'bar',
  data: { labels: monthLabels, datasets: [{ label: 'Appointments', data: monthData, backgroundColor: '#1D9E7588', borderColor: '#1D9E75', borderWidth: 1, borderRadius: 6 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

new Chart(document.getElementById('chartStatus'), {
  type: 'doughnut',
  data: { labels: statusLabels, datasets: [{ data: statusData, backgroundColor: ['#F59E0B','#3B82F6','#1D9E75','#FC8181'], borderWidth: 2 }] },
  options: { plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('chartRevenue'), {
  type: 'line',
  data: { labels: revLabels, datasets: [{ label: 'Revenue (₱)', data: revData, borderColor: '#0F6E56', backgroundColor: '#1D9E7522', fill: true, tension: 0.4, pointRadius: 4 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('chartServices'), {
  type: 'bar',
  data: { labels: serviceLabels, datasets: [{ label: 'Bookings', data: serviceData, backgroundColor: ['#1D9E75','#3B82F6','#F59E0B','#FC8181','#8B5CF6'], borderRadius: 6 }] },
  options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
</script>
</body>
</html>