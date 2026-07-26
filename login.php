<?php
/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
require_once 'includes/bootstrap.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, password, role, user_group FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Password is correct, start session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['user_role'] = $user['role']; // Store user role in session
            $_SESSION['user_group'] = $user['user_group'] ?: 'default_group'; // Store user group in session
            header('Location: index.php');
            exit;
        } else {
            $error_message = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AMPNM</title>
    <!-- Animated SVG Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><defs><style>@keyframes spin{from{transform-origin:32px 32px;transform:rotate(0deg)}to{transform-origin:32px 32px;transform:rotate(360deg)}}@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}.ring{animation:spin 3s linear infinite}.dot{animation:pulse 1.5s ease-in-out infinite}</style></defs><circle cx='32' cy='32' r='28' fill='%230f172a' stroke='%2306b6d4' stroke-width='3'/><circle cx='32' cy='32' r='18' fill='none' stroke='%2322d3ee' stroke-width='1.5' stroke-dasharray='8 4' class='ring'/><circle cx='32' cy='32' r='9' fill='%2306b6d4'/><circle cx='32' cy='32' r='5' fill='%230f172a' class='dot'/><circle cx='32' cy='11' r='3' fill='%2322d3ee' class='dot'/><circle cx='53' cy='43' r='3' fill='%2322d3ee' class='dot' style='animation-delay:.5s'/><circle cx='11' cy='43' r='3' fill='%2322d3ee' class='dot' style='animation-delay:1s'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="animated-gradient-background flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-blue-600 text-white rounded-2xl shadow-xl shadow-blue-500/30 mx-auto flex items-center justify-center mb-3">
                <i class="fas fa-heartbeat text-2xl animate-pulse"></i>
            </div>
            <div class="flex items-center justify-center gap-2">
                <h1 class="text-3xl font-extrabold text-white">AMPNM</h1>
                <span class="text-xs bg-blue-900/80 text-blue-300 font-bold px-2 py-0.5 rounded border border-blue-700/50">v1.16</span>
            </div>
            <p class="text-xs font-semibold tracking-wider text-slate-400 uppercase mt-1">by IT Support BD</p>
        </div>
        <form method="POST" action="login.php" class="bg-slate-800/70 border border-slate-700 rounded-lg shadow-xl p-8 space-y-6 backdrop-blur-sm">
            <?php if ($error_message): ?>
                <div class="bg-red-500/20 border border-red-500/30 text-red-300 text-sm rounded-lg p-3 text-center">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            <div>
                <label for="username" class="block text-sm font-medium text-slate-300 mb-2">Username</label>
                <input type="text" name="username" id="username" required
                       class="w-full bg-slate-900/50 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white"
                       placeholder="admin">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                <input type="password" name="password" id="password" required
                       class="w-full bg-slate-900/50 border border-slate-600 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:border-transparent text-white"
                       placeholder="password">
            </div>
            <button type="submit"
                    class="w-full px-6 py-3 bg-cyan-600 text-white font-semibold rounded-lg hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
                Sign In
            </button>
        </form>
    </div>
</body>
</html>