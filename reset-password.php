<?php
/**
 * Reset Password - Set new credentials using secure token
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (is_admin_logged_in()) {
    header('Location: admin.php');
    exit;
}

$token = isset($_GET['token']) ? $_GET['token'] : '';
$error = '';
$success = '';

if (empty($token)) {
    $error = 'Ongeldige of ontbrekende hersteltoken.';
} else {
    // Validate token in database
    $resets = read_db('password_resets');
    $active_reset = null;
    $reset_index = -1;
    
    foreach ($resets as $idx => $r) {
        if ($r['token'] === $token && $r['expires'] > time()) {
            $active_reset = $r;
            $reset_index = $idx;
            break;
        }
    }
    
    if (!$active_reset) {
        $error = 'Deze herstellink is ongeldig, gebruikt, of verlopen. Vraag alstublieft een nieuwe link aan.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_password = $_POST['new_password'];
        $conf_password = $_POST['conf_password'];
        
        if (empty($new_password) || empty($conf_password)) {
            $error = 'Vul alle wachtwoordvelden in.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Het wachtwoord moet minimaal 6 tekens bevatten.';
        } elseif ($new_password !== $conf_password) {
            $error = 'De ingevoerde wachtwoorden komen niet overeen.';
        } else {
            $email = $active_reset['email'];
            $role = $active_reset['role'];
            $updated = false;
            
            if ($role === 'groepsleiding') {
                $settings = read_db('settings');
                $settings['accounts']['groepsleiding']['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
                write_db('settings', $settings);
                $success = 'Uw groepsleiding-wachtwoord is succesvol hersteld! U kunt zich nu aanmelden met uw nieuwe wachtwoord.';
                $login_url = 'login.php';
                $updated = true;
            } else {
                // Parent reset (primary or secondary)
                $parents = read_db('parents');
                foreach ($parents as &$parent) {
                    if ($role === 'parent_primary' && strtolower($parent['email']) === strtolower($email)) {
                        $parent['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
                        $updated = true;
                        break;
                    } elseif ($role === 'parent_secondary' && isset($parent['secondary_email']) && strtolower($parent['secondary_email']) === strtolower($email)) {
                        $parent['secondary_password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
                        $updated = true;
                        break;
                    }
                }
                
                if ($updated) {
                    write_db('parents', $parents);
                    $success = 'Uw ouderportaal-wachtwoord is succesvol hersteld! U kunt zich nu aanmelden bij het ouderportaal.';
                    $login_url = 'ouderportaal.php';
                } else {
                    $error = 'Er is een fout opgetreden bij het bijwerken van het ouderaccount. Neem contact op met de leiding.';
                }
            }
            
            if ($updated) {
                // Remove token from resets database
                unset($resets[$reset_index]);
                write_db('password_resets', array_values($resets));
            }
        }
    }
}

$page_title = "Wachtwoord Instellen";
require_once __DIR__ . '/includes/header.php';
?>

<section class="section container" style="flex-grow: 1; display: flex; align-items: center; justify-content: center; min-height: 60vh;">
    
    <div class="login-card" style="max-width: 500px; width: 100%;">
        <div style="text-align: center; margin-bottom: 24px;">
            <svg style="width: 55px; height: 55px; fill: var(--color-primary-light); margin: 0 auto 10px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"></path>
            </svg>
            <h2 style="font-size: 1.75rem; margin-bottom: 4px; color: var(--color-primary-dark);">Nieuw Wachtwoord</h2>
            <span style="font-size: 0.85rem; color: var(--color-text-muted);">Voer een nieuw, sterk wachtwoord in voor uw account (<?php echo htmlspecialchars($active_reset['email']); ?>).</span>
        </div>

        <?php if (!empty($success)): ?>
            <div style="background-color: hsla(145, 63%, 35%, 0.1); border: 2px solid var(--color-success); color: var(--color-success); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 24px; font-size: 0.95rem; line-height: 1.5; font-weight: 500; text-align: center;">
                <?php echo $success; ?>
            </div>
            <div style="text-align: center;">
                <a href="<?php echo isset($login_url) ? $login_url : 'login.php'; ?>" class="btn btn-secondary" style="width: 100%;">Direct Aanmelden</a>
            </div>
        <?php else: ?>
            
            <?php if (!empty($error)): ?>
                <div style="background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 12px; border-radius: var(--border-radius-md); margin-bottom: 20px; font-size: 0.9rem; text-align: center; font-weight: 600;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($error) || (!empty($error) && $active_reset)): ?>
                <form action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label" for="new_password">Nieuw Wachtwoord:</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Minimaal 6 tekens" required autofocus>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label class="form-label" for="conf_password">Bevestig Nieuw Wachtwoord:</label>
                        <input type="password" id="conf_password" name="conf_password" class="form-control" placeholder="Wachtwoord herhalen" required>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 12px 20px;">
                        Wachtwoord Opslaan &rarr;
                    </button>
                </form>
            <?php else: ?>
                <div style="text-align: center;">
                    <a href="forgot-password.php" class="btn btn-primary" style="width: 100%; margin-bottom: 12px;">Nieuwe herstelmail aanvragen</a>
                    <a href="login.php" class="btn btn-outline" style="width: 100%;">Terug naar login</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
