<?php
/**
 * Reusable Header Layout Component
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/parent_auth.php';

// Fetch site configuration settings
$settings = read_db('settings');
$alert_active = isset($settings['alert_active']) ? $settings['alert_active'] : false;
$alert_message = isset($settings['alert_message']) ? $settings['alert_message'] : '';
$scouts_year = isset($settings['scouts_year']) ? $settings['scouts_year'] : '2026-2027';

// Determine active page helper
$current_page = basename($_SERVER['PHP_SELF']);
function active_class($page, $current) {
    return ($page === $current) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " | Scouts Kriko-M" : "Scouts Kriko-M Sint-Niklaas"; ?></title>
    <meta name="description" content="Welkom bij Scouts Kriko-M uit Sint-Niklaas. Ontdek onze takken, maandelijkse Kriko Echo's planningen, webshop, en hoe lid te worden!">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- 1. Top Announcement Banner -->
    <?php if ($alert_active && !empty($alert_message) && $current_page !== 'ouderportaal.php' && $current_page !== 'admin.php'): ?>
        <div class="alert-banner">
            <div class="container alert-container">
                <div class="alert-text">
                    <svg style="width: 20px; height: 20px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                    </svg>
                    <span><?php echo htmlspecialchars($alert_message); ?></span>
                </div>
                <button class="alert-close" aria-label="Melding sluiten">&times;</button>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. Primary Navigation Header or Portal Exit Trigger -->
    <?php if ($current_page === 'ouderportaal.php' || $current_page === 'admin.php'): ?>
        <!-- PORTALS EXIT CROSS BUTTON -->
        <div style="position: fixed; top: 24px; left: 24px; z-index: 9999;">
            <a href="index.php" style="display: flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 12px; background-color: var(--color-accent); color: var(--color-primary-dark); font-size: 1.8rem; font-weight: 700; text-decoration: none; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25); transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275); border: 2px solid var(--color-bg-white); line-height: 1;" onmouseover="this.style.transform='scale(1.08)'; this.style.backgroundColor='var(--color-primary)'; this.style.color='var(--color-bg-white)'" onmouseout="this.style.transform='scale(1)'; this.style.backgroundColor='var(--color-accent)'; this.style.color='var(--color-primary-dark)'" aria-label="Sluiten en terug naar site">
                &times;
            </a>
        </div>
    <?php else: ?>
        <header class="header">
            <div class="container header-container">
                <!-- Brand Logo -->
                <a href="index.php" class="logo-link">
                    <?php if (file_exists(dirname(__DIR__) . '/assets/images/logo.png')): ?>
                        <img src="assets/images/logo.png" alt="Logo Kriko-M" style="height: 50px; width: auto; border-radius: 50%; border: 2px solid var(--color-accent);">
                    <?php else: ?>
                        <!-- Fallback SVG scouts emblem for logo -->
                        <svg style="width: 45px; height: 45px; fill: var(--color-accent);" viewBox="0 0 24 24">
                            <path d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm0 2.18l6 2.25v4.66c0 4.14-2.56 8-6 9.07-3.44-1.07-6-4.93-6-9.07V6.43l6-2.25zM12 7c-1.66 0-3 1.34-3 3 0 2.25 3 5 3 5s3-2.75 3-5c0-1.66-1.34-3-3-3zm0 4.5c-.83 0-1.5-.67-1.5-1.5S11.17 8.5 12 8.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                        </svg>
                    <?php endif; ?>
                    <div class="logo-text">
                        <h1>Kriko-M</h1>
                        <span>Sint-Niklaas</span>
                    </div>
                </a>

                <!-- Mobile Toggle -->
                <button class="mobile-nav-toggle" aria-label="Menu openen">&#9776;</button>

                <!-- Navigation Links -->
                <nav class="nav">
                    <ul class="nav-list">
                        <li><a href="index.php" class="nav-link <?php echo active_class('index.php', $current_page); ?>">Home</a></li>
                        <li class="nav-dropdown">
                            <a href="takken.php" class="nav-link dropdown-toggle <?php echo active_class('takken.php', $current_page); ?>">
                                Takken <span style="font-size: 0.75rem; margin-left: 2px; vertical-align: middle;">▼</span>
                            </a>
                            <div class="nav-dropdown-content">
                                <a href="takken.php">Alle Takken</a>
                                <a href="takken.php?tak=kapoenen">Kapoenen (6-8j)</a>
                                <a href="takken.php?tak=welpen">Welpen (8-11j)</a>
                                <a href="takken.php?tak=jonggivers">Jonggivers (11-14j)</a>
                                <a href="takken.php?tak=givers">Givers (14-17j)</a>
                            </div>
                        </li>
                        <li><a href="echos.php" class="nav-link <?php echo active_class('echos.php', $current_page); ?>">Kriko Echo's</a></li>
                        <li><a href="shop.php" class="nav-link <?php echo active_class('shop.php', $current_page); ?>">Webshop</a></li>
                        <li><a href="contact.php" class="nav-link <?php echo active_class('contact.php', $current_page); ?>">Contact</a></li>
                    </ul>

                    <!-- Action buttons -->
                    <div class="nav-actions">
                        <!-- Shopping Cart Toggle (Default hidden, dynamically displayed via JS when items > 0) -->
                        <button class="cart-trigger-btn" aria-label="Winkelwagen bekijken" style="display: none;">
                            <svg style="width: 22px; height: 22px; fill: none; stroke: currentColor;" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                            </svg>
                            <span class="cart-count" style="display: none;">0</span>
                        </button>
                        
                        <?php if (is_admin_logged_in()): ?>
                            <a href="admin.php" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.9rem;">Beheer</a>
                            <a href="login.php?logout=1" class="btn btn-outline" style="padding: 8px 16px; font-size: 0.9rem; color: var(--color-bg-white); border-color: var(--color-bg-white);">Log uit</a>
                        <?php else: ?>
                            <a href="inschrijven.php" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.9rem; white-space: nowrap;">Inschrijven</a>
                            <a href="ouderportaal.php" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.9rem; white-space: nowrap;">Ouderportaal</a>
                        <?php endif; ?>
                    </div>
                </nav>
            </div>
        </header>
    <?php endif; ?>

    <!-- 3. Visual Sliding Cart Drawer -->
    <div class="cart-backdrop"></div>
    <div class="cart-drawer">
        <div class="cart-drawer-header">
            <h3 style="color: var(--color-bg-white); font-size: 1.25rem;">Winkelmandje</h3>
            <button class="cart-drawer-close">&times;</button>
        </div>
        <div class="cart-drawer-body">
            <!-- Dynamically populated via JS -->
        </div>
        <div class="cart-drawer-footer">
            <div class="cart-subtotal">
                <span>Subtotaal:</span>
                <span class="cart-subtotal-value">€0,00</span>
            </div>
            <p style="font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: 16px; line-height: 1.3;">Bestellingen worden betaald via overschrijving. Instructies worden getoond tijdens de checkout.</p>
            <a href="checkout.php" class="btn btn-secondary btn-cart-checkout" style="width: 100%;">Naar afrekenen</a>
        </div>
    </div>
