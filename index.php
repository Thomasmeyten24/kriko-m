<?php
/**
 * Homepage - Index View
 * Scouts Kriko-M Web Platform
 */

$page_title = "Welkom";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

// Fetch dynamic calendar activities from flat-file database
$calendar_events = read_db('calendar');
?>

<!-- 1. Hero Banner Component (Modern, Minimalist & Playful) -->
<section class="hero" style="display: flex; align-items: center; justify-content: center; text-align: center; min-height: 420px; padding: 100px 0 120px; background: linear-gradient(rgba(18, 48, 28, 0.75), rgba(18, 48, 28, 0.85)), url('assets/images/hero-bg.jpg') center/cover no-repeat;">
    <div class="container" style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
        <div class="hero-content" style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            
            <!-- Large Bold Playful Title -->
            <h2 class="hero-title" style="font-size: clamp(2.8rem, 6vw, 4.2rem); font-family: 'Outfit', sans-serif; font-weight: 900; line-height: 1.15; margin-bottom: 25px; color: var(--color-bg-white); text-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);">
                Scouts Kriko-M<br>
                <span style="display: block; font-size: clamp(1.5rem, 3vw, 2.1rem); font-weight: 500; color: var(--color-accent); letter-spacing: 1.5px; margin-top: 12px; font-family: 'Outfit', sans-serif;">
                    T'zal wel zijn
                </span>
            </h2>
            
            <!-- Modern CTA Button Group -->
            <div class="hero-actions" style="justify-content: center; gap: 20px; width: 100%;">
                <a href="inschrijven.php" class="btn btn-secondary" style="font-size: 1.15rem; padding: 16px 36px; border-radius: 50px; font-weight: 700; box-shadow: 0 10px 25px rgba(230, 92, 57, 0.45); display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                    <span>Inschrijven</span>
                    <svg style="width: 20px; height: 20px; fill: none; stroke: currentColor;" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                </a>
                <a href="takken.php" class="btn btn-outline" style="font-size: 1.1rem; padding: 15px 32px; border-radius: 50px; font-weight: 600; color: var(--color-bg-white); border-color: rgba(255, 255, 255, 0.4); background-color: rgba(255, 255, 255, 0.05); backdrop-filter: blur(5px); display: inline-flex; align-items: center; justify-content: center;">
                    Onze Takken
                </a>
            </div>
        </div>
    </div>
</section>

<!-- 2. Welkom & Introductie Section -->
<section class="section container">
    <div class="welcome-grid">
        <div>
            <h3 style="font-size: 2rem; margin-bottom: 16px; color: var(--color-primary-dark);">Al meer dan 80 jaar scouting in Sint-Niklaas</h3>
            <p style="margin-bottom: 16px; font-size: 1.05rem; color: var(--color-text-dark);">
                Scouts Kriko-M staat voor actie, vriendschap en zelfstandigheid. Elke zondagochtend van <strong>9:45 tot 12:30</strong> openen wij ons lokaal op het VP-plein voor een namiddag vol bosspelen, sjorringen, sportieve uitdagingen en gezelligheid.
            </p>
            <p style="color: var(--color-text-muted);">
                Onze leiding is een enthousiaste en ervaren groep vrijwilligers die elke week de leukste en veiligste activiteiten bedenken voor onze leden. Ontdek snel in welke tak jouw kind past en kom gerust eens gratis proberen!
            </p>
            <div style="margin-top: 24px; display: flex; gap: 16px;">
                <a href="inschrijven.php" class="btn btn-secondary">Hoe werkt het?</a>
                <a href="contact.php" class="btn btn-outline">Vind ons lokaal</a>
            </div>
        </div>
        <div style="position: relative;">
            <!-- Beautiful visual collage frame using CSS variables -->
            <div style="width: 100%; height: 350px; background-color: var(--color-primary-light); border-radius: var(--border-radius-lg); overflow: hidden; box-shadow: var(--shadow-lg); border: 4px solid var(--color-bg-white); transform: rotate(-2deg); background: linear-gradient(rgba(18,48,28,0.2), rgba(18,48,28,0.2)), url('assets/images/collage-1.jpg') center/cover;">
                <!-- Fallback background graphic if image is missing -->
                <div style="display: flex; height: 100%; align-items: center; justify-content: center; color: var(--color-bg-white); flex-direction: column; padding: 24px; text-align: center;">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 3. Takken Overzicht Section -->
