<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Subscriber;

use MoorlCustomerAccounts\Core\Service\CustomerAccountService;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\MailTemplate\Event\MailSendSubscriberBridgeEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailSendSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly CustomerAccountService $customerAccountService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailSendSubscriberBridgeEvent::class => 'onMailSendSubscriberBridge',
            FlowSendMailActionEvent::class => 'onFlowSendMailActionEvent',
        ];
    }

    public function onFlowSendMailActionEvent(FlowSendMailActionEvent $event): void
    {
        $orderFlows = $this->customerAccountService->getOrderFlows();
        foreach ($orderFlows as $orderFlow) {
            if ($event->getFlowEvent()->getEvent()->getName() === $orderFlow->getEventName()) {
                try {
                    $eventName = str_replace(".", "_", $orderFlow->getEventName());
                    $customer = $event->getFlowEvent()->getEvent()->getOrder()->getOrderCustomer()->getCustomer();
                    $emailAddresses = $customer->getCustomFields()['moorl_ca_email'][$eventName];
                    if (empty($emailAddresses)) {
                        return;
                    }
                } catch (\Exception) {
                    return;
                }

                $emailAddresses = explode(";", (string) $emailAddresses);
                $emailAddresses = array_map('trim', $emailAddresses);

                $recipients = [];
                foreach ($emailAddresses as $emailAddress) {
                    $recipients[$emailAddress] = $emailAddress;
                }

                $event->getDataBag()->set('recipients', $recipients);
            }
        }
    }

    public function onMailSendSubscriberBridge(MailSendSubscriberBridgeEvent $event): void
    {
        $orderFlows = $this->customerAccountService->getOrderBusinessEvents();

        foreach ($orderFlows as $orderFlow) {
            if ($event->getBusinessEvent()->getEvent()->getName() === $orderFlow->getEventName()) {
                try {
                    $eventName = str_replace(".", "_", (string) $orderFlow->getEventName());
                    $customer = $event->getBusinessEvent()->getEvent()->getOrder()->getOrderCustomer()->getCustomer();
                    $emailAddresses = $customer->getCustomFields()['moorl_ca_email'][$eventName];
                    if (empty($emailAddresses)) {
                        return;
                    }
                } catch (\Exception) {
                    return;
                }

                $emailAddresses = explode(";", (string) $emailAddresses);
                $emailAddresses = array_map('trim', $emailAddresses);

                $recipients = [];
                foreach ($emailAddresses as $emailAddress) {
                    $recipients[$emailAddress] = $emailAddress;
                }

                $event->getDataBag()->set('recipients', $recipients);
            }
        }
    }
}
