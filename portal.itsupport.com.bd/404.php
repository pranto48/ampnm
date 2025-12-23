<?php
require_once 'includes/functions.php';
portal_header("Page Not Found - IT Support BD Portal");
?>

<div class="min-h-screen flex items-center justify-center py-16 px-4 sm:px-6 lg:px-8 relative">
    <div class="animated-grid"></div>
    <div class="glass-card p-10 max-w-3xl w-full text-center not-found-card">
        <div class="hero-orb orb-left"></div>
        <div class="hero-orb orb-right"></div>
        <p class="accent-badge inline-flex mb-4"><i class="fas fa-exclamation-triangle"></i> 404</p>
        <h1 class="text-4xl font-bold text-white mb-3">This page drifted offline</h1>
        <p class="text-gray-200 mb-6">The link you followed doesn’t exist. Let’s get you back to monitoring your AMPNM licenses and devices.</p>
        <div class="flex flex-wrap gap-3 justify-center">
            <a href="index.php" class="btn-glass-primary"><i class="fas fa-home mr-2"></i>Home</a>
            <a href="products.php" class="btn-glass-secondary"><i class="fas fa-box-open mr-2"></i>Products</a>
            <a href="support.php" class="btn-glass-secondary"><i class="fas fa-headset mr-2"></i>Support</a>
        </div>
    </div>
</div>

<?php portal_footer(); ?>
