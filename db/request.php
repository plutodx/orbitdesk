<?php
/**
 * ORBITDESK - Online Jobs Portal for Virtual Assistants
 * File   : db/request.php
 * Purpose: The single endpoint every AJAX call in the system talks to.
 *
 * Flow: script.js sends an "action" -> the switch routes it to the matching
 * method of a model class -> the result comes back as JSON -> jQuery redraws
 * only the affected part of the page. No page reload happens anywhere.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/VirtualAssistant.php';
require_once __DIR__ . '/../classes/Job.php';
require_once __DIR__ . '/../classes/JobApplication.php';

/** Shorthand for a JSON reply. */
function reply($payload)
{
    echo json_encode($payload);
    exit;
}

/** Reads a POST or GET value with a default. */
function input($key, $default = '')
{
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }
    return $default;
}

/**
 * Blocks every action except sign-in while nobody is logged in.
 *
 * The portal has three kinds of user, so every guarded action names the type
 * it expects. A client can never reach an admin action just by knowing its
 * name, because the type is read from the session, not from the request.
 *
 * @param string $type '' for any signed-in user, or 'admin' / 'client' / 'va'
 */
function requireUser($type = '')
{
    if (empty($_SESSION['user'])) {
        reply([
            'status'  => 'unauthorized',
            'message' => 'Your session expired. Please sign in again.'
        ]);
    }

    $user = $_SESSION['user'];

    if ($type !== '' && $user['type'] !== $type) {
        reply([
            'status'  => 'error',
            'message' => 'Your account type cannot perform that action.'
        ]);
    }

    return $user;
}

/** Shorthand used by the admin console actions. */
function requireLogin()
{
    return requireUser('admin');
}

/** Verified profiles only. Pending accounts can look around but not act. */
function requireVerified($user)
{
    if ($user['status'] !== 'verified') {
        reply([
            'status'  => 'error',
            'message' => 'Your profile is still waiting for admin verification, so this action is locked for now.'
        ]);
    }
}

$action = input('action');

// Model objects. Instantiating them here keeps every case block short.
$admin       = new Admin();
$client      = new Client();
$va          = new VirtualAssistant();
$job         = new Job();
$application = new JobApplication();

