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
 * Attachment data for an outgoing compose message.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   IMP
 *
 * @property-read boolean $linked  Should this attachment be linked?
 * @property-read Horde_Url $link_url  The URL, if the attachment is linked.
 * @property-read IMP_Compose_Attachment_Storage $storage  The storage object.
 */
class IMP_Compose_Attachment implements Serializable
{
    /**
     * Attachment ID.
     *
     * @var integer
     */
    public $id;

    /**
     * Is this part associated with multipart/related data?
     *
     * @var boolean
     */
    public $related = false;

    /**
     * Temporary filename.
     *
     * @var string
     */
    public $tmpfile = null;

    /**
     * Compose object cache ID.
     *
     * @var string
     */
    protected $_composeCache;

    /**
     * Does the part contain the attachment contents?
     *
     * @var boolean
     */
    protected $_isBuilt = false;

    /**
     * Should this attachment be linked?
     *
     * @var boolean
     */
    protected $_linked = null;

    /**
     * MIME part object.
     *
     * @var Horde_Mime_Part
     */
    protected $_part;

    /**
     * The unique identifier for the file.
     *
     * @var string
     */
    protected $_uuid = null;

    /**
     * Constructor.
     *
     * @param IMP_Compose $ob        Compose object.
     * @param Horde_Mime_Part $part  MIME part object.
     * @param string $tmp_file       Temporary filename containing the data.
     */
    public function __construct(IMP_Compose $ob, Horde_Mime_Part $part,
                                $tmp_file)
    {
        $this->id = ++$ob->atcId;
        $this->_composeCache = strval($ob);
        $this->_part = $part;
        $this->tmpfile = $tmp_file;
    }

    /**
     */
    public function __get($name)
    {
        global $injector;

        switch ($name) {
        case 'linked':
            return ($this->_linked === true);

        case 'link_url':
            return $this->storage->link_url;

        case 'storage':
            return $injector->getInstance('IMP_Factory_ComposeAtc')->create(null, $this->_uuid, $this->_linked);
        }
    }

    /**
     * Return the MIME part object.
     *
     * @param boolean $build  If true, ensures the part contains the data.
     *
     * @return Horde_Mime_Part  MIME part object.
     * @throws IMP_Compose_Exception
     */
    public function getPart($build = false)
    {
        if ($build && !$this->_isBuilt) {
            $data = is_null($this->tmpfile)
                ? $this->storage->read()
                : fopen($this->tmpfile, 'r');
            $this->_part->setContents($data, array('stream' => true));
            $this->_isBuilt = true;
        }

        return $this->_part;
    }

    /**
     * Delete the attachment data.
     */
    public function delete()
    {
        $this->tmpfile = null;

        if (!is_null($this->_uuid)) {
            if (!$this->linked) {
                try {
                    $this->storage->delete();
                } catch (Exception $e) {}
            }
            $this->_uuid = null;
        }
    }

    /**
     * Get a URL of the data.
     *
     * @return Horde_Url  URL to display the attachment data.
     */
    public function viewUrl()
    {
        return Horde::url('view.php', true)->add(array(
            'actionID' => 'compose_attach_preview',
            'composeCache' => strval($GLOBALS['injector']->getInstance('IMP_Factory_Compose')->create($this->_composeCache)),
            'id' => $this->id
        ));
    }

    /* Serializable methods. */

    /**
     */
    public function serialize()
    {
        /* Don't store Mime_Part data. Can't use clone here ATM, since there
         * appears to be a PHP bug. Since this is an object specific to IMP
         * (and we are only using in a certain predictable way), it should
         * be ok to directly alter the MIME part object without any ill
         * effects. */
        $this->_part->clearContents();
        $this->_isBuilt = false;

        if (!is_null($this->tmpfile)) {
            $this->_uuid = strval(new Horde_Support_Uuid());
            $atc = $this->storage;
            $atc->write($this->tmpfile, $this->getPart());
            /* Need to save this information now, since it is possible that
             * storage backends change their linked status based on the data
             * written to the backend. */
            $this->_linked = $atc->linked;
            $this->tmpfile = null;
        }

        return serialize(array(
            'c' => $this->_composeCache,
            'i' => $this->id,
            'l' => $this->_linked,
            'p' => $this->_part,
            'r' => $this->related,
            'u' => $this->_uuid
        ));
    }

    /**
     */
    public function unserialize($data)
    {
        $data = @unserialize($data);

        $this->_composeCache = $data['c'];
        $this->id = $data['i'];
        $this->_linked = $data['l'];
        $this->_part = $data['p'];
        $this->related = !empty($data['r']);
        $this->_uuid = $data['u'];
    }

}
