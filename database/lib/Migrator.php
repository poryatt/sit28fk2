<?php
// Guard helpers over information_schema. Every method is read-only except ensureIndex() and replaceForeignKey().
// Identifiers are validated, never trusted: only [A-Za-z0-9_] is accepted.
final class Migrator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function tableExists(string $table): bool
    {
        return $this->scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$table]) > 0;
    }

    public function columnExists(string $table, string $column): bool
    {
        return $this->scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]) > 0;
    }

    public function indexExists(string $table, string $index): bool
    {
        return $this->scalar("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?", [$table, $index]) > 0;
    }

    public function fkExists(string $table, string $name): bool
    {
        return $this->scalar("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'", [$table, $name]) > 0;
    }

    public function checkExists(string $table, string $name): bool
    {
        return $this->scalar("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'CHECK'", [$table, $name]) > 0;
    }

    /** DELETE_RULE ('CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION') or null when the FK does not exist. */
    public function fkRule(string $table, string $name): ?string
    {
        $v = $this->scalar("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?", [$table, $name]);
        return $v === null || $v === false ? null : (string)$v;
    }

    /** COLUMN_TYPE such as "enum('active','suspended')" or "varchar(512)", or null. */
    public function columnType(string $table, string $column): ?string
    {
        $v = $this->scalar("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]);
        return $v === null || $v === false ? null : strtolower((string)$v);
    }

    public function isNullable(string $table, string $column): bool
    {
        return $this->scalar("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]) === 'YES';
    }

    public function collation(string $table, string $column): ?string
    {
        $v = $this->scalar("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$table, $column]);
        return $v === null || $v === false ? null : (string)$v;
    }

    /** First column of the first row, as int (for COUNT(*) preflights). */
    public function count(string $sql, array $params = []): int
    {
        return (int)$this->scalar($sql, $params);
    }

    /** $colsSql example: "(ip_address, attempted_at)". Adds the index only when no index with that name exists. */
    public function ensureIndex(string $table, string $name, string $colsSql, bool $unique = false): void
    {
        $this->ident($table);
        $this->ident($name);
        if ($this->indexExists($table, $name)) {
            return;
        }
        $this->pdo->exec('ALTER TABLE `' . $table . '` ADD ' . ($unique ? 'UNIQUE ' : '') . 'INDEX `' . $name . '` ' . $colsSql);
    }

    /** Single-column FK. Same rule ⇒ nothing; other rule ⇒ drop + add; missing ⇒ add. */
    public function replaceForeignKey(string $table, string $name, string $col, string $refTable, string $refCol, string $onDelete): void
    {
        foreach ([$table, $name, $col, $refTable, $refCol] as $id) {
            $this->ident($id);
        }
        $rule = strtoupper($onDelete);
        if (!in_array($rule, ['RESTRICT', 'CASCADE', 'SET NULL', 'NO ACTION'], true)) {
            throw new InvalidArgumentException('Unsupported ON DELETE rule: ' . $onDelete);
        }
        $current = $this->fkRule($table, $name);
        if ($current === $rule) {
            return;
        }
        if ($current !== null) {
            $this->pdo->exec('ALTER TABLE `' . $table . '` DROP FOREIGN KEY `' . $name . '`');
        }
        $this->pdo->exec('ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $name . '` FOREIGN KEY (`' . $col . '`) REFERENCES `' . $refTable . '` (`' . $refCol . '`) ON DELETE ' . $rule);
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false ? null : $v;
    }

    private function ident(string $id): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $id)) {
            throw new InvalidArgumentException('Bad identifier: ' . $id);
        }
    }
}
