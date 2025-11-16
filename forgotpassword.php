<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | Ticketix</title>
  <link rel="stylesheet" href="css/forgot-password.css">
</head>
<body>

  <div class="container">
    <h2>Forgot Password?</h2>
    <p>Enter your registered email to reset your password.</p>

    <form method="post" action="send-password-reset.php">
      <div class="input-field">
        <input type="email" name="email" id="email" placeholder=" " required>
        <label for="email">Email Address</label>
        <span class="underline"></span>
      </div>

      <button type="submit" class="btn">Reset Password</button>
    </form>

    <div class="links">
      <p>Not a member? <a href="signup.php">Sign Up</a></p>
      <p>Already have an account? <a href="login.php">Sign In</a></p>
      <p><a href="TICKETIX NI CLAIRE.php">← Back to Home</a></p>
    </div>
  </div>

</body>
</html>
