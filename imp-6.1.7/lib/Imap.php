<?php
/**
 * Copyright 2008-2013 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2008-2013 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Provides common functions for interaction with IMAP/POP3 servers via the
 * Horde_Imap_Client package.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2008-2013 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 *
 * @property-read boolean $changed  If true, this object has changed.
 * @property-read IMP_Imap_Config $config  Backend config settings.
 * @property-read boolean $init  Has the base IMAP object been initialized?
 * @property-read integer $max_compose_recipients  The maximum number of
 *                                                 recipients to send to per
 *                                                 compose message.
 * @property-read integer $max_compose_timelimit  The maximum number of
 *                                                recipients to send to in the
 *                                                configured timelimit.
 * @property-read integer $max_create_mboxes  The maximum number of mailboxes
 *                                            a user can create.
 * @property-read string $server_key  Server key used to login.
 * @property-read string $thread_algo  The threading algorithm to use.
 */
class IMP_Imap implements Serializable
{
    /* Access constants. */
    const ACCESS_FOLDERS = 1;
    const ACCESS_SEARCH = 2;
    const ACCESS_FLAGS = 3;
    const ACCESS_UNSEEN = 4;
    const ACCESS_TRASH = 5;
    const ACCESS_CREATEMBOX = 6;
    const ACCESS_CREATEMBOX_MAX = 7;
    const ACCESS_COMPOSE_RECIPIENTS = 8;
    const ACCESS_COMPOSE_TIMELIMIT = 9;
    const ACCESS_ACL = 10;
    const ACCESS_DRAFTS = 11;

    /**
     * Cached backend configuration.
     *
     * @var array
     */
    static protected $_backends = array();

    /**
     * Has this object changed?
     *
     * @var boolean
     */
    protected $_changed = false;

    /**
     * Backend config.
     *
     * @var IMP_Imap_Config
     */
    protected $_config;

    /**
     * The Horde_Imap_Client object.
     *
     * @var Horde_Imap_Client
     */
    protected $_ob = null;

    /**
     * Temporary data cache (destroyed at end of request).
     *
     * @var array
     */
    protected $_temp = array();

    /**
     */
    public function __get($key)
    {
        switch ($key) {
        case 'changed':
            return $this->_changed || ($this->_ob && $this->_ob->changed);

        case 'config':
            return isset($this->_config)
                ? $this->_config
                : new Horde_Support_Stub();

        case 'init':
            return !is_null($this->_ob);

        case 'max_compose_recipients':
        case 'max_compose_timelimit':
            $perm = $GLOBALS['injector']->getInstance('Horde_Perms')->getPermissions('imp:' . str_replace('max_compose', 'max', $key), $GLOBALS['registry']->getAuth());
            return intval($perm[0]);

        case 'max_create_mboxes':
            $perm = $GLOBALS['injector']->getInstance('Horde_Perms')->getPermissions('imp:' . $this->_getPerm($key), $GLOBALS['registry']->getAuth());
            return intval($perm[0]);

        case 'server_key':
            return $this->init
                ? $this->_ob->getParam('imp:backend')
                : null;

        case 'thread_algo':
            if (!$this->init) {
                return 'ORDEREDSUBJECT';
            }

            if ($thread = $this->_ob->getParam('imp:thread_algo')) {
                return $thread;
            }

            $thread = $this->config->thread;
            $thread_cap = $this->queryCapability('THREAD');
            if (!in_array($thread, is_array($thread_cap) ? $thread_cap : array())) {
                $thread = 'ORDEREDSUBJECT';
            }

            $this->_ob->setParam('imp:thread_algo', $thread);
            $this->_changed = true;

            return $thread;
        }
    }

    /**
     * Get the full permission name for a permission.
     *
     * @param string $perm  The permission.
     *
     * @return string  The full (backend-specific) permission name.
     */
    private function _getPerm($perm)
    {
        return 'backends:' . ($this->init ? $this->server_key . ':' : '') . $perm;
    }

