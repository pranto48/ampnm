<?php
require_once 'includes/functions.php';

$pdo = getLicenseDbConnection();
$stmt = $pdo->query("SELECT * FROM `products` ORDER BY category ASC, price ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group products by category
$products_by_category = [];
foreach ($products as $product) {
    $category = $product['category'] ?? 'Other';
    if (!isset($products_by_category[$category])) {
        $products_by_category[$category] = [];
    }
    $products_by_category[$category][] = $product;
}

$category_icons = [
    'AMPNM' => 'fas fa-network-wired',
    'Support' => 'fas fa-headset',
    'Add-ons' => 'fas fa-puzzle-piece',
    'Hardware' => 'fas fa-server',
    'Other' => 'fas fa-cube'
];

$category_colors = [
    'AMPNM' => 'cyan',
    'Support' => 'purple',
    'Add-ons' => 'orange',
    'Hardware' => 'blue',
    'Other' => 'green'
];

portal_header("Our Products - AMPNM Network Monitoring | IT Support BD");

?>

<!-- Hero Section -->
<div class="relative overflow-hidden mb-12">
    <div class="animated-grid"></div>
    <div class="hero-particles"></div>
    
    <div class="glass-card hero-section py-12 md:py-16 px-6 md:px-10">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center relative z-10">
            <div class="space-y-5">
                <span class="accent-badge">
                    <i class="fas fa-box-open text-cyan-400"></i>
                    <span>AMPNM Catalog</span>
                </span>
                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white leading-tight">
                    Choose the Right <span class="gradient-text-hero">License</span> for Your Network
                </h1>
                <p class="text-gray-300 text-lg max-w-xl">
                    Curated plans with clear limits and quick add-to-cart actions help you compare and deploy faster.
                </p>
                <div class="flex flex-wrap gap-3">
                    <span class="feature-pill">
                        <i class="fas fa-mobile-alt text-cyan-400"></i> Mobile-ready
                    </span>
                    <span class="feature-pill">
                        <i class="fas fa-shield-alt text-green-400"></i> Secured checkout
                    </span>
                    <span class="feature-pill">
                        <i class="fas fa-bolt text-yellow-400"></i> Instant delivery
                    </span>
                </div>
            </div>
            
            <div class="glass-card-new p-6 space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm uppercase text-cyan-400 font-semibold tracking-wide">Docker + Portal</p>
                        <h3 class="text-xl md:text-2xl font-bold text-white mt-1">AMPNM Hybrid Monitoring</h3>
                    </div>
                    <span class="glow-badge">
                        <i class="fas fa-rocket"></i> Fast setup
                    </span>
                </div>
                <p class="text-gray-300">
                    Pair the Docker app with this portal to visualize nodes, renew licenses, and automate alerts.
                </p>
                <div class="flex flex-wrap gap-2">
                    <span class="mini-badge"><i class="fas fa-laptop-code"></i> API ready</span>
                    <span class="mini-badge"><i class="fas fa-cloud"></i> Cloud optimized</span>
                    <span class="mini-badge"><i class="fas fa-sitemap"></i> Multi-site</span>
                </div>
            </div>
        </div>
        
        <div class="floating-orb one"></div>
        <div class="floating-orb two"></div>
    </div>
</div>

<!-- Quick Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
    <div class="stat-card-product">
        <div class="stat-icon-wrap bg-gradient-cyan">
            <i class="fas fa-server"></i>
        </div>
        <div>
            <p class="stat-value">10+</p>
            <p class="stat-desc">License Tiers</p>
        </div>
    </div>
    <div class="stat-card-product">
        <div class="stat-icon-wrap bg-gradient-green">
            <i class="fas fa-infinity"></i>
        </div>
        <div>
            <p class="stat-value">Unlimited</p>
            <p class="stat-desc">Device Options</p>
        </div>
    </div>
    <div class="stat-card-product">
        <div class="stat-icon-wrap bg-gradient-purple">
            <i class="fas fa-headset"></i>
        </div>
        <div>
            <p class="stat-value">24/7</p>
            <p class="stat-desc">Support</p>
        </div>
    </div>
    <div class="stat-card-product">
        <div class="stat-icon-wrap bg-gradient-orange">
            <i class="fas fa-clock"></i>
        </div>
        <div>
            <p class="stat-value">30 Days</p>
            <p class="stat-desc">Offline Mode</p>
        </div>
    </div>
</div>

<?php if (empty($products_by_category)): ?>
    <div class="text-center py-16">
        <div class="text-6xl text-gray-600 mb-4"><i class="fas fa-box-open"></i></div>
        <p class="text-gray-400 text-lg">No products available at the moment. Please check back later!</p>
    </div>
<?php else: ?>
    <?php foreach ($products_by_category as $category => $category_products): ?>
        <?php 
        $icon = $category_icons[$category] ?? $category_icons['Other']; 
        $color = $category_colors[$category] ?? 'cyan';
        ?>
        <div class="mb-16">
            <!-- Category Header -->
            <div class="text-center mb-8">
                <span class="category-badge category-<?= htmlspecialchars($color) ?>">
                    <i class="<?= htmlspecialchars($icon) ?>"></i>
                    <?= htmlspecialchars($category) ?>
                </span>
                <p class="text-gray-400 mt-3 max-w-2xl mx-auto">
                    <?php if ($category === 'AMPNM'): ?>
                        Choose from flexible license tiers to match your network size and monitoring needs.
                    <?php elseif ($category === 'Support'): ?>
                        Premium support packages for dedicated assistance and priority responses.
                    <?php else: ?>
                        Additional products and services to enhance your monitoring experience.
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if ($category === 'AMPNM'): ?>
                <!-- AMPNM Pricing Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php 
                    $index = 0;
                    $popular_index = floor(count($category_products) / 2);
                    foreach ($category_products as $product): 
                        $is_popular = ($index === $popular_index);
                        $is_enterprise = ($product['max_devices'] >= 99999);
                    ?>
                        <div class="pricing-card <?= $is_popular ? 'popular' : '' ?> <?= $is_enterprise ? 'enterprise' : '' ?>">
                            <?php if ($is_popular): ?>
                                <div class="popular-badge">
                                    <i class="fas fa-star"></i> Most Popular
                                </div>
                            <?php endif; ?>
                            
                            <div class="pricing-header">
                                <div class="pricing-icon <?= $is_enterprise ? 'enterprise' : '' ?>">
                                    <i class="<?= $is_enterprise ? 'fas fa-crown' : 'fas fa-network-wired' ?>"></i>
                                </div>
                                <h3 class="pricing-title"><?= htmlspecialchars($product['name']) ?></h3>
                                <p class="pricing-subtitle"><?= htmlspecialchars($product['description']) ?></p>
                            </div>
                            
                            <div class="pricing-price">
                                <span class="price-currency">$</span>
                                <span class="price-amount"><?= htmlspecialchars(number_format($product['price'], 0)) ?></span>
                                <span class="price-period">/year</span>
                            </div>
                            
                            <ul class="pricing-features">
                                <li>
                                    <i class="fas fa-check-circle text-green-400"></i>
                                    <span><?= $product['max_devices'] >= 99999 ? 'Unlimited devices' : htmlspecialchars($product['max_devices']) . ' devices' ?></span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle text-green-400"></i>
                                    <span><?= htmlspecialchars($product['license_duration_days'] / 365) ?> year license</span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle text-green-400"></i>
                                    <span>Visual network mapping</span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle text-green-400"></i>
                                    <span>Email notifications</span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle text-green-400"></i>
                                    <span>30-day offline mode</span>
                                </li>
                                <?php if ($is_enterprise): ?>
                                <li>
                                    <i class="fas fa-check-circle text-cyan-400"></i>
                                    <span class="text-cyan-400">Priority support</span>
                                </li>
                                <?php endif; ?>
                            </ul>
                            
                            <form action="cart.php" method="POST" class="pricing-action">
                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                                <button type="submit" name="add_to_cart" class="pricing-btn <?= $is_popular ? 'popular' : '' ?>">
                                    <i class="fas fa-cart-plus"></i>
                                    <span>Add to Cart</span>
                                </button>
                            </form>
                        </div>
                    <?php 
                    $index++;
                    endforeach; 
                    ?>
                </div>
                
                <!-- Quick Selector -->
                <div class="glass-card-new p-6 mt-8 max-w-2xl mx-auto">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                        <div>
                            <h4 class="text-lg font-semibold text-white">Quick License Selector</h4>
                            <p class="text-gray-400 text-sm">Choose from dropdown and add to cart instantly</p>
                        </div>
                        <span class="glow-badge">
                            <i class="fas fa-sync"></i> Flexible renewals
                        </span>
                    </div>
                    <form action="cart.php" method="POST" class="flex flex-col sm:flex-row gap-3">
                        <select name="product_id" class="form-glass-input flex-1" required>
                            <?php foreach ($category_products as $product): ?>
                                <option value="<?= htmlspecialchars($product['id']) ?>">
                                    <?= htmlspecialchars($product['name']) ?> - $<?= htmlspecialchars(number_format($product['price'], 2)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="add_to_cart" class="btn-glow-primary whitespace-nowrap">
                            <i class="fas fa-cart-plus mr-2"></i>Add to Cart
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Other Category Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($category_products as $product): ?>
                        <div class="product-card-new">
                            <div class="product-card-header">
                                <div class="product-icon-wrap bg-gradient-<?= htmlspecialchars($color) ?>">
                                    <i class="<?= htmlspecialchars($icon) ?>"></i>
                                </div>
                                <span class="product-category-tag"><?= htmlspecialchars($category) ?></span>
                            </div>
                            
                            <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                            
                            <div class="product-meta">
                                <span class="meta-item">
                                    <i class="fas fa-memory text-cyan-400"></i>
                                    <?= $product['max_devices'] >= 99999 ? 'Unlimited' : htmlspecialchars($product['max_devices']) ?> devices
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-clock text-green-400"></i>
                                    <?= htmlspecialchars($product['license_duration_days'] / 365) ?> year
                                </span>
                            </div>
                            
                            <div class="product-footer">
                                <div class="product-price">
                                    <span class="price-small">$</span>
                                    <span class="price-large"><?= htmlspecialchars(number_format($product['price'], 0)) ?></span>
                                </div>
                                <form action="cart.php" method="POST">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                                    <button type="submit" name="add_to_cart" class="btn-add-cart">
                                        <i class="fas fa-cart-plus"></i>
                                        <span>Add</span>
                                    </button>
                                </form>
                            </div>
                            
                            <div class="product-glow"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- CTA Section -->
<div class="cta-section mt-8 mb-8">
    <div class="cta-content text-center">
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">
            Need Help Choosing?
        </h2>
        <p class="text-gray-300 mb-6 max-w-xl mx-auto">
            Our team is ready to help you find the perfect license for your network infrastructure.
        </p>
        <div class="flex flex-col sm:flex-row justify-center gap-4">
            <a href="support.php" class="btn-glow-secondary">
                <i class="fas fa-headset mr-2"></i>Contact Support
            </a>
            <a href="dashboard.php" class="btn-glow-primary">
                <i class="fas fa-th-large mr-2"></i>View Dashboard
            </a>
        </div>
    </div>
</div>

<style>
/* Products Page Specific Styles */

/* Feature Pills */
.feature-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 50px;
    font-size: 0.875rem;
    color: #e2e8f0;
}

/* Glow Badge */
.glow-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.8rem;
    background: linear-gradient(135deg, rgba(0, 212, 255, 0.2), rgba(0, 255, 136, 0.2));
    border: 1px solid rgba(0, 212, 255, 0.3);
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--primary-cyan);
    white-space: nowrap;
}

