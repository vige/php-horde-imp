<?php
/**
 * Copyright 2013 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2013 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * The configuration for a IMP backend.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 *
 * @property boolean $acl  Enable ACLs?
 * @property array $admin  Admin configuration.
 * @property array $autocreate_special  Autocreate the special mailboxes?
 * @property-read array $cache_params  The cache configuration.
 * @property mixed $cache  The cache configuration from backends.php.
 * @property array $capability_ignore  The list of capabilities to ignore.
 * @property string $comparator  The non-default comparator to use.
 * @property string $debug  The debug handler.
 * @property boolean $debug_raw  Do raw debugging?
 * @property mixed $hordeauth  Type of horde authentication to use.
 * @property string $hostspec  Hostspec of the backend.
 * @property array $id  List of ID information to send via the ID command.
 * @property-read integer $import_limit  The maximum number of messages
 *                                       allowed to be imported.
 * @property-read array $innocent_params  Innocent reporting parameters.
 * @property array $lang  The list of langauges used to display messages on
 *                        the IMAP server.
 * @property string $maildomain  The maildomain to use for outgoing mail.
 * @property string $name  Label for the backend.
 * @property array $namespace  Namespace overrides.
 * @property string $port  Port number of the backend.
 * @property-write mixed $preferred  The preferred server(s).
 * @property-read array $preferred  The preferred servers list.
 * @property string $protocol  Either 'imap' or 'pop'.
 * @property array $quota  Quota paramters.
 * @property mixed $secure  The security layer to use.
 * @property array $smtp  SMTP configuration.
 * @property boolean $sort_force  Force sorting on the web server?
 * @property array $spam  Spam configuration.
 * @property-read array $spam_params  Spam reporting parameters.
 * @property array $special_mboxes  List of special mailboxes overrides.
 * @property-read array $user_special_mboxes  List of user special mailboxes.
 * @propert string $thread  The preferred thread sort algorithm.
 * @property string $timeout  The connection timeout (in seconds).
 */
class IMP_Imap_Config implements Serializable
{
    /* Passwords session storage key. */
    const PASSWORDS_KEY = 'imap_config_pass';

    /**
     * Array options.
     *
     * @var array
     */
    private $_aoptions = array(
        'admin', 'cache_params', 'capability_ignore', 'id', 'lang',
        'namespace', 'preferred', 'quota', 'smtp', 'spam', 'special_mboxes'
    );

    /**
     * Boolean options.
     *
     * @var array
     */
    private $_boptions = array(
        'acl', 'autocreate_special', 'debug_raw', 'sort_force'
    );

    /**
     * Config data.
     *
     * @var array
     */
    private $_config = array();

    /**
     * Mixed options.
     *
     * @var array
     */
    private $_moptions = array(
        'cache', 'hordeauth', 'secure'
    );

    /**
     * Passwords.
     *
     * @var array
     */
    private $_passwords = array();

    /**
     * Password storage options (must be an array option).
     *
     * @var array
     */
    private $_poptions = array(
        'admin', 'quota'
    );

    /**
     * String options.
     *
     * @var array
     */
    private $_soptions = array(
        'comparator', 'debug', 'hostspec', 'import_limit', 'maildomain',
        'name', 'port', 'protocol', 'thread', 'timeout'
    );

    /**
     * Constructor.
     *
     * @param array $c  Config array (from backends.php).
     */
    public function __construct(array $c)
    {
        foreach ($c as $k => $v) {
            $this->$k = $v;
        }
    }

    /**
     */
    public function __set($name, $value)
    {
        if (in_array($name, $this->_poptions) && isset($value['password'])) {
            $this->_passwords[$name] = $value['password'];
            unset($value['password']);
        }

        /* Normalize values. */
        switch ($name) {
        case 'preferred':
            if (!is_array($value)) {
                $value = array($value);
            }
            break;

        case 'protocol':
            $value = (strcasecmp($value, 'pop') === 0)
                ? 'pop'
                : 'imap';
            break;
        }

        if (in_array($name, $this->_aoptions) ||
            in_array($name, $this->_moptions)) {
            /* Array and/or mixed options. */
            $this->_config[$name] = $value;
        } elseif (in_array($name, $this->_boptions)) {
            /* Boolean options. */
            $this->_config[$name] = (bool)$value;
        } elseif (in_array($name, $this->_soptions)) {
            /* String options. */
            $this->_config[$name] = strval($value);
        }
    }

