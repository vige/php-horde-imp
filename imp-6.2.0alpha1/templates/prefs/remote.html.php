<input type="hidden" name="remote_action" id="remote_action" />
<input type="hidden" name="remote_data" id="remote_data" />

<?php if ($this->new): ?>
<div class="horde-form remotemanagement">
<table>
 <tr>
  <td class="horde-form-label"><?php echo _("Label") ?>:</td>
  <td>
   <input name="remote_label" size="30" />
  </td>
  <td></td>
 </tr>
 <tr>
  <td class="horde-form-label"><?php echo _("Type") ?>:</td>
  <td>
   <select name="remote_type">
    <option value="imap" selected="selected"><?php echo _("IMAP") ?></option>
    <option value="pop3"><?php echo _("POP3") ?></option>
   </select>
  </td>
  <td class="required"><?php echo Horde_Themes_Image::tag('required.png', array('alt' => '*')) ?></td>
 </tr>
 <tr>
  <td class="horde-form-label"><?php echo _("Server") ?>:</td>
  <td>
   <input name="remote_server" size="30" />
  </td>
  <td class="required"><?php echo Horde_Themes_Image::tag('required.png', array('alt' => '*')) ?></td>
 </tr>
 <tr>
  <td class="horde-form-label"><?php echo _("Username") ?>:</td>
  <td>
   <input name="remote_user" size="30" />
  </td>
  <td class="required"><?php echo Horde_Themes_Image::tag('required.png', array('alt' => '*')) ?></td>
 </tr>
 <tr>
  <td class="horde-form-label"><?php echo _("Port") ?>:</td>
  <td>
   <input name="remote_port" size="10" />
  </td>
  <td></td>
 </tr>
 <tr>
  <td class="horde-form-label"><?php echo _("Use secure connection?") ?></td>
  <td>
   <select name="remote_secure">
    <option value="yes" selected="selected"><?php echo _("Required") ?></option>
    <option value="auto"><?php echo _("Use if available") ?></option>
    <option value="no"><?php echo _("Never") ?></option>
   </select>
  </td>
  <td></td>
 </tr>
</table>
</div>

<input id="add_button" type="button" class="horde-default" value="<?php echo _("Save") ?>" />
<input id="cancel_button" type="button" class="horde-cancel" value="<?php echo _("Cancel") ?>" />
<?php else: ?>
<table class="horde-table remotemanagement">
 <thead>
  <tr>
   <th><?php echo _("Label") ?></th>
   <th><?php echo _("Type") ?></th>
   <th><?php echo _("Server") ?></th>
   <th><?php echo _("Username") ?></th>
   <th><?php echo _("Port") ?></th>
   <th><?php echo _("Secure") ?></th>
   <th></th>
  </tr>
 </thead>
 <tbody>
<?php if ($this->accounts): ?>
<?php foreach ($this->accounts as $v): ?>
  <tr>
   <td><?php echo $this->h($v->label) ?></td>
   <td><?php echo ($v->type == IMP_Remote_Account::POP3) ? 'POP3' : 'IMAP' ?></td>
   <td><?php echo $this->h($v->hostspec) ?></td>
   <td><?php echo $this->h($v->username) ?></td>
   <td><?php echo $this->h($v->port) ?></td>
<?php if (is_null($v->secure)): ?>
   <td><?php echo _("Auto") ?></td>
<?php elseif (!$v->secure): ?>
   <td class="remoteNotSecure"><?php echo _("No") ?></td>
<?php else: ?>
   <td class="remoteSecure"><?php echo _("Yes") ?></td>
<?php endif; ?>
   <td>
    <a class="remotedelete" href="#" data-id="<?php echo $v->id ?>"><?php echo $this->hordeImage('delete-small.png') ?></a>
   </td>
  </tr>
<?php endforeach; ?>
<?php else: ?>
  <tr>
   <td class="noneconfigured" colspan="3"><?php echo _("No remote accounts configured") ?></td>
  </tr>
<?php endif; ?>
 </tbody>
</table>

<input id="new_button" type="submit" class="horde-create" value="<?php echo _("Add Account") ?>" />
<?php endif; ?>
