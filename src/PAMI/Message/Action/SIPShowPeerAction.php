<?php
/**
 * Sipshowpeer action message.
 *
 * @author     Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @link       https://github.com/ryzhov/PAMI
 * 
 */

namespace PAMI\Message\Action;

class SIPShowPeerAction extends ActionMessage
{
    public static function getMessageKeys()
    {
        return array_merge(parent::getMessageKeys(), ['peer']);
    }
}
