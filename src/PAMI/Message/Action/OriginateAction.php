<?php
/**
 * Originate action message.
 *
 * @author     Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @link       https://github.com/ryzhov/PAMI
 * 
 */

namespace PAMI\Message\Action;

class OriginateAction extends ActionMessage
{
    public static function getMessageKeys()
    {
        return array_merge(
            parent::getMessageKeys(),
            [
                'exten', 'context', 'priority', 'application', 'data', 'timeout',
                'callerid', 'account', 'async', 'codecs', 'channel'
            ]
        );
    }
}
