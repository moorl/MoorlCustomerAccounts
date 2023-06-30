<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Core\Content;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Struct\Struct;

class CustomerAccountStruct extends Struct
{
    protected ?CustomerEntity $parent = null;
    protected ?CustomerCollection $children = null;
    protected ?bool $orderCopy = false;
    protected ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

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