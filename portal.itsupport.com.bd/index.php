<?php
require_once 'includes/functions.php';
portal_header("Welcome to IT Support BD Portal");
?>

<div class="relative overflow-hidden">
    <div class="animated-grid"></div>
    <div class="glass-card text-center py-16 px-6 mb-10 tilt-card">
        <div class="tilt-inner relative">
            <span class="accent-badge mx-auto"><i class="fas fa-bolt"></i>Live Network Visibility</span>
            <h1 class="hero-title text-5xl md:text-6xl font-extrabold text-white mt-6 mb-4">
                AMPNM Portal for Real-Time Monitoring
            </h1>
            <p class="hero-subtitle text-xl text-gray-200 mb-8 leading-relaxed">
                Manage Docker AMPNM licenses, track infrastructure health, and unlock premium support from one modern control panel.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4 sm:gap-6 items-center">
                <a href="products.php" class="btn-glass-primary text-lg px-8">
                    <i class="fas fa-shopping-bag mr-2"></i>Browse Licenses
                </a>
                <a href="registration.php" class="btn-glass-secondary text-lg px-8 border border-blue-400">
                    <i class="fas fa-user-plus mr-2"></i>Start Free Account
                </a>
            </div>
            <div class="floating-orb one"></div>
            <div class="floating-orb two"></div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
    <div class="glass-card text-center p-6 tilt-card">
        <div class="tilt-inner">
            <div class="feature-icon mb-4"><i class="fas fa-shield-alt text-4xl text-blue-200"></i></div>
            <h2 class="text-2xl font-semibold mb-2 text-white">Secure Licensing</h2>
            <p class="text-gray-200">Genuine keys, encrypted delivery, and verified activation for every AMPNM deployment.</p>
        </div>
    </div>
    <div class="glass-card text-center p-6 tilt-card">
        <div class="tilt-inner">
            <div class="feature-icon mb-4"><i class="fas fa-mobile-alt text-4xl text-green-200"></i></div>
            <h2 class="text-2xl font-semibold mb-2 text-white">Mobile-Ready Portal</h2>
            <p class="text-gray-200">Responsive dashboards, thumb-friendly actions, and clean layouts for on-the-go visibility.</p>
        </div>
    </div>
    <div class="glass-card text-center p-6 tilt-card">
        <div class="tilt-inner">
            <div class="feature-icon mb-4"><i class="fas fa-headset text-4xl text-purple-200"></i></div>
            <h2 class="text-2xl font-semibold mb-2 text-white">Dedicated Support</h2>
            <p class="text-gray-200">Direct access to our support engineers, ticket follow-ups, and deployment best practices.</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-12">
    <div class="glass-card p-8 tilt-card">
        <div class="tilt-inner space-y-4">
            <h3 class="section-heading text-white">Docker AMPNM Advantage</h3>
            <p class="text-gray-200 leading-relaxed">Install the AMPNM Docker app directly from the portal and keep your network probes updated effortlessly. Automated license syncing ensures your cPanel-hosted deployment stays authorized without extra steps.</p>
            <ul class="text-gray-200 space-y-2 list-disc list-inside">
                <li>One-click license downloads for <strong>portal.itsupport.com.bd/docker-ampnm/</strong>.</li>
                <li>Versioned builds with changelogs for quick rollbacks.</li>
                <li>Role-based access to invite teammates securely.</li>
            </ul>
            <a href="download_ampnm_docker_project.php" class="btn-glass-primary inline-flex items-center mt-2">
                <i class="fas fa-download mr-2"></i>Download Docker Package
            </a>
        </div>
    </div>
    <div class="glass-card p-8 tilt-card">
        <div class="tilt-inner space-y-4">
            <h3 class="section-heading text-white">Stay License-Compliant</h3>
            <p class="text-gray-200 leading-relaxed">Track activations, expiry dates, and device limits from a consolidated dashboard. The portal highlights upcoming renewals and lets you extend coverage instantly.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-gray-200">
                <div class="glass-card p-4">
                    <p class="text-sm uppercase text-blue-200">Visibility</p>
                    <p class="text-3xl font-bold text-white">Real-time</p>
                    <p class="text-sm text-gray-300">Status sync with your AMPNM nodes.</p>
                </div>
                <div class="glass-card p-4">
                    <p class="text-sm uppercase text-blue-200">Coverage</p>
                    <p class="text-3xl font-bold text-white">10+ tiers</p>
                    <p class="text-sm text-gray-300">Flexible options for any footprint.</p>
                </div>
            </div>
            <a href="support.php" class="btn-glass-secondary inline-flex items-center text-white border border-blue-400">
                <i class="fas fa-life-ring mr-2"></i>Talk to Support
            </a>
        </div>
    </div>
</div>

<?php portal_footer(); ?>