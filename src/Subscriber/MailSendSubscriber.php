<?php

namespace MoorlCustomerAccounts\Subscriber;

use MoorlCustomerAccounts\Core\Service\CustomerAccountService;
use Shopware\Core\Content\MailTemplate\Event\MailSendSubscriberBridgeEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailSendSubscriber implements EventSubscriberInterface
{
    private CustomerAccountService $customerAccountService;

    public function __construct(
        CustomerAccountService $customerAccountService
    )
    {
        $this->customerAccountService = $customerAccountService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailSendSubscriberBridgeEvent::class => 'onMailSendSubscriberBridge'
        ];
    }

    public function onMailSendSubscriberBridge(MailSendSubscriberBridgeEvent $event): void
    {
        $orderBusinessEvents = $this->customerAccountService->getOrderBusinessEvents();

        foreach ($orderBusinessEvents as $orderBusinessEvent) {
            if ($event->getBusinessEvent()->getEvent()->getName() === $orderBusinessEvent->getEventName()) {
                try {
                    $eventName = str_replace(".", "_", $orderBusinessEvent->getEventName());
                    $customer = $event->getBusinessEvent()->getEvent()->getOrder()->getOrderCustomer()->getCustomer();
                    $emailAddresses = $customer->getCustomFields()['moorl_ca_email'][$eventName];
                    if (empty($emailAddresses)) {
                        return;
                    }
                } catch (\Exception $exception) {
                    return;
                }

                $emailAddresses = explode(";", $emailAddresses);
                $emailAddresses = array_map('trim', $emailAddresses);

                $recipients = [];
                foreach ($emailAddresses as $emailAddress) {
                    $recipients[$emailAddress] = $emailAddress;
                }

                $event->getDataBag()->set('recipients',$recipients);
            }
        }
    }
}
