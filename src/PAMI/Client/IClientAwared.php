<?php
/**
 * Interface for awared of ami client.
 *
 * @category Pami
 * @package  Client
 * @author   Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 *
 */
namespace PAMI\Client;

interface IClientAwared
{

    /**
     * Sets the client implementation.
     *
     * @param IClient $client
     *
     * @return $this
     */
    public function setClient(IClient $client);
}
