<?php
/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2011-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * This class implements an IMP system flag with matching on addresses.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2011-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
abstract class IMP_Flag_System_Match_Address extends IMP_Flag_Base
{
    /**
     * @param mixed $data  Either a list of addresses (Horde_Mail_Rfc822_List)
     *                     or the identity that matched the address list.
     */
    public function match($data)
    {
        return false;
    }

}
