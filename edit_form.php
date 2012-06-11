<?php
class block_ungradedassignments_edit_form extends block_edit_form {
	protected function specific_definition($mform) {
		$mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));
		
		$mform->addElement('advcheckbox', 'config_showunenrolled', get_string('showunenrolled', 'block_ungradedassignments'));
		$mform->setDefault('config_showunenrolled', false);

		$mform->addElement('advcheckbox', 'config_condense', get_string('condense', 'block_ungradedassignments'));
		$mform->setDefault('config_condense', true);

		$mform->addElement('advcheckbox', 'config_hidequizzes', get_string('hidequizzes', 'block_ungradedassignments'));
		$mform->setDefault('config_hidequizzes', false);

		$mform->addElement('text', 'config_blockdirectory', get_string('blockdirectory', 'block_ungradedassignments'));
		$mform->setDefault('config_blockdirectory', '/ungradedassignments');
		$mform->setType('config_blockdirectory', PARAM_MULTILANG);
	}
}
