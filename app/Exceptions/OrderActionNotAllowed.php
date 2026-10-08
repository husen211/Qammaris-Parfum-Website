<?php

namespace App\Exceptions;

/** The actor may not do this to this order (contract `action_not_allowed`). */
class OrderActionNotAllowed extends OnlineOrderRejected {}
