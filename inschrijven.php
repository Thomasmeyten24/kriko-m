<?php
/**
 * Registration Instructions - Inschrijven View
 * Scouts Kriko-M Web Platform
 */

$page_title = "Inschrijven";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

// Fetch dynamic registration fees from settings.json database
$settings = read_db('settings');
$fee_1 = isset($settings['registration_fee_first']) ? $settings['registration_fee_first'] : 50.00;
$fee_2 = isset($settings['registration_fee_extra']) ? $settings['registration_fee_extra'] : 45.00;
$scouts_year = isset($settings['scouts_year']) ? $settings['scouts_year'] : '2026-2027';
$contact_email = isset($settings['contact_email']) ? $settings['contact_email'] : 'groepsleiding@kriko-m.be';
?>

<!-- 1. Page Header -->
<section class="tak-hero kapoenen">
    <div class="container">
        <span style="color: var(--color-accent); font-family: 'Outfit', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">Word lid</span>
        <h2 class="tak-hero-title">Inschrijven & Lidgeld</h2>
        <p style="font-size: 1.2rem; color: hsla(0, 0%, 100%, 0.9); margin-top: 8px;">Sluit je aan bij de tofste scoutsgroep van Sint-Niklaas voor het scoutsjaar <?php echo htmlspecialchars($scouts_year); ?>!</p>
    </div>
</section>

