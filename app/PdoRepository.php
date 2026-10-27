<?php
declare(strict_types=1);

/** Transactional entity repository. Immutable snapshots and extension fields remain JSON. */
final class PdoRepository
{
    private const TABLES = ['users', 'categories', 'products', 'orders', 'expenses', 'incomes', 'inventory_movements', 'notifications', 'user_account_events'];
    private PDO $db;
    public function __construct()
    {
        $this->db = new PDO(getenv('SG_DB_DSN'), getenv('SG_DB_USER') ?: '', getenv('SG_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    public function migrate(): void
    {
        $this->db->exec(file_get_contents(__DIR__ . '/../database/runtime.sql'));
    }
    public function access(bool $write, callable $callback): mixed
    {
        $this->db->beginTransaction();
        try {
            // Serialize state-changing business operations, including inventory and ID allocation.
            if ($write) $this->db->query('SELECT id FROM sg_lock WHERE id = 1 FOR UPDATE')->fetch();
            $settings = $this->db->query('SELECT name, value FROM sg_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!$settings) throw new RuntimeException('Database is empty. Run bin/migrate.php first.');
            $data = ['settings' => array_map(static fn($v) => json_decode($v, true, 512, JSON_THROW_ON_ERROR), $settings)];
            foreach (self::TABLES as $table) {
                $data[$table] = array_map(static fn($v) => json_decode($v, true, 512, JSON_THROW_ON_ERROR), $this->db->query("SELECT document FROM sg_$table ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
            }
            $before = $data;
            $result = $callback($data);
            if ($write) $this->persist($data, $before);
            $this->db->commit(); return $result;
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    public function import(array $data): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->query('SELECT id FROM sg_lock WHERE id = 1 FOR UPDATE')->fetch();
            if ($this->db->query('SELECT COUNT(*) FROM sg_users')->fetchColumn() || $this->db->query('SELECT COUNT(*) FROM sg_settings')->fetchColumn()) throw new RuntimeException('Import requires an empty database; existing records were preserved.');
            $this->persist($data, []); $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    private function persist(array $data, array $before): void
    {
        $setting = $this->db->prepare('INSERT INTO sg_settings (name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
        foreach ($data['settings'] as $key => $value) if (!isset($before['settings'][$key]) || $before['settings'][$key] !== $value) $setting->execute([$key, json_encode($value, JSON_THROW_ON_ERROR)]);
        foreach (self::TABLES as $table) {
            $old = array_column($before[$table] ?? [], null, 'id');
            $statement = $this->db->prepare("INSERT INTO sg_$table (id,document) VALUES (?,?) ON DUPLICATE KEY UPDATE document=VALUES(document)");
            foreach ($data[$table] ?? [] as $row) {
                if (($old[$row['id']] ?? null) === $row) continue;
                $statement->execute([$row['id'], json_encode($row, JSON_THROW_ON_ERROR)]);
                if ($table === 'orders') {
                    $this->db->prepare('DELETE FROM sg_order_items WHERE order_id=?')->execute([$row['id']]);
                    $item = $this->db->prepare('INSERT INTO sg_order_items (order_id,position,product_id,quantity,unit_price,document) VALUES (?,?,?,?,?,?)');
                    foreach ($row['lines'] as $index => $line) $item->execute([$row['id'], $index, $line['product_id'], $line['quantity'], $line['unit_price'], json_encode($line, JSON_THROW_ON_ERROR)]);
                }
            }
        }
    }
}
