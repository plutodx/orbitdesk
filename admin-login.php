<?php
session_start();

// Already signed in? Send them wherever they belong.
if (!empty($_SESSION['user'])) {
    $landing = ['admin' => 'index.php', 'client' => 'client.php', 'va' => 'va.php'];
    header('Location: ' . $landing[$_SESSION['user']['type']]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Staff sign in | ORBITDESK</title>

<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-body">

<div class="auth-shell auth-shell-staff">

  <main class="auth-panel auth-panel-staff">
    <div class="auth-form-wrap">

      <div class="brand-lockup staff-lockup">
        <svg class="orbit-mark" viewBox="0 0 44 44" aria-hidden="true">
          <ellipse cx="22" cy="22" rx="20" ry="9" transform="rotate(-28 22 22)"/>
          <circle class="orbit-core" cx="22" cy="22" r="7"/>
          <circle class="orbit-dot" cx="39" cy="13" r="3.2"/>
        </svg>
        <span class="brand-word">ORBIT<span class="brand-word-light">DESK</span></span>
      </div>

      <p class="eyebrow eyebrow-staff">Staff only</p>
      <h2 class="auth-title auth-title-staff">Moderation console</h2>
      <p class="staff-note">
        This sign-in is for portal administrators. Clients and virtual assistants
        use the main sign-in page.
      </p>

      <div id="loginAlert" class="alert-slim d-none"></div>

      <!-- The role is fixed here, so this form can only ever sign in an admin -->
      <input type="hidden" id="fixedRole" value="admin">

      <form id="loginForm" autocomplete="off" novalidate>
        <div class="field">
          <label for="loginEmail">Work email</label>
          <input type="email" class="form-control" id="loginEmail" name="email" placeholder="you@orbitdesk.ph" required>
        </div>

        <div class="field">
          <label for="loginPassword">Password</label>
          <input type="password" class="form-control" id="loginPassword" name="password" placeholder="Enter your password" required>
        </div>

        <button type="submit" class="btn btn-orbit w-100" id="loginBtn">
          <span class="btn-label">Sign in</span>
        </button>
      </form>

      <div class="demo-card demo-card-staff">
        <p class="demo-title">Demo accounts</p>
        <button type="button" class="demo-row" data-email="admin@orbitdesk.ph" data-password="admin123">
          <span class="demo-role">admin</span>
          <span class="demo-mail">admin@orbitdesk.ph</span>
          <span class="demo-pass">admin123</span>
        </button>
        <button type="button" class="demo-row" data-email="mariel@orbitdesk.ph" data-password="mod123">
          <span class="demo-role">admin</span>
          <span class="demo-mail">mariel@orbitdesk.ph</span>
          <span class="demo-pass">mod123</span>
        </button>
        <p class="demo-hint">Tap a row to fill the form.</p>
      </div>

      <p class="auth-switch">
        Not staff? <a href="login.php">Go to the main sign in</a>
      </p>

    </div>
  </main>

</div>

<script src="assets/vendor/jquery.min.js"></script>
<script src="assets/js/login.js"></script>
</body>
</html>
