<?php
require_once 'includes/functions.php';
portal_header("AMPNM - Advanced Multi-Protocol Network Monitor | IT Support BD");
?>

<!-- Hero Section with Animated Background -->
<div class="relative overflow-hidden">
    <div class="animated-grid"></div>
    <div class="hero-particles"></div>
    
    <div class="glass-card hero-section text-center py-16 md:py-24 px-6 mb-10 tilt-card">
        <div class="tilt-inner relative z-10">
            <span class="accent-badge mx-auto animate-pulse-slow">
                <i class="fas fa-bolt text-cyan-400"></i>
                <span class="gradient-text">Live Network Visibility</span>
            </span>
            
            <h1 class="hero-title text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold mt-6 mb-6">
                <span class="text-white">AMPNM Portal for</span><br>
                <span class="gradient-text-hero">Real-Time Monitoring</span>
            </h1>
            
            <p class="hero-subtitle text-lg md:text-xl text-gray-300 mb-8 leading-relaxed max-w-3xl mx-auto">
                Manage Docker AMPNM licenses, track infrastructure health, and unlock premium support from one modern control panel.
            </p>
            
            <div class="flex flex-col sm:flex-row justify-center gap-4 sm:gap-6 items-center">
                <a href="products.php" class="btn-glow-primary text-lg px-8 py-3 w-full sm:w-auto">
                    <i class="fas fa-shopping-bag mr-2"></i>Browse Licenses
                </a>
                <a href="registration.php" class="btn-glow-secondary text-lg px-8 py-3 w-full sm:w-auto">
                    <i class="fas fa-user-plus mr-2"></i>Start Free Account
                </a>
            </div>
            
            <!-- Stats Counter -->
            <div class="flex flex-wrap justify-center gap-6 md:gap-12 mt-12">
                <div class="stat-item">
                    <span class="stat-number" data-count="500">500+</span>
                    <span class="stat-label">Active Users</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" data-count="1000">1000+</span>
                    <span class="stat-label">Devices Monitored</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">99.9%</span>
                    <span class="stat-label">Uptime</span>
                </div>
            </div>
            
            <div class="floating-orb one"></div>
            <div class="floating-orb two"></div>
            <div class="floating-orb three"></div>
        </div>
    </div>
</div>

