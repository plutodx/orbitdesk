<?php
/**
 * ORBITDESK - Online Jobs Portal for Virtual Assistants
 * File   : classes/Admin.php
 * Purpose: Admin accounts, sign-in, and moderation of client / VA profiles.
 */

require_once __DIR__ . '/../db/myDB.php';

class Admin extends myDB
{
    protected $table  = 'tbl_admins';
    protected $fields = ['full_name', 'email', 'password', 'status'];

    /** Never send the password hash to the browser. */
    public function select($id = null)
    {
        $columns = 'id, full_name, email, status, created_at';

        if ($id !== null) {
            return $this->run("SELECT {$columns} FROM {$this->table} WHERE id = :id", [':id' => $id])->fetch();
        }

        $sql = "SELECT {$columns},
                       (SELECT COUNT(*) FROM tbl_clients c WHERE c.verified_by = a.id) AS clients_reviewed,
                       (SELECT COUNT(*) FROM tbl_vas v WHERE v.verified_by = a.id)     AS vas_reviewed
                FROM {$this->table} a
                ORDER BY a.full_name ASC";

        return $this->run($sql)->fetchAll();
    }

    public function search($keyword, $filter = '')
    {
        $sql = "SELECT id, full_name, email, status, created_at,
                       (SELECT COUNT(*) FROM tbl_clients c WHERE c.verified_by = a.id) AS clients_reviewed,
                       (SELECT COUNT(*) FROM tbl_vas v WHERE v.verified_by = a.id)     AS vas_reviewed
                FROM {$this->table} a
                WHERE (a.full_name LIKE :kw OR a.email LIKE :kw2)";

        $params = [':kw' => "%{$keyword}%", ':kw2' => "%{$keyword}%"];

        if ($filter !== '') {
            $sql .= " AND a.status = :status";
            $params[':status'] = $filter;
        }

        $sql .= " ORDER BY a.full_name ASC";

        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * Hashes the password before the parent class writes the row.
     */
    public function insert(array $data)
    {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        return parent::insert($data);
    }

    /**
     * A blank password box on the edit form means "keep the current password".
     */
    public function update($id, array $data)
    {
        if (isset($data['password']) && trim($data['password']) !== '') {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }
        return parent::update($id, $data);
    }

    /**
     * Sign-in check used by the AJAX login form.
     */
    public function authenticate($email, $password)
    {
        $row = $this->run(
            "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1",
            [':email' => $email]
        )->fetch();

        if (!$row) {
            return ['status' => 'error', 'message' => 'No account found for that email address.'];
        }

        if ($row['status'] === 'inactive') {
            return ['status' => 'error', 'message' => 'This account has been deactivated.'];
        }

        if (!password_verify($password, $row['password'])) {
            return ['status' => 'error', 'message' => 'Wrong password. Please try again.'];
        }

        return [
            'status' => 'success',
            'admin'  => [
                'id'        => $row['id'],
                'full_name' => $row['full_name'],
                'email'     => $row['email']
            ]
        ];
    }

    /**
     * The moderation action the whole portal depends on. An admin verifies or
     * rejects a client or a VA profile, and the decision is stamped with who
     * made it and when.
     *
     * @param string $profileType "client" or "va"
     */
    public function moderateProfile($profileType, $profileId, $decision, $adminId)
    {
        $tables = ['client' => 'tbl_clients', 'va' => 'tbl_vas'];

        if (!isset($tables[$profileType])) {
            return ['status' => 'error', 'message' => 'Unknown profile type.'];
        }

        if (!in_array($decision, ['pending', 'verified', 'rejected'], true)) {
            return ['status' => 'error', 'message' => 'Unknown moderation decision.'];
        }

        $table = $tables[$profileType];

        if ($decision === 'pending') {
            $sql    = "UPDATE {$table} SET status = 'pending', verified_by = NULL, verified_at = NULL WHERE id = :id";
            $params = [':id' => $profileId];
        } else {
            $sql    = "UPDATE {$table} SET status = :status, verified_by = :admin, verified_at = NOW() WHERE id = :id";
            $params = [':status' => $decision, ':admin' => $adminId, ':id' => $profileId];
        }

        $this->run($sql, $params);

        $label = $profileType === 'client' ? 'Client' : 'Virtual assistant';

        return [
            'status'  => 'success',
            'message' => "{$label} profile marked as {$decision}."
        ];
    }

    /** Everything the dashboard cards and the moderation queue need, in one trip. */
    public function dashboardStats()
    {
        $one = function ($sql) {
            $row = $this->run($sql)->fetch();
            return (int) $row['total'];
        };

        $stats = [
            'clients_total'   => $one("SELECT COUNT(*) total FROM tbl_clients"),
            'vas_total'       => $one("SELECT COUNT(*) total FROM tbl_vas"),
            'jobs_open'       => $one("SELECT COUNT(*) total FROM tbl_jobs WHERE status = 'open'"),
            'jobs_total'      => $one("SELECT COUNT(*) total FROM tbl_jobs"),
            'apps_total'      => $one("SELECT COUNT(*) total FROM tbl_applications"),
            'apps_hired'      => $one("SELECT COUNT(*) total FROM tbl_applications WHERE status = 'hired'"),
            'pending_clients' => $one("SELECT COUNT(*) total FROM tbl_clients WHERE status = 'pending'"),
            'pending_vas'     => $one("SELECT COUNT(*) total FROM tbl_vas WHERE status = 'pending'"),
            'admins_active'   => $one("SELECT COUNT(*) total FROM tbl_admins WHERE status = 'active'")
        ];

        $stats['pending_total'] = $stats['pending_clients'] + $stats['pending_vas'];

        // Application status spread, used by the bar meter on the dashboard.
        $stats['app_breakdown'] = $this->run(
            "SELECT status, COUNT(*) AS total FROM tbl_applications GROUP BY status"
        )->fetchAll();

        // Busiest categories.
        $stats['top_categories'] = $this->run(
            "SELECT j.category, COUNT(a.id) AS applicants
             FROM tbl_jobs j
             LEFT JOIN tbl_applications a ON a.job_id = j.id
             GROUP BY j.category
             ORDER BY applicants DESC, j.category ASC
             LIMIT 5"
        )->fetchAll();

        // Latest applications for the activity feed.
        $stats['recent_activity'] = $this->run(
            "SELECT a.id, a.status, a.applied_at, v.full_name AS va_name,
                    j.title AS job_title, c.company_name
             FROM tbl_applications a
             INNER JOIN tbl_vas v     ON v.id = a.va_id
             INNER JOIN tbl_jobs j    ON j.id = a.job_id
             INNER JOIN tbl_clients c ON c.id = j.client_id
             ORDER BY a.applied_at DESC
             LIMIT 6"
        )->fetchAll();

        return $stats;
    }

    /** The combined verification queue shown on the Moderation screen. */
    public function moderationQueue($statusFilter = 'pending')
    {
        $where  = $statusFilter === '' ? '' : ' WHERE status = :status';
        $params = $statusFilter === '' ? [] : [':status' => $statusFilter];

        $clients = $this->run(
            "SELECT id, company_name AS name, contact_person AS subtitle, email, status,
                    verified_by, created_at, 'client' AS profile_type
             FROM tbl_clients" . $where,
            $params
        )->fetchAll();

        $vas = $this->run(
            "SELECT id, full_name AS name, skills AS subtitle, email, status,
                    verified_by, created_at, 'va' AS profile_type
             FROM tbl_vas" . $where,
            $params
        )->fetchAll();

        $queue = array_merge($clients, $vas);

        usort($queue, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $queue;
    }

    public function validate(array $data, $id = null)
    {
        $errors = [];

        if (trim($data['full_name'] ?? '') === '') {
            $errors[] = 'Full name is required.';
        }

        $email = trim($data['email'] ?? '');
        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That email address is not valid.';
        } elseif ($this->isTaken('email', $email, $id)) {
            $errors[] = 'Another admin already uses that email.';
        }

        $password = $data['password'] ?? '';
        if ($id === null && strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif ($id !== null && $password !== '' && strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        return $errors;
    }
}
