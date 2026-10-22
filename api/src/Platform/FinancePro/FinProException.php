<?php
declare(strict_types=1);

namespace App\Platform\FinancePro;

final class FinProException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 0, public readonly ?string $errCode = null)
    {
        parent::__construct($message, $httpStatus);
    }
}
