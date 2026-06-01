<?php
/**
 * Divisions Details - Takken View
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/includes/db.php';

// Load scouts divisions data dynamically from the flat-file database
$settings = read_db('settings');
$takken_data = isset($settings['takken']) ? $settings['takken'] : [];

// Determine selected division or show directory
$selected_tak = isset($_GET['tak']) && array_key_exists($_GET['tak'], $takken_data) ? $_GET['tak'] : null;

$page_title = $selected_tak ? $takken_data[$selected_tak]['name'] : "Onze Takken";
require_once __DIR__ . '/includes/header.php';

// If a division is selected, find the latest uploaded planning PDF for this division (must be approved)
$latest_echo = null;
if ($selected_tak) {
    $echos = read_db('echos');
    $tak_echos = array_filter($echos, function($echo) use ($selected_tak) {
        return $echo['tak'] === $selected_tak && isset($echo['approved']) && $echo['approved'] === true;
    });
    
    if (!empty($tak_echos)) {
        // Sort by year and month descending to get the latest
        usort($tak_echos, function($a, $b) {
            $valA = ($a['year'] * 100) + $a['month'];
            $valB = ($b['year'] * 100) + $b['month'];
            return $valB - $valA;
        });
        $latest_echo = reset($tak_echos);
    }
}
?>

<!-- 1. Takken Page Hero -->
<?php if ($selected_tak): 
    $tak = $takken_data[$selected_tak];
?>
    <section class="tak-hero <?php echo $tak['class']; ?>">
        <div class="container">
            <span style="color: var(--color-accent); font-family: 'Outfit', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;"><?php echo $tak['age_range']; ?></span>
            <h2 class="tak-hero-title"><?php echo $tak['name']; ?></h2>
            <p style="font-size: 1.2rem; color: hsla(0, 0%, 100%, 0.9); margin-top: 8px; font-family: 'Outfit', sans-serif; font-weight: 500;"><?php echo $tak['school_year']; ?></p>
        </div>
    </section>

    <!-- 2. Tak Specific Detail Layout -->
    <section class="section container">
        <div class="tak-layout">
            <!-- Left Main Column -->
            <div style="display: flex; flex-direction: column; gap: 40px;">
                <!-- Description -->
                <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-md); padding: 40px; border: 1px solid var(--color-border);">
                    <h3 style="font-size: 1.8rem; margin-bottom: 18px; color: var(--color-primary-dark);">Wie zijn we?</h3>
                    <p style="font-size: 1.05rem; line-height: 1.7; color: var(--color-text-dark); margin-bottom: 20px;"><?php echo $tak['description']; ?></p>
                    
                    <h4 style="font-size: 1.3rem; margin-top: 30px; margin-bottom: 12px; color: var(--color-primary-dark);">Programma & Vergaderingen</h4>
                    <p style="color: var(--color-text-muted);">Elke zondagochtend verzamelen we aan onze scoutslokalen op het VP-plein van <strong>9:45 tot 12:30</strong> stipt. De vergadering is gevuld met actieve bosspelen, sjorringen, knutselen en sport. Vergeet niet de maandelijkse planner (Kriko Echo) te downloaden om te zien of we een speciale activiteit hebben!</p>
                </div>

                <!-- Leaders team grid -->
                <div class="leaders-section">
                    <h3 style="font-size: 1.6rem; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 12px; color: var(--color-primary-dark);">De Leiding</h3>
                    <p style="color: var(--color-text-muted); font-size: 0.95rem; margin-top: 8px;">Dit team staat elke zondag klaar om er een onvergetelijke vergadering van te maken. Heb je een vraag? Spreek ons gerust aan na de vergadering of stuur een mailtje of sms.</p>
                    
                    <div class="leaders-grid">
                        <?php foreach ($tak['leaders'] as $leader): 
                            // Extract initials
                            $parts = explode(' ', $leader['name']);
                            $initials = '';
                            if (count($parts) >= 2) {
                                $initials = substr($parts[0], 0, 1) . substr(end($parts), 0, 1);
                            } else {
                                $initials = substr($leader['name'], 0, 2);
                            }
                        ?>
                            <div class="leader-card">
                                <div class="leader-avatar"><?php echo strtoupper($initials); ?></div>
                                <h4><?php echo htmlspecialchars($leader['name']); ?></h4>
                                <?php if (!empty($leader['totem'])): ?>
                                    <p class="leader-totem">"<?php echo htmlspecialchars($leader['totem']); ?>"</p>
                                <?php endif; ?>
                                <p style="font-weight: 600; font-size: 0.85rem; color: var(--color-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;"><?php echo htmlspecialchars($leader['role']); ?></p>
                                <?php if (!empty($leader['phone'])): ?>
                                    <a href="tel:<?php echo str_replace([' ', '.'], '', $leader['phone']); ?>" class="leader-phone">
                                        <svg style="width: 12px; height: 12px; fill: none; stroke: currentColor;" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        <?php echo htmlspecialchars($leader['phone']); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar Column -->
            <div>
                <!-- 1. Echo planning card -->
                <div class="side-card" style="background: linear-gradient(135deg, var(--color-primary-light), var(--color-primary)); color: var(--color-bg-white); border: none;">
                    <h3 style="color: var(--color-accent); border-bottom: 1px dashed rgba(255,255,255,0.2);">Kriko Echo</h3>
                    
                    <?php if ($latest_echo): ?>
                        <p style="margin-bottom: 20px; font-size: 0.95rem; color: hsla(0, 0%, 100%, 0.9);">De nieuwste kalender voor de <strong><?php echo $tak['name']; ?></strong> is beschikbaar!</p>
                        <div style="background-color: rgba(255,255,255,0.1); border-radius: var(--border-radius-md); padding: 16px; margin-bottom: 20px; text-align: center; border: 1px dashed rgba(255,255,255,0.3);">
                            <span style="font-weight: 700; display: block; font-size: 1.05rem; color: var(--color-accent);"><?php echo htmlspecialchars($latest_echo['title']); ?></span>
                            <span style="font-size: 0.8rem; color: hsla(0, 0%, 100%, 0.7); display: block; margin-top: 4px;">Geüpload op: <?php echo date('d-m-Y', strtotime($latest_echo['uploaded_at'])); ?></span>
                        </div>
                        <a href="uploads/echos/<?php echo urlencode($latest_echo['file_name']); ?>" download class="btn btn-secondary" style="width: 100%; background-color: var(--color-secondary); color: var(--color-bg-white);">
                            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Download PDF kalender
                        </a>
                    <?php else: ?>
                        <p style="margin-bottom: 20px; font-size: 0.95rem; color: hsla(0, 0%, 100%, 0.8);">Er is momenteel nog geen planning geüpload voor deze maand.</p>
                        <a href="echos.php" class="btn btn-outline" style="width: 100%; color: var(--color-bg-white); border-color: var(--color-bg-white);">Bekijk archief</a>
                    <?php endif; ?>
                </div>

                <!-- 2. Contact details card -->
                <div class="side-card">
                    <h3>Contact Leiding</h3>
                    <p style="font-size: 0.9rem; color: var(--color-text-muted); margin-bottom: 16px;">Vragen over een activiteit of weekend? Stuur een e-mail naar de takleiding:</p>
                    <a href="mailto:<?php echo htmlspecialchars($tak['email']); ?>" class="btn btn-outline" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <?php echo htmlspecialchars($tak['email']); ?>
                    </a>
                </div>

                <!-- 3. Uniform instructions card -->
                <div class="side-card">
                    <h3>Uniform regels</h3>
                    <p style="font-size: 0.9rem; line-height: 1.5; color: var(--color-text-dark);"><?php echo $tak['uniform']; ?></p>
                    <a href="ouderportaal.php?show_webshop=1" class="tak-card-link" style="margin-top: 14px; display: inline-flex;">Bestel onze groepsdas &raquo;</a>
                </div>
            </div>
        </div>
    </section>

<?php else: ?>
    <!-- 3. Show All Divisions Directory -->
    <section class="section container">
        <div class="section-header">
            <h2>Onze Scouts Groepen</h2>
            <div class="title-line"></div>
            <p>Klik op een van onze takken hieronder om gedetailleerde informatie, leidinggegevens en de laatste planningsbrieven te bekijken.</p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 30px; max-width: 800px; margin: 0 auto;">
            <?php foreach ($takken_data as $key => $tak): ?>
                <div class="tak-directory-card">
                    <div class="tak-directory-card-header <?php echo $tak['class']; ?>"></div>
                    <div class="tak-directory-card-body">
                        <div>
                            <span style="font-size: 0.8rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase;"><?php echo $tak['age_range']; ?></span>
                            <h3 style="font-size: 1.6rem; margin-top: 4px; margin-bottom: 8px;"><?php echo $tak['name']; ?></h3>
                            <p style="color: var(--color-text-muted); max-width: 500px; font-size: 0.95rem;"><?php echo htmlspecialchars(substr($tak['description'], 0, 140)) . '...'; ?></p>
                        </div>
                        <a href="takken.php?tak=<?php echo $key; ?>" class="btn btn-secondary">Bekijk deze tak</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
