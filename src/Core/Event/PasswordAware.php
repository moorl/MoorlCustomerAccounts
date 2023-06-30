<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Core\Event;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface PasswordAware extends FlowEventAware
{
    public const PASSWORD = 'password';

    public function getPassword(): string;
}
