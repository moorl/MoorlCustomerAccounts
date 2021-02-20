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
     * @return CustomerEntity|null
     */
    public function getParent(): ?CustomerEntity
    {
        return $this->parent;
    }

    /**
     * @param CustomerEntity|null $parent
     */
    public function setParent(?CustomerEntity $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return CustomerCollection|null
     */
    public function getChildren(): ?CustomerCollection
    {
        return $this->children;
    }

    /**
     * @param CustomerCollection|null $children
     */
    public function setChildren(?CustomerCollection $children): void
    {
        $this->children = $children;
    }
}