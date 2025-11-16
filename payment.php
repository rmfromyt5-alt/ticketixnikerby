<?php
session_start();
require_once __DIR__ . '/config.php';
$conn = getDBConnection();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

// Get booking data from POST
if (!isset($_POST['booking_data'])) {
    header("Location: TICKETIX NI CLAIRE.php");
    exit();
}

$bookingData = json_decode($_POST['booking_data'], true);
$seatTotal = floatval($_POST['seat_total'] ?? 0);
$foodTotal = floatval($_POST['food_total'] ?? 0);
$grandTotal = floatval($_POST['grand_total'] ?? 0);

if (!$bookingData) {
    header("Location: TICKETIX NI CLAIRE.php");
    exit();
}

$movieTitle = $bookingData['movie'] ?? '';
$branchName = $bookingData['branch'] ?? '';

// Get user ID
$userId = $_SESSION['user_id'] ?? $_SESSION['acc_id'] ?? null;

// Get user's saved payment methods
$savedPaymentMethods = [];
$defaultPaymentMethod = null;
if ($userId) {
    $stmt = $conn->prepare("SELECT * FROM USER_PAYMENT_METHODS WHERE acc_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $savedPaymentMethods[] = $row;
        if ($row['is_default'] == 1) {
            $defaultPaymentMethod = $row;
        }
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Ticketix</title>
    <link rel="icon" type="image/png" href="images/brand x.png" />
    <link rel="stylesheet" href="css/ticketix-main.css">
    <link rel="stylesheet" href="css/payment.css">
</head>
<body>
    <div class="payment-container">
        <a href="checkout.php" class="btn-back" onclick="history.back(); return false;">← Back</a>
        <h1>Payment Method</h1>
        <div class="total-amount">Total: ₱<?= number_format($grandTotal, 2) ?></div>
        <div id="referenceBanner" style="display: none; margin-bottom: 15px; padding: 12px 16px; border-radius: 8px; background: rgba(0, 191, 255, 0.1); border: 1px solid rgba(0, 191, 255, 0.4); color: #00BFFF; font-weight: 600;"></div>
        
        <?php if (isset($_GET['error'])): ?>
        <div class="error-message">
            <strong>Error:</strong> <?= htmlspecialchars($_GET['error']) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="process-booking.php" id="paymentForm">
            <input type="hidden" name="booking_data" value="<?= htmlspecialchars($_POST['booking_data']) ?>">
            <input type="hidden" name="seat_total" value="<?= $seatTotal ?>">
            <input type="hidden" name="food_total" value="<?= $foodTotal ?>">
            <input type="hidden" name="grand_total" value="<?= $grandTotal ?>">
            <input type="hidden" name="payment_type" id="paymentType" value="">
            <input type="hidden" name="reference_number" id="referenceNumber" value="">
            <input type="hidden" name="debug" value="1">
            <input type="hidden" name="saved_payment_method_id" id="savedPaymentMethodId" value="">
            
            <!-- Saved Payment Methods Section -->
            <?php if (!empty($savedPaymentMethods)): ?>
            <div class="saved-payment-methods" style="margin-bottom: 30px; padding: 20px; background: #f0f4ff; border-radius: 10px; border: 2px solid #667eea;">
                <h3 style="margin-bottom: 15px; color: #333; font-size: 1.1em;">Use Saved Payment Method</h3>
                <div style="display: grid; gap: 10px;">
                    <?php foreach ($savedPaymentMethods as $method): ?>
                        <div class="saved-method-option" style="padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; cursor: pointer; transition: all 0.3s;" 
                             onclick="selectSavedPaymentMethod(<?= htmlspecialchars(json_encode($method)) ?>)"
                             onmouseover="this.style.borderColor='#667eea'; this.style.background='#f8f9ff';"
                             onmouseout="this.style.borderColor='#e0e0e0'; this.style.background='white';">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <?php if ($method['payment_type'] === 'credit-card'): ?>
                                        <strong>💳 Credit Card</strong>
                                        <div style="margin-top: 5px; color: #666; font-size: 0.9em;">
                                            <?= htmlspecialchars($method['card_name'] ?? '') ?> - ****<?= htmlspecialchars(substr(str_replace(' ', '', $method['card_number'] ?? ''), -4)) ?>
                                            <?php if ($method['is_default']): ?>
                                                <span style="margin-left: 8px; padding: 2px 8px; background: #667eea; color: white; border-radius: 10px; font-size: 0.8em;">Default</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($method['payment_type'] === 'gcash'): ?>
                                        <strong>📱 GCash</strong>
                                        <div style="margin-top: 5px; color: #666; font-size: 0.9em;">
                                            <?= htmlspecialchars($method['gcash_number'] ?? '') ?>
                                            <?php if ($method['is_default']): ?>
                                                <span style="margin-left: 8px; padding: 2px 8px; background: #667eea; color: white; border-radius: 10px; font-size: 0.8em;">Default</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($method['payment_type'] === 'paypal'): ?>
                                        <strong>📧 PayPal</strong>
                                        <div style="margin-top: 5px; color: #666; font-size: 0.9em;">
                                            <?= htmlspecialchars($method['paypal_email'] ?? '') ?>
                                            <?php if ($method['is_default']): ?>
                                                <span style="margin-left: 8px; padding: 2px 8px; background: #667eea; color: white; border-radius: 10px; font-size: 0.8em;">Default</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($method['payment_type'] === 'paymaya'): ?>
                                        <strong>📱 PayMaya</strong>
                                        <div style="margin-top: 5px; color: #666; font-size: 0.9em;">
                                            <?= htmlspecialchars($method['paymaya_number'] ?? '') ?>
                                            <?php if ($method['is_default']): ?>
                                                <span style="margin-left: 8px; padding: 2px 8px; background: #667eea; color: white; border-radius: 10px; font-size: 0.8em;">Default</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($method['payment_type'] === 'grabpay'): ?>
                                        <strong>📱 GrabPay</strong>
                                        <div style="margin-top: 5px; color: #666; font-size: 0.9em;">
                                            <?= htmlspecialchars($method['grabpay_number'] ?? '') ?>
                                            <?php if ($method['is_default']): ?>
                                                <span style="margin-left: 8px; padding: 2px 8px; background: #667eea; color: white; border-radius: 10px; font-size: 0.8em;">Default</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                </div>
                                <input type="radio" name="saved_payment_method" value="<?= $method['payment_method_id'] ?>" style="margin-left: 10px;">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 15px; text-align: center;">
                    <a href="profile.php" style="color: #667eea; text-decoration: none; font-size: 0.9em;">Manage Payment Methods →</a>
                </div>
            </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin: 20px 0; color: #666; font-weight: 600;">OR</div>
            
            <div class="payment-methods">
                <!-- Credit Card Option -->
                <div class="payment-option" onclick="selectPayment('credit-card', event)">
                    <label>
                        <input type="radio" name="payment_method" value="credit-card" onchange="selectPayment('credit-card', event)">
                        <span class="payment-icon">💳</span>
                        <span>Credit Card</span>
                    </label>
                    
                    <!-- Credit Card Sub-options -->
                    <div class="sub-options" id="creditSubOptions" style="display: none;">
                        <div class="sub-option" onclick="event.stopPropagation(); selectSubOption('visa', 'credit-card');">
                            <input type="radio" name="credit_sub_option" value="visa" id="visa" onchange="selectSubOption('visa', 'credit-card')">
                            <label for="visa">
                                <span class="sub-option-icon">💳</span>
                                <span>Visa</span>
                            </label>
                        </div>
                        <div class="sub-option" onclick="event.stopPropagation(); selectSubOption('mastercard', 'credit-card');">
                            <input type="radio" name="credit_sub_option" value="mastercard" id="mastercard" onchange="selectSubOption('mastercard', 'credit-card')">
                            <label for="mastercard">
                                <span class="sub-option-icon">💳</span>
                                <span>Mastercard</span>
                            </label>
                        </div>
                        <div class="reference-wrapper" id="cardReferenceWrapper">
                            <label class="reference-label" for="cardReference">Card Number (Last 4 digits)</label>
                            <input type="text" name="card_reference" id="cardReference" class="reference-input" placeholder="Enter last 4 digits (e.g., 1234)" maxlength="4" pattern="[0-9]{4}">
                            <label class="reference-label" for="cardExpiry">Expiration Date</label>
                            <input type="text" name="card_expiry" id="cardExpiry" class="reference-input" placeholder="MM/YY" maxlength="5" pattern="[0-9]{2}/[0-9]{2}">
                            <label class="reference-label" for="cardCVV">CVV</label>
                            <input type="text" name="card_cvv" id="cardCVV" class="reference-input" placeholder="123" maxlength="4" pattern="[0-9]{3,4}">
                            <small class="reference-hint">Use any values for testing. In production, enter your actual card details.</small>
                        </div>
                    </div>
                </div>
                
                <!-- E-Wallet Option -->
                <div class="payment-option" onclick="selectPayment('e-wallet', event)">
                    <label>
                        <input type="radio" name="payment_method" value="e-wallet" onchange="selectPayment('e-wallet', event)">
                        <span class="payment-icon">📱</span>
                        <span>E-Wallet</span>
                    </label>
                    
                    <!-- E-Wallet Sub-options -->
                    <div class="sub-options" id="ewalletSubOptions" style="display: none;">
                        <div class="sub-option" onclick="event.stopPropagation(); selectSubOption('gcash', 'e-wallet');">
                            <input type="radio" name="ewallet_sub_option" value="gcash" id="gcash" onchange="selectSubOption('gcash', 'e-wallet')">
                            <label for="gcash">
                                <span class="sub-option-icon">📱</span>
                                <span>GCash</span>
                            </label>
                        </div>
                        <div class="sub-option" onclick="event.stopPropagation(); selectSubOption('paymaya', 'e-wallet');">
                            <input type="radio" name="ewallet_sub_option" value="paymaya" id="paymaya" onchange="selectSubOption('paymaya', 'e-wallet')">
                            <label for="paymaya">
                                <span class="sub-option-icon">📱</span>
                                <span>PayMaya</span>
                            </label>
                        </div>
                        <div class="sub-option" onclick="event.stopPropagation(); selectSubOption('paypal', 'e-wallet');">
                            <input type="radio" name="ewallet_sub_option" value="paypal" id="paypal" onchange="selectSubOption('paypal', 'e-wallet')">
                            <label for="paypal">
                                <span class="sub-option-icon">📱</span>
                                <span>PayPal</span>
                            </label>
                        </div>
                        <div class="sub-option" onclick="event.stopPropagation(); selectSubOption('grabpay', 'e-wallet');">
                            <input type="radio" name="ewallet_sub_option" value="grabpay" id="grabpay" onchange="selectSubOption('grabpay', 'e-wallet')">
                            <label for="grabpay">
                                <span class="sub-option-icon">📱</span>
                                <span>GrabPay</span>
                            </label>
                        </div>
                        <div class="reference-wrapper" id="ewalletReferenceWrapper">
                            <!-- GCash Fields -->
                            <div id="gcashInputSection" style="display: none;">
                                <label class="reference-label" for="gcashNumber">GCash Phone Number (Philippine Number) *</label>
                                <input type="text" name="gcash_number" id="gcashNumber" class="reference-input" placeholder="09123456789" maxlength="11" pattern="09[0-9]{9}">
                                <small class="reference-hint">Enter your Philippine phone number registered with GCash (11 digits, starts with 09)</small>
                            </div>
                            
                            <!-- PayPal Fields -->
                            <div id="paypalInputSection" style="display: none;">
                                <label class="reference-label" for="paypalEmail">PayPal Email *</label>
                                <input type="email" name="paypal_email" id="paypalEmail" class="reference-input" placeholder="your.email@example.com">
                                <small class="reference-hint">Enter your PayPal email address</small>
                            </div>
                            
                            <!-- Other E-Wallet Fields (PayMaya, GrabPay, etc.) -->
                            <div id="otherEwalletSection" style="display: none;">
                                <div id="gcashReferenceMessage" class="reference-hint" style="display: none; margin-bottom: 10px;"></div>
                                <div id="gcashPayLink" style="display: none; margin-bottom: 15px;">
                                    <a href="https://www.gcash.com/pay" target="_blank" class="gcash-pay-link" style="display: inline-block; padding: 12px 24px; background: #0070F3; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; text-align: center;">Click here to pay (Prototype)</a>
                                </div>
                                <small class="reference-hint" style="display:block; margin-bottom:10px;">Prototype payment link is currently available for GCash only. For other wallets, please enter the reference manually.</small>
                                <label class="reference-label" for="ewalletReference">E-Wallet Reference</label>
                                <input type="text" name="ewallet_reference" id="ewalletReference" class="reference-input" placeholder="Enter transaction reference (e.g., GCASH123456)" maxlength="20">
                                <small class="reference-hint">For demo purposes you can type any value like GCASH123456. In production, enter the reference from your e-wallet receipt.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn-pay" id="payButton" disabled>Complete Payment</button>
        </form>
    </div>
    
    <script>
        let selectedMainType = '';
        let selectedSubOption = '';
        let selectedSavedMethod = null;
        
        const referenceBanner = document.getElementById('referenceBanner');
        let gcashGeneratedReference = '';
        
        // Payment methods data from PHP
        const savedPaymentMethods = <?= json_encode($savedPaymentMethods) ?>;
        const defaultPaymentMethod = <?= json_encode($defaultPaymentMethod) ?>;

        function showReferenceBanner(message) {
            if (referenceBanner) {
                referenceBanner.innerHTML = message;
                referenceBanner.style.display = 'block';
            }
        }

        function hideReferenceBanner() {
            if (referenceBanner) {
                referenceBanner.style.display = 'none';
                referenceBanner.textContent = '';
            }
        }
        
        // Function to handle saved payment method selection
        function selectSavedPaymentMethod(method) {
            selectedSavedMethod = method;
            document.getElementById('savedPaymentMethodId').value = method.payment_method_id;
            
            // Unselect manual payment methods
            document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                radio.checked = false;
            });
            document.querySelectorAll('input[name="credit_sub_option"]').forEach(radio => {
                radio.checked = false;
            });
            document.querySelectorAll('input[name="ewallet_sub_option"]').forEach(radio => {
                radio.checked = false;
            });
            document.querySelectorAll('.payment-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Set the radio button for this saved method
            const radio = document.querySelector(`input[name="saved_payment_method"][value="${method.payment_method_id}"]`);
            if (radio) {
                radio.checked = true;
            }
            
            // Handle based on payment type
            if (method.payment_type === 'credit-card') {
            // Auto-fill credit card details
            selectedMainType = 'credit-card';
            selectedSubOption = 'visa'; // Default to visa
            document.getElementById('paymentType').value = 'credit-card-saved';

            // Show credit card section
            const creditSubOptions = document.getElementById('creditSubOptions');
            if (creditSubOptions) creditSubOptions.style.display = 'block';
            const cardWrapper = document.getElementById('cardReferenceWrapper');
            if (cardWrapper) cardWrapper.classList.add('active');

            // Auto-fill card details (last 4 digits)
            const cardNumber = method.card_number.replace(/\s/g, '');
            document.getElementById('cardReference').value = cardNumber.slice(-4);

            // Convert MM/YYYY to MM/YY format
            let expiry = method.card_expiry;
            if (expiry && expiry.length >= 7 && expiry.indexOf('/') === 2) {
                const parts = expiry.split('/');
                if (parts.length === 2 && parts[1].length === 4) {
                    expiry = parts[0] + '/' + parts[1].substring(2);
                }
            }
            document.getElementById('cardExpiry').value = expiry;
            document.getElementById('cardCVV').value = method.card_cvv;

            // Hide all e-wallet sections
            document.getElementById('ewalletSubOptions').style.display = 'none';
            document.getElementById('gcashInputSection').style.display = 'none';
            document.getElementById('paypalInputSection').style.display = 'none';
            document.getElementById('otherEwalletSection').style.display = 'none';

        } else if (['gcash', 'paypal', 'paymaya', 'grabpay'].includes(method.payment_type)) {
            // Handle all e-wallets
            selectedMainType = 'e-wallet';
            selectedSubOption = method.payment_type;
            document.getElementById('paymentType').value = method.payment_type;

            // Show e-wallet section
            const ewalletSubOptions = document.getElementById('ewalletSubOptions');
            if (ewalletSubOptions) ewalletSubOptions.style.display = 'block';

            // Hide credit card section
            document.getElementById('creditSubOptions').style.display = 'none';
            const cardWrapper = document.getElementById('cardReferenceWrapper');
            if (cardWrapper) cardWrapper.classList.remove('active');

            // Reset all e-wallet input sections
            document.getElementById('gcashInputSection').style.display = 'none';
            document.getElementById('paypalInputSection').style.display = 'none';
            document.getElementById('otherEwalletSection').style.display = 'none';

            if (method.payment_type === 'gcash') {
                document.getElementById('gcash').checked = true;
                document.getElementById('gcashInputSection').style.display = 'block';
                document.getElementById('gcashNumber').value = method.gcash_number || '';

            } else if (method.payment_type === 'paypal') {
                document.getElementById('paypal').checked = true;
                document.getElementById('paypalInputSection').style.display = 'block';
                document.getElementById('paypalEmail').value = method.paypal_email || '';

            } else if (method.payment_type === 'paymaya' || method.payment_type === 'grabpay') {
            const radio = document.getElementById(method.payment_type);
            if (radio) radio.checked = true;

            // Show e-wallet section
            const ewalletSubOptions = document.getElementById('ewalletSubOptions');
            if (ewalletSubOptions) ewalletSubOptions.style.display = 'block';

            // Hide credit card section
            document.getElementById('creditSubOptions').style.display = 'none';
            const cardWrapper = document.getElementById('cardReferenceWrapper');
            if (cardWrapper) cardWrapper.classList.remove('active');

            // Hide the generic reference section
            const otherEwalletSection = document.getElementById('otherEwalletSection');
            if (otherEwalletSection) otherEwalletSection.style.display = 'none';

            // Use GCash input section to auto-fill number
            const gcashInputSection = document.getElementById('gcashInputSection');
            if (gcashInputSection) {
                gcashInputSection.style.display = 'block';
                const gcashLabel = gcashInputSection.querySelector('label');
                const gcashNumberInput = document.getElementById('gcashNumber');

                if (method.payment_type === 'paymaya') {
                    if (gcashLabel) gcashLabel.textContent = 'PayMaya Number';
                    if (gcashNumberInput) gcashNumberInput.value = method.paymaya_number || '';
                } else if (method.payment_type === 'grabpay') {
                    if (gcashLabel) gcashLabel.textContent = 'GrabPay Number';
                    if (gcashNumberInput) gcashNumberInput.value = method.grabpay_number || '';
                    }
                }
            }
        }

        updatePayButton();

        }

        function selectPayment(type, eventElement) {
            selectedMainType = type;
            selectedSubOption = '';
            selectedSavedMethod = null; // Clear saved method selection
            
            // Clear saved payment method selection
            document.getElementById('savedPaymentMethodId').value = '';
            document.querySelectorAll('input[name="saved_payment_method"]').forEach(radio => {
                radio.checked = false;
            });

            const radio = document.querySelector(`input[value="${type}"]`);
            if (radio) {
                radio.checked = true;
            }

            document.querySelectorAll('.payment-option').forEach(opt => {
                opt.classList.remove('selected');
            });

            if (eventElement && eventElement.currentTarget) {
                eventElement.currentTarget.classList.add('selected');
            } else {
                document.querySelectorAll('.payment-option').forEach(opt => {
                    if (opt.querySelector(`input[value="${type}"]`)) {
                        opt.classList.add('selected');
                    }
                });
            }

            // Hide sub-option wrappers initially
            const creditSubOptions = document.getElementById('creditSubOptions');
            const ewalletSubOptions = document.getElementById('ewalletSubOptions');
            const cardWrapper = document.getElementById('cardReferenceWrapper');
            const ewalletWrapper = document.getElementById('ewalletReferenceWrapper');
            const cardLabel = document.querySelector('label.reference-label[for="cardReference"]');
            const walletLabel = document.querySelector('label.reference-label[for="ewalletReference"]');

            if (creditSubOptions) creditSubOptions.style.display = (type === 'credit-card') ? 'block' : 'none';
            if (ewalletSubOptions) ewalletSubOptions.style.display = (type === 'e-wallet') ? 'block' : 'none';
            if (cardWrapper) cardWrapper.classList.remove('active');
            if (ewalletWrapper) ewalletWrapper.classList.remove('active');
            if (cardLabel) cardLabel.textContent = 'Card Reference';
            if (walletLabel) walletLabel.textContent = 'E-Wallet Reference';
            hideReferenceBanner();

            document.getElementById('paymentType').value = '';
            document.getElementById('referenceNumber').value = '';
            const cardRefInput = document.getElementById('cardReference');
            const walletRefInput = document.getElementById('ewalletReference');
            if (cardRefInput) {
                cardRefInput.value = '';
                cardRefInput.dataset.subOption = '';
            }
            if (walletRefInput) {
                walletRefInput.value = '';
                walletRefInput.dataset.subOption = '';
            }

            updatePayButton();
        }
        
        function selectSubOption(subOption, mainType) {
            selectedSubOption = subOption;
            selectedMainType = mainType;
            selectedSavedMethod = null; // Clear saved method selection
            
            // Clear saved payment method selection
            document.getElementById('savedPaymentMethodId').value = '';
            document.querySelectorAll('input[name="saved_payment_method"]').forEach(radio => {
                radio.checked = false;
            });

            const paymentTypeInput = document.getElementById('paymentType');
            paymentTypeInput.value = subOption;

            const cardWrapper = document.getElementById('cardReferenceWrapper');
            const ewalletWrapper = document.getElementById('ewalletReferenceWrapper');
            const cardRefInput = document.getElementById('cardReference');
            const walletRefInput = document.getElementById('ewalletReference');
            const gcashInputSection = document.getElementById('gcashInputSection');
            const paypalInputSection = document.getElementById('paypalInputSection');
            const otherEwalletSection = document.getElementById('otherEwalletSection');

            const displayNames = {
                visa: 'Visa',
                mastercard: 'Mastercard',
                amex: 'American Express',
                discover: 'Discover',
                gcash: 'GCash',
                paymaya: 'PayMaya',
                paypal: 'PayPal',
                grabpay: 'GrabPay'
            };
            const friendlyName = displayNames[subOption] || subOption;

            if (mainType === 'credit-card') {
                if (cardWrapper) cardWrapper.classList.add('active');
                if (ewalletWrapper) ewalletWrapper.classList.remove('active');
                if (cardRefInput) {
                    cardRefInput.placeholder = `Enter last 4 digits (${friendlyName})`;
                    cardRefInput.dataset.subOption = subOption;
                    cardRefInput.focus();
                }
                const cardLabel = document.querySelector('label.reference-label[for="cardReference"]');
                if (cardLabel) {
                    cardLabel.textContent = `${friendlyName} Reference`;
                }
                // Hide e-wallet sections
                if (gcashInputSection) gcashInputSection.style.display = 'none';
                if (paypalInputSection) paypalInputSection.style.display = 'none';
                if (otherEwalletSection) otherEwalletSection.style.display = 'none';
                if (walletRefInput) {
                    walletRefInput.value = '';
                    walletRefInput.dataset.subOption = '';
                }
            } else if (mainType === 'e-wallet') {
                if (ewalletWrapper) ewalletWrapper.classList.add('active');
                if (cardWrapper) cardWrapper.classList.remove('active');
                
                // Handle GCash
                if (subOption === 'gcash') {
                    if (gcashInputSection) {
                        gcashInputSection.style.display = 'block';
                        const gcashNumberInput = document.getElementById('gcashNumber');
                        if (gcashNumberInput) gcashNumberInput.focus();
                    }
                    if (paypalInputSection) paypalInputSection.style.display = 'none';
                    if (otherEwalletSection) otherEwalletSection.style.display = 'none';
                } 
                // Handle PayPal
                else if (subOption === 'paypal') {
                    if (paypalInputSection) {
                        paypalInputSection.style.display = 'block';
                        const paypalEmailInput = document.getElementById('paypalEmail');
                        if (paypalEmailInput) paypalEmailInput.focus();
                    }
                    if (gcashInputSection) gcashInputSection.style.display = 'none';
                    if (otherEwalletSection) otherEwalletSection.style.display = 'none';
                } 
                // Handle other e-wallets (PayMaya, GrabPay, etc.)
                else {
                    if (otherEwalletSection) {
                        otherEwalletSection.style.display = 'block';
                        if (walletRefInput) {
                            walletRefInput.placeholder = `Enter ${friendlyName} reference (e.g., ${subOption.toUpperCase()}123456)`;
                            walletRefInput.dataset.subOption = subOption;
                            walletRefInput.focus();
                        }
                    }
                    if (gcashInputSection) gcashInputSection.style.display = 'none';
                    if (paypalInputSection) paypalInputSection.style.display = 'none';
                    
                    const walletLabel = document.querySelector('label.reference-label[for="ewalletReference"]');
                    if (walletLabel) {
                        walletLabel.textContent = `${friendlyName} Reference`;
                    }
                }
                
                if (cardRefInput) {
                    cardRefInput.value = '';
                    cardRefInput.dataset.subOption = '';
                }
                
                // Show GCash payment link if GCash is selected
                const gcashPayLink = document.getElementById('gcashPayLink');
                if (gcashPayLink) {
                    const isGcash = subOption === 'gcash';
                    gcashPayLink.style.display = isGcash ? 'block' : 'none';
                    const gcashMsg = document.getElementById('gcashReferenceMessage');
                    if (!isGcash && gcashMsg) {
                        gcashMsg.style.display = 'none';
                        gcashMsg.textContent = '';
                    }
                    if (!isGcash) {
                        hideReferenceBanner();
                    }
                }
            }

            updatePayButton();
        }
        
        function updatePayButton() {
            const payButton = document.getElementById('payButton');
            const savedMethodId = document.getElementById('savedPaymentMethodId').value;
            
            // Enable button if saved payment method is selected OR manual payment method is complete
            if (savedMethodId || (selectedMainType && selectedSubOption)) {
                // Additional validation for manual methods
                if (!savedMethodId) {
                    if (selectedMainType === 'credit-card') {
                        const cardRef = document.getElementById('cardReference').value;
                        const cardExpiry = document.getElementById('cardExpiry').value;
                        const cardCVV = document.getElementById('cardCVV').value;
                        if (cardRef.length >= 4 && cardExpiry.length >= 5 && cardCVV.length >= 3) {
                            payButton.disabled = false;
                        } else {
                            payButton.disabled = true;
                        }
                    } else if (selectedMainType === 'e-wallet') {
                        if (selectedSubOption === 'gcash') {
                            const gcashNumber = document.getElementById('gcashNumber').value;
                            if (gcashNumber.length >= 11) {
                                payButton.disabled = false;
                            } else {
                                payButton.disabled = true;
                            }
                        } else if (selectedSubOption === 'paypal') {
                            const paypalEmail = document.getElementById('paypalEmail').value;
                            if (paypalEmail && paypalEmail.includes('@')) {
                                payButton.disabled = false;
                            } else {
                                payButton.disabled = true;
                            }
                        } else {
                            // Other e-wallets
                            payButton.disabled = false;
                        }
                    } else {
                        payButton.disabled = true;
                    }
                } else {
                    // Saved method selected
                    payButton.disabled = false;
                }
            } else {
                payButton.disabled = true;
            }
        }
        
        // Handle reference number input for credit card
        const cardReferenceInput = document.getElementById('cardReference');
        const ewalletReferenceInput = document.getElementById('ewalletReference');
        document.querySelectorAll('.reference-wrapper').forEach(wrapper => {
            ['click', 'focus'].forEach(evt => {
                wrapper.addEventListener(evt, event => event.stopPropagation());
            });
        });

        if (cardReferenceInput) {
            ['click', 'focus'].forEach(evt => {
                cardReferenceInput.addEventListener(evt, event => event.stopPropagation());
            });
            cardReferenceInput.addEventListener('input', function() {
                const subOption = this.dataset.subOption || selectedSubOption;
                if (this.value.length >= 4 && subOption) {
                    document.getElementById('referenceNumber').value = subOption.toUpperCase() + '-' + this.value;
                } else {
                    document.getElementById('referenceNumber').value = '';
                }
            });
        }
        
        // Handle card expiry input formatting
        const cardExpiryInput = document.getElementById('cardExpiry');
        if (cardExpiryInput) {
            cardExpiryInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                e.target.value = value;
            });
        }
        
        // Handle CVV input
        const cardCVVInput = document.getElementById('cardCVV');
        if (cardCVVInput) {
            cardCVVInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        }

        // Handle reference number input for e-wallet
        if (ewalletReferenceInput) {
            ['click', 'focus'].forEach(evt => {
                ewalletReferenceInput.addEventListener(evt, event => event.stopPropagation());
            });
            ewalletReferenceInput.addEventListener('input', function() {
                const subOption = this.dataset.subOption || selectedSubOption;
                if (this.value.length > 0 && subOption) {
                    document.getElementById('referenceNumber').value = subOption.toUpperCase() + '-' + this.value;
                } else {
                    document.getElementById('referenceNumber').value = '';
                }
                updatePayButton();
            });
        }
        
        // Handle GCash number input
        const gcashNumberInput = document.getElementById('gcashNumber');
        if (gcashNumberInput) {
            ['click', 'focus'].forEach(evt => {
                gcashNumberInput.addEventListener(evt, event => event.stopPropagation());
            });
            gcashNumberInput.addEventListener('input', function() {
                // Only allow digits
                this.value = this.value.replace(/\D/g, '');
                updatePayButton();
            });
        }
        
        // Handle PayPal email input
        const paypalEmailInput = document.getElementById('paypalEmail');
        if (paypalEmailInput) {
            ['click', 'focus'].forEach(evt => {
                paypalEmailInput.addEventListener(evt, event => event.stopPropagation());
            });
            paypalEmailInput.addEventListener('input', function() {
                updatePayButton();
            });
        }
        
        // GCash link click handler to auto-generate reference
        const gcashPayLinkContainer = document.getElementById('gcashPayLink');
        function receiveGcashPayment(ref) {
            gcashGeneratedReference = ref;
            const bannerMessage = `GCash payment successful. Your reference is <strong>${ref}</strong>.`;
            const gcashMsg = document.getElementById('gcashReferenceMessage');
            if (gcashMsg) {
                gcashMsg.innerHTML = bannerMessage;
                gcashMsg.style.display = 'block';
            }
            showReferenceBanner(bannerMessage);
            if (ewalletReferenceInput) {
                ewalletReferenceInput.value = ref.replace('GCASH-', '');
                ewalletReferenceInput.dataset.subOption = 'gcash';
            }
            document.getElementById('referenceNumber').value = ref;
        }

        window.receiveGcashPayment = receiveGcashPayment;

        if (gcashPayLinkContainer) {
            const gcashAnchor = gcashPayLinkContainer.querySelector('a');
            if (gcashAnchor) {
                gcashAnchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const payWin = window.open('', 'gcashPayWindow', 'width=420,height=520');
                    if (!payWin) {
                        alert('Please allow popups to continue with GCash payment.');
                        return;
                    }
                    const amount = <?= json_encode(number_format($grandTotal, 2)) ?>;
                    payWin.document.write(`
                        <html>
                            <head>
                                <title>GCash Payment</title>
                                <style>
                                    body { font-family: Arial, sans-serif; padding: 30px; text-align: center; background: #0d1b26; color: #fff; }
                                    .amount { font-size: 32px; font-weight: 700; margin: 20px 0; }
                                    button { padding: 12px 28px; border: none; border-radius: 25px; background: #00bfff; color: #fff; font-size: 16px; cursor: pointer; }
                                    button:hover { background: #0099cc; }
                                    .note { margin-top: 15px; font-size: 13px; color: #b8d7ff; }
                                </style>
                            </head>
                            <body>
                                <h2>GCash Prototype Payment</h2>
                                <p class="amount">₱ ${amount}</p>
                                <button id="payNow">Pay ₱ ${amount}</button>
                                <p class="note">This is a demo flow. After clicking pay, a reference number will be sent back to Ticketix.</p>
                                <script>
                                    document.getElementById('payNow').addEventListener('click', function() {
                                        var generatedRef = 'GCASH-' + Math.floor(100000 + Math.random() * 900000);
                                        if (window.opener && typeof window.opener.receiveGcashPayment === 'function') {
                                            window.opener.receiveGcashPayment(generatedRef);
                                        }
                                        document.body.innerHTML = '<h3>Payment successful!</h3><p>Your reference is <strong>' + generatedRef + '</strong></p><p>You can close this window.</p>';
                                        setTimeout(function(){ window.close(); }, 2500);
                                    });
                                <\/script>
                            </body>
                        </html>
                    `);
                });
            }
        }

        // Handle form submission
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const savedMethodId = document.getElementById('savedPaymentMethodId').value;
            const paymentType = document.getElementById('paymentType').value;
            
            // Check if saved payment method is selected
            if (savedMethodId) {
                // Saved method selected - validate based on payment type
                if (paymentType === 'credit-card-saved') {
                    // Credit card saved method - ensure fields are filled
                    const cardRef = document.getElementById('cardReference').value;
                    const cardExpiry = document.getElementById('cardExpiry').value;
                    const cardCVV = document.getElementById('cardCVV').value;
                    if (!cardRef || cardRef.length < 4 || !cardExpiry || cardExpiry.length < 5 || !cardCVV || cardCVV.length < 3) {
                        e.preventDefault();
                        alert('Please ensure all credit card fields are filled.');
                        return false;
                    }
                } else if (paymentType === 'gcash' || paymentType === 'paypal') {
                    // GCash or PayPal saved method - validate input fields
                    if (paymentType === 'gcash') {
                        const gcashNumber = document.getElementById('gcashNumber').value;
                        if (!gcashNumber || gcashNumber.length < 11) {
                            e.preventDefault();
                            alert('Please enter a valid GCash phone number.');
                            return false;
                        }
                    } else if (paymentType === 'paypal') {
                        const paypalEmail = document.getElementById('paypalEmail').value;
                        if (!paypalEmail || !paypalEmail.includes('@')) {
                            e.preventDefault();
                            alert('Please enter a valid PayPal email address.');
                            return false;
                        }
                    }
                }
            } else {
                // Manual payment method selection
                if (!paymentType || !selectedMainType || !selectedSubOption) {
                    e.preventDefault();
                    alert('Please select a payment method and sub-option.');
                    return false;
                }
                
                // Validate manual GCash payment
                if (selectedMainType === 'e-wallet' && selectedSubOption === 'gcash') {
                    const gcashNumber = document.getElementById('gcashNumber').value;
                    if (!gcashNumber || gcashNumber.length < 11) {
                        e.preventDefault();
                        alert('Please enter a valid GCash phone number.');
                        return false;
                    }
                }
                
                // Validate manual PayPal payment
                if (selectedMainType === 'e-wallet' && selectedSubOption === 'paypal') {
                    const paypalEmail = document.getElementById('paypalEmail').value;
                    if (!paypalEmail || !paypalEmail.includes('@')) {
                        e.preventDefault();
                        alert('Please enter a valid PayPal email address.');
                        return false;
                    }
                }
            }
            
            // Generate reference number if not provided
            const refNumber = document.getElementById('referenceNumber').value;
            if (!refNumber || refNumber === '') {
                let prefix = 'PAY';
                if (savedMethodId && paymentType === 'credit-card-saved') {
                    prefix = 'CARD';
                    const digits = (cardReferenceInput && cardReferenceInput.value.length >= 4)
                        ? cardReferenceInput.value
                        : String(Math.floor(1000 + Math.random() * 9000));
                    document.getElementById('referenceNumber').value = prefix + '-' + digits;
                } else if (savedMethodId && (paymentType === 'gcash' || paymentType === 'paypal')) {
                    prefix = paymentType.toUpperCase();
                    const walletDigits = String(Math.floor(100000 + Math.random() * 900000));
                    document.getElementById('referenceNumber').value = prefix + '-' + walletDigits;
                } else if (selectedMainType === 'credit-card') {
                    prefix = selectedSubOption ? selectedSubOption.toUpperCase() : 'CARD';
                    const digits = (cardReferenceInput && cardReferenceInput.value.length >= 4)
                        ? cardReferenceInput.value
                        : String(Math.floor(1000 + Math.random() * 9000));
                    document.getElementById('referenceNumber').value = prefix + '-' + digits;
                    if (cardReferenceInput && cardReferenceInput.value.length < 4) {
                        cardReferenceInput.value = digits;
                    }
                } else if (selectedMainType === 'e-wallet') {
                    prefix = selectedSubOption ? selectedSubOption.toUpperCase() : 'PAY';
                    const walletDigits = String(Math.floor(100000 + Math.random() * 900000));
                    document.getElementById('referenceNumber').value = prefix + '-' + walletDigits;
                    if (ewalletReferenceInput && ewalletReferenceInput.value.length === 0) {
                        ewalletReferenceInput.value = walletDigits;
                    }
                }
            }
            
            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
        });
    </script>
</body>
</html>