<!-- Product Screenshots Carousel -->
<div class="mb-16">
    <div class="text-center mb-10">
        <span class="accent-badge mx-auto mb-4">
            <i class="fas fa-images text-purple-400"></i>
            <span>Product Gallery</span>
        </span>
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
            See <span class="gradient-text-hero">AMPNM</span> in Action
        </h2>
        <p class="text-gray-400 max-w-2xl mx-auto">
            Explore the powerful features of our network monitoring solution
        </p>
    </div>
    
    <div class="screenshot-carousel">
        <div class="carousel-container">
            <div class="carousel-track" id="carouselTrack">
                <div class="carousel-slide">
                    <div class="screenshot-card">
                        <div class="screenshot-badge">
                            <i class="fas fa-project-diagram"></i> Network Map
                        </div>
                        <img src="assets/images/screenshots/network-map.png" alt="AMPNM Network Map" class="screenshot-image" loading="lazy">
                        <div class="screenshot-overlay">
                            <h4>Visual Network Topology</h4>
                            <p>Drag-and-drop network mapping with real-time status</p>
                        </div>
                    </div>
                </div>
                <div class="carousel-slide">
                    <div class="screenshot-card">
                        <div class="screenshot-badge">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </div>
                        <img src="assets/images/screenshots/dashboard.png" alt="AMPNM Dashboard" class="screenshot-image" loading="lazy">
                        <div class="screenshot-overlay">
                            <h4>Real-Time Dashboard</h4>
                            <p>Monitor all devices at a glance with status indicators</p>
                        </div>
                    </div>
                </div>
                <div class="carousel-slide">
                    <div class="screenshot-card">
                        <div class="screenshot-badge">
                            <i class="fas fa-server"></i> Device Inventory
                        </div>
                        <img src="assets/images/screenshots/device-inventory.png" alt="AMPNM Device Inventory" class="screenshot-image" loading="lazy">
                        <div class="screenshot-overlay">
                            <h4>Device Management</h4>
                            <p>Complete inventory with status tracking and actions</p>
                        </div>
                    </div>
                </div>
                <div class="carousel-slide">
                    <div class="screenshot-card">
                        <div class="screenshot-badge">
                            <i class="fas fa-history"></i> Ping History
                        </div>
                        <img src="assets/images/screenshots/ping-history.png" alt="AMPNM Ping History" class="screenshot-image" loading="lazy">
                        <div class="screenshot-overlay">
                            <h4>Performance Analytics</h4>
                            <p>Historical data with charts and detailed logs</p>
                        </div>
                    </div>
                </div>
                <div class="carousel-slide">
                    <div class="screenshot-card">
                        <div class="screenshot-badge">
                            <i class="fas fa-envelope"></i> Notifications
                        </div>
                        <img src="assets/images/screenshots/email-notifications.png" alt="AMPNM Email Notifications" class="screenshot-image" loading="lazy">
                        <div class="screenshot-overlay">
                            <h4>Email Alerts</h4>
                            <p>Customizable SMTP notifications for device events</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Carousel Controls -->
        <div class="carousel-controls">
            <button class="carousel-btn carousel-prev" id="prevBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="carousel-dots" id="carouselDots"></div>
            <button class="carousel-btn carousel-next" id="nextBtn">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<!-- Feature Cards with Icons -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 mt-12">
    <div class="feature-card-new tilt-card" data-aos="fade-up" data-aos-delay="0">
        <div class="tilt-inner">
            <div class="feature-icon-new bg-gradient-cyan">
                <i class="fas fa-shield-alt text-3xl md:text-4xl"></i>
            </div>
            <h2 class="text-xl md:text-2xl font-semibold mb-3 text-white">Secure Licensing</h2>
            <p class="text-gray-400 text-sm md:text-base">Genuine keys, encrypted delivery, and verified activation for every AMPNM deployment.</p>
            <div class="feature-glow cyan"></div>
        </div>
    </div>
    
    <div class="feature-card-new tilt-card" data-aos="fade-up" data-aos-delay="100">
        <div class="tilt-inner">
            <div class="feature-icon-new bg-gradient-green">
                <i class="fas fa-mobile-alt text-3xl md:text-4xl"></i>
            </div>
            <h2 class="text-xl md:text-2xl font-semibold mb-3 text-white">Mobile-Ready Portal</h2>
            <p class="text-gray-400 text-sm md:text-base">Responsive dashboards, thumb-friendly actions, and clean layouts for on-the-go visibility.</p>
            <div class="feature-glow green"></div>
        </div>
    </div>
    
    <div class="feature-card-new tilt-card" data-aos="fade-up" data-aos-delay="200">
        <div class="tilt-inner">
            <div class="feature-icon-new bg-gradient-purple">
                <i class="fas fa-headset text-3xl md:text-4xl"></i>
            </div>
            <h2 class="text-xl md:text-2xl font-semibold mb-3 text-white">Dedicated Support</h2>
            <p class="text-gray-400 text-sm md:text-base">Direct access to our support engineers, ticket follow-ups, and deployment best practices.</p>
            <div class="feature-glow purple"></div>
        </div>
    </div>
    
    <div class="feature-card-new tilt-card" data-aos="fade-up" data-aos-delay="300">
        <div class="tilt-inner">
            <div class="feature-icon-new bg-gradient-orange">
                <i class="fas fa-network-wired text-3xl md:text-4xl"></i>
            </div>
            <h2 class="text-xl md:text-2xl font-semibold mb-3 text-white">Multi-Protocol</h2>
            <p class="text-gray-400 text-sm md:text-base">ICMP ping, TCP ports, HTTP/HTTPS monitoring with customizable thresholds.</p>
            <div class="feature-glow orange"></div>
        </div>
    </div>
    
    <div class="feature-card-new tilt-card" data-aos="fade-up" data-aos-delay="400">
        <div class="tilt-inner">
            <div class="feature-icon-new bg-gradient-pink">
                <i class="fas fa-bell text-3xl md:text-4xl"></i>
            </div>
            <h2 class="text-xl md:text-2xl font-semibold mb-3 text-white">Smart Alerts</h2>
            <p class="text-gray-400 text-sm md:text-base">Email notifications on status changes with latency and packet loss thresholds.</p>
            <div class="feature-glow pink"></div>
        </div>
    </div>
    
    <div class="feature-card-new tilt-card" data-aos="fade-up" data-aos-delay="500">
        <div class="tilt-inner">
            <div class="feature-icon-new bg-gradient-blue">
                <i class="fas fa-docker text-3xl md:text-4xl"></i>
            </div>
            <h2 class="text-xl md:text-2xl font-semibold mb-3 text-white">Docker Ready</h2>
            <p class="text-gray-400 text-sm md:text-base">One-click deployment with Docker Compose, auto-updates, and easy scaling.</p>
            <div class="feature-glow blue"></div>
        </div>
    </div>
