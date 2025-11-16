<?php
session_start();
require_once __DIR__ . '/config.php';
$conn = getDBConnection();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? $_SESSION['acc_id'] ?? null;

if (!$userId) {
    header("Location: login.php");
    exit();
}

$message = '';
$messageType = '';

// Handle payment method operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add payment method

    if (isset($_POST['add_payment_method'])) {
        $paymentType = $_POST['payment_type'] ?? '';
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        
        if ($isDefault) {
            $stmt = $conn->prepare("UPDATE USER_PAYMENT_METHODS SET is_default = 0 WHERE acc_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
        }

        if ($paymentType === 'credit-card') {
            $cardNumber = trim($_POST['card_number'] ?? '');
            $cardName = trim($_POST['card_name'] ?? '');
            $cardExpiry = trim($_POST['card_expiry'] ?? '');
            $cardCVV = trim($_POST['card_cvv'] ?? '');
            
            if (empty($cardNumber) || empty($cardName) || empty($cardExpiry) || empty($cardCVV)) {
                $message = "All credit card fields are required.";
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO USER_PAYMENT_METHODS (acc_id, payment_type, card_number, card_name, card_expiry, card_cvv, is_default) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssi", $userId, $paymentType, $cardNumber, $cardName, $cardExpiry, $cardCVV, $isDefault);
                if ($stmt->execute()) {
                    $message = "Credit card added successfully!";
                    $messageType = 'success';
                } else {
                    $message = "Error adding credit card: " . $conn->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
        } elseif ($paymentType === 'gcash' || $paymentType === 'grabpay' || $paymentType === 'paymaya') {
            $numberField = $paymentType . "_number";
            $number = trim($_POST[$numberField] ?? '');
            
            if (empty($number)) {
                $message = ucfirst($paymentType) . " number is required.";
                $messageType = 'error';
            } elseif (!preg_match('/^(09\d{9}|63\d{10})$/', $number)) {
                $message = "Please enter a valid Philippine number (09XXXXXXXXX or 63XXXXXXXXXX).";
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO USER_PAYMENT_METHODS (acc_id, payment_type, gcash_number, is_default) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $userId, $paymentType, $number, $isDefault);
                if ($stmt->execute()) {
                    $message = ucfirst($paymentType) . " added successfully!";
                    $messageType = 'success';
                } else {
                    $message = "Error adding " . ucfirst($paymentType) . ": " . $conn->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
        } elseif ($paymentType === 'paypal') {
            $paypalEmail = trim($_POST['paypal_email'] ?? '');
            if (empty($paypalEmail) || !filter_var($paypalEmail, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid PayPal email address.";
                $messageType = 'error';
            } else {
                $stmt = $conn->prepare("INSERT INTO USER_PAYMENT_METHODS (acc_id, payment_type, paypal_email, is_default) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $userId, $paymentType, $paypalEmail, $isDefault);
                if ($stmt->execute()) {
                    $message = "PayPal added successfully!";
                    $messageType = 'success';
                } else {
                    $message = "Error adding PayPal: " . $conn->error;
                    $messageType = 'error';
                }
                $stmt->close();
            }
        }
    }

    
    // Delete payment method
    if (isset($_POST['delete_payment_method'])) {
        $methodId = intval($_POST['method_id'] ?? 0);
        if ($methodId > 0) {
            $stmt = $conn->prepare("DELETE FROM USER_PAYMENT_METHODS WHERE payment_method_id = ? AND acc_id = ?");
            $stmt->bind_param("ii", $methodId, $userId);
            if ($stmt->execute()) {
                $message = "Payment method deleted successfully!";
                $messageType = 'success';
            } else {
                $message = "Error deleting payment method: " . $conn->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
    
    // Set default payment method
    if (isset($_POST['set_default'])) {
        $methodId = intval($_POST['method_id'] ?? 0);
        if ($methodId > 0) {
            // Remove default flag from all methods
            $stmt = $conn->prepare("UPDATE USER_PAYMENT_METHODS SET is_default = 0 WHERE acc_id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();
            
            // Set selected method as default
            $stmt = $conn->prepare("UPDATE USER_PAYMENT_METHODS SET is_default = 1 WHERE payment_method_id = ? AND acc_id = ?");
            $stmt->bind_param("ii", $methodId, $userId);
            if ($stmt->execute()) {
                $message = "Default payment method updated!";
                $messageType = 'success';
            } else {
                $message = "Error setting default payment method: " . $conn->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $contNo = trim($_POST['contNo'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $birthdate = $_POST['birthdate'] ?? null;
    
    if (empty($firstName)) {
        $message = "First name is required.";
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("UPDATE USER_ACCOUNT SET firstName = ?, lastName = ?, contNo = ?, address = ? WHERE acc_id = ?");
        $stmt->bind_param("ssssi", $firstName, $lastName, $contNo, $address, $userId);
        
        if ($stmt->execute()) {
            $message = "Profile updated successfully!";
            $messageType = 'success';
            // Update session name
            $_SESSION['user_name'] = $firstName . ' ' . $lastName;
        } else {
            $message = "Error updating profile: " . $conn->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Get user data
$stmt = $conn->prepare("SELECT acc_id, firstName, lastName, email, contNo, address, birthdate, time_created FROM USER_ACCOUNT WHERE acc_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get user payment methods
$stmt = $conn->prepare("SELECT * FROM USER_PAYMENT_METHODS WHERE acc_id = ? ORDER BY is_default DESC, created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$paymentMethodsResult = $stmt->get_result();
$paymentMethods = [];
while ($row = $paymentMethodsResult->fetch_assoc()) {
    $paymentMethods[] = $row;
}
$stmt->close();
$conn->close();

if (!$user) {
    header("Location: login.php");
    exit();
}

// Format birthdate for input
$birthdateFormatted = $user['birthdate'] ? date('Y-m-d', strtotime($user['birthdate'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Ticketix</title>
    <link rel="stylesheet" href="css/ticketix-main.css">
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>
    <div class="profile-container">
        <div class="page-header">
            <div class="profile-avatar">
                <?php
                $initials = strtoupper(substr($user['firstName'], 0, 1) . substr($user['lastName'], 0, 1));
                echo $initials;
                ?>
            </div>
            <h1>My Profile</h1>
            <p>Manage your personal information</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="account-info">
            <div class="account-info-item">
                <span class="account-info-label">Email:</span>
                <span class="account-info-value"><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <div class="account-info-item">
                <span class="account-info-label">Account Created:</span>
                <span class="account-info-value"><?= date('F d, Y', strtotime($user['time_created'])) ?></span>
            </div>
        </div>

        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">First Name *</label>
                    <input type="text" id="firstName" name="firstName" value="<?= htmlspecialchars($user['firstName']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name (Optional)</label>
                    <input type="text" id="lastName" name="lastName" value="<?= htmlspecialchars($user['lastName'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="contNo">Contact Number</label>
                    <input type="text" id="contNo" name="contNo" value="<?= htmlspecialchars($user['contNo'] ?? '') ?>" placeholder="09123456789">
                </div>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" placeholder="Enter your address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <a href="TICKETIX NI CLAIRE.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </div>
        </form>

        <!-- Payment Methods Section -->
        <div class="payment-methods-section" style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #e0e0e0;">
            <h2 style="margin-bottom: 20px; color: #333;">Manage Payment Methods</h2>
            
            <!-- Display existing payment methods -->
            <?php if (!empty($paymentMethods)): ?>
                <div class="existing-payment-methods" style="margin-bottom: 30px;">
                    <?php foreach ($paymentMethods as $method): ?>
                        <div class="payment-method-item" style="padding: 15px; margin-bottom: 15px; border: 2px solid #e0e0e0; border-radius: 8px; background: #f9f9f9;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <?php if ($method['payment_type'] === 'credit-card'): ?>
                                        <strong>💳 Credit Card</strong>
                                        <div style="margin-top: 5px; color: #666;">
                                            Card Name: <?= htmlspecialchars($method['card_name']) ?><br>
                                            Card Number: ****<?= htmlspecialchars(substr($method['card_number'], -4)) ?><br>
                                            Expiry: <?= htmlspecialchars($method['card_expiry']) ?>
                                        </div>
                                    <?php elseif ($method['payment_type'] === 'gcash'): ?>
                                        <strong>📱 GCash</strong>
                                        <div style="margin-top: 5px; color: #666;">
                                            Number: <?= htmlspecialchars($method['gcash_number']) ?>
                                        </div>
                                    <?php elseif ($method['payment_type'] === 'grabpay'): ?>
                                        <strong>📱 GrabPay</strong>
                                        <div style="margin-top: 5px; color: #666;">
                                            Number: <?= htmlspecialchars($method['gcash_number']) ?>
                                        </div>
                                    <?php elseif ($method['payment_type'] === 'paymaya'): ?>
                                        <strong>📱 PayMaya</strong>
                                        <div style="margin-top: 5px; color: #666;">
                                            Number: <?= htmlspecialchars($method['gcash_number']) ?>
                                        </div>
                                    <?php elseif ($method['payment_type'] === 'paypal'): ?>
                                        <strong>📧 PayPal</strong>
                                        <div style="margin-top: 5px; color: #666;">
                                            Email: <?= htmlspecialchars($method['paypal_email']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($method['is_default']): ?>
                                        <span style="display: inline-block; margin-top: 5px; padding: 3px 10px; background: #667eea; color: white; border-radius: 12px; font-size: 0.85em;">Default</span>
                                    <?php endif; ?>

                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <?php if (!$method['is_default']): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Set this as default payment method?');">
                                            <input type="hidden" name="method_id" value="<?= $method['payment_method_id'] ?>">
                                            <button type="submit" name="set_default" class="btn" style="padding: 8px 15px; font-size: 0.9em; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">Set Default</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this payment method?');">
                                        <input type="hidden" name="method_id" value="<?= $method['payment_method_id'] ?>">
                                        <button type="submit" name="delete_payment_method" class="btn" style="padding: 8px 15px; font-size: 0.9em; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #666; margin-bottom: 20px;">No payment methods saved yet. Add one below.</p>
            <?php endif; ?>
            
            <!-- Add Payment Method Form -->
            <div class="add-payment-method" style="padding: 20px; border: 2px solid #e0e0e0; border-radius: 10px; background: #f9f9f9;">
                <h3 style="margin-bottom: 15px; color: #333;">Add Payment Method</h3>
                <form method="POST" id="addPaymentForm">
                    <div class="form-group">
                        <label for="payment_type_select">Payment Method Type *</label>
                        <select id="payment_type_select" name="payment_type" required onchange="togglePaymentFields()" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1em; box-sizing: border-box;">
                            <option value="">Select Payment Method</option>
                            <option value="credit-card">Credit Card</option>
                            <option value="gcash">GCash</option>
                            <option value="paypal">PayPal</option>
                            <option value="grabpay">GrabPay</option>
                            <option value="paymaya">PayMaya</option>
                        </select>
                    </div>
                    
                    <!-- Credit Card Fields -->
                    <div id="credit-card-fields" style="display: none;">
                        <div class="form-group">
                            <label for="card_name">Cardholder Name *</label>
                            <input type="text" id="card_name" name="card_name" placeholder="John Doe">
                        </div>
                        <div class="form-group">
                            <label for="card_number">Card Number *</label>
                            <input type="text" id="card_number" name="card_number" placeholder="1234 1234 1234 1234" maxlength="19" pattern="([0-9]{4} ?){3}[0-9]{4}">

                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="card_expiry">Expiry Date (MM/YYYY) *</label>
                                <input type="text" id="card_expiry" name="card_expiry" placeholder="12/2025" maxlength="7" pattern="[0-9]{2}/[0-9]{4}">
                            </div>
                            <div class="form-group">
                                <label for="card_cvv">CVV *</label>
                                <input type="text" id="card_cvv" name="card_cvv" placeholder="123" maxlength="4" pattern="[0-9]{3,4}">
                            </div>
                        </div>
                    </div>
                    
                    <!-- GCash Fields -->
                    <div id="gcash-fields" style="display: none;">
                        <div class="form-group">
                            <label for="gcash_number">Philippine Phone Number *</label>
                            <input type="text" id="gcash_number" name="gcash_number" placeholder="09123456789 or 639123456789" maxlength="13" pattern="(09[0-9]{9}|63[0-9]{10})">
                            <small style="color: #666; display: block; margin-top: 5px;">Format: 09XXXXXXXXX (11 digits, starts with 09) or 63XXXXXXXXXX (13 digits, starts with 63)</small>
                        </div>
                    </div>
                    
                    <!-- PayPal Fields -->
                    <div id="paypal-fields" style="display: none;">
                        <div class="form-group">
                            <label for="paypal_email">PayPal Email *</label>
                            <input type="email" id="paypal_email" name="paypal_email" placeholder="your.email@example.com">
                        </div>
                    </div>

                    <!-- GrabPay Fields -->
                    <div id="grabpay-fields" style="display: none;">
                        <div class="form-group">
                            <label for="grabpay_number">GrabPay Number *</label>
                            <input type="text" id="grabpay_number" name="grabpay_number" placeholder="09123456789 or 639123456789" maxlength="13" pattern="(09[0-9]{9}|63[0-9]{10})">
                            <small style="color: #666;">Format: 09XXXXXXXXX or 63XXXXXXXXXX</small>
                        </div>
                    </div>

                    <!-- PayMaya Fields -->
                    <div id="paymaya-fields" style="display: none;">
                        <div class="form-group">
                            <label for="paymaya_number">PayMaya Number *</label>
                            <input type="text" id="paymaya_number" name="paymaya_number" placeholder="09123456789 or 639123456789" maxlength="13" pattern="(09[0-9]{9}|63[0-9]{10})">
                            <small style="color: #666;">Format: 09XXXXXXXXX or 63XXXXXXXXXX</small>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" name="is_default" style="margin-right: 8px; width: auto;">
                            <span>Set as default payment method</span>
                        </label>
                    </div>
                    
                    <button type="submit" name="add_payment_method" class="btn btn-primary">Add Payment Method</button>
                </form>
            </div>
        </div>

        <div class="text-center">
            <a href="TICKETIX NI CLAIRE.php" class="back-link">← Back to Homepage</a>
        </div>
    </div>
    
    <script>
        function togglePaymentFields() {
            const paymentType = document.getElementById('payment_type_select').value;
            document.getElementById('credit-card-fields').style.display = (paymentType === 'credit-card') ? 'block' : 'none';
    document.getElementById('gcash-fields').style.display = (paymentType === 'gcash') ? 'block' : 'none';
    document.getElementById('paypal-fields').style.display = (paymentType === 'paypal') ? 'block' : 'none';
    document.getElementById('grabpay-fields').style.display = (paymentType === 'grabpay') ? 'block' : 'none';
    document.getElementById('paymaya-fields').style.display = (paymentType === 'paymaya') ? 'block' : 'none';
        }
        
        // Format card expiry input
        const cardExpiryInput = document.getElementById('card_expiry');
        if (cardExpiryInput) {
            cardExpiryInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 6);
                }
                e.target.value = value;
            });
        }
        
        // Format card number input (add spaces every 4 digits)
        const cardNumberInput = document.getElementById('card_number');
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                value = value.match(/.{1,4}/g)?.join(' ') || value;
                e.target.value = value;
            });
        }
        
        // Format CVV input
        const cardCVVInput = document.getElementById('card_cvv');
        if (cardCVVInput) {
            cardCVVInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        }
        
        // Format GCash number input
        const gcashNumberInput = document.getElementById('gcash_number');
        if (gcashNumberInput) {
            gcashNumberInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        }
    </script>
</body>
</html>