<section class="section section-bg">
    <div class="container">
        <div class="section-header">
            <h2>Onze Scouts Takken</h2>
            <div class="title-line"></div>
            <p>Onze scoutsgroep is ingedeeld in verschillende leeftijdsgroepen (takken) zodat elk lid activiteiten krijgt die perfect aansluiten bij zijn leefwereld.</p>
        </div>

        <div class="takken-grid">
            <!-- Kapoenen -->
            <div class="tak-card">
                <div class="tak-card-header kapoenen"></div>
                <div class="tak-card-body">
                    <span class="tak-card-tag">Leeftijd: 6 - 8 jaar</span>
                    <h3>Kapoenen</h3>
                    <span class="tak-card-age">1e & 2e leerjaar</span>
                    <p class="tak-card-desc">Bij de kapoenen staat het spel centraal. Ze ontdekken de wereld om zich heen spelenderwijs en leren voor het eerst samenwerken.</p>
                    <div class="tak-card-footer">
                        <a href="takken.php?tak=kapoenen" class="tak-card-link">Meer info &raquo;</a>
                    </div>
                </div>
            </div>

            <!-- Welpen -->
            <div class="tak-card">
                <div class="tak-card-header welpen"></div>
                <div class="tak-card-body">
                    <span class="tak-card-tag">Leeftijd: 8 - 11 jaar</span>
                    <h3>Welpen</h3>
                    <span class="tak-card-age">3e, 4e & 5e leerjaar</span>
                    <p class="tak-card-desc">Welpen duiken in de jungle! Geïnspireerd door het Jungleboek beleven ze actieve spelen, leren ze scouts-vaardigheden en gaan ze op weekend.</p>
                    <div class="tak-card-footer">
                        <a href="takken.php?tak=welpen" class="tak-card-link">Meer info &raquo;</a>
                    </div>
                </div>
            </div>

            <!-- Jonggivers -->
            <div class="tak-card">
                <div class="tak-card-header jonggivers"></div>
                <div class="tak-card-body">
                    <span class="tak-card-tag">Leeftijd: 11 - 14 jaar</span>
                    <h3>Jonggivers</h3>
                    <span class="tak-card-age">6e, 1e & 2e middelbaar</span>
                    <p class="tak-card-desc">Tijd voor actie! Jonggivers leren knopen leggen, vlotten bouwen, navigeren met kaart en kompas, en gaan op tentenkamp in de Ardennen.</p>
                    <div class="tak-card-footer">
                        <a href="takken.php?tak=jonggivers" class="tak-card-link">Meer info &raquo;</a>
                    </div>
                </div>
            </div>

            <!-- Givers -->
            <div class="tak-card">
                <div class="tak-card-header givers"></div>
                <div class="tak-card-body">
                    <span class="tak-card-tag">Leeftijd: 14 - 17 jaar</span>
                    <h3>Givers</h3>
                    <span class="tak-card-age">3e, 4e & 5e middelbaar</span>
                    <p class="tak-card-desc">De givers bepalen hun eigen weg. Ze nemen initiatief, gaan op trektocht en beleven uitdagende avonturen (en een buitenlands kamp!).</p>
                    <div class="tak-card-footer">
                        <a href="takken.php?tak=givers" class="tak-card-link">Meer info &raquo;</a>
                    </div>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <a href="takken.php" class="btn btn-secondary">Bekijk al onze groepen</a>
        </div>
    </div>
</section>