    /**
     * Returns the IMAP Client object.
     *
     * @param IMP_Mailbox $mbox  Get the IMAP client for a given mailbox. If
     *                           null, returns the IMAP client for the login
     *                           backend.
     *
     * @return Horde_Imap_Client_Base  An IMAP Client object.
     */
    public function getOb($mbox = null)
    {
        return $this->_ob;
    }

    /**
     * Determine if this is a connection to an IMAP server.
     *
     * @param IMP_Mailbox $mbox  Specifically check this mailbox. Otherwise,
     *                           checks the base IMAP objecct.
     *
     * @return boolean  True if connected to IMAP server, false if connected
     *                  to a POP3 server.
     */
    public function isImap($mbox = null)
    {
        return !$this->_ob || ($this->_ob instanceof Horde_Imap_Client_Socket);
    }

    /**
     * Is sorting available for a mailbox?
     *
     * @param IMP_Mailbox $mbox  The mailbox to query.
     *
     * @return boolean  True if sorting is available.
     */
    public function canSort(IMP_Mailbox $mbox)
    {
        return ($this->config->sort_force ||
                $this->getOb($mbox)->queryCapability('SORT'));
    }

    /**
     * Create a new Horde_Imap_Client object.
     *
     * @param string $username  The username to authenticate with.
     * @param string $password  The password to authenticate with.
     * @param string $key       Create a new object using this server key.
     *
     * @return Horde_Imap_Client_Base  Client object.
     * @throws IMP_Imap_Exception
     */
    public function createImapObject($username, $password, $key)
    {
        if (!is_null($this->_ob)) {
            return $this->_ob;
        }

        if (($config = $this->loadServerConfig($key)) === false) {
            $error = new IMP_Imap_Exception('Could not load server configuration.');
            Horde::log($error);
            throw $error;
        }

        $imap_config = array(
            'cache' => $config->cache_params,
            'capability_ignore' => $config->capability_ignore,
            'comparator' => $config->comparator,
            'debug' => $config->debug,
            'debug_literal' => $config->debug_raw,
            'hostspec' => $config->hostspec,
            'id' => $config->id,
            'lang' => $config->lang,
            'password' => new IMP_Imap_Password($password),
            'port' => $config->port,
            'secure' => (($secure = $config->secure) ? $secure : false),
            'timeout' => $config->timeout,
            'username' => $username,
            // IMP specific config
            'imp:backend' => $key
            // 'imp:login' - Set in __call()
            // 'imp:nsdefault' - Set in defaultNamespace()
        );

        try {
            $ob = ($config->protocol == 'imap')
                ? new Horde_Imap_Client_Socket($imap_config)
                : new Horde_Imap_Client_Socket_Pop3($imap_config);
        } catch (Horde_Imap_Client_Exception $e) {
            $error = new IMP_Imap_Exception($e);
            Horde::log($error);
            throw $error;
        }

        $this->_config = $config;
        $this->_ob = $ob;

        return $ob;
    }

    /**
     * Perform post-login tasks.
     */
    public function doPostLoginTasks()
    {
        global $prefs;

        switch ($this->_config->protocol) {
        case 'imap':
            /* Overwrite default special mailbox names. */
            foreach ($this->_config->special_mboxes as $key => $val) {
                if ($key != IMP_Mailbox::MBOX_USERSPECIAL) {
                    $prefs->setValue($key, $val, array(
                        'force' => true,
                        'nosave' => true
                    ));
                }
            }
            break;

        case 'pop':
            /* Turn some options off if we are working with POP3. */
            foreach (array('newmail_notify', 'save_sent_mail') as $val) {
                $prefs->setValue($val, false, array(
                    'force' => true,
                    'nosave' => true
                ));
                $prefs->setLocked($val, true);
            }
            $prefs->setLocked(IMP_Mailbox::MBOX_DRAFTS, true);
            $prefs->setLocked(IMP_Mailbox::MBOX_SENT, true);
            $prefs->setLocked(IMP_Mailbox::MBOX_SPAM, true);
            $prefs->setLocked(IMP_Mailbox::MBOX_TEMPLATES, true);
            $prefs->setLocked(IMP_Mailbox::MBOX_TRASH, true);
            break;
        }
        $this->updateFetchIgnore();
    }

