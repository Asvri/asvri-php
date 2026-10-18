<?php

namespace Asvri\Exception;

/** 429: too many requests; slow down and try again. */
class RateLimitException extends ApiErrorException
{
}
