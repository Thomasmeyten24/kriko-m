<?php
/**
 * Diagnostic Mailpit Test Script
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/db.php';

$settings = read_db('settings');
$contact_email = isset($settings['contact_email']) ? $settings['contact_email'] : 'groepsleiding@kriko-m.be';

$test_recipient = isset($_POST['recipient']) ? filter_input(INPUT_POST, 'recipient', FILTER_VALIDATE_EMAIL) : 'test.parent@kriko-m.be';
$subject = isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : 'Mailpit Test-Overschrijving';

$message_sent = false;
$smtp_connected = false;
$error_message = '';
$log_contents = '';

// Check if socket SMTP port 1025 is listening
$socket_test = @fsockopen('127.0.0.1', 1025, $errno, $errstr, 0.2);
if ($socket_test) {
    $smtp_connected = true;
    fclose($socket_test);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $test_recipient) {
    $test_html_body = "
    <h2>📬 Hallo Test-Gebruiker,</h2>
    <p>Dit is een succesvolle test-email verzonden vanaf het <strong>Scouts Kriko-M Web Platform</strong> om uw lokale <strong>Mailpit</strong> mail-configuratie te controleren!</p>
    
    <p>Als u dit bericht ziet, betekent dit dat de SMTP-socketkoppeling op poort <strong>1025</strong> perfect functioneert. U bent nu helemaal klaar om de volledige workflow (wachtwoordherstel, bestellingen en inschrijvingen) lokaal te testen.</p>
    
    <div class='payment-box'>
        <h4>🎉 Mailpit SMTP-Status: Actief!</h4>
        <p style='margin: 0; font-size: 0.9rem;'>De e-mail is via direct sockets getransmiteerd naar <code>127.0.0.1:1025</code>.</p>
    </div>
    
    <p>Gebruik de onderstaande knop om terug te keren naar de site:</p>
    <div class='button-container'>
        <a href='http://localhost/ouderportaal.php' class='button' style='color: white !important;'>Naar Ouderportaal</a>
    </div>
    
    <p>Met sportieve scouts-groeten,<br><strong>De Dev-Leiding</strong></p>
    ";
    
    if (scouts_send_mail($test_recipient, $subject, $test_html_body)) {
        $message_sent = true;
        
        // Load the last few lines of the email log
        $log_file = __DIR__ . '/../data/email_log.txt';
        if (file_exists($log_file)) {
            $log_contents = htmlspecialchars(file_get_contents($log_file));
        }
    } else {
        $error_message = 'Kon de e-mail niet verzenden. Controleer uw PHP/mail instellingen of SMTP logs.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mailpit Mail-Tester | Scouts Kriko-M</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: #7a1b2e;
            --color-primary-light: #a22c42;
            --color-primary-dark: #4c101c;
            --color-secondary: #d97706;
            --color-accent: #f59e0b;
            --color-bg-white: #ffffff;
            --color-bg-linen: #fcfbfa;
            --color-border: #e2e8f0;
            --color-text-dark: #1e293b;
            --color-text-muted: #64748b;
            --color-success: #10b981;
            --color-error: #ef4444;
            --border-radius-lg: 16px;
            --border-radius-md: 10px;
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f7f5f0;
            color: var(--color-text-dark);
            margin: 0;
            padding: 40px 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 650px;
            margin: 0 auto;
        }
        
        .card {
            background-color: var(--color-bg-white);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--color-border);
            box-shadow: var(--shadow-md);
            padding: 40px;
            margin-bottom: 24px;
        }
        
        h1 {
            color: var(--color-primary-dark);
            font-size: 2rem;
            margin-top: 0;
            margin-bottom: 8px;
            font-weight: 800;
        }
        
        h2 {
            font-size: 1.25rem;
            color: var(--color-primary-dark);
            margin-top: 0;
            margin-bottom: 16px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 24px;
        }
        
        .status-badge.online {
            background-color: hsla(145, 63%, 35%, 0.1);
            border: 1px solid var(--color-success);
            color: var(--color-success);
        }
        
        .status-badge.offline {
            background-color: hsla(4, 75%, 48%, 0.1);
            border: 1px solid var(--color-error);
            color: var(--color-error);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--color-border);
            border-radius: var(--border-radius-md);
            box-sizing: border-box;
            font-family: inherit;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--color-primary-light);
        }
        
        .btn {
            background-color: var(--color-primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s ease;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn:hover {
            background-color: var(--color-primary-light);
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--color-primary);
            border: 2px solid var(--color-primary);
        }
        
        .btn-outline:hover {
            background-color: var(--color-primary);
            color: white;
        }
        
        .info-box {
            background-color: #f8fafc;
            border-radius: var(--border-radius-md);
            border: 1px solid var(--color-border);
            padding: 20px;
            font-size: 0.9rem;
            margin-bottom: 24px;
        }
        
        .info-box h3 {
            margin-top: 0;
            color: var(--color-primary-dark);
            font-size: 1rem;
        }
        
        .info-box code {
            background-color: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85rem;
        }
        
        .log-box {
            background-color: #1e293b;
            color: #f1f5f9;
            padding: 20px;
            border-radius: var(--border-radius-md);
            font-family: monospace;
            font-size: 0.8rem;
            overflow-x: auto;
            max-height: 200px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h1>📧 Mailpit Mail-Tester</h1>
        
        <?php if ($smtp_connected): ?>
            <span class="status-badge online">
                ● Mailpit SMTP-Server Online (Poort 1025)
            </span>
        <?php else: ?>
            <span class="status-badge offline">
                ● Mailpit SMTP-Server Offline (Geen verbinding op poort 1025)
            </span>
        <?php endif; ?>
        
        <?php if ($message_sent): ?>
            <div style="background-color: hsla(145, 63%, 35%, 0.1); border: 2px solid var(--color-success); color: var(--color-success); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 24px; font-weight: 500;">
                ✓ Test-email succesvol verzonden naar <strong><?php echo htmlspecialchars($test_recipient); ?></strong>!<br>
                Open uw lokale Mailpit interface op <a href="http://localhost:8025/" target="_blank" style="color: var(--color-success); font-weight: 700; text-decoration: underline;">http://localhost:8025/</a> om de e-mail te bekijken.
            </div>
        <?php elseif ($error_message): ?>
            <div style="background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 24px; font-weight: 500;">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label class="form-label" for="recipient">Ontvanger E-mailadres:</label>
                <input type="email" id="recipient" name="recipient" class="form-control" value="<?php echo htmlspecialchars($test_recipient); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="subject">Onderwerp:</label>
                <input type="text" id="subject" name="subject" class="form-control" value="<?php echo htmlspecialchars($subject); ?>" required>
            </div>
            
            <button type="submit" class="btn">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"></path>
                </svg>
                Verstuur Test-Email naar Mailpit
            </button>
        </form>
    </div>

    <div class="card">
        <h2>🛠 Hoe Mailpit te starten op macOS</h2>
        <p style="font-size: 0.9rem; margin-bottom: 15px;">Als Mailpit nog niet geïnstalleerd of actief is, kunt u het starten met de volgende commando's in uw macOS terminal:</p>
        
        <div class="info-box">
            <h3>1. Installeren via Homebrew (indien nodig):</h3>
            <code>brew install mailpit</code>
            
            <h3 style="margin-top: 15px;">2. Mailpit SMTP server opstarten:</h3>
            <code>mailpit</code>
        </div>
        
        <p style="font-size: 0.9rem;">Zodra Mailpit draait, vangt het alle uitgaande e-mails op poort <strong>1025</strong> op. U kunt de inkomende mails in real-time bekijken via de browser op:</p>
        <a href="http://localhost:8025/" target="_blank" class="btn btn-outline" style="text-decoration: none;">
            Open Mailpit Web UI (http://localhost:8025/) &rarr;
        </a>
    </div>

    <?php if ($log_contents): ?>
        <div class="card">
            <h2>📄 Laatste E-mail Logboek Entry (`data/email_log.txt`)</h2>
            <pre class="log-box"><?php echo $log_contents; ?></pre>
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="../ouderportaal.php" style="color: var(--color-primary); font-weight: 600; text-decoration: underline;">&larr; Terug naar Ouderportaal</a>
    </div>
</div>

</body>
</html>
