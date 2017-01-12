<?php
/**
 * TCP Client implementation for AMI.
 *
 * @author     Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @link       https://github.com/ryzhov/PAMI
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
use PAMI\Client\Exception\SocketException;
use PAMI\Client\Exception\EofSocketException;
use PAMI\Client\Exception\ReadSocketException;
use PAMI\Client\Exception\WriteSocketException;
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
     * The callbacks queue for asyncronous response.
     * @var \Closure[]
     */
    private $incomingQueue;

    /**
     * Our current received message. May be incomplete, will be completed
     * eventually with an EOM.
     * @var string
     */
    private $currentProcessingMessage;

    public function getSocketUri()
    {
        return sprintf('%s%s:%s', $this->scheme, $this->host, $this->port);
    }

    /**
     * @throws SocketException
     */
    protected function connect()
    {
        $errno = $errstr = null;
        $socketUri = $this->getSocketUri();

        $this->context = stream_context_create();
        
        $this->socket = stream_socket_client(
            $socketUri,
            $errno,
            $errstr,
            $this->cTimeout,
            STREAM_CLIENT_CONNECT,
            $this->context
        );
        
        if ($this->socket === false) {
            throw new SocketException(sprintf('error: "%s" while create socket "%s"', $errstr, $socketUri));
        } else {
            $this->logger->debug(sprintf('ami socket "%s" connected, cTimeout => [%d]', $socketUri, $this->cTimeout));
        }
        
        // set socket in block mode
        if (!stream_set_blocking($this->socket, true)) {
            throw new SocketException(sprintf('error set block mode on "%s" socket', $socketUri));
        } else {
            $this->logger->debug(sprintf('ami socket "%s" set in blocking mode', $socketUri));
        }

        // set read timeout on socket
        if (!stream_set_timeout($this->socket, $this->rTimeout)) {
            throw new SocketException(
                sprintf('socket "%s" timeout "%s" set error', $socketUri, $this->rTimeout)
            );
        } else {
            $this->logger->debug(sprintf('ami socket "%s" set rTimeout => [%d]', $socketUri, $this->rTimeout));
        }
    }

    /**
     * Opens a connection to ami.
     *
     * @throws ClientException
     * @throws ReadSocketException
     * @return void
     */
    public function open()
    {
        $this->connect();
        $socketUri = $this->getSocketUri();

        $asteriskId = stream_get_line($this->socket, 1024, Message::EOL);

        if ($asteriskId === false) {
            throw new ReadSocketException(sprintf('error: "%s" while read socket', socket_strerror(socket_last_error())));
        }
        
        if (strstr($asteriskId, 'Asterisk') === false) {
            throw new ClientException(sprintf('Unknown peer: "%s"', $asteriskId));
        }

        $this->logger->debug(sprintf('recv <-- asteriskId: "%s"', $asteriskId));
        
        $msg = new LoginAction($this->user, $this->pass);

        $this->send($msg, function (ResponseMessage $response) use ($socketUri) {
            if (!$response->isSuccess()) {
                throw new ClientException(
                    sprintf('Could not connect to: "%s", response: "%s"', $socketUri, $response->getMessage())
                );
            }
        });

        $this->currentProcessingMessage = '';
        $this->logger->info(sprintf('login to: "%s" as user: "%s"', $socketUri, $this->user));
    }

    /**
     * Registers the given listener so it can receive events. Returns the generated
     * id for this new listener. You can pass in a an IEventListener, a Closure,
     * and an array containing the object and name of the method to invoke. Can specify
     * an optional predicate to invoke before calling the callback.
     *
     * @param mixed $listener
     * @param \Closure|array|null $predicate
     *
     * @throws ClientException
     * @return string
     */
    public function registerEventListener($listener, $predicate = null)
    {
        if (is_array($predicate) && !is_callable($predicate)) {
            $events = $predicate;
            $predicate = function(IncomingMessage $event) use ($events) {
                return count($events) ? in_array(get_class($event), $events) : true;
            };
        } elseif (null !== $predicate && !is_callable($predicate)) {
            throw new ClientException(sprintf(
                'Event listener: "%s" predicate must be callable type, this "%s" given',
                get_class($listener),
                gettype($predicate)
            ));
        }
        
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
     * @return \string[]
     */
    protected function readMessages()
    {
        $msgs = [];
        
        // Read something.
        if (false === $read = fread($this->socket, 2048)) {
            return false;
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
     * 
     * @throws ReadSocketException
     */
    public function process()
    {
        $msgs = $this->readMessages();
 
        if ($msgs === false) {
            throw new ReadSocketException(sprintf('error read socket: "%s"', $this->getSocketUri()));
        }
       
        foreach ($msgs as $aMsg) {
            $resPos = strpos($aMsg, 'Response:');
            $evePos = strpos($aMsg, 'Event:');

            if (($resPos !== false) && (($resPos < $evePos) || $evePos === false)) {
                
                $response = $this->messageToResponse($aMsg);
                
                $this->logger->debug(
                    sprintf('recv <-- class: "%s": "%s"', get_class($response), $response)
                );
                $actionId = $response->getActionId();
               
                if (isset($this->incomingQueue[$actionId])) {
                    $callback = $this->incomingQueue[$actionId];
                    if (is_callable($callback)) {
                        unset($this->incomingQueue[$actionId]);
                        $callback($response);
                    } else {
                        $this->logger->warning(sprintf('callback: "%s" not callable', gettype($callback)));
                    }
                } else {
                    $this->logger->warning(sprintf('actionid: "%s" not found in queue', $actionId));
                }
            } elseif ($evePos !== false) {
                
                $event = $this->messageToEvent($aMsg);
                
                $this->logger->debug(
                    sprintf('recv <-- class: "%s": "%s"', get_class($event), $event)
                );
                
                $this->dispatch($event);
                
            } else {
                // broken ami.. sending a response with events without
                // Event and ActionId
                
                $this->logger->error(
                    sprintf('broken recv <-- raw: "%s"', $aMsg)
                );
            }
        }
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
        if (null === $response->getActionId()) {
            throw new ClientException(sprintf('response "%s" without actionId', $response));
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
        return $this->eventFactory->createFromRaw($msg);
    }

    /**
     * @param string $string String for write to socket
     * @return boolean true on success, false otherwise
     */
    protected function socket_write($string)
    {
        for ($written = 0; $written < strlen($string); $written += $write) {
            $write = fwrite($this->socket, substr($string, $written));
            if ($write === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Sends a message to ami asyncronously.
     *
     * @param \PAMI\Message\OutgoingMessage $message Message to send.
     * @param \Closure $p Callback executed when correspond response received
     *
     * @throws EofSocketException
     * @throws WriteSocketException
     * @return void
     */
    public function send(OutgoingMessage $message, \Closure $p)
    {
        $actionId = $message->getActionId();
        
        if (null === $actionId) {
            $actionId = bin2hex(random_bytes(8));
            $message->setActionId($actionId);
            
            $this->logger->debug(
                sprintf('set actionId: "%s" on message of class "%s"', $actionId, get_class($message))
            );
        }

        $this->logger->debug(sprintf('send --> class: "%s": "%s"', get_class($message), $message));
        
        $messageToSend = $message->serialize();

        $this->incomingQueue[$actionId] = $p;
        
        if (feof($this->socket)) {
            // -- socket error or closed --
            throw new EofSocketException(sprintf(
                'socket "%s" feof=true, last error "%s"',
                $this->getSocketUri(),
                socket_strerror(socket_last_error())
            ));
        }
            
        if (!$this->socket_write($messageToSend)) {
            // -- data not writen on socket --
            throw new WriteSocketException(sprintf('error "%s" while socket write', socket_strerror(socket_last_error())));
        }

        //$this->process();
    }

    /**
     * Closes the connection to ami.
     *
     * @return void
     */
    public function close()
    {
        $this->logger->info(sprintf('Closing "%s" AMI connection.', $this->getSocketUri()));
        stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
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
     * Gets the socket.
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
    }
}
