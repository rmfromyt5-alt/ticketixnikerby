<?php
session_start();
require_once 'config.php';



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Only access $_POST when the form is submitted
    $input = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = $_POST['password'] ?? '';

    $conn = getDBConnection();

    if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // Use prepared statement to query by email
        $stmt = $conn->prepare("SELECT * FROM USER_ACCOUNT WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();

                // Compare passwords using password_verify for hashed passwords
                if (password_verify($password, $row['user_password'])) {
                    // Ensure email verified
                    $verifyStmt = $conn->prepare("SELECT 1 FROM email_verifications WHERE acc_id = ? AND used_at IS NOT NULL ORDER BY used_at DESC LIMIT 1");
                    if ($verifyStmt) {
                        $verifyStmt->bind_param("i", $row['acc_id']);
                        $verifyStmt->execute();
                        $verifiedRes = $verifyStmt->get_result();
                        $isVerified = $verifiedRes && $verifiedRes->num_rows > 0;
                        $verifyStmt->close();
                    } else {
                        $isVerified = false;
                    }

                    if (!$isVerified) {
                        $error_message = "Please verify your email before logging in. Check your inbox.";
                    } else {
                    // Clear any existing session data to prevent conflicts
                    $_SESSION = array();
                    
                    // Regenerate session ID for security (prevents session fixation)
                    session_regenerate_id(true);
                    
                    // Set session variables
                    // Prefer display name (fullName column), then firstName + lastName, then email
                    if (isset($row['fullName']) && !empty($row['fullName'])) {
                        $_SESSION['user_name'] = $row['fullName']; // Display name/nickname
                    } elseif (isset($row['firstName']) && isset($row['lastName'])) {
                        $_SESSION['user_name'] = $row['firstName'] . ' ' . $row['lastName'];
                    } else {
                        $_SESSION['user_name'] = $row['email']; // Fallback to email if no name available
                    }
                    $_SESSION['user_id'] = $row['acc_id'];
                    $_SESSION['acc_id'] = $row['acc_id']; // Also set acc_id for compatibility
                    $_SESSION['role'] = $row['role'] ?? 'user'; // ✅ store the user's role
                    $_SESSION['logged_in'] = true;

                    // Update user status to online
                    $update_query = "UPDATE USER_ACCOUNT SET user_status = 'online' WHERE acc_id = " . (int)$row['acc_id'];
                    $conn->query($update_query);

                    // ✅ Redirect based on role
                    if ($row['role'] === 'admin') {
                        header("Location: admin-panel.php"); // your admin panel file
                    } else {
                        header("Location: TICKETIX NI CLAIRE.php"); // normal homepage
                    }
                    exit();

                    }
                } else {
                    $error_message = "Invalid password.";
                }
            } else {
                $error_message = "No user found.";
            }

            $stmt->close();
        } else {
            $error_message = "Login error. Please try again later.";
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ticketix Login / Sign Up</title>
  <link rel="icon" type="image/png" href="images/brand x.png" />
  <link rel="stylesheet" href="css/login.css">
</head>
<body>
  <div class="container" id="container">
    <!-- Sign Up Form -->
    <div class="form-container sign-up-container">
      <form action="signup.php" method="POST" id="signup-form" novalidate>
        <h1>Create Account</h1>
        <input type="text" name="firstName" placeholder="First Name" required>
        <input type="text" name="lastName" placeholder="Last Name (Optional)">
        <input type="text" name="fullName" placeholder="Display Name / Nickname" required>
        <div class="email-input-wrapper">
          <input type="email" name="email" placeholder="Email" required id="email">
          <small id="email-help"></small>
        </div>
        <input type="password" name="password" placeholder="Password" required>
        <input type="tel" name="contact" placeholder="Contact Number" pattern="[0-9]{11}" maxlength="11" required>
        <input type="text" name="address" placeholder="Address" maxlength="100" required>
        <button type="submit">Sign Up</button>
      </form>
    </div>

    <!-- Sign In Form -->
    <div class="form-container sign-in-container">
      <form action="login.php" method="POST">
        <h1>Sign In</h1>
        <?php if (isset($error_message)): ?>
          <p style="color: red;"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
        <a href="forgotpassword.php">Forgot Password?</a>
      </form>
    </div>

    <!-- Overlay -->
    <div class="overlay-container">
      <div class="overlay">
        <div class="overlay-panel overlay-left">
          <h1>Welcome Back!</h1>
          <p>Please login your info!</p>
          <button class="ghost" id="signIn">Sign In</button>
        </div>
        <div class="overlay-panel overlay-right">
          <h1>Hello, Friend!</h1>
          <p>Register and start your Ticketix journey</p>
          <button class="ghost" id="signUp">Sign Up</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    const signUpButton = document.getElementById('signUp');
    const signInButton = document.getElementById('signIn');
    const container = document.getElementById('container');

    signUpButton.addEventListener('click', () => {
      container.classList.add("right-panel-active");
    });

    signInButton.addEventListener('click', () => {
      container.classList.remove("right-panel-active");
    });

    // Email validation from signup.html
    (function() {
      const form = document.getElementById('signup-form');
      if (!form) return; // Exit if form doesn't exist
      
      const emailInput = document.getElementById('email');
      const help = document.getElementById('email-help');
      if (!emailInput || !help) return; // Exit if elements don't exist
      
      let lastChecked = '';
      let inflight = 0;

      function show(msg) {
        help.textContent = msg;
        help.style.display = msg ? 'block' : 'none';
      }

      async function checkEmailAvailability(email) {
        try {
          const resp = await fetch('email-validation.php?email=' + encodeURIComponent(email), { cache: 'no-store' });
          const data = await resp.json();
          return data;
        } catch (e) {
          return { ok: false, reason: 'network' };
        }
      }

      async function validateEmail() {
        const value = emailInput.value.trim();
        if (!value) {
          show('Email is required.');
          return false;
        }

        const basic = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!basic.test(value)) {
          show('Please enter a valid email address.');
          return false;
        }

        if (value === lastChecked) {
          return help.style.display !== 'block';
        }

        lastChecked = value;
        inflight++;
        const res = await checkEmailAvailability(value);
        inflight--;

        if (!res.ok && res.reason === 'invalid_format') {
          show('Please enter a valid email address.');
          return false;
        }

        if (!res.ok) {
          show('Could not validate email right now. Try again.');
          return false;
        }

        if (res.available === false) {
          show('Email is already registered.');
          return false;
        }

        show('');
        return true;
      }

      emailInput.addEventListener('blur', validateEmail);
      emailInput.addEventListener('input', function() {
        show('');
      });

      form.addEventListener('submit', async function(e) {
        const valid = await validateEmail();
        if (!valid) {
          e.preventDefault();
        }
      });
    })();
  </script>
</body>
</html>