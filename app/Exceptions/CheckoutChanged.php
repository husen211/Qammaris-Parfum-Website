<?php

namespace App\Exceptions;

/** ORD-04: the cart no longer matches the catalog (price, availability or product); the customer must review again. */
class CheckoutChanged extends OnlineOrderRejected {}
