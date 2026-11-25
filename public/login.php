<?php
require_once __DIR__ . '/../config/config.php';

// If already logged in, go to role dashboard
if (Auth::currentUser() !== null) {
    header('Location: ' . APP_URL . 'index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::enforceCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $user = Auth::attempt($username, $password);
        if ($user !== null) {
            Security::flash('success', 'Welcome back, ' . $user['full_name'] . '!');
            header('Location: ' . APP_URL . 'index.php');
            exit;
        }
        $error = 'Invalid credentials or inactive account.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-bg">
<div class="container d-flex align-items-center min-vh-100" style="max-width:440px">
  <div class="card shadow-lg w-100 border-0">
    <div class="card-body p-4 p-lg-5">
      <div class="text-center mb-4">
        <i class="fa-solid fa-landmark fa-3x" style="color:#0d3b66"></i>
        <h2 class="h4 mt-2 fw-bold"><?= APP_NAME ?></h2>
        <p class="text-muted small">Sign in with your officer or administrator account</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= Security::e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" novalidate>
        <?= Security::csrfField() ?>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required autofocus
                 value="<?= Security::e($_POST['username'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2">
          <i class="fa-solid fa-right-to-bracket me-1"></i>Sign In
        </button>
      </form>

      <div class="alert alert-info small mt-3 mb-0" role="note">
        <div class="fw-bold mb-1"><i class="fa-solid fa-circle-info me-1"></i>Demo accounts</div>
        <div><strong>Admin:</strong> <code>admin</code> / <code>Admin@123</code></div>
        <div><strong>Officers:</strong> <code>officer1</code>, <code>officer2</code>, or <code>officer3</code> / <code>Officer@123</code></div>
        <div class="mt-1">Change these passwords before production use.</div>
      </div>

      <hr>
      <div class="d-flex justify-content-between small">
        <a href="ticket.php" class="text-decoration-none"><i class="fa-solid fa-ticket me-1"></i>Applicant Ticket (public)</a>
        <a href="display.php" target="_blank" class="text-decoration-none"><i class="fa-solid fa-tv me-1"></i>Queue Display</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
