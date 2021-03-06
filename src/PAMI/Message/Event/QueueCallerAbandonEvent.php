<?php
/**
 * @category   Pami
 * @package    Message
 * @subpackage Event
 * @author   Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @license  http://github.com/ryzhov/PAMI Apache License 2.0
 *
 * Copyright 2017 Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 */
namespace PAMI\Message\Event;

use PAMI\Message\Event\EventMessage;

class QueueCallerAbandonEvent extends EventMessage
{
    public static function getMessageKeys()
    {
        return array_merge(
            parent::getMessageKeys(),
            [
                'privilege',
                'channel',
                'channelstate',
                'channelstatedesc',
                'calleridnum',
                'calleridname',
                'connectedlinenum',
                'connectedlinename',
                'language',
                'accountcode',
                'context',
                'exten',
                'priority',
                'uniqueid',
                'queue',
                'position',
                'holdtime',
                'originalposition'
            ]
        );
    }
}
