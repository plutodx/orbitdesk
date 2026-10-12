/* =====================================================================
   ORBITDESK - assets/js/client.js
   The client side of the portal. A client only ever sees their own jobs
   and their own applicants, and the server checks that on every request.
   ===================================================================== */

$(function () {

    'use strict';

    const ENDPOINT = 'db/request.php';
    const USER     = window.ORBIT_USER || {};
    const VERIFIED = USER.status === 'verified';

    let currentSection = 'home';
    let searchTimer    = null;
    let pendingDelete  = null;

    /* ----------------------------- helpers ----------------------------- */

    function esc(value) {
        if (value === null || value === undefined) { return ''; }
        return String(value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function money(value) { return '$' + parseFloat(value || 0).toFixed(2); }

    function initials(name) {
        const parts = String(name || '?').trim().split(/\s+/);
        return ((parts[0] ? parts[0][0] : '?') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
    }

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

    function emptyState(title, text) {
        return '<div class="empty-state"><div class="empty-ring">&#9675;</div>' +
               '<p class="empty-title">' + esc(title) + '</p>' +
               '<p class="empty-text">' + esc(text) + '</p></div>';
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
            sub:   'How your postings are doing.',
            load:  loadHome
        },
        jobs: {
            title:  'My job postings',
            sub:    'Everything you have posted, and who applied.',
            search: true,
            action: '+ Post a job',
            load:   loadJobs
        },
        profile: {
            title: 'Company profile',
            sub:   'This is what virtual assistants see next to your postings.',
            load:  loadProfile
        }
    };

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
        $('#searchWrap').toggleClass('d-none', !config.search);
        $('#primaryAction').toggleClass('d-none', !config.action);
        if (config.action) { $('#primaryAction').text(config.action); }

        closeSidebar();
        config.load();
    }

    /* ---------------------------- overview ---------------------------- */

    function loadHome() {
        api({ action: 'client_stats' }, function (res) {
            if (res.status !== 'success') { return; }
            const d = res.data;

            const cards = [
                { label: 'Live postings',   value: d.jobs_open,        tone: 'violet', foot: '<strong>' + d.jobs_total + '</strong> posted in total' },
                { label: 'Total applicants', value: d.applicants_total, tone: 'ink',    foot: '<strong>' + d.applicants_new + '</strong> still unreviewed' },
                { label: 'Hired',            value: d.hires,            tone: 'jade',   foot: 'Applicants you marked as hired' },
                { label: 'Account status',   value: VERIFIED ? 'Live' : 'Review', tone: VERIFIED ? 'jade' : 'amber',
                  foot: VERIFIED ? 'Your profile is verified' : 'Waiting on an admin' }
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

        // Pull the newest applicants across all of this client's jobs.
        api({ action: 'client_jobs' }, function (res) {
            if (res.status !== 'success') { return; }

            const jobs = (res.data || []).filter(function (j) { return parseInt(j.applicant_count, 10) > 0; });

            if (!jobs.length) {
                $('#recentApplicants').html(emptyState('No applicants yet', 'Once someone applies, they will show up here.'));
                return;
            }

            let done = 0;
            let rows = [];

            jobs.slice(0, 5).forEach(function (job) {
                api({ action: 'client_applicants', id: job.id }, function (r) {
                    (r.data || []).forEach(function (app) { rows.push(app); });
                    done++;

                    if (done === Math.min(jobs.length, 5)) {
                        rows.sort(function (a, b) { return String(b.applied_at).localeCompare(String(a.applied_at)); });

                        let html = '';
                        rows.slice(0, 6).forEach(function (app) {
                            html += '<div class="activity-row">' +
                                      '<span class="person-avatar">' + esc(initials(app.va_name)) + '</span>' +
                                      '<span class="activity-text"><strong>' + esc(app.va_name) + '</strong> applied to ' +
                                        esc(app.job_title) + '</span>' +
                                      pill(app.status) +
                                      '<span class="activity-meta">' + niceDate(app.applied_at) + '</span>' +
                                    '</div>';
                        });
                        $('#recentApplicants').html(html || emptyState('No applicants yet', 'Once someone applies, they will show up here.'));
                    }
                });
            });
        });
    }

    /* ----------------------------- my jobs ---------------------------- */

    function loadJobs() {
        api({ action: 'client_jobs', keyword: $('#globalSearch').val() }, function (res) {
            if (res.status !== 'success') { return; }

            const rows = res.data || [];
            if (!rows.length) {
                $('#jobBoard').html(emptyState('No postings yet',
                    VERIFIED ? 'Use the Post a job button to create your first one.'
                             : 'Posting unlocks once an admin verifies your account.'));
                return;
            }

            let html = '';
            rows.forEach(function (job) {
                const rateLabel = job.rate_type === 'monthly' ? '/mo' : '/hr';

                html += '<article class="job-card">' +
                          '<div class="job-card-top">' +
                            '<span class="job-cat">' + esc(job.category) + '</span>' +
                            pill(job.status) +
                          '</div>' +
                          '<h3 class="job-title">' + esc(job.title) + '</h3>' +
                          '<p class="job-company">Posted ' + niceDate(job.created_at) + '</p>' +
                          '<p class="job-desc">' + esc(job.description) + '</p>' +
                          '<div class="job-meta">' +
                            '<span class="job-rate">' + money(job.rate_offered) + '<span>' + rateLabel + '</span></span>' +
                            '<button class="job-applicants" data-applicants="' + esc(job.id) + '" data-title="' + esc(job.title) + '">' +
                              esc(job.applicant_count) + ' applicant' + (parseInt(job.applicant_count, 10) === 1 ? '' : 's') +
                            '</button>' +
                            '<span class="job-actions">' +
                              '<button class="act" data-edit-job="' + esc(job.id) + '" title="Edit">&#9998;</button>' +
                              '<button class="act act-danger" data-del-job="' + esc(job.id) + '" data-label="' + esc(job.title) + '" title="Delete">&#128465;</button>' +
                            '</span>' +
                          '</div>' +
                        '</article>';
            });

            $('#jobBoard').html(html);
        });
    }

    function openJobModal(id) {
        if (!VERIFIED) {
            toast('Posting is locked until an admin verifies your account.', true);
            return;
        }

        $('#jobForm')[0].reset();
        $('#jobId').val('');

        api({ action: 'public_categories' }, function (res) {
            let list = '';
            (res.data || []).forEach(function (row) {
                list += '<option value="' + esc(row.category) + '"></option>';
            });
            $('#categoryOptions').html(list);
        });

        if (id) {
            $('#jobModalTitle').text('Edit job posting');
            api({ action: 'client_jobs' }, function (res) {
                const job = (res.data || []).filter(function (j) { return String(j.id) === String(id); })[0];
                if (!job) { return; }

                $('#jobId').val(job.id);
                $('#jobTitle').val(job.title);
                $('#jobCategory').val(job.category);
                $('#jobDescription').val(job.description);
                $('#jobRate').val(job.rate_offered);
                $('#jobRateType').val(job.rate_type);
                $('#jobSlots').val(job.slots);
                $('#jobStatus').val(job.status);
            });
        } else {
            $('#jobModalTitle').text('Post a job');
        }

        new bootstrap.Modal('#jobModal').show();
    }

    $('#jobForm').on('submit', function (e) {
        e.preventDefault();

        const data = $(this).serializeArray();
        data.push({ name: 'action', value: 'client_save_job' });

        const $submit = $(this).find('button[type="submit"]');
        $submit.prop('disabled', true).text('Saving...');

        api(data, function (res) {
            $submit.prop('disabled', false).text('Save job');

            if (res.status !== 'success') {
                toast(res.message || 'Could not save the posting.', true);
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById('jobModal')).hide();
            toast('Job posting saved.');
            loadJobs();
        });
    });

    /* --------------------------- applicants --------------------------- */

    $(document).on('click', '[data-applicants]', function () {
        const jobId = $(this).data('applicants');
        $('#applicantsSub').text($(this).data('title'));

        api({ action: 'client_applicants', id: jobId }, function (res) {
            const rows = res.data || [];

            if (!rows.length) {
                $('#applicantsBody').html(emptyState('No applicants yet', 'Nobody has applied to this posting.'));
            } else {
                let html = '';
                rows.forEach(function (app) {
                    // Contact details stay hidden until the client shows real
                    // interest. Shortlisting or hiring unlocks them.
                    const unlocked = app.status === 'shortlisted' || app.status === 'hired';

                    const contact = unlocked
                        ? '<p class="contact-line"><strong>Contact:</strong> ' +
                            '<a href="mailto:' + esc(app.va_email) + '">' + esc(app.va_email) + '</a>' +
                            ' &middot; ' + esc(app.va_phone) + '</p>'
                        : '<p class="contact-locked">Contact details unlock when you shortlist them</p>';

                    html += '<div class="applicant-row">' +
                              '<span class="person-avatar">' + esc(initials(app.va_name)) + '</span>' +
                              '<div class="applicant-main">' +
                                '<p class="applicant-name">' + esc(app.va_name) + ' &middot; asking ' + money(app.expected_rate) + '</p>' +
                                '<p class="applicant-note">' + esc(app.cover_note) + '</p>' +
                                '<p class="applicant-note"><strong>Skills:</strong> ' + esc(app.skills) + '</p>' +
                                contact +
                              '</div>' +
                              '<select class="status-select" data-app-status="' + esc(app.id) + '">' +
                                ['pending', 'shortlisted', 'hired', 'rejected'].map(function (o) {
                                    return '<option value="' + o + '"' + (app.status === o ? ' selected' : '') + '>' +
                                           o.charAt(0).toUpperCase() + o.slice(1) + '</option>';
                                }).join('') +
                              '</select>' +
                            '</div>';
                });
                $('#applicantsBody').html(html);
            }

            new bootstrap.Modal('#applicantsModal').show();
        });
    });

    $(document).on('change', '[data-app-status]', function () {
        const $select = $(this);
        api({ action: 'client_set_status', id: $select.data('app-status'), status: $select.val() }, function (res) {
            toast(res.message, res.status !== 'success');
            if (res.status === 'success' && currentSection === 'jobs') { loadJobs(); }
        });
    });

    /* ----------------------------- profile ---------------------------- */

    function loadProfile() {
        api({ action: 'client_profile' }, function (res) {
            const c = res.data;
            if (!c) { return; }

            $('#pfCompany').val(c.company_name);
            $('#pfContact').val(c.contact_person);
            $('#pfEmail').val(c.email);
            $('#pfPhone').val(c.phone);
            $('#pfLocation').val(c.location);
            $('#pfPassword').val('');

            $('#profileStatus').html('Profile status: ' + pill(c.status) +
                (c.verified_by_name ? ' <span class="cell-sub" style="display:inline">reviewed by ' + esc(c.verified_by_name) + '</span>' : ''));
        });
    }

    $('#profileForm').on('submit', function (e) {
        e.preventDefault();

        const data = $(this).serializeArray();
        data.push({ name: 'action', value: 'client_save_profile' });

        api(data, function (res) {
            toast(res.message || 'Profile saved.', res.status !== 'success');
            if (res.status === 'success') { loadProfile(); }
        });
    });

    /* ------------------------------ delete ---------------------------- */

    $(document).on('click', '[data-edit-job]', function () { openJobModal($(this).data('edit-job')); });

    $(document).on('click', '[data-del-job]', function () {
        pendingDelete = $(this).data('del-job');
        $('#confirmText').text('"' + $(this).data('label') + '" and every application on it will be removed.');
        new bootstrap.Modal('#confirmModal').show();
    });

    $('#confirmYes').on('click', function () {
        if (!pendingDelete) { return; }

        api({ action: 'client_delete_job', id: pendingDelete }, function (res) {
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            toast(res.message, res.status !== 'success');
            if (res.status === 'success') { loadJobs(); }
            pendingDelete = null;
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

    $('.nav-item').on('click', function () { switchSection($(this).data('section')); });
    $('#primaryAction').on('click', function () { openJobModal(); });

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

    switchSection('home');

});
