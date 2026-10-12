<?php
session_start();

// Guard the console. No session, no entry.
if (empty($_SESSION['user']) || $_SESSION['user']['type'] !== 'admin') {
    header('Location: admin-login.php');
    exit;
}

$currentAdmin = $_SESSION['admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ORBITDESK | Online Jobs Portal for Virtual Assistants</title>

<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Passes the signed-in admin to script.js without an extra AJAX round trip -->
<script>
  window.ORBIT_ADMIN = <?php echo json_encode($currentAdmin); ?>;
</script>

<div class="app-shell">

  <!-- ===================== SIDEBAR ===================== -->
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
      <p class="nav-group-label">Overview</p>
      <button class="nav-item active" data-section="dashboard">
        <span class="nav-glyph">&#9679;</span> Dashboard
      </button>

      <p class="nav-group-label">Marketplace</p>
      <button class="nav-item" data-section="jobs">
        <span class="nav-glyph">&#9632;</span> Job postings
      </button>
      <button class="nav-item" data-section="applications">
        <span class="nav-glyph">&#9650;</span> Applications
      </button>

      <p class="nav-group-label">Accounts</p>
      <button class="nav-item" data-section="vas">
        <span class="nav-glyph">&#9670;</span> Virtual assistants
      </button>
      <button class="nav-item" data-section="clients">
        <span class="nav-glyph">&#9644;</span> Clients
      </button>

      <p class="nav-group-label">Control</p>
      <button class="nav-item" data-section="moderation">
        <span class="nav-glyph">&#10003;</span> Moderation
        <span class="nav-badge d-none" id="queueBadge">0</span>
      </button>
      <button class="nav-item" data-section="admins">
        <span class="nav-glyph">&#9733;</span> Admin team
      </button>
    </nav>

    <div class="side-user">
      <div class="avatar-ring">
        <span class="avatar-initials"><?php echo htmlspecialchars(strtoupper(substr($currentAdmin['full_name'], 0, 1))); ?></span>
      </div>
      <div class="side-user-meta">
        <p class="side-user-name"><?php echo htmlspecialchars($currentAdmin['full_name']); ?></p>
        <p class="side-user-role">Administrator</p>
      </div>
      <button class="btn-signout" id="signOutBtn" title="Sign out">&#10162;</button>
    </div>

  </aside>

  <!-- ===================== MAIN ===================== -->
  <div class="main-area">

    <header class="topbar">
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">&#9776;</button>

      <div class="topbar-heading">
        <h1 class="page-title" id="pageTitle">Dashboard</h1>
        <p class="page-sub" id="pageSub">Everything moving through the portal right now.</p>
      </div>

      <div class="topbar-tools" id="topbarTools">
        <div class="search-wrap d-none" id="searchWrap">
          <span class="search-icon">&#9906;</span>
          <input type="text" class="form-control search-input" id="globalSearch" placeholder="Search...">
          <button class="search-clear d-none" id="searchClear" title="Clear">&times;</button>
        </div>

        <select class="form-select filter-select d-none" id="globalFilter"></select>

        <button class="btn btn-orbit d-none" id="primaryAction">+ New</button>
      </div>
    </header>

    <div class="content-scroll">

      <!-- ============ DASHBOARD ============ -->
      <section class="section active" id="section-dashboard">

        <div class="stat-grid" id="statGrid">
          <div class="skeleton-card"></div>
          <div class="skeleton-card"></div>
          <div class="skeleton-card"></div>
          <div class="skeleton-card"></div>
        </div>

        <div class="panel-row">
          <div class="panel">
            <div class="panel-head">
              <h2 class="panel-title">Application pipeline</h2>
              <p class="panel-note">Where every submitted application currently sits.</p>
            </div>
            <div id="pipelineMeter" class="pipeline-meter"></div>
            <div id="pipelineLegend" class="pipeline-legend"></div>
          </div>

          <div class="panel">
            <div class="panel-head">
              <h2 class="panel-title">Busiest categories</h2>
              <p class="panel-note">Ranked by applications received.</p>
            </div>
            <div id="categoryList" class="category-list"></div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <h2 class="panel-title">Latest activity</h2>
            <p class="panel-note">The six most recent applications across the portal.</p>
          </div>
          <div id="activityFeed" class="activity-feed"></div>
        </div>

      </section>

      <!-- ============ JOBS ============ -->
      <section class="section" id="section-jobs">
        <div id="jobBoard" class="job-board"></div>
      </section>

      <!-- ============ APPLICATIONS ============ -->
      <section class="section" id="section-applications">
        <div class="table-card">
          <div class="table-responsive">
            <table class="table orbit-table">
              <thead>
                <tr>
                  <th>Virtual assistant</th>
                  <th>Applied to</th>
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

      <!-- ============ VIRTUAL ASSISTANTS ============ -->
      <section class="section" id="section-vas">
        <div class="table-card">
          <div class="table-responsive">
            <table class="table orbit-table">
              <thead>
                <tr>
                  <th>Virtual assistant</th>
                  <th>Skills</th>
                  <th>Exp.</th>
                  <th>Rate</th>
                  <th>Availability</th>
                  <th>Profile</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="vaRows"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ============ CLIENTS ============ -->
      <section class="section" id="section-clients">
        <div class="table-card">
          <div class="table-responsive">
            <table class="table orbit-table">
              <thead>
                <tr>
                  <th>Company</th>
                  <th>Contact</th>
                  <th>Location</th>
                  <th>Jobs</th>
                  <th>Profile</th>
                  <th>Reviewed by</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="clientRows"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ============ MODERATION ============ -->
      <section class="section" id="section-moderation">
        <div class="mod-toolbar">
          <div class="seg-control" id="queueTabs">
            <button class="seg-btn active" data-status="pending">Waiting</button>
            <button class="seg-btn" data-status="verified">Verified</button>
            <button class="seg-btn" data-status="rejected">Rejected</button>
            <button class="seg-btn" data-status="">All</button>
          </div>
          <p class="mod-hint">Verification stamps the reviewing admin and the timestamp on the profile.</p>
        </div>
        <div id="queueGrid" class="queue-grid"></div>
      </section>

      <!-- ============ ADMINS ============ -->
      <section class="section" id="section-admins">
        <div class="table-card">
          <div class="table-responsive">
            <table class="table orbit-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Clients reviewed</th>
                  <th>VAs reviewed</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="adminRows"></tbody>
            </table>
          </div>
        </div>
      </section>

    </div><!-- /content-scroll -->
  </div><!-- /main-area -->
</div><!-- /app-shell -->

<div class="sidebar-scrim" id="sidebarScrim"></div>

<!-- ===================== TOAST ===================== -->
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
              <input type="text" class="form-control" id="jobTitle" name="title" placeholder="Executive Assistant to the CEO">
            </div>
            <div class="col-md-5 field">
              <label for="jobClient">Posted by</label>
              <select class="form-select" id="jobClient" name="client_id"></select>
            </div>
            <div class="col-md-5 field">
              <label for="jobCategory">Category</label>
              <input type="text" class="form-control" id="jobCategory" name="category" list="categoryOptions" placeholder="Admin Support">
              <datalist id="categoryOptions"></datalist>
            </div>
            <div class="col-md-3 field">
              <label for="jobRate">Rate offered</label>
              <input type="number" step="0.01" min="0" class="form-control" id="jobRate" name="rate_offered" placeholder="10.00">
            </div>
            <div class="col-md-4 field">
              <label for="jobRateType">Rate type</label>
              <select class="form-select" id="jobRateType" name="rate_type">
                <option value="hourly">Per hour</option>
                <option value="monthly">Per month</option>
              </select>
            </div>
            <div class="col-12 field">
              <label for="jobDescription">What the work involves</label>
              <textarea class="form-control" id="jobDescription" name="description" rows="3" placeholder="Describe the day to day tasks."></textarea>
            </div>
            <div class="col-md-6 field">
              <label for="jobSlots">Slots open</label>
              <input type="number" min="1" class="form-control" id="jobSlots" name="slots" value="1">
            </div>
            <div class="col-md-6 field">
              <label for="jobStatus">Posting status</label>
              <select class="form-select" id="jobStatus" name="status">
                <option value="open">Open</option>
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

<!-- ===================== CLIENT MODAL ===================== -->
<div class="modal fade" id="clientModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content orbit-modal">
      <div class="modal-head">
        <h3 class="modal-title-orbit" id="clientModalTitle">Add a client</h3>
        <button class="modal-x" data-bs-dismiss="modal">&times;</button>
      </div>
      <form id="clientForm" novalidate>
        <input type="hidden" name="id" id="clientId">
        <div class="modal-body-orbit">
          <div class="row g-3">
            <div class="col-12 field">
              <label for="clientCompany">Company name</label>
              <input type="text" class="form-control" id="clientCompany" name="company_name">
            </div>
            <div class="col-md-6 field">
              <label for="clientContact">Contact person</label>
              <input type="text" class="form-control" id="clientContact" name="contact_person">
            </div>
            <div class="col-md-6 field">
              <label for="clientEmail">Email</label>
              <input type="email" class="form-control" id="clientEmail" name="email">
            </div>
            <div class="col-md-6 field">
              <label for="clientPhone">Phone</label>
              <input type="text" class="form-control" id="clientPhone" name="phone">
            </div>
            <div class="col-md-6 field">
              <label for="clientLocation">Location</label>
              <input type="text" class="form-control" id="clientLocation" name="location">
            </div>
          </div>
          <p class="form-note" id="clientFormNote">New client accounts land in the moderation queue as pending.</p>
        </div>
        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-orbit">Save client</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== VA MODAL ===================== -->
<div class="modal fade" id="vaModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content orbit-modal">
      <div class="modal-head">
        <h3 class="modal-title-orbit" id="vaModalTitle">Add a virtual assistant</h3>
        <button class="modal-x" data-bs-dismiss="modal">&times;</button>
      </div>
      <form id="vaForm" novalidate>
        <input type="hidden" name="id" id="vaId">
        <div class="modal-body-orbit">
          <div class="row g-3">
            <div class="col-md-6 field">
              <label for="vaName">Full name</label>
              <input type="text" class="form-control" id="vaName" name="full_name">
            </div>
            <div class="col-md-6 field">
              <label for="vaEmail">Email</label>
              <input type="email" class="form-control" id="vaEmail" name="email">
            </div>
            <div class="col-md-6 field">
              <label for="vaPhone">Phone</label>
              <input type="text" class="form-control" id="vaPhone" name="phone">
            </div>
            <div class="col-md-6 field">
              <label for="vaAvailability">Availability</label>
              <select class="form-select" id="vaAvailability" name="availability">
                <option value="full-time">Full-time</option>
                <option value="part-time">Part-time</option>
                <option value="project-based">Project-based</option>
              </select>
            </div>
            <div class="col-12 field">
              <label for="vaSkills">Skills</label>
              <input type="text" class="form-control" id="vaSkills" name="skills" placeholder="Calendar management, Email handling, CRM data entry">
            </div>
            <div class="col-md-6 field">
              <label for="vaYears">Years of experience</label>
              <input type="number" min="0" max="50" class="form-control" id="vaYears" name="experience_years" value="0">
            </div>
            <div class="col-md-6 field">
              <label for="vaRate">Hourly rate (USD)</label>
              <input type="number" step="0.01" min="0" class="form-control" id="vaRate" name="hourly_rate">
            </div>
          </div>
          <p class="form-note" id="vaFormNote">New profiles land in the moderation queue as pending.</p>
        </div>
        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-orbit">Save profile</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== APPLICATION MODAL ===================== -->
<div class="modal fade" id="applicationModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content orbit-modal">
      <div class="modal-head">
        <h3 class="modal-title-orbit" id="applicationModalTitle">Record an application</h3>
        <button class="modal-x" data-bs-dismiss="modal">&times;</button>
      </div>
      <form id="applicationForm" novalidate>
        <input type="hidden" name="id" id="applicationId">
        <div class="modal-body-orbit">
          <div class="row g-3">
            <div class="col-12 field">
              <label for="appJob">Job posting</label>
              <select class="form-select" id="appJob" name="job_id"></select>
            </div>
            <div class="col-12 field">
              <label for="appVa">Virtual assistant</label>
              <select class="form-select" id="appVa" name="va_id"></select>
            </div>
            <div class="col-12 field">
              <label for="appNote">Cover note</label>
              <textarea class="form-control" id="appNote" name="cover_note" rows="3" placeholder="Why this VA fits the role."></textarea>
            </div>
            <div class="col-md-6 field">
              <label for="appRate">Expected rate</label>
              <input type="number" step="0.01" min="0" class="form-control" id="appRate" name="expected_rate">
            </div>
            <div class="col-md-6 field">
              <label for="appStatus">Status</label>
              <select class="form-select" id="appStatus" name="status">
                <option value="pending">Pending</option>
                <option value="shortlisted">Shortlisted</option>
                <option value="hired">Hired</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
          </div>
          <p class="form-note">Only verified virtual assistants and open jobs appear in these lists.</p>
        </div>
        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-orbit">Save application</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== ADMIN MODAL ===================== -->
<div class="modal fade" id="adminModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content orbit-modal">
      <div class="modal-head">
        <h3 class="modal-title-orbit" id="adminModalTitle">Add an admin</h3>
        <button class="modal-x" data-bs-dismiss="modal">&times;</button>
      </div>
      <form id="adminForm" novalidate>
        <input type="hidden" name="id" id="adminId">
        <div class="modal-body-orbit">
          <div class="row g-3">
            <div class="col-12 field">
              <label for="adminName">Full name</label>
              <input type="text" class="form-control" id="adminName" name="full_name">
            </div>
            <div class="col-12 field">
              <label for="adminEmail">Email</label>
              <input type="email" class="form-control" id="adminEmail" name="email">
            </div>
            <div class="col-12 field">
              <label for="adminPassword">Password</label>
              <input type="password" class="form-control" id="adminPassword" name="password" placeholder="At least 6 characters">
              <span class="field-hint" id="adminPasswordHint">Leave blank to keep the current password.</span>
            </div>
            <div class="col-12 field">
              <label for="adminStatus">Account status</label>
              <select class="form-select" id="adminStatus" name="admin_status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-orbit">Save admin</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== APPLICANTS DRAWER ===================== -->
<div class="modal fade" id="applicantsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content orbit-modal">
      <div class="modal-head">
        <div>
          <h3 class="modal-title-orbit" id="applicantsTitle">Applicants</h3>
          <p class="modal-sub" id="applicantsSub"></p>
        </div>
        <button class="modal-x" data-bs-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body-orbit" id="applicantsBody"></div>
    </div>
  </div>
</div>

<!-- ===================== CONFIRM MODAL ===================== -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content orbit-modal">
      <div class="modal-body-orbit text-center">
        <h3 class="confirm-title" id="confirmTitle">Delete this record?</h3>
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
<script src="assets/js/script.js"></script>
</body>
</html>
