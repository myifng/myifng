<?php
declare(strict_types=1);

namespace App\Core;

/** abort(404) जैसी HTTP त्रुटियों के लिए */
class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }
}