try {

    switch ($action) {

        /* ---------------------------------------------------------------
         * AUTHENTICATION
         * ------------------------------------------------------------- */
        case 'login':
            $role     = input('role', 'admin');
            $email    = trim(input('email'));
            $password = input('password');

            if ($role === 'client') {
                $result = $client->authenticate($email, $password);
            } elseif ($role === 'va') {
                $result = $va->authenticate($email, $password);
            } else {
                $result = $admin->authenticate($email, $password);

                // Reshape the admin payload so all three roles look the same.
                if ($result['status'] === 'success') {
                    $result['user'] = [
                        'type'   => 'admin',
                        'id'     => $result['admin']['id'],
                        'name'   => $result['admin']['full_name'],
                        'email'  => $result['admin']['email'],
                        'status' => 'verified'
                    ];
                }
            }

            if ($result['status'] === 'success') {
                // New session id on sign in, so a session id captured before
                // login cannot be reused afterwards (session fixation).
                session_regenerate_id(true);

                $_SESSION['user'] = $result['user'];

                // Kept for the admin console, which reads role off this key.
                if ($result['user']['type'] === 'admin') {
                    $_SESSION['admin'] = $result['admin'];
                }

                $landing = ['admin' => 'index.php', 'client' => 'client.php', 'va' => 'va.php'];

                reply([
                    'status'   => 'success',
                    'user'     => $result['user'],
                    'redirect' => $landing[$result['user']['type']]
                ]);
            }
            reply($result);

        case 'register':
            $role = input('role');

            if ($role === 'client') {
                $data = [
                    'company_name'   => trim(input('company_name')),
                    'contact_person' => trim(input('contact_person')),
                    'email'          => trim(input('email')),
                    'password'       => input('password'),
                    'phone'          => trim(input('phone')),
                    'location'       => trim(input('location')),
                    'status'         => 'pending'
                ];

                $errors = $client->validate($data, null);
                if ($errors) {
                    reply(['status' => 'error', 'message' => implode(' ', $errors)]);
                }

                $client->insert($data);

            } elseif ($role === 'va') {
                $data = [
                    'full_name'        => trim(input('full_name')),
                    'email'            => trim(input('email')),
                    'password'         => input('password'),
                    'phone'            => trim(input('phone')),
                    'skills'           => trim(input('skills')),
                    'experience_years' => input('experience_years', 0),
                    'hourly_rate'      => input('hourly_rate'),
                    'availability'     => input('availability', 'full-time'),
                    'status'           => 'pending'
                ];

                $errors = $va->validate($data, null);
                if ($errors) {
                    reply(['status' => 'error', 'message' => implode(' ', $errors)]);
                }

                $va->insert($data);

            } else {
                reply(['status' => 'error', 'message' => 'Choose whether you are signing up as a client or a virtual assistant.']);
            }

            reply([
                'status'  => 'success',
                'message' => 'Account created. An admin needs to verify your profile before it goes live, but you can sign in now.'
            ]);

        case 'logout':
            session_unset();
            session_destroy();
            reply(['status' => 'success', 'message' => 'Signed out.']);

        case 'whoami':
            reply(empty($_SESSION['user'])
                ? ['status' => 'unauthorized']
                : ['status' => 'success', 'user' => $_SESSION['user']]);

        /* ---------------------------------------------------------------
         * DASHBOARD
         * ------------------------------------------------------------- */
        case 'stats':
            requireLogin();
            reply(['status' => 'success', 'data' => $admin->dashboardStats()]);

        /* ---------------------------------------------------------------
         * JOBS
         * ------------------------------------------------------------- */
        case 'fetch_jobs':
            requireLogin();
            $keyword = trim(input('keyword'));
            $filter  = trim(input('filter'));
            $rows    = ($keyword === '' && $filter === '')
                        ? $job->select()
                        : $job->search($keyword, $filter);
            reply(['status' => 'success', 'data' => $rows]);

        case 'get_job':
            requireLogin();
            reply(['status' => 'success', 'data' => $job->select(input('id'))]);

        case 'save_job':
            requireLogin();
            $id   = input('id');
            $data = [
                'client_id'    => input('client_id'),
                'title'        => trim(input('title')),
                'category'     => trim(input('category')),
                'description'  => trim(input('description')),
                'rate_offered' => input('rate_offered'),
                'rate_type'    => input('rate_type'),
                'slots'        => input('slots'),
                'status'       => input('status', 'open')
            ];

            $errors = $job->validate($data, $id ?: null);
            if ($errors) {
                reply(['status' => 'error', 'message' => implode(' ', $errors)]);
            }

            reply($id ? $job->update($id, $data) : $job->insert($data));

        case 'delete_job':
            requireLogin();
            reply($job->delete(input('id')));

        case 'job_applicants':
            requireLogin();
            reply(['status' => 'success', 'data' => $application->byJob(input('id'))]);

        case 'job_categories':
            requireLogin();
            reply(['status' => 'success', 'data' => $job->categories()]);

        case 'job_options':
            requireLogin();
            reply(['status' => 'success', 'data' => $job->openOptions()]);

        /* ---------------------------------------------------------------
         * CLIENTS
         * ------------------------------------------------------------- */
        case 'fetch_clients':
            requireLogin();
            $keyword = trim(input('keyword'));
            $filter  = trim(input('filter'));
            $rows    = ($keyword === '' && $filter === '')
                        ? $client->select()
                        : $client->search($keyword, $filter);
            reply(['status' => 'success', 'data' => $rows]);

        case 'get_client':
            requireLogin();
            reply(['status' => 'success', 'data' => $client->select(input('id'))]);

        case 'save_client':
            requireLogin();
            $id   = input('id');
            $data = [
                'company_name'   => trim(input('company_name')),
                'contact_person' => trim(input('contact_person')),
                'email'          => trim(input('email')),
                'phone'          => trim(input('phone')),
                'location'       => trim(input('location'))
            ];

            // A brand new client always starts in the moderation queue.
            if (!$id) {
                $data['status'] = 'pending';
            }

            $errors = $client->validate($data, $id ?: null);
            if ($errors) {
                reply(['status' => 'error', 'message' => implode(' ', $errors)]);
            }

            reply($id ? $client->update($id, $data) : $client->insert($data));

        case 'delete_client':
            requireLogin();
            reply($client->delete(input('id')));

        case 'client_options':
            requireLogin();
            reply(['status' => 'success', 'data' => $client->verifiedOptions()]);

        /* ---------------------------------------------------------------
         * VIRTUAL ASSISTANTS
         * ------------------------------------------------------------- */
        case 'fetch_vas':
            requireLogin();
            $keyword = trim(input('keyword'));
            $filter  = trim(input('filter'));
            $rows    = ($keyword === '' && $filter === '')
                        ? $va->select()
                        : $va->search($keyword, $filter);
            reply(['status' => 'success', 'data' => $rows]);

        case 'get_va':
            requireLogin();
            reply(['status' => 'success', 'data' => $va->select(input('id'))]);

        case 'save_va':
            requireLogin();
            $id   = input('id');
            $data = [
                'full_name'        => trim(input('full_name')),
                'email'            => trim(input('email')),
                'phone'            => trim(input('phone')),
                'skills'           => trim(input('skills')),
                'experience_years' => input('experience_years'),
                'hourly_rate'      => input('hourly_rate'),
                'availability'     => input('availability')
            ];

            if (!$id) {
                $data['status'] = 'pending';
            }

            $errors = $va->validate($data, $id ?: null);
            if ($errors) {
                reply(['status' => 'error', 'message' => implode(' ', $errors)]);
            }

            reply($id ? $va->update($id, $data) : $va->insert($data));

        case 'delete_va':
            requireLogin();
            reply($va->delete(input('id')));

        case 'va_options':
            requireLogin();
            reply(['status' => 'success', 'data' => $va->verifiedOptions()]);

        /* ---------------------------------------------------------------
         * APPLICATIONS
         * ------------------------------------------------------------- */
        case 'fetch_applications':
            requireLogin();
            $keyword = trim(input('keyword'));
            $filter  = trim(input('filter'));
            $rows    = ($keyword === '' && $filter === '')
                        ? $application->select()
                        : $application->search($keyword, $filter);
            reply(['status' => 'success', 'data' => $rows]);

        case 'get_application':
            requireLogin();
            reply(['status' => 'success', 'data' => $application->select(input('id'))]);

        case 'save_application':
            requireLogin();
            $id   = input('id');
            $data = [
                'job_id'        => input('job_id'),
                'va_id'         => input('va_id'),
                'cover_note'    => trim(input('cover_note')),
                'expected_rate' => input('expected_rate'),
                'status'        => input('status', 'pending')
            ];

            $errors = $application->validate($data, $id ?: null);
            if ($errors) {
                reply(['status' => 'error', 'message' => implode(' ', $errors)]);
            }

            reply($id ? $application->update($id, $data) : $application->insert($data));

        case 'delete_application':
            requireLogin();
            reply($application->delete(input('id')));

        case 'set_application_status':
            requireLogin();

            $id     = input('id');
            $jobId  = $application->jobIdOf($id);
            $status = input('status');

            $wasHired = $application->statusOf($id) === 'hired';

            if ($status === 'hired' && !$wasHired && !$job->hasOpenSlot($jobId)) {
                reply(['status' => 'error',
                       'message' => 'This posting has no slots left. Edit the job to add more slots first.']);
            }

            $result = $application->setStatus($id, $status);
            $job->syncFilledStatus($jobId);

            reply($result);

        /* ---------------------------------------------------------------
         * ADMINS
         * ------------------------------------------------------------- */
        case 'fetch_admins':
            requireLogin();
            $keyword = trim(input('keyword'));
            $filter  = trim(input('filter'));
            $rows    = ($keyword === '' && $filter === '')
                        ? $admin->select()
                        : $admin->search($keyword, $filter);
            reply(['status' => 'success', 'data' => $rows]);

        case 'get_admin':
            requireLogin();
            reply(['status' => 'success', 'data' => $admin->select(input('id'))]);

        case 'save_admin':
            requireLogin();

            $id   = input('id');
            $data = [
                'full_name' => trim(input('full_name')),
                'email'     => trim(input('email')),
                'password'  => input('password'),
                'status'    => input('admin_status', 'active')
            ];

            $errors = $admin->validate($data, $id ?: null);
            if ($errors) {
                reply(['status' => 'error', 'message' => implode(' ', $errors)]);
            }

            reply($id ? $admin->update($id, $data) : $admin->insert($data));

        case 'delete_admin':
            $current = requireLogin();

            // Stops an admin from deleting the account they are signed in with,
            // which would lock them straight out of the console.
            if ((int) input('id') === (int) $current['id']) {
                reply(['status' => 'error', 'message' => 'You cannot delete the account you are signed in with.']);
            }

            reply($admin->delete(input('id')));

        /* ---------------------------------------------------------------
         * MODERATION
         * ------------------------------------------------------------- */
        case 'fetch_queue':
            requireLogin();
            reply(['status' => 'success', 'data' => $admin->moderationQueue(input('status', 'pending'))]);

        case 'moderate':
            $current = requireLogin();
            reply($admin->moderateProfile(
                input('profile_type'),
                input('id'),
                input('decision'),
                $current['id']
            ));


        /* ---------------------------------------------------------------
         * CLIENT PORTAL
         * Every action re-checks ownership against the session, so a client
         * can only ever touch their own jobs and their own applicants.
         * ------------------------------------------------------------- */
        case 'client_stats':
            $me = requireUser('client');
            reply(['status' => 'success', 'data' => $client->portalStats($me['id']), 'profile_status' => $me['status']]);

        case 'client_jobs':
            $me = requireUser('client');
            reply(['status' => 'success', 'data' => $job->byClient($me['id'], trim(input('keyword')))]);

        case 'client_save_job':
            $me = requireUser('client');
            requireVerified($me);

            $id = input('id');

            if ($id && !$job->belongsTo($id, $me['id'])) {
                reply(['status' => 'error', 'message' => 'That job posting does not belong to your account.']);
            }

            $data = [
                'client_id'    => $me['id'],           // taken from the session, never the form
                'title'        => trim(input('title')),
                'category'     => trim(input('category')),
                'description'  => trim(input('description')),
                'rate_offered' => input('rate_offered'),
                'rate_type'    => input('rate_type'),
                'slots'        => input('slots'),
                'status'       => input('status', 'open')
            ];

            $errors = $job->validate($data, $id ?: null);
            if ($errors) {
                reply(['status' => 'error', 'message' => implode(' ', $errors)]);
            }

            reply($id ? $job->update($id, $data) : $job->insert($data));

        case 'client_delete_job':
            $me = requireUser('client');
            $id = input('id');

            if (!$job->belongsTo($id, $me['id'])) {
                reply(['status' => 'error', 'message' => 'That job posting does not belong to your account.']);
            }

            reply($job->delete($id));

        case 'client_applicants':
            $me = requireUser('client');
            $id = input('id');

            if (!$job->belongsTo($id, $me['id'])) {
                reply(['status' => 'error', 'message' => 'That job posting does not belong to your account.']);
            }

            reply(['status' => 'success', 'data' => $application->byJob($id)]);

        case 'client_set_status':
            $me = requireUser('client');
            requireVerified($me);
            $id = input('id');

            if (!$application->belongsToClient($id, $me['id'])) {
                reply(['status' => 'error', 'message' => 'That application is not on one of your job postings.']);
            }

            $jobId  = $application->jobIdOf($id);
            $status = input('status');

            // Do not let a posting hire more people than it has slots for.
            // Re-selecting hired on someone already hired is a no-op, not an error.
            $wasHired = $application->statusOf($id) === 'hired';

            if ($status === 'hired' && !$wasHired && !$job->hasOpenSlot($jobId)) {
                reply(['status' => 'error',
                       'message' => 'This posting has no slots left. Edit the job to add more slots first.']);
            }

            $result = $application->setStatus($id, $status);
            $job->syncFilledStatus($jobId);

            reply($result);

        case 'client_profile':
            $me = requireUser('client');
            reply(['status' => 'success', 'data' => $client->select($me['id'])]);

        case 'client_save_profile':
            $me = requireUser('client');

            $data = [
                'company_name'   => trim(input('company_name')),
                'contact_person' => trim(input('contact_person')),
                'email'          => trim(input('email')),
                'password'       => input('password'),
                'phone'          => trim(input('phone')),
                'location'       => trim(input('location'))
            ];

            $errors = $client->validate($data, $me['id']);
            if ($errors) {
                reply(['status' => 'error', 'message' => implode(' ', $errors)]);
            }

            $result = $client->update($me['id'], $data);
            $_SESSION['user']['name']  = $data['company_name'];
            $_SESSION['user']['email'] = $data['email'];

            reply($result);

        /* ---------------------------------------------------------------
         * VIRTUAL ASSISTANT PORTAL
         * ------------------------------------------------------------- */
        case 'va_stats':
            $me = requireUser('va');
            reply(['status' => 'success', 'data' => $va->portalStats($me['id']), 'profile_status' => $me['status']]);

        case 'va_browse':
            $me = requireUser('va');
            reply(['status' => 'success', 'data' => $va->browseJobs($me['id'], trim(input('keyword')), trim(input('filter')))]);

        case 'va_apply':
            $me = requireUser('va');
            requireVerified($me);

            // The board hides closed and filled postings, but hiding is not
            // enforcement. Check again here before accepting anything.
            $problem = $job->applicationCheck(input('job_id'));
            if ($problem !== null) {
                reply(['status' => 'error', 'message' => $problem]);
            }

            $data = [
                'job_id'        => input('job_id'),
                'va_id'         => $me['id'],          // taken from the session, never the form
                'cover_note'    => trim(input('cover_note')),
                'expected_rate' => input('expected_rate'),
                'status'        => 'pending'
            ];

            $errors = $application->validate($data, null);
            if ($errors) {
                reply(['status' => 'error', 'message' => implode(' ', $errors)]);
            }

            $result            = $application->insert($data);
            $result['message'] = 'Application sent. The client will see it on their dashboard.';
            reply($result);

        case 'va_applications':
            $me = requireUser('va');
            reply(['status' => 'success', 'data' => $application->byVa($me['id'], trim(input('filter')))]);

        case 'va_withdraw':
            $me = requireUser('va');
            $id = input('id');

            if (!$application->belongsToVa($id, $me['id'])) {
                reply(['status' => 'error', 'message' => 'That application does not belong to your profile.']);
            }

            $result            = $application->delete($id);
            $result['message'] = 'Application withdrawn.';
            reply($result);

        case 'va_profile':
            $me = requireUser('va');
            reply(['status' => 'success', 'data' => $va->select($me['id'])]);

        case 'va_save_profile':
            $me = requireUser('va');

            $data = [
                'full_name'        => trim(input('full_name')),
                'email'            => trim(input('email')),
                'password'         => input('password'),
                'phone'            => trim(input('phone')),
                'skills'           => trim(input('skills')),
                'experience_years' => input('experience_years'),
                'hourly_rate'      => input('hourly_rate'),
                'availability'     => input('availability')
            ];

            $errors = $va->validate($data, $me['id']);
            if ($errors) {
                reply(['status' => 'error', 'message' => implode(' ', $errors)]);
            }

            $result = $va->update($me['id'], $data);
            $_SESSION['user']['name']  = $data['full_name'];
            $_SESSION['user']['email'] = $data['email'];

            reply($result);

        case 'public_categories':
            requireUser();
            reply(['status' => 'success', 'data' => $job->categories()]);

        /* ---------------------------------------------------------------
         * FALLBACK
         * ------------------------------------------------------------- */
        default:
            reply(['status' => 'error', 'message' => 'Unknown action requested: ' . htmlspecialchars($action)]);
    }

} catch (PDOException $e) {

    // Friendlier text for the two database errors this app can realistically hit.
    $message = $e->getMessage();

    if ($e->getCode() === '23000') {
        $message = 'That record conflicts with an existing one. Check for duplicate emails or a repeat application.';
    }

    reply(['status' => 'error', 'message' => $message]);

} catch (Exception $e) {
    reply(['status' => 'error', 'message' => $e->getMessage()]);
}
