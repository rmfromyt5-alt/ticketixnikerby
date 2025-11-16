<?php
$token = isset($_GET['token']) ? $_GET['token'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Password Reset</title>
  <link rel="stylesheet" href="css/password-reset.css">
</head>
<body>

  <div class="container">
    <h2>Password Reset</h2>
    <p>Enter your new password below.</p>

    <form method="post" action="process-password-reset.php">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <label for="password">New Password</label>
      <input type="password" name="password" id="password" placeholder="Enter new password" required>

      <label for="password_confirmation">Repeat Password</label>
      <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Repeat new password" required>

      <input type="submit" value="Confirm">
    </form>

    <div class="links">
      <p><a href="login.php">Back to Login?</a></p>
      <p><a href="TICKETIX NI CLAIRE.php">Back to Home?</a></p>
    </div>
  </div>

</body>
</html>