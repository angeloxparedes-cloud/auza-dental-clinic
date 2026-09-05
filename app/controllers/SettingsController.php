<?php
require_once __DIR__ . '/../config.php';

class SettingsController {

    public function index() {
        requireLogin();
        $user_id = $_SESSION['user_id'];
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, phone, role, avatar, temp_password
            FROM users WHERE id = ?
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $user = $rows[0] ?? [];
        require __DIR__ . '/../views/shared/settings.php';
    }

    public function updateProfile() {
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('settings');

        $user_id    = $_SESSION['user_id'];
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name  = sanitize($_POST['last_name'] ?? '');
        $phone      = sanitize($_POST['phone'] ?? '');

        if (empty($first_name) || empty($last_name)) {
            redirect('settings', 'Name fields are required.', 'error');
        }

        $db = getDB();

        // Handle avatar upload
        if (!empty($_FILES['avatar']['name'])) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            if (!in_array($_FILES['avatar']['type'], $allowed)) {
                redirect('settings', 'Only JPG, PNG, GIF, WEBP images are allowed.', 'error');
            }
            if ($_FILES['avatar']['size'] > $maxSize) {
                redirect('settings', 'Image must be under 2MB.', 'error');
            }

            $ext      = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $dest     = __DIR__ . '/../../public/uploads/avatars/' . $filename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                // Delete old avatar if exists
                $lookup = $db->prepare("SELECT avatar FROM users WHERE id = ?");
                $lookup->bind_param('i', $user_id);
                $lookup->execute();
                $oldAvatar = $lookup->get_result()->fetch_assoc()['avatar'] ?? '';
                $lookup->close();

                if ($oldAvatar) {
                    $oldPath = __DIR__ . '/../../public/uploads/avatars/' . $oldAvatar;
                    if (file_exists($oldPath)) unlink($oldPath);
                }

                $updateAvatar = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $updateAvatar->bind_param('si', $filename, $user_id);
                $updateAvatar->execute();
                $updateAvatar->close();

                $_SESSION['avatar'] = $filename;
            }
        }

        $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
        $stmt->bind_param('sssi', $first_name, $last_name, $phone, $user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name']  = $last_name;

        redirect('settings', 'Profile updated successfully!', 'success');
    }

    public function changePassword() {
        requireLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('settings');

        $user_id  = $_SESSION['user_id'];
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($current) || empty($new_pass) || empty($confirm)) {
            redirect('settings', 'All password fields are required.', 'error');
        }
        if ($new_pass !== $confirm) {
            redirect('settings', 'New passwords do not match.', 'error');
        }
        if (strlen($new_pass) < 8) {
            redirect('settings', 'Password must be at least 8 characters.', 'error');
        }

        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, password, phone, role, is_verified, status
            FROM users
            WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
            LIMIT 1
        ");
        $email = $_SESSION['email'];
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($rows) || !password_verify($current, $rows[0]['password'])) {
            redirect('settings', 'Current password is incorrect.', 'error');
        }

        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $update = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param('si', $hashed, $user_id);
        $update->execute();
        $update->close();

        redirect('settings', 'Password changed successfully!', 'success');
    }
}