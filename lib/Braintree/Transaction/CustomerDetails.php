<?php

namespace Braintree\Transaction;

use Braintree\Instance;

/**
 * Customer details from a transaction
 * Creates an instance of customer details as returned from a transaction
 *
 * @package    Braintree
 * @subpackage Transaction
 *
 * @property-read string $company
 * @property-read string $email
 * @property-read string $fax
 * @property-read string $firstName
 * @property-read string $id
 * @property-read string $lastName
 * @property-read string $phone
 * @property-read string $website
 */
class CustomerDetails extends Instance
{
}
