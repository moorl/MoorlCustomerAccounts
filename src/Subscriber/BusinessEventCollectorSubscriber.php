<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Subscriber;

use MoorlCustomerAccounts\Core\Event\InitialPasswordEvent;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BusinessEventCollectorSubscriber implements EventSubscriberInterface
{
    /**
     * @var BusinessEventCollector
     */
    private $businessEventCollector;

    public function __construct(
        BusinessEventCollector $businessEventCollector
    ) {
        $this->businessEventCollector = $businessEventCollector;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BusinessEventCollectorEvent::NAME => 'onBusinessEventCollectorEvent',
        ];
    }

    public function onBusinessEventCollectorEvent(BusinessEventCollectorEvent $event): void
    {
        $collection = $event->getCollection();
        $collection->add($this->businessEventCollector->define(InitialPasswordEvent::class));
    }
}
