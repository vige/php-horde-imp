<?php
/**
 * Copyright 2012-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2012-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Defines AJAX actions used for unblocking images in HTML messages.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2012-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Ajax_Application_Handler_ImageUnblock extends Horde_Core_Ajax_Application_Handler
{
    /**
     * AJAX action: Store e-mail sender of message in safe address list.
     *
     * Requires mailbox/indices form input.
     *
     * @return boolean  True.
     */
    public function imageUnblockAdd()
    {
        global $injector, $notification;

        $indices = new IMP_Indices_Mailbox($this->vars);
        $address = $injector->getInstance('IMP_Factory_Contents')
            ->create($indices)
            ->getHeader()
            ->getOb('from')
            ->bare_addresses[0];

        if ($injector->getInstance('IMP_Prefs_Special_ImageReplacement')->addSafeAddrList($address)) {
            $this->_base->queue->message($indices);
            $notification->push(sprintf(_("Always showing images in messages sent by %s."), $address), 'horde.success');
        }

        return true;
    }

}
