<?php
/**
 * Administrator Control Dashboard - Admin View
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Secure the route
check_admin_auth();

$success_alert = '';
$error_alert = '';

$role = $_SESSION['admin_role'];
$is_super = is_super_admin();

// Determine active tab based on role permissions
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : '';
if ($is_super) {
    if (!in_array($active_tab, ['orders', 'echos', 'tak_settings', 'messages', 'settings', 'registrations', 'calendar'])) {
        $active_tab = 'orders';
    }
} else {
    if (!in_array($active_tab, ['echos', 'tak_settings', 'settings', 'registrations'])) {
        $active_tab = 'echos';
    }
}

/**
 * Enforces the quota limit of maximum 2 approved Kriko Echo's per tak.
 * If there are more than 2, the oldest approved echo is physically unlinked from disk
 * and removed from the echos.json database.
 */
function enforce_echo_limit($tak) {
    $echos = read_db('echos');
    
    // Extract approved echos for this tak
    $approved_indices = [];
    foreach ($echos as $index => $echo) {
        if ($echo['tak'] === $tak && isset($echo['approved']) && $echo['approved'] === true) {
            $approved_indices[$index] = $echo;
        }
    }
    
    // If we have more than 2 approved echos for this tak
    if (count($approved_indices) > 2) {
        // Sort approved echos by month/year descending so the newest ones are first
        uasort($approved_indices, function($a, $b) {
            $valA = ((int)$a['year'] * 100) + (int)$a['month'];
            $valB = ((int)$b['year'] * 100) + (int)$b['month'];
            if ($valA === $valB) {
                return strtotime($b['uploaded_at']) - strtotime($a['uploaded_at']);
            }
            return $valB - $valA;
        });
        
        // We keep the first 2. The rest must be deleted.
        $kept_count = 0;
        $to_delete_indices = [];
        foreach ($approved_indices as $index => $echo) {
            $kept_count++;
            if ($kept_count > 2) {
                $to_delete_indices[] = $index;
            }
        }
        
        // Now physically delete the files and remove from the list
        foreach ($to_delete_indices as $index) {
            $file_name = $echos[$index]['file_name'];
            $file_path = UPLOADS_DIR . 'echos/' . $file_name;
            if (!empty($file_name) && file_exists($file_path)) {
                @unlink($file_path);
            }
            unset($echos[$index]);
        }
        
        // Write the cleaned echos array back to database
        write_db('echos', array_values($echos));
    }
}

/* ==========================================================================
   1. POST / GET Request Operations
   ========================================================================== */

