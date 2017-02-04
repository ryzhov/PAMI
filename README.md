
# Introduction

This is the fork of https://github.com/marcelog/PAMI

PAMI means PHP Asterisk Manager Interface. As its name suggests its just a
set of php classes that will let you issue commands to an ami and/or receive
events, using an observer-listener pattern.

# Installing

Add this library to your [Composer](https://packagist.org/) configuration. In
composer.json:
```json
  "require": {
    "ryzhov/pami": "dev-master"
  }
```

# QuickStart

```php
// Make sure you include the composer autoload.
require __DIR__ . '/vendor/autoload.php';

$options = array(
    'host' => '2.3.4.5',
    'scheme' => 'tcp://',
    'port' => 9999,
    'username' => 'asd',
    'secret' => 'asd',
    'connect_timeout' => 10,
    'read_timeout' => 10
);
$client = new \PAMI\Client\Impl\ClientImpl($options);

// Registering a closure
$client->registerEventListener(function ($event) {
});

// Register a specific method of an object for event listening
$client->registerEventListener(array($listener, 'handle'));

// Register an IEventListener:
$client->registerEventListener($listener);
```

# Using Predicates
A second (optional) argument can be used when registering the event listener: a
closure that will be evaluated before calling the callback. The callback will
be called only if this predicate returns true:

```php
use PAMI\Message\Event\DialEvent;

$client->registerEventListener(
    array($listener, 'handleDialStart'),
    function ($event) {
        return $event instanceof DialEvent && $event->getSubEvent() == 'Begin';
    })
);
```

## Debugging, logging

You can optionally set a [PSR-3](http://www.php-fig.org/psr/psr-3/) compatible logger:
```php
$pami->setLogger($logger);
```

By default, the client will use the [NullLogger](http://www.php-fig.org/psr/psr-3/#1-4-helper-classes-and-interfaces).

# Thanks To

* Marcelo Gornstein <marcelog@gmail.com>

* Jason Blank <rumpled at github> for helping in the debugging of the queue
functionality and some other ami inconsistencies.

* Francesco Usseglio Gaudi, for help in debugging the Originate action.

* Matías Barletta, for the vgms support.

* Eli Hunter, for helping in bringing in tls compatibility.

* Freddy dafredmail at googlemail, for his help and testing environment to add
dongle support.

* Joshua Elson for his help in trying and debugging in loaded asterisk servers.

* Jacob Kiers for his help in bringing in and testing async agi functionality,
and CEL event
support.

* Richard Baar for noticing the lack of eof support when reading from socket,
the JabberEvent, and the ScreenName in JabberAction.

* Scot Opell for helping in debugging stream_get_line() in 5.3.9 and 5.3.10

* Brian (wormling) for trying and fixing bugs on asyncagi

* Henning Bragge for helping with newstate event and queues.

* mbonneau for ParkedCall and UnParkedCall events.
