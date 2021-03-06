<?php
/**
 * Interface for an ami client.
 *
 * @package  Client
 * @author   Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @author   Marcelo Gornstein <marcelog@gmail.com>
 * @link     https://github.com/ryzhov/PAMI
 */
namespace PAMI\Client;

use PAMI\Message\OutgoingMessage;
use Psr\Log\LoggerInterface;

/**
 * Interface for an ami client.
 *
 * PHP Version 5
 *
 * @category Pami
 * @package  Client
 * @author   Marcelo Gornstein <marcelog@gmail.com>
 * @license  http://marcelog.github.com/PAMI/ Apache License 2.0
 * @version  SVN: $Id$
 * @link     http://marcelog.github.com/PAMI/
 */
interface IClient
{

    /**
     * Gets the socket.
     *
     * @return resource
     */
    public function getSocket();

    /**
     * Gets the socket URI.
     *
     * @return string socket URI
     */
    public function getSocketUri();


    /**
     * Opens a tcp connection to ami.
     *
     * @throws \PAMI\Client\Exception\ClientException
     * @return void
     */
    public function open();

    /**
     * Main processing loop. Also called from send(), you should call this in
     * your own application in order to continue reading events and responses
     * from ami.
     *
     * @return void
     */
    public function process();

    /**
     * Registers the given listener so it can receive events. Returns the generated
     * id for this new listener. You can pass in a an IEventListener, a Closure,
     * and an array containing the object and name of the method to invoke. Can specify
     * an optional predicate to invoke before calling the callback.
     *
     * @param mixed $listener
     * @param Closure|null $predicate
     *
     * @return string
     */
    public function registerEventListener($listener, $predicate = null);

    /**
     * Unregisters an event listener.
     *
     * @param string $listenerId The id returned by registerEventListener.
     *
     * @return void
     */
    public function unregisterEventListener($listenerId);

    /**
     * Closes the connection to ami.
     *
     * @return void
     */
    public function close();

    /**
     * Sends a message to ami asyncronously.
     *
     * @param OutgoingMessage $message Message to send.
     * @param \Closure $callback Callback executed when correspond response received
     *
     * @throws \PAMI\Client\Exception\ClientException
     * @return void
     */
    public function send(OutgoingMessage $message, \Closure $callback);

    /**
     * Sets the logger implementation.
     *
     * @param Psr\Log\LoggerInterface $logger The PSR3-Logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger);
}
