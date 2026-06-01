<?php
/**
 * Kriko Echo's Archive - Echos View
 * Scouts Kriko-M Web Platform
 */

$page_title = "Kriko Echo's";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

// Fetch all uploaded planners (must be approved)
$all_echos = array_filter(read_db('echos'), function($echo) {
    return isset($echo['approved']) && $echo['approved'] === true;
});

// Load scouts divisions data dynamically from the flat-file database
$settings = read_db('settings');
$takken_data = isset($settings['takken']) ? $settings['takken'] : [];
$takken_keys = ['kapoenen', 'welpen', 'jonggivers', 'givers'];

// Group approved echos for each branch
$echos_by_tak = [];
foreach ($takken_keys as $t_key) {
    $echos_by_tak[$t_key] = [];
}

foreach ($all_echos as $echo) {
    $t_key = $echo['tak'];
    if (in_array($t_key, $takken_keys)) {
        $echos_by_tak[$t_key][] = $echo;
    }
}

// Sort each branch's echos chronologically descending
foreach ($takken_keys as $t_key) {
    usort($echos_by_tak[$t_key], function($a, $b) {
        $valA = ((int)$a['year'] * 100) + (int)$a['month'];
        $valB = ((int)$b['year'] * 100) + (int)$b['month'];
        if ($valA === $valB) {
            return strtotime($b['uploaded_at']) - strtotime($a['uploaded_at']);
        }
        return $valB - $valA;
    });
}

// Dutch Month names helper
function get_dutch_month($month_num) {
    $months_nl = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maart', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Augustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'December'
    ];
    return isset($months_nl[(int)$month_num]) ? $months_nl[(int)$month_num] : 'Onbekend';
}
?>

<!-- 1. Header Banner -->
<section class="tak-hero leiding">
    <div class="container">
        <span style="color: var(--color-accent); font-family: 'Outfit', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">Maandelijkse kalender</span>
        <h2 class="tak-hero-title">Kriko Echo's</h2>
        <p style="font-size: 1.2rem; color: hsla(0, 0%, 100%, 0.9); margin-top: 8px;">Blijf op de hoogte van alle scoutsvergaderingen, weekends en evenementen!</p>
    </div>
</section>

<!-- 2. Main Page Grid of 4 Boxes -->
<section class="section container">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 30px; margin-top: 20px;">
        <?php foreach ($takken_keys as $tak_key): 
            $tak = isset($takken_data[$tak_key]) ? $takken_data[$tak_key] : ['name' => ucfirst($tak_key), 'age_range' => ''];
            // Cap to exactly 2 active echoes on frontend just in case
            $approved_tak_echos = array_slice($echos_by_tak[$tak_key], 0, 2);
        ?>
            <!-- Tak Card -->
            <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--color-border); overflow: hidden; display: flex; flex-direction: column; transition: var(--transition-normal);" class="tak-card">
                <div class="tak-card-header <?php echo $tak_key; ?>"></div>
                <div style="padding: 28px; display: flex; flex-direction: column; flex-grow: 1; align-items: stretch; text-align: center;">
                    <span class="tak-card-tag"><?php echo htmlspecialchars($tak['age_range']); ?></span>
                    <h3 style="font-size: 1.6rem; color: var(--color-primary-dark); margin-bottom: 20px; font-family: 'Outfit', sans-serif; font-weight: 700;"><?php echo htmlspecialchars($tak['name']); ?></h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px; flex-grow: 1; justify-content: center;">
                        <?php if (empty($approved_tak_echos)): ?>
                            <div style="padding: 20px; background-color: var(--color-bg-linen); border-radius: var(--border-radius-sm); border: 1px dashed var(--color-border);">
                                <p style="font-size: 0.9rem; color: var(--color-text-muted); font-style: italic; margin: 0;">Geen actieve planningsbrieven</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($approved_tak_echos as $echo): 
                                $month_name = get_dutch_month($echo['month']);
                                $btn_label = htmlspecialchars($echo['title']);
                            ?>
                                <a href="uploads/echos/<?php echo urlencode($echo['file_name']); ?>" download class="btn btn-secondary" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 18px; border-radius: var(--border-radius-md); font-family: 'Outfit', sans-serif; font-weight: 600; transition: var(--transition-fast); text-decoration: none; width: 100%;">
                                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                    <span><?php echo $btn_label; ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
