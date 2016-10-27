<?php
/**
 * TCP Client implementation for AMI.
 *
 * @author     Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @link       https://github.com/ryzhov/PAMI
 * 
 */

declare(ticks=1);

namespace PAMI\Client\Impl;

use PAMI\Message\OutgoingMessage;
use PAMI\Message\Message;
use PAMI\Message\IncomingMessage;
use PAMI\Message\Action\LoginAction;
use PAMI\Message\Response\ResponseMessage;
use PAMI\Message\Event\Factory\Impl\EventFactoryImpl;
use PAMI\Listener\IEventListener;
use PAMI\Client\Exception\ClientException;
use PAMI\Client\IClient;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;

class ClientImpl implements IClient, LoggerAwareInterface
{
    /**
     * PSR-3 logger.
     * @var Logger
     */
    protected $logger;

    /**
     * Hostname
     * @var string
     */
    private $host;

    /**
     * TCP Port.
     * @var integer
     */
    private $port;

    /**
     * Username
     * @var string
     */
    private $user;

    /**
     * Password
     * @var string
     */
    private $pass;

    /**
     * Connection timeout, in seconds.
     * @var integer
     */
    private $cTimeout;

    /**
     * Connection scheme, like tcp:// or tls://
     * @var string
     */
    private $scheme;

    /**
     * Event factory.
     * @var EventFactoryImpl
     */
    private $eventFactory;

    /**
     * R/W timeout, in milliseconds.
     * @var integer
     */
    private $rTimeout;

    /**
     * Our stream socket resource.
     * @var resource
     */
    private $socket;

    /**
     * Our stream context resource.
     * @var resource
     */
    private $context;

    /**
     * Our event listeners
     * @var IEventListener[]
     */
    private $eventListeners;

    /**
     * The receiving queue.
     * @var IncomingMessage[]
     */
    private $incomingQueue;

    /**
     * Our current received message. May be incomplete, will be completed
     * eventually with an EOM.
     * @var string
     */
    private $currentProcessingMessage;

    /**
     * This should not happen. Asterisk may send responses without a
     * corresponding ActionId.
     * @var string
     */
    private $lastActionId;


    private function getSocketUri()
    {
        return sprintf('%s%s:%s', $this->scheme, $this->host, $this->port);
    }

    /**
     * Opens a tcp connection to ami.
     *
     * @throws \PAMI\Client\Exception\ClientException
     * @return void
     */
    public function open()
    {
        $this->context = stream_context_create();
        $errno = 0;
        $errstr = '';
        
        $this->socket = @stream_socket_client(
            $this->getSocketUri(),
            $errno,
            $errstr,
            $this->cTimeout,
            STREAM_CLIENT_CONNECT,
            $this->context
        );
        
        if ($this->socket === false) {
            throw new ClientException(sprintf('Error: "%s" while connecting to: "%s"', $errstr, $this->getSocketUri()));
        }
        
        $msg = new LoginAction($this->user, $this->pass);
        $asteriskId = @stream_get_line($this->socket, 1024, Message::EOL);
        if (strstr($asteriskId, 'Asterisk') === false) {
            throw new ClientException(
                "Unknown peer. Is this an ami?: $asteriskId"
            );
        }

        $response = $this->send($msg);
        if (!$response->isSuccess()) {
            throw new ClientException(
                'Could not connect: ' . $response->getMessage()
            );
        }
        
        @stream_set_blocking($this->socket, 0);
        
        $this->currentProcessingMessage = '';
        $this->logger->debug(sprintf('Login to: "%s" by user: "%s"', $this->getSocketUri(), $this->user));
    }

