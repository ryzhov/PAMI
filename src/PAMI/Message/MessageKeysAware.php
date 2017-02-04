<?php
/**
 * @package  Message
 * @author   Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @link     https://github.com/ryzhov/PAMI
 */

namespace PAMI\Message;

interface MessageKeysAware
{
    static function getMessageKeys();
}
