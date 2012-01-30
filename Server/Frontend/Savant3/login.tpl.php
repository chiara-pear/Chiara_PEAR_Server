<?php
if ($this->loginerror) {
    ?><div class="error"><?php echo $this->loginerror; ?></div><?php
}
$form = $this->plugin('form');
echo $form->form('begin');
echo $form->form('auto', $this->formdata);
echo $form->form('end');
?>