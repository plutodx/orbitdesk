<?php
/**
 * ORBITDESK - Online Jobs Portal for Virtual Assistants
 * File   : db/myDB.php
 * Purpose: Abstract parent class holding every shared CRUD operation.
 *
 * OOP concepts demonstrated:
 *   - Interface    : CrudInterface forces every model to expose the same methods
 *   - Abstraction  : myDB can never be instantiated on its own
 *   - Inheritance  : Admin, Client, VirtualAssistant, Job, JobApplication extend it
 *   - Polymorphism : children override select(), search() and validate()
 *   - Encapsulation: $conn is protected, the raw PDO handle never leaks to the view
 */

require_once __DIR__ . '/config.php';

interface CrudInterface
{
    public function insert(array $data);
    public function select($id = null);
    public function update($id, array $data);
    public function delete($id);
    public function search($keyword, $filter = '');
}

abstract class myDB implements CrudInterface
{
    /** @var PDO Shared connection handle */
    protected $conn;

    /** @var string Physical table name, set by each child class */
    protected $table;

    /** @var array Columns the child class allows to be written */
    protected $fields = [];

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    /**
     * Runs any prepared statement. Every query in the system passes through here,
     * so SQL injection is blocked in one place instead of twenty.
     */
    protected function run($sql, array $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Keeps only the keys that the child class declared in $fields.
     */
    protected function filterFields(array $data)
    {
        return array_intersect_key($data, array_flip($this->fields));
    }

    /**
     * CREATE. Builds "INSERT INTO tbl (a,b) VALUES (:a,:b)" from $fields.
     */
    public function insert(array $data)
    {
        $clean = $this->filterFields($data);
        if (empty($clean)) {
            return ['status' => 'error', 'message' => 'No valid fields were submitted.'];
        }

        $columns      = implode(', ', array_keys($clean));
        $placeholders = ':' . implode(', :', array_keys($clean));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $this->run($sql, $clean);

        return [
            'status'  => 'success',
            'id'      => $this->conn->lastInsertId(),
            'message' => 'Record saved successfully.'
        ];
    }

    /**
     * READ. Children normally override this to add their JOINs.
     */
    public function select($id = null)
    {
        if ($id !== null) {
            return $this->run("SELECT * FROM {$this->table} WHERE id = :id", [':id' => $id])->fetch();
        }
        return $this->run("SELECT * FROM {$this->table} ORDER BY id DESC")->fetchAll();
    }

    /**
     * UPDATE. Builds "UPDATE tbl SET a=:a, b=:b WHERE id=:id".
     */
    public function update($id, array $data)
    {
        $clean = $this->filterFields($data);
        if (empty($clean)) {
            return ['status' => 'error', 'message' => 'No valid fields were submitted.'];
        }

        $assignments = [];
        foreach (array_keys($clean) as $column) {
            $assignments[] = "{$column} = :{$column}";
        }

        $sql            = "UPDATE {$this->table} SET " . implode(', ', $assignments) . " WHERE id = :id";
        $clean[':id']   = $id;

        $this->run($sql, $clean);

        return ['status' => 'success', 'message' => 'Record updated successfully.'];
    }

    /**
     * DELETE. Foreign keys with ON DELETE CASCADE clean up the child rows.
     */
    public function delete($id)
    {
        $this->run("DELETE FROM {$this->table} WHERE id = :id", [':id' => $id]);
        return ['status' => 'success', 'message' => 'Record deleted successfully.'];
    }

    /**
     * SEARCH. Default behaviour returns everything, children narrow it down.
     */
    public function search($keyword, $filter = '')
    {
        return $this->select();
    }

    /** Simple row counter used by the dashboard cards. */
    public function countAll($where = '', array $params = [])
    {
        $sql = "SELECT COUNT(*) AS total FROM {$this->table} " . $where;
        $row = $this->run($sql, $params)->fetch();
        return (int) $row['total'];
    }

    /** Checks a value for uniqueness, ignoring the row being edited. */
    protected function isTaken($column, $value, $ignoreId = null)
    {
        $sql    = "SELECT id FROM {$this->table} WHERE {$column} = :value";
        $params = [':value' => $value];

        if ($ignoreId) {
            $sql .= " AND id <> :id";
            $params[':id'] = $ignoreId;
        }

        return (bool) $this->run($sql, $params)->fetch();
    }

    /**
     * Every child must describe how its own data is validated.
     * This is the polymorphic hook the router calls before writing anything.
     *
     * @return array List of error messages, empty array means the data is good.
     */
    abstract public function validate(array $data, $id = null);
}
