<?php
session_start();
require_once 'config.php';

// Connect sa MySQL database mo
$conn = getDBConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate required fields
    if (empty($_POST['firstName']) || empty($_POST['fullName']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['contact']) || empty($_POST['address'])) {
        die("Error: First name, display name, email, password, contact, and address are required!");
    }

    // Kunin ang data galing sa form
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName'] ?? ''); // Last name is now optional
    $displayName = trim($_POST['fullName'] ?? ''); // Display name/nickname (stored in fullName column)
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $contact = trim($_POST['contact']);
    $address = trim($_POST['address']);
    
    // Validate display name
    if (empty($displayName)) {
        die("Error: Display name is required!");
    }
    if (mb_strlen($displayName) > 50) {
        die("Error: Display name must be 50 characters or fewer.");
    }

    // Validate firstName and lastName
    if (mb_strlen($firstName) > 50) {
        die("Error: First name must be 50 characters or fewer.");
    }
    if (mb_strlen($firstName) == 0) {
        die("Error: First name cannot be empty.");
    }
    if (mb_strlen($lastName) > 50) {
        die("Error: Last name must be 50 characters or fewer.");
    }
    // Last name can be empty now (optional)

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Error: Invalid email format!");
    }

    // Validate contact number format (11 digits)
    if (!preg_match('/^[0-9]{11}$/', $contact)) {
        die("Error: Contact number must be exactly 11 digits!");
    }

    // Length validations to match DB schema (USER_ACCOUNT columns)
    if (mb_strlen($email) > 50) {
        die("Error: Email must be 50 characters or fewer.");
    }
    if (mb_strlen($address) > 50) {
        die("Error: Address must be 50 characters or fewer.");
    }

    // Birthday field removed - no longer required

	// Check if email already exists to avoid duplicate key error
	$check = $conn->prepare("SELECT acc_id FROM USER_ACCOUNT WHERE email = ? LIMIT 1");
	if (!$check) {
		die("Prepare failed: " . $conn->error);
	}
	$check->bind_param("s", $email);
	$check->execute();
	$checkResult = $check->get_result();
	if ($checkResult && $checkResult->num_rows > 0) {
		$check->close();
		die("Error: Email already registered. Please use a different email or log in.");
	}
	$check->close();

	// Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if firstName and lastName columns exist in the database
    $checkCols = $conn->query("SHOW COLUMNS FROM USER_ACCOUNT LIKE 'firstName'");
    $hasFirstName = $checkCols && $checkCols->num_rows > 0;
    
    if (!$hasFirstName) {
        // Columns don't exist - show clear error message
        die("
        <html>
        <head><title>Database Setup Required</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
            .error-box { background: white; padding: 30px; border-radius: 10px; max-width: 800px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #d32f2f; margin-top: 0; }
            code { background: #f5f5f5; padding: 15px; display: block; border-left: 4px solid #d32f2f; margin: 20px 0; font-size: 14px; }
            .steps { background: #e3f2fd; padding: 20px; border-radius: 5px; margin: 20px 0; }
            .steps ol { margin: 10px 0; padding-left: 25px; }
            .steps li { margin: 10px 0; }
        </style>
        </head>
        <body>
        <div class='error-box'>
            <h1>⚠️ Database Setup Required</h1>
            <p><strong>The database doesn't have firstName and lastName columns yet.</strong></p>
            <p>You need to add these columns to match your ticketix.sql schema.</p>
            
            <div class='steps'>
                <h2>How to Fix:</h2>
                <ol>
                    <li>Open <strong>phpMyAdmin</strong> in your browser</li>
                    <li>Select the <strong>TICKETIX</strong> database</li>
                    <li>Click on the <strong>SQL</strong> tab</li>
                    <li>Copy and paste the SQL code below</li>
                    <li>Click <strong>Go</strong> to execute</li>
                    <li>Come back and try signing up again</li>
                </ol>
            </div>
            
            <h3>SQL Code to Run:</h3>
            <code>
USE TICKETIX;<br><br>
ALTER TABLE USER_ACCOUNT ADD COLUMN firstName VARCHAR(50) NOT NULL DEFAULT '' AFTER acc_id;<br>
ALTER TABLE USER_ACCOUNT ADD COLUMN lastName VARCHAR(50) NOT NULL DEFAULT '' AFTER firstName;
            </code>
            
            <p><strong>Or</strong> open the file <code>RUN_THIS_FIRST.sql</code> in your project folder and copy its contents.</p>
        </div>
        </body>
        </html>
        ");
    }
    
    // Check if fullName column also exists (for backwards compatibility)
    $checkFullName = $conn->query("SHOW COLUMNS FROM USER_ACCOUNT LIKE 'fullName'");
    $hasFullName = $checkFullName && $checkFullName->num_rows > 0;
    
    // Columns exist - use firstName and lastName (and fullName if it exists)
    if ($hasFullName) {
        // If fullName column exists, use it to store the display name/nickname
        $stmt = $conn->prepare("
            INSERT INTO USER_ACCOUNT (firstName, lastName, fullName, email, user_password, contNo, address, time_created, user_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'online')
        ");
        
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        
        // Bind parameters: firstName, lastName, displayName (stored in fullName), email, password, contact, address
        $stmt->bind_param("sssssss", $firstName, $lastName, $displayName, $email, $hashed_password, $contact, $address);
    } else {
        // Only firstName and lastName exist (ideal scenario)
        $stmt = $conn->prepare("
            INSERT INTO USER_ACCOUNT (firstName, lastName, email, user_password, contNo, address, time_created, user_status)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'online')
        ");
        
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        
        // Bind parameters: firstName, lastName, email, password, contact, address
        $stmt->bind_param("ssssss", $firstName, $lastName, $email, $hashed_password, $contact, $address);
    }

	try {
		$executed = $stmt->execute();
	} catch (mysqli_sql_exception $e) {
		if ((int)$e->getCode() === 1062) { // duplicate key
			$stmt->close();
			die("Error: Email already registered. Please use a different email or log in.");
		}
		throw $e;
	}

	if ($executed) {
		$user_id = $stmt->insert_id;
		$stmt->close();

		// Ensure email verification table exists
		$conn->query("CREATE TABLE IF NOT EXISTS email_verifications (
			id INT AUTO_INCREMENT PRIMARY KEY,
			acc_id INT NOT NULL,
			token VARCHAR(255) NOT NULL,
			expires_at DATETIME NOT NULL,
			used_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			INDEX (acc_id),
			UNIQUE KEY token_unique (token)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		// Create verification token
		$raw = bin2hex(random_bytes(32));
		$token = hash('sha256', $raw . uniqid('', true));
		$expires = date('Y-m-d H:i:s', time() + 24 * 60 * 60);

		$ins = $conn->prepare("INSERT INTO email_verifications (acc_id, token, expires_at) VALUES (?, ?, ?)");
		if ($ins) {
			$ins->bind_param("iss", $user_id, $token, $expires);
			$ins->execute();
			$ins->close();
		}

		// Send verification email
		require_once __DIR__ . '/mailer.php';
		$appUrl = getenv('APP_URL');
		if ($appUrl) {
			$link = rtrim($appUrl, "/") . "/verify-email.php?token=" . urlencode($token);
		} else {
			$link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/\\') . "/verify-email.php?token=" . urlencode($token);
		}
		try {
			$fromEmail = getenv('SMTP_FROM_EMAIL');
			$fromName = getenv('SMTP_FROM_NAME') ?: 'Ticketix';
			if ($fromEmail) {
				$mail->setFrom($fromEmail, $fromName);
			} elseif (!empty($mail->Username)) {
				$mail->setFrom($mail->Username, $fromName);
			}
			$displayNameForEmail = !empty($displayName) ? $displayName : ($firstName . ' ' . $lastName);
			$mail->addAddress($email, $displayNameForEmail);
			$mail->Subject = 'Verify your Ticketix email address';
			$mail->Body = '<p>Hi ' . htmlspecialchars($displayNameForEmail, ENT_QUOTES, 'UTF-8') . ',</p>' .
				'<p>Thanks for signing up. Please verify your email by clicking the link below:</p>' .
				'<p><a href="' . $link . '">Verify Email</a></p>' .
				'<p>This link will expire in 24 hours.</p>';
			$mail->AltBody = "Hi $displayNameForEmail,\n\nPlease verify your email: $link\n\nThis link expires in 24 hours.";
			$mail->send();
		} catch (Exception $e) {
			echo "Registration successful, but we couldn't send the verification email: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
			exit();
		}

		// Do not auto-login; ask user to verify
		echo "Registration successful! Please check your email to verify your account.";
		exit();
    } else {
		echo "Error inserting record: " . $stmt->error;
    }

	$stmt->close();
} else {
    header("Location: signup.html");
    exit();
}

$conn->close();
?>
