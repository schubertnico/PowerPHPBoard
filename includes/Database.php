<?php

declare(strict_types=1);

/**
 * PowerPHPBoard - Database Abstraction Layer
 *
 * MIT License
 *
 * Copyright (c) 2026 PowerScripts
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 */

namespace PowerPHPBoard;

use PDO;
use PDOException;
use PDOStatement;

/**
 * PDO-based database abstraction layer
 * Replaces all legacy mysql_* functions with prepared statements
 */
class Database
{
    private static ?Database $instance = null;

    private readonly PDO $pdo;

    /**
     * @param array{server: string, user: string, password: string, database: string} $config
     */
    private function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config['server'],
            $config['database']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // MySQL-specific: Set charset on connection
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        $this->pdo = new PDO($dsn, $config['user'], $config['password'], $options);
    }

    /**
     * Get singleton instance
     *
     * @param array{server: string, user: string, password: string, database: string}|null $config
     */
    public static function getInstance(?array $config = null): self
    {
        if (!self::$instance instanceof self) {
            if ($config === null) {
                throw new PDOException('Database configuration required for first initialization');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Get PDO instance directly
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prepare a statement
     */
    public function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    /**
     * Execute a query with parameters
     *
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row
     *
     * @param array<int|string, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetch all rows
     *
     * @param array<int|string, mixed> $params
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Get row count for a query
     *
     * @param array<int|string, mixed> $params
     */
    public function count(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? (int) ($result['count'] ?? $result[0] ?? 0) : 0;
    }

    /**
     * Execute and return affected rows count
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        /** @var string $id PHPStan/Psalm disagree on PDO::lastInsertId return type */
        $id = $this->pdo->lastInsertId();
        return $id;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }
}
