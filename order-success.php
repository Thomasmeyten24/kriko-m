<?php
/**
 * Checkout Success - Success View
 * Scouts Kriko-M Web Platform
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to shop if no session order is active
if (!isset($_SESSION['last_order'])) {
    header('Location: shop.php');
    exit;
}

$order = $_SESSION['last_order'];

require_once __DIR__ . '/includes/db.php';
$settings = read_db('settings');

$bank_iban = isset($settings['bank_iban']) ? $settings['bank_iban'] : 'BE76 1234 5678 9012';
$bank_bic = isset($settings['bank_bic']) ? $settings['bank_bic'] : 'KRIKOBE2B';
$bank_holder = isset($settings['bank_holder']) ? $settings['bank_holder'] : 'Scouts Kriko-M vzw';

$page_title = "Bestelling Geslaagd";
require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Celebrating banner -->
<section class="section container" style="text-align: center; max-width: 700px;">
    <div style="width: 80px; height: 80px; background-color: hsla(145, 63%, 35%, 0.1); color: var(--color-success); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; border: 3px solid var(--color-success);">
        <svg style="width: 48px; height: 48px;" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
        </svg>
    </div>
    
    <h2 style="font-size: 2.25rem; color: var(--color-primary-dark); margin-bottom: 8px;">Bedankt voor je bestelling!</h2>
    <p style="color: var(--color-text-muted); font-size: 1.1rem; line-height: 1.5;">
        We hebben je bestelling goed ontvangen onder nummer **<?php echo htmlspecialchars($order['id']); ?>**. Volg de onderstaande stappen om de betaling via overschrijving te voldoen.
    </p>
</section>

<!-- 2. Detailed Receipt & Payment Box -->
<section class="section section-bg">
    <div class="container" style="max-width: 800px;">
        
        <!-- Payment Instructions Block -->
        <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-md); border: 2px solid var(--color-secondary); padding: 40px; margin-bottom: 40px; position: relative; overflow: hidden;">
            <!-- Corner Tag -->
            <div style="position: absolute; top: 0; right: 0; background-color: var(--color-secondary); color: var(--color-bg-white); font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 0.8rem; padding: 6px 20px; border-bottom-left-radius: var(--border-radius-md); text-transform: uppercase; letter-spacing: 1px;">Betalingsinstructies</div>
            
            <h3 style="font-size: 1.6rem; color: var(--color-primary-dark); margin-bottom: 16px;">Overschrijving Gegevens</h3>
            <p style="font-size: 0.95rem; color: var(--color-text-muted); margin-bottom: 24px; line-height: 1.5;">
                Gelieve de betaling uit te voeren via uw bankapp met de onderstaande details. **Vermeld exact de gestructureerde mededeling** voor een automatische koppeling!
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 28px;">
                <div>
                    <span style="font-size: 0.8rem; color: var(--color-text-muted); display: block; text-transform: uppercase; font-weight: 600;">Begunstigde:</span>
                    <strong style="font-size: 1.05rem; color: var(--color-primary-dark);"><?php echo htmlspecialchars($bank_holder); ?></strong>
                </div>
                
                <div>
                    <span style="font-size: 0.8rem; color: var(--color-text-muted); display: block; text-transform: uppercase; font-weight: 600;">Rekeningnummer (IBAN):</span>
                    <strong style="font-size: 1.05rem; color: var(--color-primary-dark); font-family: monospace; letter-spacing: 0.5px;"><?php echo htmlspecialchars($bank_iban); ?></strong>
                </div>
                
                <div>
                    <span style="font-size: 0.8rem; color: var(--color-text-muted); display: block; text-transform: uppercase; font-weight: 600;">Bankcode (BIC):</span>
                    <strong style="font-size: 1.05rem; color: var(--color-primary-dark); font-family: monospace;"><?php echo htmlspecialchars($bank_bic); ?></strong>
                </div>
                
                <div>
                    <span style="font-size: 0.8rem; color: var(--color-text-muted); display: block; text-transform: uppercase; font-weight: 600;">Te overschrijven bedrag:</span>
                    <strong style="font-size: 1.4rem; color: var(--color-secondary);">€<?php echo number_format($order['total'], 2, ',', ''); ?></strong>
                </div>
            </div>
            
            <!-- Belgian Structured Reference Block -->
            <div style="background-color: var(--color-primary-dark); border-radius: var(--border-radius-md); padding: 20px; border: 1px solid var(--color-primary);">
                <span style="font-size: 0.8rem; color: var(--color-accent); display: block; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; text-align: center; margin-bottom: 4px;">Gestructureerde Mededeling (België):</span>
                <div class="structured-communication" style="margin: 0; font-size: 1.6rem; color: var(--color-bg-white);">
                    <?php echo htmlspecialchars($order['communication']); ?>
                </div>
            </div>
            
            <p style="font-size: 0.85rem; color: var(--color-error); font-weight: 700; margin-top: 16px; line-height: 1.4; display: flex; gap: 6px; align-items: flex-start;">
                <svg style="width: 18px; height: 18px; flex-shrink: 0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span>OPGELET: Als de gestructureerde mededeling niet exact klopt, kan de leiding je betaling niet automatisch matchen. Dit kan de levering van je kledij vertragen!</span>
            </p>
        </div>
        
        <!-- Invoice Styled Receipt summary -->
        <div class="invoice-receipt" style="background-color: var(--color-bg-white); box-shadow: var(--shadow-sm);">
            <div class="invoice-header">
                <h4 style="margin-bottom: 4px; font-family: inherit;">BESTELLING OVERZICHT</h4>
                <p style="font-size: 0.8rem; color: var(--color-text-muted); font-family: inherit;"><?php echo date('d-m-Y H:i:s', strtotime($order['date'])); ?></p>
            </div>
            
            <div style="font-family: inherit; font-size: 0.85rem; margin-bottom: 20px; line-height: 1.6;">
                <div><strong>Koper:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></div>
                <div><strong>Lid:</strong> <?php echo htmlspecialchars($order['child_name']); ?> (<?php echo ucfirst($order['child_tak']); ?>)</div>
                <div><strong>E-mail:</strong> <?php echo htmlspecialchars($order['email']); ?></div>
                <div><strong>Tel:</strong> <?php echo htmlspecialchars($order['phone']); ?></div>
            </div>
            
            <div style="border-top: 1px dashed var(--color-border); padding-top: 16px; margin-top: 16px; font-family: inherit;">
                <?php foreach ($order['items'] as $item): ?>
                    <div class="invoice-row" style="font-family: inherit;">
                        <span style="font-family: inherit;"><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['name']); ?> (Maat: <?php echo htmlspecialchars($item['size']); ?>)</span>
                        <span style="font-family: inherit;">€<?php echo number_format($item['price'] * $item['quantity'], 2, ',', ''); ?></span>
                    </div>
                <?php endforeach; ?>
                
                <div class="invoice-row invoice-total" style="font-family: inherit;">
                    <span style="font-family: inherit; font-weight: bold;">TOTAAL BEDRAG:</span>
                    <span style="font-family: inherit; font-weight: bold;">€<?php echo number_format($order['total'], 2, ',', ''); ?></span>
                </div>
            </div>
        </div>
        
        <!-- View Actions -->
        <div style="display: flex; gap: 16px; justify-content: center; margin-top: 40px; flex-wrap: wrap;">
            <button onclick="window.print()" class="btn btn-outline" style="display: flex; align-items: center; gap: 8px;">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                Afdrukken / Opslaan
            </button>
            <a href="shop.php" class="btn btn-secondary">Terug naar de winkel</a>
        </div>
        
    </div>
</section>

<!-- Robustly clear client cart localStorage after successful checkout -->
<script>
    if (typeof localStorage !== 'undefined') {
        localStorage.removeItem('kriko_cart');
    }
</script>

<?php 
// Keep the order details stored in history but clear the session so reload redirects or doesn't show again
unset($_SESSION['last_order']);
require_once __DIR__ . '/includes/footer.php'; 
?>
