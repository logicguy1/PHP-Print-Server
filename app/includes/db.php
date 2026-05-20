<?php

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    db_init($pdo);
    return $pdo;
}

function db_init(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role          TEXT NOT NULL DEFAULT 'user',
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS print_jobs (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id       INTEGER NOT NULL,
            filename      TEXT NOT NULL,
            original_name TEXT NOT NULL,
            copies        INTEGER NOT NULL DEFAULT 1,
            paper_size    TEXT NOT NULL DEFAULT 'A4',
            duplex        TEXT NOT NULL DEFAULT 'none',
            quality       TEXT NOT NULL DEFAULT 'normal',
            status        TEXT NOT NULL DEFAULT 'pending',
            cups_job_id   INTEGER,
            error_message TEXT,
            created_at    TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");

    // Migrate: add pages_per_sheet if missing (existing deployments)
    $cols = array_column($pdo->query('PRAGMA table_info(print_jobs)')->fetchAll(), 'name');
    if (!in_array('pages_per_sheet', $cols, true)) {
        $pdo->exec("ALTER TABLE print_jobs ADD COLUMN pages_per_sheet INTEGER NOT NULL DEFAULT 1");
    }

    // Seed default admin if no users exist
    $count = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ((int)$count === 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')"
        );
        $stmt->execute(['admin', password_hash('admin', PASSWORD_BCRYPT)]);
    }
}
