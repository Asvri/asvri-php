<?php

namespace Asvri\Exception;

/** 401: the secret key is wrong, expired, or a publishable key. */
class AuthenticationException extends ApiErrorException
{
}