</div>

<!-- Docker AMPNM Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mt-16">
    <div class="glass-card-new p-6 md:p-8 tilt-card">
        <div class="tilt-inner space-y-5">
            <div class="flex items-center gap-3 mb-4">
                <div class="icon-circle bg-gradient-cyan">
                    <i class="fab fa-docker text-2xl"></i>
                </div>
                <h3 class="section-heading-new text-white text-xl md:text-2xl">Docker AMPNM Advantage</h3>
            </div>
            
            <p class="text-gray-300 leading-relaxed">
                Install the AMPNM Docker app directly from the portal and keep your network probes updated effortlessly.
            </p>
            
            <ul class="feature-list space-y-3">
                <li class="flex items-start gap-3">
                    <i class="fas fa-check-circle text-cyan-400 mt-1"></i>
                    <span class="text-gray-300">One-click license downloads for Docker deployment</span>
                </li>
                <li class="flex items-start gap-3">
                    <i class="fas fa-check-circle text-cyan-400 mt-1"></i>
                    <span class="text-gray-300">Versioned builds with changelogs for quick rollbacks</span>
                </li>
                <li class="flex items-start gap-3">
                    <i class="fas fa-check-circle text-cyan-400 mt-1"></i>
                    <span class="text-gray-300">Role-based access to invite teammates securely</span>
                </li>
                <li class="flex items-start gap-3">
                    <i class="fas fa-check-circle text-cyan-400 mt-1"></i>
                    <span class="text-gray-300">Visual network topology with drag-and-drop editor</span>
                </li>
            </ul>
            
            <a href="download_ampnm_docker_project.php" class="btn-glow-primary inline-flex items-center mt-4">
                <i class="fas fa-download mr-2"></i>Download Docker Package
            </a>
        </div>
    </div>
    
    <div class="glass-card-new p-6 md:p-8 tilt-card">
        <div class="tilt-inner space-y-5">
            <div class="flex items-center gap-3 mb-4">
                <div class="icon-circle bg-gradient-green">
                    <i class="fas fa-key text-2xl"></i>
                </div>
                <h3 class="section-heading-new text-white text-xl md:text-2xl">Stay License-Compliant</h3>
            </div>
            
            <p class="text-gray-300 leading-relaxed">
                Track activations, expiry dates, and device limits from a consolidated dashboard.
            </p>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="stat-card">
                    <p class="stat-card-label">Visibility</p>
                    <p class="stat-card-value gradient-text">Real-time</p>
                    <p class="stat-card-desc">Status sync with your AMPNM nodes</p>
                </div>
                <div class="stat-card">
                    <p class="stat-card-label">Coverage</p>
                    <p class="stat-card-value gradient-text">10+ tiers</p>
                    <p class="stat-card-desc">Flexible options for any footprint</p>
                </div>
            </div>
            
            <a href="support.php" class="btn-glow-secondary inline-flex items-center mt-4">
                <i class="fas fa-life-ring mr-2"></i>Talk to Support
            </a>
        </div>
    </div>
</div>

