<?php

namespace Asvri\Exception;

/** A webhook whose Asvri-Signature doesn't match your endpoint's secret, or is too old: answer it with 400 and ignore it. */
class SignatureVerificationException extends AsvriException
{
}
