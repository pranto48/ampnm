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

portal_header("Our Products - IT Support BD Portal");
?>

<div class="products-hero glass-card mb-10 p-10 relative overflow-hidden">
    <div class="animated-grid"></div>
    <div class="hero-orb orb-left"></div>
    <div class="hero-orb orb-right"></div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-center relative">
        <div class="space-y-3">
            <p class="accent-badge inline-flex"><i class="fas fa-box-open"></i> AMPNM catalog</p>
            <h1 class="text-4xl font-bold text-white">Choose the right AMPNM license for your network</h1>
            <p class="text-gray-200 max-w-2xl">Curated plans with clear limits, animated callouts, and quick add-to-cart actions help you compare and deploy faster.</p>
            <div class="flex flex-wrap gap-3 text-gray-200 text-sm">
                <span class="meta-pill"><i class="fas fa-mobile-alt"></i> Mobile-ready</span>
                <span class="meta-pill"><i class="fas fa-shield-alt"></i> Secured checkout</span>
                <span class="meta-pill"><i class="fas fa-bolt"></i> Instant delivery</span>
            </div>
        </div>
        <div class="products-spotlight glass-card p-6 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm uppercase text-blue-200">Docker + Portal</p>
                    <h3 class="text-2xl font-semibold text-white">AMPNM hybrid monitoring</h3>
                </div>
                <span class="glow-pill"><i class="fas fa-rocket"></i> Fast setup</span>
            </div>
            <p class="text-gray-200">Pair the Docker app with this portal to visualize nodes, renew licenses, and automate alerts.</p>
            <div class="flex gap-3 text-gray-100 text-sm">
                <span class="glow-pill subtle"><i class="fas fa-laptop-code"></i> API ready</span>
                <span class="glow-pill subtle"><i class="fas fa-cloud"></i> Cloud optimized</span>
                <span class="glow-pill subtle"><i class="fas fa-sitemap"></i> Multi-site</span>
            </div>
        </div>
    </div>
</div>

<?php if (empty($products_by_category)): ?>
    <p class="text-center text-gray-200 col-span-full">No products available at the moment. Please check back later!</p>
<?php else: ?>
    <?php foreach ($products_by_category as $category => $category_products): ?>
        <?php $icon = $category_icons[$category] ?? $category_icons['Other']; ?>
        <div class="mb-12">
            <div class="flex flex-col items-center mb-6 space-y-3">
                <div class="category-chip"><i class="<?= htmlspecialchars($icon) ?> mr-2"></i><?= htmlspecialchars($category) ?></div>
                <p class="text-gray-300 text-center max-w-3xl">Animated cards, glowing accents, and clear device limits make this category easy to skim.</p>
            </div>
            <?php if ($category === 'AMPNM'): ?>
                <div class="glass-card p-6 max-w-3xl mx-auto ampnm-selector">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <p class="text-sm uppercase text-blue-200">Pick your coverage</p>
                            <h3 class="text-2xl font-semibold text-white">AMPNM license tiers</h3>
                            <p class="text-gray-300">Slide through your preferred tier and add it to the cart instantly.</p>
                        </div>
                        <span class="glow-pill"><i class="fas fa-sync"></i> Flexible renewals</span>
                    </div>
                    <form action="cart.php" method="POST" class="space-y-4 mt-4">
                        <div>
                            <label for="ampnm_product_select" class="block text-gray-200 text-sm font-bold mb-2">Choose License:</label>
                            <select id="ampnm_product_select" name="product_id" class="form-glass-input" required>
                                <?php foreach ($category_products as $product): ?>
                                    <option value="<?= htmlspecialchars($product['id']) ?>">
                                        <?= htmlspecialchars($product['name']) ?> - $<?= htmlspecialchars(number_format($product['price'], 2)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_to_cart" class="btn-glass-primary w-full">
                            <i class="fas fa-cart-plus mr-2"></i>Add AMPNM License
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($category_products as $product): ?>
                        <div class="product-card glass-card flex flex-col justify-between p-6 tilt-card">
                            <div class="tilt-inner space-y-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-2xl font-semibold text-white mb-1"><?= htmlspecialchars($product['name']) ?></h3>
                                        <p class="text-gray-200 mb-2"><?= htmlspecialchars($product['description']) ?></p>
                                    </div>
                                    <span class="glow-pill subtle"><i class="fas fa-layer-group"></i> <?= htmlspecialchars($category) ?></span>
                                </div>
                                <div class="flex flex-wrap gap-3 text-gray-100 text-sm">
                                    <span class="meta-pill"><i class="fas fa-memory"></i> <?= $product['max_devices'] == 99999 ? 'Unlimited devices' : htmlspecialchars($product['max_devices']) . ' devices' ?></span>
                                    <span class="meta-pill"><i class="fas fa-clock"></i> <?= htmlspecialchars($product['license_duration_days'] / 365) ?> year term</span>
                                </div>
                            </div>
                            <div class="mt-6 pt-4 border-t border-gray-600 flex items-center justify-between">
                                <p class="text-3xl font-bold text-blue-300">$<?= htmlspecialchars(number_format($product['price'], 2)) ?></p>
                                <form action="cart.php" method="POST">
                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                                    <button type="submit" name="add_to_cart" class="btn-glass-primary">
                                        <i class="fas fa-cart-plus mr-2"></i>Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php portal_footer(); ?>
