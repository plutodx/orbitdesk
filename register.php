<?php
session_start();

// Already signed in? No reason to be on the signup page.
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
<title>Create an account | ORBITDESK</title>

<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="auth-body">

<div class="auth-shell">

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

      <h1 class="auth-headline">Join the<br>board.</h1>

      <p class="auth-sub">
        Sign up as a virtual assistant looking for work, or as a client with work to
        hand off. An admin reviews every new profile before it appears publicly.
      </p>

      <ul class="auth-points">
        <li><span class="tick"></span> Free to create an account</li>
        <li><span class="tick"></span> Verification usually takes a day</li>
        <li><span class="tick"></span> You can sign in while you wait</li>
      </ul>

      <p class="auth-foot">ITELEC 4100 Project #1 &middot; Object-Oriented PHP, MySQL, AJAX</p>
    </div>
  </aside>

  <main class="auth-panel">
    <div class="auth-form-wrap auth-form-wide">

      <p class="eyebrow">Create an account</p>
      <h2 class="auth-title">Tell us who you are</h2>

      <div class="role-tabs" id="roleTabs">
        <button type="button" class="role-tab active" data-role="va">I'm a virtual assistant</button>
        <button type="button" class="role-tab" data-role="client">I'm a client</button>
      </div>

      <div id="registerAlert" class="alert-slim d-none"></div>

      <!-- ---------- VA SIGNUP ---------- -->
      <form id="vaSignupForm" class="signup-form" autocomplete="off" novalidate>
        <div class="row g-3">
          <div class="col-md-6 field">
            <label for="rvName">Full name</label>
            <input type="text" class="form-control" id="rvName" name="full_name">
          </div>
          <div class="col-md-6 field">
            <label for="rvEmail">Email</label>
            <input type="email" class="form-control" id="rvEmail" name="email">
          </div>
          <div class="col-md-6 field">
            <label for="rvPhone">Phone</label>
            <input type="text" class="form-control" id="rvPhone" name="phone">
          </div>
          <div class="col-md-6 field">
            <label for="rvPassword">Password</label>
            <input type="password" class="form-control" id="rvPassword" name="password" placeholder="At least 6 characters">
          </div>
          <div class="col-12 field">
            <label for="rvSkills">Skills</label>
            <input type="text" class="form-control" id="rvSkills" name="skills" placeholder="Calendar management, Email handling, Bookkeeping">
          </div>
          <div class="col-md-4 field">
            <label for="rvYears">Years of experience</label>
            <input type="number" min="0" max="50" class="form-control" id="rvYears" name="experience_years" value="1">
          </div>
          <div class="col-md-4 field">
            <label for="rvRate">Hourly rate (USD)</label>
            <input type="number" step="0.01" min="0" class="form-control" id="rvRate" name="hourly_rate" placeholder="8.00">
          </div>
          <div class="col-md-4 field">
            <label for="rvAvailability">Availability</label>
            <select class="form-select" id="rvAvailability" name="availability">
              <option value="full-time">Full-time</option>
              <option value="part-time">Part-time</option>
              <option value="project-based">Project-based</option>
            </select>
          </div>
        </div>

        <button type="submit" class="btn btn-orbit w-100 mt-2">
          <span class="btn-label">Create my profile</span>
        </button>
      </form>

      <!-- ---------- CLIENT SIGNUP ---------- -->
      <form id="clientSignupForm" class="signup-form d-none" autocomplete="off" novalidate>
        <div class="row g-3">
          <div class="col-12 field">
            <label for="rcCompany">Company name</label>
            <input type="text" class="form-control" id="rcCompany" name="company_name">
          </div>
          <div class="col-md-6 field">
            <label for="rcContact">Contact person</label>
            <input type="text" class="form-control" id="rcContact" name="contact_person">
          </div>
          <div class="col-md-6 field">
            <label for="rcEmail">Email</label>
            <input type="email" class="form-control" id="rcEmail" name="email">
          </div>
          <div class="col-md-6 field">
            <label for="rcPhone">Phone</label>
            <input type="text" class="form-control" id="rcPhone" name="phone">
          </div>
          <div class="col-md-6 field">
            <label for="rcLocation">Location</label>
            <input type="text" class="form-control" id="rcLocation" name="location" placeholder="San Diego, CA">
          </div>
          <div class="col-12 field">
            <label for="rcPassword">Password</label>
            <input type="password" class="form-control" id="rcPassword" name="password" placeholder="At least 6 characters">
          </div>
        </div>

        <button type="submit" class="btn btn-orbit w-100 mt-2">
          <span class="btn-label">Create my account</span>
        </button>
      </form>

      <p class="auth-switch">
        Already registered? <a href="login.php">Sign in instead</a>
      </p>

    </div>
  </main>

</div>

<script src="assets/vendor/jquery.min.js"></script>
<script src="assets/js/register.js"></script>
</body>
</html>
