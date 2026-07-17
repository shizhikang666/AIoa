<?php

declare(strict_types=1);

namespace app\support\migration;

use RuntimeException;

/**
 * Safe, non-data-bearing failure raised by the workflow variable migration.
 */
class WorkflowVariableMigrationException extends RuntimeException
{
}
