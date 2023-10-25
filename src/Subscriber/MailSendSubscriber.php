<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Subscriber;

use MoorlCustomerAccounts\Core\Service\CustomerAccountService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\OrderAware;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MailSendSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly CustomerAccountService $customerAccountService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FlowSendMailActionEvent::class => 'onFlowSendMailActionEvent',
        ];
    }

    public function onFlowSendMailActionEvent(FlowSendMailActionEvent $event): void
    {
        $orderFlows = $this->customerAccountService->getOrderFlows();

        foreach ($orderFlows as $orderFlow) {
            if ($event instanceof OrderAware) {
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
            } elseif ($event->getStorableFlow()->getData(OrderAware::ORDER)) {
                if ($event->getStorableFlow()->getName() === $orderFlow->getEventName()) {
                    try {
                        $eventName = str_replace(".", "_", $orderFlow->getEventName());
                        /** @var CustomerEntity $order */
                        $customer = $event->getStorableFlow()->getData(CustomerAware::CUSTOMER);
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
}