/* Mini Badge */
.mini-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.7rem;
    background: rgba(0, 212, 255, 0.1);
    border-radius: 6px;
    font-size: 0.75rem;
    color: #94a3b8;
}

/* Category Badge */
.category-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.5rem;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.category-cyan {
    background: rgba(0, 212, 255, 0.15);
    border: 1px solid rgba(0, 212, 255, 0.3);
    color: var(--primary-cyan);
}

.category-purple {
    background: rgba(168, 85, 247, 0.15);
    border: 1px solid rgba(168, 85, 247, 0.3);
    color: #a855f7;
}

.category-orange {
    background: rgba(255, 123, 0, 0.15);
    border: 1px solid rgba(255, 123, 0, 0.3);
    color: #ff7b00;
}

.category-green {
    background: rgba(0, 255, 136, 0.15);
    border: 1px solid rgba(0, 255, 136, 0.3);
    color: #00ff88;
}

.category-blue {
    background: rgba(59, 130, 246, 0.15);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #3b82f6;
}

/* Stat Cards */
.stat-card-product {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: rgba(15, 23, 42, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 1.25rem;
    transition: all 0.3s ease;
}

.stat-card-product:hover {
    border-color: rgba(0, 212, 255, 0.3);
    transform: translateY(-3px);
}

.stat-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: white;
}

