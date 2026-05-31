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

// Sorting chronologically descending
usort($all_echos, function($a, $b) {
    $valA = ($a['year'] * 100) + $a['month'];
    $valB = ($b['year'] * 100) + $b['month'];
    return $valB - $valA;
});

// Check if filter is set
$filter_tak = isset($_GET['tak']) && in_array($_GET['tak'], ['kapoenen', 'welpen', 'jonggivers', 'givers']) ? $_GET['tak'] : 'all';

// Apply filter
$filtered_echos = $all_echos;
if ($filter_tak !== 'all') {
    $filtered_echos = array_filter($all_echos, function($echo) use ($filter_tak) {
        return $echo['tak'] === $filter_tak;
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

<!-- 2. Main Page Grid & Filters -->
<section class="section container">
    <div style="display: flex; flex-direction: column; gap: 40px;">
        
        <!-- Filter Controls Bar -->
        <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-md); box-shadow: var(--shadow-sm); padding: 20px; border: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div style="font-weight: 700; color: var(--color-primary-dark); font-family: 'Outfit', sans-serif;">Filter per tak:</div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="echos.php?tak=all" class="btn <?php echo $filter_tak === 'all' ? 'btn-secondary' : 'btn-outline'; ?>" style="padding: 6px 16px; font-size: 0.9rem; border-radius: 30px;">Alles</a>
                <a href="echos.php?tak=kapoenen" class="btn <?php echo $filter_tak === 'kapoenen' ? 'btn-secondary' : 'btn-outline'; ?>" style="padding: 6px 16px; font-size: 0.9rem; border-radius: 30px;">Kapoenen</a>
                <a href="echos.php?tak=welpen" class="btn <?php echo $filter_tak === 'welpen' ? 'btn-secondary' : 'btn-outline'; ?>" style="padding: 6px 16px; font-size: 0.9rem; border-radius: 30px;">Welpen</a>
                <a href="echos.php?tak=jonggivers" class="btn <?php echo $filter_tak === 'jonggivers' ? 'btn-secondary' : 'btn-outline'; ?>" style="padding: 6px 16px; font-size: 0.9rem; border-radius: 30px;">Jonggivers</a>
                <a href="echos.php?tak=givers" class="btn <?php echo $filter_tak === 'givers' ? 'btn-secondary' : 'btn-outline'; ?>" style="padding: 6px 16px; font-size: 0.9rem; border-radius: 30px;">Givers</a>
            </div>
        </div>

        <!-- Document Grid -->
        <?php if (empty($filtered_echos)): ?>
            <!-- Blank State -->
            <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--color-border); padding: 60px; text-align: center; max-width: 600px; margin: 0 auto; width: 100%;">
                <svg style="width: 64px; height: 64px; fill: none; stroke: var(--color-primary-light); opacity: 0.5; margin: 0 auto 20px;" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 style="margin-bottom: 8px;">Geen kalenders gevonden</h3>
                <p style="color: var(--color-text-muted);">Er zijn momenteel geen geüploade kalenders beschikbaar voor de geselecteerde tak of periode. Vraag ernaar bij de leiding!</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px;">
                <?php foreach ($filtered_echos as $echo): 
                    $tak_name = ucfirst($echo['tak']);
                    $month_name = get_dutch_month($echo['month']);
                    $uploaded_date = date('d-m-Y', strtotime($echo['uploaded_at']));
                ?>
                    <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--color-border); overflow: hidden; display: flex; flex-direction: column; transition: var(--transition-normal);" class="tak-card">
                        <div class="tak-card-header <?php echo $echo['tak']; ?>"></div>
                        <div style="padding: 24px; display: flex; flex-direction: column; flex-grow: 1;">
                            <span class="tak-card-tag"><?php echo $month_name . ' ' . htmlspecialchars($echo['year']); ?></span>
                            <h3 style="font-size: 1.35rem; margin-bottom: 8px;"><?php echo htmlspecialchars($echo['title']); ?></h3>
                            
                            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; margin-top: auto;">
                                <span class="status-badge" style="background-color: var(--color-bg-linen); color: var(--color-primary-dark); font-size: 0.75rem; padding: 2px 8px;"><?php echo $tak_name; ?></span>
                                <span style="font-size: 0.75rem; color: var(--color-text-muted); align-self: center;">Geüpload: <?php echo $uploaded_date; ?></span>
                            </div>
                            
                            <!-- Download PDF Link -->
                            <a href="uploads/echos/<?php echo urlencode($echo['file_name']); ?>" download class="btn btn-secondary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <svg style="width: 18px; height: 18px;" fill="none; stroke: currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Download PDF
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
