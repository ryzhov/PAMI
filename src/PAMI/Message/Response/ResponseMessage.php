<?php
/**
 * A generic response message from ami.
 *
 * @package    Message
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @link       https://github.com/ryzhov/PAMI
 */
namespace PAMI\Message\Response;

use PAMI\Message\IncomingMessage;
use PAMI\Message\Event\EventMessage;

class ResponseMessage extends IncomingMessage
{
    /**
     * Child events.
     * @var EventMessage[]
     */
    private $events;

    /**
     * Is this response completed? (with all its events).
     * @var boolean
     */
    private $completed;

    /**
     * Serialize function.
     *
     * @return string[]
     */
    public function __sleep()
    {
        $ret = parent::__sleep();
        $ret[] = 'completed';
        $ret[] = 'events';
        return $ret;
    }

    /**
     * True if this response is complete. A response is considered complete
     * if it's not a list OR it's a list with its last child event containing
     * an EventList = Complete.
     *
     * @return boolean
     */
    public function isComplete()
    {
        return $this->completed;
    }

    /**
     * Adds an event to this response.
     *
     * @param EventMessage $event Child event to add.
     *
     * @return void
     */
    public function addEvent(EventMessage $event)
    {
        $this->events[] = $event;
        if (stristr($event->getEventList(), 'complete') !== false
            || stristr($event->getName(), 'complete') !== false
            || stristr($event->getName(), 'DBGetResponse') !== false
        ) {
            $this->completed = true;
        }
    }

    /**
     * Returns all associated events for this response.
     *
     * @return EventMessage[]
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Checks if the Response field has the word Error in it.
     *
     * @return boolean
     */
    public function isSuccess()
    {
        return stristr($this->getKey('response'), 'Error') === false;
    }

    /**
     * Returns true if this response contains the key EventList with the
     * word 'start' in it. Another way is to have a Message key, like:
     * Message: Result will follow
     *
     * @return boolean
     */
    public function isList()
    {
        return
            stristr($this->getKey('eventlist'), 'start') !== false
            || stristr($this->getMessage(), 'follow') !== false
        ;
    }

    /**
     * Returns key: 'Privilege'.
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->getKey('message');
    }

    /**
     * Constructor.
     *
     * @param string $rawContent Literal message as received from ami.
     *
     * @return void
     */
    public function __construct($rawContent)
    {
        parent::__construct($rawContent);
        $this->events = array();
        $this->eventsCount = 0;
        $this->completed = !$this->isList();
    }
}
