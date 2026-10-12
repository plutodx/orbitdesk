<?php
/**
 * ORBITDESK - Online Jobs Portal for Virtual Assistants
 * File   : classes/VirtualAssistant.php
 * Purpose: The talent side of the portal.
 */

require_once __DIR__ . '/../db/myDB.php';

class VirtualAssistant extends myDB
{
    protected $table  = 'tbl_vas';
    protected $fields = ['full_name', 'email', 'password', 'phone', 'skills', 'experience_years',
                         'hourly_rate', 'availability', 'status'];

    /** Hashes the password before the row is written. */
    public function insert(array $data)
    {
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        return parent::insert($data);
    }

    /** A blank password box means "keep the current password". */
    public function update($id, array $data)
    {
        if (isset($data['password']) && trim($data['password']) !== '') {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }
        return parent::update($id, $data);
    }

    /** Sign-in for the virtual assistant portal. */
    public function authenticate($email, $password)
    {
        $row = $this->run(
            "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1",
            [':email' => $email]
        )->fetch();

        if (!$row || !password_verify($password, $row['password'])) {
            return ['status' => 'error', 'message' => 'Wrong email or password.'];
        }

        if ($row['status'] === 'rejected') {
            return ['status' => 'error', 'message' => 'This profile was rejected during review. Contact the portal admins.'];
        }

        return [
            'status' => 'success',
            'user'   => [
                'type'   => 'va',
                'id'     => $row['id'],
                'name'   => $row['full_name'],
                'email'  => $row['email'],
                'status' => $row['status']
            ]
        ];
    }

    /** Numbers for the VA portal home screen. */
    public function portalStats($vaId)
    {
        return $this->run(
            "SELECT
               (SELECT COUNT(*) FROM tbl_applications WHERE va_id = :v1) AS applications_sent,
               (SELECT COUNT(*) FROM tbl_applications WHERE va_id = :v2 AND status = 'shortlisted') AS shortlisted,
               (SELECT COUNT(*) FROM tbl_applications WHERE va_id = :v3 AND status = 'hired') AS hired,
               (SELECT COUNT(*) FROM tbl_jobs j
                  INNER JOIN tbl_clients c ON c.id = j.client_id
                  WHERE j.status = 'open' AND c.status = 'verified') AS jobs_open",
            [':v1' => $vaId, ':v2' => $vaId, ':v3' => $vaId]
        )->fetch();
    }

    /**
     * The job board a VA sees. Only open jobs from verified clients, and each
     * row carries a flag saying whether this VA has already applied.
     */
    public function browseJobs($vaId, $keyword = '', $category = '')
    {
        $sql = "SELECT j.*, c.company_name, c.location,
                       (SELECT COUNT(*) FROM tbl_applications a
                         WHERE a.job_id = j.id AND a.va_id = :va) AS already_applied,
                       (SELECT COUNT(*) FROM tbl_applications a WHERE a.job_id = j.id) AS applicant_count
                FROM tbl_jobs j
                INNER JOIN tbl_clients c ON c.id = j.client_id
                WHERE j.status = 'open' AND c.status = 'verified'";

        $params = [':va' => $vaId];

        if ($keyword !== '') {
            $sql .= " AND (j.title LIKE :kw OR j.category LIKE :kw2 OR j.description LIKE :kw3 OR c.company_name LIKE :kw4)";
            $params[':kw']  = "%{$keyword}%";
            $params[':kw2'] = "%{$keyword}%";
            $params[':kw3'] = "%{$keyword}%";
            $params[':kw4'] = "%{$keyword}%";
        }

        if ($category !== '') {
            $sql .= " AND j.category = :category";
            $params[':category'] = $category;
        }

        $sql .= " ORDER BY j.created_at DESC";

        return $this->run($sql, $params)->fetchAll();
    }

    private function baseQuery()
    {
        return "SELECT v.*, a.full_name AS verified_by_name,
                       (SELECT COUNT(*) FROM tbl_applications ap WHERE ap.va_id = v.id) AS applications_sent,
                       (SELECT COUNT(*) FROM tbl_applications ap WHERE ap.va_id = v.id AND ap.status = 'hired') AS times_hired
                FROM tbl_vas v
                LEFT JOIN tbl_admins a ON a.id = v.verified_by";
    }

    public function select($id = null)
    {
        if ($id !== null) {
            return $this->run($this->baseQuery() . " WHERE v.id = :id", [':id' => $id])->fetch();
        }
        return $this->run($this->baseQuery() . " ORDER BY v.id DESC")->fetchAll();
    }

    public function search($keyword, $filter = '')
    {
        $sql = $this->baseQuery() . " WHERE (v.full_name LIKE :kw
                                          OR v.email LIKE :kw2
                                          OR v.skills LIKE :kw3)";

        $params = [
            ':kw'  => "%{$keyword}%",
            ':kw2' => "%{$keyword}%",
            ':kw3' => "%{$keyword}%"
        ];

        if ($filter !== '') {
            $sql .= " AND v.status = :status";
            $params[':status'] = $filter;
        }

        $sql .= " ORDER BY v.id DESC";

        return $this->run($sql, $params)->fetchAll();
    }

    /** Verified VAs only, used by the application form dropdown. */
    public function verifiedOptions()
    {
        return $this->run(
            "SELECT id, full_name, hourly_rate FROM tbl_vas WHERE status = 'verified' ORDER BY full_name ASC"
        )->fetchAll();
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
            $errors[] = 'Another virtual assistant already uses that email.';
        }

        if (trim($data['phone'] ?? '') === '') {
            $errors[] = 'Phone number is required.';
        }

        // Password is only mandatory when the profile is first created.
        if (array_key_exists('password', $data)) {
            $password = $data['password'];
            if ($id === null && strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            } elseif ($id !== null && $password !== '' && strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }
        }

        if (trim($data['skills'] ?? '') === '') {
            $errors[] = 'List at least one skill.';
        }

        $years = $data['experience_years'] ?? '';
        if ($years === '' || !is_numeric($years) || $years < 0 || $years > 50) {
            $errors[] = 'Years of experience must be a number between 0 and 50.';
        }

        $rate = $data['hourly_rate'] ?? '';
        if ($rate === '' || !is_numeric($rate) || $rate <= 0) {
            $errors[] = 'Hourly rate must be greater than zero.';
        }

        if (!in_array($data['availability'] ?? '', ['full-time', 'part-time', 'project-based'], true)) {
            $errors[] = 'Please choose a valid availability.';
        }

        return $errors;
    }
}
