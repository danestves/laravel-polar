<?php

namespace Danestves\LaravelPolar\Exceptions;

/**
 * Thrown for 401/403 responses: the token is missing, invalid, or lacks access.
 */
class AuthenticationException extends PolarApiError {}
