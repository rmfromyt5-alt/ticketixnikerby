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
if (!$bookingData) {
    header("Location: TICKETIX NI CLAIRE.php");
    exit();
}

// Extract booking information
$movieTitle = urldecode($bookingData['movie'] ?? ''); // Decode URL-encoded movie title
$branchName = urldecode($bookingData['branch'] ?? ''); // Decode URL-encoded branch name
$showTime = $bookingData['time'] ?? '';
$showDate = $bookingData['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $showDate)) {
    $showDate = date('Y-m-d');
}
$selectedSeats = $bookingData['seats'] ?? [];
$foodItems = $bookingData['food'] ?? [];
$foodTotal = floatval($bookingData['foodTotal'] ?? 0);

// Get movie details
$movie = null;
$moviePoster = 'images/default.png';
if ($movieTitle) {
    $stmt = $conn->prepare("SELECT movie_show_id, title, image_poster FROM MOVIE WHERE title = ? LIMIT 1");
    $stmt->bind_param("s", $movieTitle);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $movie = $result->fetch_assoc();
        $moviePoster = !empty($movie['image_poster']) ? htmlspecialchars($movie['image_poster']) : 'images/default.png';
    }
    $stmt->close();
}

// Calculate seat prices (Regular = ₱250, VIP = ₱350)
// For now, assume all seats are Regular. You can enhance this later.
$seatPrice = 250.00;
$seatCount = count($selectedSeats);
$seatTotal = $seatPrice * $seatCount;
$grandTotal = $seatTotal + $foodTotal;

// Get user ID
$userId = $_SESSION['user_id'] ?? $_SESSION['acc_id'] ?? null; // Support both session variable names
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Ticketix</title>
    <link rel="icon" type="image/png" href="images/brand x.png" />
    <link rel="stylesheet" href="css/ticketix-main.css">
    <link rel="stylesheet" href="css/checkout.css">
</head>
<body>
    <div class="checkout-container">
        <a href="javascript:history.back()" class="btn-back">← Back</a>
        <h1>Checkout</h1>
        
        <div class="booking-summary">
            <div class="movie-poster-section">
                <img src="<?= htmlspecialchars($moviePoster) ?>" alt="<?= htmlspecialchars($movieTitle) ?>">
                <h3><?= htmlspecialchars($movieTitle) ?></h3>
            </div>
            
            <div class="summary-details">
                <h2>Booking Summary</h2>
                <div class="detail-row">
                    <strong>Branch:</strong>
                    <span><?= htmlspecialchars($branchName ?: 'SM Mall of Asia') ?></span>
                </div>
                <div class="detail-row">
                    <strong>Show Date:</strong>
                    <span><?= date('F d, Y', strtotime($showDate)) ?></span>
                </div>
                <div class="detail-row">
                    <strong>Show Time:</strong>
                    <span><?= htmlspecialchars($showTime) ?></span>
                </div>
                <div class="detail-row">
                    <strong>Seats (<?= $seatCount ?>):</strong>
                    <div class="seats-list"><?= htmlspecialchars(implode(', ', $selectedSeats)) ?></div>
                </div>
                
                <?php if (count($foodItems) > 0): ?>
                <div class="detail-row food-items-row">
                    <strong>Food Items:</strong>
                    <div class="food-items-table-wrapper">
                        <table class="food-items-table">
                            <thead>
                                <tr>
                                    <th>Quantity</th>
                                    <th>Name</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($foodItems as $food): ?>
                                <tr>
                                    <td><?= htmlspecialchars($food['quantity'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars($food['name'] ?? 'N/A') ?></td>
                                    <td>₱<?= number_format($food['subtotal'] ?? 0, 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="price-section">
            <div class="price-row">
                <span>Seat Total (<?= $seatCount ?> seats):</span>
                <span>₱<?= number_format($seatTotal, 2) ?></span>
            </div>
            <?php if ($foodTotal > 0): ?>
            <div class="price-row">
                <span>Food Total:</span>
                <span>₱<?= number_format($foodTotal, 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="price-row total">
                <span>Grand Total:</span>
                <span>₱<?= number_format($grandTotal, 2) ?></span>
            </div>
        </div>
        
        <?php if (isset($_GET['error'])): ?>
        <div class="error-message">
            <strong>Error:</strong> <?= htmlspecialchars($_GET['error']) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="payment.php">
            <input type="hidden" name="booking_data" value="<?= htmlspecialchars($_POST['booking_data']) ?>">
            <input type="hidden" name="seat_total" value="<?= $seatTotal ?>">
            <input type="hidden" name="food_total" value="<?= $foodTotal ?>">
            <input type="hidden" name="grand_total" value="<?= $grandTotal ?>">
            <button type="submit" class="btn-proceed">Proceed to Payment →</button>
        </form>
    </div>
</body>
</html>

