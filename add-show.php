<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config.php';
$conn = getDBConnection();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title'] ?? '');
    $genre = trim($_POST['genre'] ?? '');
    $duration = intval($_POST['duration'] ?? 0);
    $rating = trim($_POST['rating'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $show_date = $_POST['show_date'] ?? '';
    $show_hour = $_POST['show_hour'] ?? '';
    // Make checkboxes mutually exclusive - if coming_soon is checked, now_showing must be false
    $coming_soon = isset($_POST['coming_soon']) ? 1 : 0;
    $now_showing = ($coming_soon == 1) ? 0 : (isset($_POST['now_showing']) ? 1 : 0);
    
    // Handle file uploads
    $image_poster = '';
    $carousel_image = '';
    
    // Create upload directories if they don't exist
    $images_dir = __DIR__ . '/images';
    $carousel_dir = __DIR__ . '/carousel';
    if (!is_dir($images_dir)) {
        mkdir($images_dir, 0755, true);
    }
    if (!is_dir($carousel_dir)) {
        mkdir($carousel_dir, 0755, true);
    }
    
    // Handle image_poster upload
    if (isset($_FILES['image_poster']) && $_FILES['image_poster']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image_poster'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'movie_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $images_dir . '/' . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $image_poster = 'images/' . $new_filename;
            } else {
                $error_message = "Failed to upload poster image.";
            }
        } else {
            $error_message = "Invalid poster image. Please upload a valid image file (JPEG, PNG, GIF, or WebP) under 5MB.";
        }
    }
    
    // Handle carousel_image upload
    if (isset($_FILES['carousel_image']) && $_FILES['carousel_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['carousel_image'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'carousel_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $carousel_dir . '/' . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $carousel_image = 'carousel/' . $new_filename;
            } else {
                $error_message = "Failed to upload carousel image.";
            }
        } else {
            $error_message = "Invalid carousel image. Please upload a valid image file (JPEG, PNG, GIF, or WebP) under 5MB.";
        }
    }
    
    if (empty($title) || empty($genre) || empty($duration) || empty($rating)) {
        $error_message = "Please fill in all required fields (Title, Genre, Duration, Rating).";
    } else {
        // Truncate genre to 100 characters (database limit)
        $genre = substr($genre, 0, 100);
        
        // Check if now_showing and coming_soon columns exist
        $columns_check = $conn->query("SHOW COLUMNS FROM MOVIE LIKE 'now_showing'");
        $has_now_showing = $columns_check && $columns_check->num_rows > 0;
        
        // Check if carousel_image column exists
        $carousel_check = $conn->query("SHOW COLUMNS FROM MOVIE LIKE 'carousel_image'");
        $has_carousel_image = $carousel_check && $carousel_check->num_rows > 0;
        
        // Insert movie - use appropriate query based on column existence
        if ($has_now_showing && $has_carousel_image) {
            // Has both now_showing/coming_soon and carousel_image columns
            $stmt = $conn->prepare("INSERT INTO MOVIE (title, genre, duration, rating, movie_descrp, image_poster, carousel_image, now_showing, coming_soon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssissssii", $title, $genre, $duration, $rating, $description, $image_poster, $carousel_image, $now_showing, $coming_soon);
                $execute_result = $stmt->execute();
            } else {
                $execute_result = false;
                $error_message = "Error preparing statement: " . $conn->error;
            }
        } else if ($has_now_showing) {
            // Has now_showing/coming_soon but not carousel_image
            $stmt = $conn->prepare("INSERT INTO MOVIE (title, genre, duration, rating, movie_descrp, image_poster, now_showing, coming_soon) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssisssii", $title, $genre, $duration, $rating, $description, $image_poster, $now_showing, $coming_soon);
                $execute_result = $stmt->execute();
            } else {
                $execute_result = false;
                $error_message = "Error preparing statement: " . $conn->error;
            }
        } else if ($has_carousel_image) {
            // Has carousel_image but not now_showing/coming_soon
            $stmt = $conn->prepare("INSERT INTO MOVIE (title, genre, duration, rating, movie_descrp, image_poster, carousel_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssissss", $title, $genre, $duration, $rating, $description, $image_poster, $carousel_image);
                $execute_result = $stmt->execute();
            } else {
                $execute_result = false;
                $error_message = "Error preparing statement: " . $conn->error;
            }
        } else {
            // Insert without now_showing/coming_soon and carousel_image columns
            $stmt = $conn->prepare("INSERT INTO MOVIE (title, genre, duration, rating, movie_descrp, image_poster) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssisss", $title, $genre, $duration, $rating, $description, $image_poster);
                $execute_result = $stmt->execute();
            } else {
                $execute_result = false;
                $error_message = "Error preparing statement: " . $conn->error;
            }
        }
        
        if ($execute_result) {
            $movie_id = $conn->insert_id;
            $success_message = "Movie added successfully!";
            
            // Check if branch_id column exists in MOVIE_SCHEDULE
            $branch_check = $conn->query("SHOW COLUMNS FROM MOVIE_SCHEDULE LIKE 'branch_id'");
            $has_branch_id = $branch_check && $branch_check->num_rows > 0;
            
            // If show date and time are provided, add schedule for ALL branches
            if (!empty($show_date) && !empty($show_hour)) {
                // Check if BRANCH table exists first
                $table_check = $conn->query("SHOW TABLES LIKE 'BRANCH'");
                if (!$table_check || $table_check->num_rows == 0) {
                    // Try lowercase
                    $table_check = $conn->query("SHOW TABLES LIKE 'branch'");
                }
                
                if ($table_check && $table_check->num_rows > 0) {
                    // Fetch all branches - try uppercase first, then lowercase
                    $branches_query = @$conn->query("SELECT branch_id FROM BRANCH");
                    if (!$branches_query) {
                        $branches_query = @$conn->query("SELECT branch_id FROM branch");
                    }
                    
                    $branches_added = 0;
                    $branches_failed = 0;
                    
                    if ($branches_query && $branches_query->num_rows > 0) {
                        if ($has_branch_id) {
                            // Insert schedule for each branch
                            $schedule_stmt = $conn->prepare("INSERT INTO MOVIE_SCHEDULE (movie_show_id, show_date, show_hour, branch_id) VALUES (?, ?, ?, ?)");
                            while ($branch = $branches_query->fetch_assoc()) {
                                $schedule_stmt->bind_param("issi", $movie_id, $show_date, $show_hour, $branch['branch_id']);
                                if ($schedule_stmt->execute()) {
                                    $branches_added++;
                                } else {
                                    $branches_failed++;
                                }
                            }
                            $schedule_stmt->close();
                        } else {
                            // No branch_id column - insert single schedule (backward compatibility)
                            $schedule_stmt = $conn->prepare("INSERT INTO MOVIE_SCHEDULE (movie_show_id, show_date, show_hour) VALUES (?, ?, ?)");
                            $schedule_stmt->bind_param("iss", $movie_id, $show_date, $show_hour);
                            if ($schedule_stmt->execute()) {
                                $branches_added = 1;
                            } else {
                                $branches_failed = 1;
                                $error_message = "Movie added but schedule failed: " . $conn->error;
                            }
                            $schedule_stmt->close();
                        }
                        
                        if ($branches_added > 0) {
                            if ($has_branch_id) {
                                $success_message .= " Show schedule added to {$branches_added} branch(es) successfully!";
                                if ($branches_failed > 0) {
                                    $error_message = "Movie added, but {$branches_failed} branch schedule(s) failed.";
                                }
                            } else {
                                $success_message .= " Show schedule added successfully!";
                            }
                        }
                    } else {
                        if ($branches_query === false) {
                            $error_message = "Movie added but could not fetch branches. Error: " . $conn->error . ". Please ensure the BRANCH table exists.";
                        } else {
                            $error_message = "Movie added but no branches found. Please add branches first.";
                        }
                    }
                } else {
                    // BRANCH table doesn't exist
                    $error_message = "Movie added but BRANCH table not found. Please run the database setup script (ticketix.sql) to create the BRANCH table first.";
                }
            } else {
                // If movie is marked as "now showing", automatically create default schedules for all branches
                if ($now_showing == 1) {
                    // Check if BRANCH table exists
                    $table_check = $conn->query("SHOW TABLES LIKE 'BRANCH'");
                    if (!$table_check || $table_check->num_rows == 0) {
                        $table_check = $conn->query("SHOW TABLES LIKE 'branch'");
                    }
                    
                    if ($table_check && $table_check->num_rows > 0) {
                        // Fetch all branches
                        $branches_query = @$conn->query("SELECT branch_id FROM BRANCH");
                        if (!$branches_query) {
                            $branches_query = @$conn->query("SELECT branch_id FROM branch");
                        }
                        
                        if ($branches_query && $branches_query->num_rows > 0) {
                            // Default show times (can be customized)
                            $default_times = ['10:00:00', '13:00:00', '16:00:00', '19:00:00', '22:00:00'];
                            $today = date('Y-m-d');
                            
                            $branches_added = 0;
                            $branches_failed = 0;
                            
                            if ($has_branch_id) {
                                // Create schedules for each branch with default times
                                $schedule_stmt = $conn->prepare("INSERT INTO MOVIE_SCHEDULE (movie_show_id, show_date, show_hour, branch_id) VALUES (?, ?, ?, ?)");
                                while ($branch = $branches_query->fetch_assoc()) {
                                    foreach ($default_times as $time) {
                                        $schedule_stmt->bind_param("issi", $movie_id, $today, $time, $branch['branch_id']);
                                        if ($schedule_stmt->execute()) {
                                            $branches_added++;
                                        } else {
                                            $branches_failed++;
                                        }
                                    }
                                }
                                $schedule_stmt->close();
                                
                                if ($branches_added > 0) {
                                    $success_message .= " Default schedules created for all branches ({$branches_added} schedule entries).";
                                }
                            } else {
                                // No branch_id column - create single schedule per time
                                $schedule_stmt = $conn->prepare("INSERT INTO MOVIE_SCHEDULE (movie_show_id, show_date, show_hour) VALUES (?, ?, ?)");
                                foreach ($default_times as $time) {
                                    $schedule_stmt->bind_param("iss", $movie_id, $today, $time);
                                    if ($schedule_stmt->execute()) {
                                        $branches_added++;
                                    } else {
                                        $branches_failed++;
                                    }
                                }
                                $schedule_stmt->close();
                                
                                if ($branches_added > 0) {
                                    $success_message .= " Default schedules created ({$branches_added} schedule entries).";
                                }
                            }
                        }
                    }
                } else {
                    // Movie not marked as "now showing" - inform that schedules can be added later
                    $success_message .= " You can add schedules for this movie later from the movie list.";
                }
            }
        } else {
            $error_message = "Error adding movie: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch all movies for reference
$movies_query = $conn->query("SELECT movie_show_id, title FROM MOVIE ORDER BY title");
$movies = [];
if ($movies_query) {
    while ($row = $movies_query->fetch_assoc()) {
        $movies[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Add Show - Admin Panel</title>
    <link rel="icon" type="image/png" href="images/brand x.png" />
    <link rel="stylesheet" href="css/admin-panel.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-group input[type="file"] {
            padding: 8px;
            cursor: pointer;
            background: #f8f9fa;
        }
        
        .form-group input[type="file"]:hover {
            background: #e9ecef;
        }
        
        .form-group small {
            display: block;
            margin-top: 4px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #00BFFF;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #00BFFF, #3C50B2);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .add-schedule-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
        }
        
        .add-schedule-section h3 {
            margin-bottom: 20px;
            color: #333;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="profile-section">
            <img src="images/brand x.png" alt="Profile Picture" class="profile-pic" />
            <h2>Admin</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="admin-panel.php">Dashboard</a>
            <a href="add-show.php" class="active">Add Shows</a>
            <a href="view-shows.php">List Shows</a>
            <a href="view-bookings.php">List Bookings</a>
        </nav>
    </aside>
    <main class="main-content">
        <header>
            <h1>Add <span class="highlight">Show</span></h1>
        </header>

        <div class="form-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <form method="POST" action="add-show.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Movie Title *</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="genre">Genre *</label>
                        <input type="text" id="genre" name="genre" required>
                    </div>
                    <div class="form-group">
                        <label for="duration">Duration (minutes) *</label>
                        <input type="number" id="duration" name="duration" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="rating">Rating *</label>
                    <select id="rating" name="rating" required>
                        <option value="">Select Rating</option>
                        <option value="G">G</option>
                        <option value="PG">PG</option>
                        <option value="PG-13">PG-13</option>
                        <option value="R">R</option>
                        <option value="NC-17">NC-17</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="image_poster">Image Poster (Portrait)</label>
                        <input type="file" id="image_poster" name="image_poster" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                        <small style="color: #666; font-size: 12px;">Portrait/vertical poster image for movie cards (JPEG, PNG, GIF, or WebP, max 5MB)</small>
                    </div>
                    <div class="form-group">
                        <label for="carousel_image">Carousel Image (Landscape)</label>
                        <input type="file" id="carousel_image" name="carousel_image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                        <small style="color: #666; font-size: 12px;">Landscape/horizontal image for carousel background (JPEG, PNG, GIF, or WebP, max 5MB)</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Enter movie description..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="now_showing" name="now_showing" value="1" style="width: auto; cursor: pointer;" onchange="handleNowShowingChange()">
                            <span>Mark as Now Showing</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="coming_soon" name="coming_soon" value="1" style="width: auto; cursor: pointer;" onchange="handleComingSoonChange()">
                            <span>Mark as Coming Soon</span>
                        </label>
                    </div>
                </div>
                
                <script>
                    function handleNowShowingChange() {
                        const nowShowing = document.getElementById('now_showing');
                        const comingSoon = document.getElementById('coming_soon');
                        if (nowShowing.checked) {
                            comingSoon.checked = false;
                        }
                    }
                    
                    function handleComingSoonChange() {
                        const nowShowing = document.getElementById('now_showing');
                        const comingSoon = document.getElementById('coming_soon');
                        if (comingSoon.checked) {
                            nowShowing.checked = false;
                        }
                    }
                </script>

                <div class="add-schedule-section">
                    <h3>Add Show Schedule (Optional)</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="show_date">Show Date</label>
                            <input type="date" id="show_date" name="show_date">
                        </div>
                        <div class="form-group">
                            <label for="show_hour">Show Time</label>
                            <input type="time" id="show_hour" name="show_hour">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Add Show</button>
            </form>
        </div>
    </main>
</body>
</html>

