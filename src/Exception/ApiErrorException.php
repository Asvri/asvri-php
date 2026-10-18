<?php

namespace Asvri\Exception;

/**
 * ASVRI answered with an error. getHttpStatus(), getErrorType() (authentication_error, invalid_request_error,
 * api_error…), getParam() (the field at fault, when there is one) and getJsonBody() say what happened; the message is
 * ASVRI's own and fit to log.
 */
class ApiErrorException extends AsvriException
{
    /** @var int */
    protected $httpStatus;
    /** @var string|null */
    protected $errorType;
    /** @var string|null */
    protected $param;
    /** @var array<string, mixed>|null */
    protected $jsonBody;

    public function __construct($message, $httpStatus, $errorType = null, $param = null, $jsonBody = null)
    {
        parent::__construct($message, (int)$httpStatus);
        $this->httpStatus = (int)$httpStatus;
        $this->errorType = $errorType;
        $this->param = $param;
        $this->jsonBody = $jsonBody;
    }

    /** The right subclass for a status: 401 Authentication, 429 RateLimit, 5xx Api, other 4xx InvalidRequest. */
    public static function fromResponse($status, $json, $raw)
    {
        $err = is_array($json) && isset($json['error']) && is_array($json['error']) ? $json['error'] : [];
        $message = isset($err['message']) ? (string)$err['message'] : ("ASVRI answered HTTP $status." . ($raw !== '' && $json === null ? ' ' . substr($raw, 0, 200) : ''));
        $type = isset($err['type']) ? (string)$err['type'] : null;
        $param = isset($err['param']) ? (string)$err['param'] : null;
        if ($status === 401) {
            return new AuthenticationException($message, $status, $type, $param, $json);
        }
        if ($status === 429) {
            return new RateLimitException($message, $status, $type, $param, $json);
        }
        if ($status >= 500) {
            return new ApiException($message, $status, $type, $param, $json);
        }
        return new InvalidRequestException($message, $status, $type, $param, $json);
    }

    public function getHttpStatus()
    {
        return $this->httpStatus;
    }

    public function getErrorType()
    {
        return $this->errorType;
    }

    public function getParam()
    {
        return $this->param;
    }

    public function getJsonBody()
    {
        return $this->jsonBody;
    }
}
