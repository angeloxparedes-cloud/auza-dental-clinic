<?php
require_once __DIR__ . '/../config.php';

class AuthController {

    public function login() {
        if (isLoggedIn()) {
            redirect(isAdmin() ? 'admin_dashboard' : 'patient_dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email    = sanitize($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Please fill in all fields.';
            } else {
                $db = getDB();
                $stmt = $db->prepare("
                    SELECT id, first_name, last_name, email, password, phone, role, is_verified, status
                    FROM users
                    WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
                    LIMIT 1
                ");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                if (empty($rows)) {
                    $error = 'Invalid email or password.';
                } else {
                    $user = $rows[0];

                    // ── CHECK TEMP PASSWORD FIRST ────────────────────────────
                    // If admin set a temp password, check if patient is using it
                    $tempPass = $user['temp_password'] ?? null;
                    $usingTempPassword = false;

                    if ($tempPass && $tempPass !== 'pending') {
                        if (password_verify($password, $tempPass)) {
                            $usingTempPassword = true;
                        }
                    }
                    // ─────────────────────────────────────────────────────────

                    if ($usingTempPassword || password_verify($password, $user['password'])) {

                        // ── ACCOUNT VERIFICATION CHECK ───────────────────────
                        if ($user['role'] === 'patient') {
                            if ($user['status'] === 'pending') {
                                $error = 'Your account is still pending admin approval. Please wait.';
                            } elseif ($user['status'] === 'rejected') {
                                $error = 'Your account has been rejected. Please contact the clinic.';
                            } else {
                                // approved — allow login
                                $this->createSession($user);

                                // ── FORCE PASSWORD RESET IF USING TEMP ──────
                                if ($usingTempPassword) {
                                    $_SESSION['force_reset'] = true;
                                    redirect('reset_password');
                                }
                                // ────────────────────────────────────────────

                                redirect('patient_dashboard');
                            }
                        } elseif ($user['role'] === 'staff') {
                            if ($user['status'] !== 'approved') {
                                $error = 'Your account has been deactivated. Please contact the admin.';
                            } else {
                                $this->createSession($user);

                                // ── FORCE PASSWORD RESET IF USING TEMP ──────
                                if ($usingTempPassword) {
                                    $_SESSION['force_reset'] = true;
                                    redirect('reset_password');
                                }
                                // ────────────────────────────────────────────

                                redirect('admin_dashboard');
                            }
                        } else {
                            // admin — always allow login
                            $this->createSession($user);
                            redirect('admin_dashboard');
                        }
                        // ─────────────────────────────────────────────────────

                    } else {
                        $error = 'Invalid email or password.';
                    }
                }
            }
        }

        require __DIR__ . '/../views/auth/login.php';
    }

    public function register() {
        if (isLoggedIn()) {
            redirect(isAdmin() ? 'admin_dashboard' : 'patient_dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $first_name = sanitize($_POST['first_name'] ?? '');
            $last_name  = sanitize($_POST['last_name'] ?? '');
            $email      = sanitize($_POST['email'] ?? '');
            $phone      = sanitize($_POST['phone'] ?? '');
            $password   = $_POST['password'] ?? '';
            $confirm    = $_POST['confirm_password'] ?? '';

            if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
                $error = 'Please fill in all required fields.';
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $db = getDB();

                // Mirrors sp_register_patient's duplicate-email guard
                $check = $db->prepare("SELECT COUNT(*) AS cnt FROM users WHERE email = ?");
                $check->bind_param('s', $email);
                $check->execute();
                $emailExists = $check->get_result()->fetch_assoc()['cnt'] > 0;
                $check->close();

                if ($emailExists) {
                    $error = 'Email already registered. Please login.';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO users (first_name, last_name, email, password, phone, role, status)
                        VALUES (?, ?, ?, ?, ?, 'patient', 'pending')
                    ");
                    $stmt->bind_param('sssss', $first_name, $last_name, $email, $hashed, $phone);
                    if ($stmt->execute()) {
                        $stmt->close();
                        redirect('login', 'Registration submitted! Please wait for admin approval before logging in.', 'success');
                    } else {
                        $stmt->close();
                        $error = 'Registration failed. Please try again.';
                    }
                }
            }
        }

        require __DIR__ . '/../views/auth/register.php';
    }

    public function logout() {
        session_destroy();
        redirect('login', 'You have been logged out.', 'info');
    }

    // ── PRIVATE HELPER: sets session variables ───────────────────────────────
    private function createSession($user) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name']  = $user['last_name'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['role']       = $user['role'];

        // Fetch fresh data from DB to get avatar and temp_password
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, phone, role, avatar, temp_password
            FROM users WHERE id = ?
        ");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $fresh = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $_SESSION['avatar'] = $fresh[0]['avatar'] ?? '';
    }
    // ─────────────────────────────────────────────────────────────────────────
}