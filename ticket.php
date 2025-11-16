<?php
session_start();
require_once __DIR__ . '/config.php';
$conn = getDBConnection();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

$ticketId = intval($_GET['ticket_id'] ?? 0);
$userId = $_SESSION['user_id'] ?? $_SESSION['acc_id'] ?? null; // Support both session variable names

if (!$ticketId || !$userId) {
    header("Location: TICKETIX NI CLAIRE.php");
    exit();
}

// Check if booking_status column exists
$bookingStatusCheck = $conn->query("SHOW COLUMNS FROM RESERVE LIKE 'booking_status'");
$has_booking_status = $bookingStatusCheck && $bookingStatusCheck->num_rows > 0;

// Get ticket details
$ticketQuery = "
    SELECT t.*, r.*, m.title, m.image_poster, ms.show_date, ms.show_hour, 
           p.payment_type, p.amount_paid, p.reference_number
";

// Check if MOVIE_SCHEDULE has branch_id
$msBranchCheck = $conn->query("SHOW COLUMNS FROM MOVIE_SCHEDULE LIKE 'branch_id'");
$msHasBranch = $msBranchCheck && $msBranchCheck->num_rows > 0;

if ($msHasBranch) {
    $ticketQuery .= ", b.branch_name
        FROM TICKET t
        JOIN RESERVE r ON t.reserve_id = r.reservation_id
        JOIN MOVIE_SCHEDULE ms ON r.schedule_id = ms.schedule_id
        JOIN MOVIE m ON ms.movie_show_id = m.movie_show_id
        LEFT JOIN BRANCH b ON ms.branch_id = b.branch_id
        JOIN PAYMENT p ON t.payment_id = p.payment_id
        WHERE t.ticket_id = ? AND r.acc_id = ?";
} else {
    $ticketQuery .= "
        FROM TICKET t
        JOIN RESERVE r ON t.reserve_id = r.reservation_id
        JOIN MOVIE_SCHEDULE ms ON r.schedule_id = ms.schedule_id
        JOIN MOVIE m ON ms.movie_show_id = m.movie_show_id
        JOIN PAYMENT p ON t.payment_id = p.payment_id
        WHERE t.ticket_id = ? AND r.acc_id = ?";
}