<!-- 2. Registration Guide steps -->
<section class="section container">
    <div class="register-layout">
        
        <!-- Left: Progressive Steps Guide -->
        <div>
            <h3 style="font-size: 1.8rem; margin-bottom: 24px; color: var(--color-primary-dark);">Hoe word je lid van Kriko-M?</h3>
            
            <!-- Step 1: Trial Sundays -->
            <div class="step-card">
                <div class="step-number">1</div>
                <div class="step-body">
                    <h4>Kom gratis proberen!</h4>
                    <p style="color: var(--color-text-dark); font-size: 0.95rem;">
                        Nieuwe leden hoeven zich niet meteen in te schrijven. Ieder kind mag eerst <strong>3 keer gratis proberen</strong>! Kom gewoon op zondagochtend om <strong>9:45 stipt</strong> langs bij ons lokaal op het VP-plein (Industriepark-Noord 33). Onze leiding heet je kind van harte welkom en hij of zij kan direct meespelen en ontdekken!
                    </p>
                </div>
            </div>
            
            <!-- Step 2: Online Portal Registration -->
            <div class="step-card">
                <div class="step-number">2</div>
                <div class="step-body">
                    <h4>Inschrijven via ons Ouderportaal</h4>
                    <p style="color: var(--color-text-dark); font-size: 0.95rem; margin-bottom: 12px;">
                        Als je kind na drie proefzondagen overtuigd is, kun je hem of haar officieel inschrijven via ons <strong>Ouderportaal</strong> op deze website. Hier beheer je de gegevens van je kinderen, schrijf je ze in voor weekenden/kampen en volg je bestellingen.
                    </p>
                    <a href="ouderportaal.php" class="btn btn-secondary" style="font-size: 0.9rem; padding: 8px 18px;">
                        Naar het Ouderportaal &raquo;
                    </a>
                </div>
            </div>
            
            <!-- Step 3: Payment instructions -->
            <div class="step-card">
                <div class="step-number">3</div>
                <div class="step-body">
                    <h4>Betaling Lidgeld</h4>
                    <p style="color: var(--color-text-dark); font-size: 0.95rem; margin-bottom: 8px;">
                        Om de inschrijving af te ronden, dient het jaarlijkse lidgeld te worden overgeschreven. Dit lidgeld dekt de verplichte scoutsverzekering (€38 gaat rechtstreeks naar Scouts en Gidsen Vlaanderen) en onze lokale werking (€12 voor spelmaterialen en wekelijkse activiteiten).
                    </p>
                    <div style="background-color: var(--color-bg-linen); border-radius: var(--border-radius-md); padding: 12px 16px; border: 1px solid var(--color-border); font-size: 0.9rem; margin-top: 10px;">
                        <strong>Rekeningnummer:</strong> <code>BE59 7360 6413 2626</code><br>
                        <strong>Begunstigde:</strong> Scouts Kriko-M<br>
                        <strong>Mededeling:</strong> "Lidgeld [Naam van het kind] + [Tak]"
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right: Pricing Sidebar & FAQ -->
        <div>
            <!-- Pricing card -->
            <div class="side-card" style="background-color: var(--color-primary-dark); color: var(--color-bg-white); border: none;">
                <h3 style="color: var(--color-accent); border-bottom: 1px dashed rgba(255,255,255,0.2);">Jaarlijks Lidgeld</h3>
                <p style="font-size: 0.95rem; color: hsla(0, 0%, 100%, 0.85); margin-bottom: 20px;">De tarieven voor het scoutsjaar <strong><?php echo htmlspecialchars($scouts_year); ?></strong>:</p>
                
                <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; background-color: rgba(255,255,255,0.1); border-radius: var(--border-radius-md); padding: 14px; border: 1px solid rgba(255,255,255,0.15);">
                        <div>
                            <strong style="display: block; font-size: 0.95rem;">1e Kind:</strong>
                            <span style="font-size: 0.8rem; opacity: 0.7;">Eerste gezinslid</span>
                        </div>
                        <strong style="font-size: 1.5rem; color: var(--color-accent);">€<?php echo number_format($fee_1, 2, ',', ''); ?></strong>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; background-color: rgba(255,255,255,0.1); border-radius: var(--border-radius-md); padding: 14px; border: 1px solid rgba(255,255,255,0.15);">
                        <div>
                            <strong style="display: block; font-size: 0.95rem;">Vanaf 2e Kind:</strong>
                            <span style="font-size: 0.8rem; opacity: 0.7;">Korting per extra kind</span>
                        </div>
                        <strong style="font-size: 1.5rem; color: var(--color-accent);">€<?php echo number_format($fee_2, 2, ',', ''); ?></strong>
                    </div>
                </div>
                
                <p style="font-size: 0.8rem; opacity: 0.8; line-height: 1.4;">
                    * Heeft u recht op verminderd lidgeld of is de betaling financieel moeilijk? Contacteer gerust onze groepsleiding via <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" style="color: var(--color-accent); font-weight: 700; text-decoration: underline;"><?php echo htmlspecialchars($contact_email); ?></a>. Wij behandelen elke aanvraag strikt vertrouwelijk en zorgen dat elk kind kan meespelen!
                </p>
            </div>
            
            <!-- Scouting op Maat & Insurance Card FAQ -->
            <div class="side-card">
                <h3>Scouting op Maat & Verzekering</h3>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px; font-size: 0.9rem;">
                    <li>
                        <strong>Verminderd Lidgeld:</strong>
                        <span style="color: var(--color-text-muted); display: block; margin-top: 2px;">Dankzij *Scouting op Maat* betalen gezinnen met financiële moeilijkheden slechts <strong>€10</strong> (of <strong>€5</strong> na 1 maart). Neem discreet contact op met de groepsleiding via <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" style="color: var(--color-primary); font-weight: 700; text-decoration: underline;"><?php echo htmlspecialchars($contact_email); ?></a>.</span>
                    </li>
                    <li>
                        <strong>Mutualiteit terugbetaling:</strong>
                        <span style="color: var(--color-text-muted); display: block; margin-top: 2px;">Vlaamse mutualiteiten (CM, Helan, Solidaris, LM) betalen jaarlijks <strong>€15 tot €25</strong> van het lidgeld of kampen terug! Download het formulier en bezorg het aan de leiding.</span>
                    </li>
                    <li>
                        <strong>Fiscaal Attest:</strong>
                        <span style="color: var(--color-text-muted); display: block; margin-top: 2px;">Kampen en weekenden voor kinderen onder 12 jaar zijn fiscaal aftrekbaar. We genereren deze attesten automatisch aan het einde van het jaar.</span>
                    </li>
                    <li>
                        <strong>Medische Fiche:</strong>
                        <span style="color: var(--color-text-muted); display: block; margin-top: 2px;">Bij inschrijving vult u een medische fiche in op ons ouderportaal. Gelieve allergieën of medische aandachtspunten steeds aan de specifieke takleiding te melden!</span>
                    </li>
                </ul>
            </div>
        </div>
        
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
