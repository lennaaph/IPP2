<?php

namespace IPP\Student;

use IPP\Core\Exception\IPPException;
use Throwable;

class ErrorException extends IPPException
{
    public function __construct(string $message, int $code, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous, false);
    }
}