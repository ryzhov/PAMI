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

use PAMI\Exception\PAMIException;
use PAMI\Message\OutgoingMessage;

abstract class ActionMessage extends OutgoingMessage
{
    public static function getMessageKeys()
    {
        return ['action', 'actionid'];
    }

    /**
     * Constructor.
     *
     * @param string $action Action command.
     *
     * @return void
     */
    public function __construct($actionId = null)
    {
        parent::__construct();

        $action = (new \ReflectionClass(get_class($this)))->getShortName();

        if (preg_match('/([\S]+)Action$/', $action, $matches)) {
            $this->setAction($matches[1]);
        } else {
            throw new \RuntimeException(sprintf('AMI Action invalid class: "%s"', get_class($this)));
        }
        
        $this->setActionID(isset($actionId) ? $actionId : microtime(true));
    }

    /**
     * Sets Action ID.
     *
     * The ActionID can be at most 69 characters long, according to
     * {@link https://issues.asterisk.org/jira/browse/14847 Asterisk Issue 14847}.
     *
     * Therefore we'll throw an exception when the ActionID is too long.
     *
     * @param $actionID The Action ID to have this action known by
     *
     * @return void
     * @throws PAMIException When the ActionID is more then 69 characters long
     */
    public function setActionID($actionID)
    {
        if (0 == strlen($actionID)) {
            throw new PAMIException('ActionID cannot be empty.');
        }

        if (strlen($actionID) > 69) {
            throw new PAMIException('ActionID can be at most 69 characters long.');
        }

        $this->setKey('ActionID', $actionID);
    }
}
