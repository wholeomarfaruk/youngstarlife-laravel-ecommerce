<?php

namespace App\Services\Couriers;

/** A courier API refused or could not be reached. $reason: auth | gateway | validation | not_supported | needs_location | failed */
class CourierException extends \RuntimeException
{
    public function __construct(string $message, public string $reason = 'failed', public array $raw = [])
    {
        parent::__construct($message);
    }
}
