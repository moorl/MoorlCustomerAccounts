<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Subscriber;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use MoorlCustomerAccounts\Core\Content\CustomerAccountStruct;
use MoorlCustomerAccounts\Core\Service\CustomerAccountService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
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

        $salesChannelContext = $this->customerAccountService->getSalesChannelContext();
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
        $customer = $event->getSalesChannelContext()->getCustomer();

        if (!$customer) {
            return;
        }

        $this->customerAccountService->setSalesChannelContext($event->getSalesChannelContext());

        $customerAccount = new CustomerAccountStruct();
        $customFields = $customer->getCustomFields();

        if ($customFields && !empty($customFields['moorl_ca_parent_id'])) {
            $customerAccount->setParent($this->customerAccountService->getCustomer($customFields['moorl_ca_parent_id'], false));
            $customerAccount->setOrderCopy(!empty($customFields['moorl_ca_order_copy']));
            $customer->setId($customFields['moorl_ca_parent_id']);
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
