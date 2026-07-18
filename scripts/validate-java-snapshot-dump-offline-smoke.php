#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/validate-java-snapshot-dump.php';

function validator_smoke_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function validator_smoke_dump(): string
{
    $lines = [
        '-- synthetic mysqldump safety fixture',
        '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;',
        '/*!50503 SET NAMES utf8mb4 */;',
        '',
    ];
    for ($index = 1; $index <= 121; $index++) {
        $table = sprintf('fixture_%03d', $index);
        array_push(
            $lines,
            "DROP TABLE IF EXISTS `{$table}`;",
            '/*!40101 SET @saved_cs_client = @@character_set_client */;',
            '/*!50503 SET character_set_client = utf8mb4 */;',
            "CREATE TABLE `{$table}` (",
            '  `ID` bigint NOT NULL,',
            '  `PAYLOAD` varchar(255) DEFAULT NULL,',
            '  PRIMARY KEY (`ID`)',
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='fixture';",
            '/*!40101 SET character_set_client = @saved_cs_client */;',
            "LOCK TABLES `{$table}` WRITE;",
            "/*!40000 ALTER TABLE `{$table}` DISABLE KEYS */;"
        );
        if ($index === 1) {
            $lines[] = "INSERT INTO `{$table}` VALUES (1,'safe; DROP USER must_remain_data');";
        }
        array_push(
            $lines,
            "/*!40000 ALTER TABLE `{$table}` ENABLE KEYS */;",
            'UNLOCK TABLES;',
            ''
        );
    }
    array_push(
        $lines,
        '/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;',
        '-- synthetic dump complete',
        ''
    );

    return implode("\n", $lines);
}

function validator_smoke_validate(string $contents, ?string $expectedHash = null): array
{
    $path = tempnam(sys_get_temp_dir(), 'oa-dump-validator-');
    if (!is_string($path)) {
        throw new RuntimeException('unable to create validator smoke fixture');
    }
    try {
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('unable to write validator smoke fixture');
        }

        return snapshot_dump_validate_file(
            $path,
            $expectedHash ?? hash_file('sha256', $path),
            'oa2026'
        );
    } finally {
        @unlink($path);
    }
}

function validator_smoke_rejects(string $contents, string $label): void
{
    try {
        validator_smoke_validate($contents);
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException("validator accepted {$label}");
}

try {
    $valid = validator_smoke_dump();
    $summary = validator_smoke_validate($valid);
    validator_smoke_assert(($summary['status'] ?? '') === 'passed', 'valid mysqldump fixture failed');
    validator_smoke_assert(($summary['createTableCount'] ?? 0) === 121, 'valid fixture table count failed');
    validator_smoke_assert(($summary['insertStatementCount'] ?? 0) === 1, 'valid fixture insert count failed');
    try {
        validator_smoke_validate($valid, str_repeat('0', 64));
        throw new RuntimeException('validator accepted a mismatched SHA-256');
    } catch (RuntimeException $exception) {
        validator_smoke_assert(
            str_contains($exception->getMessage(), 'SHA-256 differs'),
            'mismatched SHA-256 did not fail at the digest gate'
        );
    }

    $firstDrop = 'DROP TABLE IF EXISTS `fixture_001`;';
    $dangerousLines = [
        'mysql client system command' => '\\! echo blocked',
        'mysql client source command' => '\\. blocked.sql',
        'dynamic SQL prepare' => "PREPARE blocked FROM 'DROP USER attacker';",
        'dynamic SQL execute' => 'EXECUTE blocked;',
        'user administration' => "DROP USER 'attacker'@'localhost';",
        'privilege administration' => "GRANT ALL ON *.* TO 'attacker'@'localhost';",
        'database administration' => 'CREATE DATABASE escaped;',
        'cross-database selection' => 'USE mysql;',
        'server file import' => "LOAD DATA INFILE 'C:/escaped' INTO TABLE fixture_001;",
        'server file export' => "SELECT 'escaped' INTO OUTFILE 'C:/escaped';",
        'session collation override' => 'SET SESSION default_collation_for_utf8mb4=utf8mb4_0900_ai_ci;',
        'dangerous executable comment' => "/*!50000 CREATE USER 'attacker'@'localhost' */;",
        'same-line appended command' => "UNLOCK TABLES; DROP USER 'attacker'@'localhost';",
        'same-line appended executable comment' => "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */; DROP USER 'attacker'@'localhost';",
        'qualified cross-schema insert' => 'INSERT INTO `mysql`.`user` VALUES (1);',
    ];
    foreach ($dangerousLines as $label => $line) {
        validator_smoke_rejects(str_replace($firstDrop, $line . "\n" . $firstDrop, $valid), $label);
    }

    validator_smoke_rejects(
        str_replace(
            "INSERT INTO `fixture_001` VALUES (1,'safe; DROP USER must_remain_data');",
            "INSERT INTO `fixture_001` VALUES (1,NULL); DROP USER 'attacker'@'localhost';",
            $valid
        ),
        'dangerous statement appended to INSERT'
    );
    validator_smoke_rejects(
        str_replace(
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='fixture';",
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DATA DIRECTORY='C:/escaped';",
            $valid
        ),
        'unsafe CREATE TABLE data directory'
    );

    fwrite(STDOUT, json_encode([
        'status' => 'passed',
        'networkConnections' => 0,
        'databaseWrites' => 0,
        'acceptedFixtures' => 1,
        'rejectedFixtures' => count($dangerousLines) + 3,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'snapshot dump validator offline smoke failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
