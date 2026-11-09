<?php

namespace Danestves\LaravelPolar\Exceptions;

/**
 * Thrown for 422 responses: Polar rejected the payload (FastAPI validation errors).
 */
class ValidationException extends PolarApiError {}
