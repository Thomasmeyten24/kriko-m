<?php
/**
 * Admin Session Login - Login View
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/includes/auth.php';

// Handle logout query trigger
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    admin_logout();
    $logout_success = 'U bent succesvol uitgelogd.';
}

// Redirect if already logged in
if (is_admin_logged_in()) {
    header('Location: admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($role) || empty($password)) {
        $error = 'Selecteer een rol en vul het wachtwoord in.';
    } else {
        if (verify_admin_login($role, $password)) {
            // Successful validation! Redirect to control dashboard
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Ongeldig wachtwoord voor deze leidingrol! Gelieve opnieuw te proberen.';
        }
    }
}

$page_title = "Leiding Login";
require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Centered login card layout -->
<section class="section container" style="flex-grow: 1; display: flex; align-items: center; justify-content: center; min-height: 50vh;">
    
    <div class="login-card">
        <div style="text-align: center; margin-bottom: 24px;">
            <svg style="width: 55px; height: 55px; fill: var(--color-primary-light); margin: 0 auto 10px;" viewBox="0 0 24 24">
                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
            </svg>
            <h2 style="font-size: 1.75rem; margin-bottom: 4px; color: var(--color-primary-dark);">Leiding Portaal</h2>
            <span style="font-size: 0.85rem; color: var(--color-text-muted);">Meld aan om planningsbrieven en takpagina's te beheren.</span>
        </div>

        <?php if (!empty($logout_success)): ?>
            <div style="background-color: hsla(145, 63%, 35%, 0.1); border: 1px solid var(--color-success); color: var(--color-success); padding: 12px; border-radius: var(--border-radius-md); margin-bottom: 20px; font-size: 0.9rem; text-align: center; font-weight: 600;">
                <?php echo $logout_success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div style="background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 12px; border-radius: var(--border-radius-md); margin-bottom: 20px; font-size: 0.9rem; text-align: center; font-weight: 600;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Form submission -->
        <form action="login.php" method="POST">
            <div class="form-group" style="margin-bottom: 18px;">
                <label class="form-label" for="role">Leiding Tak / Rol:</label>
                <select id="role" name="role" class="form-control" required style="text-align-last: center;">
                    <option value="" disabled selected>Kies uw rol</option>
                    <option value="groepsleiding">Groepsleiding (Super Admin)</option>
                    <option value="kapoenen">Kapoenenleiding (6-8j)</option>
                    <option value="welpen">Welpenleiding (8-11j)</option>
                    <option value="jonggivers">Jonggiverleiding (11-14j)</option>
                    <option value="givers">Giverleiding (14-17j)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="password">Wachtwoord:</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Wachtwoord invoeren" style="text-align: center;" required autofocus>
                <div style="text-align: right; margin-top: 8px;">
                    <a href="forgot-password.php" style="font-size: 0.8rem; color: var(--color-primary-light); text-decoration: underline;">Wachtwoord vergeten?</a>
                </div>
                
                <div style="background-color: var(--color-bg-linen); border: 1px solid var(--color-border); border-radius: var(--border-radius-sm); padding: 10px; margin-top: 15px; font-size: 0.75rem; color: var(--color-text-muted); line-height: 1.4;">
                    <strong>Standaard Wachtwoorden voor testen:</strong><br>
                    • Groepsleiding: <code style="font-weight: 600; color: var(--color-primary);">KrikoGroep2026!</code><br>
                    • Kapoenen: <code style="font-weight: 600; color: var(--color-primary);">KrikoKapoenen2026!</code><br>
                    • Welpen: <code style="font-weight: 600; color: var(--color-primary);">KrikoWelpen2026!</code><br>
                    • Jonggivers: <code style="font-weight: 600; color: var(--color-primary);">KrikoJonggivers2026!</code><br>
                    • Givers: <code style="font-weight: 600; color: var(--color-primary);">KrikoGivers2026!</code>
                </div>
            </div>
            
            <button type="submit" class="btn btn-secondary" style="width: 100%; margin-top: 10px; padding: 12px 20px;">
                Aanmelden &rarr;
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 24px;">
            <a href="index.php" style="font-size: 0.85rem; color: var(--color-primary-light); text-decoration: underline;">&larr; Terug naar de website</a>
        </div>
    </div>
    
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
