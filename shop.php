<?php
/**
 * Clothing Webshop - Shop View
 * Scouts Kriko-M Web Platform
 */

$page_title = "Scoutsshop";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

// Fetch catalogue from database
$all_items = read_db('shop');

// Filter active items only
$active_items = array_filter($all_items, function($item) {
    return isset($item['active']) && $item['active'] === true;
});

// Category names mapping
$categories = [
    'kledij' => 'Kriko-M Kledij',
    'uniform' => 'Scouts Uniform',
    'accessoires' => 'Accessoires & Kentekens'
];
?>

<!-- 1. Page Header -->
<section class="tak-hero givers">
    <div class="container">
        <span style="color: var(--color-accent); font-family: 'Outfit', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">Draag met trots</span>
        <h2 class="tak-hero-title">Onze Scouts Webshop</h2>
        <p style="font-size: 1.2rem; color: hsla(0, 0%, 100%, 0.9); margin-top: 8px;">Kriko-M truien, t-shirts, dassen en kentekens.</p>
    </div>
</section>

<!-- 2. Main Shop Gallery -->
<section class="section container">
    
    <!-- Info Announcement Bar -->
    <div style="background-color: hsla(42, 85%, 55%, 0.1); border: 2px dashed var(--color-accent); border-radius: var(--border-radius-lg); padding: 24px; margin-bottom: 40px; display: flex; gap: 16px; align-items: flex-start;">
        <svg style="width: 28px; height: 28px; color: var(--color-secondary); flex-shrink: 0; margin-top: 2px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
            <h4 style="color: var(--color-primary-dark); font-size: 1.15rem; margin-bottom: 4px;">Hoe werkt bestellen bij ons?</h4>
            <p style="font-size: 0.95rem; color: var(--color-text-dark); line-height: 1.5;">
                Voeg kledingstukken toe aan je winkelwagen en voltooi de checkout. Betalingen gebeuren eenvoudig via **overschrijving** (je ontvangt direct alle instructies en een gestructureerde mededeling). Zodra we de betaling binnenkrijgen, ligt de bestelling de **eerstvolgende zondag** klaar aan de lokalen!
            </p>
        </div>
    </div>

    <!-- Category Groups Loop -->
    <?php foreach ($categories as $cat_key => $cat_name): 
        // Filter items in this category
        $cat_items = array_filter($active_items, function($item) use ($cat_key) {
            return $item['category'] === $cat_key;
        });
        
        if (empty($cat_items)) continue;
    ?>
        <div style="margin-bottom: 60px;">
            <h3 style="font-size: 1.75rem; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 8px; margin-bottom: 24px; color: var(--color-primary-dark);"><?php echo $cat_name; ?></h3>
            
            <div class="shop-grid">
                <?php foreach ($cat_items as $item): ?>
                    <div class="shop-card">
                        <!-- Product image container -->
                        <div class="shop-card-image">
                            <?php if (!empty($item['image']) && file_exists(__DIR__ . '/' . $item['image'])): ?>
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php else: ?>
                                <!-- Visual fallback SVG graphic if image is missing -->
                                <div style="display: flex; height: 100%; width: 100%; align-items: center; justify-content: center; background-color: var(--color-primary-light); color: var(--color-bg-white);">
                                    <svg style="width: 60px; height: 60px; fill: currentColor; opacity: 0.35;" viewBox="0 0 24 24">
                                        <path d="M12 2c1.1 0 2 .9 2 2v1h-4V4c0-1.1.9-2 2-2zm6 3h-2v1h-8V5H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm-6 13c-2.76 0-5-2.24-5-5h2c0 1.66 1.34 3 3 3s3-1.34 3-3h2c0 2.76-2.24 5-5 5z"/>
                                    </svg>
                                    <span style="position: absolute; bottom: 12px; font-size: 0.8rem; letter-spacing: 0.5px; opacity: 0.8; font-family: 'Outfit', sans-serif; font-weight: 700; text-transform: uppercase;">Scouts Kriko-M</span>
                                </div>
                            <?php endif; ?>
                            <span class="shop-badge"><?php echo htmlspecialchars($item['category']); ?></span>
                        </div>

                        <!-- Product body details -->
                        <div class="shop-card-body">
                            <h3 class="shop-card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <div class="shop-card-price">€<?php echo number_format($item['price'], 2, ',', ''); ?></div>
                            <p class="shop-card-desc"><?php echo htmlspecialchars($item['description']); ?></p>
                            
                            <!-- Size Select -->
                            <?php if (!empty($item['sizes']) && count($item['sizes']) > 0): 
                                $select_id = 'size-select-' . htmlspecialchars($item['id']);
                            ?>
                                <label class="form-label" for="<?php echo $select_id; ?>" style="margin-bottom: 4px; font-size: 0.8rem;">Selecteer Maat:</label>
                                <select id="<?php echo $select_id; ?>" name="size[<?php echo htmlspecialchars($item['id']); ?>]" class="shop-size-select">
                                    <?php foreach ($item['sizes'] as $size): ?>
                                        <option value="<?php echo htmlspecialchars($size); ?>"><?php echo htmlspecialchars($size); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            
                            <!-- Trigger Button -->
                            <button class="btn btn-secondary btn-add-to-cart" style="width: 100%; margin-top: auto;" 
                                    data-id="<?php echo htmlspecialchars($item['id']); ?>"
                                    data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                    data-price="<?php echo htmlspecialchars($item['price']); ?>"
                                    data-image="<?php echo htmlspecialchars($item['image']); ?>">
                                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                In winkelmandje
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
