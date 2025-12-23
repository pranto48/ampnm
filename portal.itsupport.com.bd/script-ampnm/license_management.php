<?php
require_once 'includes/auth_check.php';
include 'header.php';
?>

<main id="app">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-white mb-6">License Management</h1>
        <!-- The React app will render here -->
        <div id="react-root-license-management"></div>
    </div>
</main>

<?php include 'footer.php'; ?>