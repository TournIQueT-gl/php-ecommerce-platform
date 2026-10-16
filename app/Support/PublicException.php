<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Exception whose message is intentionally safe to show to an end user.
 * Infrastructure/database exceptions must never use this type.
 */
final class PublicException extends \RuntimeException
{
}
