<?php
session_start();

// Already signed in? Send them to the right home screen.
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
<title>Sign in | ORBITDESK</title>

<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-body">

<div class="auth-shell">

  <!-- Brand side -->
  <aside class="auth-brand">
    <div class="auth-brand-inner">

      <div class="brand-lockup">
        <svg class="orbit-mark" viewBox="0 0 44 44" aria-hidden="true">
          <ellipse cx="22" cy="22" rx="20" ry="9" transform="rotate(-28 22 22)"/>
          <circle class="orbit-core" cx="22" cy="22" r="7"/>
          <circle class="orbit-dot" cx="39" cy="13" r="3.2"/>
        </svg>
        <span class="brand-word">ORBIT<span class="brand-word-light">DESK</span></span>
      </div>

      <h1 class="auth-headline">Remote talent,<br>kept in orbit.</h1>

      <p class="auth-sub">
        Clients post the work. Virtual assistants apply for it. Admins verify both sides
        before anything goes live on the board.
      </p>

      <ul class="auth-points">
        <li><span class="tick"></span> Virtual assistants browse and apply</li>
        <li><span class="tick"></span> Clients post jobs and pick applicants</li>
        <li><span class="tick"></span> Admins verify every profile first</li>
      </ul>

      <p class="auth-foot">ITELEC 4100 Project #1 &middot; Object-Oriented PHP, MySQL, AJAX</p>
    </div>
  </aside>

  <!-- Form side -->
  <main class="auth-panel">
    <div class="auth-form-wrap">

      <p class="eyebrow">Sign in</p>
      <h2 class="auth-title">Welcome back</h2>

      <div class="role-tabs" id="roleTabs">
        <button type="button" class="role-tab active" data-role="va">Virtual assistant</button>
        <button type="button" class="role-tab" data-role="client">Client</button>
      </div>

      <div id="loginAlert" class="alert-slim d-none"></div>

      <form id="loginForm" autocomplete="off" novalidate>
        <div class="field">
          <label for="loginEmail">Email address</label>
          <input type="email" class="form-control" id="loginEmail" name="email" placeholder="you@email.com" required>
        </div>

        <div class="field">
          <label for="loginPassword">Password</label>
          <input type="password" class="form-control" id="loginPassword" name="password" placeholder="Enter your password" required>
        </div>

        <button type="submit" class="btn btn-orbit w-100" id="loginBtn">
          <span class="btn-label">Sign in</span>
        </button>
      </form>

      <p class="auth-switch">
        New here? <a href="register.php">Create an account</a>
      </p>

      <div class="demo-card">
        <p class="demo-title">Demo accounts</p>

        <div class="demo-set" data-for="va">
          <button type="button" class="demo-row" data-email="jasmine.ocampo@gmail.com" data-password="va123">
            <span class="demo-role">verified</span>
            <span class="demo-mail">jasmine.ocampo@gmail.com</span>
            <span class="demo-pass">va123</span>
          </button>
          <button type="button" class="demo-row" data-email="paolo.santos@gmail.com" data-password="va123">
            <span class="demo-role pending">pending</span>
            <span class="demo-mail">paolo.santos@gmail.com</span>
            <span class="demo-pass">va123</span>
          </button>
        </div>

        <div class="demo-set d-none" data-for="client">
          <button type="button" class="demo-row" data-email="alicia@northgateproperty.com" data-password="client123">
            <span class="demo-role">verified</span>
            <span class="demo-mail">alicia@northgateproperty.com</span>
            <span class="demo-pass">client123</span>
          </button>
          <button type="button" class="demo-row" data-email="owen@driftwoodtravel.com" data-password="client123">
            <span class="demo-role pending">pending</span>
            <span class="demo-mail">owen@driftwoodtravel.com</span>
            <span class="demo-pass">client123</span>
          </button>
        </div>

        <p class="demo-hint">Tap a row to fill the form. The pending accounts show what an unverified profile sees.</p>
      </div>

    </div>
  </main>

</div>

<script src="assets/vendor/jquery.min.js"></script>
<script src="assets/js/login.js"></script>
</body>
</html>
