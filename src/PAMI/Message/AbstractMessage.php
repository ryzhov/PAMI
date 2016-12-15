<?php
/**
 * A abstract ami message, in-or-outbound.
 *
 * PHP Version 5
 *
 * @category Pami
 * @package  Message
 * @author   Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @license  http://github.com/ryzhov/PAMI Apache License 2.0
 *
 * Copyright 2016 Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 */
namespace PAMI\Message;
use PAMI\Exception\PAMIException;

abstract class AbstractMessage extends Message implements MessageKeysAware
{

    public function __call($name, $args)
    {
        if (!preg_match('/^(set|get)(\w+)$/', $name, $matches)) {
            throw new PAMIException(sprintf('unexpected method name "%s"', $name));
        }
        
        if (!in_array($key = strtolower($matches[2]), static::getMessageKeys())) {
            throw new PAMIException(sprintf('unsupported key "%s" for message class "%s" in "%s" method call',
                $key,
                get_class($this),
                $name
            ));
        }

        if ($matches[1] === 'set') {
            $this->setKey($key, $args[0]);
        } elseif ($matches[1] === 'get') {
            return $this->getKey($key);
        } else {
            throw new PAMIException(sprintf('unexpected method name "%s"', $matches[1]));
        }

        return $this;
    }

}
