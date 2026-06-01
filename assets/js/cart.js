/**
 * Scouts Kriko-M - Shopping Cart Manager (Vanilla JS)
 * Uses localStorage to persist cart items across views.
 */

document.addEventListener('DOMContentLoaded', () => {
    initCart();
});

function initCart() {
    // Select cart elements
    const cartTriggers = document.querySelectorAll('.cart-trigger-btn');
    const cartDrawer = document.querySelector('.cart-drawer');
    const cartClose = document.querySelector('.cart-drawer-close');
    const cartBackdrop = document.querySelector('.cart-backdrop');
    
    // Toggle Cart Drawer
    if (cartTriggers.length > 0 && cartDrawer && cartBackdrop) {
        cartTriggers.forEach(trigger => {
            trigger.addEventListener('click', () => {
                cartDrawer.classList.add('open');
                cartBackdrop.classList.add('open');
                updateCartUI();
            });
        });
    }
    
    if (cartClose && cartDrawer && cartBackdrop) {
        cartClose.addEventListener('click', closeCart);
    }
    
    if (cartBackdrop) {
        cartBackdrop.addEventListener('click', closeCart);
    }
    
    // Bind Add to Cart buttons using document delegation (robust against dynamic SPA DOM swaps!)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-add-to-cart');
        if (btn) {
            const itemId = btn.getAttribute('data-id');
            const itemName = btn.getAttribute('data-name');
            const itemPrice = parseFloat(btn.getAttribute('data-price'));
            const itemImage = btn.getAttribute('data-image');
            
            // Get selected size from parent container
            const card = btn.closest('.shop-card');
            const sizeSelect = card ? card.querySelector('.shop-size-select') : null;
            const selectedSize = sizeSelect ? sizeSelect.value : 'Standaard';
            
            addToCart(itemId, itemName, itemPrice, selectedSize, itemImage);
        }
    });
    
    // Initial UI render
    updateCartUI();
}

function closeCart() {
    const cartDrawer = document.querySelector('.cart-drawer');
    const cartBackdrop = document.querySelector('.cart-backdrop');
    if (cartDrawer) cartDrawer.classList.remove('open');
    if (cartBackdrop) cartBackdrop.classList.remove('open');
}

/**
 * Fetch cart from localStorage
 */
function getCart() {
    try {
        const cartStr = localStorage.getItem('kriko_cart');
        return cartStr ? JSON.parse(cartStr) : [];
    } catch (e) {
        return [];
    }
}

/**
 * Save cart to localStorage and refresh UI
 */
function saveCart(cart) {
    localStorage.setItem('kriko_cart', JSON.stringify(cart));
    updateCartUI();
}

/**
 * Add an item to the cart
 */
function addToCart(id, name, price, size, image) {
    let cart = getCart();
    
    // Check if item with same ID and Size already in cart
    const existingIndex = cart.findIndex(item => item.id === id && item.size === size);
    
    if (existingIndex > -1) {
        cart[existingIndex].quantity += 1;
    } else {
        cart.push({
            id,
            name,
            price,
            size,
            image,
            quantity: 1
        });
    }
    
    saveCart(cart);
    
    // Auto-open drawer to provide immediate feedback
    const cartDrawer = document.querySelector('.cart-drawer');
    const cartBackdrop = document.querySelector('.cart-backdrop');
    if (cartDrawer && cartBackdrop) {
        cartDrawer.classList.add('open');
        cartBackdrop.classList.add('open');
    }
}

/**
 * Update quantity of a cart item
 */
function updateQuantity(id, size, delta) {
    let cart = getCart();
    const index = cart.findIndex(item => item.id === id && item.size === size);
    
    if (index > -1) {
        cart[index].quantity += delta;
        if (cart[index].quantity <= 0) {
            cart.splice(index, 1);
        }
        saveCart(cart);
    }
}

/**
 * Remove item completely from cart
 */
function removeFromCart(id, size) {
    let cart = getCart();
    cart = cart.filter(item => !(item.id === id && item.size === size));
    saveCart(cart);
}

/**
 * Clear the cart (typically called after successful order placement)
 */
function clearCart() {
    localStorage.removeItem('kriko_cart');
    updateCartUI();
}

/**
 * Refresh all cart UI nodes
 */
