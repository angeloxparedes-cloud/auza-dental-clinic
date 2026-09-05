<?php
require_once __DIR__ . '/../config.php';

class PaymentController {

    public function patientPayments() {
        requirePatient();
        $patient_id = $_SESSION['user_id'];
        $db = getDB();

        $stmt = $db->prepare("
            SELECT p.id, p.amount, p.method, p.status, p.reference_no, p.notes, p.paid_at, p.created_at,
                   a.appointment_date, a.appointment_time, a.status AS appt_status,
                   s.name AS service_name,
                   CONCAT(d.first_name,' ',d.last_name) AS dentist_name
            FROM payments p
            JOIN appointments a ON p.appointment_id = a.id
            JOIN services s ON a.service_id = s.id
            JOIN dentists d ON a.dentist_id = d.id
            WHERE p.patient_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->bind_param('i', $patient_id);
        $stmt->execute();
        $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt2 = $db->prepare("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.notes,
                   CONCAT(d.first_name, ' ', d.last_name) AS dentist_name,
                   d.specialization,
                   s.name AS service_name, s.price, s.duration_minutes
            FROM appointments a
            JOIN dentists d ON a.dentist_id = d.id
            JOIN services s ON a.service_id = s.id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
        ");
        $stmt2->bind_param('i', $patient_id);
        $stmt2->execute();
        $appointments = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        // Only confirmed/completed appointments without an existing payment
        $payable = [];
        $existingStmt = $db->prepare("SELECT * FROM payments WHERE appointment_id = ? LIMIT 1");
        foreach ($appointments as $a) {
            if (in_array($a['status'], ['confirmed', 'completed'])) {
                $apptId = (int)$a['id'];
                $existingStmt->bind_param('i', $apptId);
                $existingStmt->execute();
                $existing = $existingStmt->get_result()->fetch_assoc();
                if (empty($existing)) $payable[] = $a;
            }
        }
        $existingStmt->close();

        require __DIR__ . '/../views/patient/payments.php';
    }

    public function submitPayment() {
        requirePatient();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('patient_payments');

        $patient_id     = $_SESSION['user_id'];
        $appointment_id = (int)($_POST['appointment_id'] ?? 0);
        $amount         = (float)($_POST['amount'] ?? 0);
        $method         = sanitize($_POST['method'] ?? 'cash');
        $reference_no   = sanitize($_POST['reference_no'] ?? '');
        $notes          = sanitize($_POST['notes'] ?? '');

        if (!$appointment_id || $amount <= 0) {
            redirect('patient_payments', 'Invalid payment details.', 'error');
        }

        $valid_methods = ['cash','gcash','maya','card'];
        if (!in_array($method, $valid_methods)) $method = 'cash';

        $db = getDB();

        // Mirrors sp_create_payment's guard: only one non-refunded payment per appointment
        $check = $db->prepare("SELECT COUNT(*) AS cnt FROM payments WHERE appointment_id = ? AND status != 'refunded'");
        $check->bind_param('i', $appointment_id);
        $check->execute();
        $alreadyExists = $check->get_result()->fetch_assoc()['cnt'] > 0;
        $check->close();

        if ($alreadyExists) {
            redirect('patient_payments', 'Payment submission failed. It may already exist.', 'error');
        } else {
            $stmt = $db->prepare("
                INSERT INTO payments (appointment_id, patient_id, amount, method, reference_no, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param('iidsss', $appointment_id, $patient_id, $amount, $method, $reference_no, $notes);
            $stmt->execute();
            $stmt->close();
            redirect('patient_payments', 'Payment submitted! Awaiting admin confirmation.', 'success');
        }
    }
}