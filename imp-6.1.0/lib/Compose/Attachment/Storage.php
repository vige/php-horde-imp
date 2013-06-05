<?php
/**
 * Copyright 2013 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2013 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   IMP
 */

/**
 * Abstract base class for attachment data storage.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   IMP
 *
 * @property-read boolean $linked  Can this attachment be linked?
 * @property-read Horde_Url $link_url  The URL, if the attachment is linked.
 */
abstract class IMP_Compose_Attachment_Storage
{
    /**
     * Attachment identifier.
     *
     * @var string
     */
    protected $_id;

    /**
     * Attachment owner.
     *
     * @var string
     */
    protected $_user;

    /**
     * Constructor.
     *
     * @param string $user  Attachment owner.
     * @param string $id    Attachment identifier.
     */
    public function __construct($user, $id = null)
    {
        $this->_user = $user;
        $this->_id = $id;
    }

    /**
     */
    public function __get($name)
    {
        switch ($name) {
        case 'linked':
            return ($this instanceof IMP_Compose_Attachment_Linked);

        case 'link_url':
            return $this->linked
                ? Horde::url(
                    'attachment.php',
                    true,
                    array('append_session' => -1)
                  )->add(array(
                      'id' => $this->_id,
                      'u' => $this->_user
                  ))
                : null;
        }
    }

    /**
     * Read attachment data from storage.
     *
     * @return resource  Stream containing data.
     * @throws IMP_Compose_Exception
     */
    abstract public function read();

    /**
     * Write attachment to storage.
     *
     * @param string $filename       Filename containing attachment data.
     * @param Horde_Mime_Part $part  Mime part object containing attachment
     *                               metadata.
     *
     * @throws IMP_Compose_Exception
     */
    abstract public function write($filename, Horde_Mime_Part $part);

    /**
     * Delete data from storage.
     */
    abstract public function delete();

    /**
     * Does the attachment exist in the storage backend?
     *
     * @return boolean  True if the file exists.
     */
    abstract public function exists();

    /**
     * Garbage collection.
     */
    public function gc()
    {
    }

}
