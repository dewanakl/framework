<?php

namespace Core\Events;

use Core\Facades\App;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Kerjakan eventnya.
 *
 * @class Dispatch
 * @package \Core\Events
 */
class Dispatch implements EventDispatcherInterface
{
    /**
     * Listener object.
     *
     * @var ListenerProviderInterface $listenerProvider
     */
    private $listenerProvider;

    /**
     * Init object.
     *
     * @param ListenerProviderInterface $listenerProvider
     * @return void
     */
    public function __construct(ListenerProviderInterface $listenerProvider)
    {
        $this->listenerProvider = $listenerProvider;
    }

    /**
     * Kerjakan.
     *
     * @param object $event
     * @return object
     */
    public function dispatch(object $event): object
    {
        if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
            return $event;
        }

        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            $listener = App::get()->singleton($listener);
            $listener($event);
        }

        return $event;
    }
}
