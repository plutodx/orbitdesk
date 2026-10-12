<?php
/**
 * ORBITDESK - Online Jobs Portal for Virtual Assistants
 * File   : classes/Client.php
 * Purpose: Companies that post work on the portal.
 */

require_once __DIR__ . '/../db/myDB.php';

class Client extends myDB
{
    protected $table  = 'tbl_clients';
    protected $fields = ['company_name', 'contact_person', 'email', 'password', 'phone', 'location', 'status'];

    /**
     * Hashes the password on the way in, so a plain password never reaches
     * the database no matter which screen created the account.
     */
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

    /** Sign-in for the client portal. */
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
            return ['status' => 'error', 'message' => 'This account was rejected during review. Contact the portal admins.'];
        }

        return [
            'status' => 'success',
            'user'   => [
                'type'   => 'client',
                'id'     => $row['id'],
                'name'   => $row['company_name'],
                'email'  => $row['email'],
                'status' => $row['status']
            ]
        ];
    }

    /** Numbers for the client portal home screen. */
    public function portalStats($clientId)
    {
        $row = $this->run(
            "SELECT
               (SELECT COUNT(*) FROM tbl_jobs WHERE client_id = :c1) AS jobs_total,
               (SELECT COUNT(*) FROM tbl_jobs WHERE client_id = :c2 AND status = 'open') AS jobs_open,
               (SELECT COUNT(*) FROM tbl_applications a
                  INNER JOIN tbl_jobs j ON j.id = a.job_id WHERE j.client_id = :c3) AS applicants_total,
               (SELECT COUNT(*) FROM tbl_applications a
                  INNER JOIN tbl_jobs j ON j.id = a.job_id
                  WHERE j.client_id = :c4 AND a.status = 'pending') AS applicants_new,
               (SELECT COUNT(*) FROM tbl_applications a
                  INNER JOIN tbl_jobs j ON j.id = a.job_id
                  WHERE j.client_id = :c5 AND a.status = 'hired') AS hires",
            [':c1' => $clientId, ':c2' => $clientId, ':c3' => $clientId, ':c4' => $clientId, ':c5' => $clientId]
        )->fetch();

        return $row;
    }

    /** Base query, joined to the admin who reviewed the profile. */
    private function baseQuery()
    {
        return "SELECT c.*, a.full_name AS verified_by_name,
                       (SELECT COUNT(*) FROM tbl_jobs j WHERE j.client_id = c.id) AS jobs_posted
                FROM tbl_clients c
                LEFT JOIN tbl_admins a ON a.id = c.verified_by";
    }

    public function select($id = null)
    {
        if ($id !== null) {
            return $this->run($this->baseQuery() . " WHERE c.id = :id", [':id' => $id])->fetch();
        }
        return $this->run($this->baseQuery() . " ORDER BY c.id DESC")->fetchAll();
    }

    public function search($keyword, $filter = '')
    {
        $sql = $this->baseQuery() . " WHERE (c.company_name LIKE :kw
                                          OR c.contact_person LIKE :kw2
                                          OR c.email LIKE :kw3
                                          OR c.location LIKE :kw4)";

        $params = [
            ':kw'  => "%{$keyword}%",
            ':kw2' => "%{$keyword}%",
            ':kw3' => "%{$keyword}%",
            ':kw4' => "%{$keyword}%"
        ];

        if ($filter !== '') {
            $sql .= " AND c.status = :status";
            $params[':status'] = $filter;
        }

        $sql .= " ORDER BY c.id DESC";

        return $this->run($sql, $params)->fetchAll();
    }

    /** Fills the client dropdown on the job form. Verified accounts only. */
    public function verifiedOptions()
    {
        return $this->run(
            "SELECT id, company_name FROM tbl_clients WHERE status = 'verified' ORDER BY company_name ASC"
        )->fetchAll();
    }

    public function validate(array $data, $id = null)
    {
        $errors = [];

        if (trim($data['company_name'] ?? '') === '') {
            $errors[] = 'Company name is required.';
        }

        if (trim($data['contact_person'] ?? '') === '') {
            $errors[] = 'Contact person is required.';
        }

        $email = trim($data['email'] ?? '');
        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That email address is not valid.';
        } elseif ($this->isTaken('email', $email, $id)) {
            $errors[] = 'Another client already uses that email.';
        }

        if (trim($data['phone'] ?? '') === '') {
            $errors[] = 'Phone number is required.';
        }

        // Password is only mandatory when the account is first created.
        if (array_key_exists('password', $data)) {
            $password = $data['password'];
            if ($id === null && strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            } elseif ($id !== null && $password !== '' && strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            }
        }

        if (trim($data['location'] ?? '') === '') {
            $errors[] = 'Location is required.';
        }

        return $errors;
    }
}