.stat-desc {
    font-size: 0.8rem;
    color: #64748b;
}

/* Pricing Cards */
.pricing-card {
    position: relative;
    background: linear-gradient(145deg, rgba(15, 23, 42, 0.95), rgba(15, 23, 42, 0.8));
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 20px;
    padding: 2rem;
    display: flex;
    flex-direction: column;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.pricing-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 50% 0%, rgba(0, 212, 255, 0.1), transparent 60%);
    opacity: 0;
    transition: opacity 0.4s ease;
}

.pricing-card:hover {
    transform: translateY(-8px);
    border-color: rgba(0, 212, 255, 0.3);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.pricing-card:hover::before {
    opacity: 1;
}

.pricing-card.popular {
    border-color: rgba(0, 212, 255, 0.4);
    background: linear-gradient(145deg, rgba(0, 212, 255, 0.08), rgba(15, 23, 42, 0.95));
}

.pricing-card.enterprise {
    background: linear-gradient(145deg, rgba(168, 85, 247, 0.08), rgba(15, 23, 42, 0.95));
    border-color: rgba(168, 85, 247, 0.3);
}

.popular-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.4rem 0.8rem;
    background: linear-gradient(135deg, var(--primary-cyan), var(--primary-green));
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
    color: #0a0e17;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    z-index: 10;
}

