<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Core\Event;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface ParentAware extends FlowEventAware
{
    public const PARENT = 'parent';

    public function getParent(): ?CustomerEntity;
}
