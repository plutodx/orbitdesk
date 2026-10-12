<?php
/**
 * ORBITDESK - Online Jobs Portal for Virtual Assistants
 * File   : classes/Job.php
 * Purpose: Job postings. One client posts many jobs.
 */

require_once __DIR__ . '/../db/myDB.php';

class Job extends myDB
{
    protected $table  = 'tbl_jobs';
    protected $fields = ['client_id', 'title', 'category', 'description',
                         'rate_offered', 'rate_type', 'slots', 'status'];

    /** Joined to the client so the listing shows who posted it. */
    protected function baseQuery()
    {
        return "SELECT j.*, c.company_name, c.location, c.status AS client_status,
                       (SELECT COUNT(*) FROM tbl_applications a WHERE a.job_id = j.id) AS applicant_count,
                       (SELECT COUNT(*) FROM tbl_applications a WHERE a.job_id = j.id AND a.status = 'hired') AS hired_count
                FROM tbl_jobs j
                INNER JOIN tbl_clients c ON c.id = j.client_id";
    }

    public function select($id = null)
    {
        if ($id !== null) {
            return $this->run($this->baseQuery() . " WHERE j.id = :id", [':id' => $id])->fetch();
        }
        return $this->run($this->baseQuery() . " ORDER BY j.id DESC")->fetchAll();
    }

    /** The live keyword search from the job board, filtered by category. */
    public function search($keyword, $filter = '')
    {
        $sql = $this->baseQuery() . " WHERE (j.title LIKE :kw
                                          OR j.category LIKE :kw2
                                          OR j.description LIKE :kw3
                                          OR c.company_name LIKE :kw4)";

        $params = [
            ':kw'  => "%{$keyword}%",
            ':kw2' => "%{$keyword}%",
            ':kw3' => "%{$keyword}%",
            ':kw4' => "%{$keyword}%"
        ];

        if ($filter !== '') {
            $sql .= " AND j.category = :category";
            $params[':category'] = $filter;
        }

        $sql .= " ORDER BY j.id DESC";

        return $this->run($sql, $params)->fetchAll();
    }

    /** Only the jobs belonging to one client, for the client portal. */
    public function byClient($clientId, $keyword = '')
    {
        $sql    = $this->baseQuery() . " WHERE j.client_id = :client";
        $params = [':client' => $clientId];

        if ($keyword !== '') {
            $sql .= " AND (j.title LIKE :kw OR j.category LIKE :kw2 OR j.description LIKE :kw3)";
            $params[':kw']  = "%{$keyword}%";
            $params[':kw2'] = "%{$keyword}%";
            $params[':kw3'] = "%{$keyword}%";
        }

        $sql .= " ORDER BY j.id DESC";

        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * Everything that must be true before an application is accepted.
     * The job board already hides closed and filled postings, but hiding is
     * not enforcement, so the server checks again on every apply.
     *
     * @return string|null  An error message, or null when the job is open.
     */
    public function applicationCheck($jobId)
    {
        $row = $this->run(
            "SELECT j.status, j.slots, c.status AS client_status,
                    (SELECT COUNT(*) FROM tbl_applications a
                      WHERE a.job_id = j.id AND a.status = 'hired') AS hired
             FROM tbl_jobs j
             INNER JOIN tbl_clients c ON c.id = j.client_id
             WHERE j.id = :id",
            [':id' => $jobId]
        )->fetch();

        if (!$row) {
            return 'That job posting no longer exists.';
        }
        if ($row['client_status'] !== 'verified') {
            return 'That client account is not verified, so the posting is not open.';
        }
        if ($row['status'] !== 'open') {
            return 'That job posting is no longer accepting applications.';
        }
        if ((int) $row['hired'] >= (int) $row['slots']) {
            return 'That job posting has already been filled.';
        }

        return null;
    }

    /** True when the job still has room for one more hire. */
    public function hasOpenSlot($jobId)
    {
        $row = $this->run(
            "SELECT j.slots,
                    (SELECT COUNT(*) FROM tbl_applications a
                      WHERE a.job_id = j.id AND a.status = 'hired') AS hired
             FROM tbl_jobs j WHERE j.id = :id",
            [':id' => $jobId]
        )->fetch();

        return $row && (int) $row['hired'] < (int) $row['slots'];
    }

    /**
     * Keeps the posting status honest after a hire or an un-hire.
     * Full postings become filled, and a posting that frees up reopens.
     */
    public function syncFilledStatus($jobId)
    {
        $row = $this->run(
            "SELECT j.status, j.slots,
                    (SELECT COUNT(*) FROM tbl_applications a
                      WHERE a.job_id = j.id AND a.status = 'hired') AS hired
             FROM tbl_jobs j WHERE j.id = :id",
            [':id' => $jobId]
        )->fetch();

        if (!$row) {
            return;
        }

        if ((int) $row['hired'] >= (int) $row['slots'] && $row['status'] === 'open') {
            $this->run("UPDATE {$this->table} SET status = 'filled' WHERE id = :id", [':id' => $jobId]);
        } elseif ((int) $row['hired'] < (int) $row['slots'] && $row['status'] === 'filled') {
            $this->run("UPDATE {$this->table} SET status = 'open' WHERE id = :id", [':id' => $jobId]);
        }
    }

    /** Ownership check. Stops a client touching a job that is not theirs. */
    public function belongsTo($jobId, $clientId)
    {
        $row = $this->run(
            "SELECT id FROM {$this->table} WHERE id = :id AND client_id = :client",
            [':id' => $jobId, ':client' => $clientId]
        )->fetch();

        return (bool) $row;
    }

    /** Distinct categories for the filter dropdown. */
    public function categories()
    {
        return $this->run(
            "SELECT DISTINCT category FROM tbl_jobs ORDER BY category ASC"
        )->fetchAll();
    }

    /** Open jobs for the application form dropdown. */
    public function openOptions()
    {
        return $this->run(
            "SELECT j.id, j.title, c.company_name
             FROM tbl_jobs j
             INNER JOIN tbl_clients c ON c.id = j.client_id
             WHERE j.status = 'open'
             ORDER BY j.title ASC"
        )->fetchAll();
    }

    public function validate(array $data, $id = null)
    {
        $errors = [];

        if (empty($data['client_id']) || !is_numeric($data['client_id'])) {
            $errors[] = 'Please choose the client posting this job.';
        }

        if (trim($data['title'] ?? '') === '') {
            $errors[] = 'Job title is required.';
        }

        if (trim($data['category'] ?? '') === '') {
            $errors[] = 'Category is required.';
        }

        if (strlen(trim($data['description'] ?? '')) < 15) {
            $errors[] = 'Description should be at least 15 characters.';
        }

        $rate = $data['rate_offered'] ?? '';
        if ($rate === '' || !is_numeric($rate) || $rate <= 0) {
            $errors[] = 'Offered rate must be greater than zero.';
        }

        if (!in_array($data['rate_type'] ?? '', ['hourly', 'monthly'], true)) {
            $errors[] = 'Choose either an hourly or a monthly rate.';
        }

        $slots = $data['slots'] ?? '';
        if ($slots === '' || !is_numeric($slots) || $slots < 1) {
            $errors[] = 'Slots must be at least 1.';
        }

        return $errors;
    }
}
