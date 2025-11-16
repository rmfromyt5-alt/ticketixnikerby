<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config.php';
$conn = getDBConnection();

// --- Fetch Stats ---
$totalUsersQuery = $conn->query("SELECT COUNT(*) AS total FROM USER_ACCOUNT WHERE role = 'user'");
$totalUsers = $totalUsersQuery ? intval($totalUsersQuery->fetch_assoc()['total'] ?? 0) : 0;

// Count all reservations (bookings)
$totalBookingsQuery = $conn->query("SELECT COUNT(*) AS total FROM RESERVE");
$totalBookings = $totalBookingsQuery ? intval($totalBookingsQuery->fetch_assoc()['total'] ?? 0) : 0;

// Calculate total revenue from all paid payments
// amount_paid should equal seat_total + food_total for each booking
$totalRevenueQuery = $conn->query("SELECT IFNULL(SUM(amount_paid), 0) AS total FROM PAYMENT WHERE payment_status = 'paid'");
$revenueResult = $totalRevenueQuery ? $totalRevenueQuery->fetch_assoc() : null;
$totalRevenue = $revenueResult ? floatval($revenueResult['total'] ?? 0) : 0.00;

// --- Fetch Movies ---
$today = date('Y-m-d');

// Check if now_showing column exists
$columns_check = $conn->query("SHOW COLUMNS FROM MOVIE LIKE 'now_showing'");
$has_now_showing = $columns_check && $columns_check->num_rows > 0;