    /**
     */
    public function __get($name)
    {
        global $injector, $prefs;

        if (in_array($name, $this->_aoptions)) {
            /* Array options. */
            $out = isset($this->_config[$name])
                ? $this->_config[$name]
                : array();

            if (isset($this->_passwords[$name])) {
                $out['password'] = $this->_passwords[$name];
            }
        } elseif (in_array($name, $this->_boptions)) {
            /* Boolean options. */
            $out = !empty($this->_config[$name]);
        } elseif (in_array($name, $this->_soptions) ||
                  in_array($name, $this->_moptions)) {
            /* Mixed and/or string options. */
            $out = isset($this->_config[$name])
                ? $this->_config[$name]
                : null;
        } else {
            $out = null;
        }

        switch ($name) {
        case 'autocreate_special':
            $out = ($out && $injector->getInstance('IMP_Imap')->access(IMP_Imap::ACCESS_FOLDERS));
            break;

        case 'cache_params':
            $ob = null;
            if ($c = $this->cache) {
                if ($c instanceof Horde_Imap_Client_Cache_Backend) {
                    $ob = $c;
                } elseif (strcasecmp($c, 'nosql') === 0) {
                    $db = $injector->getInstance('Horde_Nosql_Adapter');
                    if ($db instanceof Horde_Mongo_Client) {
                        $ob = new Horde_Imap_Client_Cache_Backend_Mongo(array(
                            'mongo_db' => $db
                        ));
                    } else {
                        Horde::log(sprintf('IMAP client package does not support %s as a cache driver.', get_class($db)), 'ERR');
                    }
                } elseif (strcasecmp($c, 'sql') === 0) {
                    $ob = new Horde_Imap_Client_Cache_Backend_Db(array(
                        'db' => $injector->getInstance('Horde_Db_Adapter')
                    ));
                } else {
                    /* TODO: For IMP 6.x BC, treat everything else as the
                     * 'cache' option. */
                    $ob = new Horde_Imap_Client_Cache_Backend_Cache(array(
                        'cacheob' => $injector->getInstance('Horde_Cache')
                    ));
                }
            }

            if (is_null($ob)) {
                $ob = new Horde_Imap_Client_Cache_Backend_Cache(array(
                    'cacheob' => new Horde_Cache(new Horde_Cache_Storage_Mock(), array(
                        'compress' => true
                    ))
                ));
            }

            $out = array(
                'backend' => $ob,
            );
            break;

        case 'import_limit':
            $out = is_null($out)
                ? 2500
                : intval($out);
            break;

        case 'innocent_params':
            $p = $this->spam;
            $out = isset($p['innocent'])
                ? $p['innocent']
                : array();
            break;

        case 'maildomain':
            if ($md = $prefs->getValue('mail_domain')) {
                $out = $md;
            }
            break;

        case 'smtp':
            if (empty($out['horde_auth'])) {
                if (!isset($out['username'])) {
                    $out['username'] = $injector->getInstance('IMP_Imap')->getParam('username');
                }
                if (!isset($out['password'])) {
                    $out['password'] = $injector->getInstance('IMP_Imap')->getParam('password');
                }
            }
            break;

        case 'spam_params':
            $p = $this->spam;
            $out = isset($p['spam'])
                ? $p['spam']
                : array();
            break;

        case 'thread':
            if (is_null($out)) {
                $out = 'REFERENCES';
            }
            break;

        case 'user_special_mboxes':
            $out = (isset($out[IMP_Mailbox::MBOX_USERSPECIAL]) && is_array($out[IMP_Mailbox::MBOX_USERSPECIAL]))
                ? $out[IMP_Mailbox::MBOX_USERSPECIAL]
                : array();
            break;
        }

        return $out;
    }

    /**
     */
    public function __isset($name)
    {
        $tmp = $this->$name;
        return !empty($tmp);
    }

    /**
     */
    public function __unset($name)
    {
        unset($this->_config[$name]);
    }

    /* Serializable methods. */

    /**
     */
    public function serialize()
    {
        global $session;

        if (!empty($this->_passwords)) {
            $session->set('imp', self::PASSWORDS_KEY, $this->_passwords, $session::ENCRYPT);
        }

        return json_encode(array_filter($this->_config));
    }

    /**
     */
    public function unserialize($data)
    {
        global $session;

        $this->_config = json_decode($data, true);

        $this->_passwords = $session->get('imp', self::PASSWORDS_KEY, $session::TYPE_ARRAY);
    }

}
