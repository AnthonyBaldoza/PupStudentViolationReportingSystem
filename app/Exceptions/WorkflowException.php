<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule violation in the report workflow (safe to show to the user).
 */
class WorkflowException extends RuntimeException
{
}
