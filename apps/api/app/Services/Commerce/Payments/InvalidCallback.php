<?php

namespace App\Services\Commerce\Payments;

use RuntimeException;

/** Signature/validation failure on a payment callback (answered with 400, nothing changes). */
class InvalidCallback extends RuntimeException {}