// 1.1 Action triggers (GET)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $item_id = isset($_GET['id']) ? $_GET['id'] : '';
    
    // CSV Export Action (Super Admin / Tak Owner Check)
    if ($action === 'download_csv' && isset($_GET['event'])) {
        $event_title = trim($_GET['event']);
        $regs = read_db('registrations');
        
        // Filter by role (security check)
        if (!$is_super) {
            $regs = array_filter($regs, function($r) use ($role) {
                return $r['child_tak'] === $role;
            });
        }
        
        // Filter by event title
        $event_regs = array_filter($regs, function($r) use ($event_title) {
            return trim($r['activity_title']) === $event_title;
        });
        
        // Prevent any error/deprecation messages from polluting the CSV output
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Clear any existing output buffer to prevent issues
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Output CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="registraties-' . str_replace(' ', '-', strtolower($event_title)) . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers: Naam, Speciale Voorkeuren, Betaalstatus, Tak, Ouder, E-mail, Telefoon
        fputcsv($output, ['Naam Kind', 'Tak', 'Speciale Voorkeuren / Opmerkingen', 'Betaalstatus', 'Ouder Naam', 'E-mail', 'Telefoon'], ';', '"', '\\');
        
        foreach ($event_regs as $r) {
            $status_label = 'Niet betaald';
            if ($r['status'] === 'paid') {
                $status_label = 'Betaald';
            } elseif ($r['status'] === 'waiting_approval') {
                $status_label = 'Wacht op goedkeuring';
            }
            
            $remarks = isset($r['remarks']) ? $r['remarks'] : '';
            
            fputcsv($output, [
                $r['child_name'],
                ucfirst($r['child_tak']),
                $remarks,
                $status_label,
                $r['customer_name'],
                $r['email'],
                $r['phone']
            ], ';', '"', '\\');
        }
        
        fclose($output);
        exit;
    }
    
    // A. Super Admin Order status updates
    if (in_array($action, ['update_order', 'delete_order']) && !$is_super) {
        header("HTTP/1.1 403 Forbidden");
        exit("Toegang geweigerd. U heeft geen rechten om bestellingen te beheren.");
    }
    
    if ($action === 'update_order' && !empty($item_id)) {
        $new_status = isset($_GET['status']) ? $_GET['status'] : '';
        if (in_array($new_status, ['pending', 'waiting_approval', 'paid', 'completed'])) {
            $orders = read_db('orders');
            foreach ($orders as &$ord) {
                if ($ord['id'] === $item_id) {
                    $ord['status'] = $new_status;
                    break;
                }
            }
            write_db('orders', $orders);
            $success_alert = 'Bestelstatus succesvol bijgewerkt!';
        }
    }
    
    if ($action === 'delete_order' && !empty($item_id)) {
        $orders = read_db('orders');
        $orders = array_filter($orders, function($ord) use ($item_id) {
            return $ord['id'] !== $item_id;
        });
        write_db('orders', array_values($orders));
        $success_alert = 'Bestelling succesvol verwijderd!';
    }
    
    // B. Echo approvals (Super Admin Only)
    if ($action === 'approve_echo' && !empty($item_id)) {
        if (!$is_super) {
            header("HTTP/1.1 403 Forbidden");
            exit("Toegang geweigerd.");
        }
        $echos = read_db('echos');
        $found = false;
        $approved_tak = '';
        foreach ($echos as &$echo) {
            if ($echo['id'] === $item_id) {
                $echo['approved'] = true;
                $approved_tak = $echo['tak'];
                $found = true;
                break;
            }
        }
        if ($found) {
            write_db('echos', $echos);
            if ($approved_tak) {
                enforce_echo_limit($approved_tak);
            }
            $success_alert = 'Kriko Echo planningsbrief succesvol goedgekeurd en gepubliceerd!';
        }
        $active_tab = 'echos';
    }
    
    // C. Delete Echo planner PDF (With division ownership check)
    if ($action === 'delete_echo' && !empty($item_id)) {
        $echos = read_db('echos');
        $found = false;
        $file_to_delete = '';
        
        foreach ($echos as $key => $echo) {
            if ($echo['id'] === $item_id) {
                // Security check: Takleiders can only delete their own
                if (!$is_super && $echo['tak'] !== $role) {
                    header("HTTP/1.1 403 Forbidden");
                    exit("Toegang geweigerd. U kunt alleen planningen van uw eigen tak beheren.");
                }
                $file_to_delete = $echo['file_name'];
                unset($echos[$key]);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            write_db('echos', array_values($echos));
            
            // Delete actual PDF file
            $file_path = UPLOADS_DIR . 'echos/' . $file_to_delete;
            if (!empty($file_to_delete) && file_exists($file_path)) {
                unlink($file_path);
            }
            $success_alert = 'Planningsbrief met succes verwijderd!';
        }
        $active_tab = 'echos';
    }
    
    // D. Super Admin Message Box operations
    if (in_array($action, ['delete_message', 'read_message']) && !$is_super) {
        header("HTTP/1.1 403 Forbidden");
        exit("Toegang geweigerd.");
    }
    
    if ($action === 'delete_message' && !empty($item_id)) {
        $messages = read_db('messages');
        $messages = array_filter($messages, function($msg) use ($item_id) {
            return $msg['id'] !== $item_id;
        });
        write_db('messages', array_values($messages));
        $success_alert = 'Bericht met succes verwijderd!';
        $active_tab = 'messages';
    }
    
    if ($action === 'read_message' && !empty($item_id)) {
        $messages = read_db('messages');
        foreach ($messages as &$msg) {
            if ($msg['id'] === $item_id) {
                $msg['read'] = true;
                break;
            }
        }
        write_db('messages', $messages);
        $success_alert = 'Bericht gemarkeerd als gelezen!';
        $active_tab = 'messages';
    }
    
    // E. Delete Calendar Event (Super Admin Only)
    if ($action === 'delete_calendar' && !empty($item_id)) {
        if (!$is_super) {
            header("HTTP/1.1 403 Forbidden");
            exit("Toegang geweigerd.");
        }
        $calendar = read_db('calendar');
        $calendar = array_filter($calendar, function($item) use ($item_id) {
            return $item['id'] !== $item_id;
        });
        write_db('calendar', array_values($calendar));
        $success_alert = 'Activiteit met succes verwijderd!';
        $active_tab = 'calendar';
    }
    
    // F. Update Registration status (Super Admin / Tak Owner Check)
    if ($action === 'update_registration' && !empty($item_id)) {
        $new_status = isset($_GET['status']) ? $_GET['status'] : '';
        if (in_array($new_status, ['pending', 'paid'])) {
            $registrations = read_db('registrations');
            $found = false;
            foreach ($registrations as &$reg) {
                if ($reg['id'] === $item_id) {
                    // Security check: Takleiders can only manage their own
                    if (!$is_super && $reg['child_tak'] !== $role) {
                        header("HTTP/1.1 403 Forbidden");
                        exit("Toegang geweigerd. U kunt alleen inschrijvingen van uw eigen tak beheren.");
                    }
                    $reg['status'] = $new_status;
                    $found = true;
                    break;
                }
            }
            if ($found) {
                write_db('registrations', $registrations);
                $success_alert = 'Inschrijvingsstatus succesvol bijgewerkt!';
            } else {
                $error_alert = 'Inschrijving niet gevonden.';
            }
        }
        $active_tab = 'registrations';
    }
    
    // G. Delete Registration (Super Admin / Tak Owner Check)
    if ($action === 'delete_registration' && !empty($item_id)) {
        $registrations = read_db('registrations');
        $found = false;
        
        foreach ($registrations as $key => $reg) {
            if ($reg['id'] === $item_id) {
                // Security check: Takleiders can only delete their own
                if (!$is_super && $reg['child_tak'] !== $role) {
                    header("HTTP/1.1 403 Forbidden");
                    exit("Toegang geweigerd. U kunt alleen inschrijvingen van uw eigen tak beheren.");
                }
                unset($registrations[$key]);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            write_db('registrations', array_values($registrations));
            $success_alert = 'Inschrijving succesvol verwijderd!';
        } else {
            $error_alert = 'Inschrijving niet gevonden.';
        }
        $active_tab = 'registrations';
    }
}

// 1.2 Form Submissions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Upload new Kriko Echo PDF
    if (isset($_POST['upload_echo'])) {
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS);
        $year = filter_input(INPUT_POST, 'year', FILTER_SANITIZE_NUMBER_INT);
        $month = filter_input(INPUT_POST, 'month', FILTER_SANITIZE_NUMBER_INT);
        
        // Tak is chosen by super admin, or hardcoded to division role
        if ($is_super) {
            $tak = filter_input(INPUT_POST, 'tak', FILTER_SANITIZE_SPECIAL_CHARS);
            $approved_status = true; // Auto approved for super admin
        } else {
            $tak = $role;
            $approved_status = false; // Requires approval for takleiders
        }
        
        if (empty($title) || empty($year) || empty($month) || empty($tak)) {
            $error_alert = 'Vul alle formuliervelden in voor het uploaden.';
        } elseif (!isset($_FILES['echo_file']) || $_FILES['echo_file']['error'] !== UPLOAD_ERR_OK) {
            $error_alert = 'Fout bij het uploaden van het bestand. Probeer het opnieuw.';
        } else {
            $file = $_FILES['echo_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if ($ext !== 'pdf') {
                $error_alert = 'Alleen PDF-bestanden zijn toegestaan!';
            } else {
                $new_filename = "echo-{$year}-{$month}-{$tak}-" . uniqid() . ".pdf";
                $upload_path = UPLOADS_DIR . 'echos/' . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $echos = read_db('echos');
                    $echos[] = [
                        'id' => 'echo_' . uniqid(),
                        'title' => $title,
                        'month' => (string)$month,
                        'year' => (string)$year,
                        'tak' => $tak,
                        'file_name' => $new_filename,
                        'uploaded_at' => date('Y-m-d H:i:s'),
                        'approved' => $approved_status
                    ];
                    write_db('echos', $echos);
                    
                    if ($approved_status) {
                        enforce_echo_limit($tak);
                        $success_alert = 'Kriko Echo succesvol geüpload en direct gepubliceerd!';
                    } else {
                        $success_alert = 'Kriko Echo succesvol geüpload! Deze wacht nu op goedkeuring van de groepsleiding.';
                    }
                    $active_tab = 'echos';
                } else {
                    $error_alert = 'Kon het bestand niet verplaatsen naar de uploadmap.';
                }
            }
        }
    }
    
    // B. Save global website settings (Super Admin Only)
    if (isset($_POST['save_settings'])) {
        if (!$is_super) {
            header("HTTP/1.1 403 Forbidden");
            exit("Toegang geweigerd.");
        }
        $settings = read_db('settings');
        
        $settings['scouts_year'] = filter_input(INPUT_POST, 'scouts_year', FILTER_SANITIZE_SPECIAL_CHARS);
        $settings['bank_iban'] = filter_input(INPUT_POST, 'bank_iban', FILTER_SANITIZE_SPECIAL_CHARS);
        $settings['bank_bic'] = filter_input(INPUT_POST, 'bank_bic', FILTER_SANITIZE_SPECIAL_CHARS);
        $settings['bank_holder'] = filter_input(INPUT_POST, 'bank_holder', FILTER_SANITIZE_SPECIAL_CHARS);
        $settings['contact_email'] = filter_input(INPUT_POST, 'contact_email', FILTER_VALIDATE_EMAIL);
        $settings['contact_phone'] = filter_input(INPUT_POST, 'contact_phone', FILTER_SANITIZE_SPECIAL_CHARS);
        $settings['contact_address'] = filter_input(INPUT_POST, 'contact_address', FILTER_SANITIZE_SPECIAL_CHARS);
        $settings['alert_message'] = filter_input(INPUT_POST, 'alert_message', FILTER_SANITIZE_SPECIAL_CHARS);
        $settings['alert_active'] = isset($_POST['alert_active']);
        $settings['registration_fee_first'] = filter_input(INPUT_POST, 'fee_1', FILTER_VALIDATE_FLOAT);
        $settings['registration_fee_extra'] = filter_input(INPUT_POST, 'fee_2', FILTER_VALIDATE_FLOAT);
        
        write_db('settings', $settings);
        $success_alert = 'Website-instellingen succesvol bijgewerkt!';
        $active_tab = 'settings';
    }
    
    // C. Save division specific settings (Tak Instellingen)
    if (isset($_POST['save_tak_settings'])) {
        $edit_tak = filter_input(INPUT_POST, 'edit_tak', FILTER_SANITIZE_SPECIAL_CHARS);
        
        // Security gate: Takleiders can only edit their own
        if (!$is_super && $edit_tak !== $role) {
            header("HTTP/1.1 403 Forbidden");
            exit("Toegang geweigerd. U kunt alleen instellingen van uw eigen tak bewerken.");
        }
        
        $settings = read_db('settings');
        if (isset($settings['takken']) && isset($settings['takken'][$edit_tak])) {
            $settings['takken'][$edit_tak]['email'] = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $settings['takken'][$edit_tak]['age_range'] = filter_input(INPUT_POST, 'age_range', FILTER_SANITIZE_SPECIAL_CHARS);
            $settings['takken'][$edit_tak]['school_year'] = filter_input(INPUT_POST, 'school_year', FILTER_SANITIZE_SPECIAL_CHARS);
            $settings['takken'][$edit_tak]['description'] = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);
            $settings['takken'][$edit_tak]['uniform'] = filter_input(INPUT_POST, 'uniform', FILTER_SANITIZE_SPECIAL_CHARS);
            
            // Parse leaders list
            $leaders_list = $_POST['leaders_list'];
            $lines = explode("\n", $leaders_list);
            $parsed_leaders = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = explode('-', $line, 2);
                $l_name = trim($parts[0]);
                $l_role = isset($parts[1]) ? trim($parts[1]) : 'Leiding';
                $parsed_leaders[] = ['name' => $l_name, 'role' => $l_role];
            }
            $settings['takken'][$edit_tak]['leaders'] = $parsed_leaders;
            
            // Parse activities details
            if (isset($_POST['activities']) && is_array($_POST['activities'])) {
                $parsed_activities = [];
                foreach ($_POST['activities'] as $act_type => $act_data) {
                    $parsed_activities[$act_type] = [
                        'title' => filter_var($act_data['title'], FILTER_SANITIZE_SPECIAL_CHARS),
                        'dates' => filter_var($act_data['dates'], FILTER_SANITIZE_SPECIAL_CHARS),
                        'reg_open' => filter_var($act_data['reg_open'], FILTER_SANITIZE_SPECIAL_CHARS),
                        'reg_close' => filter_var($act_data['reg_close'], FILTER_SANITIZE_SPECIAL_CHARS),
                        'price' => filter_var($act_data['price'], FILTER_VALIDATE_FLOAT),
                        'active' => isset($act_data['active']) && $act_data['active'] == '1'
                    ];
                }
                $settings['takken'][$edit_tak]['activities'] = $parsed_activities;
            }
            
            write_db('settings', $settings);
            $success_alert = "Gegevens van de tak '" . ucfirst($edit_tak) . "' succesvol bijgewerkt!";
        }
        $active_tab = 'tak_settings';
    }
    
    // D. Change role's password (allows any logged in role to edit their own)
    if (isset($_POST['change_password'])) {
        $old_pw = $_POST['old_password'];
        $new_pw = $_POST['new_password'];
        $conf_pw = $_POST['conf_password'];
        
        $settings = read_db('settings');
        $hash = $settings['accounts'][$role]['password_hash'];
        
        if (empty($old_pw) || empty($new_pw) || empty($conf_pw)) {
            $error_alert = 'Vul alle wachtwoordvelden in.';
        } elseif (!password_verify($old_pw, $hash)) {
            $error_alert = 'Het huidige wachtwoord is onjuist!';
        } elseif ($new_pw !== $conf_pw) {
            $error_alert = 'Het nieuwe wachtwoord en de bevestiging komen niet overeen.';
        } else {
            $settings['accounts'][$role]['password_hash'] = password_hash($new_pw, PASSWORD_DEFAULT);
            write_db('settings', $settings);
            $success_alert = 'Wachtwoord succesvol gewijzigd!';
            $active_tab = 'settings';
        }
    }

    // D.2 Change passwords of other divisions (Super Admin Only)
    if (isset($_POST['change_tak_passwords'])) {
        if (!$is_super) {
            header("HTTP/1.1 403 Forbidden");
            exit("Toegang geweigerd.");
        }
        
        $settings = read_db('settings');
        $updated_taks = [];
        $taks_to_update = ['kapoenen', 'welpen', 'jonggivers', 'givers'];
        
        foreach ($taks_to_update as $t_role) {
            $post_key = 'new_password_' . $t_role;
            if (!empty($_POST[$post_key])) {
                $settings['accounts'][$t_role]['password_hash'] = password_hash($_POST[$post_key], PASSWORD_DEFAULT);
                $updated_taks[] = $settings['accounts'][$t_role]['role_name'];
            }
        }
        
        if (!empty($updated_taks)) {
            write_db('settings', $settings);
            $success_alert = 'Wachtwoorden succesvol bijgewerkt voor: ' . implode(', ', $updated_taks) . '!';
        } else {
            $error_alert = 'Voer minimaal één nieuw wachtwoord in.';
        }
        $active_tab = 'settings';
    }
    
    // E. Save / Update Calendar Event (Super Admin Only)
    if (isset($_POST['save_calendar_event'])) {
        if (!$is_super) {
            header("HTTP/1.1 403 Forbidden");
            exit("Toegang geweigerd.");
        }
        $cal_id = filter_input(INPUT_POST, 'cal_id', FILTER_SANITIZE_SPECIAL_CHARS);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS);
        $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_SPECIAL_CHARS);
        $time = filter_input(INPUT_POST, 'time', FILTER_SANITIZE_SPECIAL_CHARS);
        $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_SPECIAL_CHARS);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);
        
        if (empty($title) || empty($date) || empty($time) || empty($location) || empty($description)) {
            $error_alert = 'Vul alle verplichte velden in.';
        } else {
            $calendar = read_db('calendar');
            
            if (empty($cal_id)) {
                // ADD NEW EVENT
                $new_id = 'cal_' . uniqid();
                $calendar[] = [
                    'id' => $new_id,
                    'title' => $title,
                    'date' => $date,
                    'time' => $time,
                    'location' => $location,
                    'description' => $description
                ];
                $success_alert = 'Nieuwe activiteit succesvol toegevoegd!';
            } else {
                // EDIT EXISTING EVENT
                $found = false;
                foreach ($calendar as &$item) {
                    if ($item['id'] === $cal_id) {
                        $item['title'] = $title;
                        $item['date'] = $date;
                        $item['time'] = $time;
                        $item['location'] = $location;
                        $item['description'] = $description;
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $success_alert = 'Activiteit succesvol bijgewerkt!';
                } else {
                    $error_alert = 'Activiteit niet gevonden.';
                }
            }
            
            if (empty($error_alert)) {
                write_db('calendar', $calendar);
            }
        }
        $active_tab = 'calendar';
    }
}

