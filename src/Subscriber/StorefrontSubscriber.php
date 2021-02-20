<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Subscriber;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use MoorlCustomerAccounts\Core\Service\CustomerAccountService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class StorefrontSubscriber implements EventSubscriberInterface
{
    /**
     * @var CustomerAccountService
     */
    private $customerAccountService;

    public function __construct(
        CustomerAccountService $customerAccountService
    )
    {
        $this->customerAccountService = $customerAccountService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onCustomerLogin',
            SalesChannelContextResolvedEvent::class => 'onSalesChannelContextResolved',
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced'
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $this->customerAccountService->addCustomerIdToOrder($event->getOrder());
    }

    public function onSalesChannelContextResolved(SalesChannelContextResolvedEvent $event): void
    {
        $customer = $event->getSalesChannelContext()->getCustomer();

        if (!$customer) {
            return;
        }

        $customFields = $customer->getCustomFields();

        if ($customFields && !empty($customFields['moorl_ca_parent_id'])) {
            $customer->addExtension('MoorlCustomerAccounts', new ArrayStruct([
                'customerId' => $customer->getId()
            ]));

            $customer->setId($customFields['moorl_ca_parent_id']);

            $this->customerAccountService->setSalesChannelContext($event->getSalesChannelContext());
        }
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        return;
    }
}
