/* =====================================================================
   ORBITDESK - assets/js/va.js
   The virtual assistant side of the portal. A VA browses open jobs from
   verified clients, applies, and tracks their own applications.
   ===================================================================== */

$(function () {

    'use strict';

    const ENDPOINT = 'db/request.php';
    const USER     = window.ORBIT_USER || {};
    const VERIFIED = USER.status === 'verified';

    let currentSection = 'home';
    let searchTimer    = null;
    let pendingWithdraw = null;

    /* ----------------------------- helpers ----------------------------- */

    function esc(value) {
        if (value === null || value === undefined) { return ''; }
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function money(value) { return '$' + parseFloat(value || 0).toFixed(2); }

    function niceDate(value) {
        if (!value) { return '--'; }
        const d = new Date(String(value).replace(' ', 'T'));
        return isNaN(d) ? esc(value) : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function pill(status) {
        return '<span class="pill pill-' + esc(status) + '">' + esc(status) + '</span>';
    }

    function toast(message, isError) {
        const $t = $('<div class="toast-orbit' + (isError ? ' error' : '') + '">' +
                     '<span class="t-dot"></span><span>' + esc(message) + '</span></div>');
        $('#toastStack').append($t);
        setTimeout(function () {
            $t.addClass('leaving');
            setTimeout(function () { $t.remove(); }, 220);
        }, 3400);
    }

    function emptyState(title, text, colspan) {
        const block = '<div class="empty-state"><div class="empty-ring">&#9675;</div>' +
                      '<p class="empty-title">' + esc(title) + '</p>' +
                      '<p class="empty-text">' + esc(text) + '</p></div>';
        return colspan ? '<tr><td colspan="' + colspan + '">' + block + '</td></tr>' : block;
    }

    function api(data, onDone) {
        return $.ajax({
            url: ENDPOINT, type: 'POST', dataType: 'json', data: data,
            success: function (res) {
                if (res.status === 'unauthorized') {
                    toast(res.message || 'Session expired.', true);
                    setTimeout(function () { window.location.href = 'login.php'; }, 1200);
                    return;
                }
                onDone(res);
            },
            error: function (xhr) {
                console.error('Request failed:', xhr.responseText);
                toast('The server did not respond correctly.', true);
            }
        });
    }

    /* --------------------------- navigation --------------------------- */

    const SECTIONS = {
        home: {
            title: 'Overview',
            sub:   'Where your applications stand.',
            load:  loadHome
        },
        browse: {
            title:  'Find work',
            sub:    'Open roles from verified clients.',
            search: 'Search roles, skills, companies',
            filter: 'categories',
            load:   loadBrowse
        },
        applications: {
            title:  'My applications',
            sub:    'Everything you have applied to.',
            filter: [
                { value: '',            label: 'All statuses' },
                { value: 'pending',     label: 'Pending' },
                { value: 'shortlisted', label: 'Shortlisted' },
                { value: 'hired',       label: 'Hired' },
                { value: 'rejected',    label: 'Rejected' }
            ],
            load: loadApplications
        },
        profile: {
            title: 'My profile',
            sub:   'This is what clients see when you apply.',
            load:  loadProfile
        }
    };

    let jobCategories = [];

    function switchSection(name) {
        currentSection = name;
        const config = SECTIONS[name];

        $('.nav-item').removeClass('active').filter('[data-section="' + name + '"]').addClass('active');
        $('.section').removeClass('active');
        $('#section-' + name).addClass('active');

        $('#pageTitle').text(config.title);
        $('#pageSub').text(config.sub);

        $('#globalSearch').val('');
        $('#searchClear').addClass('d-none');

        if (config.search) {
            $('#searchWrap').removeClass('d-none');
            $('#globalSearch').attr('placeholder', config.search);
        } else {
            $('#searchWrap').addClass('d-none');
        }

        const $filter = $('#globalFilter');
        if (config.filter === 'categories') {
            let html = '<option value="">All categories</option>';
            jobCategories.forEach(function (row) {
                html += '<option value="' + esc(row.category) + '">' + esc(row.category) + '</option>';
            });
            $filter.removeClass('d-none').html(html);
        } else if (Array.isArray(config.filter)) {
            let html = '';
            config.filter.forEach(function (o) {
                html += '<option value="' + esc(o.value) + '">' + esc(o.label) + '</option>';
            });
            $filter.removeClass('d-none').html(html);
        } else {
            $filter.addClass('d-none').empty();
        }

        closeSidebar();
        config.load();
    }

    /* ---------------------------- overview ---------------------------- */

    function loadHome() {
        api({ action: 'va_stats' }, function (res) {
            if (res.status !== 'success') { return; }
            const d = res.data;

            const cards = [
                { label: 'Applications sent', value: d.applications_sent, tone: 'violet', foot: 'Across every role you applied to' },
                { label: 'Shortlisted',       value: d.shortlisted,       tone: 'ink',    foot: 'Clients looking at you right now' },
                { label: 'Hired',             value: d.hired,             tone: 'jade',   foot: 'Roles you landed' },
                { label: 'Open roles',        value: d.jobs_open,         tone: 'amber',  foot: 'Waiting on the board today' }
            ];

            let html = '';
            cards.forEach(function (c) {
                html += '<article class="stat-card accent-' + c.tone + '">' +
                          '<p class="stat-label">' + esc(c.label) + '</p>' +
                          '<p class="stat-value">' + esc(c.value) + '</p>' +
                          '<p class="stat-foot">' + c.foot + '</p>' +
                        '</article>';
            });
            $('#statGrid').html(html);
        });

        api({ action: 'va_applications' }, function (res) {
            const rows = res.data || [];

            if (!rows.length) {
                $('#recentApplications').html(emptyState('Nothing yet', 'Head to Find work and apply to your first role.'));
                return;
            }

            let html = '';
            rows.slice(0, 6).forEach(function (app) {
                html += '<div class="activity-row">' +
                          '<span class="activity-dot"></span>' +
                          '<span class="activity-text"><strong>' + esc(app.job_title) + '</strong> at ' + esc(app.company_name) + '</span>' +
                          pill(app.status) +
                          '<span class="activity-meta">' + niceDate(app.applied_at) + '</span>' +
                        '</div>';
            });
            $('#recentApplications').html(html);
        });
    }

    /* ---------------------------- find work --------------------------- */

    function loadBrowse() {
        api({
            action:  'va_browse',
            keyword: $('#globalSearch').val(),
            filter:  $('#globalFilter').val() || ''
        }, function (res) {
            if (res.status !== 'success') { return; }

            const rows = res.data || [];
            if (!rows.length) {
                $('#jobBoard').html(emptyState('No open roles match', 'Try a different keyword or clear the category filter.'));
                return;
            }

            let html = '';
            rows.forEach(function (job) {
                const rateLabel = job.rate_type === 'monthly' ? '/mo' : '/hr';
                const applied   = parseInt(job.already_applied, 10) > 0;

                let button;
                if (applied) {
                    button = '<button class="btn-applied" disabled>Applied</button>';
                } else if (!VERIFIED) {
                    button = '<button class="btn-applied" disabled title="Verification pending">Locked</button>';
                } else {
                    button = '<button class="btn-apply" data-apply="' + esc(job.id) + '" ' +
                             'data-title="' + esc(job.title) + '" data-company="' + esc(job.company_name) + '" ' +
                             'data-rate="' + esc(job.rate_offered) + '">Apply</button>';
                }

                html += '<article class="job-card">' +
                          '<div class="job-card-top">' +
                            '<span class="job-cat">' + esc(job.category) + '</span>' +
                            '<span class="cell-sub" style="margin:0">' + esc(job.applicant_count) + ' applied</span>' +
                          '</div>' +
                          '<h3 class="job-title">' + esc(job.title) + '</h3>' +
                          '<p class="job-company"><strong>' + esc(job.company_name) + '</strong> &middot; ' + esc(job.location) + '</p>' +
                          '<p class="job-desc">' + esc(job.description) + '</p>' +
                          '<div class="job-meta">' +
                            '<span class="job-rate">' + money(job.rate_offered) + '<span>' + rateLabel + '</span></span>' +
                            '<span class="job-actions">' + button + '</span>' +
                          '</div>' +
                        '</article>';
            });

            $('#jobBoard').html(html);
        });
    }

    $(document).on('click', '[data-apply]', function () {
        const $btn = $(this);

        $('#applyForm')[0].reset();
        $('#applyJobId').val($btn.data('apply'));
        $('#applySub').text($btn.data('title') + ' at ' + $btn.data('company'));
        $('#applyRate').val($btn.data('rate'));

        new bootstrap.Modal('#applyModal').show();
    });

    $('#applyForm').on('submit', function (e) {
        e.preventDefault();

        const data = $(this).serializeArray();
        data.push({ name: 'action', value: 'va_apply' });

        const $submit = $(this).find('button[type="submit"]');
        $submit.prop('disabled', true).text('Sending...');

        api(data, function (res) {
            $submit.prop('disabled', false).text('Send application');

            if (res.status !== 'success') {
                toast(res.message || 'Could not send the application.', true);
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('applyModal')).hide();
            toast(res.message);
            loadBrowse();
        });
    });

    /* ------------------------- my applications ------------------------ */

    function loadApplications() {
        api({ action: 'va_applications', filter: $('#globalFilter').val() || '' }, function (res) {
            if (res.status !== 'success') { return; }

            const rows = res.data || [];
            if (!rows.length) {
                $('#applicationRows').html(emptyState('No applications', 'Roles you apply to will be listed here.', 6));
                return;
            }

            let html = '';
            rows.forEach(function (app) {
                html += '<tr>' +
                          '<td><span class="cell-primary">' + esc(app.job_title) + '</span>' +
                              '<span class="cell-sub">' + esc(app.category) + '</span></td>' +
                          '<td>' + esc(app.company_name) +
                            ((app.status === 'shortlisted' || app.status === 'hired')
                              ? '<span class="cell-sub"><a href="mailto:' + esc(app.client_email) + '">' +
                                esc(app.client_email) + '</a></span>'
                              : '') +
                          '</td>' +
                          '<td class="cell-mono">' + money(app.expected_rate) + '</td>' +
                          '<td>' + pill(app.status) + '</td>' +
                          '<td class="cell-mono">' + niceDate(app.applied_at) + '</td>' +
                          '<td class="text-end">' +
                            (app.status === 'hired'
                              ? '<span class="cell-sub" style="margin:0">Hired</span>'
                              : '<button class="act act-danger" data-withdraw="' + esc(app.id) + '" data-label="' + esc(app.job_title) + '" title="Withdraw">&#128465;</button>') +
                          '</td>' +
                        '</tr>';
            });

            $('#applicationRows').html(html);
        });
    }

    $(document).on('click', '[data-withdraw]', function () {
        pendingWithdraw = $(this).data('withdraw');
        $('#confirmText').text('Your application to "' + $(this).data('label') + '" will be removed.');
        new bootstrap.Modal('#confirmModal').show();
    });

    $('#confirmYes').on('click', function () {
        if (!pendingWithdraw) { return; }

        api({ action: 'va_withdraw', id: pendingWithdraw }, function (res) {
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            toast(res.message, res.status !== 'success');
            if (res.status === 'success') { loadApplications(); }
            pendingWithdraw = null;
        });
    });

    /* ----------------------------- profile ---------------------------- */

    function loadProfile() {
        api({ action: 'va_profile' }, function (res) {
            const v = res.data;
            if (!v) { return; }

            $('#pfName').val(v.full_name);
            $('#pfEmail').val(v.email);
            $('#pfPhone').val(v.phone);
            $('#pfSkills').val(v.skills);
            $('#pfYears').val(v.experience_years);
            $('#pfRate').val(v.hourly_rate);
            $('#pfAvailability').val(v.availability);
            $('#pfPassword').val('');

            $('#profileStatus').html('Profile status: ' + pill(v.status) +
                (v.verified_by_name ? ' <span class="cell-sub" style="display:inline">reviewed by ' + esc(v.verified_by_name) + '</span>' : ''));
        });
    }

    $('#profileForm').on('submit', function (e) {
        e.preventDefault();

        const data = $(this).serializeArray();
        data.push({ name: 'action', value: 'va_save_profile' });

        api(data, function (res) {
            toast(res.message || 'Profile saved.', res.status !== 'success');
            if (res.status === 'success') { loadProfile(); }
        });
    });

    /* --------------------------- search + nav -------------------------- */

    $('#globalSearch').on('input keyup', function () {
        $('#searchClear').toggleClass('d-none', $(this).val() === '');
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { SECTIONS[currentSection].load(); }, 300);
    });

    $('#searchClear').on('click', function () {
        $('#globalSearch').val('').focus();
        $(this).addClass('d-none');
        SECTIONS[currentSection].load();
    });

    $('#globalFilter').on('change', function () { SECTIONS[currentSection].load(); });

    $('.nav-item').on('click', function () { switchSection($(this).data('section')); });

    $('#signOutBtn').on('click', function () {
        api({ action: 'logout' }, function () { window.location.href = 'login.php'; });
    });

    function closeSidebar() {
        $('#sidebar').removeClass('open');
        $('#sidebarScrim').removeClass('show');
    }
    $('#menuToggle').on('click', function () {
        $('#sidebar').toggleClass('open');
        $('#sidebarScrim').toggleClass('show');
    });
    $('#sidebarScrim').on('click', closeSidebar);

    // Categories first, then open the overview.
    api({ action: 'public_categories' }, function (res) {
        jobCategories = res.data || [];
        switchSection('home');
    });

});