.pricing-header {
    text-align: center;
    margin-bottom: 1.5rem;
    position: relative;
    z-index: 1;
}

.pricing-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    background: linear-gradient(135deg, var(--primary-cyan), var(--primary-green));
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #0a0e17;
}

.pricing-icon.enterprise {
    background: linear-gradient(135deg, #a855f7, #ec4899);
}

.pricing-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: white;
    margin-bottom: 0.5rem;
}

.pricing-subtitle {
    font-size: 0.875rem;
    color: #64748b;
}

.pricing-price {
    text-align: center;
    margin-bottom: 1.5rem;
    padding: 1rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    position: relative;
    z-index: 1;
}

.price-currency {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-cyan);
    vertical-align: super;
}

.price-amount {
    font-size: 3rem;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary-cyan), var(--primary-green));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.price-period {
    font-size: 1rem;
    color: #64748b;
}

.pricing-features {
    list-style: none;
    padding: 0;
    margin: 0 0 1.5rem 0;
    flex: 1;
    position: relative;
    z-index: 1;
}

.pricing-features li {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0;
    font-size: 0.9rem;
    color: #94a3b8;
}

.pricing-action {
    position: relative;
    z-index: 1;
}

.pricing-btn {
    width: 100%;
    padding: 0.875rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    background: rgba(0, 212, 255, 0.1);
    border: 1px solid rgba(0, 212, 255, 0.3);
    color: var(--primary-cyan);
}

.pricing-btn:hover {
    background: rgba(0, 212, 255, 0.2);
    transform: translateY(-2px);
}

.pricing-btn.popular {
    background: linear-gradient(135deg, var(--primary-cyan), var(--primary-green));
    border: none;
    color: #0a0e17;
}

.pricing-btn.popular:hover {
    box-shadow: 0 10px 30px -10px rgba(0, 212, 255, 0.5);
}

/* Product Card New */
.product-card-new {
    position: relative;
    background: linear-gradient(145deg, rgba(15, 23, 42, 0.95), rgba(15, 23, 42, 0.8));
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 20px;
    padding: 1.75rem;
    transition: all 0.4s ease;
    overflow: hidden;
}

.product-card-new:hover {
    transform: translateY(-6px);
    border-color: rgba(0, 212, 255, 0.3);
}

.product-card-new:hover .product-glow {
    opacity: 0.15;
}

.product-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.product-icon-wrap {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.product-category-tag {
    padding: 0.3rem 0.6rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    font-size: 0.7rem;
    color: #64748b;
    text-transform: uppercase;
}

.product-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: white;
    margin-bottom: 0.5rem;
}

.product-description {
    font-size: 0.9rem;
    color: #64748b;
    margin-bottom: 1rem;
    line-height: 1.5;
}

.product-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.meta-item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: #94a3b8;
}

.product-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.product-price {
    display: flex;
    align-items: baseline;
}

.price-small {
    font-size: 1rem;
    font-weight: 600;
    color: var(--primary-cyan);
}

.price-large {
    font-size: 2rem;
    font-weight: 800;
    color: white;
}

.btn-add-cart {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: linear-gradient(135deg, var(--primary-cyan), var(--primary-green));
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    color: #0a0e17;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-add-cart:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0, 212, 255, 0.4);
}

.product-glow {
    position: absolute;
    width: 200px;
    height: 200px;
    background: var(--primary-cyan);
    border-radius: 50%;
    filter: blur(80px);
    top: -50px;
    right: -50px;
    opacity: 0;
    transition: opacity 0.4s ease;
    pointer-events: none;
}

/* Responsive */
@media (max-width: 768px) {
    .pricing-card {
        padding: 1.5rem;
    }
    
    .price-amount {
        font-size: 2.5rem;
    }
    
    .stat-card-product {
        padding: 1rem;
    }
    
    .stat-icon-wrap {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .stat-value {
        font-size: 1rem;
    }
}
</style>

<?php portal_footer(); ?>
