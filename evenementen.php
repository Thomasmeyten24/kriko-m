<?php
/**
 * Dynamic division activities and event registrations page.
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/parent_auth.php';

// Enforce parent login to access event registrations
if (!is_parent_logged_in()) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['parent_error'] = 'U moet ingelogd zijn als ouder om uw kind in te kunnen schrijven voor weekends of kampen.';
    header('Location: ouderportaal.php');
    exit;
}

$settings = read_db('settings');
$takken_data = isset($settings['takken']) ? $settings['takken'] : [];

// Generate structured Belgian communication Modulo 97
function generate_event_communication() {
    $first_ten = rand(1000000000, 9999999999);
    $modulo = $first_ten % 97;
    $check = ($modulo === 0) ? 97 : $modulo;
    $check_str = str_pad($check, 2, '0', STR_PAD_LEFT);
    $full_twelve = str_pad($first_ten . $check_str, 12, '0', STR_PAD_LEFT);
    
    $part1 = substr($full_twelve, 0, 3);
    $part2 = substr($full_twelve, 3, 4);
    $part3 = substr($full_twelve, 7, 5);
    
    return "+++{$part1}/{$part2}/{$part3}+++";
}

$error = '';
$success = false;
$modal_activity_title = '';
$modal_activity_price = 0.0;

// Handle form submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_parent_logged_in()) {
        $error = 'U moet ingelogd zijn als ouder om uw kind in te kunnen schrijven.';
    } else {
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
                
                // Pre-populate modal variables for auto-reopen in case of subsequent errors
                $modal_activity_title = $act['title'];
                $modal_activity_price = (float)$act['price'];
                
                // Validate date constraints
                if ($today < $act['reg_open']) {
                    $error = 'De inschrijvingen voor dit evenement zijn nog niet geopend.';
                } elseif ($today > $act['reg_close']) {
                    $error = 'De inschrijvingsperiode voor dit evenement is verstreken.';
                } else {
                    // Check if child is already registered for this specific activity type
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
                        // Construct registration registry object
                        $comm = generate_event_communication();
                        $reg_id = 'reg_' . uniqid();
                        
                        $registration = [
                            'id' => $reg_id,
                            'date' => date('Y-m-d H:i:s'),
                            'status' => 'pending', // pending, paid
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
                        
                        // Set session cache to display success invoice receipt
                        $_SESSION['last_registration'] = $registration;
                        
                        header('Location: registration-success.php');
                        exit;
                    }
                }
            }
        }
    }
}

// Determine selected division or show directory grid
$selected_tak = isset($_GET['tak']) && array_key_exists($_GET['tak'], $takken_data) ? $_GET['tak'] : null;

$page_title = $selected_tak ? "Inschrijven - " . $takken_data[$selected_tak]['name'] : "Inschrijven Weekend/Kamp";
require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Page Header -->
<section class="tak-hero leiding">
    <div class="container">
        <span style="color: var(--color-accent); font-family: 'Outfit', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">Evenementen Kalender</span>
        <h2 class="tak-hero-title">Inschrijven Weekend & Kamp</h2>
        <p style="font-size: 1.2rem; color: hsla(0, 0%, 100%, 0.9); margin-top: 8px;">Meld uw kind aan voor takweekends, groepsweekends of het grote zomerkamp!</p>
    </div>
</section>

<!-- 2. Main Content Grid -->
<section class="section container">
    
    <?php if (!empty($error)): ?>
        <div style="background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 30px; font-weight: 600;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (!$selected_tak): ?>
        
        <!-- Step 1: Select Division Grid -->
        <div class="section-header">
            <h2>Kies de tak van uw kind</h2>
            <div class="title-line"></div>
            <p>Elke scoutsafdeling heeft haar eigen geplande weekends, kampdatums en specifieke lidgelden. Selecteer hieronder de tak van uw kind om de kalender en boekingsopties te bekijken.</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 30px; max-width: 1000px; margin: 40px auto 0;">
            <?php foreach ($takken_data as $key => $tak): ?>
                <a href="evenementen.php?tak=<?php echo $key; ?>" class="tak-card" style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--color-border); overflow: hidden; display: flex; flex-direction: column; text-decoration: none; transition: var(--transition-normal);">
                    <div class="tak-card-header <?php echo $tak['class']; ?>" style="height: 12px; width: 100%;"></div>
                    <div style="padding: 30px; text-align: center; display: flex; flex-direction: column; flex-grow: 1;">
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase;"><?php echo $tak['age_range']; ?></span>
                        <h3 style="font-size: 1.5rem; margin-top: 6px; margin-bottom: 12px; color: var(--color-primary-dark);"><?php echo $tak['name']; ?></h3>
                        <p style="color: var(--color-text-muted); font-size: 0.85rem; line-height: 1.5; margin-bottom: 20px;"><?php echo htmlspecialchars($tak['school_year']); ?></p>
                        <span class="btn btn-secondary" style="margin-top: auto; width: 100%; padding: 8px 16px; font-size: 0.85rem;">Activiteiten bekijken &raquo;</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

    <?php else: 
        $tak = $takken_data[$selected_tak];
        $activities = isset($tak['activities']) ? $tak['activities'] : [];
        $active_activities = array_filter($activities, function($a) { return $a['active']; });
    ?>
        
        <!-- Step 2: Show Division Activities -->
        <div style="margin-bottom: 30px;">
            <a href="evenementen.php" class="btn btn-outline" style="padding: 6px 14px; font-size: 0.85rem; border-radius: 30px; display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                &larr; Andere tak kiezen
            </a>
        </div>

        <div class="section-header" style="text-align: left; margin-bottom: 40px;">
            <h2 style="font-size: 2rem; color: var(--color-primary-dark); display: flex; align-items: center; gap: 10px;">
                <span class="status-badge" style="background-color: var(--color-primary-light); color: var(--color-bg-white); font-size: 1.1rem; padding: 4px 12px; border-radius: 8px;"><?php echo $tak['name']; ?></span>
                Geplande Activiteiten
            </h2>
            <p style="margin-top: 8px; max-width: 100%;">Hieronder vindt u de weekends en kampen voor de <strong><?php echo $tak['name']; ?></strong>. U kunt uw kind direct aanmelden voor open inschrijvingen.</p>
        </div>



        <?php if (empty($active_activities)): ?>
            <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); border: 1px solid var(--color-border); padding: 40px; text-align: center;">
                <p style="color: var(--color-text-muted); font-size: 1rem;">Er zijn momenteel geen activiteiten actief voor de <?php echo $tak['name']; ?>. Vraag ernaar bij de leiding!</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: 1fr; gap: 30px; max-width: 900px; margin: 0 auto;">
                <?php foreach ($active_activities as $type_key => $act): 
                    $today = date('Y-m-d');
                    $price_formatted = number_format($act['price'], 2, ',', '');
                    
                    // State calculation
                    $state = 'open';
                    $state_label = 'Open voor inschrijving';
                    $badge_class = 'status-badge paid'; // green
                    
                    if ($today < $act['reg_open']) {
                        $state = 'upcoming';
                        $state_label = 'Open vanaf ' . date('d-m-Y', strtotime($act['reg_open']));
                        $badge_class = 'status-badge pending'; // amber
                    } elseif ($today > $act['reg_close']) {
                        $state = 'closed';
                        $state_label = 'Inschrijvingen gesloten';
                        $badge_class = 'status-badge completed'; // grey/muted
                    }
                ?>
                    <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--color-border); overflow: hidden; display: flex; flex-direction: column; transition: var(--transition-normal);" class="tak-card">
                        <div style="padding: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                            <div style="flex-grow: 1; min-width: 250px;">
                                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 8px;">
                                    <span class="<?php echo $badge_class; ?>" style="font-size: 0.75rem; padding: 2px 8px; font-weight: 700;"><?php echo $state_label; ?></span>
                                    <span style="font-size: 0.8rem; color: var(--color-text-muted);">Deadline: <?php echo date('d-m-Y', strtotime($act['reg_close'])); ?></span>
                                </div>
                                <h3 style="font-size: 1.5rem; color: var(--color-primary-dark); margin-bottom: 6px;"><?php echo htmlspecialchars($act['title']); ?></h3>
                                <p style="color: var(--color-text-dark); font-size: 0.95rem; display: flex; align-items: center; gap: 6px;">
                                    <svg style="width: 16px; height: 16px; color: var(--color-secondary);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <strong>Datums:</strong> <?php echo htmlspecialchars($act['dates']); ?>
                                </p>
                            </div>
                            
                            <div style="text-align: right; min-width: 150px; display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                <span style="font-size: 0.85rem; color: var(--color-text-muted);">Deelnameprijs:</span>
                                <strong style="font-size: 1.6rem; color: var(--color-secondary);">€<?php echo $price_formatted; ?></strong>
                                
                                <?php if ($state === 'open'): ?>
                                    <button onclick="openRegisterForm('<?php echo $type_key; ?>', '<?php echo htmlspecialchars($act['title']); ?>', <?php echo $act['price']; ?>)" class="btn btn-secondary" style="padding: 10px 20px; font-size: 0.9rem; margin-top: 6px;">
                                        Nu Inschrijven
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline" style="padding: 10px 20px; font-size: 0.9rem; margin-top: 6px;" disabled>
                                        Niet Beschikbaar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 3. Enrollment Form Drawer/Modal Container (Hidden by default) -->
            <div id="registration-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: flex-start; padding: 20px; backdrop-filter: blur(4px); overflow-y: auto;">
                <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-lg); width: 100%; max-width: 550px; overflow: hidden; border: 1px solid var(--color-border); position: relative; animation: modalReveal 0.3s ease; margin: 40px auto;">
                    
                    <!-- Modal Header -->
                    <div style="background-color: var(--color-primary-dark); color: var(--color-bg-white); padding: 20px 24px; position: relative;">
                        <h3 style="font-size: 1.3rem; margin: 0; color: var(--color-accent);" id="modal-event-title">Evenement Inschrijven</h3>
                        <span style="font-size: 0.8rem; color: hsla(0,0%,100%,0.8); display: block; margin-top: 4px;">Inschrijving voor Tak: <?php echo $tak['name']; ?></span>
                        <button onclick="closeRegisterForm()" style="position: absolute; top: 20px; right: 24px; background: none; border: none; color: var(--color-bg-white); font-size: 1.8rem; line-height: 0.5; cursor: pointer; opacity: 0.7; font-weight: 100;">&times;</button>
                    </div>

                    <!-- Modal Body Form -->
                    <form action="evenementen.php?tak=<?php echo $selected_tak; ?>" method="POST" style="padding: 24px;">
                        <input type="hidden" name="child_tak" value="<?php echo htmlspecialchars($selected_tak); ?>">
                        <input type="hidden" name="activity_type" id="modal-activity-type">

                        <div style="background-color: var(--color-bg-linen); border: 1px solid var(--color-border); border-radius: var(--border-radius-md); padding: 12px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.9rem; font-weight: 600; color: var(--color-text-dark);">Totaal te betalen:</span>
                            <strong style="font-size: 1.25rem; color: var(--color-secondary);" id="modal-event-price">€0,00</strong>
                        </div>

                        <?php 
                        $parent_children = [];
                        $current_parent = get_logged_in_parent();
                        if ($current_parent && isset($current_parent['children'])) {
                            foreach ($current_parent['children'] as $child) {
                                if ($child['tak'] === $selected_tak) {
                                    $parent_children[] = $child;
                                }
                            }
                        }
                        
                        if (empty($parent_children)): 
                        ?>
                            <div style="background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 15px; border-radius: var(--border-radius-md); font-size: 0.85rem; line-height: 1.4; margin-bottom: 20px;">
                                <strong>Geen geschikt kind gevonden:</strong> U heeft in uw ouderaccount geen kinderen toegevoegd die ingedeeld zijn bij de tak <strong><?php echo ucfirst($tak['name']); ?></strong>.<br><br>
                                <a href="ouderportaal.php" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 12px; text-decoration: none; display: inline-block;">+ Kind toevoegen in Ouderportaal &rarr;</a>
                            </div>
                            <div style="display: flex; gap: 12px; margin-top: 10px;">
                                <button type="button" onclick="closeRegisterForm()" class="btn btn-outline" style="width: 100%;">Sluiten</button>
                            </div>
                        <?php else: ?>
                            <!-- Child selector dropdown -->
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label class="form-label" for="child_id">Kies uw kind (Lid):</label>
                                <select id="child_id" name="child_id" class="form-control" required style="text-align-last: center;">
                                    <?php foreach ($parent_children as $child): ?>
                                        <option value="<?php echo htmlspecialchars($child['id']); ?>">
                                            <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?> (geb. <?php echo date('d-m-Y', strtotime($child['dob'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Parent name -->
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label class="form-label" for="parent_name">Volledige Naam Ouder / Voogd:</label>
                                <input type="text" id="parent_name" name="parent_name" class="form-control" placeholder="Voornaam + Achternaam" value="<?php echo htmlspecialchars($current_parent['first_name'] . ' ' . $current_parent['last_name']); ?>" required>
                            </div>

                            <div class="form-row" style="margin-bottom: 16px;">
                                <!-- Email -->
                                <div class="form-group">
                                    <label class="form-label" for="email">E-mailadres:</label>
                                    <input type="email" id="email" name="email" class="form-control" placeholder="ouder@domein.be" value="<?php echo htmlspecialchars($current_parent['email']); ?>" required>
                                </div>
                                
                                <!-- Phone -->
                                <div class="form-group">
                                    <label class="form-label" for="phone">Telefoonnummer:</label>
                                    <input type="tel" id="phone" name="phone" class="form-control" placeholder="0470 00 00 00" value="<?php echo htmlspecialchars($current_parent['phone']); ?>" required>
                                </div>
                            </div>

                            <!-- Special Remarks -->
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label class="form-label" for="remarks">Speciale opmerkingen (bv. kind sluit een dag later aan, dieetwensen, allergieën):</label>
                                <textarea id="remarks" name="remarks" class="form-control" rows="3" placeholder="Typ hier eventuele opmerkingen of opmerkingen voor de leiding..."></textarea>
                            </div>

                            <div style="background-color: var(--color-bg-linen); padding: 12px; border-radius: var(--border-radius-sm); border: 1px dashed var(--color-border); margin-bottom: 20px; font-size: 0.75rem; color: var(--color-text-muted); line-height: 1.4;">
                                * Na het indienen ontvangt u de unieke betalingsgegevens en Belgische gestructureerde mededeling. Gelieve hiermee de betaling handmatig uit te voeren via uw bankapp.
                            </div>

                            <div style="display: flex; gap: 12px; margin-top: 10px;">
                                <button type="button" onclick="closeRegisterForm()" class="btn btn-outline" style="width: 40%;">Annuleren</button>
                                <button type="submit" class="btn btn-secondary" style="width: 60%;">Inschrijven & Betalen</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Dynamic JS controllers for Modal drawer -->
            <script>
                function openRegisterForm(type, title, price) {
                    const overlay = document.getElementById('registration-overlay');
                    const inputType = document.getElementById('modal-activity-type');
                    const textTitle = document.getElementById('modal-event-title');
                    const textPrice = document.getElementById('modal-event-price');

                    if (overlay && inputType && textTitle && textPrice) {
                        inputType.value = type;
                        textTitle.textContent = "Inschrijven voor " + title;
                        textPrice.textContent = "€" + price.toFixed(2).replace('.', ',');
                        
                        overlay.style.display = 'flex';
                        document.body.style.overflow = 'hidden'; // block background scroll
                    }
                }

                function closeRegisterForm() {
                    const overlay = document.getElementById('registration-overlay');
                    if (overlay) {
                        overlay.style.display = 'none';
                        document.body.style.overflow = 'auto'; // restore background scroll
                    }
                }

                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error) && !empty($modal_activity_title)): ?>
                document.addEventListener("DOMContentLoaded", function() {
                    openRegisterForm('<?php echo htmlspecialchars($activity_type); ?>', '<?php echo htmlspecialchars($modal_activity_title); ?>', <?php echo (float)$modal_activity_price; ?>);
                    
                    // Restore user entries in inputs
                    const childSelect = document.getElementById('child_id');
                    if (childSelect) childSelect.value = '<?php echo htmlspecialchars($child_id); ?>';
                    
                    const parentNameInput = document.getElementById('parent_name');
                    if (parentNameInput) parentNameInput.value = '<?php echo htmlspecialchars($parent_name); ?>';
                    
                    const emailInput = document.getElementById('email');
                    if (emailInput) emailInput.value = '<?php echo htmlspecialchars($email); ?>';
                    
                    const phoneInput = document.getElementById('phone');
                    if (phoneInput) phoneInput.value = '<?php echo htmlspecialchars($phone); ?>';
                    
                    const remarksInput = document.getElementById('remarks');
                    if (remarksInput) remarksInput.value = '<?php echo htmlspecialchars(addslashes($remarks)); ?>';
                });
                <?php endif; ?>
            </script>
        <?php endif; ?>

    <?php endif; ?>

</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
