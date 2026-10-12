<?php

namespace App\Exceptions;

use RuntimeException;

/** A safe, user-facing reason why an order change was not applied. */
class OnlineOrderRejected extends RuntimeException {}
