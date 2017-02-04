<?php
/**
 * Implement this interface in your own classes to make them event listeners.
 *
 * @package  Listener
 * @author   Marcelo Gornstein <marcelog@gmail.com>
 * @link     https://github.com/ryzhov/PAMI
 */
namespace PAMI\Listener;

use PAMI\Message\Event\EventMessage;

interface IEventListener
{
    /**
     * @param \PAMI\Message\Event\EventMessage $event The received event.
     * @return void
     */
    public function handle(EventMessage $event);
}
