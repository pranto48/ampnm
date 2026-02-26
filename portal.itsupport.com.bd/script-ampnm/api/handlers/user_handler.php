<?php
// This file is included by api.php and assumes $pdo, $action, and $input are available.

// Ensure only admin can perform these actions
if ($_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Only admin can manage users.']);
    exit;
}

switch ($action) {
    case 'get_users':
        $stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($users);
        break;

    case 'create_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            $role = $input['role'] ?? 'viewer'; // Default to 'viewer' for new users

            if (empty($username) || empty($password)) {
                http_response_code(400);
                echo json_encode(['error' => 'Username and password are required.']);
                exit;
            }

            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Username already exists.']);
                exit;
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $role]);
            
            echo json_encode(['success' => true, 'message' => 'User created successfully.']);
        }
        break;

    case 'update_user_role': // NEW ACTION
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $new_role = $input['role'] ?? null;

            if (!$id || empty($new_role)) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID and new role are required.']);
                exit;
            }

            // Prevent admin from changing their own role or deleting themselves
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $user['username'] === $_SESSION['username'] && $id == $_SESSION['user_id']) { // Check against session username
                http_response_code(403);
                echo json_encode(['error' => 'Cannot change your own role.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $id]);
            echo json_encode(['success' => true, 'message' => 'User role updated successfully.']);
        }
        break;

    case 'update_user_password': // NEW ACTION
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $new_password = $input['new_password'] ?? null;

            if (!$id || empty($new_password)) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID and new password are required.']);
                exit;
            }
            if (strlen($new_password) < 6) {
                http_response_code(400);
                echo json_encode(['error' => 'New password must be at least 6 characters long.']);
                exit;
            }

            // Prevent admin from changing their own password through this API if they are the target user
            // This is a safety measure, though the frontend prevents it for 'admin' user.
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $user['username'] === $_SESSION['username'] && $id == $_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Cannot change your own password through this interface. Please use the dedicated admin password change page if available.']);
                exit;
            }

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $id]);
            echo json_encode(['success' => true, 'message' => 'User password updated successfully.']);
        }
        break;

    case 'delete_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required.']);
                exit;
            }

            // Prevent admin from deleting themselves
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && $user['username'] === $_SESSION['username'] && $id == $_SESSION['user_id']) { // Check against session username
                http_response_code(403);
                echo json_encode(['error' => 'Cannot delete your own user account.']);
                exit;
            }
            if ($user && $user['username'] === 'admin') { // Also prevent deleting the default 'admin' user
                http_response_code(403);
                echo json_encode(['error' => 'Cannot delete the default admin user.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'User deleted successfully.']);
        }
        break;
}