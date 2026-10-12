<?php
session_start();

// Virtual assistants only.
if (empty($_SESSION['user']) || $_SESSION['user']['type'] !== 'va') {
    header('Location: login.php');
    exit;
}

$me = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VA portal | ORBITDESK</title>

<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<script>
  window.ORBIT_USER = <?php echo json_encode($me); ?>;
</script>

<div class="app-shell">

  <aside class="sidebar" id="sidebar">

    <div class="brand-lockup sidebar-brand">
      <svg class="orbit-mark" viewBox="0 0 44 44" aria-hidden="true">
        <ellipse cx="22" cy="22" rx="20" ry="9" transform="rotate(-28 22 22)"/>
        <circle class="orbit-core" cx="22" cy="22" r="7"/>
        <circle class="orbit-dot" cx="39" cy="13" r="3.2"/>
      </svg>
      <span class="brand-word">ORBIT<span class="brand-word-light">DESK</span></span>
    </div>

    <nav class="side-nav">
      <p class="nav-group-label">VA portal</p>
      <button class="nav-item active" data-section="home">
        <span class="nav-glyph">&#9679;</span> Overview
      </button>
      <button class="nav-item" data-section="browse">
        <span class="nav-glyph">&#9632;</span> Find work
      </button>
      <button class="nav-item" data-section="applications">
        <span class="nav-glyph">&#9650;</span> My applications
      </button>
      <button class="nav-item" data-section="profile">
        <span class="nav-glyph">&#9670;</span> My profile
      </button>
    </nav>

    <div class="side-user">
      <div class="avatar-ring">
        <span class="avatar-initials"><?php echo htmlspecialchars(strtoupper(substr($me['name'], 0, 1))); ?></span>
      </div>
      <div class="side-user-meta">
        <p class="side-user-name"><?php echo htmlspecialchars($me['name']); ?></p>
        <p class="side-user-role">Virtual assistant</p>
      </div>
      <button class="btn-signout" id="signOutBtn" title="Sign out">&#10162;</button>
    </div>

  </aside>

  <div class="main-area">

    <header class="topbar">
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">&#9776;</button>

      <div class="topbar-heading">
        <h1 class="page-title" id="pageTitle">Overview</h1>
        <p class="page-sub" id="pageSub">Where your applications stand.</p>
      </div>

      <div class="topbar-tools">
        <div class="search-wrap d-none" id="searchWrap">
          <span class="search-icon">&#9906;</span>
          <input type="text" class="form-control search-input" id="globalSearch" placeholder="Search jobs">
          <button class="search-clear d-none" id="searchClear" title="Clear">&times;</button>
        </div>
        <select class="form-select filter-select d-none" id="globalFilter"></select>
      </div>
    </header>

    <div class="content-scroll">

      <?php if ($me['status'] !== 'verified'): ?>
        <div class="notice-strip">
          Your profile is still waiting for admin verification. You can browse the board,
          but applying is locked until an admin approves your profile.
        </div>
      <?php endif; ?>

      <!-- ============ OVERVIEW ============ -->
      <section class="section active" id="section-home">
        <div class="stat-grid" id="statGrid">
          <div class="skeleton-card"></div>
          <div class="skeleton-card"></div>
          <div class="skeleton-card"></div>
          <div class="skeleton-card"></div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <h2 class="panel-title">Your latest applications</h2>
            <p class="panel-note">The most recent roles you applied to.</p>
          </div>
          <div id="recentApplications" class="activity-feed"></div>
        </div>
      </section>

      <!-- ============ FIND WORK ============ -->
      <section class="section" id="section-browse">
        <div id="jobBoard" class="job-board"></div>
      </section>

      <!-- ============ MY APPLICATIONS ============ -->
      <section class="section" id="section-applications">
        <div class="table-card">
          <div class="table-responsive">
            <table class="table orbit-table">
              <thead>
                <tr>
                  <th>Role</th>
                  <th>Client</th>
                  <th>Asking</th>
                  <th>Status</th>
                  <th>Applied</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="applicationRows"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ============ PROFILE ============ -->
      <section class="section" id="section-profile">
        <div class="panel profile-panel">
          <div class="panel-head">
            <h2 class="panel-title">My profile</h2>
            <p class="panel-note">This is what clients see when you apply.</p>
          </div>

          <form id="profileForm" novalidate>
            <div class="row g-3">
              <div class="col-md-6 field">
                <label for="pfName">Full name</label>
                <input type="text" class="form-control" id="pfName" name="full_name">
              </div>
              <div class="col-md-6 field">
                <label for="pfEmail">Email</label>
                <input type="email" class="form-control" id="pfEmail" name="email">
              </div>
              <div class="col-md-6 field">
                <label for="pfPhone">Phone</label>
                <input type="text" class="form-control" id="pfPhone" name="phone">
              </div>
              <div class="col-md-6 field">
                <label for="pfAvailability">Availability</label>
                <select class="form-select" id="pfAvailability" name="availability">
                  <option value="full-time">Full-time</option>
                  <option value="part-time">Part-time</option>
                  <option value="project-based">Project-based</option>
                </select>
              </div>
              <div class="col-12 field">
                <label for="pfSkills">Skills</label>
                <input type="text" class="form-control" id="pfSkills" name="skills">
              </div>
              <div class="col-md-6 field">
                <label for="pfYears">Years of experience</label>
                <input type="number" min="0" max="50" class="form-control" id="pfYears" name="experience_years">
              </div>
              <div class="col-md-6 field">
                <label for="pfRate">Hourly rate (USD)</label>
                <input type="number" step="0.01" min="0" class="form-control" id="pfRate" name="hourly_rate">
              </div>
              <div class="col-12 field">
                <label for="pfPassword">New password</label>
                <input type="password" class="form-control" id="pfPassword" name="password" placeholder="Leave blank to keep your current password">
              </div>
            </div>

            <div class="profile-foot">
              <span class="profile-status" id="profileStatus"></span>
              <button type="submit" class="btn btn-orbit">Save changes</button>
            </div>
          </form>
        </div>
      </section>

    </div>
  </div>