$stmt = $conn->prepare($ticketQuery);
// Use user_id from session (which is acc_id from database)
$stmt->bind_param("ii", $ticketId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$ticket = $result->fetch_assoc();
$stmt->close();

if (!$ticket) {
    header("Location: TICKETIX NI CLAIRE.php");
    exit();
}

// Get seats
$stmt = $conn->prepare("
    SELECT s.seat_number, s.seat_type
    FROM RESERVE_SEAT rs
    JOIN SEAT s ON rs.seat_id = s.seat_id
    WHERE rs.reservation_id = ?
");
$stmt->bind_param("i", $ticket['reserve_id']);
$stmt->execute();
$seatsResult = $stmt->get_result();
$seats = [];
while ($row = $seatsResult->fetch_assoc()) {
    $seats[] = $row;
}
$stmt->close();

// Get food items
$stmt = $conn->prepare("
    SELECT f.food_name, tf.quantity, f.food_price
    FROM TICKET_FOOD tf
    JOIN FOOD f ON tf.food_id = f.food_id
    WHERE tf.ticket_id = ?
");
$stmt->bind_param("i", $ticketId);
$stmt->execute();
$foodResult = $stmt->get_result();
$foodItems = [];
while ($row = $foodResult->fetch_assoc()) {
    $foodItems[] = $row;
}
$stmt->close();

// Format show time
$showTime = date('g:i A', strtotime($ticket['show_hour']));
$showDate = date('F d, Y', strtotime($ticket['show_date']));

// Generate QR code URL (using a free QR code API)
$qrData = $ticket['e_ticket_code'] ?? $ticket['ticket_number'] ?? 'TICKET-' . $ticketId;
if (empty($qrData)) {
    $qrData = 'TICKET-' . $ticketId;
}

// Use multiple QR code API options for reliability
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);
$qrCodeUrl2 = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . urlencode($qrData);

// Fallback QR code if API fails (SVG-based)
$qrCodeAlt = "data:image/svg+xml;charset=utf-8," . rawurlencode('
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
    <rect width="200" height="200" fill="white" stroke="#000" stroke-width="2"/>
    <text x="100" y="90" text-anchor="middle" font-size="14" font-weight="bold" fill="#000">QR Code</text>
    <text x="100" y="110" text-anchor="middle" font-size="10" fill="#666">' . htmlspecialchars(substr($qrData, 0, 20)) . '</text>
    <text x="100" y="130" text-anchor="middle" font-size="10" fill="#666">' . htmlspecialchars(substr($qrData, 20, 20)) . '</text>
</svg>');

// Determine display status (pending/approved/declined)
$ticketDisplayStatus = 'pending';
if ($has_booking_status && isset($ticket['booking_status'])) {
    $bookingStatus = strtolower($ticket['booking_status']);
    if ($bookingStatus === 'approved') {
        $ticketDisplayStatus = 'approved';
    } elseif ($bookingStatus === 'declined') {
        $ticketDisplayStatus = 'cancelled';
    }
} else {
    $ticketDisplayStatus = strtolower($ticket['ticket_status'] ?? 'pending');
    if ($ticketDisplayStatus === 'valid') {
        $ticketDisplayStatus = 'approved';
    }
}
$ticketStatusClass = 'status-' . str_replace([' ', '_'], '-', $ticketDisplayStatus);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Ticket - Ticketix</title>
    <link rel="icon" type="image/png" href="images/brand x.png" />
    <link rel="stylesheet" href="css/ticketix-main.css">
    <link rel="stylesheet" href="css/ticket.css">
</head>
<body>
    <div class="ticket-container">
        <?php
            if ($ticketDisplayStatus === 'approved') {
                echo '<div class="success-message success">✅ Booking approved! Enjoy your movie.</div>';
            } elseif ($ticketDisplayStatus === 'pending') {
                echo '<div class="success-message">✅ Payment successful! Your ticket is pending approval.</div>';
            } elseif ($ticketDisplayStatus === 'cancelled') {
                echo '<div class="success-message error">⚠️ Booking was declined. Please contact support.</div>';
            }
        ?>
        
        <div class="ticket-header">
            <h1>🎬 Ticketix</h1>
            <div class="ticket-number">Ticket #<?= htmlspecialchars($ticket['ticket_number']) ?></div>
        </div>
        
        <div class="ticket-content">
            <div class="ticket-details">
                <h2>Movie Details</h2>
                <div class="detail-item">
                    <span class="detail-label">Movie:</span>
                    <span class="detail-value"><?= htmlspecialchars($ticket['title']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Branch:</span>
                    <span class="detail-value">
                        <?php
                            $branchDisplay = $ticket['branch_name'] ?? $branchName ?? 'SM Mall of Asia';
                            echo htmlspecialchars($branchDisplay);
                        ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Date & Time:</span>
                    <span class="detail-value"><?= $showDate ?> at <?= $showTime ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Seats:</span>
                    <div class="seats-list">
                        <?php foreach ($seats as $seat): ?>
                        <span class="seat-badge"><?= htmlspecialchars($seat['seat_number']) ?> (<?= $seat['seat_type'] ?>)</span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <?php if (count($foodItems) > 0): ?>
                <div class="detail-item">
                    <span class="detail-label">Food Items:</span>
                    <div class="detail-value">
                        <?php foreach ($foodItems as $food): ?>
                        <div><?= htmlspecialchars($food['food_name']) ?> x<?= $food['quantity'] ?> - ₱<?= number_format($food['food_price'] * $food['quantity'], 2) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="detail-item">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value">
                        <?php
                        // Format payment type display
                        $paymentDisplay = ucfirst(str_replace('-', ' ', $ticket['payment_type'] ?? 'N/A'));
                        // Try to extract sub-option from reference number if available
                        if (isset($ticket['reference_number']) && !empty($ticket['reference_number'])) {
                            $refParts = explode('-', $ticket['reference_number']);
                            if (count($refParts) > 0) {
                                $subOption = strtolower($refParts[0]);
                                $subOptionMap = [
                                    'visa' => 'Visa',
                                    'mastercard' => 'Mastercard',
                                    'amex' => 'American Express',
                                    'discover' => 'Discover',
                                    'gcash' => 'GCash',
                                    'paymaya' => 'PayMaya',
                                    'paypal' => 'PayPal',
                                    'grabpay' => 'GrabPay',
                                    'card' => 'Credit Card'
                                ];
                                // Handle uppercase versions too
                                if (isset($subOptionMap[$subOption])) {
                                    $paymentDisplay = $subOptionMap[$subOption];
                                } else {
                                    $subOptionUpper = strtoupper($refParts[0]);
                                    $upperMap = [
                                        'VISA' => 'Visa',
                                        'MASTERCARD' => 'Mastercard',
                                        'AMEX' => 'American Express',
                                        'DISCOVER' => 'Discover',
                                        'GCASH' => 'GCash',
                                        'PAYMAYA' => 'PayMaya',
                                        'PAYPAL' => 'PayPal',
                                        'GRABPAY' => 'GrabPay',
                                        'CARD' => 'Credit Card'
                                    ];
                                    if (isset($upperMap[$subOptionUpper])) {
                                        $paymentDisplay = $upperMap[$subOptionUpper];
                                    }
                                }
                            }
                        }
                        echo htmlspecialchars($paymentDisplay);
                        ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Payment Status:</span>
                    <span class="detail-value <?= 'status-badge ' . $ticketStatusClass ?>">
                        <?= ucfirst($ticketDisplayStatus) ?>
                    </span>
                </div>
                <?php if (($ticket['payment_status'] ?? '') === 'refunded'): ?>
                <div class="detail-alert detail-alert-info">Refund processed. Please check your payment provider for the returned funds.</div>
                <?php endif; ?>
                <div class="detail-item">
                    <span class="detail-label">Total Paid:</span>
                    <span class="detail-value highlight">₱<?= number_format($ticket['amount_paid'], 2) ?></span>
                </div>
            </div>
            
            <div class="qr-section">
                <h2>QR Code</h2>
                <div class="qr-code">
                    <img src="<?= $qrCodeUrl ?>" alt="QR Code" 
                         onerror="if(this.src !== '<?= $qrCodeUrl2 ?>') { this.src='<?= $qrCodeUrl2 ?>'; } else { this.src='<?= $qrCodeAlt ?>'; this.onerror=null; }"
                         class="qr-code-img">
                </div>
                <p class="qr-code-text">Present this QR code at the cinema</p>
                <p class="qr-code-code">Code: <?= htmlspecialchars($ticket['e_ticket_code']) ?></p>
            </div>
        </div>
        
        <a href="TICKETIX NI CLAIRE.php" class="btn-home">Back to Home</a>
    </div>
</body>
</html>

