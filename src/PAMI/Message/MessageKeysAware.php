<?php
/**
 * @category Pami
 * @package  Message
 * @author   Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @license  http://github.com/ryzhov/PAMI Apache License 2.0
 *
 * Copyright 2016 Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 */

namespace PAMI\Message;

interface MessageKeysAware
{
    static function getMessageKeys();
}
