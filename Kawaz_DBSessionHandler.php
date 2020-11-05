<?php
namespace Kawaz {

    use PDO;
    use PDOException;
    use SessionHandlerInterface;
    use SessionIdInterface;

    class DBSessionHandler implements SessionHandlerInterface, SessionIdInterface
    {
        private $ulid = null;
        private $pdo = null;
        private $dsn = null;
        private $username = null;
        private $password = null;
        private $stmt_create_table = null;
        private $stmt_select = null;
        private $stmt_upsert = null;
        private $stmt_delete = null;
        private $stmt_test = null;
        private $stmt_gc = null;

        function __construct(string $dsn, string $username, string $password, string $table = "php_session", string $col_id = "id", string $col_value = "value", string $col_created = "created_at", string $col_updated = "updated_at")
        {
            $this->ulid = new Ulid();
            $this->dsn = $dsn;
            $this->username = $username;
            $this->password = $password;
            $this->stmt_create_table = "create table $table(id varchar(256) primary key, $col_value text, $col_created timestamp not null default current_timestamp, $col_updated timestamp not null default current_timestamp)";
            $this->stmt_upsert = "INSERT INTO $table ($col_id, $col_value) VALUES (:id, :value) ON DUPLICATE KEY UPDATE value=:value, $col_updated=current_timestamp";
            $this->stmt_select = "SELECT $col_value FROM $table WHERE $col_id=:id";
            $this->stmt_delete = "DELETE FROM $table WHERE $col_id=:id";
            $this->stmt_test = "SELECT $col_id,$col_value,$col_created,$col_updated FROM $table LIMIT 0";
            $this->stmt_gc = "DELETE FROM $table WHERE $col_updated < current_timestamp - INTERVAL :maxage SECOND";
        }

        /**
         * @return string
         */
        public function create_sid(): string
        {
            return $this->ulid->generate();
        }

        /**
         * @param string
         * @param string
         * @return bool
         */
        public function open($save_path, $session_name): bool
        {
            if ($this->pdo == null) {
                try {
                    $this->pdo = new PDO($this->dsn, $this->username, $this->password);
                    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    try {
                        $this->pdo->query($this->stmt_test);
                    } catch (PDOException $e) {
                        //TODO: DBが無いときに自動で CREATE TABLE する
                        if ($e->getCode() === '42S02') {
                            // SQLSTATE[42S02]: Base table or view not found: 1146 Table 'テーブル名' doesn't exist
                            // テーブルが無ければ作る
                            $this->pdo->exec($this->stmt_create_table);
                            $this->pdo->query($this->stmt_test);
                        }
                        throw $e;
                    }
                    return true;
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    return false;
                }
            }
        }
        /**
         * @param string
         * @return string
         */
        public function read($session_id): string
        {
            $stmt = $this->pdo->prepare($this->stmt_select);
            if ($stmt->execute([":id" => $session_id])) {
                if ($stmt->rowCount() > 0) {
                    $row = $stmt->fetch(PDO::FETCH_NUM);
                    if ($row[0] !== null) {
                        return $row[0];
                    }
                }
            }
            return '';
        }
        /**
         * @param string
         * @param string
         * @return bool
         */
        public function write($session_id, $session_data): bool
        {
            $stmt = $this->pdo->prepare($this->stmt_upsert);
            $stmt->execute([":id" => $session_id, ":value" => $session_data]);
            return true;
        }
        /**
         * @param int
         * @return int
         */
        public function gc($maxlifetime): int
        {
            $stmt = $this->pdo->prepare($this->stmt_gc);
            return $stmt->execute([":maxage" => $maxlifetime]);
        }
        /**
         * @param string
         */
        public function destroy($session_id): bool
        {
            $stmt = $this->pdo->prepare($this->stmt_delete);
            return $stmt->execute([":id" => $session_id]);
        }
        public function close(): bool
        {
            return true;
        }
    }

    class Ulid
    {
        const BASE32_CHARS = "0123456789ABCDEFGHJKMNPQRSTVWXYZ";
        private $lastTimestamp = 0;
        public function generate(): string
        {
            $ts = (int) (microtime(true) * 1000);
            if ($ts === $this->lastTimestamp) {
                $ts = $this->lastTimestamp + 1;
            }
            $this->lastTimestamp = $ts;
            $time10 = '';
            for ($i = 1; $i <= 10; $i++) {
                $mod = $ts % 32;
                $time10 = self::BASE32_CHARS[$mod] . $time10;
                $ts = ($ts - $mod) / 32;
            }
            $rand16 = '';
            for ($i = 0; $i < 16; $i++) {
                $rand16 .= self::BASE32_CHARS[random_int(0, 31)];
            }
            return $time10 . $rand16;
        }
    }
}