<!-- 4. Kalender & Nieuws Section -->
<section class="section container">
    <div class="home-grid">
        <!-- Calendar Events Grid (Dynamic) -->
        <div class="calendar-card">
            <h3 style="font-size: 1.75rem; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 12px; display: flex; align-items: center; gap: 10px;">
                <svg style="width: 24px; height: 24px; fill: none; stroke: var(--color-secondary);" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Aankomende Activiteiten
            </h3>
            
            <div class="calendar-list">
                <?php if (empty($calendar_events)): ?>
                    <p style="color: var(--color-text-muted);">Er zijn momenteel geen geplande groepsactiviteiten.</p>
                <?php else: ?>
                    <?php foreach ($calendar_events as $event): 
                        // Format the date dynamically
                        $timestamp = strtotime($event['date']);
                        $day = date('d', $timestamp);
                        
                        // Dutch month abbreviations
                        $months_nl = [
                            '01' => 'Jan', '02' => 'Feb', '03' => 'Mrt', '04' => 'Apr',
                            '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Aug',
                            '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Dec'
                        ];
                        $month_num = date('m', $timestamp);
                        $month = isset($months_nl[$month_num]) ? $months_nl[$month_num] : date('M', $timestamp);
                    ?>
                        <div class="calendar-item">
                            <div class="calendar-date-block">
                                <span class="calendar-day"><?php echo $day; ?></span>
                                <span class="calendar-month"><?php echo $month; ?></span>
                            </div>
                            <div class="calendar-details">
                                <div class="calendar-time">
                                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($event['time']); ?> &bull; <?php echo htmlspecialchars($event['location']); ?></span>
                                </div>
                                <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                                <p class="calendar-desc"><?php echo htmlspecialchars($event['description']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Side Banners -->
        <div style="display: flex; flex-direction: column; gap: 30px;">
            <div class="info-banner">
                <h3>Kriko Echo planning</h3>
                <p>Elke maand brengt onze leiding de "Kriko Echo" uit: het complete programmaboekje met alle activiteiten en informatie per tak. Zorg dat je op de hoogte bent!</p>
                <a href="echos.php" class="btn btn-primary" style="align-self: flex-start; background-color: var(--color-accent); color: var(--color-primary-dark); font-weight: 700;">Download de Echo &raquo;</a>
            </div>
            
            <div style="background-color: var(--color-bg-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--color-border); padding: 30px;">
                <h4 style="margin-bottom: 12px; font-size: 1.3rem;">Praktische Info</h4>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 14px;">
                    <li style="display: flex; gap: 10px; align-items: flex-start;">
                        <span style="color: var(--color-secondary); font-weight: bold; font-size: 1.2rem; line-height: 1;">&bull;</span>
                        <div>
                            <strong style="display: block; font-size: 0.95rem;">Wanneer?</strong>
                            <span style="font-size: 0.9rem; color: var(--color-text-muted);">Elke zondag van 9:45 tot 12:30.</span>
                        </div>
                    </li>
                    <li style="display: flex; gap: 10px; align-items: flex-start;">
                        <span style="color: var(--color-secondary); font-weight: bold; font-size: 1.2rem; line-height: 1;">&bull;</span>
                        <div>
                            <strong style="display: block; font-size: 0.95rem;">Waar?</strong>
                            <span style="font-size: 0.9rem; color: var(--color-text-muted);">VP-plein (Industriepark-Noord 33, naast drankenhandel De Vidts), 9100 Sint-Niklaas.</span>
                        </div>
                    </li>
                    <li style="display: flex; gap: 10px; align-items: flex-start;">
                        <span style="color: var(--color-secondary); font-weight: bold; font-size: 1.2rem; line-height: 1;">&bull;</span>
                        <div>
                            <strong style="display: block; font-size: 0.95rem;">Scoutswinkel (Hopper)</strong>
                            <span style="font-size: 0.9rem; color: var(--color-text-muted);">Algemene scoutshemden en broeken koop je bij Hopper, groepsdassen en T-shirts koop je in onze webshop.</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
