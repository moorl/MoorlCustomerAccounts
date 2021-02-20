<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Core\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\MailActionInterface;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;
use Shopware\Core\Checkout\Customer\CustomerEntity;

final class InitialPasswordEvent extends Event implements MailActionInterface, SalesChannelAware
{
    public const EVENT_NAME = 'moorl_ca_initial_password.send';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $salesChannelId;

    /**
     * @var MailRecipientStruct
     */
    private $recipients;

    /**
     * @var CustomerEntity|null
     */
    private $customer;

    /**
     * @var CustomerEntity|null
     */
    private $parent;

    public function __construct(
        Context $context,
        string $salesChannelId,
        MailRecipientStruct $recipients,
        CustomerEntity $customer,
        CustomerEntity $parent
    )
    {
        $this->context = $context;
        $this->salesChannelId = $salesChannelId;
        $this->recipients = $recipients;
        $this->customer = $customer;
        $this->parent = $parent;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('customer', new ObjectType())
            ->add('parent', new ObjectType());
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
