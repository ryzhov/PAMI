<?php
/**
 * GetVar action message.
 *
 * @category   Pami
 * @author     Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 */

namespace PAMI\Message\Action;

class GetVarAction extends ActionMessage
{
    public static function getMessageKeys()
    {
        return array_merge(parent::getMessageKeys(), ['channel', 'variable']);
    }
}
