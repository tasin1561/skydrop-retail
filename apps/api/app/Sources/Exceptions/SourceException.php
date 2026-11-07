<?php

namespace App\Sources\Exceptions;

use RuntimeException;

/** The provider failed (network, 5xx, blocked, unparseable). Counts towards the circuit breaker. */
class SourceException extends RuntimeException {}
