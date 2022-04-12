<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Core\Event;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\MailActionInterface;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;
use Shopware\Core\Checkout\Customer\CustomerEntity;

class InitialPasswordEvent extends Event implements MailActionInterface, MailAware, SalesChannelAware
{
    public const EVENT_NAME = 'moorl_ca_initial_password.send';

    private Context $context;
    private string $salesChannelId;
    private MailRecipientStruct $recipients;
    private CustomerEntity $customer;
    private CustomerEntity $parent;
    private string $password;

    public function __construct(
        Context $context,
        string $salesChannelId,
        MailRecipientStruct $recipients,
        CustomerEntity $customer,
        CustomerEntity $parent,
        string $password
    )
    {
        $this->context = $context;
        $this->salesChannelId = $salesChannelId;
        $this->recipients = $recipients;
        $this->customer = $customer;
        $this->parent = $parent;
        $this->password = $password;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('password', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('customer', new EntityType(CustomerDefinition::class))
            ->add('parent', new EntityType(CustomerDefinition::class));
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return CustomerEntity|null
     */
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

    /**
     * @return CustomerEntity
     */
    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }
}
