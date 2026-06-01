<?php
/**
 * Ouderportaal - Parent Accounts and Dashboard
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/parent_auth.php';

// Handle logout trigger
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    parent_logout();
    header('Location: ouderportaal.php');
    exit;
}
// Intercept guest parent actions (like confirming payment or requesting cancellation while logged out)
if (!is_parent_logged_in() && isset($_GET['action']) && in_array($_GET['action'], ['confirm_payment', 'request_cancellation'])) {
    $_SESSION['redirect_after_parent_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['parent_error'] = 'U moet eerst inloggen om deze actie te kunnen voltooien.';
}

$error = '';
$success = '';

// Load flash messages from session
if (isset($_SESSION['parent_success'])) {
    $success = $_SESSION['parent_success'];
    unset($_SESSION['parent_success']);
}
if (isset($_SESSION['parent_error'])) {
    $error = $_SESSION['parent_error'];
    unset($_SESSION['parent_error']);
}

$active_tab = 'login'; // 'login' or 'register' for guests


// Handle parent login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'login') {
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($email) || empty($password)) {
        $error = 'Gelieve uw e-mailadres en wachtwoord in te vullen.';
    } else {
        if (verify_parent_login($email, $password)) {
            // Check if there was a redirect saved
            if (isset($_SESSION['redirect_after_parent_login'])) {
                $redirect = $_SESSION['redirect_after_parent_login'];
                unset($_SESSION['redirect_after_parent_login']);
                header("Location: $redirect");
                exit;
            }
            header('Location: ouderportaal.php');
            exit;
        } else {
            $error = 'Ongeldig e-mailadres of wachtwoord. Gelieve opnieuw te proberen.';
        }
    }
}

// Handle parent registration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'register') {
    $active_tab = 'register';
    $first_name = isset($_POST['first_name']) ? $_POST['first_name'] : '';
    $last_name = isset($_POST['last_name']) ? $_POST['last_name'] : '';
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Gelieve alle verplichte velden (*) in te vullen.';
    } else {
        $res = register_parent($first_name, $last_name, $email, $password, $phone);
        if ($res['success']) {
            $success = 'Uw ouderaccount is succesvol aangemaakt! U bent nu ingelogd.';
            $active_tab = 'dashboard';
        } else {
            $error = $res['message'];
        }
    }
}

// Handle adding a child
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'add_child' && is_parent_logged_in()) {
    $first_name = isset($_POST['child_first_name']) ? $_POST['child_first_name'] : '';
    $last_name = isset($_POST['child_last_name']) ? $_POST['child_last_name'] : '';
    $dob = isset($_POST['child_dob']) ? $_POST['child_dob'] : '';
    
    if (empty($first_name) || empty($last_name) || empty($dob)) {
        $error = 'Vul alle gegevens van uw kind in.';
    } else {
        $classification = classify_child_by_dob($dob);
        $parents = read_db('parents');
        $parent_id = $_SESSION['parent_id'];
        
        $child_id = 'child_' . uniqid();
        $new_child = [
            'id' => $child_id,
            'first_name' => trim($first_name),
            'last_name' => trim($last_name),
            'dob' => $dob,
            'tak' => $classification['tak'],
            'warning' => $classification['warning']
        ];
        
        // Update database
        foreach ($parents as &$parent) {
            if ($parent['id'] === $parent_id) {
                if (!isset($parent['children'])) {
                    $parent['children'] = [];
                }
                $parent['children'][] = $new_child;
                break;
            }
        }
        write_db('parents', $parents);
        $success = htmlspecialchars($new_child['first_name']) . ' is succesvol toegevoegd en automatisch ingedeeld bij de ' . ucfirst($new_child['tak']) . '!';
    }
}

// Handle adding a secondary parent login (partner access)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'add_partner' && is_parent_logged_in()) {
    if (isset($_SESSION['parent_role']) && $_SESSION['parent_role'] !== 'primary') {
        $error = 'Enkel de hoofdaccountbeheerder kan een partner-login toevoegen.';
    } else {
        $partner_first_name = isset($_POST['partner_first_name']) ? trim($_POST['partner_first_name']) : '';
        $partner_last_name = isset($_POST['partner_last_name']) ? trim($_POST['partner_last_name']) : '';
        $partner_email = isset($_POST['partner_email']) ? strtolower(trim($_POST['partner_email'])) : '';
        $partner_password = isset($_POST['partner_password']) ? $_POST['partner_password'] : '';
        $partner_phone = isset($_POST['partner_phone']) ? trim($_POST['partner_phone']) : '';
        
        if (empty($partner_first_name) || empty($partner_last_name) || empty($partner_email) || empty($partner_password)) {
            $error = 'Vul alle verplichte velden (*) van uw partner in.';
        } else {
            $parents = read_db('parents');
            $parent_id = $_SESSION['parent_id'];
            $email_exists = false;
            
            // Validate email duplicate check
            foreach ($parents as $parent) {
                if (strtolower($parent['email']) === $partner_email) {
                    $email_exists = true;
                    break;
                }
                if (isset($parent['secondary_email']) && strtolower($parent['secondary_email']) === $partner_email) {
                    $email_exists = true;
                    break;
                }
            }
            
            if ($email_exists) {
                $error = 'Dit e-mailadres is al in gebruik door een ander account.';
            } else {
                $updated = false;
                foreach ($parents as &$parent) {
                    if ($parent['id'] === $parent_id) {
                        $parent['secondary_first_name'] = $partner_first_name;
                        $parent['secondary_last_name'] = $partner_last_name;
                        $parent['secondary_email'] = $partner_email;
                        $parent['secondary_password_hash'] = password_hash($partner_password, PASSWORD_DEFAULT);
                        $parent['secondary_phone'] = $partner_phone;
                        $updated = true;
                        break;
                    }
                }
                
                if ($updated) {
                    write_db('parents', $parents);
                    $success = 'Partner-login succesvol toegevoegd! Mama en Papa kunnen nu beide met hun eigen e-mail en wachtwoord inloggen op hetzelfde account.';
                } else {
                    $error = 'Account niet gevonden.';
                }
            }
        }
    }
}

// Handle removing a secondary parent login (partner access removal)
if (isset($_GET['action']) && $_GET['action'] === 'remove_partner' && is_parent_logged_in()) {
    if (isset($_SESSION['parent_role']) && $_SESSION['parent_role'] !== 'primary') {
        $_SESSION['parent_error'] = 'Enkel de hoofdaccountbeheerder kan de partner-login verwijderen.';
    } else {
        $parents = read_db('parents');
        $parent_id = $_SESSION['parent_id'];
        $updated = false;
        
        foreach ($parents as &$parent) {
            if ($parent['id'] === $parent_id) {
                unset($parent['secondary_first_name']);
                unset($parent['secondary_last_name']);
                unset($parent['secondary_email']);
                unset($parent['secondary_password_hash']);
                unset($parent['secondary_phone']);
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            write_db('parents', $parents);
            $_SESSION['parent_success'] = 'Partner-login is succesvol verwijderd van uw account.';
        } else {
            $_SESSION['parent_error'] = 'Account niet gevonden.';
        }
    }
    header('Location: ouderportaal.php');
    exit;
}

// Handle payment confirmation reported by parent ("Ik heb overgeschreven")
if (isset($_GET['action']) && $_GET['action'] === 'confirm_payment' && isset($_GET['reg_id']) && is_parent_logged_in()) {
    $reg_id = $_GET['reg_id'];
    $parent_id = $_SESSION['parent_id'];
    
    $registrations = read_db('registrations');
    $updated = false;
    
    $reg_found = false;
    $owns_child = false;
    $status_pending = false;
    $reg_child_name = 'Onbekend';
    $reg_status = '';
    
    // Fetch parent info
    $parents = read_db('parents');
    $current_p = null;
    foreach ($parents as $p) {
        if ($p['id'] === $parent_id) {
            $current_p = $p;
            break;
        }
    }
    
    foreach ($registrations as &$reg) {
        if ($reg['id'] === $reg_id) {
            $reg_found = true;
            $reg_child_name = isset($reg['child_name']) ? $reg['child_name'] : 'Onbekend';
            $reg_status = isset($reg['status']) ? $reg['status'] : '';
            
            // Check direct parent_id matching first
            if (isset($reg['parent_id']) && $reg['parent_id'] === $parent_id) {
                $owns_child = true;
            } elseif ($current_p) {
                // Check if email matches primary or secondary parent email
                if (isset($reg['email']) && (strtolower(trim($reg['email'])) === strtolower(trim($current_p['email'])) || (isset($current_p['secondary_email']) && strtolower(trim($reg['email'])) === strtolower(trim($current_p['secondary_email']))))) {
                    $owns_child = true;
                    $reg['parent_id'] = $parent_id; // Auto-link parent_id permanently
                } else {
                    // Check children
                    if (isset($current_p['children'])) {
                        foreach ($current_p['children'] as $child) {
                            if (isset($reg['child_id']) && $reg['child_id'] === $child['id']) {
                                $owns_child = true;
                                $reg['parent_id'] = $parent_id; // Auto-link parent_id permanently
                                break;
                            }
                            $child_full_name = strtolower(trim($child['first_name'] . ' ' . $child['last_name']));
                            if (strtolower(trim($reg['child_name'])) === $child_full_name) {
                                $owns_child = true;
                                $reg['parent_id'] = $parent_id; // Auto-link parent_id permanently
                                break;
                            }
                        }
                    }
                }
            }
            
            if ($reg['status'] === 'pending') {
                $status_pending = true;
            }
            
            if ($owns_child && $status_pending) {
                $reg['status'] = 'waiting_approval';
                $updated = true;
                break;
            }
        }
    }
    
    if ($updated) {
        $write_success = write_db('registrations', $registrations);
        if ($write_success) {
            $_SESSION['parent_success'] = 'Bedankt! Uw betaling is gemeld. De status is bijgewerkt naar "Wachten op bevestiging van de leiding".';
        } else {
            $_SESSION['parent_error'] = 'Fout: Kon de betalingsmelding niet opslaan in de database. Gelieve te controleren of de schrijfrechten voor de map "data" en het bestand "data/registrations.json" correct zijn ingesteld.';
        }
    } else {
        if (!$reg_found) {
            $_SESSION['parent_error'] = 'Kon betalingsmelding niet verwerken: Inschrijving met ID "' . htmlspecialchars($reg_id) . '" is niet gevonden in onze database.';
        } elseif (!$owns_child) {
            $_SESSION['parent_error'] = 'Kon betalingsmelding niet verwerken: Deze inschrijving (deelnemer: ' . htmlspecialchars($reg_child_name) . ') is niet gekoppeld aan uw ouderaccount of e-mailadres.';
        } elseif (!$status_pending) {
            $_SESSION['parent_error'] = 'Kon betalingsmelding niet verwerken: Deze inschrijving is al gemeld of verwerkt (huidige status: "' . htmlspecialchars($reg_status) . '").';
        } else {
            $_SESSION['parent_error'] = 'Kon betalingsmelding niet verwerken wegens een onbekende fout.';
        }
    }
    session_write_close();
    header('Location: ouderportaal.php');
    exit;
}

// Handle order payment confirmation reported by parent ("Ik heb overgeschreven")
if (isset($_GET['action']) && $_GET['action'] === 'confirm_order_payment' && isset($_GET['order_id']) && is_parent_logged_in()) {
    $order_id = $_GET['order_id'];
    $parent_id = $_SESSION['parent_id'];
    
    $orders = read_db('orders');
    $updated = false;
    
    $order_found = false;
    $owns_order = false;
    $status_pending = false;
    
    // Fetch parent info
    $parents = read_db('parents');
    $current_p = null;
    foreach ($parents as $p) {
        if ($p['id'] === $parent_id) {
            $current_p = $p;
            break;
        }
    }
    
    foreach ($orders as &$ord) {
        if ($ord['id'] === $order_id) {
            $order_found = true;
            
            // Check direct parent_id matching first
            if (isset($ord['parent_id']) && $ord['parent_id'] === $parent_id) {
                $owns_order = true;
            } elseif ($current_p) {
                // Check if email matches parent email
                if (isset($ord['email']) && (strtolower(trim($ord['email'])) === strtolower(trim($current_p['email'])) || (isset($current_p['secondary_email']) && strtolower(trim($ord['email'])) === strtolower(trim($current_p['secondary_email']))))) {
                    $owns_order = true;
                    $ord['parent_id'] = $parent_id; // Auto-link parent_id permanently
                }
            }
            
            if ($ord['status'] === 'pending') {
                $status_pending = true;
            }
            
            if ($owns_order && $status_pending) {
                $ord['status'] = 'waiting_approval';
                $updated = true;
                break;
            }
        }
    }
    
    if ($updated) {
        $write_success = write_db('orders', $orders);
        if ($write_success) {
            $_SESSION['parent_success'] = 'Bedankt! Uw webshop betaling is gemeld. De status is bijgewerkt naar "Wachten op bevestiging".';
        } else {
            $_SESSION['parent_error'] = 'Fout: Kon de betalingsmelding niet opslaan in de database. Gelieve te controleren of de schrijfrechten voor de map "data" en het bestand "data/orders.json" correct zijn ingesteld.';
        }
    } else {
        if (!$order_found) {
            $_SESSION['parent_error'] = 'Kon betalingsmelding niet verwerken: Bestelling met ID "' . htmlspecialchars($order_id) . '" is niet gevonden.';
        } elseif (!$owns_order) {
            $_SESSION['parent_error'] = 'Kon betalingsmelding niet verwerken: Deze bestelling is niet gekoppeld aan uw ouderaccount.';
        } elseif (!$status_pending) {
            $_SESSION['parent_error'] = 'Kon betalingsmelding niet verwerken: Deze bestelling is al gemeld of verwerkt.';
        } else {
            $_SESSION['parent_error'] = 'Kon betalingsmelding niet verwerken wegens een onbekende fout.';
        }
    }
    session_write_close();
    header('Location: ouderportaal.php');
    exit;
}

// Handle cancellation request trigger
if (isset($_GET['action']) && $_GET['action'] === 'request_cancellation' && isset($_GET['reg_id']) && is_parent_logged_in()) {
    $reg_id = $_GET['reg_id'];
    $parent_id = $_SESSION['parent_id'];
    
    $registrations = read_db('registrations');
    $updated = false;
    
    // Fetch parent info
    $parents = read_db('parents');
    $current_p = null;
    foreach ($parents as $p) {
        if ($p['id'] === $parent_id) {
            $current_p = $p;
            break;
        }
    }
    
    foreach ($registrations as &$reg) {
        if ($reg['id'] === $reg_id) {
            $owns_child = false;
            
            // Check direct parent_id matching first
            if (isset($reg['parent_id']) && $reg['parent_id'] === $parent_id) {
                $owns_child = true;
            } elseif ($current_p) {
                // Check if email matches primary or secondary parent email
                if (isset($reg['email']) && (strtolower(trim($reg['email'])) === strtolower(trim($current_p['email'])) || (isset($current_p['secondary_email']) && strtolower(trim($reg['email'])) === strtolower(trim($current_p['secondary_email']))))) {
                    $owns_child = true;
                    $reg['parent_id'] = $parent_id; // Auto-link parent_id permanently
                } else {
                    // Check children
                    if (isset($current_p['children'])) {
                        foreach ($current_p['children'] as $child) {
                            if (isset($reg['child_id']) && $reg['child_id'] === $child['id']) {
                                $owns_child = true;
                                $reg['parent_id'] = $parent_id; // Auto-link parent_id permanently
                                break;
                            }
                            $child_full_name = strtolower(trim($child['first_name'] . ' ' . $child['last_name']));
                            if (strtolower(trim($reg['child_name'])) === $child_full_name) {
                                $owns_child = true;
                                $reg['parent_id'] = $parent_id; // Auto-link parent_id permanently
                                break;
                            }
                        }
                    }
                }
            }
            
            if ($owns_child) {
                $reg['cancellation_requested'] = true;
                $updated = true;
                break;
            }
        }
    }
    
    if ($updated) {
        $write_success = write_db('registrations', $registrations);
        if ($write_success) {
            $_SESSION['parent_success'] = 'Uw annuleringsaanvraag is verstuurd naar de leiding. Zij zullen dit verwerken.';
        } else {
            $_SESSION['parent_error'] = 'Fout: Kon de annuleringsaanvraag niet opslaan in de database. Gelieve te controleren of de schrijfrechten voor de map "data" en het bestand "data/registrations.json" correct zijn ingesteld.';
        }
    } else {
        $_SESSION['parent_error'] = 'Kon annuleringsaanvraag niet indienen. Onvoldoende rechten of inschrijving niet gevonden.';
    }
    session_write_close();
    header('Location: ouderportaal.php');
    exit;
}

// Handle resetting of successful event registration view state
if (isset($_GET['action']) && $_GET['action'] === 'reset_events_state' && is_parent_logged_in()) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['last_event_success_data']);
    session_write_close();
    header('Location: ouderportaal.php?show_evenementen=1');
    exit;
}

// Handle event registration submission in parent portal (100% SPA)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'register_event' && is_parent_logged_in()) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $child_id = filter_input(INPUT_POST, 'child_id', FILTER_SANITIZE_SPECIAL_CHARS);
    $parent_name = filter_input(INPUT_POST, 'parent_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);
    $child_tak = filter_input(INPUT_POST, 'child_tak', FILTER_SANITIZE_SPECIAL_CHARS);
    $activity_type = filter_input(INPUT_POST, 'activity_type', FILTER_SANITIZE_SPECIAL_CHARS);
    $remarks = filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_SPECIAL_CHARS);
    
    // Find child and validate
    $current_parent = get_logged_in_parent();
    $child_data = null;
    if ($current_parent && isset($current_parent['children'])) {
        foreach ($current_parent['children'] as $child) {
            if ($child['id'] === $child_id) {
                $child_data = $child;
                break;
            }
        }
    }
    
    $settings = read_db('settings');
    $takken_data = isset($settings['takken']) ? $settings['takken'] : [];
    
    if (empty($child_id) || empty($parent_name) || empty($email) || empty($phone) || empty($child_tak) || empty($activity_type)) {
        $error = 'Vul alstublieft alle verplichte velden in.';
    } elseif (!$child_data) {
        $error = 'Geselecteerd kind is niet gevonden in uw account. Voeg uw kind eerst toe in het Ouderportaal.';
    } elseif ($child_data['tak'] !== $child_tak) {
        $error = 'Dit kind is ingedeeld bij de ' . ucfirst($child_data['tak']) . ' en kan niet ingeschreven worden voor een activiteit van de ' . ucfirst($child_tak) . '.';
    } elseif (!array_key_exists($child_tak, $takken_data)) {
        $error = 'Ongeldige tak geselecteerd.';
    } else {
        $tak = $takken_data[$child_tak];
        $activities = isset($tak['activities']) ? $tak['activities'] : [];
        
        if (!isset($activities[$activity_type]) || !$activities[$activity_type]['active']) {
            $error = 'Dit evenement is momenteel niet beschikbaar of inactief.';
        } else {
            $act = $activities[$activity_type];
            $today = date('Y-m-d');
            
            // Validate date constraints
            if ($today < $act['reg_open']) {
                $error = 'De inschrijvingen voor dit evenement zijn nog niet geopend.';
            } elseif ($today > $act['reg_close']) {
                $error = 'De inschrijvingsperiode voor dit evenement is verstreken.';
            } else {
                // Check duplicate
                $all_registrations = read_db('registrations');
                $is_duplicate = false;
                foreach ($all_registrations as $reg) {
                    if (
                        isset($reg['child_id']) && $reg['child_id'] === $child_data['id'] &&
                        isset($reg['activity_type']) && $reg['activity_type'] === $activity_type &&
                        isset($reg['child_tak']) && $reg['child_tak'] === $child_tak
                    ) {
                        $is_duplicate = true;
                        break;
                    }
                }
                
                if ($is_duplicate) {
                    $error = 'Dit kind (' . htmlspecialchars($child_data['first_name'] . ' ' . $child_data['last_name']) . ') is al ingeschreven voor het evenement "' . htmlspecialchars($act['title']) . '". U kunt een lid niet twee keer inschrijven voor dezelfde activiteit.';
                } else {
                    // Generate structured communication Modulo 97
                    $first_ten = rand(1000000000, 9999999999);
                    $modulo = $first_ten % 97;
                    $check = ($modulo === 0) ? 97 : $modulo;
                    $check_str = str_pad($check, 2, '0', STR_PAD_LEFT);
                    $full_twelve = str_pad($first_ten . $check_str, 12, '0', STR_PAD_LEFT);
                    
                    $part1 = substr($full_twelve, 0, 3);
                    $part2 = substr($full_twelve, 3, 4);
                    $part3 = substr($full_twelve, 7, 5);
                    $comm = "+++{$part1}/{$part2}/{$part3}+++";
                    $reg_id = 'reg_' . uniqid();
                    
                    $registration = [
                        'id' => $reg_id,
                        'date' => date('Y-m-d H:i:s'),
                        'status' => 'pending',
                        'parent_id' => $current_parent['id'],
                        'child_id' => $child_data['id'],
                        'child_name' => $child_data['first_name'] . ' ' . $child_data['last_name'],
                        'child_tak' => $child_tak,
                        'activity_type' => $activity_type,
                        'activity_title' => $act['title'],
                        'customer_name' => $parent_name,
                        'email' => $email,
                        'phone' => $phone,
                        'price' => (float)$act['price'],
                        'communication' => $comm,
                        'remarks' => trim($remarks)
                    ];
                    
                    // Write to database registrations
                    $registrations = read_db('registrations');
                    $registrations[] = $registration;
                    write_db('registrations', $registrations);
                    
                    // Send Email Confirmation Invoice using Mailpit client
                    require_once __DIR__ . '/includes/mail.php';
                    $bank_iban = isset($settings['bank_iban']) ? $settings['bank_iban'] : 'BE59 7360 6413 2626';
                    $bank_holder = isset($settings['bank_holder']) ? $settings['bank_holder'] : 'Scouts Kriko-M';
                    
                    $email_body = "<h2>Beste " . htmlspecialchars($parent_name) . ",</h2>
                    <p>Gefeliciteerd! Uw inschrijving voor <strong>" . htmlspecialchars($act['title']) . "</strong> is succesvol geregistreerd voor uw kind: <strong>" . htmlspecialchars($child_data['first_name'] . ' ' . $child_data['last_name']) . "</strong>.</p>
                    
                    <p>Gelieve de deelnamebijdrage van <strong>€" . number_format($act['price'], 2, ',', '.') . "</strong> handmatig via overschrijving te voldoen met de onderstaande details:</p>
                    
                    <div class='payment-box'>
                        <h4>💳 Overschrijving details</h4>
                        <div class='payment-details'>
                            <strong>Begunstigde:</strong> <span>" . htmlspecialchars($bank_holder) . "</span>
                            <strong>IBAN-nummer:</strong> <code>" . htmlspecialchars($bank_iban) . "</code>
                            <strong>Bedrag:</strong> <span style='font-size: 1.1rem; color: #d97706; font-weight: 700;'>€" . number_format($act['price'], 2, ',', '.') . "</span>
                            <strong>Gestructureerde mededeling:</strong> <code style='font-weight: bold; color: #7a1b2e;'>{$comm}</code>
                        </div>
                        <p class='warning-text'>⚠ Let op: Vermeld de gestructureerde mededeling exact zoals hierboven getoond, anders kan de leiding uw betaling niet automatisch koppelen aan de inschrijving.</p>
                    </div>
                    
                    <h3 style='color: #7a1b2e; font-size: 1.15rem; margin-top: 25px; margin-bottom: 10px; font-weight: bold;'>Inschrijving samenvatting</h3>
                    <table style='width: 100%; border-collapse: collapse; margin-bottom: 25px;'>
                        <thead>
                            <tr style='background-color: #7a1b2e; color: white;'>
                                <th style='padding: 10px; text-align: left;'>Deelnemer</th>
                                <th style='padding: 10px; text-align: left;'>Tak</th>
                                <th style='padding: 10px; text-align: right;'>Prijs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style='padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: left;'>" . htmlspecialchars($child_data['first_name'] . ' ' . $child_data['last_name']) . "</td>
                                <td style='padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: left;'>" . ucfirst(htmlspecialchars($child_tak)) . "</td>
                                <td style='padding: 10px; border-bottom: 1px solid #e2e8f0; text-align: right;'>€" . number_format($act['price'], 2, ',', '.') . "</td>
                            </tr>
                        </tbody>
                    </table>";
                    
                    if (!empty($remarks)) {
                        $email_body .= "<div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 25px;'>
                            <strong>Opmerkingen / Medische info:</strong><br>
                            <span style='font-style: italic; font-size: 0.9rem; color: #4a5568;'>" . nl2br(htmlspecialchars($remarks)) . "</span>
                        </div>";
                    }
                    
                    $email_body .= "<p>Zodra we uw overschrijving ontvangen, keurt de takleiding de inschrijving goed. U kunt de status en betalingsbevestiging te allen tijde opvolgen via het Ouderportaal.</p>
                    <p>We kijken er alvast enorm naar uit om er een geweldig avontuur van te maken!</p>";
                    
                    scouts_send_mail($email, "Inschrijving Bevestiging: " . $act['title'] . " - Scouts Kriko-M", $email_body);
                    
                    // Cache success data in session
                    $_SESSION['last_event_success_data'] = $registration;
                    $_SESSION['parent_success'] = 'Gefeliciteerd! De inschrijving voor ' . htmlspecialchars($child_data['first_name']) . ' is succesvol ontvangen.';
                    
                    session_write_close();
                    header('Location: ouderportaal.php?show_evenementen=1');
                    exit;
                }
            }
        }
    }
    
    if (!empty($error)) {
        $_SESSION['parent_error'] = $error;
        session_write_close();
        header('Location: ouderportaal.php?show_evenementen=1');
        exit;
    }
}

// Fetch current parent, registrations, and shop orders
$current_parent = get_logged_in_parent();
$my_children = ($current_parent && isset($current_parent['children'])) ? $current_parent['children'] : [];
$my_registrations = [];
$my_orders = [];
$active_shop_items = [];
$shop_categories = [];
$event_success_data = null;
if (is_parent_logged_in() && isset($_SESSION['last_event_success_data'])) {
    $event_success_data = $_SESSION['last_event_success_data'];
}

if ($current_parent) {
    // Load webshop database catalogue
    $all_items = read_db('shop');
    $active_shop_items = array_filter($all_items, function($item) {
        return isset($item['active']) && $item['active'] === true;
    });
    $shop_categories = [
        'kledij' => 'Kriko-M Kledij',
        'uniform' => 'Scouts Uniform',
        'accessoires' => 'Accessoires & Kentekens'
    ];
    // 1. Fetch event registrations
    $all_registrations = read_db('registrations');
    $needs_write_back = false;
    
    foreach ($all_registrations as &$reg) {
        $is_mine = false;
        
        // Match by parent_id
        if (isset($reg['parent_id']) && $reg['parent_id'] === $current_parent['id']) {
            $is_mine = true;
        } elseif (isset($reg['email']) && (strtolower(trim($reg['email'])) === strtolower(trim($current_parent['email'])) || (isset($current_parent['secondary_email']) && strtolower(trim($reg['email'])) === strtolower(trim($current_parent['secondary_email']))))) {
            $is_mine = true;
            // Auto-link parent_id permanently in the db if not yet set!
            if (!isset($reg['parent_id']) || $reg['parent_id'] !== $current_parent['id']) {
                $reg['parent_id'] = $current_parent['id'];
                $needs_write_back = true;
            }
        } else {
            // Fallback match by child name or child_id
            foreach ($my_children as $child) {
                if (isset($reg['child_id']) && $reg['child_id'] === $child['id']) {
                    $is_mine = true;
                    if (!isset($reg['parent_id']) || $reg['parent_id'] !== $current_parent['id']) {
                        $reg['parent_id'] = $current_parent['id'];
                        $needs_write_back = true;
                    }
                    break;
                }
                $child_full_name = strtolower(trim($child['first_name'] . ' ' . $child['last_name']));
                if (strtolower(trim($reg['child_name'])) === $child_full_name) {
                    $is_mine = true;
                    if (!isset($reg['parent_id']) || $reg['parent_id'] !== $current_parent['id']) {
                        $reg['parent_id'] = $current_parent['id'];
                        $needs_write_back = true;
                    }
                    break;
                }
            }
        }
        
        if ($is_mine) {
            $my_registrations[] = $reg;
        }
    }
    
    if ($needs_write_back) {
        write_db('registrations', $all_registrations);
    }
    
    // 2. Fetch webshop orders
    $all_orders = read_db('orders');
    foreach ($all_orders as $ord) {
        $is_mine = false;
        
        // Match by parent_id
        if (isset($ord['parent_id']) && $ord['parent_id'] === $current_parent['id']) {
            $is_mine = true;
        } else {
            // Fallback match by primary or secondary emails
            if (strtolower(trim($ord['email'])) === strtolower(trim($current_parent['email']))) {
                $is_mine = true;
            }
            if (!$is_mine && isset($current_parent['secondary_email']) && strtolower(trim($ord['email'])) === strtolower(trim($current_parent['secondary_email']))) {
                $is_mine = true;
            }
        }
        
        if ($is_mine) {
            $my_orders[] = $ord;
        }
    }
}

$page_title = "Ouderportaal";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Banner Section -->
<section class="tak-hero givers" style="padding: 60px 0 40px; text-align: center; background: linear-gradient(rgba(78, 18, 28, 0.85), rgba(78, 18, 28, 0.95)); color: var(--color-bg-white);">
    <div class="container">
        <h2 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 8px; color: var(--color-accent);">Ouderportaal</h2>
        <p style="font-size: 1.1rem; max-width: 600px; margin: 0 auto; color: var(--color-bg-linen); opacity: 0.9;">
            Meld u aan, beheer uw kinderen en bekijk betalingen of inschrijvingen voor weekends en kampen.
        </p>
    </div>
</section>

<section class="section container" id="ouderportaal-wrapper" style="padding-top: 40px; padding-bottom: 80px; min-height: 60vh;">
    
    <!-- System Notification Banner -->
    <?php if (!empty($success)): ?>
        <div class="system-success-banner" style="background-color: hsla(145, 63%, 35%, 0.1); border: 2px solid var(--color-success); color: var(--color-success); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 30px; font-weight: 600; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px;">
            <svg style="width: 24px; height: 24px; fill: none; stroke: currentColor;" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="system-error-banner" style="background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 30px; font-weight: 600; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px;">
            <svg style="width: 24px; height: 24px; fill: none; stroke: currentColor;" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!is_parent_logged_in()): ?>
        <!-- GUEST VIEW: LOGIN / REGISTER FORMS -->
        <div style="max-width: 550px; margin: 0 auto; background: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-lg); overflow: hidden; border: 1px solid var(--color-border);">
            
            <!-- Auth Tab Toggles -->
            <div style="display: flex; background: var(--color-bg-linen); border-bottom: 1px solid var(--color-border);">
                <button onclick="toggleAuthTab('login')" id="tab-btn-login" style="flex: 1; padding: 16px; font-weight: 700; border: none; background: none; cursor: pointer; color: var(--color-text-dark); transition: var(--transition-fast); border-bottom: 3px solid <?php echo $active_tab === 'login' ? 'var(--color-primary)' : 'transparent'; ?>; opacity: <?php echo $active_tab === 'login' ? '1' : '0.6'; ?>; font-size: 1rem;">
                    Aanmelden
                </button>
                <button onclick="toggleAuthTab('register')" id="tab-btn-register" style="flex: 1; padding: 16px; font-weight: 700; border: none; background: none; cursor: pointer; color: var(--color-text-dark); transition: var(--transition-fast); border-bottom: 3px solid <?php echo $active_tab === 'register' ? 'var(--color-primary)' : 'transparent'; ?>; opacity: <?php echo $active_tab === 'register' ? '1' : '0.6'; ?>; font-size: 1rem;">
                    Nieuw account aanmaken
                </button>
            </div>

            <!-- Login Container -->
            <div id="auth-panel-login" style="padding: 30px; display: <?php echo $active_tab === 'login' ? 'block' : 'none'; ?>;">
                <div style="text-align: center; margin-bottom: 24px;">
                    <h3 style="font-size: 1.5rem; color: var(--color-primary-dark); margin-bottom: 6px;">Welkom terug!</h3>
                    <p style="font-size: 0.85rem; color: var(--color-text-muted);">Meld u aan met uw ouderaccount om door te gaan.</p>
                </div>
                
                <form action="ouderportaal.php" method="POST">
                    <input type="hidden" name="action_type" value="login">
                    
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label class="form-label" for="login_email">E-mailadres *</label>
                        <input type="email" id="login_email" name="email" class="form-control" placeholder="naam@voorbeeld.com" required>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label class="form-label" for="login_password" style="margin-bottom: 0;">Wachtwoord *</label>
                            <a href="forgot-password.php" style="font-size: 0.85rem; color: var(--color-primary-light); text-decoration: underline;">Wachtwoord vergeten?</a>
                        </div>
                        <input type="password" id="login_password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 12px;">
                        Inloggen &rarr;
                    </button>
                </form>
            </div>

            <!-- Register Container -->
            <div id="auth-panel-register" style="padding: 30px; display: <?php echo $active_tab === 'register' ? 'block' : 'none'; ?>;">
                <div style="text-align: center; margin-bottom: 24px;">
                    <h3 style="font-size: 1.5rem; color: var(--color-primary-dark); margin-bottom: 6px;">Ouderaccount Registratie</h3>
                    <p style="font-size: 0.85rem; color: var(--color-text-muted);">Maak een account aan om uw kinderen eenvoudig in te schrijven.</p>
                </div>
                
                <form action="ouderportaal.php" method="POST">
                    <input type="hidden" name="action_type" value="register">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
                        <div class="form-group">
                            <label class="form-label" for="reg_first_name">Voornaam *</label>
                            <input type="text" id="reg_first_name" name="first_name" class="form-control" placeholder="bv. Jan" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reg_last_name">Achternaam *</label>
                            <input type="text" id="reg_last_name" name="last_name" class="form-control" placeholder="bv. Janssens" required>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label class="form-label" for="reg_email">E-mailadres *</label>
                        <input type="email" id="reg_email" name="email" class="form-control" placeholder="naam@voorbeeld.com" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label class="form-label" for="reg_phone">Telefoonnummer (Optioneel)</label>
                        <input type="tel" id="reg_phone" name="phone" class="form-control" placeholder="bv. 0468123456">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 24px;">
                        <label class="form-label" for="reg_password">Kies een wachtwoord *</label>
                        <input type="password" id="reg_password" name="password" class="form-control" placeholder="Minimaal 6 tekens" minlength="6" required>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary" style="width: 100%; padding: 12px;">
                        Account Aanmaken &rarr;
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- LOGGED-IN VIEW: DASHBOARD -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; padding-bottom: 15px; border-bottom: 1px solid var(--color-border); flex-wrap: wrap; gap: 15px;">
            <div>
                <h3 style="font-size: 1.8rem; color: var(--color-primary-dark); font-weight: 700;">
                    Welkom, <?php echo htmlspecialchars($_SESSION['parent_name']); ?>!
                </h3>
                <span style="font-size: 0.9rem; color: var(--color-text-muted);">
                    Beheer uw kinderen, inschrijvingen en betalingen.
                </span>
            </div>
            <div>
                <a href="ouderportaal.php?action=logout" class="btn btn-outline" style="padding: 8px 20px;">
                    Afmelden
                </a>
            </div>
        </div>
        
        <style>
            .quick-action-btn-1, .quick-action-btn-2, .quick-action-btn-3 {
                transition: var(--transition-normal);
            }
            .quick-action-btn-1:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(78, 18, 28, 0.25) !important;
            }
            .quick-action-btn-2:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(254, 197, 34, 0.25) !important;
            }
            .quick-action-btn-3:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(16, 185, 129, 0.25) !important;
            }
        </style>

        <!-- Quick Actions Panel -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 40px;">
            <!-- Button 1: Kind toevoegen -->
            <button onclick="toggleAddChildForm(); document.getElementById('add-child-drawer').scrollIntoView({behavior: 'smooth'});" style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%); border: none; border-radius: var(--border-radius-lg); padding: 30px 24px; color: var(--color-bg-white); box-shadow: var(--shadow-md); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 16px; text-align: left; width: 100%;" class="quick-action-btn-1">
                <div style="background: rgba(255, 255, 255, 0.15); border-radius: 50%; padding: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg style="width: 32px; height: 32px; fill: white;" viewBox="0 0 24 24">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                </div>
                <div>
                    <h4 style="font-size: 1.35rem; font-family: 'Outfit', sans-serif; font-weight: 700; margin: 0; margin-bottom: 4px; color: white;">Kind Toevoegen</h4>
                    <p style="font-size: 0.85rem; color: hsla(0, 0%, 100%, 0.85); margin: 0; font-family: 'Inter', sans-serif; line-height: 1.3;">Registreer uw kind en ontdek direct de takindeling.</p>
                </div>
            </button>

            <!-- Button 2: Inschrijven voor kamp/weekend -->
            <button onclick="toggleEvenementen();" style="background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-secondary) 100%); border: none; border-radius: var(--border-radius-lg); padding: 30px 24px; color: var(--color-primary-dark); box-shadow: var(--shadow-md); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 16px; text-decoration: none; text-align: left; width: 100%;" class="quick-action-btn-2">
                <div style="background: rgba(0, 0, 0, 0.08); border-radius: 50%; padding: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg style="width: 32px; height: 32px; fill: var(--color-primary-dark);" viewBox="0 0 24 24">
                        <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm-2-7h-5v5h5v-5z"/>
                    </svg>
                </div>
                <div>
                    <h4 style="font-size: 1.35rem; font-family: 'Outfit', sans-serif; font-weight: 700; margin: 0; margin-bottom: 4px; color: var(--color-primary-dark);">Inschrijven voor Kamp / Weekend</h4>
                    <p style="font-size: 0.85rem; color: hsla(345, 30%, 15%, 0.8); margin: 0; font-family: 'Inter', sans-serif; line-height: 1.3;">Meld uw kinderen snel en eenvoudig aan voor activiteiten.</p>
                </div>
            </button>

            <!-- Button 3: Kledij Bestellen / Webshop -->
            <button onclick="toggleWebshop(); document.getElementById('webshop-drawer').scrollIntoView({behavior: 'smooth'});" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); border: none; border-radius: var(--border-radius-lg); padding: 30px 24px; color: var(--color-bg-white); box-shadow: var(--shadow-md); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 16px; text-align: left; width: 100%;" class="quick-action-btn-3">
                <div style="background: rgba(255, 255, 255, 0.15); border-radius: 50%; padding: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <svg style="width: 32px; height: 32px; fill: white;" viewBox="0 0 24 24">
                        <path d="M17.21 9l-4.38-6.56c-.18-.27-.51-.44-.83-.44-.32 0-.65.17-.83.44L6.79 9H2c-.55 0-1 .45-1 1 0 .09.01.18.04.27l2.54 9.27c.23.84 1 1.46 1.88 1.46h13.08c.88 0 1.65-.62 1.88-1.46l2.54-9.27.04-.27c0-.55-.45-1-1-1h-4.79zM9 9l3-4.5L15 9H9z"/>
                    </svg>
                </div>
                <div>
                    <h4 style="font-size: 1.35rem; font-family: 'Outfit', sans-serif; font-weight: 700; margin: 0; margin-bottom: 4px; color: white;">Scouts Kledij Bestellen</h4>
                    <p style="font-size: 0.85rem; color: hsla(0, 0%, 100%, 0.85); margin: 0; font-family: 'Inter', sans-serif; line-height: 1.3;">Koop truien, T-shirts, dassen of badges online.</p>
                </div>
            </button>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 40px;">
            
            <!-- SECTION 1: MY CHILDREN -->
            <div style="background: var(--color-bg-white); border-radius: var(--border-radius-lg); padding: 30px; box-shadow: var(--shadow-md); border: 1px solid var(--color-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 12px; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <svg style="width: 28px; height: 28px; fill: var(--color-primary-light);" viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        <h4 style="font-size: 1.4rem; color: var(--color-primary-dark); font-weight: 700; margin: 0;">Mijn Kinderen</h4>
                    </div>
                    <button onclick="toggleAddChildForm()" class="btn btn-primary" style="padding: 6px 16px; font-size: 0.9rem;">
                        + Kind toevoegen
                    </button>
                </div>

                <!-- Add Child Form Drawer -->
                <div id="add-child-drawer" style="display: none; background: var(--color-bg-linen); padding: 20px; border-radius: var(--border-radius-md); margin-bottom: 24px; border: 1px solid var(--color-border);">
                    <h5 style="font-size: 1.1rem; color: var(--color-primary-dark); margin-bottom: 15px; font-weight: 700;">Nieuw Kind Toevoegen</h5>
                    
                    <form action="ouderportaal.php" method="POST">
                        <input type="hidden" name="action_type" value="add_child">
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 18px;">
                            <div class="form-group">
                                <label class="form-label" for="child_first_name">Voornaam *</label>
                                <input type="text" id="child_first_name" name="child_first_name" class="form-control" placeholder="bv. Bobby" required style="background: var(--color-bg-white);">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="child_last_name">Achternaam *</label>
                                <input type="text" id="child_last_name" name="child_last_name" class="form-control" placeholder="bv. Janssens" required style="background: var(--color-bg-white);">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="child_dob">Geboortedatum *</label>
                                <input type="date" id="child_dob" name="child_dob" class="form-control" required style="background: var(--color-bg-white);">
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 12px; justify-content: flex-end;">
                            <button type="button" onclick="toggleAddChildForm()" class="btn btn-outline" style="padding: 8px 16px; font-size: 0.9rem;">
                                Annuleren
                            </button>
                            <button type="submit" class="btn btn-secondary" style="padding: 8px 24px; font-size: 0.9rem;">
                                Opslaan & Indelen &rarr;
                            </button>
                        </div>
                    </form>
                    <div style="margin-top: 15px; font-size: 0.75rem; color: var(--color-text-muted); line-height: 1.4; background: var(--color-bg-white); border-radius: var(--border-radius-sm); padding: 10px; border: 1px dashed var(--color-border);">
                        <strong>Automatische groepsindeling (Scoutsjaar 2026-2027):</strong><br>
                        • Kapoenen: Geboren 2018 - 2020 • Welpen: Geboren 2015 - 2017<br>
                        • Jonggivers: Geboren 2012 - 2014 • Givers: Geboren 2008 - 2011
                    </div>
                </div>

                <!-- Children Grid List -->
                <?php if (empty($my_children)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--color-text-muted); background: var(--color-bg-linen); border-radius: var(--border-radius-md); border: 2px dashed var(--color-border);">
                        <svg style="width: 48px; height: 48px; fill: currentColor; opacity: 0.4; margin-bottom: 12px;" viewBox="0 0 24 24">
                            <path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zm-9 2c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V20h-6v-2c0-1.93 2.69-3 4-3z"/>
                        </svg>
                        <p style="font-size: 1.05rem; font-weight: 600; margin-bottom: 4px;">U heeft nog geen kinderen toegevoegd.</p>
                        <p style="font-size: 0.85rem;">Klik hierboven op "+ Kind toevoegen" om uw kinderen te registreren en automatisch in te delen in een tak.</p>
                    </div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px;">
                        <?php foreach ($my_children as $child): 
                            $tak_color = "var(--color-primary)";
                            if ($child['tak'] === 'kapoenen') $tak_color = "var(--color-kapoenen)";
                            elseif ($child['tak'] === 'welpen') $tak_color = "var(--color-welpen)";
                            elseif ($child['tak'] === 'jonggivers') $tak_color = "var(--color-jonggivers)";
                            elseif ($child['tak'] === 'givers') $tak_color = "var(--color-givers)";
                        ?>
                            <div style="background: var(--color-bg-linen); border-radius: var(--border-radius-md); padding: 20px; border-left: 6px solid <?php echo $tak_color; ?>; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                                <div>
                                    <h5 style="font-size: 1.25rem; font-weight: 700; color: var(--color-primary-dark); margin-bottom: 4px;">
                                        <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                    </h5>
                                    
                                    <div style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 12px;">
                                        <svg style="width: 14px; height: 14px; fill: none; stroke: currentColor;" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>Geboortedatum: <?php echo date('d-m-Y', strtotime($child['dob'])); ?></span>
                                    </div>
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 8px; margin-top: auto;">
                                    <div style="align-self: flex-start; display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 50px; background: <?php echo $tak_color; ?>; color: <?php echo $child['tak'] === 'kapoenen' ? 'var(--color-text-dark)' : 'var(--color-bg-white)'; ?>; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor;"></span>
                                        <?php echo htmlspecialchars($child['tak']); ?>
                                    </div>

                                    <?php if (!empty($child['warning'])): ?>
                                        <div style="color: var(--color-error); font-size: 0.75rem; font-weight: 600; margin-top: 4px; line-height: 1.2; display: flex; align-items: flex-start; gap: 4px;">
                                            <span style="margin-top: 2px;">⚠️</span>
                                            <span><?php echo htmlspecialchars($child['warning']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SECTION 1B: PARTNER ACCESS -->
            <div style="background: var(--color-bg-white); border-radius: var(--border-radius-lg); padding: 30px; box-shadow: var(--shadow-md); border: 1px solid var(--color-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 12px; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <svg style="width: 28px; height: 28px; fill: var(--color-primary-light);" viewBox="0 0 24 24">
                            <path d="M16.5 12c1.38 0 2.49-1.1 2.49-2.5S17.88 7 16.5 7C15.12 7 14 8.1 14 9.5s1.12 2.5 2.5 2.5zM9 11c1.66 0 2.99-1.34 2.99-3S10.66 5 9 5C7.34 5 6 6.34 6 8s1.34 3 3 3zm7.5 3c-1.83 0-5.5.92-5.5 2.75V19h11v-2.25c0-1.83-3.67-2.75-5.5-2.75zM9 13c-2.33 0-7 1.17-7 3.5V19h7v-2.25c0-.85.33-2.34 0-3.75z"/>
                        </svg>
                        <h4 style="font-size: 1.4rem; color: var(--color-primary-dark); font-weight: 700; margin: 0;">Partner Toegang (Tweede Login)</h4>
                    </div>
                    <?php if (isset($_SESSION['parent_role']) && $_SESSION['parent_role'] === 'primary' && !isset($current_parent['secondary_email'])): ?>
                        <button onclick="toggleAddPartnerForm()" class="btn btn-primary" style="padding: 6px 16px; font-size: 0.9rem;">
                            + Partner toevoegen
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (isset($_SESSION['parent_role']) && $_SESSION['parent_role'] === 'secondary'): ?>
                    <!-- Partner is currently logged in, show restricted notice -->
                    <div style="background-color: var(--color-bg-linen); border: 1px solid var(--color-border); padding: 15px; border-radius: var(--border-radius-md); font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.5;">
                        ℹ️ U bent momenteel ingelogd via de <strong>partner-login</strong>. Het beheren van extra logins kan enkel worden uitgevoerd door de hoofdaccount login (<code><?php echo htmlspecialchars($current_parent['email']); ?></code>).
                    </div>
                <?php else: ?>
                    <!-- Primary parent is logged in, they can manage partners -->
                    <?php if (!isset($current_parent['secondary_email'])): ?>
                        
                        <!-- Add Partner Form Drawer -->
                        <div id="add-partner-drawer" style="display: none; background: var(--color-bg-linen); padding: 20px; border-radius: var(--border-radius-md); margin-bottom: 24px; border: 1px solid var(--color-border);">
                            <h5 style="font-size: 1.1rem; color: var(--color-primary-dark); margin-bottom: 15px; font-weight: 700;">Partner Toevoegen</h5>
                            <p style="font-size: 0.8rem; color: var(--color-text-muted); margin-bottom: 15px; line-height: 1.4;">
                                Vul de gegevens van uw partner in. Zij ontvangen hun eigen e-mail en wachtwoord waarmee ze kunnen inloggen op <strong>hetzelfde account</strong>. Zo beheert u samen dezelfde kinderen en inschrijvingen!
                            </p>
                            
                            <form action="ouderportaal.php" method="POST">
                                <input type="hidden" name="action_type" value="add_partner">
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 18px;">
                                    <div class="form-group">
                                        <label class="form-label" for="partner_first_name">Voornaam Partner *</label>
                                        <input type="text" id="partner_first_name" name="partner_first_name" class="form-control" placeholder="bv. mama/papa voornaam" required style="background: var(--color-bg-white);">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="partner_last_name">Achternaam Partner *</label>
                                        <input type="text" id="partner_last_name" name="partner_last_name" class="form-control" placeholder="bv. achternaam" required style="background: var(--color-bg-white);">
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 18px;">
                                    <div class="form-group">
                                        <label class="form-label" for="partner_email">E-mailadres Partner *</label>
                                        <input type="email" id="partner_email" name="partner_email" class="form-control" placeholder="partner@voorbeeld.com" required style="background: var(--color-bg-white);">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="partner_password">Kies Wachtwoord Partner *</label>
                                        <input type="password" id="partner_password" name="partner_password" class="form-control" placeholder="Minimaal 6 tekens" minlength="6" required style="background: var(--color-bg-white);">
                                    </div>
                                </div>

                                <div class="form-group" style="margin-bottom: 18px; max-width: 300px;">
                                    <label class="form-label" for="partner_phone">Telefoonnummer Partner</label>
                                    <input type="tel" id="partner_phone" name="partner_phone" class="form-control" placeholder="bv. 0468123456" style="background: var(--color-bg-white);">
                                </div>
                                
                                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                                    <button type="button" onclick="toggleAddPartnerForm()" class="btn btn-outline" style="padding: 8px 16px; font-size: 0.9rem;">
                                        Annuleren
                                    </button>
                                    <button type="submit" class="btn btn-secondary" style="padding: 8px 24px; font-size: 0.9rem;">
                                        Opslaan & Toegang Verlenen &rarr;
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div style="text-align: center; padding: 30px 20px; color: var(--color-text-muted); background: var(--color-bg-linen); border-radius: var(--border-radius-md); border: 2px dashed var(--color-border);">
                            <p style="font-size: 1rem; font-weight: 600; margin-bottom: 4px;">Geen partner-login gekoppeld.</p>
                            <p style="font-size: 0.85rem;">Wilt u uw partner (mama of papa) ook toegang geven? Klik hierboven op "+ Partner toevoegen" om een aparte inlog aan te maken.</p>
                        </div>

                    <?php else: ?>
                        <!-- Partner is added, show partner details card -->
                        <div style="background: var(--color-bg-linen); border-radius: var(--border-radius-md); padding: 20px; border-left: 6px solid var(--color-success); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                            <div>
                                <div style="display: inline-flex; align-items: center; gap: 6px; padding: 2px 8px; border-radius: 50px; background: var(--color-success); color: var(--color-bg-white); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-bottom: 8px;">
                                    ✓ Actief Partner Account
                                </div>
                                <h5 style="font-size: 1.2rem; font-weight: 700; color: var(--color-primary-dark); margin: 0 0 6px 0;">
                                    <?php echo htmlspecialchars($current_parent['secondary_first_name'] . ' ' . $current_parent['secondary_last_name']); ?>
                                </h5>
                                <div style="font-size: 0.85rem; color: var(--color-text-dark); margin-bottom: 4px;">
                                    <strong>E-mailadres:</strong> <code><?php echo htmlspecialchars($current_parent['secondary_email']); ?></code>
                                </div>
                                <?php if (!empty($current_parent['secondary_phone'])): ?>
                                    <div style="font-size: 0.85rem; color: var(--color-text-dark);">
                                        <strong>Telefoon:</strong> <?php echo htmlspecialchars($current_parent['secondary_phone']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="ouderportaal.php?action=remove_partner" data-confirm="Weet u zeker dat u de partner-login wilt verwijderen? Uw partner zal niet langer kunnen inloggen op dit account." class="btn btn-outline" style="border-color: var(--color-error); color: var(--color-error); padding: 8px 16px; font-size: 0.85rem; text-decoration: none; font-weight: bold;">
                                    Partner verwijderen
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- SECTION 2: MY ENROLLMENTS -->
            <div style="background: var(--color-bg-white); border-radius: var(--border-radius-lg); padding: 30px; box-shadow: var(--shadow-md); border: 1px solid var(--color-border);">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 24px; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 12px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <svg style="width: 28px; height: 28px; fill: var(--color-primary-light);" viewBox="0 0 24 24">
                            <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                        </svg>
                        <h4 style="font-size: 1.4rem; color: var(--color-primary-dark); font-weight: 700; margin: 0;">Mijn Inschrijvingen</h4>
                    </div>
                    <?php if (!empty($my_registrations)): ?>
                        <button onclick="toggleEvenementen();" class="btn btn-primary" style="font-size: 0.85rem; padding: 6px 14px; border: none; cursor: pointer;">
                            🏕 Inschrijven voor kamp/weekend
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($my_registrations)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--color-text-muted); background: var(--color-bg-linen); border-radius: var(--border-radius-md); border: 2px dashed var(--color-border);">
                        <p style="font-size: 1.05rem; font-weight: 600; margin-bottom: 4px;">Er zijn nog geen inschrijvingen voor dit scoutsjaar gevonden.</p>
                        <p style="font-size: 0.85rem; margin-bottom: 15px;">Schrijf uw kinderen in voor een weekend of kamp via ons evenementenportaal.</p>
                        <button onclick="toggleEvenementen();" class="btn btn-primary" style="font-size: 0.9rem; padding: 8px 24px; border: none; cursor: pointer;">
                            🏕 Inschrijven voor kamp/weekend &rarr;
                        </button>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto; margin-bottom: 30px;">
                        <table class="table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                            <thead>
                                <tr style="background: var(--color-bg-linen); border-bottom: 2px solid var(--color-border);">
                                    <th style="padding: 12px; font-weight: 700; color: var(--color-primary-dark);">Deelnemer</th>
                                    <th style="padding: 12px; font-weight: 700; color: var(--color-primary-dark);">Tak</th>
                                    <th style="padding: 12px; font-weight: 700; color: var(--color-primary-dark);">Activiteit</th>
                                    <th style="padding: 12px; font-weight: 700; color: var(--color-primary-dark);">Kosten</th>
                                    <th style="padding: 12px; font-weight: 700; color: var(--color-primary-dark);">Status Betaling</th>
                                    <th style="padding: 12px; font-weight: 700; color: var(--color-primary-dark);">Opmerking</th>
                                    <th style="padding: 12px; font-weight: 700; color: var(--color-primary-dark); text-align: right;">Actie</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_registrations as $reg): 
                                    $tak_color = "var(--color-primary)";
                                    if ($reg['child_tak'] === 'kapoenen') $tak_color = "var(--color-kapoenen)";
                                    elseif ($reg['child_tak'] === 'welpen') $tak_color = "var(--color-welpen)";
                                    elseif ($reg['child_tak'] === 'jonggivers') $tak_color = "var(--color-jonggivers)";
                                    elseif ($reg['child_tak'] === 'givers') $tak_color = "var(--color-givers)";
                                ?>
                                    <tr style="border-bottom: 1px solid var(--color-border); transition: var(--transition-fast);">
                                        <td style="padding: 15px 12px; font-weight: 600; color: var(--color-text-dark);">
                                            <?php echo htmlspecialchars($reg['child_name']); ?>
                                        </td>
                                        <td style="padding: 15px 12px;">
                                            <span style="font-size: 0.75rem; font-weight: 700; color: <?php echo $reg['child_tak'] === 'kapoenen' ? 'var(--color-text-dark)' : 'var(--color-bg-white)'; ?>; background: <?php echo $tak_color; ?>; padding: 2px 8px; border-radius: 4px; text-transform: uppercase;">
                                                <?php echo htmlspecialchars($reg['child_tak']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 15px 12px; color: var(--color-text-dark);">
                                            <?php echo htmlspecialchars($reg['activity_title']); ?>
                                        </td>
                                        <td style="padding: 15px 12px; font-weight: 700; color: var(--color-text-dark);">
                                            €<?php echo number_format($reg['price'], 2, ',', '.'); ?>
                                        </td>
                                        <td style="padding: 15px 12px;">
                                            <?php if (isset($reg['cancellation_requested']) && $reg['cancellation_requested'] === true): ?>
                                                <span style="background-color: hsla(4, 75%, 48%, 0.1); border: 1px solid var(--color-error); color: var(--color-error); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                    ⚠️ Annulering aangevraagd
                                                </span>
                                            <?php elseif ($reg['status'] === 'approved' || $reg['status'] === 'paid'): ?>
                                                <span style="background-color: hsla(145, 63%, 35%, 0.1); border: 1px solid var(--color-success); color: var(--color-success); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                    ✓ Goedgekeurd & Betaald
                                                </span>
                                            <?php elseif ($reg['status'] === 'waiting_approval'): ?>
                                                <span style="background-color: hsla(38, 92%, 50%, 0.1); border: 1px solid var(--color-warning); color: var(--color-warning); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                    ⏱ Wachten op bevestiging van de leiding
                                                </span>
                                            <?php else: ?>
                                                <span style="background-color: hsla(4, 75%, 48%, 0.1); border: 1px solid var(--color-error); color: var(--color-error); padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                    ⏱ Te betalen
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 15px 12px; font-size: 0.8rem; color: var(--color-text-muted); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo isset($reg['remarks']) ? htmlspecialchars($reg['remarks']) : ''; ?>">
                                            <?php echo isset($reg['remarks']) && !empty($reg['remarks']) ? htmlspecialchars($reg['remarks']) : '-'; ?>
                                        </td>
                                        <td style="padding: 15px 12px; text-align: right;">
                                            <?php if (isset($reg['cancellation_requested']) && $reg['cancellation_requested'] === true): ?>
                                                <span style="font-size: 0.8rem; color: var(--color-text-muted); font-style: italic;">
                                                    Aanvraag ingediend
                                                </span>
                                            <?php else: ?>
                                                <button onclick="confirmCancellation('<?php echo $reg['id']; ?>')" class="btn btn-outline" style="border-color: var(--color-error); color: var(--color-error); padding: 4px 10px; font-size: 0.8rem; border-radius: 6px;">
                                                    Annuleren
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- If payment is pending, show payment instructions details row -->
                                    <?php if ($reg['status'] === 'pending' && (!isset($reg['cancellation_requested']) || $reg['cancellation_requested'] !== true)): ?>
                                        <tr style="background: hsla(38, 92%, 50%, 0.02); border-bottom: 1px solid var(--color-border);">
                                            <td colspan="7" style="padding: 16px 20px;">
                                                <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: center; justify-content: space-between; background-color: hsla(38, 92%, 50%, 0.03); border: 1px solid hsla(38, 92%, 50%, 0.18); padding: 20px 24px; border-radius: var(--border-radius-md); width: 100%; box-sizing: border-box;">
                                                    <div style="flex: 1; min-width: 280px;">
                                                        <h5 style="color: var(--color-primary-dark); font-size: 0.95rem; margin-top: 0; margin-bottom: 8px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                                                            <span style="font-size: 1.15rem;">💳</span> Betalingsinstructies Lidgeld
                                                        </h5>
                                                        <p style="margin: 0 0 12px 0; font-size: 0.85rem; color: var(--color-text-dark); line-height: 1.45;">
                                                            Gelieve het lidgeld van <strong style="color: var(--color-primary); font-size: 0.9rem;">€<?php echo number_format($reg['price'], 2, ',', '.'); ?></strong> handmatig over te schrijven om de inschrijving te voltooien:
                                                        </p>
                                                        <div style="display: grid; grid-template-columns: auto 1fr; gap: 6px 12px; font-size: 0.82rem; color: var(--color-text-dark); background-color: var(--color-bg-white); padding: 12px 16px; border-radius: var(--border-radius-sm); border: 1px solid rgba(0,0,0,0.04); box-shadow: inset 0 1px 3px rgba(0,0,0,0.02); width: 100%; box-sizing: border-box;">
                                                            <strong>Rekeningnummer:</strong> <code><?php echo htmlspecialchars(isset($settings['bank_iban']) ? $settings['bank_iban'] : 'BE76 1234 5678 9012'); ?></code>
                                                            <strong>Begunstigde:</strong> <span><?php echo htmlspecialchars(isset($settings['bank_holder']) ? $settings['bank_holder'] : 'Scouts Kriko-M vzw'); ?></span>
                                                            <strong>Gestructureerde mededeling:</strong> <code style="font-weight: 700; color: var(--color-primary);"><?php echo htmlspecialchars($reg['communication']); ?></code>
                                                        </div>
                                                    </div>
                                                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px; min-width: 220px; justify-content: center; height: 100%;">
                                                        <a href="ouderportaal.php?action=confirm_payment&amp;reg_id=<?php echo $reg['id']; ?>" class="btn" style="padding: 10px 20px; font-size: 0.9rem; background-color: var(--color-success); color: var(--color-bg-white); border-radius: var(--border-radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); transition: all 0.2s ease; border: none; cursor: pointer;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 14px rgba(16, 185, 129, 0.3)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 10px rgba(16, 185, 129, 0.2)';">
                                                            <span style="font-size: 1.1rem; line-height: 1;">✓</span> Ik heb overgeschreven
                                                        </a>
                                                        <span style="font-style: italic; font-size: 0.75rem; color: var(--color-text-muted); text-align: right;">
                                                            Meld uw betaling om de goedkeuring te versnellen.
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SECTION 3: MY WEBSHOP ORDERS -->
            <div style="background: var(--color-bg-white); border-radius: var(--border-radius-lg); padding: 30px; box-shadow: var(--shadow-md); border: 1px solid var(--color-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 12px; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <svg style="width: 28px; height: 28px; fill: var(--color-primary-light);" viewBox="0 0 24 24">
                            <path d="M17.21 9l-4.38-6.56c-.18-.27-.51-.44-.83-.44-.32 0-.65.17-.83.44L6.79 9H2c-.55 0-1 .45-1 1 0 .09.01.18.04.27l2.54 9.27c.23.84 1 1.46 1.88 1.46h13.08c.88 0 1.65-.62 1.88-1.46l2.54-9.27.04-.27c0-.55-.45-1-1-1h-4.79zM9 9l3-4.5L15 9H9zm3 8c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>
                        </svg>
                        <h4 style="font-size: 1.4rem; color: var(--color-primary-dark); font-weight: 700; margin: 0;">Mijn Webshop Bestellingen</h4>
                    </div>
                    <button onclick="toggleWebshop();" class="btn btn-secondary" style="padding: 6px 16px; font-size: 0.9rem; background-color: var(--color-success); border-color: var(--color-success); color: white;">
                        + Scouts Kledij Bestellen
                    </button>
                </div>

                <?php if (empty($my_orders)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--color-text-muted); background: var(--color-bg-linen); border-radius: var(--border-radius-md); border: 2px dashed var(--color-border);">
                        <p style="font-size: 1rem; font-weight: 600; margin-bottom: 4px;">Geen bestellingen gevonden.</p>
                        <p style="font-size: 0.85rem; margin-bottom: 15px;">Heeft u scouts T-shirts, truien of dassen nodig? U kunt deze eenvoudig bestellen via onze webshop.</p>
                        <button onclick="toggleWebshop()" class="btn btn-secondary" style="font-size: 0.85rem; padding: 8px 18px; border: none; cursor: pointer; display: inline-block;">Scouts shop openen &raquo;</button>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--color-border); text-align: left;">
                                    <th style="padding: 12px; font-size: 0.85rem; font-weight: 700; color: var(--color-text-dark); text-transform: uppercase;">Datum</th>
                                    <th style="padding: 12px; font-size: 0.85rem; font-weight: 700; color: var(--color-text-dark); text-transform: uppercase;">Bestellingsnr</th>
                                    <th style="padding: 12px; font-size: 0.85rem; font-weight: 700; color: var(--color-text-dark); text-transform: uppercase;">Artikelen</th>
                                    <th style="padding: 12px; font-size: 0.85rem; font-weight: 700; color: var(--color-text-dark); text-transform: uppercase;">Voor Lid</th>
                                    <th style="padding: 12px; font-size: 0.85rem; font-weight: 700; color: var(--color-text-dark); text-transform: uppercase;">Totaal</th>
                                    <th style="padding: 12px; font-size: 0.85rem; font-weight: 700; color: var(--color-text-dark); text-transform: uppercase;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_orders as $ord): ?>
                                    <tr style="border-bottom: 1px solid var(--color-border); background-color: <?php echo $ord['status'] === 'pending' ? 'hsla(38, 92%, 50%, 0.01)' : 'transparent'; ?>;">
                                        <td style="padding: 15px 12px; font-size: 0.85rem; color: var(--color-text-dark); font-weight: 600;">
                                            <?php echo date('d-m-Y H:i', strtotime($ord['date'])); ?>
                                        </td>
                                        <td style="padding: 15px 12px; font-family: monospace; font-size: 0.85rem; color: var(--color-primary);">
                                            <?php echo htmlspecialchars($ord['id']); ?>
                                        </td>
                                        <td style="padding: 15px 12px; font-size: 0.85rem;">
                                            <ul style="margin: 0; padding-left: 16px; color: var(--color-text-dark); line-height: 1.4;">
                                                <?php foreach ($ord['items'] as $item): ?>
                                                    <li>
                                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong> 
                                                        (Maat: <?php echo htmlspecialchars($item['size']); ?>) 
                                                        - <?php echo (int)$item['quantity']; ?>x
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </td>
                                        <td style="padding: 15px 12px; font-size: 0.85rem; color: var(--color-text-dark);">
                                            <strong><?php echo htmlspecialchars($ord['child_name']); ?></strong><br>
                                            <span style="font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase;"><?php echo htmlspecialchars($ord['child_tak']); ?></span>
                                        </td>
                                        <td style="padding: 15px 12px; font-size: 0.95rem; font-weight: 700; color: var(--color-text-dark);">
                                            €<?php echo number_format($ord['total'], 2, ',', '.'); ?>
                                        </td>
                                        <td style="padding: 15px 12px;">
                                            <?php if ($ord['status'] === 'paid'): ?>
                                                <span style="background-color: hsla(145, 63%, 35%, 0.1); border: 1px solid var(--color-success); color: var(--color-success); padding: 4px 8px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                    ✓ Betaald
                                                </span>
                                            <?php elseif ($ord['status'] === 'completed'): ?>
                                                <span style="background-color: hsla(207, 90%, 54%, 0.1); border: 1px solid #1d4ed8; color: #1d4ed8; padding: 4px 8px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                    📦 Geleverd
                                                </span>
                                            <?php elseif ($ord['status'] === 'waiting_approval'): ?>
                                                <span style="background-color: hsla(38, 92%, 50%, 0.1); border: 1px solid var(--color-warning); color: var(--color-warning); padding: 4px 8px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                    ⏱ Wachten op bevestiging van de leiding
                                                </span>
                                            <?php else: ?>
                                                <span style="background-color: hsla(4, 75%, 48%, 0.1); border: 1px solid var(--color-error); color: var(--color-error); padding: 4px 8px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                    ⏱ Te betalen
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- Payment Instructions if Pending -->
                                    <?php if ($ord['status'] === 'pending'): ?>
                                        <tr style="background: hsla(38, 92%, 50%, 0.02); border-bottom: 1px solid var(--color-border);">
                                            <td colspan="6" style="padding: 16px 20px;">
                                                <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: center; justify-content: space-between; background-color: hsla(38, 92%, 50%, 0.03); border: 1px solid hsla(38, 92%, 50%, 0.18); padding: 20px 24px; border-radius: var(--border-radius-md); width: 100%; box-sizing: border-box;">
                                                    <div style="flex: 1; min-width: 280px;">
                                                        <h5 style="color: var(--color-primary-dark); font-size: 0.95rem; margin-top: 0; margin-bottom: 8px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                                                            <span style="font-size: 1.15rem;">💳</span> Webshop Betaalinstructies
                                                        </h5>
                                                        <p style="margin: 0 0 12px 0; font-size: 0.85rem; color: var(--color-text-dark); line-height: 1.45;">
                                                            Gelieve het totaalbedrag van <strong style="color: var(--color-primary); font-size: 0.9rem;">€<?php echo number_format($ord['total'], 2, ',', '.'); ?></strong> handmatig over te schrijven om uw bestelling af te ronden:
                                                        </p>
                                                        <div style="display: grid; grid-template-columns: auto 1fr; gap: 6px 12px; font-size: 0.82rem; color: var(--color-text-dark); background-color: var(--color-bg-white); padding: 12px 16px; border-radius: var(--border-radius-sm); border: 1px solid rgba(0,0,0,0.04); box-shadow: inset 0 1px 3px rgba(0,0,0,0.02); width: 100%; box-sizing: border-box;">
                                                            <strong>Rekeningnummer:</strong> <code><?php echo htmlspecialchars(isset($settings['bank_iban']) ? $settings['bank_iban'] : 'BE76 1234 5678 9012'); ?></code>
                                                            <strong>Begunstigde:</strong> <span><?php echo htmlspecialchars(isset($settings['bank_holder']) ? $settings['bank_holder'] : 'Scouts Kriko-M vzw'); ?></span>
                                                            <strong>Gestructureerde mededeling:</strong> <code style="font-weight: 700; color: var(--color-primary);"><?php echo htmlspecialchars($ord['communication']); ?></code>
                                                        </div>
                                                    </div>
                                                    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px; min-width: 220px; justify-content: center; height: 100%;">
                                                        <a href="ouderportaal.php?action=confirm_order_payment&amp;order_id=<?php echo $ord['id']; ?>" class="btn" style="padding: 10px 20px; font-size: 0.9rem; background-color: var(--color-success); color: var(--color-bg-white); border-radius: var(--border-radius-md); text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); transition: all 0.2s ease; border: none; cursor: pointer;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 14px rgba(16, 185, 129, 0.3)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 4px 10px rgba(16, 185, 129, 0.2)';">
                                                            <span style="font-size: 1.1rem; line-height: 1;">✓</span> Ik heb overgeschreven
                                                        </a>
                                                        <span style="font-style: italic; font-size: 0.75rem; color: var(--color-text-muted); text-align: right;">
                                                            Meld uw betaling om de levering te versnellen.
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SECTION 4: EMBEDDED WEBSHOP CATALOG -->
            <div id="webshop-drawer" style="display: none; background: var(--color-bg-white); border-radius: var(--border-radius-lg); padding: 30px; box-shadow: var(--shadow-md); border: 1px solid var(--color-border); margin-top: 30px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 12px; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <svg style="width: 28px; height: 28px; fill: #10B981;" viewBox="0 0 24 24">
                            <path d="M17.21 9l-4.38-6.56c-.18-.27-.51-.44-.83-.44-.32 0-.65.17-.83.44L6.79 9H2c-.55 0-1 .45-1 1 0 .09.01.18.04.27l2.54 9.27c.23.84 1 1.46 1.88 1.46h13.08c.88 0 1.65-.62 1.88-1.46l2.54-9.27.04-.27c0-.55-.45-1-1-1h-4.79zM9 9l3-4.5L15 9H9z"/>
                        </svg>
                        <h4 style="font-size: 1.4rem; color: var(--color-primary-dark); font-weight: 700; margin: 0;">Onze Scouts Shop</h4>
                    </div>
                    <button onclick="toggleWebshop()" class="btn btn-outline" style="padding: 6px 16px; font-size: 0.9rem; border-color: var(--color-error); color: var(--color-error) !important;">
                        Sluiten
                    </button>
                </div>

                <!-- Info Announcement Bar -->
                <div style="background-color: hsla(42, 85%, 55%, 0.05); border: 2px dashed var(--color-accent); border-radius: var(--border-radius-md); padding: 20px; margin-bottom: 30px; display: flex; gap: 16px; align-items: flex-start;">
                    <svg style="width: 24px; height: 24px; color: var(--color-secondary); flex-shrink: 0; margin-top: 2px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h5 style="color: var(--color-primary-dark); font-size: 1.05rem; margin: 0 0 4px 0; font-weight: 700;">Hoe werkt bestellen bij ons?</h5>
                        <p style="font-size: 0.9rem; color: var(--color-text-dark); margin: 0; line-height: 1.45;">
                            Voeg kledingstukken toe aan uw winkelwagen (gebruik de zwevende groene winkelmand-knop rechtsonder om uw selectie te openen) en voltooi de afrekening. Betalingen gebeuren via handmatige overschrijving. Zodra we de betaling ontvangen, ligt de bestelling de <strong>eerstvolgende zondag</strong> klaar aan de lokalen!
                        </p>
                    </div>
                </div>

                <!-- Categories Loop -->
                <?php foreach ($shop_categories as $cat_key => $cat_name): 
                    $cat_items = array_filter($active_shop_items, function($item) use ($cat_key) {
                        return $item['category'] === $cat_key;
                    });
                    
                    if (empty($cat_items)) continue;
                ?>
                    <div style="margin-bottom: 40px;">
                        <h5 style="font-size: 1.25rem; border-bottom: 1px solid var(--color-border); padding-bottom: 6px; margin-bottom: 20px; color: var(--color-primary-dark); font-weight: 700;"><?php echo $cat_name; ?></h5>
                        
                        <div class="shop-grid">
                            <?php foreach ($cat_items as $item): ?>
                                <div class="shop-card">
                                    <div class="shop-card-image">
                                        <?php if (!empty($item['image']) && file_exists(__DIR__ . '/' . $item['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                            <div style="display: flex; height: 100%; width: 100%; align-items: center; justify-content: center; background-color: var(--color-primary-light); color: var(--color-bg-white); height: 240px; position: relative;">
                                                <svg style="width: 50px; height: 50px; fill: currentColor; opacity: 0.35;" viewBox="0 0 24 24">
                                                    <path d="M12 2c1.1 0 2 .9 2 2v1h-4V4c0-1.1.9-2 2-2zm6 3h-2v1h-8V5H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm-6 13c-2.76 0-5-2.24-5-5h2c0 1.66 1.34 3 3 3s3-1.34 3-3h2c0 2.76-2.24 5-5 5z"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        <span class="shop-badge" style="background-color: var(--color-secondary);"><?php echo htmlspecialchars($item['category']); ?></span>
                                    </div>

                                    <div class="shop-card-body" style="padding: 20px; display: flex; flex-direction: column; flex-grow: 1;">
                                        <h3 class="shop-card-title" style="font-size: 1.15rem; margin-top: 0; margin-bottom: 6px; color: var(--color-primary-dark); font-weight: 700;"><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <div class="shop-card-price" style="font-size: 1.25rem; font-weight: 800; color: var(--color-secondary); margin-bottom: 12px;">€<?php echo number_format($item['price'], 2, ',', ''); ?></div>
                                        <p class="shop-card-desc" style="font-size: 0.85rem; color: var(--color-text-muted); line-height: 1.4; margin-bottom: 16px; flex-grow: 1;"><?php echo htmlspecialchars($item['description']); ?></p>
                                        
                                        <!-- Size Select -->
                                        <?php if (!empty($item['sizes']) && count($item['sizes']) > 0): 
                                            $select_id = 'size-select-' . htmlspecialchars($item['id']);
                                        ?>
                                            <label class="form-label" for="<?php echo $select_id; ?>" style="margin-bottom: 4px; font-size: 0.8rem; font-weight: 600;">Maat:</label>
                                            <select id="<?php echo $select_id; ?>" name="size[<?php echo htmlspecialchars($item['id']); ?>]" class="shop-size-select" style="margin-bottom: 12px;">
                                                <?php foreach ($item['sizes'] as $size): ?>
                                                    <option value="<?php echo htmlspecialchars($size); ?>"><?php echo htmlspecialchars($size); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                        
                                        <!-- Trigger Button -->
                                        <button class="btn btn-secondary btn-add-to-cart" style="width: 100%; margin-top: auto; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: none; cursor: pointer;" 
                                                data-id="<?php echo htmlspecialchars($item['id']); ?>"
                                                data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                data-price="<?php echo htmlspecialchars($item['price']); ?>"
                                                data-image="<?php echo htmlspecialchars($item['image']); ?>">
                                            <svg style="width: 16px; height: 16px; fill: none; stroke: currentColor;" stroke-width="2.5" viewBox="0 0 24 24">
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
            </div>

            <!-- SECTION 5: EMBEDDED EVENTS & CAMP REGISTRATIONS -->
            <div id="evenementen-drawer" style="display: <?php echo (isset($_GET['show_evenementen']) || isset($event_success_data) || !empty($error)) ? 'block' : 'none'; ?>; background: var(--color-bg-white); border-radius: var(--border-radius-lg); padding: 30px; box-shadow: var(--shadow-md); border: 1px solid var(--color-border); margin-top: 30px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 12px; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <svg style="width: 28px; height: 28px; fill: var(--color-accent);" viewBox="0 0 24 24">
                            <path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm-2-7h-5v5h5v-5z"/>
                        </svg>
                        <h4 style="font-size: 1.4rem; color: var(--color-primary-dark); font-weight: 700; margin: 0;">Weekend & Kamp Inschrijvingen</h4>
                    </div>
                    <button onclick="toggleEvenementen()" class="btn btn-outline" style="padding: 6px 16px; font-size: 0.9rem; border-color: var(--color-error); color: var(--color-error) !important; cursor: pointer;">
                        Sluiten
                    </button>
                </div>

                <?php if (isset($event_success_data) && !empty($event_success_data)): ?>
                    <!-- CELEBRATORY SUCCESS VIEW INLINE -->
                    <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); border: 1px solid var(--color-border); overflow: hidden; margin-bottom: 20px;">
                        <div style="background-color: var(--color-primary-dark); color: var(--color-bg-white); padding: 35px 30px; text-align: center; border-bottom: 4px solid var(--color-secondary);">
                            <div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; background-color: var(--color-secondary); border-radius: 50%; margin-bottom: 15px; color: var(--color-primary-dark);">
                                <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <h3 style="font-size: 1.5rem; margin: 0; color: var(--color-accent);">Hartelijk bedankt voor uw inschrijving!</h3>
                            <p style="font-size: 0.9rem; color: hsla(0, 0%, 100%, 0.85); margin-top: 6px; margin-bottom: 0;">We hebben de aanmelding voor <strong><?php echo htmlspecialchars($event_success_data['child_name']); ?></strong> succesvol ontvangen.</p>
                        </div>
                        
                        <div style="padding: 25px;">
                            <p style="font-size: 0.9rem; line-height: 1.5; color: var(--color-text-dark); margin-bottom: 20px; text-align: center;">
                                Gelieve het verschuldigde bedrag handmatig over te schrijven naar onze bankrekening met <strong>exact</strong> de onderstaande gestructureerde mededeling:
                            </p>
                            
                            <!-- BILLING DETAILS -->
                            <div style="background-color: var(--color-bg-linen); border: 2px dashed var(--color-border); border-radius: var(--border-radius-md); padding: 20px; margin-bottom: 25px;">
                                <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.85rem;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">
                                        <span style="font-weight: 600; color: var(--color-text-muted);">Te betalen bedrag:</span>
                                        <strong style="font-size: 1.3rem; color: var(--color-secondary);">€<?php echo number_format($event_success_data['price'], 2, ',', '.'); ?></strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">
                                        <span style="font-weight: 600; color: var(--color-text-muted);">IBAN Rekeningnummer:</span>
                                        <span style="font-family: monospace; font-size: 0.95rem; font-weight: bold; color: var(--color-primary-dark);"><?php echo htmlspecialchars($settings['bank_iban']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--color-border); padding-bottom: 8px;">
                                        <span style="font-weight: 600; color: var(--color-text-muted);">Naam Begunstigde:</span>
                                        <span style="font-weight: 600; color: var(--color-primary-dark);"><?php echo htmlspecialchars($settings['bank_holder']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background-color: var(--color-primary-dark); color: var(--color-bg-white); padding: 10px 15px; border-radius: var(--border-radius-sm); margin-top: 5px;">
                                        <span style="font-weight: 600; color: var(--color-accent);">Gestructureerde Mededeling:</span>
                                        <strong style="font-family: monospace; font-size: 1.1rem; letter-spacing: 0.5px; color: var(--color-bg-white);"><?php echo htmlspecialchars($event_success_data['communication']); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div style="background-color: hsla(145, 63%, 35%, 0.06); border: 1px solid var(--color-success); padding: 15px; border-radius: var(--border-radius-md); margin-bottom: 25px; text-align: center; font-size: 0.85rem;">
                                <p style="margin-top: 0; margin-bottom: 8px; color: var(--color-success); font-weight: bold;">Heeft u de betaling al uitgevoerd?</p>
                                <a href="ouderportaal.php?action=confirm_payment&amp;reg_id=<?php echo $event_success_data['id']; ?>" class="btn" style="background-color: var(--color-success); color: var(--color-bg-white); padding: 8px 20px; font-size: 0.85rem; font-weight: bold; text-decoration: none; display: inline-block; border-radius: var(--border-radius-md); border: none; cursor: pointer;">
                                    Ik heb overgeschreven
                                </a>
                            </div>
                            
                            <div style="text-align: center;">
                                <button type="button" onclick="resetEventsDrawer()" class="btn btn-outline" style="padding: 8px 20px; font-size: 0.85rem; cursor: pointer;">Nieuwe inschrijving starten</button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- main event portal content -->
                    <div id="events-main-portal">
                        <!-- Division Grid Selector -->
                        <div id="events-branch-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px;">
                            <?php 
                            $takken_settings = isset($settings['takken']) ? $settings['takken'] : [];
                            foreach ($takken_settings as $key => $tak): 
                            ?>
                                <div onclick="selectEventTak('<?php echo $key; ?>')" class="tak-card" style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); border: 1px solid var(--color-border); overflow: hidden; display: flex; flex-direction: column; text-align: center; cursor: pointer; padding: 0; width: 100%; transition: var(--transition-normal);">
                                    <div class="tak-card-header <?php echo $tak['class']; ?>" style="height: 10px; width: 100%;"></div>
                                    <div style="padding: 20px; flex-grow: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; width: 100%; box-sizing: border-box;">
                                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase;"><?php echo $tak['age_range']; ?></span>
                                        <h3 style="font-size: 1.25rem; margin-top: 4px; margin-bottom: 8px; color: var(--color-primary-dark); font-weight: 700;"><?php echo $tak['name']; ?></h3>
                                        <span class="btn btn-secondary" style="margin-top: auto; padding: 6px 12px; font-size: 0.8rem; width: 100%;">Bekijken &raquo;</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Division Specific Panels (hidden by default) -->
                        <?php foreach ($takken_settings as $key => $tak): 
                            $activities = isset($tak['activities']) ? $tak['activities'] : [];
                            $active_activities = array_filter($activities, function($a) { return $a['active']; });
                        ?>
                            <div id="events-tak-panel-<?php echo $key; ?>" class="events-tak-panel" style="display: none; margin-top: 15px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                                    <h4 style="font-size: 1.25rem; color: var(--color-primary-dark); font-weight: 700; margin: 0; display: flex; align-items: center; gap: 8px;">
                                        <span class="status-badge" style="background-color: var(--color-primary-light); color: var(--color-bg-white); font-size: 0.85rem; padding: 3px 10px; border-radius: 6px;"><?php echo $tak['name']; ?></span>
                                        Geplande Activiteiten
                                    </h4>
                                    <button onclick="showEventsBranchGrid()" class="btn btn-outline" style="padding: 5px 12px; font-size: 0.8rem; border-radius: 20px; font-weight: 600; cursor: pointer; border: 1px solid var(--color-border); background: var(--color-bg-white);">
                                        &larr; Andere tak kiezen
                                    </button>
                                </div>

                                <?php if (empty($active_activities)): ?>
                                    <div style="background-color: var(--color-bg-linen); border-radius: var(--border-radius-md); border: 1px solid var(--color-border); padding: 30px; text-align: center;">
                                        <p style="color: var(--color-text-muted); font-size: 0.9rem; margin: 0;">Er zijn momenteel geen actieve weekends of kampen voor de <?php echo $tak['name']; ?>. Vraag ernaar bij de leiding!</p>
                                    </div>
                                <?php else: ?>
                                    <div style="display: flex; flex-direction: column; gap: 20px;">
                                        <?php foreach ($active_activities as $type_key => $act): 
                                            $today = date('Y-m-d');
                                            $state = 'open';
                                            $state_label = 'Open voor inschrijving';
                                            $badge_class = 'status-badge paid';
                                            
                                            if ($today < $act['reg_open']) {
                                                $state = 'upcoming';
                                                $state_label = 'Open vanaf ' . date('d-m-Y', strtotime($act['reg_open']));
                                                $badge_class = 'status-badge pending';
                                            } elseif ($today > $act['reg_close']) {
                                                $state = 'closed';
                                                $state_label = 'Gesloten';
                                                $badge_class = 'status-badge completed';
                                            }
                                        ?>
                                            <div style="background-color: var(--color-bg-linen); border-radius: var(--border-radius-md); border: 1px solid var(--color-border); padding: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; transition: var(--transition-normal);" class="tak-card">
                                                <div style="text-align: left;">
                                                    <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 6px;">
                                                        <span class="<?php echo $badge_class; ?>" style="font-size: 0.7rem; padding: 1px 6px; font-weight: 700;"><?php echo $state_label; ?></span>
                                                        <span style="font-size: 0.75rem; color: var(--color-text-muted);">Deadline: <?php echo date('d-m-Y', strtotime($act['reg_close'])); ?></span>
                                                    </div>
                                                    <h5 style="font-size: 1.15rem; color: var(--color-primary-dark); margin: 0 0 6px 0; font-weight: 700; text-align: left;"><?php echo htmlspecialchars($act['title']); ?></h5>
                                                    <p style="color: var(--color-text-dark); font-size: 0.85rem; display: flex; align-items: center; gap: 4px; margin: 0;">
                                                        <svg style="width: 14px; height: 14px; color: var(--color-secondary);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                        <strong>Datums:</strong> <?php echo htmlspecialchars($act['dates']); ?>
                                                    </p>
                                                </div>
                                                <div style="text-align: right; min-width: 120px; display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                                    <span style="font-size: 0.75rem; color: var(--color-text-muted);">Deelnameprijs:</span>
                                                    <strong style="font-size: 1.3rem; color: var(--color-secondary); margin-bottom: 4px;">€<?php echo number_format($act['price'], 2, ',', '.'); ?></strong>
                                                    
                                                    <?php if ($state === 'open'): ?>
                                                        <button onclick="openPortalRegisterForm('<?php echo $key; ?>', '<?php echo $tak['name']; ?>', '<?php echo $type_key; ?>', '<?php echo htmlspecialchars($act['title']); ?>', <?php echo $act['price']; ?>)" class="btn btn-secondary" style="padding: 6px 14px; font-size: 0.8rem; cursor: pointer; border: none;">
                                                            Nu Inschrijven
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline" style="padding: 6px 14px; font-size: 0.8rem;" disabled>
                                                            Niet Beschikbaar
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Portal Enrollment Form Overlay (Hidden by default) -->
            <div id="portal-registration-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: flex-start; padding: 20px; backdrop-filter: blur(4px); overflow-y: auto;">
                <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-lg); width: 100%; max-width: 550px; overflow: hidden; border: 1px solid var(--color-border); position: relative; animation: modalReveal 0.3s ease; margin: 40px auto;">
                    
                    <!-- Modal Header -->
                    <div style="background-color: var(--color-primary-dark); color: var(--color-bg-white); padding: 20px 24px; position: relative;">
                        <h3 style="font-size: 1.3rem; margin: 0; color: var(--color-accent);" id="portal-modal-event-title">Evenement Inschrijven</h3>
                        <span style="font-size: 0.8rem; color: hsla(0,0%,100%,0.8); display: block; margin-top: 4px;" id="portal-modal-event-tak-name">Inschrijving voor Tak: ...</span>
                        <button onclick="closePortalRegisterForm()" style="position: absolute; top: 20px; right: 24px; background: none; border: none; color: var(--color-bg-white); font-size: 1.8rem; line-height: 0.5; cursor: pointer; opacity: 0.7; font-weight: 100;">&times;</button>
                    </div>

                    <!-- Modal Body Form -->
                    <form action="ouderportaal.php" method="POST" style="padding: 24px;">
                        <input type="hidden" name="action_type" value="register_event">
                        <input type="hidden" name="child_tak" id="portal-modal-child-tak">
                        <input type="hidden" name="activity_type" id="portal-modal-activity-type">

                        <div style="background-color: var(--color-bg-linen); border: 1px solid var(--color-border); border-radius: var(--border-radius-md); padding: 12px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.9rem; font-weight: 600; color: var(--color-text-dark);">Totaal te betalen:</span>
                            <strong style="font-size: 1.25rem; color: var(--color-secondary);" id="portal-modal-event-price">€0,00</strong>
                        </div>

                        <!-- Dropdown warning container if no child matches this branch -->
                        <div id="portal-modal-no-children-warning" style="display: none; background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 15px; border-radius: var(--border-radius-md); font-size: 0.85rem; line-height: 1.4; margin-bottom: 20px; text-align: left;">
                            <strong>Geen kind gevonden in deze tak:</strong> U heeft in uw ouderaccount momenteel geen kinderen toegevoegd die ingedeeld zijn bij de geselecteerde tak.<br><br>
                            <button type="button" onclick="closePortalRegisterForm(); toggleAddChildForm();" class="btn" style="font-size: 0.8rem; padding: 8px 14px; background-color: var(--color-secondary); color: white; border: none; cursor: pointer; border-radius: var(--border-radius-sm);">+ Kind toevoegen in Ouderportaal &rarr;</button>
                        </div>

                        <!-- Child selector dropdown container -->
                        <div id="portal-modal-child-selector-container">
                            <div class="form-group" style="margin-bottom: 16px; text-align: left;">
                                <label class="form-label" for="portal-modal-child-id">Kies uw kind (Lid):</label>
                                <select id="portal-modal-child-id" name="child_id" class="form-control" required style="text-align-last: center;">
                                    <?php foreach ($my_children as $child): ?>
                                        <option value="<?php echo htmlspecialchars($child['id']); ?>" data-tak="<?php echo htmlspecialchars($child['tak']); ?>">
                                            <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?> (geb. <?php echo date('d-m-Y', strtotime($child['dob'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Parent name -->
                            <div class="form-group" style="margin-bottom: 16px; text-align: left;">
                                <label class="form-label" for="portal-modal-parent-name">Volledige Naam Ouder / Voogd:</label>
                                <input type="text" id="portal-modal-parent-name" name="parent_name" class="form-control" placeholder="Voornaam + Achternaam" value="<?php echo htmlspecialchars($current_parent['first_name'] . ' ' . $current_parent['last_name']); ?>" required>
                            </div>

                            <div class="form-row" style="margin-bottom: 16px;">
                                <!-- Email -->
                                <div class="form-group" style="text-align: left;">
                                    <label class="form-label" for="portal-modal-email">E-mailadres:</label>
                                    <input type="email" id="portal-modal-email" name="email" class="form-control" placeholder="ouder@domein.be" value="<?php echo htmlspecialchars($current_parent['email']); ?>" required>
                                </div>
                                
                                <!-- Phone -->
                                <div class="form-group" style="text-align: left;">
                                    <label class="form-label" for="portal-modal-phone">Telefoonnummer:</label>
                                    <input type="tel" id="portal-modal-phone" name="phone" class="form-control" placeholder="0470 00 00 00" value="<?php echo htmlspecialchars($current_parent['phone']); ?>" required>
                                </div>
                            </div>

                            <!-- Special Remarks -->
                            <div class="form-group" style="margin-bottom: 20px; text-align: left;">
                                <label class="form-label" for="portal-modal-remarks">Speciale opmerkingen (bv. kind sluit een dag later aan, dieetwensen, allergieën):</label>
                                <textarea id="portal-modal-remarks" name="remarks" class="form-control" rows="3" placeholder="Typ hier eventuele opmerkingen of opmerkingen voor de leiding..."></textarea>
                            </div>

                            <div style="background-color: var(--color-bg-linen); padding: 12px; border-radius: var(--border-radius-sm); border: 1px dashed var(--color-border); margin-bottom: 20px; font-size: 0.75rem; color: var(--color-text-muted); line-height: 1.4; text-align: left;">
                                * Na het indienen ontvangt u de unieke betalingsgegevens en Belgische gestructureerde mededeling. Gelieve hiermee de betaling handmatig uit te voeren via uw bankapp.
                            </div>

                            <div style="display: flex; gap: 12px; margin-top: 10px;">
                                <button type="button" onclick="closePortalRegisterForm()" class="btn btn-outline" style="width: 40%; cursor: pointer;">Annuleren</button>
                                <button type="submit" class="btn btn-secondary" style="width: 60%; cursor: pointer;">Inschrijven & Betalen</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- FLOATING CART FAB (automatically toggled by cart.js based on item count) -->
            <button class="cart-trigger-btn" aria-label="Winkelwagen bekijken" style="display: none; position: fixed; bottom: 24px; right: 24px; z-index: 9999; width: 56px; height: 56px; border-radius: 50%; background-color: #10B981; color: white; border: 2px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.3); align-items: center; justify-content: center; cursor: pointer; transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'">
                <svg style="width: 24px; height: 24px; fill: none; stroke: currentColor;" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                <span class="cart-count" style="display: none; position: absolute; top: -4px; right: -4px; background-color: var(--color-error); color: white; border-radius: 50%; width: 22px; height: 22px; font-size: 0.75rem; font-weight: 700; align-items: center; justify-content: center; border: 1.5px solid white;">0</span>
            </button>

        </div>
    <?php endif; ?>

</section>

<!-- Inline Javascript for tab toggles, cancellation confirm, and modern SPA Fetch transitions -->
<script>
function toggleAuthTab(tab) {
    const loginPanel = document.getElementById('auth-panel-login');
    const registerPanel = document.getElementById('auth-panel-register');
    const loginBtn = document.getElementById('tab-btn-login');
    const registerBtn = document.getElementById('tab-btn-register');
    
    if (tab === 'login') {
        if (loginPanel) loginPanel.style.display = 'block';
        if (registerPanel) registerPanel.style.display = 'none';
        if (loginBtn) {
            loginBtn.style.borderBottomColor = 'var(--color-primary)';
            loginBtn.style.opacity = '1';
        }
        if (registerBtn) {
            registerBtn.style.borderBottomColor = 'transparent';
            registerBtn.style.opacity = '0.6';
        }
    } else {
        if (loginPanel) loginPanel.style.display = 'none';
        if (registerPanel) registerPanel.style.display = 'block';
        if (loginBtn) {
            loginBtn.style.borderBottomColor = 'transparent';
            loginBtn.style.opacity = '0.6';
        }
        if (registerBtn) {
            registerBtn.style.borderBottomColor = 'var(--color-primary)';
            registerBtn.style.opacity = '1';
        }
    }
}

function toggleWebshop() {
    const drawer = document.getElementById('webshop-drawer');
    if (drawer) {
        if (drawer.style.display === 'none' || drawer.style.display === '') {
            drawer.style.display = 'block';
            drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            drawer.style.display = 'none';
        }
    }
}

function toggleEvenementen() {
    const drawer = document.getElementById('evenementen-drawer');
    if (drawer) {
        if (drawer.style.display === 'none' || drawer.style.display === '') {
            drawer.style.display = 'block';
            drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            drawer.style.display = 'none';
        }
    }
}

function selectEventTak(takKey) {
    // Hide branch grid selector
    const grid = document.getElementById('events-branch-grid');
    if (grid) grid.style.display = 'none';
    
    // Hide all tak panels
    const panels = document.querySelectorAll('.events-tak-panel');
    panels.forEach(p => p.style.display = 'none');
    
    // Show selected tak panel
    const selectedPanel = document.getElementById('events-tak-panel-' + takKey);
    if (selectedPanel) selectedPanel.style.display = 'block';
}

function showEventsBranchGrid() {
    // Hide all tak panels
    const panels = document.querySelectorAll('.events-tak-panel');
    panels.forEach(p => p.style.display = 'none');
    
    // Show branch grid selector
    const grid = document.getElementById('events-branch-grid');
    if (grid) grid.style.display = 'grid';
}

function openPortalRegisterForm(takKey, takName, typeKey, title, price) {
    const overlay = document.getElementById('portal-registration-overlay');
    const inputChildTak = document.getElementById('portal-modal-child-tak');
    const inputActivityType = document.getElementById('portal-modal-activity-type');
    const textTitle = document.getElementById('portal-modal-event-title');
    const textTakName = document.getElementById('portal-modal-event-tak-name');
    const textPrice = document.getElementById('portal-modal-event-price');

    if (overlay && inputChildTak && inputActivityType && textTitle && textTakName && textPrice) {
        inputChildTak.value = takKey;
        inputActivityType.value = typeKey;
        textTitle.textContent = "Inschrijven voor " + title;
        textTakName.textContent = "Inschrijving voor Tak: " + takName;
        textPrice.textContent = "€" + price.toFixed(2).replace('.', ',');
        
        // Filter children options to only show children matching this takKey
        const selectBox = document.getElementById('portal-modal-child-id');
        const warningBox = document.getElementById('portal-modal-no-children-warning');
        const formContainer = document.getElementById('portal-modal-child-selector-container');
        
        if (selectBox && warningBox && formContainer) {
            let visibleCount = 0;
            let firstVisibleVal = '';
            
            for (let i = 0; i < selectBox.options.length; i++) {
                const opt = selectBox.options[i];
                if (opt.getAttribute('data-tak') === takKey) {
                    opt.style.display = 'block';
                    opt.disabled = false;
                    if (visibleCount === 0) {
                        firstVisibleVal = opt.value;
                    }
                    visibleCount++;
                } else {
                    opt.style.display = 'none';
                    opt.disabled = true;
                }
            }
            
            if (visibleCount > 0) {
                selectBox.value = firstVisibleVal;
                warningBox.style.display = 'none';
                formContainer.style.display = 'block';
            } else {
                warningBox.style.display = 'block';
                formContainer.style.display = 'none';
            }
        }
        
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // block background scroll
    }
}

function closePortalRegisterForm() {
    const overlay = document.getElementById('portal-registration-overlay');
    if (overlay) {
        overlay.style.display = 'none';
        document.body.style.overflow = 'auto'; // restore background scroll
    }
}

function resetEventsDrawer() {
    executeAction('ouderportaal.php?action=reset_events_state');
}

// Auto-reveal webshop or events drawer if parameter is present in URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('show_webshop') && urlParams.get('show_webshop') === '1') {
        setTimeout(() => {
            const drawer = document.getElementById('webshop-drawer');
            if (drawer) {
                drawer.style.display = 'block';
                drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 300);
    }
    if (urlParams.has('show_evenementen') && urlParams.get('show_evenementen') === '1') {
        setTimeout(() => {
            const drawer = document.getElementById('evenementen-drawer');
            if (drawer) {
                drawer.style.display = 'block';
                drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }, 300);
    }
});

function toggleAddChildForm() {
    const drawer = document.getElementById('add-child-drawer');
    if (drawer) {
        if (drawer.style.display === 'none' || drawer.style.display === '') {
            drawer.style.display = 'block';
            drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            drawer.style.display = 'none';
        }
    }
}

function toggleAddPartnerForm() {
    const drawer = document.getElementById('add-partner-drawer');
    if (drawer) {
        if (drawer.style.display === 'none' || drawer.style.display === '') {
            drawer.style.display = 'block';
            drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            drawer.style.display = 'none';
        }
    }
}

window.showConfirmModal = function(message, onConfirm) {
    let modal = document.getElementById('scouts-confirm-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'scouts-confirm-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 11000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        `;
        
        modal.innerHTML = `
            <div style="
                background: var(--color-bg-white);
                border: 1px solid var(--color-border);
                border-radius: var(--border-radius-lg);
                box-shadow: var(--shadow-lg);
                padding: 30px;
                max-width: 450px;
                width: 90%;
                text-align: center;
                transform: translateY(20px) scale(0.95);
                transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                font-family: 'Outfit', sans-serif;
            ">
                <div style="
                    width: 60px;
                    height: 60px;
                    background-color: hsla(4, 75%, 48%, 0.1);
                    color: var(--color-error);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 20px;
                ">
                    <svg style="width: 32px; height: 32px; fill: none; stroke: currentColor; stroke-width: 2.5;" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h4 style="margin: 0 0 10px; color: var(--color-primary-dark); font-size: 1.35rem; font-weight: 700; font-family: 'Outfit', sans-serif;">Bent u zeker?</h4>
                <p id="scouts-confirm-message" style="color: var(--color-text-muted); font-size: 0.95rem; line-height: 1.5; margin: 0 0 25px; font-family: 'Plus Jakarta Sans', sans-serif;"></p>
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button id="scouts-confirm-cancel" class="btn btn-outline" style="padding: 10px 20px; font-size: 0.9rem; min-width: 110px; cursor: pointer; border-radius: 30px; font-weight: 600; font-family: 'Outfit', sans-serif;">Annuleren</button>
                    <button id="scouts-confirm-ok" class="btn btn-secondary" style="padding: 10px 20px; font-size: 0.9rem; min-width: 110px; background-color: var(--color-error); border-color: var(--color-error); color: #ffffff !important; cursor: pointer; border-radius: 30px; font-weight: 600; font-family: 'Outfit', sans-serif;">Bevestigen</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    const messageEl = modal.querySelector('#scouts-confirm-message');
    messageEl.textContent = message;

    const cancelBtn = modal.querySelector('#scouts-confirm-cancel');
    const okBtn = modal.querySelector('#scouts-confirm-ok');
    const innerContent = modal.querySelector('div');

    const hideModal = () => {
        modal.style.opacity = '0';
        modal.style.pointerEvents = 'none';
        innerContent.style.transform = 'translateY(20px) scale(0.95)';
    };

    const showModal = () => {
        modal.style.opacity = '1';
        modal.style.pointerEvents = 'auto';
        innerContent.style.transform = 'translateY(0) scale(1)';
    };

    // Clean listeners
    const newCancelBtn = cancelBtn.cloneNode(true);
    const newOkBtn = okBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    okBtn.parentNode.replaceChild(newOkBtn, okBtn);

    newCancelBtn.addEventListener('click', hideModal);
    newOkBtn.addEventListener('click', () => {
        hideModal();
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    });

    setTimeout(showModal, 10);
};

function confirmCancellation(regId) {
    showConfirmModal("Weet u zeker dat u een annuleringsaanvraag wilt indienen voor deze activiteit? De leiding zal hiervan op de hoogte worden gebracht.", function() {
        executeAction("ouderportaal.php?action=request_cancellation&reg_id=" + regId);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // 1. Inject Glassmorphic Toast Styles
    const styleEl = document.createElement('style');
    styleEl.textContent = `
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }
        .toast {
            pointer-events: auto;
            min-width: 320px;
            max-width: 480px;
            padding: 16px 20px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.02);
            color: var(--color-text-dark);
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            gap: 14px;
            transform: translateY(-20px) scale(0.9);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .toast.show {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        .toast.success {
            border-left: 5px solid var(--color-success);
        }
        .toast.error {
            border-left: 5px solid var(--color-error);
        }
        .toast-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .toast-icon.success {
            background-color: hsla(145, 63%, 35%, 0.1);
            color: var(--color-success);
        }
        .toast-icon.error {
            background-color: hsla(4, 75%, 48%, 0.1);
            color: var(--color-error);
        }
        .toast-message {
            font-size: 0.95rem;
            font-weight: 600;
            flex-grow: 1;
            line-height: 1.4;
        }
        .toast-close {
            background: none;
            border: none;
            color: var(--color-text-muted);
            cursor: pointer;
            font-size: 1.25rem;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.6;
            transition: opacity 0.2s;
            margin-left: 8px;
        }
        .toast-close:hover {
            opacity: 1;
        }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(4px);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            border-radius: var(--border-radius-lg);
        }
        .loading-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--color-border);
            border-top: 4px solid var(--color-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(styleEl);

    // 2. Create Toast Container
    const toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container';
    document.body.appendChild(toastContainer);

    // 3. Show Toast Function
    window.showToast = function(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const iconSVG = type === 'success' 
            ? `<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>`
            : `<svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>`;
            
        toast.innerHTML = `
            <div class="toast-icon ${type}">${iconSVG}</div>
            <div class="toast-message">${message}</div>
            <button class="toast-close">&times;</button>
        `;
        
        toastContainer.appendChild(toast);
        
        // Trigger reflow & show
        setTimeout(() => toast.classList.add('show'), 10);
        
        const closeBtn = toast.querySelector('.toast-close');
        const dismissToast = () => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        };
        
        closeBtn.addEventListener('click', dismissToast);
        
        // Auto-dismiss after 3.5s
        setTimeout(dismissToast, 3500);
    };

    // 4. Loading Overlay
    function toggleLoading(show) {
        let overlay = document.querySelector('.loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner"></div>';
            const wrapper = document.querySelector('#ouderportaal-wrapper');
            if (wrapper) {
                wrapper.style.position = 'relative';
                wrapper.appendChild(overlay);
            }
        }
        if (show) {
            overlay.classList.add('active');
        } else {
            overlay.classList.remove('active');
        }
    }

    // 5. Parse and Extract Alerts from HTML
    function checkAndShowAlerts(doc) {
        // Find success alert
        const successDiv = doc.querySelector('.system-success-banner');
        if (successDiv) {
            const msg = successDiv.querySelector('span')?.textContent || successDiv.textContent.trim();
            showToast(msg, 'success');
            successDiv.remove();
        }
        
        // Find error alert
        const errorDiv = doc.querySelector('.system-error-banner');
        if (errorDiv) {
            const msg = errorDiv.querySelector('span')?.textContent || errorDiv.textContent.trim();
            showToast(msg, 'error');
            errorDiv.remove();
        }
    }

    // 6. Execute Action (GET)
    window.executeAction = function(urlStr) {
        toggleLoading(true);
        const url = new URL(urlStr, window.location.origin);
        
        fetch(url.toString())
            .then(response => {
                const finalUrl = new URL(response.url);
                return response.text().then(text => ({ text, finalUrl }));
            })
            .then(({ text, finalUrl }) => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(text, 'text/html');
                const newWrapper = doc.querySelector('#ouderportaal-wrapper');
                const currentWrapper = document.querySelector('#ouderportaal-wrapper');
                
                if (newWrapper && currentWrapper) {
                    checkAndShowAlerts(doc);
                    currentWrapper.innerHTML = newWrapper.innerHTML;
                    
                    // Unlock overflow
                    document.body.style.overflow = 'auto';
                    
                    // Scroll to appropriate drawer based on redirected URL
                    const urlParams = finalUrl.searchParams;
                    if (urlParams.get('show_evenementen') === '1' || finalUrl.toString().includes('show_evenementen=1')) {
                        setTimeout(() => {
                            const drawer = document.getElementById('evenementen-drawer');
                            if (drawer) {
                                drawer.style.display = 'block';
                                drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }
                        }, 100);
                    } else if (urlParams.get('show_webshop') === '1' || finalUrl.toString().includes('show_webshop=1')) {
                        setTimeout(() => {
                            const drawer = document.getElementById('webshop-drawer');
                            if (drawer) {
                                drawer.style.display = 'block';
                                drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            }
                        }, 100);
                    }
                }
            })
            .catch(err => {
                console.error('Action failed:', err);
                showToast('Actie mislukt. Probeer het opnieuw.', 'error');
            })
            .finally(() => {
                toggleLoading(false);
            });
    };

    // 7. Event Delegation - Clicks
    document.addEventListener('click', function(e) {
        const actionLink = e.target.closest('a');
        if (actionLink && !e.defaultPrevented) {
            const href = actionLink.getAttribute('href');
            if (href && href.includes('ouderportaal.php') && href.includes('action=')) {
                const url = new URL(href, window.location.origin);
                const action = url.searchParams.get('action');
                
                // Exclude logout from AJAX
                if (action && action !== 'logout') {
                    e.preventDefault();
                    const confirmMsg = actionLink.getAttribute('data-confirm');
                    if (confirmMsg) {
                        showConfirmModal(confirmMsg, function() {
                            executeAction(href);
                        });
                    } else {
                        executeAction(href);
                    }
                }
            }
        }
    });

    // 8. Event Delegation - Form Submissions
    document.addEventListener('submit', function(e) {
        const form = e.target.closest('form');
        if (form) {
            const actionAttr = form.getAttribute('action') || '';
            if (actionAttr.includes('ouderportaal.php') || actionAttr === '') {
                e.preventDefault();
                
                toggleLoading(true);
                
                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
                
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'Verwerken...';
                }
                
                const actionUrl = new URL(actionAttr || window.location.href, window.location.origin);
                
                fetch(actionUrl.toString(), {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    const finalUrl = new URL(response.url);
                    return response.text().then(text => ({ text, finalUrl }));
                })
                .then(({ text, finalUrl }) => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'text/html');
                    const newWrapper = doc.querySelector('#ouderportaal-wrapper');
                    const currentWrapper = document.querySelector('#ouderportaal-wrapper');
                    
                    if (newWrapper && currentWrapper) {
                        checkAndShowAlerts(doc);
                        currentWrapper.innerHTML = newWrapper.innerHTML;
                        
                        // Unlock overflow
                        document.body.style.overflow = 'auto';
                        
                        // Scroll to appropriate drawer based on redirected URL or submitted action
                        const urlParams = finalUrl.searchParams;
                        const isEventReg = formData.get('action_type') === 'register_event';
                        if (isEventReg || urlParams.get('show_evenementen') === '1' || finalUrl.toString().includes('show_evenementen=1')) {
                            setTimeout(() => {
                                const drawer = document.getElementById('evenementen-drawer');
                                if (drawer) {
                                    drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                }
                            }, 100);
                        } else if (urlParams.get('show_webshop') === '1' || finalUrl.toString().includes('show_webshop=1')) {
                            setTimeout(() => {
                                const drawer = document.getElementById('webshop-drawer');
                                if (drawer) {
                                    drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                }
                            }, 100);
                        }
                    }
                })
                .catch(err => {
                    console.error('Form submission failed:', err);
                    showToast('Formulier verzenden mislukt.', 'error');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                })
                .finally(() => {
                    toggleLoading(false);
                });
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
