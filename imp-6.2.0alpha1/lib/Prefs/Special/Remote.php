<?php
/**
 * Copyright 2013-2014 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2013-2014 Horde LLC
 * @license   http://www.fsf.org/copyleft/gpl.html GPL
 * @package   IMP
 */

/**
 * Special prefs handling for the 'remotemanagement' preference.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2014 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Prefs_Special_Remote implements Horde_Core_Prefs_Ui_Special
{
    /**
     */
    public function init(Horde_Core_Prefs_Ui $ui)
    {
    }

    /**
     */
    public function display(Horde_Core_Prefs_Ui $ui)
    {
        global $injector, $page_output;

        $ui->nobuttons = true;

        $page_output->addScriptFile('remoteprefs.js');
        $page_output->addInlineJsVars(array(
            'ImpRemotePrefs.confirm_delete' => _("Are you sure you want to delete this account?")
        ));

        $view = new Horde_View(array(
            'templatePath' => IMP_TEMPLATES . '/prefs'
        ));
        $view->addHelper('Horde_Core_View_Helper_Image');
        $view->addHelper('Text');

        switch ($ui->vars->remote_action) {
        case 'new':
            $view->new = true;
            break;

        default:
            $view->accounts = iterator_to_array($injector->getInstance('IMP_Remote'));
            break;
        }

        return $view->render('remote');
    }

    /**
     */
    public function update(Horde_Core_Prefs_Ui $ui)
    {
        global $injector, $notification;

        $remote = $injector->getInstance('IMP_Remote');

        switch ($ui->vars->remote_action) {
        case 'add':
            try {
                $ob = new IMP_Remote_Account();
                $ob->hostspec = $ui->vars->remote_server;
                $ob->username = $ui->vars->remote_user;
                if (strlen($ui->vars->remote_label)) {
                    $ob->label = $ui->vars->remote_label;
                }
                if ($ui->vars->remote_port) {
                    $ob->port = $ui->vars->remote_port;
                }

                switch ($ui->vars->remote_secure) {
                case 'auto':
                    /* Check for non-SSL connection. */
                    $ob->secure = false;
                    if ($stream = @stream_socket_client($ob->hostspec . ':' . $ob->port)) {
                        stream_set_timeout($stream, 2);
                        if (fread($stream, 1024)) {
                            $ob->secure = true;
                        }
                        fclose($stream);
                    }
                    break;

                case 'no':
                    $ob->secure = false;
                    break;

                case 'yes':
                    /* Check for non-SSL connection. */
                    $ob->secure = 'ssl';
                    if ($stream = @stream_socket_client($ob->hostspec . ':' . $ob->port)) {
                        stream_set_timeout($stream, 2);
                        if (fread($stream, 1024)) {
                            $ob->secure = 'tls';
                        }
                        fclose($stream);
                    }
                    break;
                }

                if ($ui->vars->get('remote_type') == 'pop3') {
                    $ob->type = $ob::POP3;
                }

                $remote[strval($ob)] = $ob;

                $notification->push(sprintf(_("Account \"%s\" added."), $ui->vars->remote_server), 'horde.success');

                $injector->getInstance('IMP_Ftree')->insert($ob);
            } catch (IMP_Exception $e) {
                $notification->push($e->getMessage(), 'horde.error');
            }
            break;

        case 'delete':
            if (isset($remote[$ui->vars->remote_data])) {
                $ob = $remote[$ui->vars->remote_data];
                unset($remote[$ui->vars->remote_data]);
                $notification->push(sprintf(_("Account \"%s\" deleted."), $ob->label), 'horde.success');

                $injector->getInstance('IMP_Ftree')->delete($ob);
            }
            break;
        }

        return false;
    }

}
