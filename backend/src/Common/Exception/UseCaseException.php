<?php

namespace App\Common\Exception;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class UseCaseException extends Exception
{
    public function __construct(string $message = "", int $code = Response::HTTP_BAD_REQUEST)
    {
        parent::__construct($message, $code);
    }
}