    /**
     * Update the list of mailboxes to ignore when caching FETCH data in the
     * IMAP client object.
     */
    public function updateFetchIgnore()
    {
        if ($this->isImap()) {
            $special = IMP_Mailbox::getSpecialMailboxes();
            $cache = $this->_ob->getParam('cache');
            $cache['fetch_ignore'] = array_filter(array(
                strval($special[IMP_Mailbox::SPECIAL_SPAM]),
                strval($special[IMP_Mailbox::SPECIAL_TRASH])
            ));
            $this->_ob->setParam('cache', $cache);
        }
    }

    /**
     * Checks access rights for a server.
     *
     * @param integer $right  Access right.
     *
     * @return boolean  Does the access right exist?
     */
    public function access($right)
    {
        global $injector;

        if (!$this->_ob) {
            return false;
        }

        switch ($right) {
        case self::ACCESS_ACL:
            return ($this->config->acl && $this->queryCapability('ACL'));

        case self::ACCESS_CREATEMBOX:
            return ($this->isImap() &&
                    $injector->getInstance('Horde_Core_Perms')->hasAppPermission($this->_getPerm('create_mboxes')));

        case self::ACCESS_CREATEMBOX_MAX:
            return ($this->isImap() &&
                    $injector->getInstance('Horde_Core_Perms')->hasAppPermission($this->_getPerm('max_create_mboxes')));

        case self::ACCESS_DRAFTS:
        case self::ACCESS_FLAGS:
        case self::ACCESS_SEARCH:
        case self::ACCESS_UNSEEN:
            return $this->isImap();

        case self::ACCESS_FOLDERS:
        case self::ACCESS_TRASH:
            return ($this->isImap() &&
                    $injector->getInstance('Horde_Core_Perms')->hasAppPermission($this->_getPerm('allow_folders')));
        }

        return false;
    }

    /**
     * Checks compose access rights for a server.
     *
     * @param integer $right        Access right.
     * @param integer $email_count  The number of e-mail recipients.
     *
     * @return boolean  Is the access allowed?
     */
    public function accessCompose($right, $email_count)
    {
        switch ($right) {
        case self::ACCESS_COMPOSE_RECIPIENTS:
        case self::ACCESS_COMPOSE_TIMELIMIT:
            return $GLOBALS['injector']->getInstance('Horde_Core_Perms')->hasAppPermission(
                ($right == self::ACCESS_COMPOSE_RECIPIENTS) ? 'max_recipients' : 'max_timelimit',
                array(
                    'opts' => array(
                        'value' => $email_count
                    )
                )
            );
        }

        return false;
    }

    /**
     * Get the namespace list.
     *
     * @return array  See Horde_Imap_Client_Base#getNamespaces().
     */
    public function getNamespaceList()
    {
        try {
            $ns = $this->config->namespace;
            return $this->getNamespaces(is_null($ns) ? array() : $ns);
        } catch (Horde_Imap_Client_Exception $e) {
            return array();
        }
    }

    /**
     * Get namespace info for a full mailbox path.
     *
     * @param string $mailbox    The mailbox path.
     * @param boolean $personal  If true, will return empty namespace only
     *                           if it is a personal namespace.
     *
     * @return mixed  The namespace info for the mailbox path or null if the
     *                path doesn't exist.
     */
    public function getNamespace($mailbox = null, $personal = false)
    {
        if (!$this->isImap($mailbox)) {
            return null;
        }

        $ns = $this->getNamespaceList();

        if (is_null($mailbox)) {
            reset($ns);
            $mailbox = key($ns);
        }

        foreach ($ns as $key => $val) {
            $mbox = $mailbox . $val['delimiter'];
            if (strlen($key) && (strpos($mbox, $key) === 0)) {
                return $val;
            }
        }

        return (isset($ns['']) && (!$personal || ($val['type'] == Horde_Imap_Client::NS_PERSONAL)))
            ? $ns['']
            : null;
    }

