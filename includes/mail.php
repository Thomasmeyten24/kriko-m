<?php
/**
 * Local SMTP Socket Mail Sender & HTML Email Template Helper
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/db.php';

/**
 * Sends a premium HTML email. Automatically attempts SMTP on localhost:1025 (Mailpit),
 * falls back to PHP mail(), and logs everything to data/email_log.txt.
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject line
 * @param string $body_html Raw HTML content of email body
 * @param string $from Sender email address
 * @return bool True if successful, false otherwise
 */
function scouts_send_mail($to, $subject, $body_html, $from = 'no-reply@kriko-m.be') {
    // Determine path to email log file
    $log_file = dirname(__DIR__) . '/data/email_log.txt';
    
    // Renders full beautiful email template
    $full_html = render_email_template($subject, $body_html);
    
    // 1. Log to data/email_log.txt for local verification
    $log_entry = "[" . date('Y-m-d H:i:s') . "] TO: {$to} | SUBJECT: {$subject}\n";
    $log_entry .= "BODY:\n" . strip_tags($body_html) . "\n";
    $log_entry .= str_repeat('=', 60) . "\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);

    // 2. Try SMTP via socket on localhost:1025 (Mailpit default)
    $host = '127.0.0.1';
    $port = 1025;
    
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, 0.5); // short 500ms timeout
    
    if ($socket) {
        $getResponse = function($sock) {
            $response = '';
            while (($line = fgets($sock, 512)) !== false) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') {
                    break;
                }
            }
            return $response;
        };
        
        $getResponse($socket); // Greeting
        
        fwrite($socket, "EHLO localhost\r\n");
        $getResponse($socket);
        
        fwrite($socket, "MAIL FROM:<{$from}>\r\n");
        $getResponse($socket);
        
        fwrite($socket, "RCPT TO:<{$to}>\r\n");
        $getResponse($socket);
        
        fwrite($socket, "DATA\r\n");
        $getResponse($socket);
        
        // Prepare headers and message body (handling MIME and UTF-8)
        $headers = [
            "From: Scouts Kriko-M <{$from}>",
            "To: <{$to}>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "Date: " . date('r'),
            "Message-ID: <" . uniqid('', true) . "@kriko-m.be>",
        ];
        
        $email_content = implode("\r\n", $headers) . "\r\n\r\n" . $full_html . "\r\n.\r\n";
        
        fwrite($socket, $email_content);
        $getResponse($socket);
        
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return true;
    }
    
    // 3. Fallback to native PHP mail() if SMTP socket is closed
    $headers_str = "From: Scouts Kriko-M <{$from}>\r\n";
    $headers_str .= "Reply-To: {$from}\r\n";
    $headers_str .= "MIME-Version: 1.0\r\n";
    $headers_str .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers_str .= "Content-Transfer-Encoding: 8bit\r\n";
    
    return @mail($to, $subject, $full_html, $headers_str);
}

/**
 * Wraps dynamic body HTML inside a gorgeous Scouts Kriko-M brand template
 */
function render_email_template($title, $body_html) {
    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body { font-family: "Outfit", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background-color: #f7f5f0; color: #2d3748; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }
        .wrapper { max-width: 600px; margin: 40px auto; background-color: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 25px rgba(0, 0, 0, 0.04); }
        .header { background: linear-gradient(135deg, #7a1b2e 0%, #a22c42 100%); padding: 35px 30px; text-align: center; color: #ffffff; border-bottom: 3px solid #d97706; }
        .header h1 { margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; font-family: "Outfit", sans-serif; text-shadow: 0 2px 4px rgba(0,0,0,0.15); }
        .content { padding: 40px 35px; line-height: 1.6; font-size: 15px; background-color: #ffffff; font-family: "Plus Jakarta Sans", "Segoe UI", sans-serif; }
        .content h2 { color: #7a1b2e; font-size: 20px; font-weight: 700; margin-top: 0; margin-bottom: 20px; font-family: "Outfit", sans-serif; }
        .button-container { text-align: center; margin: 30px 0; }
        .button { background-color: #d97706; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 30px; font-weight: 700; display: inline-block; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.35); transition: all 0.2s ease; font-family: "Outfit", sans-serif; font-size: 15px; }
        .receipt-table { width: 100%; border-collapse: collapse; margin: 24px 0; background-color: #faf9f6; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; }
        .receipt-table th { background-color: #7a1b2e; color: #ffffff; padding: 12px 16px; font-size: 0.85rem; font-weight: 700; text-align: left; text-transform: uppercase; }
        .receipt-table td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; }
        .receipt-total { background-color: #7a1b2e; color: #ffffff; font-weight: bold; }
        .payment-box { background-color: #fffbeb; border: 2px dashed #d97706; border-radius: 12px; padding: 24px; margin: 24px 0; }
        .payment-box h4 { margin: 0 0 10px 0; color: #7a1b2e; font-size: 1.1rem; font-weight: 700; }
        .payment-details { display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; font-size: 0.9rem; margin-bottom: 15px; }
        .payment-details strong { color: #4a5568; }
        .payment-details code { font-family: monospace; font-size: 1rem; color: #7a1b2e; background-color: #ffffff; padding: 2px 6px; border-radius: 4px; border: 1px solid #e2e8f0; }
        .warning-text { color: #c53030; font-size: 0.82rem; font-weight: 600; margin: 0; line-height: 1.4; }
        .footer { background-color: #f8fafc; padding: 30px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 13px; color: #64748b; }
        .footer a { color: #7a1b2e; text-decoration: underline; font-weight: 600; }
        .footer p { margin: 6px 0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Scouts Kriko-M</h1>
        </div>
        <div class="content">
            ' . $body_html . '
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' Scouts Kriko-M Sint-Niklaas. Alle rechten voorbehouden.</p>
            <p>Aangesloten bij Scouts en Gidsen Vlaanderen. Vragen? Mail naar <a href="mailto:groepsleiding@kriko-m.be">groepsleiding@kriko-m.be</a></p>
        </div>
    </div>
</body>
</html>';
}
