<?php

namespace Asvri\Exception;

/** 400, 404, 409: something in the request (getParam() names the field), or a session that can't do that now. */
class InvalidRequestException extends ApiErrorException
{
}
