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
 * Message page for dynamic view.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2012-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Dynamic_Message extends IMP_Dynamic_Base
{
    /**
     * @throws IMP_Exception
     */
    protected function _init()
    {
        global $conf, $injector, $notification, $page_output;

        if (!$this->indices) {
            throw new IMP_Exception(_("No message index given."));
        }

        $page_output->addScriptFile('message-dimp.js');
        $page_output->addScriptFile('textarearesize.js', 'horde');
        $page_output->addScriptFile('toggle_quotes.js', 'horde');

        $page_output->addScriptPackage('IMP_Script_Package_Imp');

        $js_vars = array();

        switch ($this->vars->actionID) {
        case 'strip_attachment':
            try {
                $this->indices = new IMP_Indices_Mailbox(
                    $this->indices->mailbox,
                    $injector->getInstance('IMP_Message')->stripPart($this->indices, $this->vars->id)
                );
                $js_vars['-DimpMessage.strip'] = 1;
                $notification->push(_("Attachment successfully stripped."), 'horde.success');
            } catch (IMP_Exception $e) {
                $notification->push($e);
            }
            break;
        }

        try {
            $show_msg = new IMP_Ajax_Application_ShowMessage($this->indices);
            $msg_res = $show_msg->showMessage(array(
                'headers' => array_diff(array_keys($injector->getInstance('IMP_Message_Ui')->basicHeaders()), array('subject')),
                'preview' => false
            ));
        } catch (IMP_Exception $e) {
            $notification->notify(array(
                'listeners' => array('status', 'audio')
            ));
            echo Horde::wrapInlineScript(array(
                'parent.close()'
            ));
            exit;
        }

        $ajax_queue = $injector->getInstance('IMP_Ajax_Queue');
        $ajax_queue->poll($this->indices->mailbox);

        list(,$buid) = $this->indices->buids->getSingle();

        foreach (array('from', 'to', 'cc', 'bcc', 'replyTo', 'log') as $val) {
            if (!empty($msg_res[$val])) {
                $js_vars['DimpMessage.' . $val] = $msg_res[$val];
            }
        }
        if (!empty($msg_res['list_info']['exists'])) {
            $js_vars['DimpMessage.reply_list'] = true;
            $this->view->listinfo = Horde::popupJs(
                IMP_Basic_Listinfo::url(array(
                    'buid' => $buid,
                    'mailbox' => $this->indices->mailbox
                )), array(
                    'urlencode' => true
                )
            );
        }
        $js_vars['DimpMessage.buid'] = $buid;
        $js_vars['DimpMessage.mbox'] = $this->indices->mailbox->form_to;
        $js_vars['DimpMessage.tasks'] = $injector->getInstance('Horde_Core_Factory_Ajax')->create('imp', $this->vars)->getTasks();

        $page_output->addInlineJsVars($js_vars);
        if (isset($msg_res['js'])) {
            $page_output->addInlineScript(array_filter($msg_res['js']), true);
        }

        $this->_pages[] = 'message';

        /* Determine if compose mode is disabled. */
        if (IMP_Compose::canCompose()) {
            $this->view->qreply = $injector
                ->getInstance('IMP_Dynamic_Compose_Common')
                ->compose(
                    $this,
                    array('title' => _("Message") . ': ' . $msg_res['subject']));

            $this->_pages[] = 'qreply';

            $this->js_conf['qreply'] = 1;
        }

        $page_output->noDnsPrefetch();

        $this->view->show_delete = $this->indices->mailbox->access_deletemsgs;

        list($real_mbox,) = $this->indices->getSingle();
        $this->view->show_innocent = $real_mbox->innocent_show;
        $this->view->show_spam = $real_mbox->spam_show;

        $this->view->show_view_all = empty($msg_res['onepart']);
        $this->view->show_view_source = !empty($conf['user']['allow_view_source']);

        $this->view->save_as = $msg_res['save_as'];
        $this->view->subject = isset($msg_res['subjectlink'])
            ? $msg_res['subjectlink']
            : $msg_res['subject'];

        $hdrs = array();
        foreach ($msg_res['headers'] as $val) {
            $hdrs[] = array_filter(array(
                'id' => (isset($val['id']) ? 'msgHeader' . $val['id'] : null),
                'label' => $val['name'],
                'val' => $val['value']
            ));
        }
        $this->view->hdrs = $hdrs;

        if (isset($msg_res['atc_label'])) {
            $this->view->atc_label = $msg_res['atc_label'];
            if (isset($msg_res['atc_list'])) {
                $this->view->atc_list = $msg_res['atc_list'];
            } else {
                $this->view->atc_list = array();
            }
            if (isset($msg_res['atc_download'])) {
                $this->view->atc_download = $msg_res['atc_download'];
            }
        } else {
            $this->view->atc_list = array();
        }

        $this->view->msgtext = $msg_res['msgtext'];

        Horde::startBuffer();
        $notification->notify(array(
            'listeners' => array('status', 'audio')
        ));
        $this->view->status = Horde::endBuffer();

        $this->title = $msg_res['title'];
        $this->view->title = $this->title;
    }

    /**
     */
    static public function url(array $opts = array())
    {
        return Horde::url('dynamic.php')->add('page', 'message');
    }

}
