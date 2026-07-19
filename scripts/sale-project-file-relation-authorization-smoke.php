#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\service\biz\FileRelationService;
use app\service\biz\SaleProjectFileRelationService;
use app\service\biz\SaleProjectService;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'app\\')) {
        return;
    }

    $file = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
}, true, true);

function expectExceptionCode(callable $callback, int $expectedCode, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        if ($exception->getCode() === $expectedCode) {
            return;
        }

        throw new RuntimeException($message . ': expected code ' . $expectedCode
            . ', got ' . $exception->getCode());
    }

    throw new RuntimeException($message . ': expected exception');
}

$projectAccess = new class extends SaleProjectService {
    /** @var array<int, array{id: string, payload: array<string, mixed>}> */
    public array $calls = [];

    public function assertReadable(string $id, array $payload = []): void
    {
        $this->calls[] = ['id' => $id, 'payload' => $payload];
        if ($id === 'outside-scope') {
            throw new RuntimeException('permission denied', 403);
        }
    }
};

$fileRelations = new class extends FileRelationService {
    /** @var array<int, array{filters: array<string, mixed>, payload: array<string, mixed>}> */
    public array $calls = [];

    public function list(array $filters = [], array $payload = []): array
    {
        $this->calls[] = ['filters' => $filters, 'payload' => $payload];

        return [['id' => 'relation-1']];
    }
};

$service = new SaleProjectFileRelationService($projectAccess, $fileRelations);
$payload = ['user_id' => 'user-1', 'tenant_id' => 'tenant-1'];

expectExceptionCode(
    static fn (): array => $service->list('  ', $payload),
    400,
    'missing project id is rejected'
);

expectExceptionCode(
    static fn (): array => $service->list('outside-scope', $payload),
    403,
    'project outside caller scope is rejected'
);

if ($fileRelations->calls !== []) {
    throw new RuntimeException('file query must not run after project authorization denial');
}

$result = $service->list(' project-1 ', $payload);
if ($result !== [['id' => 'relation-1']]) {
    throw new RuntimeException('authorized attachment result is returned unchanged');
}

$expectedProjectCalls = [
    ['id' => 'outside-scope', 'payload' => $payload],
    ['id' => 'project-1', 'payload' => $payload],
];
if ($projectAccess->calls !== $expectedProjectCalls) {
    throw new RuntimeException('every non-empty project id must pass the project read guard');
}

$expectedFileCalls = [[
    'filters' => [
        'objectId' => 'project-1',
        'category' => 'SALE_PROJECT',
    ],
    'payload' => $payload,
]];
if ($fileRelations->calls !== $expectedFileCalls) {
    throw new RuntimeException('attachment query must be bound to the authorized project and SALE_PROJECT category');
}

fwrite(STDOUT, "sale project file relation authorization smoke passed\n");
