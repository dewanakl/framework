<?php

namespace Core\Events;

use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Pendengar event.
 *
 * @class Listener
 * @package \Core\Events
 */
class Listener implements ListenerProviderInterface
{
    /**
     * Listeners from Event Service Provider.
     *
     * @var array $listeners
     */
    private $listeners = [];

    /**
     * Init object.
     *
     * @param array<string, array<int, string>> $listeners
     * @return void
     */
    public function __construct(array $listeners)
    {
        $this->listeners = $listeners;
    }

    /**
     * @inheritDoc
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventType = get_class($event);
        if (array_key_exists($eventType, $this->listeners)) {
            return $this->listeners[$eventType];
        }

        return [];
    }

    /**
     * Clear event by type.
     *
     * @param string $eventType
     * @return void
     */
    public function clearListeners(string $eventType): void
    {
        if (array_key_exists($eventType, $this->listeners)) {
            unset($this->listeners[$eventType]);
        }
    }
}
