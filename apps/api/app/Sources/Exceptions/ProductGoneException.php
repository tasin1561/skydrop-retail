<?php

namespace App\Sources\Exceptions;

use RuntimeException;

/** The marketplace says the product page no longer exists. Not a provider failure. */
class ProductGoneException extends RuntimeException {}
