<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Subscriber;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use MoorlCustomerAccounts\Core\Service\CustomerAccountService;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
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
            CustomerLoginEvent::class => 'onCustomerLogin'
        ];
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $session = new Session();

        $customer = $event->getCustomer();
        $customFields = $customer->getCustomFields();
    }
}
