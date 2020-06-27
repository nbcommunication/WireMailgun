<?php namespace ProcessWire;

/**
 * WireMail Mailgun Configuration
 *
 */

class WireMailgunConfig extends ModuleConfig {

	/**
	 * Returns default values for module variables
	 *
	 * @return array
	 *
	 */
	public function getDefaults() {
		return [
			'region' => 'us',
			'batchMode' => (int) $this->wire('modules')->isInstalled('ProMailer'),
			'trackOpens' => 1,
			'trackClicks' => 1,
		];
	}

	/**
	 * Returns inputs for module configuration
	 *
	 * @return InputfieldWrapper
	 *
	 */
	public function getInputfields() {

		$modules = $this->wire('modules');
		$inputfields = parent::getInputfields();

		$mgUrl = 'https://app.mailgun.com/app';
		$mgLink = "[Mailgun]($mgUrl/sending/domains)";
		$hasProMailer = $modules->isInstalled('ProMailer');

		// API Setup
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('API Setup');
		$fieldset->icon = 'key';

		$fieldset->add([
			'type' => 'text',
			'name' => 'apiKey',
			'label' => $this->_('Key'),
			'notes' => sprintf($this->_('You can find your API Key on %s.'), $mgLink),
			'required' => true,
			'columnWidth' => 40,
		]);

		$fieldset->add([
			'type' => 'text',
			'name' => 'domain',
			'label' => $this->_('Domain Name'),
			'notes' => sprintf($this->_('The domain name must be setup and verified on %s.'), $mgLink),
			'required' => true,
			'columnWidth' => 40,
		]);

		$fieldset->add([
			'type' => 'radios',
			'name' => 'region',
			'label' => $this->_('Region'),
			'required' => true,
			'columnWidth' => 20,
			'optionColumns' => 1,
			'options' => $modules->get('WireMailgun')::regions,
		]);

		$inputfields->add($fieldset);

		// Default Sender
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('Default Sender');
		$fieldset->icon = 'envelope';

		$fieldset->add([
			'type' => 'text',
			'name' => 'fromEmail',
			'label' => $this->_('Email Address'),
			'description' => $this->_('The *from* email address.'),
			'notes' => sprintf($this->_('When left empty, defaults to %s.'), '*processwire@[domainName]*'),
			'columnWidth' => 50,
		]);

		$fieldset->add([
			'type' => 'text',
			'name' => 'fromEmailName',
			'label' => $this->_('Name'),
			'description' => $this->_('The *from* email name.'),
			'notes' => sprintf($this->_('When left empty, defaults to %s.'), '*ProcessWire*'),
			'columnWidth' => 50,
		]);

		$inputfields->add($fieldset);

		// Options
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('Options');
		$fieldset->icon = 'cog';

		$fieldset->add([
			'type' => 'checkbox',
			'name' => 'batchMode',
			'label' => $this->_('Batch Mode'),
			'description' => $this->_('When enabled, emails will be sent individually to each address.'),
			'notes' => ($hasProMailer ? sprintf($this->_('When %1$s is installed, %2$s is recommended.'), '`ProMailer`', '`batchMode`') . "\n" : '') .
				sprintf($this->_('See %s method of this class for more information.'), '`setBatchMode()`'),
			'collapsed' => ($hasProMailer ? 0 : 2),
		]);

		$fieldset->add([
			'type' => 'checkbox',
			'name' => 'trackOpens',
			'label' => $this->_('Track Message Opens'),
			'notes' => sprintf($this->_('Only enabled if %s is passed.'), '`bodyHTML`'),
			'columnWidth' => 50,
		]);

		$fieldset->add([
			'type' => 'checkbox',
			'name' => 'trackClicks',
			'label' => $this->_('Track Message Clicks'),
			'notes' => sprintf($this->_('Only enabled if %s is passed.'), '`bodyHTML`'),
			'columnWidth' => 50,
		]);

		$fieldset->add([
			'type' => 'checkbox',
			'name' => 'testMode',
			'label' => $this->_('Enable Test Mode'),
			'description' => $this->_('When enabled, Mailgun will accept messages but will not send them.'),
			'collapsed' => 2,
		]);

		$fieldset->add([
			'type' => 'checkbox',
			'name' => 'disableSslCheck',
			'label' => $this->_('Disable cURL SSL Check'),
			'description' => sprintf(
				$this->_('This option will allow you to work around the following error: %s.'),
				'*cURL Error: SSL certificate problem: unable to get local issuer certificate*'
			),
			'notes' => $this->_('It is recommended that you leave this option unchecked on production servers.'),
			'collapsed' => 2,
		]);

		$inputfields->add($fieldset);

		return $inputfields;
	}
}