function updateCartUI() {
    const cart = getCart();
    
    // 1. Update Navigation Badge Counts & Trigger Button Visibility
    const badges = document.querySelectorAll('.cart-count');
    const cartTriggers = document.querySelectorAll('.cart-trigger-btn');
    const totalQty = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    cartTriggers.forEach(trigger => {
        // Floating FAB on portal should use flex, standard headers should use inline-flex
        if (trigger.style.position === 'fixed') {
            trigger.style.display = totalQty > 0 ? 'flex' : 'none';
        } else {
            trigger.style.display = totalQty > 0 ? 'inline-flex' : 'none';
        }
    });
    
    badges.forEach(badge => {
        badge.textContent = totalQty;
        badge.style.display = totalQty > 0 ? 'flex' : 'none';
    });
    
    // 2. Render Drawer List
    const drawerBody = document.querySelector('.cart-drawer-body');
    const drawerSubtotal = document.querySelector('.cart-subtotal-value');
    
    if (drawerBody) {
        if (cart.length === 0) {
            drawerBody.innerHTML = `
                <div style="text-align: center; margin-top: 40px; color: var(--color-text-muted);">
                    <svg style="width: 48px; height: 48px; opacity: 0.5; margin-bottom: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                    <p>Je winkelmandje is leeg.</p>
                </div>
            `;
            if (drawerSubtotal) drawerSubtotal.textContent = '€0,00';
            
            const checkoutBtn = document.querySelector('.btn-cart-checkout');
            if (checkoutBtn) {
                checkoutBtn.style.pointerEvents = 'none';
                checkoutBtn.style.opacity = '0.5';
            }
        } else {
            let html = '';
            let subtotal = 0;
            
            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                
                html += `
                    <div class="cart-item">
                        <div class="cart-item-details">
                            <div class="cart-item-title">${item.name}</div>
                            <div class="cart-item-meta">Maat: ${item.size}</div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
                                <button onclick="updateQuantity('${item.id}', '${item.size}', -1)" style="width: 24px; height: 24px; border: 1px solid var(--color-border); background: var(--color-bg-linen); border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold;">-</button>
                                <span style="font-weight: 600; font-size: 0.9rem;">${item.quantity}</span>
                                <button onclick="updateQuantity('${item.id}', '${item.size}', 1)" style="width: 24px; height: 24px; border: 1px solid var(--color-border); background: var(--color-bg-linen); border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold;">+</button>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div class="cart-item-price">€${itemTotal.toFixed(2).replace('.', ',')}</div>
                            <button onclick="removeFromCart('${item.id}', '${item.size}')" class="cart-item-remove" style="margin-top: 6px;">Verwijder</button>
                        </div>
                    </div>
                `;
            });
            
            drawerBody.innerHTML = html;
            if (drawerSubtotal) drawerSubtotal.textContent = `€${subtotal.toFixed(2).replace('.', ',')}`;
            
            const checkoutBtn = document.querySelector('.btn-cart-checkout');
            if (checkoutBtn) {
                checkoutBtn.style.pointerEvents = 'auto';
                checkoutBtn.style.opacity = '1';
            }
        }
    }
    
    // 3. Update Checkout Page Summary (if active)
    updateCheckoutUI(cart);
}

/**
 * Handle checkout summary generation on checkout.php
 */
function updateCheckoutUI(cart) {
    const checkoutItems = document.getElementById('checkout-items-list');
    const checkoutTotal = document.getElementById('checkout-grand-total');
    const cartInput = document.getElementById('cart-data-input');
    
    if (checkoutItems && checkoutTotal) {
        if (cart.length === 0) {
            checkoutItems.innerHTML = '<p style="color: var(--color-text-muted);">Geen items in winkelwagentje.</p>';
            checkoutTotal.textContent = '€0,00';
            
            const submitBtn = document.getElementById('btn-place-order');
            if (submitBtn) submitBtn.disabled = true;
            return;
        }
        
        let html = '';
        let total = 0;
        
        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;
            
            html += `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--color-border);">
                    <div>
                        <div style="font-weight: 600; font-size: 0.95rem;">${item.name}</div>
                        <div style="font-size: 0.8rem; color: var(--color-text-muted);">Maat: ${item.size} | Aantal: ${item.quantity}</div>
                    </div>
                    <div style="font-weight: 700; color: var(--color-secondary);">€${itemTotal.toFixed(2).replace('.', ',')}</div>
                </div>
            `;
        });
        
        checkoutItems.innerHTML = html;
        checkoutTotal.textContent = `€${total.toFixed(2).replace('.', ',')}`;
        
        // Populate hidden JSON input for PHP submission
        if (cartInput) {
            cartInput.value = JSON.stringify(cart);
        }
        
        const submitBtn = document.getElementById('btn-place-order');
        if (submitBtn) submitBtn.disabled = false;
    }
}
