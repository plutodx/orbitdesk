/* =====================================================================
   ORBITDESK - assets/js/script.js
   Every screen in this console is drawn from JSON returned by
   db/request.php. Nothing in here triggers a page reload.
   ===================================================================== */

$(function () {

    'use strict';

    const ENDPOINT = 'db/request.php';
    const ADMIN    = window.ORBIT_ADMIN || {};

    let currentSection = 'dashboard';
    let searchTimer    = null;
    let pendingDelete  = null;   // { action, id, label }
    let jobCategories  = [];

    /* -----------------------------------------------------------------
       HELPERS
       ----------------------------------------------------------------- */

    /** Escapes anything that came out of the database before it hits the DOM. */
    function esc(value) {
        if (value === null || value === undefined) { return ''; }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function money(value) {
        return '$' + parseFloat(value || 0).toFixed(2);
    }

    function initials(name) {
        const parts = String(name || '?').trim().split(/\s+/);
        const first = parts[0] ? parts[0][0] : '?';
        const last  = parts.length > 1 ? parts[parts.length - 1][0] : '';
        return (first + last).toUpperCase();
    }

    /** "2026-06-14 15:33:00" -> "Jun 14, 2026" */
    function niceDate(value) {
        if (!value) { return '--'; }
        const d = new Date(String(value).replace(' ', 'T'));
        if (isNaN(d)) { return esc(value); }
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
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
        }, 3200);
    }

    function emptyState(title, text, colspan) {
        const block = '<div class="empty-state">' +
                        '<div class="empty-ring">&#9675;</div>' +
                        '<p class="empty-title">' + esc(title) + '</p>' +
                        '<p class="empty-text">' + esc(text) + '</p>' +
                      '</div>';
        return colspan ? '<tr><td colspan="' + colspan + '">' + block + '</td></tr>' : block;
    }

    /** One wrapper around $.ajax so session handling lives in a single place. */
    function api(data, onDone) {
        return $.ajax({
            url: ENDPOINT,
            type: 'POST',
            dataType: 'json',
            data: data,

            success: function (res) {
                if (res.status === 'unauthorized') {
                    toast(res.message || 'Session expired.', true);
                    setTimeout(function () { window.location.href = 'admin-login.php'; }, 1200);
                    return;
                }
                onDone(res);
            },

            error: function (xhr) {
                console.error('Request failed:', xhr.responseText);
                toast('The server did not respond correctly. Check the PHP error log.', true);
            }
        });
    }

    /* -----------------------------------------------------------------
       SECTION CONFIGURATION
       ----------------------------------------------------------------- */

    const SECTIONS = {
        dashboard: {
            title: 'Dashboard',
            sub:   'Everything moving through the portal right now.',
            load:  loadDashboard
        },
        jobs: {
            title:  'Job postings',
            sub:    'Roles clients have opened to the VA pool.',
            search: 'Search titles, skills, companies',
            filter: 'categories',
            action: { label: '+ Post a job', open: openJobModal },
            load:   loadJobs
        },
        applications: {
            title:  'Applications',
            sub:    'Every VA application and where it stands.',
            search: 'Search by VA, job, or client',
            filter: [
                { value: '',            label: 'All statuses' },
                { value: 'pending',     label: 'Pending' },
                { value: 'shortlisted', label: 'Shortlisted' },
                { value: 'hired',       label: 'Hired' },
                { value: 'rejected',    label: 'Rejected' }
            ],
            action: { label: '+ Record application', open: openApplicationModal },
            load:   loadApplications
        },
        vas: {
            title:  'Virtual assistants',
            sub:    'The talent side of the portal.',
            search: 'Search names, emails, skills',
            filter: [
                { value: '',         label: 'All profiles' },
                { value: 'verified', label: 'Verified' },
                { value: 'pending',  label: 'Pending' },
                { value: 'rejected', label: 'Rejected' }
            ],
            action: { label: '+ Add a VA', open: openVaModal },
            load:   loadVas
        },
        clients: {
            title:  'Clients',
            sub:    'Companies posting work on the portal.',
            search: 'Search companies, contacts, locations',
            filter: [
                { value: '',         label: 'All profiles' },
                { value: 'verified', label: 'Verified' },
                { value: 'pending',  label: 'Pending' },
                { value: 'rejected', label: 'Rejected' }
            ],
            action: { label: '+ Add a client', open: openClientModal },
            load:   loadClients
        },
        moderation: {
            title: 'Moderation',
            sub:   'Screen and verify profiles before they go live.',
            load:  loadQueue
        },
        admins: {
            title:  'Admin team',
            sub:    'Accounts that can moderate the portal.',
            search: 'Search admins',
            filter: [
                { value: '',         label: 'All accounts' },
                { value: 'active',   label: 'Active' },
                { value: 'inactive', label: 'Inactive' }
            ],
            action: { label: '+ Add an admin', open: openAdminModal },
            load:   loadAdmins
        }
    };

    function switchSection(name) {
        if (!SECTIONS[name]) { return; }

        currentSection = name;
        const config = SECTIONS[name];

        $('.nav-item').removeClass('active').filter('[data-section="' + name + '"]').addClass('active');
        $('.section').removeClass('active');
        $('#section-' + name).addClass('active');

        $('#pageTitle').text(config.title);
        $('#pageSub').text(config.sub);

        // Search box
        $('#globalSearch').val('');
        $('#searchClear').addClass('d-none');
        if (config.search) {
            $('#searchWrap').removeClass('d-none');
            $('#globalSearch').attr('placeholder', config.search);
        } else {
            $('#searchWrap').addClass('d-none');
        }

        // Filter dropdown
        const $filter = $('#globalFilter');
        if (config.filter === 'categories') {
            $filter.removeClass('d-none').html(buildCategoryOptions());
        } else if (Array.isArray(config.filter)) {
            let html = '';
            config.filter.forEach(function (option) {
                html += '<option value="' + esc(option.value) + '">' + esc(option.label) + '</option>';
            });
            $filter.removeClass('d-none').html(html);
        } else {
            $filter.addClass('d-none').empty();
        }

        // Primary button
        const $action = $('#primaryAction');
        if (config.action) {
            $action.removeClass('d-none').text(config.action.label);
        } else {
            $action.addClass('d-none');
        }

        closeSidebar();
        config.load();
    }

    function buildCategoryOptions() {
        let html = '<option value="">All categories</option>';
        jobCategories.forEach(function (row) {
            html += '<option value="' + esc(row.category) + '">' + esc(row.category) + '</option>';
        });
        return html;
    }

    /* -----------------------------------------------------------------
       DASHBOARD
       ----------------------------------------------------------------- */

    function loadDashboard() {
        api({ action: 'stats' }, function (res) {
            if (res.status !== 'success') { return; }
            const d = res.data;

            // Stat cards
            const cards = [
                {
                    label: 'Open roles', value: d.jobs_open, tone: 'violet',
                    foot: '<strong>' + d.jobs_total + '</strong> job postings in total'
                },
                {
                    label: 'Applications', value: d.apps_total, tone: 'ink',
                    foot: '<strong>' + d.apps_hired + '</strong> ended in a hire'
                },
                {
                    label: 'Verified talent', value: d.vas_total, tone: 'jade',
                    foot: '<strong>' + d.clients_total + '</strong> client accounts registered'
                },
                {
                    label: 'Waiting on review', value: d.pending_total, tone: 'amber',
                    foot: '<strong>' + d.pending_vas + '</strong> VAs and <strong>' + d.pending_clients + '</strong> clients'
                }
            ];

            let cardHtml = '';
            cards.forEach(function (card) {
                cardHtml += '<article class="stat-card accent-' + card.tone + '">' +
                                '<p class="stat-label">' + esc(card.label) + '</p>' +
                                '<p class="stat-value">' + esc(card.value) + '</p>' +
                                '<p class="stat-foot">' + card.foot + '</p>' +
                            '</article>';
            });
            $('#statGrid').html(cardHtml);

            // Moderation badge in the sidebar
            const $badge = $('#queueBadge');
            if (parseInt(d.pending_total, 10) > 0) {
                $badge.removeClass('d-none').text(d.pending_total);
            } else {
                $badge.addClass('d-none');
            }

            // Pipeline meter
            const order  = ['pending', 'shortlisted', 'hired', 'rejected'];
            const counts = { pending: 0, shortlisted: 0, hired: 0, rejected: 0 };
            (d.app_breakdown || []).forEach(function (row) { counts[row.status] = parseInt(row.total, 10); });

            const total = order.reduce(function (sum, key) { return sum + counts[key]; }, 0) || 1;

            let meter  = '';
            let legend = '';
            order.forEach(function (key) {
                const pct = (counts[key] / total) * 100;
                meter  += '<div class="meter-seg ' + key + '" style="width:' + pct.toFixed(1) + '%"></div>';
                legend += '<div class="legend-item">' +
                            '<span class="legend-swatch" style="background:' + segColor(key) + '"></span>' +
                            '<span class="legend-label">' + key + '</span>' +
                            '<span class="legend-count">' + counts[key] + '</span>' +
                          '</div>';
            });
            $('#pipelineMeter').html(meter);
            $('#pipelineLegend').html(legend);

            // Category ranking
            const cats = d.top_categories || [];
            const max  = cats.length ? Math.max.apply(null, cats.map(function (c) { return parseInt(c.applicants, 10); })) || 1 : 1;

            let catHtml = '';
            cats.forEach(function (cat) {
                const pct = (parseInt(cat.applicants, 10) / max) * 100;
                catHtml += '<div class="cat-row">' +
                             '<div class="cat-top">' +
                               '<span class="cat-name">' + esc(cat.category) + '</span>' +
                               '<span class="cat-count">' + esc(cat.applicants) + ' applied</span>' +
                             '</div>' +
                             '<div class="cat-track"><div class="cat-fill" style="width:' + pct.toFixed(1) + '%"></div></div>' +
                           '</div>';
            });
            $('#categoryList').html(catHtml || emptyState('No categories yet', 'Post a job to get started.'));

            // Activity feed
            let feed = '';
            (d.recent_activity || []).forEach(function (row) {
                feed += '<div class="activity-row">' +
                          '<span class="activity-dot" style="background:' + segColor(row.status) + '"></span>' +
                          '<span class="activity-text"><strong>' + esc(row.va_name) + '</strong> applied to ' +
                            esc(row.job_title) + ' at ' + esc(row.company_name) + '</span>' +
                          '<span class="activity-meta">' + niceDate(row.applied_at) + '</span>' +
                        '</div>';
            });
            $('#activityFeed').html(feed || emptyState('Nothing yet', 'Applications will show up here.'));
        });
    }

    function segColor(status) {
        const map = {
            pending: '#FFB443',
            shortlisted: '#6C4BF4',
            hired: '#12B886',
            rejected: '#C9C4DE'
        };
        return map[status] || '#6C4BF4';
    }

    /* -----------------------------------------------------------------
       JOBS
       ----------------------------------------------------------------- */

    function loadJobs() {
        api({
            action:  'fetch_jobs',
            keyword: $('#globalSearch').val(),
            filter:  $('#globalFilter').val() || ''
        }, function (res) {
            if (res.status !== 'success') { return; }

            const rows = res.data || [];
            if (!rows.length) {
                $('#jobBoard').html(emptyState('No job postings match', 'Try a different keyword or clear the category filter.'));
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
                          '<p class="job-company"><strong>' + esc(job.company_name) + '</strong> &middot; ' + esc(job.location) + '</p>' +
                          '<p class="job-desc">' + esc(job.description) + '</p>' +
                          '<div class="job-meta">' +
                            '<span class="job-rate">' + money(job.rate_offered) + '<span>' + rateLabel + '</span></span>' +
                            '<button class="job-applicants" data-applicants="' + esc(job.id) + '" data-title="' + esc(job.title) + '" data-company="' + esc(job.company_name) + '">' +
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

    function refreshCategories(callback) {
        api({ action: 'job_categories' }, function (res) {
            if (res.status === 'success') {
                jobCategories = res.data || [];
                let list = '';
                jobCategories.forEach(function (row) {
                    list += '<option value="' + esc(row.category) + '"></option>';
                });
                $('#categoryOptions').html(list);
            }
            if (callback) { callback(); }
        });
    }

    function openJobModal(id) {
        const $form = $('#jobForm')[0];
        $form.reset();
        $('#jobId').val('');

        api({ action: 'client_options' }, function (res) {
            let options = '<option value="">Choose a client</option>';
            (res.data || []).forEach(function (client) {
                options += '<option value="' + esc(client.id) + '">' + esc(client.company_name) + '</option>';
            });
            $('#jobClient').html(options);

            if (id) {
                $('#jobModalTitle').text('Edit job posting');
                api({ action: 'get_job', id: id }, function (jobRes) {
                    const job = jobRes.data;
                    if (!job) { return; }
                    $('#jobId').val(job.id);
                    $('#jobTitle').val(job.title);
                    $('#jobClient').val(job.client_id);
                    $('#jobCategory').val(job.category);
                    $('#jobRate').val(job.rate_offered);
                    $('#jobRateType').val(job.rate_type);
                    $('#jobDescription').val(job.description);
                    $('#jobSlots').val(job.slots);
                    $('#jobStatus').val(job.status);
                });
            } else {
                $('#jobModalTitle').text('Post a job');
            }

            new bootstrap.Modal('#jobModal').show();
        });
    }

    /* -----------------------------------------------------------------
       APPLICATIONS
       ----------------------------------------------------------------- */

    function loadApplications() {
        api({
            action:  'fetch_applications',
            keyword: $('#globalSearch').val(),
            filter:  $('#globalFilter').val() || ''
        }, function (res) {
            if (res.status !== 'success') { return; }

            const rows = res.data || [];
            if (!rows.length) {
                $('#applicationRows').html(emptyState('No applications match', 'Try another keyword or reset the status filter.', 7));
                return;
            }

            let html = '';
            rows.forEach(function (app) {
                html += '<tr>' +
                          '<td>' +
                            '<div class="person">' +
                              '<span class="person-avatar">' + esc(initials(app.va_name)) + '</span>' +
                              '<span><span class="cell-primary">' + esc(app.va_name) + '</span>' +
                              '<span class="cell-sub">' + esc(app.va_email) + '</span></span>' +
                            '</div>' +
                          '</td>' +
                          '<td><span class="cell-primary">' + esc(app.job_title) + '</span>' +
                              '<span class="cell-sub">' + esc(app.category) + '</span></td>' +
                          '<td>' + esc(app.company_name) + '</td>' +
                          '<td class="cell-mono">' + money(app.expected_rate) + '</td>' +
                          '<td>' + pill(app.status) + '</td>' +
                          '<td class="cell-mono">' + niceDate(app.applied_at) + '</td>' +
                          '<td class="text-end">' +
                            '<span class="act-group">' +
                              '<button class="act" data-edit-app="' + esc(app.id) + '" title="Edit">&#9998;</button>' +
                              '<button class="act act-danger" data-del-app="' + esc(app.id) + '" data-label="' + esc(app.va_name) + '" title="Delete">&#128465;</button>' +
                            '</span>' +
                          '</td>' +
                        '</tr>';
            });

            $('#applicationRows').html(html);
        });
    }

    function openApplicationModal(id) {
        const $form = $('#applicationForm')[0];
        $form.reset();
        $('#applicationId').val('');

        api({ action: 'job_options' }, function (jobRes) {
            let jobOptions = '<option value="">Choose a job posting</option>';
            (jobRes.data || []).forEach(function (job) {
                jobOptions += '<option value="' + esc(job.id) + '">' + esc(job.title) + ' (' + esc(job.company_name) + ')</option>';
            });
            $('#appJob').html(jobOptions);

            api({ action: 'va_options' }, function (vaRes) {
                let vaOptions = '<option value="">Choose a virtual assistant</option>';
                (vaRes.data || []).forEach(function (va) {
                    vaOptions += '<option value="' + esc(va.id) + '" data-rate="' + esc(va.hourly_rate) + '">' +
                                 esc(va.full_name) + '</option>';
                });
                $('#appVa').html(vaOptions);

                if (id) {
                    $('#applicationModalTitle').text('Edit application');
                    api({ action: 'get_application', id: id }, function (appRes) {
                        const app = appRes.data;
                        if (!app) { return; }

                        // The job may be closed by now, so make sure it still appears.
                        if (!$('#appJob option[value="' + app.job_id + '"]').length) {
                            $('#appJob').append('<option value="' + esc(app.job_id) + '">' + esc(app.job_title) + '</option>');
                        }
                        if (!$('#appVa option[value="' + app.va_id + '"]').length) {
                            $('#appVa').append('<option value="' + esc(app.va_id) + '">' + esc(app.va_name) + '</option>');
                        }

                        $('#applicationId').val(app.id);
                        $('#appJob').val(app.job_id);
                        $('#appVa').val(app.va_id);
                        $('#appNote').val(app.cover_note);
                        $('#appRate').val(app.expected_rate);
                        $('#appStatus').val(app.status);
                    });
                } else {
                    $('#applicationModalTitle').text('Record an application');
                }

                new bootstrap.Modal('#applicationModal').show();
            });
        });
    }

    // Picking a VA pre-fills the expected rate with their listed rate.
    $('#appVa').on('change', function () {
        const rate = $(this).find(':selected').data('rate');
        if (rate && $('#appRate').val() === '') {
            $('#appRate').val(rate);
        }
    });

    /* -----------------------------------------------------------------
       VIRTUAL ASSISTANTS
       ----------------------------------------------------------------- */

    function loadVas() {
        api({
            action:  'fetch_vas',
            keyword: $('#globalSearch').val(),
            filter:  $('#globalFilter').val() || ''
        }, function (res) {
            if (res.status !== 'success') { return; }

            const rows = res.data || [];
            if (!rows.length) {
                $('#vaRows').html(emptyState('No virtual assistants match', 'Try another keyword or reset the filter.', 7));
                return;
            }

            let html = '';
            rows.forEach(function (va) {
                const tone = va.status === 'verified' ? 'tone-jade' : (va.status === 'pending' ? 'tone-amber' : '');

                html += '<tr>' +
                          '<td>' +
                            '<div class="person">' +
                              '<span class="person-avatar ' + tone + '">' + esc(initials(va.full_name)) + '</span>' +
                              '<span><span class="cell-primary">' + esc(va.full_name) + '</span>' +
                              '<span class="cell-sub">' + esc(va.email) + '</span></span>' +
                            '</div>' +
                          '</td>' +
                          '<td><span class="cell-sub" style="margin:0">' + esc(va.skills) + '</span></td>' +
                          '<td class="cell-mono">' + esc(va.experience_years) + ' yr</td>' +
                          '<td class="cell-mono">' + money(va.hourly_rate) + '</td>' +
                          '<td>' + esc(va.availability) + '</td>' +
                          '<td>' + pill(va.status) +
                            (va.verified_by_name ? '<span class="cell-sub">by ' + esc(va.verified_by_name) + '</span>' : '') +
                          '</td>' +
                          '<td class="text-end">' +
                            '<span class="act-group">' +
                              '<button class="act" data-edit-va="' + esc(va.id) + '" title="Edit">&#9998;</button>' +
                              '<button class="act act-danger" data-del-va="' + esc(va.id) + '" data-label="' + esc(va.full_name) + '" title="Delete">&#128465;</button>' +
                            '</span>' +
                          '</td>' +
                        '</tr>';
            });

            $('#vaRows').html(html);
        });
    }

    function openVaModal(id) {
        const $form = $('#vaForm')[0];
        $form.reset();
        $('#vaId').val('');

        if (id) {
            $('#vaModalTitle').text('Edit virtual assistant');
            $('#vaFormNote').text('Editing a profile does not change its verification status.');

            api({ action: 'get_va', id: id }, function (res) {
                const va = res.data;
                if (!va) { return; }
                $('#vaId').val(va.id);
                $('#vaName').val(va.full_name);
                $('#vaEmail').val(va.email);
                $('#vaPhone').val(va.phone);
                $('#vaSkills').val(va.skills);
                $('#vaYears').val(va.experience_years);
                $('#vaRate').val(va.hourly_rate);
                $('#vaAvailability').val(va.availability);
            });
        } else {
            $('#vaModalTitle').text('Add a virtual assistant');
            $('#vaFormNote').text('New profiles land in the moderation queue as pending.');
        }

        new bootstrap.Modal('#vaModal').show();
    }

    /* -----------------------------------------------------------------
       CLIENTS
       ----------------------------------------------------------------- */

    function loadClients() {
        api({
            action:  'fetch_clients',
            keyword: $('#globalSearch').val(),
            filter:  $('#globalFilter').val() || ''
        }, function (res) {
            if (res.status !== 'success') { return; }

            const rows = res.data || [];
            if (!rows.length) {
                $('#clientRows').html(emptyState('No clients match', 'Try another keyword or reset the filter.', 7));
                return;
            }

            let html = '';
            rows.forEach(function (client) {
                html += '<tr>' +
                          '<td>' +
                            '<div class="person">' +
                              '<span class="person-avatar">' + esc(initials(client.company_name)) + '</span>' +
                              '<span><span class="cell-primary">' + esc(client.company_name) + '</span>' +
                              '<span class="cell-sub">' + esc(client.email) + '</span></span>' +
                            '</div>' +
                          '</td>' +
                          '<td>' + esc(client.contact_person) +
                              '<span class="cell-sub">' + esc(client.phone) + '</span></td>' +
                          '<td>' + esc(client.location) + '</td>' +
                          '<td class="cell-mono">' + esc(client.jobs_posted) + '</td>' +
                          '<td>' + pill(client.status) + '</td>' +
                          '<td>' + (client.verified_by_name ? esc(client.verified_by_name) : '<span class="cell-sub" style="margin:0">Not reviewed</span>') + '</td>' +
                          '<td class="text-end">' +
                            '<span class="act-group">' +
                              '<button class="act" data-edit-client="' + esc(client.id) + '" title="Edit">&#9998;</button>' +
                              '<button class="act act-danger" data-del-client="' + esc(client.id) + '" data-label="' + esc(client.company_name) + '" title="Delete">&#128465;</button>' +
                            '</span>' +
                          '</td>' +
                        '</tr>';
            });

            $('#clientRows').html(html);
        });
    }

    function openClientModal(id) {
        const $form = $('#clientForm')[0];
        $form.reset();
        $('#clientId').val('');

        if (id) {
            $('#clientModalTitle').text('Edit client');
            $('#clientFormNote').text('Editing a client does not change its verification status.');

            api({ action: 'get_client', id: id }, function (res) {
                const client = res.data;
                if (!client) { return; }
                $('#clientId').val(client.id);
                $('#clientCompany').val(client.company_name);
                $('#clientContact').val(client.contact_person);
                $('#clientEmail').val(client.email);
                $('#clientPhone').val(client.phone);
                $('#clientLocation').val(client.location);
            });
        } else {
            $('#clientModalTitle').text('Add a client');
            $('#clientFormNote').text('New client accounts land in the moderation queue as pending.');
        }

        new bootstrap.Modal('#clientModal').show();
    }

    /* -----------------------------------------------------------------
       ADMINS
       ----------------------------------------------------------------- */

    function loadAdmins() {
        api({
            action:  'fetch_admins',
            keyword: $('#globalSearch').val(),
            filter:  $('#globalFilter').val() || ''
        }, function (res) {
            if (res.status !== 'success') { return; }

            const rows = res.data || [];
            if (!rows.length) {
                $('#adminRows').html(emptyState('No admins match', 'Try another keyword.', 7));
                return;
            }

            let html = '';
            rows.forEach(function (admin) {
                const isMe    = parseInt(admin.id, 10) === parseInt(ADMIN.id, 10);
                const actions = '<span class="act-group">' +
                        '<button class="act" data-edit-admin="' + esc(admin.id) + '" title="Edit">&#9998;</button>' +
                        (isMe ? '' : '<button class="act act-danger" data-del-admin="' + esc(admin.id) + '" data-label="' + esc(admin.full_name) + '" title="Delete">&#128465;</button>') +
                      '</span>';

                html += '<tr>' +
                          '<td>' +
                            '<div class="person">' +
                              '<span class="person-avatar">' + esc(initials(admin.full_name)) + '</span>' +
                              '<span><span class="cell-primary">' + esc(admin.full_name) + (isMe ? ' (you)' : '') + '</span>' +
                              '<span class="cell-sub">Joined ' + niceDate(admin.created_at) + '</span></span>' +
                            '</div>' +
                          '</td>' +
                          '<td>' + esc(admin.email) + '</td>' +
                          '<td class="cell-mono">' + esc(admin.clients_reviewed) + '</td>' +
                          '<td class="cell-mono">' + esc(admin.vas_reviewed) + '</td>' +
                          '<td>' + pill(admin.status) + '</td>' +
                          '<td class="text-end">' + actions + '</td>' +
                        '</tr>';
            });

            $('#adminRows').html(html);
        });
    }

    function openAdminModal(id) {
        const $form = $('#adminForm')[0];
        $form.reset();
        $('#adminId').val('');

        if (id) {
            $('#adminModalTitle').text('Edit admin');
            $('#adminPasswordHint').text('Leave blank to keep the current password.');

            api({ action: 'get_admin', id: id }, function (res) {
                const admin = res.data;
                if (!admin) { return; }
                $('#adminId').val(admin.id);
                $('#adminName').val(admin.full_name);
                $('#adminEmail').val(admin.email);
                $('#adminStatus').val(admin.status);
            });
        } else {
            $('#adminModalTitle').text('Add an admin');
            $('#adminPasswordHint').text('At least 6 characters. The admin can change it later.');
        }

        new bootstrap.Modal('#adminModal').show();
    }

    /* -----------------------------------------------------------------
       MODERATION QUEUE
       ----------------------------------------------------------------- */

    function loadQueue() {
        const status = $('#queueTabs .seg-btn.active').data('status');

        api({ action: 'fetch_queue', status: status === undefined ? 'pending' : status }, function (res) {
            if (res.status !== 'success') { return; }

            const rows = res.data || [];
            if (!rows.length) {
                $('#queueGrid').html(emptyState('Queue is clear', 'Nothing is waiting for a decision right now.'));
                return;
            }

            let html = '';
            rows.forEach(function (row) {
                const typeLabel = row.profile_type === 'client' ? 'Client account' : 'Virtual assistant';
                const stateClass = row.status === 'verified' ? 'is-verified' : (row.status === 'rejected' ? 'is-rejected' : '');

                let buttons = '';
                if (row.status !== 'verified') {
                    buttons += '<button class="mod-btn approve" data-mod="verified" data-type="' + esc(row.profile_type) + '" data-id="' + esc(row.id) + '">Verify</button>';
                }
                if (row.status !== 'rejected') {
                    buttons += '<button class="mod-btn reject" data-mod="rejected" data-type="' + esc(row.profile_type) + '" data-id="' + esc(row.id) + '">Reject</button>';
                }
                if (row.status !== 'pending') {
                    buttons += '<button class="mod-btn reset" data-mod="pending" data-type="' + esc(row.profile_type) + '" data-id="' + esc(row.id) + '">Send back to queue</button>';
                }

                html += '<article class="queue-card ' + stateClass + '">' +
                          '<p class="queue-type">' + esc(typeLabel) + ' &middot; joined ' + niceDate(row.created_at) + '</p>' +
                          '<h3 class="queue-name">' + esc(row.name) + '</h3>' +
                          '<p class="queue-sub">' + esc(row.subtitle) + '</p>' +
                          '<p class="queue-mail">' + esc(row.email) + '</p>' +
                          '<div class="queue-actions">' + buttons + '</div>' +
                        '</article>';
            });

            $('#queueGrid').html(html);
        });
    }

    $('#queueTabs').on('click', '.seg-btn', function () {
        $('#queueTabs .seg-btn').removeClass('active');
        $(this).addClass('active');
        loadQueue();
    });

    $(document).on('click', '[data-mod]', function () {
        const $btn = $(this);
        api({
            action:       'moderate',
            profile_type: $btn.data('type'),
            id:           $btn.data('id'),
            decision:     $btn.data('mod')
        }, function (res) {
            toast(res.message, res.status !== 'success');
            if (res.status === 'success') {
                loadQueue();
                refreshBadge();
            }
        });
    });

    function refreshBadge() {
        api({ action: 'stats' }, function (res) {
            if (res.status !== 'success') { return; }
            const pending = parseInt(res.data.pending_total, 10);
            const $badge  = $('#queueBadge');
            if (pending > 0) { $badge.removeClass('d-none').text(pending); }
            else { $badge.addClass('d-none'); }
        });
    }

    /* -----------------------------------------------------------------
       APPLICANTS DRAWER
       ----------------------------------------------------------------- */

    $(document).on('click', '[data-applicants]', function () {
        const jobId = $(this).data('applicants');

        $('#applicantsTitle').text('Applicants');
        $('#applicantsSub').text($(this).data('title') + ' at ' + $(this).data('company'));

        api({ action: 'job_applicants', id: jobId }, function (res) {
            const rows = res.data || [];

            if (!rows.length) {
                $('#applicantsBody').html(emptyState('No applicants yet', 'Nobody has applied to this posting.'));
            } else {
                let html = '';
                rows.forEach(function (app) {
                    html += '<div class="applicant-row">' +
                              '<span class="person-avatar">' + esc(initials(app.va_name)) + '</span>' +
                              '<div class="applicant-main">' +
                                '<p class="applicant-name">' + esc(app.va_name) + ' &middot; ' + money(app.expected_rate) + '</p>' +
                                '<p class="applicant-note">' + esc(app.cover_note) + '</p>' +
                              '</div>' +
                              '<select class="status-select" data-app-status="' + esc(app.id) + '">' +
                                ['pending', 'shortlisted', 'hired', 'rejected'].map(function (option) {
                                    return '<option value="' + option + '"' + (app.status === option ? ' selected' : '') + '>' +
                                           option.charAt(0).toUpperCase() + option.slice(1) + '</option>';
                                }).join('') +
                              '</select>' +
                            '</div>';
                });
                $('#applicantsBody').html(html);
            }

            new bootstrap.Modal('#applicantsModal').show();
        });
    });

    // Changing the dropdown updates the application straight away.
    $(document).on('change', '[data-app-status]', function () {
        const $select = $(this);
        api({
            action: 'set_application_status',
            id:     $select.data('app-status'),
            status: $select.val()
        }, function (res) {
            toast(res.message, res.status !== 'success');
            if (res.status === 'success' && currentSection === 'jobs') { loadJobs(); }
        });
    });

    /* -----------------------------------------------------------------
       FORM SUBMISSION (one handler pattern per form)
       ----------------------------------------------------------------- */

    function submitForm($form, action, modalId, afterSave) {
        const data = $form.serializeArray();
        data.push({ name: 'action', value: action });

        const $submit = $form.find('button[type="submit"]');
        const original = $submit.text();
        $submit.prop('disabled', true).text('Saving...');

        api(data, function (res) {
            $submit.prop('disabled', false).text(original);

            if (res.status !== 'success') {
                toast(res.message || 'Could not save that record.', true);
                return;
            }

            bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
            toast(res.message || 'Saved.');
            afterSave();
        });
    }

    $('#jobForm').on('submit', function (e) {
        e.preventDefault();
        submitForm($(this), 'save_job', 'jobModal', function () {
            refreshCategories(function () {
                if (currentSection === 'jobs') {
                    const keep = $('#globalFilter').val();
                    $('#globalFilter').html(buildCategoryOptions()).val(keep);
                }
                loadJobs();
            });
        });
    });

    $('#clientForm').on('submit', function (e) {
        e.preventDefault();
        submitForm($(this), 'save_client', 'clientModal', function () {
            loadClients();
            refreshBadge();
        });
    });

    $('#vaForm').on('submit', function (e) {
        e.preventDefault();
        submitForm($(this), 'save_va', 'vaModal', function () {
            loadVas();
            refreshBadge();
        });
    });

    $('#applicationForm').on('submit', function (e) {
        e.preventDefault();
        submitForm($(this), 'save_application', 'applicationModal', loadApplications);
    });

    $('#adminForm').on('submit', function (e) {
        e.preventDefault();
        submitForm($(this), 'save_admin', 'adminModal', loadAdmins);
    });

    /* -----------------------------------------------------------------
       EDIT AND DELETE TRIGGERS
       ----------------------------------------------------------------- */

    $(document).on('click', '[data-edit-job]',    function () { openJobModal($(this).data('edit-job')); });
    $(document).on('click', '[data-edit-client]', function () { openClientModal($(this).data('edit-client')); });
    $(document).on('click', '[data-edit-va]',     function () { openVaModal($(this).data('edit-va')); });
    $(document).on('click', '[data-edit-app]',    function () { openApplicationModal($(this).data('edit-app')); });
    $(document).on('click', '[data-edit-admin]',  function () { openAdminModal($(this).data('edit-admin')); });

    function askDelete(action, id, label, warning) {
        pendingDelete = { action: action, id: id };
        $('#confirmText').text(warning || ('"' + label + '" will be removed for good.'));
        new bootstrap.Modal('#confirmModal').show();
    }

    $(document).on('click', '[data-del-job]', function () {
        askDelete('delete_job', $(this).data('del-job'), $(this).data('label'),
                  '"' + $(this).data('label') + '" and every application attached to it will be removed.');
    });

    $(document).on('click', '[data-del-client]', function () {
        askDelete('delete_client', $(this).data('del-client'), $(this).data('label'),
                  '"' + $(this).data('label') + '", its job postings, and the applications on them will all be removed.');
    });

    $(document).on('click', '[data-del-va]', function () {
        askDelete('delete_va', $(this).data('del-va'), $(this).data('label'),
                  '"' + $(this).data('label') + '" and every application they submitted will be removed.');
    });

    $(document).on('click', '[data-del-app]', function () {
        askDelete('delete_application', $(this).data('del-app'), $(this).data('label'),
                  'The application from "' + $(this).data('label') + '" will be removed.');
    });

    $(document).on('click', '[data-del-admin]', function () {
        askDelete('delete_admin', $(this).data('del-admin'), $(this).data('label'),
                  '"' + $(this).data('label') + '" will lose access to the console. Profiles they verified stay verified.');
    });

    $('#confirmYes').on('click', function () {
        if (!pendingDelete) { return; }

        api({ action: pendingDelete.action, id: pendingDelete.id }, function (res) {
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            toast(res.message, res.status !== 'success');

            if (res.status === 'success') {
                SECTIONS[currentSection].load();
                refreshBadge();
            }
            pendingDelete = null;
        });
    });

    /* -----------------------------------------------------------------
       SEARCH, FILTER, NAVIGATION
       ----------------------------------------------------------------- */

    // Live search. Waits 300ms after the last keystroke so the server is not
    // hit on every single letter.
    $('#globalSearch').on('input keyup', function () {
        const value = $(this).val();
        $('#searchClear').toggleClass('d-none', value === '');

        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            SECTIONS[currentSection].load();
        }, 300);
    });

    $('#searchClear').on('click', function () {
        $('#globalSearch').val('').focus();
        $(this).addClass('d-none');
        SECTIONS[currentSection].load();
    });

    $('#globalFilter').on('change', function () {
        SECTIONS[currentSection].load();
    });

    $('.nav-item').on('click', function () {
        switchSection($(this).data('section'));
    });

    $('#primaryAction').on('click', function () {
        const config = SECTIONS[currentSection];
        if (config.action) { config.action.open(); }
    });

    $('#signOutBtn').on('click', function () {
        api({ action: 'logout' }, function () {
            window.location.href = 'admin-login.php';
        });
    });

    /* Mobile navigation */
    function closeSidebar() {
        $('#sidebar').removeClass('open');
        $('#sidebarScrim').removeClass('show');
    }
    $('#menuToggle').on('click', function () {
        $('#sidebar').toggleClass('open');
        $('#sidebarScrim').toggleClass('show');
    });
    $('#sidebarScrim').on('click', closeSidebar);

    /* -----------------------------------------------------------------
       BOOT
       ----------------------------------------------------------------- */
    refreshCategories(function () {
        switchSection('dashboard');
    });

});
