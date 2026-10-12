<?php

namespace App\Exceptions;

/** The App expense is already linked to another order (contract `expense_already_linked`). */
class ExpenseAlreadyLinked extends OnlineOrderRejected {}
