<?php
/**
 * A generic ami message, in-or-outbound.
 *
 * @author     Aleksandr N. Ryzhov <a.n.ryzhov@gmail.com>
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @link       https://github.com/ryzhov/PAMI
 *
 */
namespace PAMI\Message;

abstract class Message
{
    /**
     * End Of Line means this token.
     * @var string
     */
    const EOL = "\r\n";

    /**
     * End Of Message means this token.
     * @var string
     */
    const EOM = "\r\n\r\n";

    /**
     * Metadata. Message variables (key/value).
     * @var string[]
     */
    protected $variables;

    /**
     * Metadata. Message "keys" i.e: Action: login
     * @var string[]
     */
    protected $keys;

    /**
     * Serialize function.
     *
     * @return string[]
     */
    public function __sleep()
    {
        return ['variables', 'keys'];
    }

    public function __toString()
    {
        return $this->serialize();
    }

    /**
     * Returns key: 'ActionId'.
     *
     * @return string
     */
    public function getActionId()
    {
        return $this->getKey('actionid');
    }

    /**
     * Sets Action Id.
     *
     * The ActionId can be at most 69 characters long, according to
     * {@link https://issues.asterisk.org/jira/browse/14847 Asterisk Issue 14847}.
     *
     * Therefore we'll throw an exception when the ActionId is too long.
     *
     * @param $actionId The Action Id to have this action known by
     *
     * @return void
     * @throws PAMIException When the ActionId is more then 69 characters long
     */
    public function setActionId($actionId)
    {
        if (0 == strlen($actionId)) {
            throw new PAMIException('ActionId cannot be empty.');
        }

        if (strlen($actionId) > 69) {
            throw new PAMIException('ActionId can be at most 69 characters long.');
        }

        $this->setKey('actionid', $actionId);
    }

    /**
     * Adds a variable to this message.
     *
     * @param string $key   Variable name.
     * @param string $value Variable value.
     *
     * @return Message
     */
    public function setVariable($key, $value)
    {
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * Returns a variable by name.
     *
     * @param string $key Variable name.
     *
     * @return string|null
     */
    public function getVariable($key)
    {
        return isset($this->variables[$key]) ? $this->variables[$key] : null;
    }

    /**
     * Adds a variable to this message.
     *
     * @param string $key   Key name (i.e: Action).
     * @param string $value Key value.
     *
     * @return Message
     */
    public function setKey($key, $value)
    {
        $this->keys[$key] = (string)$value;
        return $this;
    }

    /**
     * Returns a key by name.
     *
     * @param string $key Key name (i.e: Action).
     *
     * @return string|null
     */
    public function getKey($key)
    {
        return isset($this->keys[$key]) ? (string)$this->keys[$key] : null;
    }

    /**
     * Returns all keys for this message.
     *
     * @return string[]
     */
    public function getKeys()
    {
        return $this->keys;
    }

    /**
     * Returns all variabels for this message.
     *
     * @return string[]
     */
    public function getVariables()
    {
        return $this->variables;
    }

    /**
     * Returns the end of message token appended to the end of a given message.
     *
     * @return string
     */
    protected function finishMessage($message)
    {
        return $message . self::EOL . self::EOL;
    }

    /**
     * Returns the string representation for an ami action variable.
     *
     * @param string $key
     * @param string $value
     *
     * @return string
     */
    private function serializeVariable($key, $value)
    {
        return "Variable: $key=$value";
    }

    /**
     * Gives a string representation for this message, ready to be sent to
     * ami.
     *
     * @return string
     */
    public function serialize()
    {
        $result = array();
        foreach ($this->getKeys() as $k => $v) {
            $result[] = $k . ': ' . $v;
        }
        foreach ($this->getVariables() as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $singleValue) {
                    $result[] = $this->serializeVariable($k, $singleValue);
                }
            } else {
                $result[] = $this->serializeVariable($k, $v);
            }
        }
        
        $mStr = $this->finishMessage(implode(self::EOL, $result));
        return $mStr;
    }

    /**
     * Gives a array representation of this message 
     * @return array
     */
    public function toArray()
    {
        $result = [];
        
        if (count($this->getVariables())) {
            $result['vars'] = [];
        }

        foreach ($this->keys as $key => $value) {
            $result[$key] = $value;
        }
        
        foreach ($this->variables as $key => $value) {
            $result['vars'][$key] = $value;
        }

        return $result;
    }

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->variables = [];
        $this->keys = [];
    }
}
