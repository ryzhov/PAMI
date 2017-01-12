<?php
/**
 * Response from an async originate.
 *
 * PHP Version 5
 */

namespace PAMI\Message\Event;

use PAMI\Message\Event\EventMessage;

class OriginateResponseEvent extends EventMessage
{
    /**
     * Returns key: 'privilege'.
     *
     * @return string
     */
    public function getPrivilege()
    {
        return $this->getKey('privilege');
    }

    /**
     * Returns key: 'exten'.
     *
     * @return string
     */
    public function getExten()
    {
        return $this->getKey('exten');
    }

    /**
     * Returns key: 'context'.
     *
     * @return string
     */
    public function getContext()
    {
        return $this->getKey('context');
    }

    /**
     * Returns key: 'channel'.
     *
     * @return string
     */
    public function getChannel()
    {
        return $this->getKey('channel');
    }

    /**
     * Returns key: 'reason'.
     *
     * @return string
     */
    public function getReason()
    {
        return $this->getKey('reason');
    }

    /**
     * Returns key: 'uniqueid'.
     *
     * @return string
     */
    public function getUniqueId()
    {
        return $this->getKey('uniqueid');
    }

    /**
     * Returns key: 'response'.
     *
     * @return string
     */
    public function getResponse()
    {
        return $this->getKey('response');
    }

    /**
     * Returns key: 'calleridnum'.
     *
     * @return string
     */
    public function getCallerIdNum()
    {
        return $this->getKey('calleridnum');
    }

    /**
     * Returns key: 'calleridname'.
     *
     * @return string
     */
    public function getCallerIdName()
    {
        return $this->getKey('calleridname');
    }
}
