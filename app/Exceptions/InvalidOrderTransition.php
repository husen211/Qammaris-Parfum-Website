<?php

namespace App\Exceptions;

/** The requested step does not follow from the current state (contract `invalid_transition`). */
class InvalidOrderTransition extends OnlineOrderRejected {}
