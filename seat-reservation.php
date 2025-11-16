<?php
// --- PHP LOGIC START ---
require_once __DIR__ . '/config.php';
$conn = getDBConnection();

$timings = ["10:30 AM", "12:30 AM", "3:00 PM", "05:30 PM", "06:30 PM", "08:30 PM", "9:30 PM", "10:30 PM"];
$selectedTime = $_GET['time'] ?? '10:30 AM'; // get selected time or default
$todayDate = date('Y-m-d');
$maxSelectableDate = date('Y-m-d', strtotime('+30 days'));
$selectedDate = $_GET['date'] ?? $todayDate;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = $todayDate;
}
if (strtotime($selectedDate) < strtotime($todayDate)) {
    $selectedDate = $todayDate;
}
if (strtotime($selectedDate) > strtotime($maxSelectableDate)) {
    $selectedDate = $maxSelectableDate;
}

// Get movie from URL parameter
$movieTitle = $_GET['movie'] ?? null;
$branchName = $_GET['branch'] ?? null;

// Fetch movie details from database
$movie = null;
$moviePoster = 'images/default.png'; // default poster
$displayMovieTitle = 'Select Movie';

if ($movieTitle) {
    $stmt = $conn->prepare("SELECT movie_show_id, title, image_poster FROM MOVIE WHERE title = ? LIMIT 1");
    $stmt->bind_param("s", $movieTitle);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $movie = $result->fetch_assoc();
        $displayMovieTitle = htmlspecialchars($movie['title']);
        $moviePoster = !empty($movie['image_poster']) ? htmlspecialchars($movie['image_poster']) : 'images/default.png';
    }
    $stmt->close();
}

// Get branch name if provided
$displayBranchName = 'Select Branch';
$branchId = null;
if ($branchName) {
    $displayBranchName = htmlspecialchars($branchName);
    // Get branch_id from branch name
    $stmt = $conn->prepare("SELECT branch_id FROM BRANCH WHERE branch_name = ? LIMIT 1");
    $stmt->bind_param("s", $branchName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $branchData = $result->fetch_assoc();
        $branchId = $branchData['branch_id'];
    }
    $stmt->close();
} else {
    // Default to SM Mall of Asia if no branch specified
    $displayBranchName = 'SM Mall of Asia';
    $stmt = $conn->prepare("SELECT branch_id FROM BRANCH WHERE branch_name = 'SM Mall of Asia' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $branchData = $result->fetch_assoc();
        $branchId = $branchData['branch_id'];
    }
    $stmt->close();
}

// Check if MOVIE_SCHEDULE has branch_id column
$branchIdColumnCheck = $conn->query("SHOW COLUMNS FROM MOVIE_SCHEDULE LIKE 'branch_id'");
$movieScheduleHasBranchId = $branchIdColumnCheck && $branchIdColumnCheck->num_rows > 0;

