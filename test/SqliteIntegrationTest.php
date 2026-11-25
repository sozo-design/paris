<?php

require_once __DIR__ . '/DatabaseIntegrationTestCase.php';

class SqliteIntegrationTest extends DatabaseIntegrationTestCase {

    protected function connect(): void {
        ORM::configure('sqlite::memory:');
    }

    protected function primaryKey(): string {
        return 'INTEGER PRIMARY KEY';
    }
}
