<?php

require_once __DIR__ . '/DatabaseIntegrationTestCase.php';

/**
 * Runs only when PARIS_TEST_MYSQL_DSN is set, for example:
 * PARIS_TEST_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=paris_test'
 * PARIS_TEST_MYSQL_USER=root PARIS_TEST_MYSQL_PASSWORD=secret composer test
 * The tables it uses are dropped and recreated, so use an empty database.
 */
class MysqlIntegrationTest extends DatabaseIntegrationTestCase {

    protected function connect(): void {
        $dsn = getenv('PARIS_TEST_MYSQL_DSN');
        if (!$dsn) {
            $this->markTestSkipped('Set PARIS_TEST_MYSQL_DSN to run the MySQL tests.');
        }
        ORM::configure($dsn);
        ORM::configure('username', getenv('PARIS_TEST_MYSQL_USER') ?: 'root');
        ORM::configure('password', getenv('PARIS_TEST_MYSQL_PASSWORD') ?: '');
    }

    protected function primaryKey(): string {
        return 'INT AUTO_INCREMENT PRIMARY KEY';
    }
}
