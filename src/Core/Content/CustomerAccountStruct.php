<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Core\Content;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Struct\Struct;

class CustomerAccountStruct extends Struct
{
    /**
     * @var CustomerEntity|null
     */
    protected $parent;
    /**
     * @var CustomerCollection|null
     */
    protected $children;
    /**
     * @var bool|null
     */
    protected $orderCopy;

    public function getOrderCopy(): ?bool
    {
        return $this->orderCopy;
    }

    public function setOrderCopy(?bool $orderCopy): void
    {
        $this->orderCopy = $orderCopy;
    }

    public function getParent(): ?CustomerEntity
    {
        return $this->parent;
    }

    public function setParent(?CustomerEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?CustomerCollection
    {
        return $this->children;
    }

    public function setChildren(?CustomerCollection $children): void
    {
        $this->children = $children;
    }
}