</div>

<div class="sidebar-scrim" id="sidebarScrim"></div>
<div class="toast-stack" id="toastStack"></div>

<!-- ===================== APPLY MODAL ===================== -->
<div class="modal fade" id="applyModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content orbit-modal">
      <div class="modal-head">
        <div>
          <h3 class="modal-title-orbit">Apply for this role</h3>
          <p class="modal-sub" id="applySub"></p>
        </div>
        <button class="modal-x" data-bs-dismiss="modal">&times;</button>
      </div>
      <form id="applyForm" novalidate>
        <input type="hidden" name="job_id" id="applyJobId">
        <div class="modal-body-orbit">
          <div class="field">
            <label for="applyNote">Cover note</label>
            <textarea class="form-control" id="applyNote" name="cover_note" rows="4"
                      placeholder="Tell the client why you are a good fit. Keep it short and specific."></textarea>
          </div>
          <div class="field">
            <label for="applyRate">Your expected rate (USD per hour)</label>
            <input type="number" step="0.01" min="0" class="form-control" id="applyRate" name="expected_rate">
          </div>
          <p class="form-note">The client sees your name, skills, cover note, and rate.</p>
        </div>
        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-orbit">Send application</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== CONFIRM ===================== -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content orbit-modal">
      <div class="modal-body-orbit text-center">
        <h3 class="confirm-title">Withdraw this application?</h3>
        <p class="confirm-text" id="confirmText"></p>
      </div>
      <div class="modal-foot justify-content-center">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Keep it</button>
        <button type="button" class="btn btn-danger-orbit" id="confirmYes">Withdraw</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/vendor/jquery.min.js"></script>
<script src="assets/vendor/bootstrap.bundle.min.js"></script>
<script src="assets/js/va.js"></script>
</body>
</html>