    /**
     * Registers the given listener so it can receive events. Returns the generated
     * id for this new listener. You can pass in a an IEventListener, a Closure,
     * and an array containing the object and name of the method to invoke. Can specify
     * an optional predicate to invoke before calling the callback.
     *
     * @param mixed $listener
     * @param \Closure|null $predicate
     *
     * @return string
     */
    public function registerEventListener($listener, $predicate = null)
    {
        $listenerId = uniqid('PamiListener');
        $this->eventListeners[$listenerId] = array($listener, $predicate);
        return $listenerId;
    }

    /**
     * Unregisters an event listener.
     *
     * @param string $listenerId The id returned by registerEventListener.
     *
     * @return void
     */
    public function unregisterEventListener($listenerId)
    {
        if (isset($this->eventListeners[$listenerId])) {
            unset($this->eventListeners[$listenerId]);
        }
    }

    /**
     * Reads a complete message over the stream until EOM.
     *
     * @throws ClientException
     * @return \string[]
     */
    protected function getMessages()
    {
        $msgs = array();
        // Read something.
        $read = @fread($this->socket, 65535);
        
        if ($read === false) {
            throw new ClientException(sprintf('Error fread socket: "%s"', $this->getSocketUri()));
        }

        if (@feof($this->socket)) {
            $message = sprintf('EOF on socket: "%s"', $this->getSocketUri());
            $this->logger->warning($message);
            throw new ClientException($message);
        }
        
        $this->currentProcessingMessage .= $read;
        // If we have a complete message, then return it. Save the rest for
        // later.
        while (($marker = strpos($this->currentProcessingMessage, Message::EOM))) {
            $msg = substr($this->currentProcessingMessage, 0, $marker);
            $this->currentProcessingMessage = substr(
                $this->currentProcessingMessage,
                $marker + strlen(Message::EOM)
            );
            $msgs[] = $msg;
        }
        return $msgs;
    }

    /**
     * Main processing loop. Also called from send(), you should call this in
     * your own application in order to continue reading events and responses
     * from ami.
     */
    public function process()
    {
        $msgs = $this->getMessages();
        
        foreach ($msgs as $aMsg) {
            $resPos = strpos($aMsg, 'Response:');
            $evePos = strpos($aMsg, 'Event:');

            if (($resPos !== false) && (($resPos < $evePos) || $evePos === false)) {
                
                $response = $this->messageToResponse($aMsg);
                
                $this->logger->debug(
                    sprintf('recv <-- class: "%s": "%s"', get_class($response), $response)
                );
                
                $this->incomingQueue[$response->getActionId()] = $response;
                
            } elseif ($evePos !== false) {
                
                $event = $this->messageToEvent($aMsg);
                
                $this->logger->debug(
                    sprintf('recv <-- class: "%s": "%s"', get_class($event), $event)
                );

                $response = $this->findResponse($event);
                if ($response === false || $response->isComplete()) {
                    $this->dispatch($event);
                } else {
                    $response->addEvent($event);
                }
                
            } else {
                // broken ami.. sending a response with events without
                // Event and ActionId
                $bMsg = 'Event: ResponseEvent' . "\r\n";
                $bMsg .= 'ActionId: ' . $this->lastActionId . "\r\n" . $aMsg;
                $event = $this->messageToEvent($bMsg);
                
                $this->logger->error(
                    sprintf('broken recv <-- raw: "%s"', $aMsg)
                );
                
                $response = $this->findResponse($event);
                $response->addEvent($event);
            }
        }
    }

    /**
     * Tries to find an associated response for the given message.
     *
     * @param IncomingMessage $message Message sent by asterisk.
     *
     * @return \PAMI\Message\Response\ResponseMessage
     */
    protected function findResponse(IncomingMessage $message)
    {
        $actionId = $message->getActionId();
        if (isset($this->incomingQueue[$actionId])) {
            return $this->incomingQueue[$actionId];
        }
        return false;
    }

