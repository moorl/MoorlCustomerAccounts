<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Core\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;
use Shopware\Core\Checkout\Customer\CustomerEntity;

class InitialPasswordEvent extends Event implements CustomerAware, MailAware, SalesChannelAware
{
    final public const EVENT_NAME = 'moorl_ca_initial_password.send';

    public function __construct(private readonly Context $context, private readonly string $salesChannelId, private readonly MailRecipientStruct $recipients, private readonly CustomerEntity $customer, private readonly CustomerEntity $parent, private readonly string $password)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('password', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('customer', new EntityType(CustomerDefinition::class))
            ->add('parent', new EntityType(CustomerDefinition::class));
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getParent(): ?CustomerEntity
    {
        return $this->parent;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->recipients;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return string
     */
    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getCustomerId(): string
    {
        return $this->getCustomer()->getId();
    }
}
