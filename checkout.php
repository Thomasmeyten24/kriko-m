<?php
/**
 * Shop Checkout - Checkout View
 * Scouts Kriko-M Web Platform
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/parent_auth.php';

$current_parent = is_parent_logged_in() ? get_logged_in_parent() : null;


// Helper function to generate a valid Belgian structured communication reference (Modulo 97)
function generate_structured_communication() {
    // Generate a random 10-digit number
    $first_ten = rand(1000000000, 9999999999);
    // Modulo 97 check digit calculation
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $customer_name = filter_input(INPUT_POST, 'customer_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $child_name = filter_input(INPUT_POST, 'child_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $child_tak = filter_input(INPUT_POST, 'child_tak', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS);
    $cart_data_json = isset($_POST['cart_data']) ? $_POST['cart_data'] : '';
    
    $cart = json_decode($cart_data_json, true);
    
    if (empty($customer_name) || empty($child_name) || empty($child_tak) || empty($email) || empty($phone)) {
        $error = 'Vul alstublieft alle contact- en bestelgegevens in.';
    } elseif (!$cart || count($cart) === 0) {
        $error = 'Je winkelwagen is leeg. Ga terug naar de shop.';
    } else {
        // Calculate dynamic total
        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        $comm = generate_structured_communication();
        $order_id = 'ord_' . uniqid();
        
        // Construct order object
        $order = [
            'id' => $order_id,
            'date' => date('Y-m-d H:i:s'),
            'status' => 'pending', // pending, paid, completed
            'customer_name' => $customer_name,
            'child_name' => $child_name,
            'child_tak' => $child_tak,
            'email' => $email,
            'phone' => $phone,
            'items' => $cart,
            'total' => $total,
            'communication' => $comm
        ];
        
        if ($current_parent) {
            $order['parent_id'] = $current_parent['id'];
        }

        
        // Load, append and save to database
        $orders = read_db('orders');
        $orders[] = $order;
        write_db('orders', $orders);
        
        // Save to session to display on success page
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['last_order'] = $order;
        
        // Redirect to success page (prevents form resubmissions)
        header('Location: order-success.php');
        exit;
    }
}

$page_title = "Afrekenen";
require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Page Header -->
<section class="tak-hero givers">
    <div class="container">
        <span style="color: var(--color-accent); font-family: 'Outfit', sans-serif; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">Bijna klaar</span>
        <h2 class="tak-hero-title">Bestelling afronden</h2>
        <p style="font-size: 1.2rem; color: hsla(0, 0%, 100%, 0.9); margin-top: 8px;">Vul je gegevens in om de bestelling via overschrijving te plaatsen.</p>
    </div>
</section>

<!-- 2. Main Checkout Form & Summary Grid -->
<section class="section container">
    <?php if (!empty($error)): ?>
        <div style="background-color: hsla(4, 75%, 48%, 0.1); border: 2px solid var(--color-error); color: var(--color-error); padding: 16px; border-radius: var(--border-radius-md); margin-bottom: 30px; font-weight: 600;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="checkout-layout">
        
        <!-- Left: Details Form -->
        <div class="checkout-card">
            <h3 style="font-size: 1.6rem; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 12px; margin-bottom: 24px; color: var(--color-primary-dark);">Contact & Bestelgegevens</h3>
            
            <form action="checkout.php" method="POST" id="checkout-form">
                <!-- Hidden input containing dynamic JSON string of cart items -->
                <input type="hidden" name="cart_data" id="cart-data-input">
                
                <!-- Billing Name -->
                <div class="form-group">
                    <label class="form-label" for="customer_name">Naam Ouder / Voogd:</label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" placeholder="Voornaam + Achternaam" value="<?php echo $current_parent ? htmlspecialchars($current_parent['first_name'] . ' ' . $current_parent['last_name']) : ''; ?>" required>
                </div>
                
                <!-- Child Info Rows -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="child_name">Naam Lid (Kind):</label>
                        <input type="text" id="child_name" name="child_name" class="form-control" placeholder="Voornaam + Achternaam" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="child_tak">Tak van Lid:</label>
                        <select id="child_tak" name="child_tak" class="form-control" required>
                            <option value="" disabled selected>Selecteer Tak</option>
                            <option value="kapoenen">Kapoenen (6-8j)</option>
                            <option value="welpen">Welpen (8-11j)</option>
                            <option value="jonggivers">Jonggivers (11-14j)</option>
                            <option value="givers">Givers (14-17j)</option>
                        </select>
                    </div>
                </div>
                
                <!-- Contact Rows -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="email">E-mailadres:</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="ouder@domein.be" value="<?php echo $current_parent ? htmlspecialchars($current_parent['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="phone">Telefoonnummer:</label>
                        <input type="tel" id="phone" name="phone" class="form-control" placeholder="0470 00 00 00" value="<?php echo $current_parent ? htmlspecialchars($current_parent['phone']) : ''; ?>" required>
                    </div>
                </div>
                
                <div style="background-color: var(--color-bg-linen); border-radius: var(--border-radius-md); padding: 20px; border: 1px solid var(--color-border); margin: 24px 0 30px;">
                    <strong style="display: block; color: var(--color-primary-dark); margin-bottom: 6px;">Betalingsinformatie</strong>
                    <span style="font-size: 0.9rem; color: var(--color-text-muted); line-height: 1.4; display: block;">
                        Door op 'Bestelling plaatsen' te klikken, stem je in met de betalingsvoorwaarden van Scouts Kriko-M. Je ontvangt onmiddellijk de bankgegevens en een unieke gestructureerde mededeling op het volgende scherm. Zodra we je overschrijving ontvangen, wordt je bestelling verwerkt!
                    </span>
                </div>
                
                <button type="submit" id="btn-place-order" class="btn btn-secondary" style="width: 100%; padding: 14px 28px; font-size: 1.1rem;" disabled>
                    Bestelling plaatsen (Handmatige Overschrijving)
                </button>
            </form>
        </div>
        
        <!-- Right: Cart Summary Sidebar -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="checkout-card" style="background-color: var(--color-bg-white);">
                <h3 style="font-size: 1.4rem; border-bottom: 2px solid var(--color-bg-linen); padding-bottom: 8px; margin-bottom: 16px; color: var(--color-primary-dark);">Jouw Mandje</h3>
                
                <!-- Dynamic Checkout Summary list filled by JS -->
                <div id="checkout-items-list">
                    <!-- Populated via cart.js -->
                </div>
                
                <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.25rem; color: var(--color-primary-dark); margin-top: 20px; padding-top: 16px; border-top: 2px solid var(--color-bg-linen);">
                    <span>Totaal:</span>
                    <span id="checkout-grand-total">€0,00</span>
                </div>
            </div>
            
            <a href="shop.php" class="btn btn-outline" style="width: 100%;">
                &larr; Verder winkelen
            </a>
        </div>
        
    </div>
</section>

<!-- Cart is safely cleared in order-success.php upon page load to prevent race conditions -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
