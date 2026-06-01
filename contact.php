<?php
/**
 * Contact - Contact View
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/includes/db.php';
$settings = read_db('settings');

$contact_email = isset($settings['contact_email']) ? $settings['contact_email'] : 'groepsleiding@kriko-m.be';
$contact_phone = isset($settings['contact_phone']) ? $settings['contact_phone'] : '+32 3 776 00 00';
$contact_address = isset($settings['contact_address']) ? $settings['contact_address'] : 'Industriepark-Noord 33, 9100 Sint-Niklaas';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_SPECIAL_CHARS);
    $message_text = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS);
    
    if (empty($name) || empty($email) || empty($subject) || empty($message_text)) {
        $error_message = 'Vul alstublieft alle formuliervelden in.';
    } else {
        // Save message to flat-file messages database
        $messages = read_db('messages');
        $messages[] = [
            'id' => 'msg_' . uniqid(),
            'date' => date('Y-m-d H:i:s'),
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message_text,
            'read' => false
        ];
        write_db('messages', $messages);
        
        $success_message = 'Bedankt! Jouw bericht is succesvol verzonden. Onze leiding zal zo snel mogelijk reageren.';
    }
}

$page_title = "Contact";
require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Page Header -->
<section class="tak-hero leiding">
    <div class="container">
        <span style="color: var(--color-accent); font-family: 'Outfit', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">Vragen of opmerkingen?</span>
        <h2 class="tak-hero-title">Neem contact op</h2>
        <p style="font-size: 1.2rem; color: hsla(0, 0%, 100%, 0.9); margin-top: 8px;">We staan altijd klaar om je te helpen!</p>
    </div>
</section>

<!-- 2. Main Contact Grid Layout -->
<section class="section container">
    <?php if (!empty($success_message)): ?>
        <div style="background-color: hsla(145, 63%, 35%, 0.1); border: 2px solid var(--color-success); color: var(--color-success); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 30px; font-weight: 600;">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div style="background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 30px; font-weight: 600;">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="checkout-layout">
        
        <!-- Left: Beautiful Form -->
        <div class="checkout-card">
            <h3 style="font-size: 1.6rem; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 12px; margin-bottom: 24px; color: var(--color-primary-dark);">Stuur een bericht</h3>
            
            <form action="contact.php" method="POST">
                <!-- Name -->
                <div class="form-group">
                    <label class="form-label" for="name">Jouw Naam:</label>
                    <input type="text" id="name" name="name" class="form-control" placeholder="Voornaam + Achternaam" required>
                </div>
                
                <!-- Email -->
                <div class="form-group">
                    <label class="form-label" for="email">E-mailadres:</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="naam@domein.be" required>
                </div>
                
                <!-- Subject selector -->
                <div class="form-group">
                    <label class="form-label" for="subject">Onderwerp / Bestemming:</label>
                    <select id="subject" name="subject" class="form-control" required>
                        <option value="" disabled selected>Kies een onderwerp</option>
                        <option value="groepsleiding">Algemene Groepsleiding</option>
                        <option value="kapoenen">Kapoenenleiding (6-8j)</option>
                        <option value="welpen">Welpenleiding (8-11j)</option>
                        <option value="jonggivers">Jonggiverleiding (11-14j)</option>
                        <option value="givers">Giverleiding (14-17j)</option>
                        <option value="webshop">Webshop & Uniformen</option>
                    </select>
                </div>
                
                <!-- Message -->
                <div class="form-group">
                    <label class="form-label" for="message">Bericht:</label>
                    <textarea id="message" name="message" class="form-control" rows="5" placeholder="Typ hier uw vraag of bericht..." style="resize: vertical; font-family: inherit;" required></textarea>
                </div>
                
                <button type="submit" class="btn btn-secondary" style="width: 100%; margin-top: 10px; padding: 14px 20px;">
                    Bericht verzenden
                </button>
            </form>
        </div>
        
        <!-- Right: Contact coordinates sidebar -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Address details -->
            <div class="checkout-card" style="background-color: var(--color-bg-white);">
                <h3 style="font-size: 1.4rem; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 8px; margin-bottom: 16px; color: var(--color-primary-dark);">Ons Lokaal</h3>
                <p style="font-size: 0.95rem; color: var(--color-text-dark); margin-bottom: 20px; line-height: 1.5;">
                    Onze scoutsruimten bevinden zich in het noorden van Sint-Niklaas. We beschikken over een prachtig speelterrein op het VP-plein en uitstekende binnenlokalen.
                </p>
                <div style="background-color: var(--color-bg-linen); border-radius: var(--border-radius-md); padding: 16px; border: 1px solid var(--color-border); font-size: 0.9rem;">
                    <strong>Adres:</strong><br>
                    <?php echo htmlspecialchars($contact_address); ?><br>
                    <span style="font-size: 0.8rem; color: var(--color-text-muted);">(naast drankenhandel De Vidts)</span><br><br>
                    <strong>Vergaderuren:</strong><br>
                    Elke zondagochtend van 9:45 tot 12:30.
                </div>
            </div>

            <!-- Groepsleiding Contact Details -->
            <div class="checkout-card" style="background-color: var(--color-bg-white);">
                <h3 style="font-size: 1.4rem; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 8px; margin-bottom: 16px; color: var(--color-primary-dark);">Groepsleiding</h3>
                <ul class="contact-sidebar-list" style="margin-bottom: 10px;">
                    <li class="contact-sidebar-item" style="border-bottom: 1px solid var(--color-bg-linen); padding-bottom: 8px; margin-bottom: 8px;">
                        <strong>Ruben Meyten</strong> <span style="font-style: italic; font-size: 0.8rem; color: var(--color-text-muted);">"Strijdlustige Slechtvalk"</span><br>
                        <span style="font-size: 0.85rem; color: var(--color-text-dark);">Tel: +32 471 31 37 78</span>
                    </li>
                    <li class="contact-sidebar-item" style="border-bottom: 1px solid var(--color-bg-linen); padding-bottom: 8px; margin-bottom: 8px;">
                        <strong>Lisa-Lee Lyssens</strong> <span style="font-style: italic; font-size: 0.8rem; color: var(--color-text-muted);">"Lyrische Lijster"</span><br>
                        <span style="font-size: 0.85rem; color: var(--color-text-dark);">Tel: +32 497 50 62 54</span>
                    </li>
                    <li class="contact-sidebar-item">
                        <strong>Brecht Van Strijthem</strong> <span style="font-style: italic; font-size: 0.8rem; color: var(--color-text-muted);">"Markante Mier"</span><br>
                        <span style="font-size: 0.85rem; color: var(--color-text-dark);">Tel: +32 478 78 51 99</span>
                    </li>
                </ul>
            </div>
            
            <!-- Direct emails list -->
            <div class="checkout-card" style="background-color: var(--color-bg-white);">
                <h3 style="font-size: 1.4rem; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 8px; margin-bottom: 16px; color: var(--color-primary-dark);">Directe Adressen</h3>
                <ul class="contact-sidebar-list">
                    <li class="contact-sidebar-item">
                        <span>Algemene Groepsleiding:</span>
                        <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" style="font-weight: 700; color: var(--color-secondary);"><?php echo htmlspecialchars($contact_email); ?></a>
                    </li>
                    <li class="contact-sidebar-item">
                        <span>Kapoenen:</span>
                        <a href="mailto:kapoenenleiding@kriko-m.be" style="font-weight: 700; color: var(--color-primary);">kapoenenleiding@kriko-m.be</a>
                    </li>
                    <li class="contact-sidebar-item">
                        <span>Welpen:</span>
                        <a href="mailto:welpenleiding@kriko-m.be" style="font-weight: 700; color: var(--color-primary);">welpenleiding@kriko-m.be</a>
                    </li>
                    <li class="contact-sidebar-item">
                        <span>Jonggivers:</span>
                        <a href="mailto:jonggiverleiding@kriko-m.be" style="font-weight: 700; color: var(--color-primary);">jonggiverleiding@kriko-m.be</a>
                    </li>
                    <li class="contact-sidebar-item">
                        <span>Givers:</span>
                        <a href="mailto:giverleiding@kriko-m.be" style="font-weight: 700; color: var(--color-primary);">giverleiding@kriko-m.be</a>
                    </li>
                </ul>
            </div>
        </div>
        
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