// Now Showing
if ($has_now_showing) {
    $nowShowingResult = $conn->query("
        SELECT DISTINCT m.*
        FROM MOVIE m
        LEFT JOIN MOVIE_SCHEDULE ms ON m.movie_show_id = ms.movie_show_id
        WHERE (m.coming_soon = FALSE OR m.coming_soon IS NULL)
        AND (m.now_showing = TRUE OR (ms.show_date >= '$today' AND ms.show_date IS NOT NULL))
        GROUP BY m.movie_show_id
        ORDER BY m.title ASC
        LIMIT 5
    ");
} else {
    $nowShowingResult = $conn->query("
        SELECT m.*
        FROM MOVIE m
        INNER JOIN MOVIE_SCHEDULE ms ON m.movie_show_id = ms.movie_show_id
        WHERE ms.show_date >= '$today'
        GROUP BY m.movie_show_id
        ORDER BY MIN(ms.show_date) ASC, MIN(ms.show_hour) ASC
        LIMIT 5
    ");
}

$nowShowing = [];
if ($nowShowingResult) {
    while ($row = $nowShowingResult->fetch_assoc()) {
        $nowShowing[] = $row;
    }
}

if (empty($nowShowing) && $has_now_showing) {
    $allMoviesResult = $conn->query("SELECT * FROM MOVIE WHERE now_showing = TRUE AND (coming_soon = FALSE OR coming_soon IS NULL) ORDER BY title ASC LIMIT 5");
    if ($allMoviesResult) {
        while ($row = $allMoviesResult->fetch_assoc()) {
            $nowShowing[] = $row;
        }
    }
}

if (empty($nowShowing)) {
    if ($has_now_showing) {
        $allMoviesResult = $conn->query("SELECT * FROM MOVIE WHERE (coming_soon = FALSE OR coming_soon IS NULL) ORDER BY title ASC LIMIT 5");
    } else {
        $allMoviesResult = $conn->query("SELECT * FROM MOVIE ORDER BY title ASC LIMIT 5");
    }
    if ($allMoviesResult) {
        while ($row = $allMoviesResult->fetch_assoc()) {
            $nowShowing[] = $row;
        }
    }
}

$activeMovies = count($nowShowing);

// Coming Soon
if ($has_now_showing) {
    $comingSoonResult = $conn->query("
        SELECT DISTINCT m.*
        FROM MOVIE m
        LEFT JOIN MOVIE_SCHEDULE ms ON m.movie_show_id = ms.movie_show_id
        WHERE (m.coming_soon = TRUE OR (ms.show_date > '$today' AND ms.show_date IS NOT NULL))
        AND (m.now_showing = FALSE OR m.now_showing IS NULL)
        GROUP BY m.movie_show_id
        ORDER BY m.title ASC
        LIMIT 5
    ");
} else {
    $comingSoonResult = $conn->query("
        SELECT m.*
        FROM MOVIE m
        INNER JOIN (
            SELECT movie_show_id, MIN(show_date) AS next_show
            FROM MOVIE_SCHEDULE
            WHERE show_date > '$today'
            GROUP BY movie_show_id
        ) ms ON m.movie_show_id = ms.movie_show_id
        ORDER BY ms.next_show ASC
        LIMIT 5
    ");
}

$comingSoon = [];
if ($comingSoonResult) {
    while ($row = $comingSoonResult->fetch_assoc()) {
        $comingSoon[] = $row;
    }
}

if (empty($comingSoon) && $has_now_showing) {
    $fallbackQuery = $conn->query("SELECT * FROM MOVIE WHERE coming_soon = TRUE AND (now_showing = FALSE OR now_showing IS NULL) ORDER BY title ASC LIMIT 5");
    if ($fallbackQuery) {
        while ($row = $fallbackQuery->fetch_assoc()) {
            $comingSoon[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Panel</title>
    <link rel="icon" type="image/png" href="images/brand x.png" />
    <link rel="stylesheet" href="css/admin-panel.css" />
    <link rel="stylesheet" href="css/ticketix-main.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
</head>
<body>
    <aside class="sidebar">
        <div class="profile-section">
            <img src="images/brand x.png" alt="Profile Picture" class="profile-pic clickable-logo" onclick="toggleLogout()" style="cursor: pointer;" />
            <h2>Admin</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="admin-panel.php" class="active">Dashboard</a>
            <a href="add-show.php">Add Shows</a>
            <a href="view-shows.php">List Shows</a>
            <a href="view-bookings.php">List Bookings</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn" id="logoutBtn" style="display: none;">➜ Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header>
            <h1>Admin <span class="highlight">Dashboard</span></h1>
        </header>

        <section class="stats-cards">
            <div class="card"><div class="card-info"><label>Total Bookings</label><h3><?= $totalBookings ?></h3></div><div class="card-icon"></div></div>
            <div class="card"><div class="card-info"><label>Total Revenue</label><h3>₱<?= number_format($totalRevenue, 2) ?></h3></div><div class="card-icon"></div></div>
            <div class="card"><div class="card-info"><label>Active Movies</label><h3><?= $activeMovies ?></h3></div><div class="card-icon"></div></div>
            <div class="card"><div class="card-info"><label>Total Users</label><h3><?= $totalUsers ?></h3></div><div class="card-icon"></div></div>
        </section>

        <!-- Now Showing -->
        <section id="now-showing">
            <h2>Now Showing</h2>
            <div class="movie-grid">
                <?php if (count($nowShowing) > 0): ?>
                    <?php foreach ($nowShowing as $movie): 
                        $duration_min = intval($movie['duration'] ?? 0);
                        $hours = floor($duration_min / 60);
                        $minutes = $duration_min % 60;
                        $duration_formatted = $hours > 0 ? $hours . 'h ' . $minutes . 'm' : $minutes . 'm';
                    ?>
                        <div class="movie">
                            <img src="<?= htmlspecialchars($movie['image_poster'] ?: 'images/default.png') ?>" alt="<?= htmlspecialchars($movie['title']) ?>" />
                            <div class="movie-overlay">
                                <div class="movie-info">
                                    <h3><?= htmlspecialchars($movie['title']) ?></h3>
                                    <p><?= htmlspecialchars($movie['genre']) ?> • <?= $duration_formatted ?> • <?= htmlspecialchars($movie['rating'] ?: 'N/A') ?></p>
                                    <div class="movie-actions">
                                        <button class="action-btn trailer-btn">▶ Trailer</button>
                                        <a href="view-shows.php" class="action-btn ticket-btn" style="text-decoration: none;">🎟 View Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; grid-column: 1 / -1; padding: 40px;">No movies currently showing.</p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Coming Soon -->
        <section id="coming-soon">
            <h2>Coming Soon</h2>
            <div class="movie-grid">
                <?php if (count($comingSoon) > 0): ?>
                    <?php foreach ($comingSoon as $movie): 
                        $duration_min = intval($movie['duration'] ?? 0);
                        $hours = floor($duration_min / 60);
                        $minutes = $duration_min % 60;
                        $duration_formatted = $hours > 0 ? $hours . 'h ' . $minutes . 'm' : $minutes . 'm';
                    ?>
                        <div class="movie">
                            <img src="<?= htmlspecialchars($movie['image_poster'] ?: 'images/default.png') ?>" alt="<?= htmlspecialchars($movie['title']) ?>" />
                            <div class="movie-info">
                                <h3><?= htmlspecialchars($movie['title']) ?></h3>
                                <p><?= htmlspecialchars($movie['genre']) ?> • <?= $duration_formatted ?> • <?= htmlspecialchars($movie['rating'] ?: 'N/A') ?></p>
                                <button class="notify-btn">Notify Me</button>
                            </div>
                            <button class="action-btn status-btn" data-id="<?= $movie['movie_show_id'] ?>" data-action="coming_soon" style="background-color: #f39c12;">
                            ⏳ Mark as Coming Soon
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #666; grid-column: 1 / -1; padding: 40px;">No upcoming movies.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script>
    function toggleLogout() {
        const logoutBtn = document.getElementById('logoutBtn');
        logoutBtn.style.display = (logoutBtn.style.display === 'none' || logoutBtn.style.display === '') ? 'block' : 'none';
    }

    document.querySelectorAll('.status-btn').forEach(button => {
        button.addEventListener('click', function() {
            const movieId = this.getAttribute('data-id');
            const action = this.getAttribute('data-action');

            if (!confirm(`Are you sure you want to mark this movie as ${action.replace('_', ' ')}?`)) return;

            fetch('update-movie-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${movieId}&action=${action}`
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Something went wrong.');
            });
        });
    });
    </script>
</body>
</html>
