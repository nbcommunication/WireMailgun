<?php namespace ProcessWire;

/**
 * WireMail Mailgun Configuration
 *
 */

class WireMailMailgunConfig extends ModuleConfig {

	/**
	 * Returns default values for module variables
	 *
	 * @return array
	 *
	 */
	public function getDefaults() {

		return [
			"region" => "us",
			"trackOpens" => 1,
			"trackClicks" => 1,
		];
	}

	/**
	 * Returns inputs for module configuration
	 *
	 * @return InputfieldWrapper
	 *
	 */
	public function getInputfields() {

		$inputfields = parent::getInputfields();
		$mgLink = "[Mailgun](https://mailgun.com/app/domains)";

		// API Setup
		$fieldset = $this->modules->get("InputfieldFieldset");
		$fieldset->label = $this->_("API Setup");
		$fieldset->icon = "key";

		$fieldset->add([
			"type" => "text",
			"name" => "apiKey",
			"label" => $this->_("Key"),
			"notes" => sprintf($this->_("You can find your API Key on %s."), $mgLink),
			"required" => true,
			"columnWidth" => 50,
		]);

		$fieldset->add([
			"type" => "radios",
			"name" => "region",
			"label" => $this->_("Region"),
			"required" => true,
			"columnWidth" => 50,
			"optionColumns" => 1,
			"options" => [
				"us" => "US",
				"eu" => "EU",
			],
		]);

		$fieldset->add([
			"type" => "text",
			"name" => "domain",
			"label" => $this->_("Domain Name"),
			"notes" => sprintf($this->_("The domain name must be setup and verified on %s."), $mgLink),
			"required" => true,
			"columnWidth" => 50,
		]);

		$fieldset->add([
			"type" => "checkbox",
			"name" => "dynamicDomain",
			"label" => $this->_("Use Dynamic Domains"),
			"notes" => $this->_("Uses email sender/from domain, ignores config setting."),
			"columnWidth" => 50,
		]);

		$fieldset->add([
			"type" => "text",
			"name" => "apiKeyPublic",
			"label" => $this->_("Mailgun Public API Key"),
			"description" => sprintf($this->_("The Public API Key is only required if you use the %s feature."), "`validateEmail()`"),
			"notes" => sprintf($this->_("You can find your Public API Key on %s."), "[Mailgun](https://app.mailgun.com/app/account/security)"),
			"collapsed" => 2,
		]);

		$inputfields->add($fieldset);

		// Default Sender
		$fieldset = $this->modules->get("InputfieldFieldset");
		$fieldset->label = $this->_("Default Sender");
		$fieldset->icon = "envelope";

		$fieldset->add([
			"type" => "text",
			"name" => "fromEmail",
			"label" => $this->_("Email Address"),
			"description" => $this->_("The *from* email address."),
			"notes" => $this->_("When left empty, defaults to *processwire@[domainName]*."),
			"columnWidth" => 50,
		]);

		$fieldset->add([
			"type" => "text",
			"name" => "fromEmailName",
			"label" => $this->_("Name"),
			"description" => $this->_("The *from* email name."),
			"notes" => sprintf($this->_("When left empty, defaults to %s."), "*ProcessWire*"),
			"columnWidth" => 50,
		]);

		$inputfields->add($fieldset);

		// Options
		$fieldset = $this->modules->get("InputfieldFieldset");
		$fieldset->label = $this->_("Options");
		$fieldset->icon = "cog";

		$fieldset->add([
			"type" => "checkbox",
			"name" => "trackOpens",
			"label" => $this->_("Track Message Opens"),
			"notes" => sprintf($this->_("Only enabled if %s is passed."), "`bodyHTML`"),
			"columnWidth" => 50,
		]);

		$fieldset->add([
			"type" => "checkbox",
			"name" => "trackClicks",
			"label" => $this->_("Track Message Clicks"),
			"notes" => sprintf($this->_("Only enabled if %s is passed."), "`bodyHTML`"),
			"columnWidth" => 50,
		]);

		$fieldset->add([
			"type" => "checkbox",
			"name" => "testMode",
			"label" => $this->_("Enable Test Mode"),
			"description" => $this->_("When this option is enabled, Mailgun will accept messages but won't send them."),
			"notes" => "[" . $this->_("Click here for more information") . "](https://documentation.mailgun.com/user_manual.html#sending-in-test-mode)",
			"collapsed" => 2,
		]);

		$fieldset->add([
			"type" => "checkbox",
			"name" => "disableSslCheck",
			"label" => $this->_("Disable cURL SSL Check"),
			"description" => $this->_("This option will allow you to work around the following error: *cURL Error: SSL certificate problem: unable to get local issuer certificate*."),
			"notes" => $this->_("It is recommended that you leave this option unchecked on production servers."),
			"collapsed" => 2,
		]);

		$inputfields->add($fieldset);

		return $inputfields;
	}
}
