<?php
final class Db
{
    protected static $pdo;
    protected static $origAutocommit = null;

    public static function connect()
    {
        $connection_string =
            "mysql:" .
            "host=" . (@Config::get()->db_creds->host ?: '127.0.0.1') . ';' .
            "dbname=" . Config::get()->db_creds->db;

        static::$pdo = new PDO(
            $connection_string,
            Config::get()->db_creds->username,
            Config::get()->db_creds->password
        );

        if (!static::$pdo) {
            error_response("Pdo connection problem\n" . implode("\n", static::$pdo->errorInfo()));
        }

        $timezone = @Config::get()->timezone;

        if ($timezone) {
            static::succeed("SET time_zone = '{$timezone}'", [], 'Failed to set timezone');
        }
    }

    public static function succeed($query, $params = [], $error_message = null)
    {
        $stmt = static::prepare($query);

        if (!$stmt->execute($params)) {
            if ($origAutocommit !== null) {
                static::rollback();
            }

            error_log("DB: did not succeed: {$query} " . print_r($stmt->errorInfo(), 1));
            error_response($error_message ?? 'Error', 500);
        }

        return $stmt;
    }

    public static function prepare($query)
    {
        $statement = static::$pdo->prepare($query);

        if (!$statement) {
            error_response("Statement problem\n" . implode("\n", static::$pdo->errorInfo()) . "\n{$query}");
        }

        return $statement;
    }

    public static function startTransaction()
    {
        if (self::$origAutocommit !== null) {
            error_response('Transaction already open', 500);
        }

        $result = static::succeed("show variables where Variable_name = 'autocommit'", [], 'Error starting transaction (1)');
        $autocommit = $result->fetch(PDO::FETCH_ASSOC);

        if (!is_array($autocommit) || !isset($autocommit['Value'])) {
            error_response('Error starting transaction (2)', 500);
        }

        static::succeed('set autocommit = OFF');
        static::succeed('start transaction');
        self::$origAutocommit = $autocommit['Value'];
    }

    public static function commit()
    {
        if (self::$origAutocommit === null) {
            error_response('No transaction open', 500);
        }

        static::succeed('commit');
        static::succeed('SET autocommit = ' . self::$origAutocommit);
        self::$origAutocommit = null;
    }

    public static function rollback()
    {
        if (self::$origAutocommit === null) {
            error_response('No transaction open', 500);
        }

        static::succeed('rollback');
        static::succeed('SET autocommit = ' . self::$origAutocommit);
        self::$origAutocommit = null;
    }
}
