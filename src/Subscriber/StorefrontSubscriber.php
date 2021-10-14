<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Subscriber;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use MoorlCustomerAccounts\Core\Content\CustomerAccountStruct;
use MoorlCustomerAccounts\Core\Service\CustomerAccountService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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
        $salesChannelContext = $this->customerAccountService->getSalesChannelContext();
        if (!$salesChannelContext) {
            return;
        }

        $this->customerAccountService->addCustomerIdToOrder($event->getOrder());

        $customer = $salesChannelContext->getCustomer();

        /* @var $customerAccount CustomerAccountStruct */
        $customerAccount = $customer->getExtension('CustomerAccount');

        if ($customerAccount && $customerAccount->getOrderCopy()) {
            $email = $customerAccount->getParent()->getEmail();

            $recipents = $event->getMailStruct()->getRecipients();
            $recipents[$email] = $email;
            $event->getMailStruct()->setRecipients($recipents);
            $event->getMailStruct()->setCc($email); // Not working for the moment
        }
    }

    public function onSalesChannelContextResolved(SalesChannelContextResolvedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $customer = $salesChannelContext->getCustomer();

        if (!$customer) {
            return;
        }

        $this->customerAccountService->setSalesChannelContext($event->getSalesChannelContext());

        $customerAccount = new CustomerAccountStruct();
        $customFields = $customer->getCustomFields();

        if ($customFields && !empty($customFields['moorl_ca_parent_id'])) {
            $parent = $this->customerAccountService->getCustomer($customFields['moorl_ca_parent_id'], false);

            $this->customerAccountService->syncCustomer($customer, $parent);

            $customerAccount->setParent($parent);
            $customerAccount->setOrderCopy(!empty($customFields['moorl_ca_order_copy']));
            $customer->setId($parent->getId());
        } else {
            $customerAccount->setChildren($this->customerAccountService->getCustomers());
        }

        $customer->addExtension('CustomerAccount', $customerAccount);
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        // TODO: If parent removed, throw error
    }
}