    /**
     * Get the default personal namespace.
     *
     * @return mixed  The default personal namespace info.
     */
    public function defaultNamespace()
    {
        if (!$this->_ob ||
            !$this->isImap() ||
            !$this->_ob->getParam('imp:login')) {
            return null;
        }

        if (is_null($ns = $this->_ob->getParam('imp:nsdefault'))) {
            foreach ($this->getNamespaceList() as $val) {
                if ($val['type'] == Horde_Imap_Client::NS_PERSONAL) {
                    $this->_ob->setParam('imp:nsdefault', $val);
                    $this->_changed = true;
                    return $val;
                }
            }
        }

        return $ns;
    }

    /**
     * Return the cache ID for this mailbox.
     *
     * @param string $mailbox  The mailbox name (UTF-8).
     * @param array $addl      Local IMP metadata to add to the cache ID.
     *
     * @return string  The cache ID.
     */
    public function getCacheId($mailbox, array $addl = array())
    {
        return $this->getSyncToken($mailbox) .
            (empty($addl) ? '' : ('|' . implode('|', $addl)));
    }

    /**
     * Parses the cache ID for this mailbox.
     *
     * @param string $id  Cache ID generated by getCacheId().
     *
     * @return array  Two element array:
     *   - date: (integer) Date information (day of year), if embedded in
     *           cache ID.
     *   - token: (string) Mailbox sync token.
     */
    public function parseCacheId($id)
    {
        $out = array('date' => null);

        if ((($pos = strrpos($id, '|')) !== false) &&
            (substr($id, $pos + 1, 1) == 'D')) {
            $out['date'] = substr($id, $pos + 2);
        }

        $out['token'] = (($pos = strpos($id, '|')) === false)
            ? $id
            : substr($id, 0, $pos);

        return $out;
    }

    /**
     * All other calls to this class are routed to the underlying
     * Horde_Imap_Client_Base object.
     *
     * @param string $method  Method name.
     * @param array $params   Method Parameters.
     *
     * @return mixed  The return from the requested method.
     * @throws BadMethodCallException
     * @throws IMP_Imap_Exception
     */
    public function __call($method, $params)
    {
        if (!$this->_ob) {
            /* Fallback for these methods. */
            switch ($method) {
            case 'getIdsOb':
                $ob = new Horde_Imap_Client_Ids();
                call_user_func_array(array($ob, 'add'), $params);
                return $ob;
            }

            throw new Horde_Exception_AuthenticationFailure('IMP is marked as authenticated, but no credentials can be found in the session.', Horde_Auth::REASON_SESSION);
        }

        if (!method_exists($this->_ob, $method)) {
            throw new BadMethodCallException(sprintf('%s: Invalid method call "%s".', __CLASS__, $method));
        }

        switch ($method) {
        case 'append':
        case 'createMailbox':
        case 'deleteMailbox':
        case 'expunge':
        case 'fetch':
        case 'getACL':
        case 'getMetadata':
        case 'getMyACLRights':
        case 'getQuota':
        case 'getQuotaRoot':
        case 'getSyncToken':
        case 'setMetadata':
        case 'setQuota':
        case 'status':
        case 'statusMultiple':
        case 'store':
        case 'subscribeMailbox':
        case 'sync':
        case 'thread':
            // Horde_Imap_Client_Mailbox: these calls all have the mailbox as
            // their first parameter.
            $params[0] = IMP_Mailbox::getImapMboxOb($params[0]);
            break;

        case 'copy':
        case 'renameMailbox':
            // Horde_Imap_Client_Mailbox: these calls all have the mailbox as
            // their first two parameters.
            $params[0] = IMP_Mailbox::getImapMboxOb($params[0]);
            $params[1] = IMP_Mailbox::getImapMboxOb($params[1]);
            break;

        case 'openMailbox':
            $mbox = IMP_Mailbox::get($params[0]);
            if ($mbox->search) {
                /* Can't open a search mailbox. */
                return;
            }
            $params[0] = $mbox->imap_mbox_ob;
            break;

        case 'search':
            $params = call_user_func_array(array($this, '_search'), $params);
            break;
        }

        try {
            $result = call_user_func_array(array($this->_ob, $method), $params);
        } catch (Horde_Imap_Client_Exception $e) {
            $error = new IMP_Imap_Exception($e);
            if ($auth_e = $error->authException(false)) {
                throw $auth_e;
            }

            Horde::log($error);
            throw $error;
        }

        /* Special handling for various methods. */
        switch ($method) {
        case 'createMailbox':
        case 'renameMailbox':
            // Mailbox is first parameter.
            IMP_Mailbox::get($params[0])->expire();
            break;

        case 'login':
            if (!$this->_ob->getParam('imp:login')) {
                /* Check for POP3 UIDL support. */
                if (!$this->isImap() &&
                    !$this->queryCapability('UIDL')) {
                    $error = new IMP_Imap_Exception('The POP3 server does not support the REQUIRED UIDL capability.');
                    Horde::log($error);
                    throw $error;
                }

                $this->_ob->setParam('imp:login', true);
                $this->_changed = true;
            }
            break;

        case 'setACL':
            IMP_Mailbox::get($params[0])->expire(IMP_Mailbox::CACHE_ACL);
            break;
        }

        return $result;
    }

