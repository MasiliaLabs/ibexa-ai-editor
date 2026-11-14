<?php

declare(strict_types=1);

namespace Masilia\IbexaAiEditor\Suggestion;

use RuntimeException;

/**
 * The model answered, but not in the shape the field requires (e.g. no JSON object).
 */
final class InvalidModelOutputException extends RuntimeException
{
}