// Get booked seats for the selected schedule
$bookedSeats = [];
if ($movie && $selectedTime) {
    // Convert time format from "10:30 AM" to "10:30:00" for database comparison
    $timeParts = date_parse($selectedTime);
    $timeFormatted = sprintf("%02d:%02d:00", $timeParts['hour'], $timeParts['minute'] ?? 0);
    
    // Get schedule_id for this movie, branch, date, and time
    if ($movieScheduleHasBranchId && $branchId) {
        $stmt = $conn->prepare("
            SELECT schedule_id 
            FROM MOVIE_SCHEDULE 
            WHERE movie_show_id = ? 
            AND branch_id = ? 
            AND show_date = ? 
            AND TIME(show_hour) = TIME(?)
            LIMIT 1
        ");
        $stmt->bind_param("iiss", $movie['movie_show_id'], $branchId, $selectedDate, $timeFormatted);
    } else {
        $stmt = $conn->prepare("
            SELECT schedule_id 
            FROM MOVIE_SCHEDULE 
            WHERE movie_show_id = ? 
            AND show_date = ? 
            AND TIME(show_hour) = TIME(?)
            LIMIT 1
        ");
        $stmt->bind_param("iss", $movie['movie_show_id'], $selectedDate, $timeFormatted);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $schedule = null;
    if ($result && $result->num_rows > 0) {
        $schedule = $result->fetch_assoc();
        $scheduleId = $schedule['schedule_id'];
        
        // Get all booked seats for this schedule from approved/pending bookings
        $column_check = $conn->query("SHOW COLUMNS FROM RESERVE LIKE 'booking_status'");
        $has_booking_status = $column_check && $column_check->num_rows > 0;
        
        if ($has_booking_status) {
            // Show seats from ALL bookings (pending, approved) - exclude only declined
            // This ensures seats are blocked immediately after booking, not just after approval
            $seatStmt = $conn->prepare("
                SELECT DISTINCT s.seat_number
                FROM RESERVE r
                JOIN RESERVE_SEAT rs ON r.reservation_id = rs.reservation_id
                JOIN SEAT s ON rs.seat_id = s.seat_id
                WHERE r.schedule_id = ? 
                AND (r.booking_status IS NULL OR r.booking_status = 'pending' OR r.booking_status = 'approved')
            ");
        } else {
            // If booking_status column doesn't exist, show all booked seats
            $seatStmt = $conn->prepare("
                SELECT DISTINCT s.seat_number
                FROM RESERVE r
                JOIN RESERVE_SEAT rs ON r.reservation_id = rs.reservation_id
                JOIN SEAT s ON rs.seat_id = s.seat_id
                WHERE r.schedule_id = ?
            ");
        }
        $seatStmt->bind_param("i", $scheduleId);
        $seatStmt->execute();
        $seatResult = $seatStmt->get_result();
        while ($row = $seatResult->fetch_assoc()) {
            $bookedSeats[] = $row['seat_number'];
        }
        $seatStmt->close();
    }
    $stmt->close();
}
// --- PHP LOGIC END ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title><?= $displayMovieTitle !== 'Select Movie' ? $displayMovieTitle . ' - ' : '' ?>Select Your Seat - Ticketix</title>
    <link rel="icon" type="image/png" href="images/brand x.png" />
    <link rel="stylesheet" href="css/seats.css" />
    <link rel="stylesheet" href="css/seat-reservation-food.css" />
    <style>
        .date-selector {
            margin: 20px 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .date-selector label {
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .date-selector input[type="date"] {
            padding: 10px 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 0.95rem;
        }
        .date-selector input[type="date"]:focus {
            outline: none;
            border-color: #00BFFF;
            box-shadow: 0 0 0 2px rgba(0, 191, 255, 0.25);
        }
        .selected-date-info {
            margin-top: 10px;
            color: #cbd5e1;
            font-size: 0.9rem;
        }
        .selected-date-info strong {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left Sidebar -->
        <aside class="timings">
            <button class="back-btn" onclick="window.location.href='TICKETIX NI CLAIRE.php'">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 1-.5.5H3.707l3.147 3.146a.5.5 0 0 1-.708.708l-4-4a.5.5 0 0 1 0-.708l4-4a.5.5 0 1 1 .708.708L3.707 7.5H14.5A.5.5 0 0 1 15 8z"/>
                </svg>
                Back to Website
            </button>
            <div class="date-selector">
                <label for="datePicker">Select Date</label>
                <input type="date" id="datePicker" value="<?= htmlspecialchars($selectedDate) ?>" min="<?= htmlspecialchars($todayDate) ?>" max="<?= htmlspecialchars($maxSelectableDate) ?>">
            </div>
            <h3>Available Timings</h3>
            <ul id="timing-list">
                <?php
                foreach ($timings as $time) {
                    $activeClass = ($time === $selectedTime) ? "active" : "";
                    echo "<li tabindex='0' role='button' class='$activeClass' data-time='$time'><span class='icon'></span> $time</li>";
                }
                ?>
            </ul>
        </aside>

        <!-- Seat Selection -->
        <main class="seat-selection">
            <h1>Select Your Seat</h1>
            <div class="screen"></div>

            <div class="seats-wrapper">
                <?php
                // --- DATABASE-DRIVEN SEAT LOGIC START ---
                $rows = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

                foreach ($rows as $row):
                    echo '<div class="row"><div class="row-label">' . $row . '</div>';
                    $totalSeats = 18;
                    for ($i = 1; $i <= $totalSeats; $i++):
                        if (($row === 'A' || $row === 'B') && $i > 9) {
                            if ($i == 10) echo '<div class="seat-gap"></div>';
                            continue;
                        }
                        if ($i == 10) echo '<div class="seat-gap"></div>';

                        // Check if seat is booked from database
                        // Seat numbers are stored as "A-1", "B-2" format in database
                        $seatNumber = $row . '-' . $i;
                        // Also check without dash in case seats were stored in different format
                        $seatNumberAlt = $row . $i;
                        $isBooked = in_array($seatNumber, $bookedSeats) || in_array($seatNumberAlt, $bookedSeats);
                        $isSelected = false; // User selection happens via JavaScript

                        $classes = "seat";
                        if ($isSelected) $classes .= " selected";
                        if ($isBooked) $classes .= " booked";

                        $disabled = $isBooked ? "aria-disabled='true' tabindex='-1'" : "tabindex='0' role='checkbox' aria-checked='false'";
                        // Use format "A-1" to match database storage
                        $dataAttr = "data-seat='$row-$i'";

                        echo "<div class='seat-container'>";
                        echo "<div class='$classes' $disabled $dataAttr></div>";
                        echo "<span class='seat-label'>$seatNumber</span>";
                        echo "</div>";
                    endfor;
                    echo '</div>';
                endforeach;
                // --- DATABASE-DRIVEN SEAT LOGIC END ---
                ?>
            </div>

            <button id="proceed-btn" class="proceed-btn" disabled>
                Proceed to checkout <span>→</span>
            </button>
        </main>

        <!-- Right Movie Info Panel -->
        <aside class="movie-info">
            <h3><?= $displayBranchName ?></h3>
            <div class="movie-poster">
                <img src="<?= $moviePoster ?>" alt="<?= $displayMovieTitle ?> Poster" onerror="this.src='images/default.png'">
            </div>
            <?php if ($movieTitle): ?>
            <div class="movie-title-center">
                <p><?= $displayMovieTitle ?></p>
            </div>
            <?php endif; ?>
            <div class="selected-date-info">
                <span>Selected Date:</span>
                <strong><?= date('M d, Y', strtotime($selectedDate)) ?></strong>
            </div>

            <div class="food-section">
                <h4>Food Selection</h4>

                <!-- === DATABASE-DRIVEN FOOD GRID === -->
                <div class="food-grid">
                    <?php
                    $foodsQuery = $conn->query("SELECT * FROM FOOD");
                    if ($foodsQuery && $foodsQuery->num_rows > 0) {
                        while ($food = $foodsQuery->fetch_assoc()) {
                            echo "
                            <div class='food-item' data-item='{$food['food_name']}' data-food-id='{$food['food_id']}' data-food-price='{$food['food_price']}'>
                                <img src='{$food['image_path']}' alt='{$food['food_name']}'>
                                <div class='food-name'>{$food['food_name']}</div>
                                <div class='food-price'>₱{$food['food_price']}</div>
                                <div class='food-controls'>
                                    <button class='decrease'>−</button>
                                    <span class='count'>0</span>
                                    <button class='increase'>+</button>
                                </div>
                            </div>";
                        }
                    } else {
                        echo "<p>No foods available.</p>";
                    }
                    ?>
                </div>
                <!-- === END FOOD GRID === -->

            </div>
        </aside>
    </div>

    <script>
        // --- Timing Selection ---
        const selectedDate = '<?= htmlspecialchars($selectedDate, ENT_QUOTES) ?>';
        const timingItems = document.querySelectorAll('#timing-list li');
        timingItems.forEach(item => {
            item.addEventListener('click', () => {
                const selectedTimeValue = item.dataset.time;
                if (!selectedTimeValue) return;
                const url = new URL(window.location.href);
                url.searchParams.set('time', selectedTimeValue);
                const dateInput = document.getElementById('datePicker');
                if (dateInput && dateInput.value) {
                    url.searchParams.set('date', dateInput.value);
                }
                window.location.href = url.toString();
            });
        });

        const datePicker = document.getElementById('datePicker');
        if (datePicker) {
            datePicker.addEventListener('change', function() {
                if (!this.value) return;
                const url = new URL(window.location.href);
                url.searchParams.set('date', this.value);
                const activeTime = document.querySelector('#timing-list li.active');
                if (activeTime && activeTime.dataset.time) {
                    url.searchParams.set('time', activeTime.dataset.time);
                }
                window.location.href = url.toString();
            });
        }

        // --- Seat Selection ---
        const seats = document.querySelectorAll('.seat:not(.booked)');
        const proceedBtn = document.getElementById('proceed-btn');
        let selectedSeats = new Set();

        seats.forEach(seat => {
            seat.addEventListener('click', () => {
                const seatId = seat.getAttribute('data-seat');
                // Convert format from "A-1" to match what's displayed and stored in DB
                const seatNumber = seatId; // Already in "A-1" format from data-seat attribute
                seat.classList.toggle('selected');
                seat.setAttribute('aria-checked', seat.classList.contains('selected'));
                if (seat.classList.contains('selected')) selectedSeats.add(seatNumber);
                else selectedSeats.delete(seatNumber);
                proceedBtn.disabled = selectedSeats.size === 0;
            });
        });

        // --- Food Quantity Controls ---
        const foodItems = document.querySelectorAll('.food-item');
        let foodSelections = {};
        const foodPrices = {};

        // Store food prices and IDs from data attributes
        foodItems.forEach(item => {
            const itemName = item.dataset.item;
            const foodId = item.dataset.foodId;
            const price = parseFloat(item.dataset.foodPrice || 0);
            foodPrices[itemName] = {price: price, id: foodId};
        });

        foodItems.forEach(item => {
            const increaseBtn = item.querySelector('.increase');
            const decreaseBtn = item.querySelector('.decrease');
            const countDisplay = item.querySelector('.count');
            const itemName = item.dataset.item;
            let count = 0;

            increaseBtn.addEventListener('click', () => {
                count++;
                countDisplay.textContent = count;
                foodSelections[itemName] = count;
            });

            decreaseBtn.addEventListener('click', () => {
                if (count > 0) count--;
                countDisplay.textContent = count;
                if (count === 0) delete foodSelections[itemName];
                else foodSelections[itemName] = count;
            });
        });

        // --- Proceed Button ---
        proceedBtn.addEventListener('click', () => {
            if (selectedSeats.size === 0) {
                alert('Please select at least one seat.');
                return;
            }

            const activeTimingElement = document.querySelector('#timing-list li.active');
            if (!activeTimingElement || !activeTimingElement.dataset.time) {
                alert('Please select a show time.');
                return;
            }

            const selectedTiming = activeTimingElement.dataset.time;
            const selectedSeatNumbers = [...selectedSeats];
            
            // Calculate food total
            let foodTotal = 0;
            const foodData = [];
            Object.entries(foodSelections).forEach(([item, qty]) => {
                const foodInfo = foodPrices[item] || {price: 0, id: 0};
                const price = foodInfo.price;
                const subtotal = price * qty;
                foodTotal += subtotal;
                foodData.push({id: foodInfo.id, name: item, quantity: qty, price: price, subtotal: subtotal});
            });

            // Prepare data for checkout
            const checkoutData = {
                movie: '<?= $movieTitle ? urlencode($movieTitle) : "" ?>',
                branch: '<?= $branchName ? urlencode($branchName) : "" ?>',
                date: selectedDate,
                time: selectedTiming,
                seats: selectedSeatNumbers,
                food: foodData,
                foodTotal: foodTotal
            };

            // Redirect to checkout page
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'checkout.php';
            
            const dataInput = document.createElement('input');
            dataInput.type = 'hidden';
            dataInput.name = 'booking_data';
            dataInput.value = JSON.stringify(checkoutData);
            form.appendChild(dataInput);
            
            document.body.appendChild(form);
            form.submit();
        });
    </script>
</body>
</html>
