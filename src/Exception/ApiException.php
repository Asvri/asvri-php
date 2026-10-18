<?php

namespace Asvri\Exception;

/** 5xx: a problem on ASVRI's side (or the gateway's, for a refund); safe to try again later. */
class ApiException extends ApiErrorException
{
}
