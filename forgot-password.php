<?php
/**
 * Forgot Password - Send recovery link
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mail.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (is_admin_logged_in()) {
    header('Location: admin.php');
    exit;
}

$success = '';
$error = '';

$settings = read_db('settings');
$contact_email = isset($settings['contact_email']) ? $settings['contact_email'] : 'groepsleiding@kriko-m.be';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    
    if (empty($email)) {
        $error = 'Vul alstublieft een geldig e-mailadres in.';
    } else {
        $email = strtolower(trim($email));
        
        $is_admin = false;
        $is_parent = false;
        $recipient_role = '';
        $parent_record = null;
        
        // 1. Check if a registered parent email (primary or secondary)
        $parents = read_db('parents');
        foreach ($parents as $parent) {
            if (strtolower($parent['email']) === $email) {
                $is_parent = true;
                $recipient_role = 'parent_primary';
                $parent_record = $parent;
                break;
            }
            if (isset($parent['secondary_email']) && strtolower($parent['secondary_email']) === $email) {
                $is_parent = true;
                $recipient_role = 'parent_secondary';
                $parent_record = $parent;
                break;
            }
        }
        
        // 2. Check if groepsleiding contact email (only if not a registered parent)
        if (!$is_parent && $email === $contact_email) {
            $is_admin = true;
            $recipient_role = 'groepsleiding';
        }
        
        if ($is_admin || $is_parent) {
            // Generate secure reset token
            $token = bin2hex(random_bytes(16));
            $expires = time() + 3600; // 1 hour validity
            
            // Write reset record to flat-file database
            $resets = read_db('password_resets');
            
            // Clean up any old resets for this email first
            $resets = array_filter($resets, function($r) use ($email) {
                return strtolower($r['email']) !== $email && $r['expires'] > time();
            });
            
            $resets[] = [
                'token' => $token,
                'email' => $email,
                'role' => $recipient_role,
                'expires' => $expires
            ];
            
            write_db('password_resets', array_values($resets));
            
            // Construct the recovery link dynamically based on active hostname
            $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $reset_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset-password.php?token=' . $token;
            
            $to = $email;
            $subject = 'Scouts Kriko-M - Wachtwoord Herstellen';
            
            if ($is_admin) {
                $salutation = "Beste Groepsleiding,";
                $intro = "Er is een verzoek ingediend om het wachtwoord van het groepsleiding-account te herstellen.";
            } else {
                $name = $recipient_role === 'parent_primary' 
                    ? $parent_record['first_name'] . ' ' . $parent_record['last_name']
                    : $parent_record['secondary_first_name'] . ' ' . $parent_record['secondary_last_name'];
                $salutation = "Beste " . htmlspecialchars($name) . ",";
                $intro = "Er is een verzoek ingediend om het wachtwoord van uw ouderaccount op het Scouts Kriko-M Ouderportaal te herstellen.";
            }
            
            $email_body = "<h2>{$salutation}</h2>
            <p>{$intro}</p>
            <p>Klik op de onderstaande knop om uw wachtwoord opnieuw in te stellen. Deze link is <strong>1 uur geldig</strong>:</p>
            
            <div class='button-container'>
                <a href='" . htmlspecialchars($reset_url) . "' class='button' style='color: #ffffff !important;'>Wachtwoord Herstellen</a>
            </div>
            
            <p style='font-size: 0.85rem; color: #64748b;'>Als de knop niet werkt, kunt u ook de volgende link kopiëren en plakken in uw browser:<br>
            <a href='" . htmlspecialchars($reset_url) . "' style='color: #7a1b2e;'>{$reset_url}</a></p>
            
            <p>Indien u dit verzoek niet zelf heeft ingediend, kunt u deze e-mail veilig negeren. Er worden geen wijzigingen aan uw account aangebracht.</p>
            <p>Met vriendelijke groeten,<br><strong>Scouts Kriko-M</strong></p>";
            
            // Send HTML email via our socket/Mailpit engine!
            scouts_send_mail($to, $subject, $email_body);
            
            $success = "Er is succesvol een herstelmail verzonden naar <strong>" . htmlspecialchars($email) . "</strong>! Controleer uw inbox (en spamfolder).<br><br><span style='font-size: 0.85rem; opacity: 0.9;'>* Voor lokaal testen zonder mailserver is de link ook weggeschreven naar `data/email_log.txt`!</span>";
        } else {
            $error = 'Dit e-mailadres is niet bekend in ons systeem als groepsleiding of als geregistreerde ouder.';
        }
}
}

$redirect_url = 'ouderportaal.php';
if (isset($recipient_role) && $recipient_role === 'groepsleiding') {
    $redirect_url = 'login.php';
}

$page_title = "Wachtwoord Vergeten";
require_once __DIR__ . '/includes/header.php';
?>

<section class="section container" style="flex-grow: 1; display: flex; align-items: center; justify-content: center; min-height: 60vh;">
    
    <div class="login-card" style="max-width: 500px; width: 100%;">
        <div style="text-align: center; margin-bottom: 24px;">
            <svg style="width: 55px; height: 55px; fill: var(--color-primary-light); margin: 0 auto 10px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H3.75v-2.25A13.5 13.5 0 0114.25 3.75h.508c1.12 0 2.188.405 3 1.148z"></path>
            </svg>
            <h2 style="font-size: 1.75rem; margin-bottom: 4px; color: var(--color-primary-dark);">Wachtwoord Herstellen</h2>
            <span style="font-size: 0.85rem; color: var(--color-text-muted);">Voer uw geregistreerde e-mailadres in om een herstellink te ontvangen.</span>
        </div>

        <?php if (!empty($success)): ?>
            <div style="background-color: hsla(145, 63%, 35%, 0.1); border: 2px solid var(--color-success); color: var(--color-success); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 24px; font-size: 0.95rem; line-height: 1.5; font-weight: 500;">
                <?php echo $success; ?>
            </div>
            <div style="text-align: center;">
                <a href="<?php echo $redirect_url; ?>" class="btn btn-secondary" style="width: 100%;">Terug naar login</a>
            </div>
        <?php else: ?>
            
            <?php if (!empty($error)): ?>
                <div style="background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 12px; border-radius: var(--border-radius-md); margin-bottom: 20px; font-size: 0.9rem; text-align: center; font-weight: 600;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="forgot-password.php" method="POST">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" for="email">Geregistreerd E-mailadres:</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="naam@voorbeeld.com" required autofocus>
                </div>
                
                <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 12px 20px;">
                    Herstellink verzenden &rarr;
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 24px; display: flex; justify-content: center; gap: 20px;">
                <a href="ouderportaal.php" style="font-size: 0.85rem; color: var(--color-primary-light); text-decoration: underline;">&larr; Ouderportaal</a>
                <a href="login.php" style="font-size: 0.85rem; color: var(--color-primary-light); text-decoration: underline;">Leiding Login &rarr;</a>
            </div>
        <?php endif; ?>
    </div>
    
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
