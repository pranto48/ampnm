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
        $stmt = $pdo->query("SELECT id, username, role, user_group, created_at FROM users ORDER BY username ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($users);
        break;

    case 'create_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            $role = $input['role'] ?? 'viewer'; // Default to 'viewer' for new users
            $user_group = !empty($input['user_group']) ? $input['user_group'] : 'default_group';

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
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, user_group) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $role, $user_group]);
            
            echo json_encode(['success' => true, 'message' => 'User created successfully.']);
        }
        break;

    case 'update_user_role': // NEW ACTION
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $input['id'] ?? null;
            $new_role = $input['role'] ?? null;
            $new_group = $input['user_group'] ?? null;

            if (!$id || (empty($new_role) && empty($new_group))) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID and either role or group are required.']);
                exit;
            }

            // Prevent admin from changing their own role or deleting themselves
            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Build fields to update
            $fields = [];
            $params = [];
            if (!empty($new_role)) {
                if ($user && $user['username'] === $_SESSION['username'] && $id == $_SESSION['user_id'] && $new_role !== $_SESSION['user_role']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Cannot change your own role.']);
                    exit;
                }
                $fields[] = "role = ?";
                $params[] = $new_role;
            }
            if (!empty($new_group)) {
                $fields[] = "user_group = ?";
                $params[] = $new_group;
            }
            
            $params[] = $id;
            $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($params);
            
            // If updating current user's group, refresh their session
            if ($user && $user['username'] === $_SESSION['username'] && $id == $_SESSION['user_id'] && !empty($new_group)) {
                $_SESSION['user_group'] = $new_group;
            }
            
            echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
        }
        break;

    case 'update_user_password':
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

            // Prevent admin from changing their OWN password here — use the profile page for that
            if ($id == $_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Cannot change your own password through User Management. Use your profile settings instead.']);
                exit;
            }

            // Confirm target user exists
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$targetUser) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found.']);
                exit;
            }

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $id]);
            echo json_encode(['success' => true, 'message' => "Password updated for user \"{$targetUser['username']}\"."]);
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