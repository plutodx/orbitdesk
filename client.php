<?php
session_start();

// Clients only. Anyone else gets bounced to the sign-in screen.
if (empty($_SESSION['user']) || $_SESSION['user']['type'] !== 'client') {
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
<title>Client portal | ORBITDESK</title>

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
      <p class="nav-group-label">Client portal</p>
      <button class="nav-item active" data-section="home">
        <span class="nav-glyph">&#9679;</span> Overview
      </button>
      <button class="nav-item" data-section="jobs">
        <span class="nav-glyph">&#9632;</span> My job postings
      </button>
      <button class="nav-item" data-section="profile">
        <span class="nav-glyph">&#9670;</span> Company profile
      </button>
    </nav>

    <div class="side-user">
      <div class="avatar-ring">
        <span class="avatar-initials"><?php echo htmlspecialchars(strtoupper(substr($me['name'], 0, 1))); ?></span>
      </div>
      <div class="side-user-meta">
        <p class="side-user-name"><?php echo htmlspecialchars($me['name']); ?></p>
        <p class="side-user-role">Client</p>
      </div>
      <button class="btn-signout" id="signOutBtn" title="Sign out">&#10162;</button>
    </div>

  </aside>

  <div class="main-area">

    <header class="topbar">
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">&#9776;</button>

      <div class="topbar-heading">
        <h1 class="page-title" id="pageTitle">Overview</h1>
        <p class="page-sub" id="pageSub">How your postings are doing.</p>
      </div>

      <div class="topbar-tools">
        <div class="search-wrap d-none" id="searchWrap">
          <span class="search-icon">&#9906;</span>
          <input type="text" class="form-control search-input" id="globalSearch" placeholder="Search my jobs">
          <button class="search-clear d-none" id="searchClear" title="Clear">&times;</button>
        </div>
        <button class="btn btn-orbit d-none" id="primaryAction">+ Post a job</button>
      </div>
    </header>

    <div class="content-scroll">

      <?php if ($me['status'] !== 'verified'): ?>
        <div class="notice-strip">
          Your company profile is still waiting for admin verification. You can look
          around, but posting jobs is locked until an admin approves the account.
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
            <h2 class="panel-title">Latest applicants</h2>
            <p class="panel-note">The most recent people who applied to your postings.</p>
          </div>
          <div id="recentApplicants" class="activity-feed"></div>
        </div>
      </section>

      <!-- ============ MY JOBS ============ -->
      <section class="section" id="section-jobs">
        <div id="jobBoard" class="job-board"></div>
      </section>

      <!-- ============ PROFILE ============ -->
      <section class="section" id="section-profile">
        <div class="panel profile-panel">
          <div class="panel-head">
            <h2 class="panel-title">Company profile</h2>
            <p class="panel-note">This is what virtual assistants see next to your postings.</p>
          </div>

          <form id="profileForm" novalidate>
            <div class="row g-3">
              <div class="col-12 field">
                <label for="pfCompany">Company name</label>
                <input type="text" class="form-control" id="pfCompany" name="company_name">
              </div>
              <div class="col-md-6 field">
                <label for="pfContact">Contact person</label>
                <input type="text" class="form-control" id="pfContact" name="contact_person">
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
                <label for="pfLocation">Location</label>
                <input type="text" class="form-control" id="pfLocation" name="location">
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

<!-- ===================== JOB MODAL ===================== -->
<div class="modal fade" id="jobModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content orbit-modal">
      <div class="modal-head">
        <h3 class="modal-title-orbit" id="jobModalTitle">Post a job</h3>
        <button class="modal-x" data-bs-dismiss="modal">&times;</button>
      </div>
      <form id="jobForm" novalidate>
        <input type="hidden" name="id" id="jobId">
        <div class="modal-body-orbit">
          <div class="row g-3">
            <div class="col-md-7 field">
              <label for="jobTitle">Job title</label>
              <input type="text" class="form-control" id="jobTitle" name="title" placeholder="Executive Assistant">
            </div>
            <div class="col-md-5 field">
              <label for="jobCategory">Category</label>
              <input type="text" class="form-control" id="jobCategory" name="category" list="categoryOptions" placeholder="Admin Support">
              <datalist id="categoryOptions"></datalist>
            </div>
            <div class="col-12 field">
              <label for="jobDescription">What the work involves</label>
              <textarea class="form-control" id="jobDescription" name="description" rows="3"></textarea>
            </div>
            <div class="col-md-4 field">
              <label for="jobRate">Rate offered</label>
              <input type="number" step="0.01" min="0" class="form-control" id="jobRate" name="rate_offered">
            </div>
            <div class="col-md-4 field">
              <label for="jobRateType">Rate type</label>
              <select class="form-select" id="jobRateType" name="rate_type">
                <option value="hourly">Per hour</option>
                <option value="monthly">Per month</option>
              </select>
            </div>
            <div class="col-md-4 field">
              <label for="jobSlots">Slots open</label>
              <input type="number" min="1" class="form-control" id="jobSlots" name="slots" value="1">
            </div>
            <div class="col-12 field">
              <label for="jobStatus">Posting status</label>
              <select class="form-select" id="jobStatus" name="status">
                <option value="open">Open, accepting applicants</option>
                <option value="filled">Filled</option>
                <option value="closed">Closed</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-orbit">Save job</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== APPLICANTS ===================== -->
<div class="modal fade" id="applicantsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content orbit-modal">
      <div class="modal-head">
        <div>
          <h3 class="modal-title-orbit">Applicants</h3>
          <p class="modal-sub" id="applicantsSub"></p>
        </div>
        <button class="modal-x" data-bs-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body-orbit" id="applicantsBody"></div>
    </div>
  </div>
</div>

<!-- ===================== CONFIRM ===================== -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content orbit-modal">
      <div class="modal-body-orbit text-center">
        <h3 class="confirm-title">Delete this posting?</h3>
        <p class="confirm-text" id="confirmText"></p>
      </div>
      <div class="modal-foot justify-content-center">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Keep it</button>
        <button type="button" class="btn btn-danger-orbit" id="confirmYes">Delete</button>
      </div>
    </div>
  </div>
</div>

<script src="assets/vendor/jquery.min.js"></script>
<script src="assets/vendor/bootstrap.bundle.min.js"></script>
<script src="assets/js/client.js"></script>
</body>
</html>