// Reload data models for view display
$orders = read_db('orders');
$echos = read_db('echos');
$messages = read_db('messages');
$settings = read_db('settings');
$calendar = read_db('calendar');
$registrations = read_db('registrations');

// Sort calendar by date asc
usort($calendar, function($a, $b) {
    return strtotime($a['date']) - strtotime($b['date']);
});

// Sort registrations by date desc
usort($registrations, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Sort echos by date desc
usort($echos, function($a, $b) {
    return strtotime($b['uploaded_at']) - strtotime($a['uploaded_at']);
});

// Sort orders by date desc
usort($orders, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Sort messages by date desc
usort($messages, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

$page_title = "Beheerportaal - " . $_SESSION['admin_role_name'];
require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Header Hero -->
<section class="tak-hero leiding">
    <div class="container">
        <span style="color: var(--color-accent); font-family: 'Outfit', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">Administrator paneel</span>
        <h2 class="tak-hero-title">Leiding Control Room</h2>
        <p style="font-size: 1.2rem; color: hsla(0, 0%, 100%, 0.9); margin-top: 8px;">Beheer de planningen, kledingwinkelbestellingen en site-instellingen.</p>
    </div>
</section>

<!-- 2. Main Admin Dashboard Panel Grid -->
<section class="section container" id="admin-portal-wrapper">
    
    <!-- Success / Error notification alerts -->
    <?php if (!empty($success_alert)): ?>
        <div style="background-color: hsla(145, 63%, 35%, 0.1); border: 2px solid var(--color-success); color: var(--color-success); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 30px; font-weight: 600; display: flex; gap: 8px;">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span><?php echo $success_alert; ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_alert)): ?>
        <div style="background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 30px; font-weight: 600; display: flex; gap: 8px;">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span><?php echo $error_alert; ?></span>
        </div>
    <?php endif; ?>

    <div class="admin-layout">
        
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar">
            <h4 style="font-size: 1.1rem; color: var(--color-primary-dark); padding: 0 8px; text-transform: uppercase; letter-spacing: 0.5px;">Menu</h4>
            <nav class="admin-menu">
                <?php if ($is_super): ?>
                    <!-- Super Admin Menus -->
                    <a href="admin.php?tab=orders" class="admin-menu-btn <?php echo $active_tab === 'orders' ? 'active' : ''; ?>">
                        Winkel Bestellingen (<?php echo count(array_filter($orders, function($o) { return in_array($o['status'], ['pending', 'waiting_approval']); })); ?>)
                    </a>
                    
                    <?php 
                    $pending_count = count(array_filter($echos, function($e) { return !isset($e['approved']) || !$e['approved']; }));
                    ?>
                    <a href="admin.php?tab=echos" class="admin-menu-btn <?php echo $active_tab === 'echos' ? 'active' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Kriko Echo Planners</span>
                        <?php if ($pending_count > 0): ?>
                            <span style="background-color: var(--color-accent); color: var(--color-primary-dark); font-size: 0.75rem; padding: 2px 6px; border-radius: 10px; font-weight: bold;">
                                <?php echo $pending_count; ?> te keuren
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <?php 
                    $pending_reg_count = count(array_filter($registrations, function($r) { return $r['status'] === 'pending'; }));
                    ?>
                    <a href="admin.php?tab=registrations" class="admin-menu-btn <?php echo $active_tab === 'registrations' ? 'active' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Inschrijvingen Weekend/Kamp</span>
                        <?php if ($pending_reg_count > 0): ?>
                            <span style="background-color: var(--color-accent); color: var(--color-primary-dark); font-size: 0.75rem; padding: 2px 6px; border-radius: 10px; font-weight: bold;">
                                <?php echo $pending_reg_count; ?> open
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="admin.php?tab=calendar" class="admin-menu-btn <?php echo $active_tab === 'calendar' ? 'active' : ''; ?>">
                        Aankomende Activiteiten (<?php echo count($calendar); ?>)
                    </a>
                    
                    <a href="admin.php?tab=tak_settings" class="admin-menu-btn <?php echo $active_tab === 'tak_settings' ? 'active' : ''; ?>">
                        Takken Pagina's Beheren
                    </a>
                    
                    <a href="admin.php?tab=messages" class="admin-menu-btn <?php echo $active_tab === 'messages' ? 'active' : ''; ?>">
                        Berichten Box (<?php echo count(array_filter($messages, function($m) { return !$m['read']; })); ?>)
                    </a>
                    <a href="admin.php?tab=settings" class="admin-menu-btn <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                        Site Instellingen
                    </a>
                <?php else: ?>
                    <!-- Tak Admin Menus -->
                    <a href="admin.php?tab=echos" class="admin-menu-btn <?php echo $active_tab === 'echos' ? 'active' : ''; ?>">
                        Mijn Planningsbrieven (Echo's)
                    </a>
                    
                    <?php 
                    $my_pending_reg_count = count(array_filter($registrations, function($r) use ($role) { return $r['child_tak'] === $role && $r['status'] === 'pending'; }));
                    ?>
                    <a href="admin.php?tab=registrations" class="admin-menu-btn <?php echo $active_tab === 'registrations' ? 'active' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Inschrijvingen Tak</span>
                        <?php if ($my_pending_reg_count > 0): ?>
                            <span style="background-color: var(--color-accent); color: var(--color-primary-dark); font-size: 0.75rem; padding: 2px 6px; border-radius: 10px; font-weight: bold;">
                                <?php echo $my_pending_reg_count; ?> open
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="admin.php?tab=tak_settings" class="admin-menu-btn <?php echo $active_tab === 'tak_settings' ? 'active' : ''; ?>">
                        Mijn Tak Gegevens
                    </a>
                    <a href="admin.php?tab=settings" class="admin-menu-btn <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                        Wachtwoord & Beveiliging
                    </a>
                <?php endif; ?>
                
                <!-- Afmeldknop (Logout) -->
                <a href="login.php?logout=1" class="admin-menu-btn" style="margin-top: 20px; border-top: 1px solid var(--color-border); padding-top: 20px; color: var(--color-error); font-weight: bold;">
                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span>Afmelden</span>
                </a>
            </nav>
        </aside>

        <!-- Right Content Tab panels -->
        <main>
            
            <?php if ($is_super): ?>
                <div class="admin-panel <?php echo $active_tab === 'orders' ? 'active' : ''; ?>" data-tab="orders">
                    <div class="admin-panel-header">
                        <h3 style="font-size: 1.5rem; color: var(--color-primary-dark);">Webshop Kledij Bestellingen</h3>
                        <span style="font-size: 0.85rem; color: var(--color-text-muted);">Totaal geplaatst: <?php echo count($orders); ?> bestellingen</span>
                    </div>
                    
                    <?php if (empty($orders)): ?>
                        <p style="color: var(--color-text-muted); text-align: center; padding: 40px 0;">Er zijn nog geen bestellingen geplaatst.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID / Datum</th>
                                        <th>Koper (Ouder)</th>
                                        <th>Lid (Kind / Tak)</th>
                                        <th>Totaal</th>
                                        <th>Mededeling</th>
                                        <th>Status</th>
                                        <th>Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): 
                                        $status_label = 'Te betalen';
                                        if ($order['status'] === 'waiting_approval') $status_label = 'Wachten op bevestiging';
                                        if ($order['status'] === 'paid') $status_label = 'Betaald';
                                        if ($order['status'] === 'completed') $status_label = 'Geleverd';
                                    ?>
                                        <tr>
                                            <td>
                                                <strong style="display: block; font-size: 0.8rem;"><?php echo htmlspecialchars($order['id']); ?></strong>
                                                <span style="font-size: 0.75rem; color: var(--color-text-muted);"><?php echo date('d-m-Y H:i', strtotime($order['date'])); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                                <span style="display: block; font-size: 0.75rem; color: var(--color-text-muted);"><?php echo htmlspecialchars($order['email']); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($order['child_name']); ?></strong>
                                                <span style="display: block; font-size: 0.75rem; color: var(--color-secondary);"><?php echo ucfirst($order['child_tak']); ?></span>
                                            </td>
                                            <td style="font-weight: 700; color: var(--color-primary-dark);">€<?php echo number_format($order['total'], 2, ',', ''); ?></td>
                                            <td style="font-family: monospace; font-size: 0.8rem; font-weight: 600;"><?php echo htmlspecialchars($order['communication']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $order['status']; ?>"><?php echo $status_label; ?></span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 6px;">
                                                    <?php if ($order['status'] === 'pending' || $order['status'] === 'waiting_approval'): ?>
                                                        <a href="admin.php?tab=orders&action=update_order&id=<?php echo $order['id']; ?>&status=paid" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem; background-color: var(--color-success);" title="Markeer als Betaald">Betaald</a>
                                                    <?php elseif ($order['status'] === 'paid'): ?>
                                                        <a href="admin.php?tab=orders&action=update_order&id=<?php echo $order['id']; ?>&status=completed" class="btn btn-primary" style="padding: 4px 8px; font-size: 0.75rem;" title="Markeer als Geleverd">Geleverd</a>
                                                    <?php endif; ?>
                                                    <a href="admin.php?tab=orders&action=delete_order&id=<?php echo $order['id']; ?>" data-confirm="Bent u zeker dat u deze bestelling wilt verwijderen?" class="btn btn-danger btn-icon" style="padding: 4px 8px; font-size: 0.75rem;" title="Verwijderen">
                                                        &times;
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Embedded Item collapse row displaying purchased sizes -->
                                        <tr style="background-color: var(--color-bg-linen);">
                                            <td colspan="7" style="padding: 8px 16px; font-size: 0.8rem; border-bottom: 2px solid var(--color-border);">
                                                <strong>Bestelde items:</strong> &bull;
                                                <?php foreach ($order['items'] as $item): ?>
                                                    <span style="margin-right: 16px; display: inline-block;">
                                                        <?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['name']); ?> (Maat: <?php echo htmlspecialchars($item['size']); ?>)
                                                    </span>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- PANEL 2: Kriko Echo's Manager (Upload & Archives) -->
            <div class="admin-panel <?php echo $active_tab === 'echos' ? 'active' : ''; ?>" data-tab="echos">
                <div class="admin-panel-header">
                    <h3 style="font-size: 1.5rem; color: var(--color-primary-dark);">
                        <?php echo $is_super ? "Upload & Beheer Planningsbrieven (Echo's)" : "Mijn Planningsbrieven (" . ucfirst($role) . ")"; ?>
                    </h3>
                </div>

                <!-- A. Groepsleiding Approvals Queue -->
                <?php if ($is_super): 
                    $pending_echos = array_filter($echos, function($e) { return !isset($e['approved']) || !$e['approved']; });
                ?>
                    <?php if (!empty($pending_echos)): ?>
                        <div style="background-color: hsla(38, 80%, 50%, 0.08); border: 2px solid var(--color-accent); border-radius: var(--border-radius-md); padding: 24px; margin-bottom: 40px;">
                            <h4 style="color: var(--color-primary-dark); font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px; margin-bottom: 14px;">
                                <svg style="width: 22px; height: 22px; fill: var(--color-accent);" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                                Wachtend op Goedkeuring (<?php echo count($pending_echos); ?>)
                            </h4>
                            <p style="font-size: 0.9rem; color: var(--color-text-muted); margin-bottom: 18px;">
                                Onderstaande planningsbrieven zijn geüpload door de takleiding. Keur ze goed om ze live te zetten op de website, of wijs ze af om ze te verwijderen.
                            </p>
                            
                            <div class="table-responsive">
                                <table class="admin-table" style="background-color: var(--color-bg-white);">
                                    <thead>
                                        <tr>
                                            <th>Titel</th>
                                            <th>Tak</th>
                                            <th>Maand / Jaar</th>
                                            <th>Ingezonden op</th>
                                            <th>Bestand</th>
                                            <th style="width: 180px;">Actie</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_echos as $pe): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($pe['title']); ?></strong></td>
                                                <td><span class="status-badge" style="background-color: var(--color-bg-linen); color: var(--color-primary-dark);"><?php echo ucfirst($pe['tak']); ?></span></td>
                                                <td><?php echo $pe['month'] . ' / ' . $pe['year']; ?></td>
                                                <td style="font-size: 0.8rem; color: var(--color-text-muted);"><?php echo date('d-m-Y H:i', strtotime($pe['uploaded_at'])); ?></td>
                                                <td>
                                                    <a href="uploads/echos/<?php echo urlencode($pe['file_name']); ?>" download style="color: var(--color-secondary); font-weight: bold; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 4px;">
                                                        Bekijken
                                                    </a>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 8px;">
                                                        <a href="admin.php?tab=echos&action=approve_echo&id=<?php echo $pe['id']; ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem; background-color: var(--color-success);">Goedkeuren</a>
                                                        <a href="admin.php?tab=echos&action=delete_echo&id=<?php echo $pe['id']; ?>" data-confirm="Bent u zeker dat u deze planning wilt afwijzen en verwijderen?" class="btn btn-danger" style="padding: 4px 8px; font-size: 0.75rem;">Afwijzen</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- B. PDF Upload Form -->
                <div style="background-color: var(--color-bg-linen); border-radius: var(--border-radius-md); padding: 24px; border: 1px solid var(--color-border); margin-bottom: 40px;">
                    <h4 style="margin-bottom: 16px; font-size: 1.15rem; color: var(--color-primary-dark);">Nieuwe kalender/planning uploaden</h4>
                    
                    <form action="admin.php" method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <!-- Title -->
                            <div class="form-group">
                                <label class="form-label" for="title">Titel Planningsbrief:</label>
                                <input type="text" id="title" name="title" class="form-control" placeholder="Bijv. Kriko Echo Oktober 2026" required>
                            </div>
                            
                            <!-- Destination division (Locked/Hidden for Takleiders) -->
                            <div class="form-group">
                                <label class="form-label">Voor welke Tak?</label>
                                <?php if ($is_super): ?>
                                    <select id="tak" name="tak" class="form-control" required>
                                        <option value="" disabled selected>Kies Tak</option>
                                        <option value="kapoenen">Kapoenen (6-8j)</option>
                                        <option value="welpen">Welpen (8-11j)</option>
                                        <option value="jonggivers">Jonggivers (11-14j)</option>
                                        <option value="givers">Givers (14-17j)</option>
                                    </select>
                                <?php else: ?>
                                    <div class="form-control" style="background-color: var(--color-border); font-weight: bold; color: var(--color-primary-dark); display: flex; align-items: center; border: 1px solid var(--color-border);">
                                        <?php echo ucfirst($role); ?> (Ingelogde tak)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row" style="grid-template-columns: 1fr 1fr 2fr;">
                            <!-- Year -->
                            <div class="form-group">
                                <label class="form-label" for="year">Jaar:</label>
                                <select id="year" name="year" class="form-control" required>
                                    <option value="<?php echo date('Y'); ?>" selected><?php echo date('Y'); ?></option>
                                    <option value="<?php echo date('Y') + 1; ?>"><?php echo date('Y') + 1; ?></option>
                                </select>
                            </div>
                            
                            <!-- Month -->
                            <div class="form-group">
                                <label class="form-label" for="month">Maand:</label>
                                <select id="month" name="month" class="form-control" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo date('n') == $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <!-- PDF file upload input -->
                            <div class="form-group">
                                <label class="form-label" for="echo_file">Selecteer PDF-bestand:</label>
                                <input type="file" id="echo_file" name="echo_file" class="form-control" accept=".pdf" style="padding: 7px 12px;" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="upload_echo" class="btn btn-secondary" style="margin-top: 10px; width: 100%;">
                            <?php echo $is_super ? "Planningsbrief uploaden & publiceren (PDF)" : "Planningsbrief uploaden ter goedkeuring (PDF)"; ?>
                        </button>
                    </form>
                </div>
                
                <!-- C. Echo registry list -->
                <h4 style="margin-bottom: 12px; font-size: 1.15rem;">
                    <?php echo $is_super ? "Geregistreerde planningen" : "Mijn ingezonden planningen"; ?>
                </h4>
                
                <?php 
                // Filter viewable planningen based on role
                $display_echos = $echos;
                if (!$is_super) {
                    $display_echos = array_filter($echos, function($e) use ($role) { return $e['tak'] === $role; });
                }
                ?>
                
                <?php if (empty($display_echos)): ?>
                    <p style="color: var(--color-text-muted);">Er zijn nog geen planningsbrieven geüpload.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Titel</th>
                                    <th>Tak</th>
                                    <th>Maand / Jaar</th>
                                    <th>Geüpload op</th>
                                    <th>Status</th>
                                    <th>Bestand</th>
                                    <th>Actie</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($display_echos as $echo): 
                                    $echo_approved = isset($echo['approved']) && $echo['approved'];
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($echo['title']); ?></strong></td>
                                        <td><span class="status-badge" style="background-color: var(--color-bg-linen); color: var(--color-primary-dark);"><?php echo ucfirst($echo['tak']); ?></span></td>
                                        <td><?php echo $echo['month'] . ' / ' . $echo['year']; ?></td>
                                        <td style="font-size: 0.8rem; color: var(--color-text-muted);"><?php echo date('d-m-Y H:i', strtotime($echo['uploaded_at'])); ?></td>
                                        <td>
                                            <?php if ($echo_approved): ?>
                                                <span class="status-badge" style="background-color: hsla(145, 63%, 35%, 0.15); color: var(--color-success);">Gepubliceerd</span>
                                            <?php else: ?>
                                                <span class="status-badge" style="background-color: hsla(38, 80%, 50%, 0.15); color: var(--color-accent);">Wacht op goedkeuring</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="uploads/echos/<?php echo urlencode($echo['file_name']); ?>" download style="color: var(--color-secondary); font-weight: bold; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                                                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                                PDF openen
                                            </a>
                                        </td>
                                        <td>
                                            <a href="admin.php?tab=echos&action=delete_echo&id=<?php echo $echo['id']; ?>" data-confirm="Bent u zeker dat u dit planningsbestand permanent wilt verwijderen?" class="btn btn-danger" style="padding: 4px 10px; font-size: 0.75rem;">
                                                Verwijder
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- PANEL 3: Tak Gegevens bewerken (Kapoenen, Welpen, etc.) -->
            <div class="admin-panel <?php echo $active_tab === 'tak_settings' ? 'active' : ''; ?>" data-tab="tak_settings">
                <?php 
                // Determine which tak we are editing
                $edit_tak = $role;
                if ($is_super) {
                    $edit_tak = isset($_GET['edit_tak']) && in_array($_GET['edit_tak'], ['kapoenen', 'welpen', 'jonggivers', 'givers']) ? $_GET['edit_tak'] : 'kapoenen';
                }
                $tak_info = isset($settings['takken'][$edit_tak]) ? $settings['takken'][$edit_tak] : [];
                
                // Convert leaders list back to string format Naam - Rol for editing
                $leaders_text = "";
                if (isset($tak_info['leaders'])) {
                    foreach ($tak_info['leaders'] as $l) {
                        $leaders_text .= $l['name'] . " - " . $l['role'] . "\n";
                    }
                }
                $leaders_text = trim($leaders_text);
                ?>
                
                <div class="admin-panel-header">
                    <h3 style="font-size: 1.5rem; color: var(--color-primary-dark);">
                        Tak Gegevens Beheren: <?php echo ucfirst($edit_tak); ?>
                    </h3>
                </div>

                <?php if ($is_super): ?>
                    <!-- Tak chooser for super admins -->
                    <div style="background-color: var(--color-bg-linen); padding: 15px; border-radius: var(--border-radius-md); border: 1px solid var(--color-border); margin-bottom: 30px; display: flex; align-items: center; gap: 12px;">
                        <strong>Kies een tak om te bewerken:</strong>
                        <div style="display: flex; gap: 8px;">
                            <a href="admin.php?tab=tak_settings&edit_tak=kapoenen" class="btn <?php echo $edit_tak === 'kapoenen' ? 'btn-secondary' : 'btn-outline'; ?>" style="padding: 4px 12px; font-size: 0.85rem;">Kapoenen</a>
                            <a href="admin.php?tab=tak_settings&edit_tak=welpen" class="btn <?php echo $edit_tak === 'welpen' ? 'btn-secondary' : 'btn-outline'; ?>" style="padding: 4px 12px; font-size: 0.85rem;">Welpen</a>
                            <a href="admin.php?tab=tak_settings&edit_tak=jonggivers" class="btn <?php echo $edit_tak === 'jonggivers' ? 'btn-secondary' : 'btn-outline'; ?>" style="padding: 4px 12px; font-size: 0.85rem;">Jonggivers</a>
                            <a href="admin.php?tab=tak_settings&edit_tak=givers" class="btn <?php echo $edit_tak === 'givers' ? 'btn-secondary' : 'btn-outline'; ?>" style="padding: 4px 12px; font-size: 0.85rem;">Givers</a>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="admin.php" method="POST">
                    <input type="hidden" name="edit_tak" value="<?php echo htmlspecialchars($edit_tak); ?>">

                    <div class="form-row">
                        <!-- Email -->
                        <div class="form-group">
                            <label class="form-label" for="email">Contact E-mailadres Tak:</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($tak_info['email']); ?>" required>
                        </div>
                        
                        <!-- Age range -->
                        <div class="form-group">
                            <label class="form-label" for="age_range">Leeftijdsgroep:</label>
                            <input type="text" id="age_range" name="age_range" class="form-control" value="<?php echo htmlspecialchars($tak_info['age_range']); ?>" placeholder="Bijv: 6 - 8 jaar" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="school_year">Schooljaren details:</label>
                        <input type="text" id="school_year" name="school_year" class="form-control" value="<?php echo htmlspecialchars($tak_info['school_year']); ?>" placeholder="Bijv: 1e & 2e leerjaar" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Beschrijving van de tak:</label>
                        <textarea id="description" name="description" class="form-control" rows="5" style="resize: vertical; line-height: 1.5;" required><?php echo htmlspecialchars($tak_info['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="uniform">Uniform instructies:</label>
                        <textarea id="uniform" name="uniform" class="form-control" rows="3" style="resize: vertical; line-height: 1.5;" required><?php echo htmlspecialchars($tak_info['uniform']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="leaders_list">De Leiding (Eén leider per regel. Formaat: <code>Naam - Rol</code>):</label>
                        <textarea id="leaders_list" name="leaders_list" class="form-control" rows="6" style="resize: vertical; font-family: monospace; font-size: 0.9rem;" placeholder="Bijvoorbeeld:&#10;Arne Janssens - Takleider&#10;Mathijs Smet - Leiding" required><?php echo htmlspecialchars($leaders_text); ?></textarea>
                        <span style="display: block; font-size: 0.75rem; color: var(--color-text-muted); margin-top: 6px;">
                            Gebruik een liggend streepje (-) om de naam van de rol te scheiden. Indien geen rol opgegeven, wordt deze standaard 'Leiding'.
                        </span>
                    </div>

                    <!-- Weekend & Kamp Activiteiten -->
                    <h4 style="margin-top: 40px; margin-bottom: 20px; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 8px; color: var(--color-primary-dark); display: flex; align-items: center; gap: 8px;">
                        <svg style="width: 22px; height: 22px; fill: none; stroke: var(--color-secondary);" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Weekend & Kamp Activiteiten
                    </h4>
                    
                    <?php 
                    $activities = isset($tak_info['activities']) ? $tak_info['activities'] : [];
                    $act_types = [
                        'takweekend' => 'Takweekend',
                        'groepsweekend' => 'Groepsweekend',
                        'kamp' => 'Zomerkamp'
                    ];
                    foreach ($act_types as $type_key => $type_name):
                        $act = isset($activities[$type_key]) ? $activities[$type_key] : [
                            'title' => '', 'dates' => '', 'reg_open' => '', 'reg_close' => '', 'price' => 0.00, 'active' => false
                        ];
                    ?>
                        <div style="background-color: var(--color-bg-linen); border: 1px solid var(--color-border); padding: 20px; border-radius: var(--border-radius-md); margin-bottom: 24px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed var(--color-border); padding-bottom: 10px; margin-bottom: 15px;">
                                <h5 style="font-size: 1.1rem; color: var(--color-primary-dark); margin: 0;"><?php echo $type_name; ?></h5>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 600; color: var(--color-text-dark);">
                                    <input type="checkbox" name="activities[<?php echo $type_key; ?>][active]" value="1" <?php echo !empty($act['active']) ? 'checked' : ''; ?> style="width: 16px; height: 16px;">
                                    Inschrijvingen Actief
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Titel Activiteit:</label>
                                    <input type="text" name="activities[<?php echo $type_key; ?>][title]" class="form-control" value="<?php echo htmlspecialchars($act['title']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Datums Activiteit:</label>
                                    <input type="text" name="activities[<?php echo $type_key; ?>][dates]" class="form-control" value="<?php echo htmlspecialchars($act['dates']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row" style="margin-top: 10px;">
                                <div class="form-group">
                                    <label class="form-label">Inschrijvingen Open (JJJJ-MM-DD):</label>
                                    <input type="date" name="activities[<?php echo $type_key; ?>][reg_open]" class="form-control" value="<?php echo htmlspecialchars($act['reg_open']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Inschrijvingen Sluiten (JJJJ-MM-DD):</label>
                                    <input type="date" name="activities[<?php echo $type_key; ?>][reg_close]" class="form-control" value="<?php echo htmlspecialchars($act['reg_close']); ?>" required>
                                </div>
                                <div class="form-group" style="max-width: 200px;">
                                    <label class="form-label">Deelnameprijs (€):</label>
                                    <input type="number" step="0.01" name="activities[<?php echo $type_key; ?>][price]" class="form-control" value="<?php echo htmlspecialchars($act['price']); ?>" required>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" name="save_tak_settings" class="btn btn-secondary" style="width: 100%; margin-top: 15px;">
                        Gegevens van <?php echo ucfirst($edit_tak); ?> opslaan
                    </button>
                </form>
            </div>
            
            <!-- PANEL 4: Berichten Box (Messages Archive) - Groepsleiding Only -->
            <?php if ($is_super): ?>
                <div class="admin-panel <?php echo $active_tab === 'messages' ? 'active' : ''; ?>" data-tab="messages">
                    <div class="admin-panel-header">
                        <h3 style="font-size: 1.5rem; color: var(--color-primary-dark);">Berichten Inbox (Contact Formulier)</h3>
                        <span style="font-size: 0.85rem; color: var(--color-text-muted);">Totaal ontvangen: <?php echo count($messages); ?> berichten</span>
                    </div>
                    
                    <?php if (empty($messages)): ?>
                        <p style="color: var(--color-text-muted); text-align: center; padding: 40px 0;">Geen berichten ontvangen.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 20px;">
                            <?php foreach ($messages as $msg): ?>
                                <div style="border: 1px solid var(--color-border); border-radius: var(--border-radius-md); padding: 20px; position: relative; <?php echo !$msg['read'] ? 'background-color: hsla(42, 85%, 55%, 0.05); border-left: 4px solid var(--color-accent);' : 'background-color: var(--color-bg-white);'; ?>">
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                                        <div>
                                            <strong style="font-size: 1.05rem; display: block; color: var(--color-primary-dark);"><?php echo htmlspecialchars($msg['name']); ?></strong>
                                            <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>" style="font-size: 0.8rem; color: var(--color-secondary); font-weight: bold;"><?php echo htmlspecialchars($msg['email']); ?></a>
                                        </div>
                                        <div style="text-align: right;">
                                            <span style="font-size: 0.75rem; color: var(--color-text-muted); display: block;"><?php echo date('d-m-Y H:i', strtotime($msg['date'])); ?></span>
                                            <span class="status-badge" style="background-color: var(--color-bg-linen); color: var(--color-primary-dark); font-size: 0.75rem; margin-top: 4px;"><?php echo ucfirst($msg['subject']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <p style="font-size: 0.95rem; line-height: 1.5; color: var(--color-text-dark); background-color: var(--color-bg-linen); padding: 12px; border-radius: var(--border-radius-sm); margin-bottom: 14px;">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    </p>
                                    
                                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                        <?php if (!$msg['read']): ?>
                                            <a href="admin.php?tab=messages&action=read_message&id=<?php echo $msg['id']; ?>" class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.75rem; background-color: var(--color-success);">Markeer als Gelezen</a>
                                        <?php endif; ?>
                                        <a href="admin.php?tab=messages&action=delete_message&id=<?php echo $msg['id']; ?>" data-confirm="Wilt u dit bericht permanent verwijderen?" class="btn btn-danger" style="padding: 4px 10px; font-size: 0.75rem;">Verwijder</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- PANEL 5: Instellingen (Settings & Configuration Panel) -->
            <div class="admin-panel <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" data-tab="settings">
                <div class="admin-panel-header">
                    <h3 style="font-size: 1.5rem; color: var(--color-primary-dark);">
                        <?php echo $is_super ? "Website Instellingen & Beveiliging" : "Wachtwoord & Beveiliging Instellingen"; ?>
                    </h3>
                </div>
                
                <?php if ($is_super): ?>
                    <!-- General Info Form - Groepsleiding Only -->
                    <form action="admin.php" method="POST" style="margin-bottom: 50px;">
                        <h4 style="margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 6px; color: var(--color-primary-dark);">Algemene Configuratie</h4>
                        
                        <div class="form-row">
                            <!-- Active Year -->
                            <div class="form-group">
                                <label class="form-label" for="scouts_year">Actief Scoutsjaar:</label>
                                <input type="text" id="scouts_year" name="scouts_year" class="form-control" value="<?php echo htmlspecialchars($settings['scouts_year']); ?>" placeholder="Bijv. 2026-2027" required>
                            </div>
                            
                            <!-- Contact Email -->
                            <div class="form-group">
                                <label class="form-label" for="contact_email">Hoofd Contact Email (Groepsleiding):</label>
                                <input type="email" id="contact_email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($settings['contact_email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <!-- Contact Phone -->
                            <div class="form-group">
                                <label class="form-label" for="contact_phone">Telefoonnummer:</label>
                                <input type="text" id="contact_phone" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>" required>
                            </div>
                            
                            <!-- Address -->
                            <div class="form-group">
                                <label class="form-label" for="contact_address">Adres Lokalen:</label>
                                <input type="text" id="contact_address" name="contact_address" class="form-control" value="<?php echo htmlspecialchars($settings['contact_address']); ?>" required>
                            </div>
                        </div>
                        
                        <h4 style="margin-top: 30px; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 6px; color: var(--color-primary-dark);">Website Alert / Welkomstbanner</h4>
                        
                        <div class="form-group">
                            <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="alert_active" value="1" <?php echo isset($settings['alert_active']) && $settings['alert_active'] ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                                Alert banner bovenaan website weergeven
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="alert_message">Inhoud Welkomstbericht:</label>
                            <input type="text" id="alert_message" name="alert_message" class="form-control" value="<?php echo htmlspecialchars($settings['alert_message']); ?>" placeholder="Bijv. Welkom! De inschrijvingen zijn geopend.">
                        </div>
                        
                        <h4 style="margin-top: 30px; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 6px; color: var(--color-primary-dark);">Bankoverschrijving & Shop Instellingen</h4>
                        
                        <div class="form-row">
                            <!-- Bank IBAN -->
                            <div class="form-group">
                                <label class="form-label" for="bank_iban">IBAN Bankrekening:</label>
                                <input type="text" id="bank_iban" name="bank_iban" class="form-control" value="<?php echo htmlspecialchars($settings['bank_iban']); ?>" required>
                            </div>
                            
                            <!-- Bank BIC -->
                            <div class="form-group">
                                <label class="form-label" for="bank_bic">BIC Code:</label>
                                <input type="text" id="bank_bic" name="bank_bic" class="form-control" value="<?php echo htmlspecialchars($settings['bank_bic']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="bank_holder">Naam Rekeninghouder:</label>
                            <input type="text" id="bank_holder" name="bank_holder" class="form-control" value="<?php echo htmlspecialchars($settings['bank_holder']); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <!-- Fee 1 -->
                            <div class="form-group">
                                <label class="form-label" for="fee_1">Lidgeld 1e gezinslid (€):</label>
                                <input type="number" step="0.01" id="fee_1" name="fee_1" class="form-control" value="<?php echo htmlspecialchars($settings['registration_fee_first']); ?>" required>
                            </div>
                            
                            <!-- Fee 2 -->
                            <div class="form-group">
                                <label class="form-label" for="fee_2">Lidgeld vanaf 2e gezinslid (€):</label>
                                <input type="number" step="0.01" id="fee_2" name="fee_2" class="form-control" value="<?php echo htmlspecialchars($settings['registration_fee_extra']); ?>" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="save_settings" class="btn btn-secondary" style="width: 100%; margin-top: 10px;">
                            Website instellingen opslaan
                        </button>
                    </form>
                <?php endif; ?>
                
                <!-- Password Change Form - Visible to ALL logged-in roles -->
                <form action="admin.php" method="POST">
                    <h4 style="margin-top: 40px; margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 6px; color: var(--color-primary-dark);">
                        <?php echo $is_super ? "Super-Admin Wachtwoord Wijzigen" : "Wachtwoord Wijzigen (" . $_SESSION['admin_role_name'] . ")"; ?>
                    </h4>
                    
                    <div class="form-group">
                        <label class="form-label" for="old_password">Huidig Wachtwoord:</label>
                        <input type="password" id="old_password" name="old_password" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="new_password">Nieuw Wachtwoord:</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="conf_password">Bevestig Nieuw Wachtwoord:</label>
                            <input type="password" id="conf_password" name="conf_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                        Wachtwoord aanpassen
                    </button>
                </form>

                <?php if ($is_super): ?>
                    <!-- Passwords Management for other divisions - Groepsleiding Only -->
                    <form action="admin.php" method="POST" style="margin-top: 50px;">
                        <h4 style="margin-bottom: 16px; border-bottom: 1px solid var(--color-border); padding-bottom: 6px; color: var(--color-primary-dark);">
                            Wachtwoorden Andere Takken Aanpassen
                        </h4>
                        <p style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 20px;">
                            Als groepsleiding kunt u hier de wachtwoorden van de andere takken aanpassen. Laat velden leeg om het huidige wachtwoord van die tak te behouden.
                        </p>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="new_password_kapoenen">Nieuw Wachtwoord Kapoenen:</label>
                                <input type="password" id="new_password_kapoenen" name="new_password_kapoenen" class="form-control" placeholder="Huidig behouden indien leeg">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="new_password_welpen">Nieuw Wachtwoord Welpen:</label>
                                <input type="password" id="new_password_welpen" name="new_password_welpen" class="form-control" placeholder="Huidig behouden indien leeg">
                            </div>
                        </div>
                        
                        <div class="form-row" style="margin-top: 10px;">
                            <div class="form-group">
                                <label class="form-label" for="new_password_jonggivers">Nieuw Wachtwoord Jonggivers:</label>
                                <input type="password" id="new_password_jonggivers" name="new_password_jonggivers" class="form-control" placeholder="Huidig behouden indien leeg">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="new_password_givers">Nieuw Wachtwoord Givers:</label>
                                <input type="password" id="new_password_givers" name="new_password_givers" class="form-control" placeholder="Huidig behouden indien leeg">
                            </div>
                        </div>
                        
                        <button type="submit" name="change_tak_passwords" class="btn btn-secondary" style="width: 100%; margin-top: 15px;">
                            Wachtwoorden van geselecteerde takken opslaan
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- PANEL 6: Aankomende Activiteiten (Calendar) - Groepsleiding Only -->
            <?php if ($is_super): ?>
                <?php 
                // Check if we are editing an event
                $edit_cal_id = isset($_GET['edit_cal']) ? $_GET['edit_cal'] : '';
                $edit_item = [
                    'id' => '', 'title' => '', 'date' => '', 'time' => '', 'location' => '', 'description' => ''
                ];
                if (!empty($edit_cal_id)) {
                    foreach ($calendar as $item) {
                        if ($item['id'] === $edit_cal_id) {
                            $edit_item = $item;
                            break;
                        }
                    }
                }
                ?>
                <div class="admin-panel <?php echo $active_tab === 'calendar' ? 'active' : ''; ?>" data-tab="calendar">
                    <div class="admin-panel-header">
                        <h3 style="font-size: 1.5rem; color: var(--color-primary-dark);">
                            Aankomende Activiteiten (Homepagina)
                        </h3>
                        <span style="font-size: 0.85rem; color: var(--color-text-muted);">
                            Hier kunt u de groepsactiviteiten beheren die getoond worden in de kalender op de homepagina.
                        </span>
                    </div>
                    
                    <!-- Add / Edit Form -->
                    <form action="admin.php?tab=calendar" method="POST" style="background-color: var(--color-bg-linen); border: 1px solid var(--color-border); padding: 25px; border-radius: var(--border-radius-lg); margin-bottom: 40px;">
                        <h4 style="margin-top: 0; margin-bottom: 20px; color: var(--color-primary-dark); border-bottom: 1px dashed var(--color-border); padding-bottom: 8px;">
                            <?php echo empty($edit_item['id']) ? "Nieuwe Activiteit Toevoegen" : "Activiteit Bewerken: " . htmlspecialchars($edit_item['title']); ?>
                        </h4>
                        
                        <input type="hidden" name="cal_id" value="<?php echo htmlspecialchars($edit_item['id']); ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="cal_title">Titel Activiteit:</label>
                                <input type="text" id="cal_title" name="title" class="form-control" value="<?php echo htmlspecialchars($edit_item['title']); ?>" placeholder="Bijv: Startdag Scoutsjaar" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="cal_date">Datum:</label>
                                <input type="date" id="cal_date" name="date" class="form-control" value="<?php echo htmlspecialchars($edit_item['date']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row" style="margin-top: 10px;">
                            <div class="form-group">
                                <label class="form-label" for="cal_time">Tijd / Uren:</label>
                                <input type="text" id="cal_time" name="time" class="form-control" value="<?php echo htmlspecialchars($edit_item['time']); ?>" placeholder="Bijv: 14:00 - 17:00" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="cal_location">Locatie:</label>
                                <input type="text" id="cal_location" name="location" class="form-control" value="<?php echo htmlspecialchars($edit_item['location']); ?>" placeholder="Bijv: Scoutslokalen, Industriepark-Noord" required>
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-top: 10px;">
                            <label class="form-label" for="cal_description">Beschrijving / Info:</label>
                            <textarea id="cal_description" name="description" class="form-control" rows="3" style="resize: vertical;" placeholder="Korte beschrijving voor op de homepagina..." required><?php echo htmlspecialchars($edit_item['description']); ?></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 12px; margin-top: 15px;">
                            <button type="submit" name="save_calendar_event" class="btn btn-secondary" style="width: 70%;">
                                <?php echo empty($edit_item['id']) ? "Activiteit Toevoegen" : "Wijzigingen Opslaan"; ?>
                            </button>
                            <?php if (!empty($edit_item['id'])): ?>
                                <a href="admin.php?tab=calendar" class="btn btn-outline" style="width: 30%; display: flex; align-items: center; justify-content: center; text-decoration: none;">Annuleren</a>
                            <?php endif; ?>
                        </div>
                    </form>
                    
                    <!-- Table of existing items -->
                    <h4 style="color: var(--color-primary-dark); margin-bottom: 15px;">Huidige Geplande Activiteiten</h4>
                    <?php if (empty($calendar)): ?>
                        <div style="background-color: var(--color-bg-linen); border: 1px solid var(--color-border); padding: 20px; border-radius: var(--border-radius-md); text-align: center; color: var(--color-text-muted);">
                            Er zijn momenteel geen aankomende activiteiten gepland.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 15%;">Datum</th>
                                        <th style="width: 25%;">Titel</th>
                                        <th style="width: 20%;">Tijd & Locatie</th>
                                        <th style="width: 25%;">Beschrijving</th>
                                        <th style="width: 15%; text-align: center;">Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($calendar as $item): ?>
                                        <tr>
                                            <td style="font-weight: bold; color: var(--color-primary-dark);">
                                                <?php echo date('d-m-Y', strtotime($item['date'])); ?>
                                            </td>
                                            <td>
                                                <strong style="color: var(--color-text-dark);"><?php echo htmlspecialchars($item['title']); ?></strong>
                                            </td>
                                            <td style="font-size: 0.85rem; line-height: 1.4;">
                                                <strong>Tijd:</strong> <?php echo htmlspecialchars($item['time']); ?><br>
                                                <strong>Locatie:</strong> <?php echo htmlspecialchars($item['location']); ?>
                                            </td>
                                            <td style="font-size: 0.85rem; color: var(--color-text-muted); max-width: 250px; white-space: normal;">
                                                <?php echo htmlspecialchars($item['description']); ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <div style="display: flex; gap: 6px; justify-content: center;">
                                                    <a href="admin.php?tab=calendar&edit_cal=<?php echo $item['id']; ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" title="Bewerken">
                                                        Bewerk
                                                    </a>
                                                    <a href="admin.php?tab=calendar&action=delete_calendar&id=<?php echo $item['id']; ?>" data-confirm="Bent u zeker dat u deze activiteit wilt verwijderen?" class="btn btn-danger" style="padding: 4px 8px; font-size: 0.75rem;" title="Verwijderen">
                                                        Verwijder
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- PANEL 7: Activiteiten Inschrijvingen (Registrations) -->
                <?php 
                // Apply role filters
                $filtered_regs = $registrations;
                if (!$is_super) {
                    $filtered_regs = array_filter($registrations, function($r) use ($role) {
                        return $r['child_tak'] === $role;
                    });
                } else {
                    // Super admin filtering
                    $filter_tak = isset($_GET['filter_tak']) ? $_GET['filter_tak'] : 'all';
                    $filter_act = isset($_GET['filter_act']) ? $_GET['filter_act'] : 'all';
                    
                    if ($filter_tak !== 'all') {
                        $filtered_regs = array_filter($filtered_regs, function($r) use ($filter_tak) {
                            return $r['child_tak'] === $filter_tak;
                        });
                    }
                    if ($filter_act !== 'all') {
                        $filtered_regs = array_filter($filtered_regs, function($r) use ($filter_act) {
                            return $r['activity_type'] === $filter_act;
                        });
                    }
                }
                ?>
                <div class="admin-panel <?php echo $active_tab === 'registrations' ? 'active' : ''; ?>" data-tab="registrations">
                    <div class="admin-panel-header">
                        <h3 style="font-size: 1.5rem; color: var(--color-primary-dark);">
                            <?php echo $is_super ? "Inschrijvingen Weekend & Kamp (Alle takken)" : "Inschrijvingen Tak: " . $_SESSION['admin_role_name']; ?>
                        </h3>
                        <span style="font-size: 0.85rem; color: var(--color-text-muted);">
                            Totaal getoond: <?php echo count($filtered_regs); ?> inschrijvingen
                        </span>
                    </div>
                    
                    <?php if ($is_super): ?>
                        <!-- Super Admin Filters Panel -->
                        <div style="background-color: var(--color-bg-linen); border: 1px solid var(--color-border); padding: 15px 20px; border-radius: var(--border-radius-md); margin-bottom: 30px;">
                            <form action="admin.php" method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                                <input type="hidden" name="tab" value="registrations">
                                
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin: 0;">Filter op Tak:</label>
                                    <select name="filter_tak" class="form-control" style="padding: 6px 12px; font-size: 0.85rem; height: auto; min-width: 150px;">
                                        <option value="all" <?php echo $filter_tak === 'all' ? 'selected' : ''; ?>>Alle Takken</option>
                                        <option value="kapoenen" <?php echo $filter_tak === 'kapoenen' ? 'selected' : ''; ?>>Kapoenen</option>
                                        <option value="welpen" <?php echo $filter_tak === 'welpen' ? 'selected' : ''; ?>>Welpen</option>
                                        <option value="jonggivers" <?php echo $filter_tak === 'jonggivers' ? 'selected' : ''; ?>>Jonggivers</option>
                                        <option value="givers" <?php echo $filter_tak === 'givers' ? 'selected' : ''; ?>>Givers</option>
                                    </select>
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <label class="form-label" style="font-size: 0.85rem; font-weight: 600; margin: 0;">Filter op Activiteit:</label>
                                    <select name="filter_act" class="form-control" style="padding: 6px 12px; font-size: 0.85rem; height: auto; min-width: 150px;">
                                        <option value="all" <?php echo $filter_act === 'all' ? 'selected' : ''; ?>>Alle Types</option>
                                        <option value="takweekend" <?php echo $filter_act === 'takweekend' ? 'selected' : ''; ?>>Takweekend</option>
                                        <option value="groepsweekend" <?php echo $filter_act === 'groepsweekend' ? 'selected' : ''; ?>>Groepsweekend</option>
                                        <option value="kamp" <?php echo $filter_act === 'kamp' ? 'selected' : ''; ?>>Zomerkamp</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.85rem; height: auto;">
                                    Filteren
                                </button>
                                <a href="admin.php?tab=registrations" class="btn btn-outline" style="padding: 8px 16px; font-size: 0.85rem; height: auto; text-decoration: none; display: inline-flex; align-items: center;">
                                    Reset
                                </a>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($filtered_regs)): ?>
                        <div style="background-color: var(--color-bg-linen); border: 1px solid var(--color-border); padding: 30px; border-radius: var(--border-radius-md); text-align: center; color: var(--color-text-muted);">
                            Er zijn geen inschrijvingen gevonden voor de geselecteerde filters.
                        </div>
                    <?php else: 
                        // Group registrations dynamically by event/activity
                        $regs_by_event = [];
                        foreach ($filtered_regs as $reg) {
                            $event_title = $reg['activity_title'];
                            if (!isset($regs_by_event[$event_title])) {
                                $regs_by_event[$event_title] = [];
                            }
                            $regs_by_event[$event_title][] = $reg;
                        }
                        
                        foreach ($regs_by_event as $event_title => $event_regs):
                            $event_count = count($event_regs);
                    ?>
                        <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--color-border); padding: 24px; margin-bottom: 40px;" class="tak-card">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; border-bottom: 1px solid var(--color-border); padding-bottom: 16px;">
                                <div>
                                    <h4 style="font-size: 1.35rem; color: var(--color-primary-dark); font-family: 'Outfit', sans-serif; font-weight: 700; margin: 0;"><?php echo htmlspecialchars($event_title); ?></h4>
                                    <span style="font-size: 0.85rem; color: var(--color-text-muted);">
                                        Aantal inschrijvingen: <strong><?php echo $event_count; ?></strong>
                                    </span>
                                </div>
                                
                                <!-- CSV Export Button -->
                                <a href="admin.php?action=download_csv&event=<?php echo urlencode($event_title); ?>" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; font-size: 0.85rem; font-family: 'Outfit', sans-serif; font-weight: 600; text-decoration: none; border-radius: 30px; background-color: var(--color-success); border-color: var(--color-success);">
                                    <svg style="width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2;" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                    Download CSV
                                </a>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="admin-table" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Inschrijfdatum</th>
                                            <th>Kind (Tak)</th>
                                            <th>Ouder / Contact</th>
                                            <th>Betaling & Mededeling</th>
                                            <th style="text-align: center;">Acties</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($event_regs as $reg): 
                                            $reg_date = date('d-m-Y H:i', strtotime($reg['date']));
                                            $is_cancelled_req = isset($reg['cancellation_requested']) && $reg['cancellation_requested'] === true;
                                            
                                            if ($reg['status'] === 'paid') {
                                                $status_class = 'status-badge paid';
                                                $status_label = 'Betaald';
                                                $row_bg = '';
                                            } elseif ($reg['status'] === 'waiting_approval') {
                                                $status_class = 'status-badge pending';
                                                $status_label = 'Overgemaakt (Wacht op controle)';
                                                $row_bg = 'background-color: hsla(38, 80%, 50%, 0.04); border-left: 4px solid var(--color-warning);';
                                            } else {
                                                $status_class = 'status-badge pending';
                                                $status_label = 'Niet betaald';
                                                $row_bg = '';
                                            }
                                            
                                            if ($is_cancelled_req) {
                                                $row_style = 'background-color: hsla(4, 75%, 48%, 0.05); border-left: 4px solid var(--color-error);';
                                            } else {
                                                $row_style = $row_bg;
                                            }
                                        ?>
                                            <tr style="<?php echo $row_style; ?>">
                                                <td style="font-size: 0.8rem; color: var(--color-text-muted);"><?php echo $reg_date; ?></td>
                                                <td>
                                                    <strong style="color: var(--color-primary-dark); font-size: 1rem;"><?php echo htmlspecialchars($reg['child_name']); ?></strong><br>
                                                    <span class="status-badge" style="background-color: var(--color-bg-linen); color: var(--color-primary-dark); font-size: 0.7rem; margin-top: 4px;">
                                                        <?php echo ucfirst(htmlspecialchars($reg['child_tak'])); ?>
                                                    </span>
                                                </td>
                                                <td style="font-size: 0.85rem; line-height: 1.4;">
                                                    <strong>Ouder:</strong> <?php echo htmlspecialchars($reg['customer_name']); ?><br>
                                                    <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($reg['email']); ?>" style="color: var(--color-secondary);"><?php echo htmlspecialchars($reg['email']); ?></a><br>
                                                    <strong>Tel:</strong> <?php echo htmlspecialchars($reg['phone']); ?>
                                                    
                                                    <?php if (isset($reg['remarks']) && !empty($reg['remarks'])): ?>
                                                        <div style="margin-top: 8px; font-size: 0.8rem; background-color: hsla(38, 80%, 50%, 0.08); border-left: 3px solid var(--color-warning); padding: 6px 10px; border-radius: 4px; color: var(--color-text-dark); line-height: 1.3;">
                                                            <strong>Opmerking:</strong> <em>"<?php echo htmlspecialchars($reg['remarks']); ?>"</em>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong style="color: var(--color-secondary);">€<?php echo number_format($reg['price'], 2, ',', ''); ?></strong><br>
                                                    <span class="<?php echo $status_class; ?>" style="font-size: 0.75rem; margin-top: 4px; margin-bottom: 4px; display: inline-block;">
                                                        <?php echo $status_label; ?>
                                                    </span><br>
                                                    
                                                    <?php if ($is_cancelled_req): ?>
                                                        <span style="background-color: hsla(4, 75%, 48%, 0.1); border: 1px solid var(--color-error); color: var(--color-error); padding: 2px 6px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; display: inline-block; margin-bottom: 4px; text-transform: uppercase;">
                                                            ⚠️ Annulering Aangevraagd
                                                        </span><br>
                                                    <?php endif; ?>
                                                    
                                                    <span style="font-family: monospace; font-size: 0.8rem; background-color: var(--color-bg-linen); padding: 2px 6px; border-radius: 4px; display: inline-block; color: var(--color-text-dark); font-weight: bold; border: 1px solid var(--color-border);">
                                                        <?php echo htmlspecialchars($reg['communication']); ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: center;">
                                                    <div style="display: flex; flex-direction: column; gap: 6px; align-items: center; justify-content: center;">
                                                        <?php if ($reg['status'] !== 'paid'): ?>
                                                            <a href="admin.php?tab=registrations&action=update_registration&id=<?php echo $reg['id']; ?>&status=paid" class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.75rem; background-color: var(--color-success); width: 100%; text-align: center;">
                                                                Markeer Betaald
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="admin.php?tab=registrations&action=update_registration&id=<?php echo $reg['id']; ?>&status=pending" class="btn btn-outline" style="padding: 4px 10px; font-size: 0.75rem; width: 100%; text-align: center;">
                                                                Markeer Open
                                                            </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($is_cancelled_req): ?>
                                                            <a href="admin.php?tab=registrations&action=delete_registration&id=<?php echo $reg['id']; ?>" data-confirm="Bent u zeker dat u deze inschrijving wilt annuleren en definitief wilt verwijderen uit het systeem?" class="btn btn-danger" style="padding: 4px 10px; font-size: 0.75rem; width: 100%; text-align: center; font-weight: bold; background-color: var(--color-error);">
                                                                Annulering Goedkeuren
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="admin.php?tab=registrations&action=delete_registration&id=<?php echo $reg['id']; ?>" data-confirm="Bent u zeker dat u deze inschrijving definitief wilt verwijderen?" class="btn btn-danger" style="padding: 4px 10px; font-size: 0.75rem; width: 100%; text-align: center;">
                                                                Verwijder
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            
        </main>
        
    </div>
</section>

<script>
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

    // 3b. Show Confirm Modal Function
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

    // 4. Tab Switcher Logic
    window.switchTab = function(tab) {
        // Toggle menu active classes
        document.querySelectorAll('.admin-menu-btn').forEach(btn => {
            const href = btn.getAttribute('href');
            if (!href) return;
            const url = new URL(href, window.location.origin);
            if (url.searchParams.get('tab') === tab) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // Toggle panel active classes
        document.querySelectorAll('.admin-panel').forEach(panel => {
            if (panel.getAttribute('data-tab') === tab) {
                panel.classList.add('active');
            } else {
                panel.classList.remove('active');
            }
        });
    };

    // 5. Handle Back/Forward Navigation
    window.addEventListener('popstate', function(e) {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab') || 'orders';
        switchTab(tab);
    });

    // 6. Loading Overlay
    function toggleLoading(show) {
        let overlay = document.querySelector('.loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner"></div>';
            const wrapper = document.querySelector('#admin-portal-wrapper');
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

    // 7. Parse and Extract Alerts from HTML
    function checkAndShowAlerts(doc) {
        // Find success alert in returned HTML
        const successDiv = doc.querySelector('div[style*="var(--color-success)"]');
        if (successDiv) {
            const msg = successDiv.querySelector('span')?.textContent || successDiv.textContent.trim();
            showToast(msg, 'success');
            // Remove success alert from DOM to keep UI extremely clean
            successDiv.remove();
        }
        
        // Find error alert in returned HTML
        const errorDiv = doc.querySelector('div[style*="var(--color-error)"]');
        if (errorDiv) {
            const msg = errorDiv.querySelector('span')?.textContent || errorDiv.textContent.trim();
            showToast(msg, 'error');
            errorDiv.remove();
        }
    }

    // 8. Event Delegation - Clicks
    document.addEventListener('click', function(e) {
        // A. Tab buttons
        const menuBtn = e.target.closest('.admin-menu-btn');
        if (menuBtn && !menuBtn.style.color.includes('var(--color-error)') && !menuBtn.href.includes('logout')) {
            const href = menuBtn.getAttribute('href');
            if (href && href.includes('tab=')) {
                e.preventDefault();
                const url = new URL(href, window.location.origin);
                const tab = url.searchParams.get('tab');
                switchTab(tab);
                history.pushState({ tab: tab }, '', href);
                return;
            }
        }

        // B. Action buttons (GET requests)
        const actionLink = e.target.closest('a');
        if (actionLink && !e.defaultPrevented) {
            const href = actionLink.getAttribute('href');
            if (href && href.includes('admin.php') && href.includes('action=')) {
                const url = new URL(href, window.location.origin);
                const action = url.searchParams.get('action');
                
                // Exclude download_csv and logout from AJAX
                if (action && action !== 'download_csv' && action !== 'logout') {
                    e.preventDefault();
                    
                    const activeTab = new URLSearchParams(window.location.search).get('tab') || 'orders';
                    url.searchParams.set('tab', activeTab);
                    
                    const performAction = () => {
                        toggleLoading(true);
                        fetch(url.toString())
                            .then(response => response.text())
                            .then(html => {
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(html, 'text/html');
                                const newWrapper = doc.querySelector('#admin-portal-wrapper');
                                const currentWrapper = document.querySelector('#admin-portal-wrapper');
                                
                                if (newWrapper && currentWrapper) {
                                    checkAndShowAlerts(doc);
                                    currentWrapper.innerHTML = newWrapper.innerHTML;
                                    // Keep the current tab active
                                    switchTab(activeTab);
                                }
                            })
                            .catch(err => {
                                console.error('AJAX Action failed:', err);
                                showToast('Actie mislukt. Probeer het opnieuw.', 'error');
                            })
                            .finally(() => {
                                toggleLoading(false);
                            });
                    };
                    
                    const confirmMsg = actionLink.getAttribute('data-confirm');
                    if (confirmMsg) {
                        showConfirmModal(confirmMsg, performAction);
                    } else {
                        performAction();
                    }
                }
            }
        }
    });

    // 9. Event Delegation - Form Submissions
    document.addEventListener('submit', function(e) {
        const form = e.target.closest('form');
        if (form) {
            const actionAttr = form.getAttribute('action') || '';
            if (actionAttr.includes('admin.php') || actionAttr === '') {
                e.preventDefault();
                
                toggleLoading(true);
                
                const formData = new FormData(form);
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
                
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'Verwerken...';
                }
                
                // Form action URL
                const actionUrl = new URL(actionAttr || window.location.href, window.location.origin);
                
                // Append active tab to POST action URL so server renders the correct panel as active
                const activeTab = new URLSearchParams(window.location.search).get('tab') || 'orders';
                actionUrl.searchParams.set('tab', activeTab);
                
                // If it is a GET form (like registrations search filters), we submit via GET fetch
                const isGet = form.getAttribute('method')?.toUpperCase() === 'GET';
                let fetchPromise;
                
                if (isGet) {
                    const searchParams = new URLSearchParams(formData);
                    actionUrl.search = searchParams.toString();
                    fetchPromise = fetch(actionUrl.toString());
                } else {
                    fetchPromise = fetch(actionUrl.toString(), {
                        method: 'POST',
                        body: formData
                    });
                }
                
                fetchPromise
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newWrapper = doc.querySelector('#admin-portal-wrapper');
                        const currentWrapper = document.querySelector('#admin-portal-wrapper');
                        
                        if (newWrapper && currentWrapper) {
                            checkAndShowAlerts(doc);
                            currentWrapper.innerHTML = newWrapper.innerHTML;
                            
                            // Re-apply correct active tab
                            const activeSidebarBtn = doc.querySelector('.admin-menu-btn.active');
                            let targetTab = activeTab;
                            if (activeSidebarBtn) {
                                const newBtnUrl = new URL(activeSidebarBtn.getAttribute('href'), window.location.origin);
                                targetTab = newBtnUrl.searchParams.get('tab') || activeTab;
                            }
                            switchTab(targetTab);
                            
                            // Update browser URL
                            const newSearch = new URLSearchParams(window.location.search);
                            newSearch.set('tab', targetTab);
                            history.pushState({ tab: targetTab }, '', `admin.php?${newSearch.toString()}`);
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
