<?php
/**
 * Registration Success Page - Manual Bank Transfer Invoice
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if no active registration session exists
if (!isset($_SESSION['last_registration'])) {
    header('Location: evenementen.php');
    exit;
}

$registration = $_SESSION['last_registration'];
$settings = read_db('settings');

$page_title = "Inschrijving Ontvangen";
require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Header Hero -->
<section class="tak-hero leiding">
    <div class="container">
        <span style="color: var(--color-accent); font-family: 'Outfit', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">Inschrijving Bevestigd</span>
        <h2 class="tak-hero-title">Bijna helemaal in orde!</h2>
        <p style="font-size: 1.2rem; color: hsla(0, 0%, 100%, 0.9); margin-top: 8px;">U bent succesvol aangemeld. Gelieve de betaling uit te voeren via handmatige overschrijving.</p>
    </div>
</section>

<!-- 2. Invoice Details Section -->
<section class="section container" style="max-width: 800px; margin: 0 auto;">
    
    <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-lg); border: 1px solid var(--color-border); overflow: hidden; margin-top: -50px; position: relative; z-index: 10;">
        
        <!-- Celebratory header -->
        <div style="background-color: var(--color-primary-dark); color: var(--color-bg-white); padding: 40px 30px; text-align: center; border-bottom: 4px solid var(--color-secondary);">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; background-color: var(--color-secondary); border-radius: 50%; margin-bottom: 20px; color: var(--color-primary-dark);">
                <svg style="width: 32px; height: 32px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 style="font-size: 1.8rem; margin: 0; color: var(--color-accent);">Hartelijk bedankt voor uw inschrijving!</h2>
            <p style="font-size: 1rem; color: hsla(0, 0%, 100%, 0.85); margin-top: 8px; margin-bottom: 0;">We hebben de aanmelding voor <strong><?php echo htmlspecialchars($registration['child_name']); ?></strong> succesvol ontvangen.</p>
        </div>
        
        <div style="padding: 30px 40px;">
            <p style="font-size: 0.95rem; line-height: 1.6; color: var(--color-text-dark); margin-bottom: 30px; text-align: center;">
                Om de inschrijving definitief te bevestigen, vragen wij u het verschuldigde bedrag handmatig over te schrijven naar onze bankrekening. Gebruik hierbij <strong>exact</strong> de onderstaande gestructureerde mededeling zodat onze administratie de betaling automatisch kan koppelen.
            </p>
            
            <!-- STYLISH BILLING CARD -->
            <div style="background-color: var(--color-bg-linen); border: 2px dashed var(--color-border); border-radius: var(--border-radius-lg); padding: 30px; margin-bottom: 35px;">
                <h3 style="font-size: 1.2rem; color: var(--color-primary-dark); margin-top: 0; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
                    Betalingsopdracht
                </h3>
                
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <!-- Amount -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
                        <span style="font-weight: 600; color: var(--color-text-muted);">Te betalen bedrag:</span>
                        <strong style="font-size: 1.5rem; color: var(--color-secondary);">€<?php echo number_format($registration['price'], 2, ',', ''); ?></strong>
                    </div>
                    
                    <!-- Bank Account IBAN -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
                        <span style="font-weight: 600; color: var(--color-text-muted);">IBAN Rekeningnummer:</span>
                        <span style="font-family: monospace; font-size: 1.1rem; font-weight: bold; color: var(--color-primary-dark);"><?php echo htmlspecialchars($settings['bank_iban']); ?></span>
                    </div>
                    
                    <!-- BIC -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
                        <span style="font-weight: 600; color: var(--color-text-muted);">BIC Code:</span>
                        <span style="font-family: monospace; font-size: 1rem; color: var(--color-primary-dark);"><?php echo htmlspecialchars($settings['bank_bic']); ?></span>
                    </div>
                    
                    <!-- Holder -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 10px;">
                        <span style="font-weight: 600; color: var(--color-text-muted);">Naam Begunstigde:</span>
                        <span style="font-weight: 600; color: var(--color-primary-dark);"><?php echo htmlspecialchars($settings['bank_holder']); ?></span>
                    </div>
                    
                    <!-- Structured communication -->
                    <div style="display: flex; justify-content: space-between; align-items: center; background-color: var(--color-primary-dark); color: var(--color-bg-white); padding: 15px; border-radius: var(--border-radius-md); margin-top: 10px;">
                        <span style="font-weight: 600; color: var(--color-accent);">Gestructureerde Mededeling:</span>
                        <strong style="font-family: monospace; font-size: 1.25rem; letter-spacing: 1px; color: var(--color-bg-white);"><?php echo htmlspecialchars($registration['communication']); ?></strong>
                    </div>
                </div>
            </div>
            
            <!-- REGISTRATION SUMMARY TABLE -->
            <h3 style="font-size: 1.2rem; color: var(--color-primary-dark); margin-bottom: 16px;">Details Inschrijving</h3>
            <div style="border: 1px solid var(--color-border); border-radius: var(--border-radius-md); overflow: hidden; margin-bottom: 30px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 12px 16px; background-color: var(--color-bg-linen); font-weight: 600; width: 35%;">Evenement:</td>
                            <td style="padding: 12px 16px; color: var(--color-text-dark);"><?php echo htmlspecialchars($registration['activity_title']); ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 12px 16px; background-color: var(--color-bg-linen); font-weight: 600;">Scouts Tak:</td>
                            <td style="padding: 12px 16px; color: var(--color-text-dark);"><?php echo ucfirst(htmlspecialchars($registration['child_tak'])); ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 12px 16px; background-color: var(--color-bg-linen); font-weight: 600;">Naam Deelnemer (Kind):</td>
                            <td style="padding: 12px 16px; color: var(--color-text-dark);"><?php echo htmlspecialchars($registration['child_name']); ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 12px 16px; background-color: var(--color-bg-linen); font-weight: 600;">Ouder / Contactpersoon:</td>
                            <td style="padding: 12px 16px; color: var(--color-text-dark);"><?php echo htmlspecialchars($registration['customer_name']); ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--color-border);">
                            <td style="padding: 12px 16px; background-color: var(--color-bg-linen); font-weight: 600;">E-mailadres:</td>
                            <td style="padding: 12px 16px; color: var(--color-text-dark);"><?php echo htmlspecialchars($registration['email']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 16px; background-color: var(--color-bg-linen); font-weight: 600;">Telefoonnummer:</td>
                            <td style="padding: 12px 16px; color: var(--color-text-dark);"><?php echo htmlspecialchars($registration['phone']); ?></td>
                        </tr>
                        <?php if (isset($registration['remarks']) && !empty($registration['remarks'])): ?>
                        <tr style="border-top: 1px solid var(--color-border);">
                            <td style="padding: 12px 16px; background-color: var(--color-bg-linen); font-weight: 600;">Opmerkingen:</td>
                            <td style="padding: 12px 16px; color: var(--color-text-dark); font-style: italic;"><?php echo htmlspecialchars($registration['remarks']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="background-color: hsla(38, 80%, 50%, 0.1); border-left: 4px solid var(--color-secondary); padding: 15px; border-radius: var(--border-radius-sm); margin-bottom: 35px; font-size: 0.85rem; color: var(--color-text-dark); line-height: 1.5;">
                <strong>Belangrijk:</strong> U heeft ook een automatische bevestigingsmail ontvangen op het door u opgegeven e-mailadres (<?php echo htmlspecialchars($registration['email']); ?>) met daarin dezelfde betalingsinstructies. Gelieve de overschrijving binnen de 5 werkdagen te voldoen.
            </div>
            
            <div style="background-color: hsla(145, 63%, 35%, 0.08); border: 2px solid var(--color-success); padding: 20px; border-radius: var(--border-radius-md); margin-bottom: 35px; text-align: center;">
                <h4 style="font-size: 1.1rem; color: var(--color-success); margin-top: 0; margin-bottom: 8px; font-weight: 700;">Heeft u de betaling al uitgevoerd?</h4>
                <p style="font-size: 0.85rem; color: var(--color-text-dark); margin-bottom: 15px;">Klik op de knop hieronder om aan de leiding te melden dat u het geld heeft overgeschreven. Dit versnelt de handmatige goedkeuring!</p>
                <a href="ouderportaal.php?action=confirm_payment&amp;reg_id=<?php echo $registration['id']; ?>" class="btn btn-secondary" style="background-color: var(--color-success); border-color: var(--color-success); color: var(--color-bg-white); padding: 10px 24px; font-weight: bold; text-decoration: none; display: inline-block;">
                    Ik heb overgeschreven
                </a>
            </div>

            <div style="text-align: center; display: flex; gap: 15px; justify-content: center;">
                <a href="evenementen.php" class="btn btn-outline" style="padding: 10px 24px;">Nieuwe inschrijving</a>
                <a href="index.php" class="btn btn-secondary" style="padding: 10px 24px;">Terug naar home</a>
            </div>
        </div>
        
    </div>
    
</section>

<?php 
// Safely clear the checkout session so that a reload won't trigger resubmissions
unset($_SESSION['last_registration']);
require_once __DIR__ . '/includes/footer.php'; 
?>