    /**
     * Dispatchs the incoming message to a handler.
     *
     * @param \PAMI\Message\IncomingMessage $message Message to dispatch.
     *
     * @return void
     */
    protected function dispatch(IncomingMessage $message)
    {
        foreach ($this->eventListeners as $data) {
            $listener = $data[0];
            $predicate = $data[1];
            if (is_callable($predicate) && !call_user_func($predicate, $message)) {
                continue;
            }
            if ($listener instanceof \Closure) {
                $listener($message);
            } elseif (is_array($listener)) {
                $listener[0]->{$listener[1]}($message);
            } else {
                $listener->handle($message);
            }
        }
    }

    /**
     * Returns a ResponseMessage from a raw string that came from asterisk.
     *
     * @param string $msg Raw string.
     *
     * @return \PAMI\Message\Response\ResponseMessage
     */
    private function messageToResponse($msg)
    {
        $response = new ResponseMessage($msg);
        $actionId = $response->getActionId();
        if (is_null($actionId)) {
            $actionId = $this->lastActionId;
            $response->setActionId($this->lastActionId);
        }
        return $response;
    }

    /**
     * Returns a EventMessage from a raw string that came from asterisk.
     *
     * @param string $msg Raw string.
     *
     * @return \PAMI\Message\Event\EventMessage
     */
    private function messageToEvent($msg)
    {
        $event = $this->eventFactory->createFromRaw($msg);


        return $event;
    }

    /**
     * Returns a message (response) related to the given message. This uses
     * the ActionID tag (key).
     *
     * @todo not suitable for multithreaded applications.
     *
     * @return \PAMI\Message\IncomingMessage
     */
    protected function getRelated(OutgoingMessage $message)
    {
        $ret = false;
        $id = $message->getActionID('ActionID');
        if (isset($this->incomingQueue[$id])) {
            $response = $this->incomingQueue[$id];
            if ($response->isComplete()) {
                unset($this->incomingQueue[$id]);
                $ret = $response;
            }
        }
        return $ret;
    }

    /**
     * Sends a message to ami.
     *
     * @param \PAMI\Message\OutgoingMessage $message Message to send.
     *
     * @see ClientImpl::send()
     * @throws \PAMI\Client\Exception\ClientException
     * @return \PAMI\Message\Response\ResponseMessage
     */
    public function send(OutgoingMessage $message)
    {
        $this->logger->debug(sprintf('send --> class: "%s": "%s"', get_class($message), $message));
        
        $messageToSend = $message->serialize();
        $length = strlen($messageToSend);
        
        $this->lastActionId = $message->getActionId();
        
        if (@fwrite($this->socket, $messageToSend) < $length) {
            throw new ClientException('Could not send message');
        }
        
        $read = 0;
        
        while ($read <= $this->rTimeout) {
            $this->process();
            $response = $this->getRelated($message);
            if ($response != false) {
                $this->lastActionId = false;
                return $response;
            }
            usleep(1000); // 1ms delay
            if ($this->rTimeout > 0) {
                $read++;
            }
        }
        throw new ClientException('Read timeout');
    }

    /**
     * Closes the connection to ami.
     *
     * @return void
     */
    public function close()
    {
        $this->logger->debug('Closing connection to asterisk.');
        @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
    }

    /**
     * Sets the logger implementation.
     *
     * @param Psr\Log\LoggerInterface $logger The PSR3-Logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Gets the sockekt.
     *
     * @return resource
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Constructor.
     *
     * @param string[] $options Options for ami client.
     *
     */
    public function __construct(array $options)
    {
        $this->logger = new NullLogger;
        $this->host = $options['host'];
        $this->port = intval($options['port']);
        $this->user = $options['username'];
        $this->pass = $options['secret'];
        $this->cTimeout = $options['connect_timeout'];
        $this->rTimeout = $options['read_timeout'];
        $this->scheme = isset($options['scheme']) ? $options['scheme'] : 'tcp://';
        $this->eventFactory = new EventFactoryImpl();
        $this->incomingQueue = [];
        $this->eventListeners = [];
        $this->lastActionId = false;
    }
}
