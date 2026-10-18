<?php

namespace Asvri\Exception;

/**
 * ASVRI couldn't be reached, or answered with something that isn't JSON. For a refund, check in ASVRI (or list the
 * session) before trying again: it may have gone through.
 */
class ApiConnectionException extends AsvriException
{
    /** @var string|null */
    protected $rawBody;

    public function __construct($message, $httpStatus = 0, $rawBody = null)
    {
        parent::__construct($message, (int)$httpStatus);
        $this->rawBody = $rawBody;
    }

    public function getRawBody()
    {
        return $this->rawBody;
    }
}
