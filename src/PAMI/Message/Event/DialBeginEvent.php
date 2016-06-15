<?php
/**
 * @category   Pami
 * @package    Message
 * @subpackage Event
 * @author   Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @license  http://github.com/ryzhov/PAMI Apache License 2.0
 *
 * Copyright 2016 Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 */
namespace PAMI\Message\Event;

use PAMI\Message\Event\EventMessage;

class DialBeginEvent extends EventMessage
{
    protected function getMessageKeys()
    {
        return
            [
                'privilege',
                'channel',
                'calleridnum',
                'accountcode',
                'uniqueid',
                'destchannel',
                'destuniqueid',
                'dialstring'
            ]
        ;
    }
}
