<?php
/**
 * Copyright 2011-2013 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2011-2013 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Method to generate MBOX data.
 *
 * @author    Didi Rieder <adrieder@sbox.tugraz.at>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2011-2013 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Mbox_Generate
{
    /**
     * Generates a string that can be saved out to an mbox format mailbox file
     * for a mailbox (or set of mailboxes), optionally including all
     * subfolders of the selected mailbox(es) as well. All mailboxes will be
     * output in the same string.
     *
     * @param mixed $mboxes  A mailbox name (UTF-8), or list of mailbox names,
     *                       to generate a mbox file for.
     *
     * @return resource  A stream resource containing the text of a mbox
     *                   format mailbox file.
     */
    public function generate($mboxes)
    {
        $body = fopen('php://temp', 'r+');

        if (!is_array($mboxes)) {
            if (!strlen($mboxes)) {
                return $body;
            }
            $mboxes = array($mboxes);
        }

        if (empty($mboxes)) {
            return $body;
        }

        $imp_imap = $GLOBALS['injector']->getInstance('IMP_Imap');

        foreach ($mboxes as $val) {
            $query = new Horde_Imap_Client_Fetch_Query();
            $query->size();

            try {
                $size = $imp_imap->fetch($val, $query, array(
                    'ids' => $imp_imap->getIdsOb(Horde_Imap_Client_Ids::ALL, true),
                    'nocache' => true
                ));
            } catch (IMP_Imap_Exception $e) {
                continue;
            }

            $curr_size = 0;
            $msgs = count($size);
            $start = 1;
            $slices = array();

            /* Handle 5 MB chunks of data at a time. */
            for ($i = 1; $i <= $msgs; ++$i) {
                if (isset($size[$i])) {
                    $curr_size += $size[$i]->getSize();
                    if ($curr_size > 5242880) {
                        $slices[] = $imp_imap->getIdsOb(range($start, $i), true);
                        $curr_size = 0;
                        $start = $i + 1;
                    }
                }
            }

            if ($start <= $msgs) {
                $slices[] = $imp_imap->getIdsOb(range($start, $msgs), true);
            }

            unset($size);

            $query = new Horde_Imap_Client_Fetch_Query();
            $query->envelope();
            $query->imapDate();
            $query->headerText(array(
                'peek' => true
            ));
            $query->bodyText(array(
                'peek' => true
            ));

            foreach ($slices as $slice) {
                try {
                    $res = $imp_imap->fetch($val, $query, array(
                        'ids' => $slice,
                        'nocache' => true
                    ));
                } catch (IMP_Imap_Exception $e) {
                    continue;
                }

                foreach ($res as $ptr) {
                    $from_env = $ptr->getEnvelope()->from;
                    $from = count($from_env)
                        ? $from_env[0]->bare_address
                        : '<>';

                    /* We need this long command since some MUAs (e.g. pine)
                     * require a space in front of single digit days. */
                    $imap_date = $ptr->getImapDate();
                    $date = sprintf('%s %2s %s', $imap_date->format('D M'), $imap_date->format('j'), $imap_date->format('H:i:s Y'));
                    fwrite($body, 'From ' . $from . ' ' . $date . "\r\n");

                    /* Remove spurious 'From ' line in headers. */
                    $stream = $ptr->getHeaderText(0, Horde_Imap_Client_Data_Fetch::HEADER_STREAM);
                    while (!feof($stream)) {
                        $line = fgets($stream);
                        if (substr($line, 0, 5) != 'From ') {
                            fwrite($body, $line);
                        }
                    }

                    fwrite($body, "\r\n");

                    /* Add Body text. */
                    $stream = $ptr->getBodyText(0, true);
                    while (!feof($stream)) {
                        fwrite($body, fread($stream, 8192));
                    }

                    fwrite($body, "\r\n");
                }
            }
        }

        return $body;
    }

}
