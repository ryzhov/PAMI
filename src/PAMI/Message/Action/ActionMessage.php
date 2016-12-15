<?php
/**
 * A generic action ami message.
 *
 * @author     Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @link       https://github.com/ryzhov/PAMI
 * 
 */

namespace PAMI\Message\Action;

use PAMI\Message\OutgoingMessage;

abstract class ActionMessage extends OutgoingMessage
{
    public static function getMessageKeys()
    {
        return ['action'];
    }

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $action = (new \ReflectionClass(get_class($this)))->getShortName();

        if (preg_match('/([\S]+)Action$/', $action, $matches)) {
            $this->setAction($matches[1]);
        } else {
            throw new \RuntimeException(sprintf('AMI Action invalid class: "%s"', get_class($this)));
        }
        
        $this->setActionId(bin2hex(random_bytes(8)));
    }
}