<!-- Why Choose AMPNM -->
<div class="mt-16 mb-8">
    <div class="text-center mb-10">
        <span class="accent-badge mx-auto mb-4">
            <i class="fas fa-star text-yellow-400"></i>
            <span>Why Choose Us</span>
        </span>
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
            The <span class="gradient-text-hero">Complete</span> Monitoring Solution
        </h2>
    </div>
    
    <div class="comparison-grid">
        <div class="comparison-card">
            <div class="comparison-icon">
                <i class="fas fa-rocket text-cyan-400"></i>
            </div>
            <h4 class="text-white font-semibold mb-2">Easy Setup</h4>
            <p class="text-gray-400 text-sm">Deploy in minutes with Docker</p>
        </div>
        <div class="comparison-card">
            <div class="comparison-icon">
                <i class="fas fa-shield-alt text-green-400"></i>
            </div>
            <h4 class="text-white font-semibold mb-2">Secure</h4>
            <p class="text-gray-400 text-sm">Role-based access control</p>
        </div>
        <div class="comparison-card">
            <div class="comparison-icon">
                <i class="fas fa-expand-arrows-alt text-purple-400"></i>
            </div>
            <h4 class="text-white font-semibold mb-2">Scalable</h4>
            <p class="text-gray-400 text-sm">From 10 to 1000+ devices</p>
        </div>
        <div class="comparison-card">
            <div class="comparison-icon">
                <i class="fas fa-clock text-orange-400"></i>
            </div>
            <h4 class="text-white font-semibold mb-2">30-Day Offline</h4>
            <p class="text-gray-400 text-sm">Works without internet</p>
        </div>
    </div>
</div>

<!-- CTA Section -->
<div class="cta-section mt-16 mb-8">
    <div class="cta-content text-center">
        <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
            Ready to Monitor Your Network?
        </h2>
        <p class="text-gray-300 mb-8 max-w-2xl mx-auto">
            Get started with AMPNM today and experience professional-grade network monitoring.
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-4">
            <a href="products.php" class="btn-glow-primary text-lg px-8 py-3">
                <i class="fas fa-shopping-cart mr-2"></i>View Pricing
            </a>
            <a href="registration.php" class="btn-glow-secondary text-lg px-8 py-3">
                <i class="fas fa-play mr-2"></i>Try Free Demo
            </a>
        </div>
    </div>
</div>

<script>
// Carousel functionality
document.addEventListener('DOMContentLoaded', function() {
    const track = document.getElementById('carouselTrack');
    const slides = document.querySelectorAll('.carousel-slide');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const dotsContainer = document.getElementById('carouselDots');
    
    let currentIndex = 0;
    const totalSlides = slides.length;
    
    // Create dots
    slides.forEach((_, index) => {
        const dot = document.createElement('button');
        dot.classList.add('carousel-dot');
        if (index === 0) dot.classList.add('active');
        dot.addEventListener('click', () => goToSlide(index));
        dotsContainer.appendChild(dot);
    });
    
    const dots = document.querySelectorAll('.carousel-dot');
    
    function updateCarousel() {
        track.style.transform = `translateX(-${currentIndex * 100}%)`;
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === currentIndex);
        });
    }
    
    function goToSlide(index) {
        currentIndex = index;
        updateCarousel();
    }
    
    function nextSlide() {
        currentIndex = (currentIndex + 1) % totalSlides;
        updateCarousel();
    }
    
    function prevSlide() {
        currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
        updateCarousel();
    }
    
    prevBtn.addEventListener('click', prevSlide);
    nextBtn.addEventListener('click', nextSlide);
    
    // Auto-advance carousel
    let autoSlide = setInterval(nextSlide, 5000);
    
    track.addEventListener('mouseenter', () => clearInterval(autoSlide));
    track.addEventListener('mouseleave', () => {
        autoSlide = setInterval(nextSlide, 5000);
    });
    
    // Touch support
    let touchStartX = 0;
    let touchEndX = 0;
    
    track.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });
    
    track.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, { passive: true });
    
    function handleSwipe() {
        const diff = touchStartX - touchEndX;
        if (Math.abs(diff) > 50) {
            if (diff > 0) nextSlide();
            else prevSlide();
        }
    }
});
</script>

<?php portal_footer(); ?>
