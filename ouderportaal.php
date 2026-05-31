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

// Fetch current parent, registrations, and shop orders
$current_parent = get_logged_in_parent();
$my_children = ($current_parent && isset($current_parent['children'])) ? $current_parent['children'] : [];
$my_registrations = [];
$my_orders = [];

if ($current_parent) {
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
        <h2 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 8px;">Ouderportaal</h2>
        <p style="font-size: 1.1rem; max-width: 600px; margin: 0 auto; color: var(--color-bg-linen); opacity: 0.9;">
            Meld u aan, beheer uw kinderen en bekijk betalingen of inschrijvingen voor weekends en kampen.
        </p>
    </div>
</section>

<section class="section container" style="padding-top: 40px; padding-bottom: 80px; min-height: 60vh;">
    
    <!-- System Notification Banner -->
    <?php if (!empty($success)): ?>
        <div style="background-color: hsla(145, 63%, 35%, 0.1); border: 2px solid var(--color-success); color: var(--color-success); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 30px; font-weight: 600; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px;">
            <svg style="width: 24px; height: 24px; fill: none; stroke: currentColor;" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div style="background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 30px; font-weight: 600; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px;">
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
                <a href="ouderportaal.php?action=logout" class="btn btn-outline" style="color: var(--color-primary); border-color: var(--color-primary); padding: 8px 20px;">
                    Afmelden
                </a>
            </div>
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
                                <a href="ouderportaal.php?action=remove_partner" onclick="return confirm('Weet u zeker dat u de partner-login wilt verwijderen? Uw partner zal niet langer kunnen inloggen op dit account.')" class="btn btn-outline" style="border-color: var(--color-error); color: var(--color-error); padding: 8px 16px; font-size: 0.85rem; text-decoration: none; font-weight: bold;">
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
                        <a href="evenementen.php" class="btn btn-primary" style="font-size: 0.85rem; padding: 6px 14px; text-decoration: none;">
                            🏕 Inschrijven voor kamp/weekend
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($my_registrations)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--color-text-muted); background: var(--color-bg-linen); border-radius: var(--border-radius-md); border: 2px dashed var(--color-border);">
                        <p style="font-size: 1.05rem; font-weight: 600; margin-bottom: 4px;">Er zijn nog geen inschrijvingen voor dit scoutsjaar gevonden.</p>
                        <p style="font-size: 0.85rem; margin-bottom: 15px;">Schrijf uw kinderen in voor een weekend of kamp via ons evenementenportaal.</p>
                        <a href="evenementen.php" class="btn btn-primary" style="font-size: 0.9rem; padding: 8px 24px; text-decoration: none;">
                            🏕 Inschrijven voor kamp/weekend &rarr;
                        </a>
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
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 12px;">
                    <svg style="width: 28px; height: 28px; fill: var(--color-primary-light);" viewBox="0 0 24 24">
                        <path d="M17.21 9l-4.38-6.56c-.18-.27-.51-.44-.83-.44-.32 0-.65.17-.83.44L6.79 9H2c-.55 0-1 .45-1 1 0 .09.01.18.04.27l2.54 9.27c.23.84 1 1.46 1.88 1.46h13.08c.88 0 1.65-.62 1.88-1.46l2.54-9.27.04-.27c0-.55-.45-1-1-1h-4.79zM9 9l3-4.5L15 9H9zm3 8c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/>
                    </svg>
                    <h4 style="font-size: 1.4rem; color: var(--color-primary-dark); font-weight: 700; margin: 0;">Mijn Webshop Bestellingen</h4>
                </div>

                <?php if (empty($my_orders)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--color-text-muted); background: var(--color-bg-linen); border-radius: var(--border-radius-md); border: 2px dashed var(--color-border);">
                        <p style="font-size: 1rem; font-weight: 600; margin-bottom: 4px;">Geen bestellingen gevonden.</p>
                        <p style="font-size: 0.85rem; margin-bottom: 15px;">Heeft u scouts T-shirts, truien of dassen nodig? U kunt deze eenvoudig bestellen via onze webshop.</p>
                        <a href="shop.php" class="btn btn-secondary" style="font-size: 0.85rem; padding: 8px 18px; text-decoration: none; display: inline-block;">Webshop bezoeken &raquo;</a>
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

        </div>
    <?php endif; ?>

</section>

<!-- Inline Javascript for tab toggles and cancellation confirm -->
<script>
function toggleAuthTab(tab) {
    const loginPanel = document.getElementById('auth-panel-login');
    const registerPanel = document.getElementById('auth-panel-register');
    const loginBtn = document.getElementById('tab-btn-login');
    const registerBtn = document.getElementById('tab-btn-register');
    
    if (tab === 'login') {
        loginPanel.style.display = 'block';
        registerPanel.style.display = 'none';
        loginBtn.style.borderBottomColor = 'var(--color-primary)';
        loginBtn.style.opacity = '1';
        registerBtn.style.borderBottomColor = 'transparent';
        registerBtn.style.opacity = '0.6';
    } else {
        loginPanel.style.display = 'none';
        registerPanel.style.display = 'block';
        loginBtn.style.borderBottomColor = 'transparent';
        loginBtn.style.opacity = '0.6';
        registerBtn.style.borderBottomColor = 'var(--color-primary)';
        registerBtn.style.opacity = '1';
    }
}

function toggleAddChildForm() {
    const drawer = document.getElementById('add-child-drawer');
    if (drawer.style.display === 'none' || drawer.style.display === '') {
        drawer.style.display = 'block';
        drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        drawer.style.display = 'none';
    }
}

function toggleAddPartnerForm() {
    const drawer = document.getElementById('add-partner-drawer');
    if (drawer.style.display === 'none' || drawer.style.display === '') {
        drawer.style.display = 'block';
        drawer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        drawer.style.display = 'none';
    }
}

function confirmCancellation(regId) {
    if (confirm("Weet u zeker dat u een annuleringsaanvraag wilt indienen voor deze activiteit? De leiding zal hiervan op de hoogte worden gebracht.")) {
        window.location.href = "ouderportaal.php?action=request_cancellation&reg_id=" + regId;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
