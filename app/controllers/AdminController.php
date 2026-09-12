<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers/mailer.php';

class AdminController {

    public function dashboard() {
        requireStaffOrAdmin();
        $db = getDB();

        // Mirrors sp_get_dashboard_stats
        $statsResult = $db->query("
            SELECT
                (SELECT COUNT(*) FROM appointments WHERE status='pending') AS pending_count,
                (SELECT COUNT(*) FROM appointments WHERE status='confirmed') AS confirmed_count,
                (SELECT COUNT(*) FROM appointments WHERE status='completed') AS completed_count,
                (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date)=CURDATE()) AS today_count,
                (SELECT COUNT(*) FROM users WHERE role='patient') AS total_patients,
                (SELECT COUNT(*) FROM appointments) AS total_appointments,
                (SELECT COUNT(*) FROM payments WHERE status='pending') AS pending_payments,
                (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid') AS total_revenue
        ");
        $stats = $statsResult->fetch_assoc() ?: [];

        // Mirrors sp_get_all_appointments
        $apptResult = $db->query("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes,
                   CONCAT(u.first_name, ' ', u.last_name) AS patient_name,
                   u.email AS patient_email, u.phone AS patient_phone,
                   CONCAT(d.first_name, ' ', d.last_name) AS dentist_name,
                   s.name AS service_name, s.price
            FROM appointments a
            JOIN users u ON a.patient_id = u.id
            JOIN dentists d ON a.dentist_id = d.id
            JOIN services s ON a.service_id = s.id
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $appointments = $apptResult->fetch_all(MYSQLI_ASSOC);

        $today = date('Y-m-d');
        $today_appointments = array_filter($appointments, fn($a) => $a['appointment_date'] === $today);
        require __DIR__ . '/../views/admin/dashboard.php';
    }

    public function appointments() {
        requireStaffOrAdmin();
        $filter = sanitize($_GET['status'] ?? '');
        $db = getDB();

        $result = $db->query("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes,
                   CONCAT(u.first_name, ' ', u.last_name) AS patient_name,
                   u.email AS patient_email, u.phone AS patient_phone,
                   CONCAT(d.first_name, ' ', d.last_name) AS dentist_name,
                   s.name AS service_name, s.price
            FROM appointments a
            JOIN users u ON a.patient_id = u.id
            JOIN dentists d ON a.dentist_id = d.id
            JOIN services s ON a.service_id = s.id
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $appointments = $result->fetch_all(MYSQLI_ASSOC);

        if ($filter) {
            $appointments = array_filter($appointments, fn($a) => $a['status'] === $filter);
        }
        require __DIR__ . '/../views/admin/appointments.php';
    }

    public function patients() {
        requireStaffOrAdmin();
        $db = getDB();
        $result = $db->query("
            SELECT id, first_name, last_name, email, phone, created_at
            FROM users
            WHERE role = 'patient'
            ORDER BY created_at DESC
        ");
        $patients = $result->fetch_all(MYSQLI_ASSOC);
        require __DIR__ . '/../views/admin/patients.php';
    }

    /**
     * AJAX endpoint: returns a single patient's profile info, appointment
     * stats, and recent appointment history as JSON. Used by the modal on
     * the Patients page (?page=admin_patient_details&id=123).
     */
    public function patientDetails() {
        requireStaffOrAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid patient id.']);
            exit;
        }

        $db = getDB();

        // Basic profile
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, phone, created_at
            FROM users
            WHERE id = ? AND role = 'patient'
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$patient) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Patient not found.']);
            exit;
        }

        // Appointment stats
        $stmt = $db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'completed') AS completed,
                SUM(status IN ('pending','confirmed')) AS upcoming,
                SUM(status = 'cancelled') AS cancelled,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN s.price ELSE 0 END), 0) AS total_spent
            FROM appointments a
            LEFT JOIN services s ON a.service_id = s.id
            WHERE a.patient_id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Recent appointment history (most recent 10)
        $stmt = $db->prepare("
            SELECT a.appointment_date, a.appointment_time, a.status,
                   CONCAT(d.first_name, ' ', d.last_name) AS dentist_name,
                   s.name AS service_name
            FROM appointments a
            JOIN dentists d ON a.dentist_id = d.id
            JOIN services s ON a.service_id = s.id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 10
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        header('Content-Type: application/json');
        echo json_encode([
            'patient' => [
                'id'         => (int)$patient['id'],
                'name'       => $patient['first_name'] . ' ' . $patient['last_name'],
                'initials'   => strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)),
                'email'      => $patient['email'],
                'phone'      => $patient['phone'] ?: '—',
                'created_at' => formatDate($patient['created_at']),
            ],
            'stats' => [
                'total'       => (int)($stats['total'] ?? 0),
                'completed'   => (int)($stats['completed'] ?? 0),
                'upcoming'    => (int)($stats['upcoming'] ?? 0),
                'cancelled'   => (int)($stats['cancelled'] ?? 0),
                'total_spent' => (float)($stats['total_spent'] ?? 0),
            ],
            'history' => array_map(fn($h) => [
                'date'    => formatDate($h['appointment_date']),
                'time'    => $h['appointment_time'],
                'dentist' => $h['dentist_name'],
                'service' => $h['service_name'],
                'status'  => $h['status'],
            ], $history),
        ]);
        exit;
    }

    public function updateStatus() {
    requireStaffOrAdmin();
    $id     = (int)($_POST['id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');
    $valid  = ['pending','confirmed','completed','cancelled'];

    if (!$id || !in_array($status, $valid)) {
        redirect('admin_appointments', 'Invalid request.', 'error');
    }

    $db = getDB();
    $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
    $stmt->close();

    // Send confirmation email only when the appointment is being confirmed
    if ($status === 'confirmed') {
        $stmt = $db->prepare("
            SELECT u.email, CONCAT(u.first_name,' ',u.last_name) AS patient_name,
                   a.appointment_date, a.appointment_time,
                   CONCAT(d.first_name,' ',d.last_name) AS dentist_name,
                   s.name AS service_name
            FROM appointments a
            JOIN users u    ON a.patient_id = u.id
            JOIN dentists d ON a.dentist_id = d.id
            JOIN services s ON a.service_id = s.id
            WHERE a.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $appt = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($appt) {
            sendAppointmentEmail(
                $appt['email'],
                $appt['patient_name'],
                $appt['appointment_date'],
                $appt['appointment_time'],
                $appt['dentist_name'],
                $appt['service_name']
            );
        }
    }

    redirect('admin_appointments', 'Status updated successfully.', 'success');
}

    public function dentists() {
        requireAdmin();
        $db = getDB();
        $result = $db->query("
            SELECT id, first_name, last_name, specialization, email, phone, is_active
            FROM dentists ORDER BY first_name
        ");
        $dentists = $result->fetch_all(MYSQLI_ASSOC);
        require __DIR__ . '/../views/admin/dentists.php';
    }

    /**
     * AJAX endpoint: returns a single dentist's profile info and
     * appointment/patient stats as JSON. Used by the modal on the
     * Dentists page (?page=admin_dentist_details&id=123).
     */
    public function dentistDetails() {
        requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid dentist id.']);
            exit;
        }

        $db = getDB();

        // Basic profile
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, specialization, email, phone, is_active
            FROM dentists
            WHERE id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $dentist = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$dentist) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Dentist not found.']);
            exit;
        }

        // Appointment + patient stats
        $stmt = $db->prepare("
            SELECT
                COUNT(*) AS total,
                COUNT(DISTINCT patient_id) AS total_patients,
                SUM(status = 'completed') AS completed,
                SUM(status IN ('pending','confirmed')) AS upcoming,
                SUM(status = 'cancelled') AS cancelled
            FROM appointments
            WHERE dentist_id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Recent appointment history (most recent 10)
        $stmt = $db->prepare("
            SELECT a.appointment_date, a.appointment_time, a.status,
                   CONCAT(u.first_name, ' ', u.last_name) AS patient_name,
                   s.name AS service_name
            FROM appointments a
            JOIN users u    ON a.patient_id = u.id
            JOIN services s ON a.service_id = s.id
            WHERE a.dentist_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 10
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        header('Content-Type: application/json');
        echo json_encode([
            'dentist' => [
                'id'             => (int)$dentist['id'],
                'name'           => 'Dr. ' . $dentist['first_name'] . ' ' . $dentist['last_name'],
                'initials'       => strtoupper(substr($dentist['first_name'], 0, 1) . substr($dentist['last_name'], 0, 1)),
                'specialization' => $dentist['specialization'] ?: '—',
                'email'          => $dentist['email'] ?: '—',
                'phone'          => $dentist['phone'] ?: '—',
                'is_active'      => (bool)$dentist['is_active'],
            ],
            'stats' => [
                'total_patients' => (int)($stats['total_patients'] ?? 0),
                'total'          => (int)($stats['total'] ?? 0),
                'completed'      => (int)($stats['completed'] ?? 0),
                'upcoming'       => (int)($stats['upcoming'] ?? 0),
                'cancelled'      => (int)($stats['cancelled'] ?? 0),
            ],
            'history' => array_map(fn($h) => [
                'date'    => formatDate($h['appointment_date']),
                'time'    => $h['appointment_time'],
                'patient' => $h['patient_name'],
                'service' => $h['service_name'],
                'status'  => $h['status'],
            ], $history),
        ]);
        exit;
    }

    public function addDentist() {
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin_dentists');
        $first_name     = sanitize($_POST['first_name'] ?? '');
        $last_name      = sanitize($_POST['last_name'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');
        $email          = sanitize($_POST['email'] ?? '');
        $phone          = sanitize($_POST['phone'] ?? '');
        if (empty($first_name) || empty($last_name)) redirect('admin_dentists','Name is required.','error');

        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO dentists (first_name, last_name, specialization, email, phone)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssss', $first_name, $last_name, $specialization, $email, $phone);
        $stmt->execute();
        $stmt->close();

        redirect('admin_dentists', 'Dentist added successfully!', 'success');
    }

    public function editDentist() {
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('admin_dentists');
        $id             = (int)($_POST['id'] ?? 0);
        $first_name     = sanitize($_POST['first_name'] ?? '');
        $last_name      = sanitize($_POST['last_name'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');
        $email          = sanitize($_POST['email'] ?? '');
        $phone          = sanitize($_POST['phone'] ?? '');
        $is_active      = (int)($_POST['is_active'] ?? 1);
        if (!$id || empty($first_name) || empty($last_name)) redirect('admin_dentists','Invalid data.','error');

        $db = getDB();
        $stmt = $db->prepare("
            UPDATE dentists
            SET first_name=?, last_name=?, specialization=?, email=?, phone=?, is_active=?
            WHERE id = ?
        ");
        $stmt->bind_param('sssssii', $first_name, $last_name, $specialization, $email, $phone, $is_active, $id);
        $stmt->execute();
        $stmt->close();

        redirect('admin_dentists', 'Dentist updated successfully!', 'success');
    }

    public function deleteDentist() {
        requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) redirect('admin_dentists','Invalid dentist.','error');

        $db = getDB();
        $stmt = $db->prepare("UPDATE dentists SET is_active = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        redirect('admin_dentists', 'Dentist deactivated.', 'info');
    }

    /**
     * Lists all staff accounts for the admin "Staff" management page.
     */
    public function staff() {
        requireAdmin();
        $db = getDB();
        $result = $db->query("
            SELECT id, first_name, last_name, email, phone, status, created_at
            FROM users
            WHERE role = 'staff'
            ORDER BY created_at DESC
        ");
        $staff = $result->fetch_all(MYSQLI_ASSOC);
        require __DIR__ . '/../views/admin/staff.php';
    }

    /**
     * Handles the "Add New Staff" modal form. Creates the account with
     * a generated temporary password (same pattern as approveReset()) —
     * the staff member logs in with it once and is forced to set a
     * real password, via the existing temp_password flow in
     * AuthController::login().
     */
    public function addStaff() {
        requireAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('admin_staff');
        }

        $first = sanitize($_POST['first_name'] ?? '');
        $last  = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');

        if (!$first || !$last || !$email) {
            redirect('admin_staff', 'Please fill in all required fields.', 'error');
        }

        $db = getDB();

        $chk = $db->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($exists) {
            redirect('admin_staff', 'That email is already registered.', 'error');
        }

        // Matches how approveReset() creates temp passwords: the temp
        // password's hash goes straight into the real password column,
        // since login() only checks that column (not temp_password).
        $temp   = 'Temp@' . rand(1000, 9999);
        $hashed = password_hash($temp, PASSWORD_BCRYPT);

        $stmt = $db->prepare("
            INSERT INTO users (first_name, last_name, email, password, phone, role, is_verified, status)
            VALUES (?, ?, ?, ?, ?, 'staff', 1, 'approved')
        ");
        $stmt->bind_param('sssss', $first, $last, $email, $hashed, $phone);
        $stmt->execute();
        $stmt->close();

        redirect('admin_staff', "Staff account created! Temporary password: {$temp}", 'success');
    }

    /**
     * Activate/deactivate a staff account (mirrors the Deactivate/Reactivate
     * buttons in staff.php). Deactivated staff are blocked at login by the
     * existing status !== 'approved' check in AuthController::login().
     */
    public function updateStaffStatus() {
        requireAdmin();

        $id     = (int)($_GET['id'] ?? 0);
        $action = $_GET['action'] ?? '';

        if (!in_array($action, ['approved', 'rejected']) || $id <= 0) {
            redirect('admin_staff', 'Invalid action.', 'error');
        }

        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'staff'");
        $stmt->bind_param('si', $action, $id);
        $stmt->execute();
        $stmt->close();

        $msg = $action === 'approved' ? 'Staff account reactivated.' : 'Staff account deactivated.';
        redirect('admin_staff', $msg, 'success');
    }

    /**
     * Permanently deletes a staff account.
     */
    public function deleteStaff() {
        requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            redirect('admin_staff', 'Invalid staff id.', 'error');
        }

        $db = getDB();
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'staff'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        redirect('admin_staff', 'Staff account deleted.', 'success');
    }

    public function payments() {
        requireStaffOrAdmin();
        $filter = sanitize($_GET['status'] ?? '');
        $db = getDB();

        $result = $db->query("
            SELECT p.id, p.amount, p.method, p.status,
                   p.reference_no, p.notes, p.paid_at, p.created_at,
                   CONCAT(u.first_name,' ',u.last_name) AS patient_name,
                   u.email AS patient_email,
                   a.appointment_date, a.appointment_time,
                   s.name AS service_name,
                   CONCAT(d.first_name,' ',d.last_name) AS dentist_name,
                   p.appointment_id
            FROM payments p
            JOIN users u ON p.patient_id = u.id
            JOIN appointments a ON p.appointment_id = a.id
            JOIN services s ON a.service_id = s.id
            JOIN dentists d ON a.dentist_id = d.id
            ORDER BY p.created_at DESC
        ");
        $payments = $result->fetch_all(MYSQLI_ASSOC);

        if ($filter) {
            $payments = array_filter($payments, fn($p) => $p['status'] === $filter);
        }
        require __DIR__ . '/../views/admin/payments.php';
    }

    public function updatePayment() {
        requireStaffOrAdmin();
        $id     = (int)($_POST['id'] ?? 0);
        $status = sanitize($_POST['status'] ?? '');
        $notes  = sanitize($_POST['notes'] ?? '');
        $valid  = ['pending','paid','refunded'];
        if (!$id || !in_array($status, $valid)) redirect('admin_payments','Invalid request.','error');

        // Mirrors sp_update_payment_status: paid_at only advances when
        // newly marking as paid, and notes only overwrite when non-empty.
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE payments
            SET status = ?,
                paid_at = IF(? = 'paid', NOW(), paid_at),
                notes = IF(? != '', ?, notes)
            WHERE id = ?
        ");
        $stmt->bind_param('ssssi', $status, $status, $notes, $notes, $id);
        $stmt->execute();
        $stmt->close();

        redirect('admin_payments', 'Payment status updated.', 'success');
    }
    public function deletePatient() {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) redirect('admin_patients', 'Invalid patient.', 'error');

    $db = getDB();

    // Delete dependent rows first, in the correct order, so this works
    // regardless of whether the database's own foreign key constraints
    // are set to cascade. feedback references appointments, so it must
    // go before appointments are removed.
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("DELETE f FROM feedback f JOIN appointments a ON f.appointment_id = a.id WHERE a.patient_id = ? OR f.patient_id = ?");
        $stmt->bind_param('ii', $id, $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM payments WHERE patient_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM appointments WHERE patient_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $db->commit();
        redirect('admin_patients', 'Patient deleted successfully.', 'success');
    } catch (\Throwable $e) {
        $db->rollback();
        redirect('admin_patients', 'Could not delete patient. Please try again or contact support.', 'error');
    }
}
public function calendar() {
        requireStaffOrAdmin();
        $db = getDB();
        $res = $db->query("
            SELECT a.appointment_date, a.appointment_time, a.status,
                   CONCAT(u.first_name,' ',u.last_name) AS patient_name,
                   CONCAT(d.first_name,' ',d.last_name) AS dentist_name,
                   s.name AS service_name
            FROM appointments a
            JOIN users u ON a.patient_id = u.id
            JOIN dentists d ON a.dentist_id = d.id
            JOIN services s ON a.service_id = s.id
            ORDER BY a.appointment_date, a.appointment_time
        ");
        $allAppts = $res->fetch_all(MYSQLI_ASSOC);
        require __DIR__ . '/../views/admin/calendar.php';
    }

    public function reports() {
        requireAdmin();
        $db = getDB();

        $range = $_GET['range'] ?? 'all';
        if (!in_array($range, ['all', 'today', 'week', 'month', 'year'])) {
            $range = 'all';
        }

        // Build the date filter and a human-readable label used both
        // on-screen and in the printed report header, so what's on paper
        // always matches what the admin selected.
        switch ($range) {
            case 'today':
                $dateWhere  = "DATE(a.appointment_date) = CURDATE()";
                $rangeLabel = 'Today — ' . date('F j, Y');
                break;
            case 'week':
                $dateWhere  = "YEARWEEK(a.appointment_date, 1) = YEARWEEK(CURDATE(), 1)";
                $rangeLabel = 'This Week — ' . date('M j', strtotime('monday this week')) . ' to ' . date('M j, Y', strtotime('sunday this week'));
                break;
            case 'month':
                $dateWhere  = "YEAR(a.appointment_date) = YEAR(CURDATE()) AND MONTH(a.appointment_date) = MONTH(CURDATE())";
                $rangeLabel = 'This Month — ' . date('F Y');
                break;
            case 'year':
                $dateWhere  = "YEAR(a.appointment_date) = YEAR(CURDATE())";
                $rangeLabel = 'This Year — ' . date('Y');
                break;
            default:
                $dateWhere  = "1=1";
                $rangeLabel = 'All Time';
        }

        // Appointments by status, filtered to the selected range
        $r1 = $db->query("SELECT a.status, COUNT(*) as cnt FROM appointments a WHERE $dateWhere GROUP BY a.status");
        $byStatus = $r1->fetch_all(MYSQLI_ASSOC);

        // Trend chart: bucketing changes depending on the range, since
        // "last 6 months" doesn't make sense once the admin narrows to
        // Today or This Week — each range gets the bucket size that
        // actually shows meaningful variation within it.
        if ($range === 'today') {
            $trendTitle = 'Appointments Today (by Time Slot)';
            $r2 = $db->query("SELECT a.appointment_time as label_key, a.appointment_time as sort_key, COUNT(*) as cnt FROM appointments a WHERE $dateWhere GROUP BY label_key, sort_key ORDER BY sort_key ASC");
            $byMonth = array_map(fn($r) => ['month' => date('g:i A', strtotime($r['label_key'])), 'cnt' => (int)$r['cnt']], $r2->fetch_all(MYSQLI_ASSOC));
        } elseif ($range === 'week') {
            $trendTitle = 'Appointments This Week';
            $r2 = $db->query("SELECT DATE_FORMAT(a.appointment_date,'%a') as label_key, DATE_FORMAT(a.appointment_date,'%w') as sort_key, COUNT(*) as cnt FROM appointments a WHERE $dateWhere GROUP BY label_key, sort_key ORDER BY sort_key ASC");
            $byMonth = array_map(fn($r) => ['month' => $r['label_key'], 'cnt' => (int)$r['cnt']], $r2->fetch_all(MYSQLI_ASSOC));
        } elseif ($range === 'month') {
            $trendTitle = 'Appointments This Month (by Day)';
            $r2 = $db->query("SELECT DAY(a.appointment_date) as label_key, DAY(a.appointment_date) as sort_key, COUNT(*) as cnt FROM appointments a WHERE $dateWhere GROUP BY label_key, sort_key ORDER BY sort_key ASC");
            $byMonth = array_map(fn($r) => ['month' => (string)$r['label_key'], 'cnt' => (int)$r['cnt']], $r2->fetch_all(MYSQLI_ASSOC));
        } elseif ($range === 'year') {
            $trendTitle = 'Appointments This Year (by Month)';
            $r2 = $db->query("SELECT DATE_FORMAT(a.appointment_date,'%b') as label_key, MONTH(a.appointment_date) as sort_key, COUNT(*) as cnt FROM appointments a WHERE $dateWhere GROUP BY label_key, sort_key ORDER BY sort_key ASC");
            $byMonth = array_map(fn($r) => ['month' => $r['label_key'], 'cnt' => (int)$r['cnt']], $r2->fetch_all(MYSQLI_ASSOC));
        } else {
            $trendTitle = 'Appointments (Last 6 Months)';
            $r2 = $db->query("
                SELECT DATE_FORMAT(a.appointment_date,'%b %Y') as label_key,
                       DATE_FORMAT(a.appointment_date,'%Y-%m') as sort_key,
                       COUNT(*) as cnt
                FROM appointments a
                WHERE a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY sort_key, label_key ORDER BY sort_key ASC
            ");
            $byMonth = array_map(fn($r) => ['month' => $r['label_key'], 'cnt' => (int)$r['cnt']], $r2->fetch_all(MYSQLI_ASSOC));
        }

        // Top services, filtered to the selected range
        $r3 = $db->query("
            SELECT s.name, COUNT(*) as cnt
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            WHERE $dateWhere
            GROUP BY s.name ORDER BY cnt DESC LIMIT 5
        ");
        $topServices = $r3->fetch_all(MYSQLI_ASSOC);

        // Revenue trend: same bucketing logic as the appointments trend,
        // completed appointments only
        if ($range === 'today') {
            $revenueTitle = 'Revenue Today (by Time Slot)';
            $r4 = $db->query("SELECT a.appointment_time as label_key, a.appointment_time as sort_key, SUM(s.price) as revenue FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.status='completed' AND $dateWhere GROUP BY label_key, sort_key ORDER BY sort_key ASC");
            $revenueByMonth = array_map(fn($r) => ['month' => date('g:i A', strtotime($r['label_key'])), 'revenue' => (float)$r['revenue']], $r4->fetch_all(MYSQLI_ASSOC));
        } elseif ($range === 'week') {
            $revenueTitle = 'Revenue This Week';
            $r4 = $db->query("SELECT DATE_FORMAT(a.appointment_date,'%a') as label_key, DATE_FORMAT(a.appointment_date,'%w') as sort_key, SUM(s.price) as revenue FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.status='completed' AND $dateWhere GROUP BY label_key, sort_key ORDER BY sort_key ASC");
            $revenueByMonth = array_map(fn($r) => ['month' => $r['label_key'], 'revenue' => (float)$r['revenue']], $r4->fetch_all(MYSQLI_ASSOC));
        } elseif ($range === 'month') {
            $revenueTitle = 'Revenue This Month (by Day)';
            $r4 = $db->query("SELECT DAY(a.appointment_date) as label_key, DAY(a.appointment_date) as sort_key, SUM(s.price) as revenue FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.status='completed' AND $dateWhere GROUP BY label_key, sort_key ORDER BY sort_key ASC");
            $revenueByMonth = array_map(fn($r) => ['month' => (string)$r['label_key'], 'revenue' => (float)$r['revenue']], $r4->fetch_all(MYSQLI_ASSOC));
        } elseif ($range === 'year') {
            $revenueTitle = 'Revenue This Year (by Month)';
            $r4 = $db->query("SELECT DATE_FORMAT(a.appointment_date,'%b') as label_key, MONTH(a.appointment_date) as sort_key, SUM(s.price) as revenue FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.status='completed' AND $dateWhere GROUP BY label_key, sort_key ORDER BY sort_key ASC");
            $revenueByMonth = array_map(fn($r) => ['month' => $r['label_key'], 'revenue' => (float)$r['revenue']], $r4->fetch_all(MYSQLI_ASSOC));
        } else {
            $revenueTitle = 'Revenue (Last 6 Months)';
            $r4 = $db->query("
                SELECT DATE_FORMAT(a.appointment_date,'%b %Y') as label_key,
                       DATE_FORMAT(a.appointment_date,'%Y-%m') as sort_key,
                       SUM(s.price) as revenue
                FROM appointments a
                JOIN services s ON a.service_id = s.id
                WHERE a.status = 'completed'
                  AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY sort_key, label_key ORDER BY sort_key ASC
            ");
            $revenueByMonth = array_map(fn($r) => ['month' => $r['label_key'], 'revenue' => (float)$r['revenue']], $r4->fetch_all(MYSQLI_ASSOC));
        }

        // Summary cards — all scoped to the same range so the printed
        // numbers stay internally consistent (e.g. "Total Patients" means
        // patients seen within the selected period, not all-time).
        $r5 = $db->query("SELECT COUNT(*) as total FROM appointments a WHERE $dateWhere");
        $totalAppts = $r5->fetch_assoc()['total'];

        $r6 = $db->query("SELECT COALESCE(SUM(s.price),0) as rev FROM appointments a JOIN services s ON a.service_id=s.id WHERE a.status='completed' AND $dateWhere");
        $totalRevenue = $r6->fetch_assoc()['rev'];

        $r7 = $db->query("SELECT COUNT(DISTINCT a.patient_id) as total FROM appointments a WHERE $dateWhere");
        $totalPatients = $r7->fetch_assoc()['total'];

        $r8 = $db->query("SELECT COUNT(*) as total FROM appointments a WHERE a.status='completed' AND $dateWhere");
        $totalCompleted = $r8->fetch_assoc()['total'];

        require __DIR__ . '/../views/admin/reports.php';
    }

    public function feedback() {
        requireAdmin();
        $db = getDB();

        $stmt = $db->query("
            SELECT f.id, f.rating, f.comment, f.created_at, f.is_read,
                   CONCAT(u.first_name,' ',u.last_name) AS patient_name,
                   u.first_name AS patient_first_name,
                   u.last_name AS patient_last_name,
                   u.avatar AS patient_avatar,
                   s.name AS service_name,
                   CONCAT(d.first_name,' ',d.last_name) AS dentist_name,
                   a.appointment_date
            FROM feedback f
            JOIN users u ON f.patient_id = u.id
            JOIN appointments a ON f.appointment_id = a.id
            JOIN services s ON a.service_id = s.id
            JOIN dentists d ON a.dentist_id = d.id
            ORDER BY f.is_read ASC, f.created_at DESC
        ");
        $allFeedback = $stmt->fetch_all(MYSQLI_ASSOC);

        $totalFeedback = count($allFeedback);
        $avgRating = $totalFeedback > 0
            ? round(array_sum(array_column($allFeedback, 'rating')) / $totalFeedback, 1)
            : 0;
        $ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($allFeedback as $f) {
            $ratingCounts[(int)$f['rating']]++;
        }

        require __DIR__ . '/../views/admin/feedback.php';
    }

    public function markFeedbackRead() {
        requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) redirect('admin_feedback', 'Invalid feedback.', 'error');
        $db = getDB();
        $stmt = $db->prepare("UPDATE feedback SET is_read = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        redirect('admin_feedback', 'Feedback marked as read.', 'success');
    }

    public function notifications() {
        requireStaffOrAdmin();
        $db = getDB();

        $r1 = $db->query("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.created_at,
                   CONCAT(u.first_name,' ',u.last_name) AS patient_name,
                   CONCAT(d.first_name,' ',d.last_name) AS dentist_name,
                   s.name AS service_name
            FROM appointments a
            JOIN users u ON a.patient_id = u.id
            JOIN dentists d ON a.dentist_id = d.id
            JOIN services s ON a.service_id = s.id
            WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY a.created_at DESC
        ");
        $newAppts = $r1->fetch_all(MYSQLI_ASSOC);

        $r2 = $db->query("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status,
                   CONCAT(u.first_name,' ',u.last_name) AS patient_name,
                   CONCAT(d.first_name,' ',d.last_name) AS dentist_name,
                   s.name AS service_name
            FROM appointments a
            JOIN users u ON a.patient_id = u.id
            JOIN dentists d ON a.dentist_id = d.id
            JOIN services s ON a.service_id = s.id
            WHERE a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
              AND a.status IN ('pending','confirmed')
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
        ");
        $upcomingAppts = $r2->fetch_all(MYSQLI_ASSOC);

        $r3 = $db->query("
            SELECT a.id, a.appointment_date, a.appointment_time,
                   CONCAT(u.first_name,' ',u.last_name) AS patient_name,
                   s.name AS service_name
            FROM appointments a
            JOIN users u ON a.patient_id = u.id
            JOIN services s ON a.service_id = s.id
            WHERE a.status = 'cancelled'
              AND a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY a.appointment_date DESC
        ");
        $cancelledAppts = $r3->fetch_all(MYSQLI_ASSOC);

        require __DIR__ . '/../views/admin/notifications.php';
    }
    public function updatePatientStatus() {
    if (!isAdmin() && !isStaff()) redirect('login');

    $id     = (int)($_GET['id'] ?? 0);
    $action = $_GET['action'] ?? '';

    if (!in_array($action, ['approved', 'rejected']) || $id <= 0) {
        redirect('pending_patients', 'Invalid action.', 'error');
    }

    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $action, $id);
    $stmt->execute();
    $stmt->close();

    $msg = $action === 'approved' ? 'Patient approved successfully.' : 'Patient rejected.';
    redirect('pending_patients', $msg, 'success');
}

public function pendingPatients() {
    if (!isAdmin() && !isStaff()) redirect('login');
    $db = getDB();
    $result = $db->query("
        SELECT id, first_name, last_name, email, phone, created_at
        FROM users
        WHERE role = 'patient' AND status = 'pending'
        ORDER BY created_at DESC
    ");
    $patients = $result->fetch_all(MYSQLI_ASSOC);
    require __DIR__ . '/../views/admin/pending_patients.php';
}
public function resetRequests() {
    requireStaffOrAdmin();
    $db = getDB();
    $result = $db->query("
        SELECT pr.id, u.first_name, u.last_name, u.email, u.phone, pr.created_at
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.status = 'pending'
        ORDER BY pr.created_at DESC
    ");
    $requests = $result->fetch_all(MYSQLI_ASSOC);
    require __DIR__ . '/../views/admin/reset_requests.php';
}

public function approveReset() {
        requireStaffOrAdmin();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) redirect('admin_reset_requests', 'Invalid request.', 'error');
 
        $temp   = 'Temp@' . rand(1000, 9999);
        $hashed = password_hash($temp, PASSWORD_BCRYPT);
 
        // Mirrors sp_approve_password_reset: updates the user's password
        // and marks the reset request approved in one go, keeping the
        // plain-text temp password only in password_resets so the patient
        // can see it on the forgot-password page.
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE users u
            JOIN password_resets pr ON pr.user_id = u.id
            SET u.password = ?,
                pr.status  = 'approved',
                pr.temp_password_plain = ?
            WHERE pr.id = ?
        ");
        $stmt->bind_param('ssi', $hashed, $temp, $id);
        $stmt->execute();
        $stmt->close();
 
        redirect('admin_reset_requests', 'Approved! Temporary password generated: ' . $temp, 'success');
    }
 
}
