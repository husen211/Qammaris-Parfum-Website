<?php

namespace App\Exceptions;

/** Another actor holds this task (contract `task_already_claimed`, precedence over `revision_conflict`). */
class TaskAlreadyClaimed extends OnlineOrderRejected
{
    public function __construct(public readonly string $task, public readonly string $holderAppUserId, public readonly string $holderName, public readonly string $claimedAt)
    {
        parent::__construct("Tugas {$task} sedang dipegang {$holderName}.");
    }
}