    /**
     * Prepares an IMAP search query.  Needed because certain configuration
     * parameters may need to be dynamically altered before passed to the
     * Imap_Client object.
     *
     * @param string $mailbox                        The mailbox to search.
     * @param Horde_Imap_Client_Search_Query $query  The search query object.
     * @param array $opts                            Additional options.
     *
     * @return array  Parameters to use in the search() call.
     */
    protected function _search($mailbox, $query = null, array $opts = array())
    {
        $mailbox = IMP_Mailbox::get($mailbox);

        if (!empty($opts['sort']) && $mailbox->access_sort) {
            /* If doing a from/to search, use display sorting if possible.
             * Although there is a fallback to a PHP-based display sort, for
             * performance reasons only do a display sort if it is supported
             * on the server. */
            foreach ($opts['sort'] as $key => $val) {
                switch ($val) {
                case Horde_Imap_Client::SORT_FROM:
                    $opts['sort'][$key] = Horde_Imap_Client::SORT_DISPLAYFROM_FALLBACK;
                    break;

                case Horde_Imap_Client::SORT_TO:
                    $opts['sort'][$key] = Horde_Imap_Client::SORT_DISPLAYTO_FALLBACK;
                    break;
                }
            }
        }

        if (!is_null($query)) {
            $query->charset('UTF-8', false);
        }

        return array($mailbox->imap_mbox_ob, $query, $opts);
    }

    /* Static methods. */

    /**
     * Loads the IMP server configuration from backends.php.
     *
     * @param string $server  Returns this labeled entry only.
     *
     * @return mixed  If $server is set return this entry; else, return the
     *                entire servers array. Returns false on error.
     */
    static public function loadServerConfig($server = null)
    {
        if (empty(self::$_backends)) {
            try {
                $s = Horde::loadConfiguration('backends.php', 'servers', 'imp');
                if (is_null($s)) {
                    return false;
                }
            } catch (Horde_Exception $e) {
                Horde::log($e, 'ERR');
                return false;
            }

            foreach ($s as $key => $val) {
                if (empty($val['disabled'])) {
                    self::$_backends[$key] = new IMP_Imap_Config($val);
                }
            }
        }

        return is_null($server)
            ? self::$_backends
            : (isset(self::$_backends[$server]) ? self::$_backends[$server] : false);
    }

    /* Serializable methods. */

    /**
     */
    public function serialize()
    {
        return serialize(array(
            $this->_ob,
            $this->_config
        ));
    }

    /**
     */
    public function unserialize($data)
    {
        list(
            $this->_ob,
            $this->_config
        ) = unserialize($data);
    }

}
