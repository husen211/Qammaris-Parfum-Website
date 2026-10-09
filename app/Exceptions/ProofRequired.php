<?php

namespace App\Exceptions;

/** Approved/paid reimbursement without attached proof or Owner waiver (contract `proof_required`). */
class ProofRequired extends OnlineOrderRejected {}
