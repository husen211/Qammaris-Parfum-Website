<?php

namespace App\Exceptions;

use RuntimeException;

/** A safe, user-facing reason why an account change was not applied. */
class AdminUserRejected extends RuntimeException {}
