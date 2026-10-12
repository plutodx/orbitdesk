<?php
/**
 * ORBITDESK - Online Jobs Portal for Virtual Assistants
 * File   : classes/JobApplication.php
 * Purpose: The junction between jobs and virtual assistants. Many to many.
 */

require_once __DIR__ . '/../db/myDB.php';

class JobApplication extends myDB
{
    protected $table  = 'tbl_applications';
    protected $fields = ['job_id', 'va_id', 'cover_note', 'expected_rate', 'status'];

    /** Triple join: application -> job -> client, plus application -> VA. */
    protected function baseQuery()
    {
        return "SELECT ap.*, j.title AS job_title, j.category, j.rate_offered, j.rate_type,
                       c.id AS client_id, c.company_name, c.contact_person,
                       c.email AS client_email, c.phone AS client_phone,
                       v.full_name AS va_name, v.email AS va_email, v.phone AS va_phone,
                       v.skills, v.status AS va_status
                FROM tbl_applications ap
                INNER JOIN tbl_jobs j    ON j.id = ap.job_id
                INNER JOIN tbl_clients c ON c.id = j.client_id
                INNER JOIN tbl_vas v     ON v.id = ap.va_id";
    }

    public function select($id = null)
    {
        if ($id !== null) {
            return $this->run($this->baseQuery() . " WHERE ap.id = :id", [':id' => $id])->fetch();
        }
        return $this->run($this->baseQuery() . " ORDER BY ap.applied_at DESC")->fetchAll();
    }

    public function search($keyword, $filter = '')
    {
        $sql = $this->baseQuery() . " WHERE (v.full_name LIKE :kw
                                          OR j.title LIKE :kw2
                                          OR c.company_name LIKE :kw3
                                          OR ap.cover_note LIKE :kw4)";

        $params = [
            ':kw'  => "%{$keyword}%",
            ':kw2' => "%{$keyword}%",
            ':kw3' => "%{$keyword}%",
            ':kw4' => "%{$keyword}%"
        ];

        if ($filter !== '') {
            $sql .= " AND ap.status = :status";
            $params[':status'] = $filter;
        }

        $sql .= " ORDER BY ap.applied_at DESC";

        return $this->run($sql, $params)->fetchAll();
    }

    /** Applications for one job, used by the "view applicants" panel. */
    public function byJob($jobId)
    {
        return $this->run(
            $this->baseQuery() . " WHERE ap.job_id = :job ORDER BY FIELD(ap.status,'hired','shortlisted','pending','rejected'), ap.applied_at ASC",
            [':job' => $jobId]
        )->fetchAll();
    }

    /** Everything one VA has applied to, for the VA portal. */
    public function byVa($vaId, $statusFilter = '')
    {
        $sql    = $this->baseQuery() . " WHERE ap.va_id = :va";
        $params = [':va' => $vaId];

        if ($statusFilter !== '') {
            $sql .= " AND ap.status = :status";
            $params[':status'] = $statusFilter;
        }

        $sql .= " ORDER BY ap.applied_at DESC";

        return $this->run($sql, $params)->fetchAll();
    }

    /** True when the application sits on a job owned by this client. */
    public function belongsToClient($applicationId, $clientId)
    {
        $row = $this->run(
            "SELECT ap.id FROM {$this->table} ap
             INNER JOIN tbl_jobs j ON j.id = ap.job_id
             WHERE ap.id = :id AND j.client_id = :client",
            [':id' => $applicationId, ':client' => $clientId]
        )->fetch();

        return (bool) $row;
    }

    /** True when the application was submitted by this VA. */
    public function belongsToVa($applicationId, $vaId)
    {
        $row = $this->run(
            "SELECT id FROM {$this->table} WHERE id = :id AND va_id = :va",
            [':id' => $applicationId, ':va' => $vaId]
        )->fetch();

        return (bool) $row;
    }

    /** Returns the job id and current status of an application. */
    public function jobIdOf($applicationId)
    {
        $row = $this->run("SELECT job_id FROM {$this->table} WHERE id = :id", [':id' => $applicationId])->fetch();
        return $row ? $row['job_id'] : null;
    }

    /** Current status of an application, or null when it does not exist. */
    public function statusOf($applicationId)
    {
        $row = $this->run("SELECT status FROM {$this->table} WHERE id = :id", [':id' => $applicationId])->fetch();
        return $row ? $row['status'] : null;
    }

    /** Quick status change from the applicants table without opening the modal. */
    public function setStatus($id, $status)
    {
        if (!in_array($status, ['pending', 'shortlisted', 'hired', 'rejected'], true)) {
            return ['status' => 'error', 'message' => 'Unknown application status.'];
        }

        $this->run("UPDATE {$this->table} SET status = :status WHERE id = :id", [
            ':status' => $status,
            ':id'     => $id
        ]);

        return ['status' => 'success', 'message' => "Application marked as {$status}."];
    }

    /** Stops the same VA from applying twice to the same job. */
    private function isDuplicate($jobId, $vaId, $ignoreId = null)
    {
        $sql    = "SELECT id FROM {$this->table} WHERE job_id = :job AND va_id = :va";
        $params = [':job' => $jobId, ':va' => $vaId];

        if ($ignoreId) {
            $sql .= " AND id <> :id";
            $params[':id'] = $ignoreId;
        }

        return (bool) $this->run($sql, $params)->fetch();
    }

    public function validate(array $data, $id = null)
    {
        $errors = [];

        $jobId = $data['job_id'] ?? '';
        $vaId  = $data['va_id'] ?? '';

        if (empty($jobId) || !is_numeric($jobId)) {
            $errors[] = 'Please choose a job posting.';
        }

        if (empty($vaId) || !is_numeric($vaId)) {
            $errors[] = 'Please choose a virtual assistant.';
        }

        if (empty($errors) && $this->isDuplicate($jobId, $vaId, $id)) {
            $errors[] = 'That virtual assistant has already applied to this job.';
        }

        if (strlen(trim($data['cover_note'] ?? '')) < 10) {
            $errors[] = 'Cover note should be at least 10 characters.';
        }

        $rate = $data['expected_rate'] ?? '';
        if ($rate === '' || !is_numeric($rate) || $rate <= 0) {
            $errors[] = 'Expected rate must be greater than zero.';
        }

        if (!in_array($data['status'] ?? 'pending', ['pending', 'shortlisted', 'hired', 'rejected'], true)) {
            $errors[] = 'Please choose a valid application status.';
        }

        return $errors;
    }
}
