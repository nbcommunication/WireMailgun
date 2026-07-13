<?php namespace ProcessWire;

/**
 * Tests for WireMailgun
 *
 * Run via: php index.php test WireMailgun
 * (or `php index.php test all`, or the WireTests admin page)
 *
 * Builder/config logic (cc/bcc, tags, recipient variables, custom data,
 * inline images, sender/region/domain/api key overrides, template usage,
 * attachments, email validation) is covered using reflection to inspect
 * protected properties (customData, inline, tags, recipientVariables,
 * mail, template, httpCode) since WireMail/WireMailgun don't expose public
 * getters for all of them.
 *
 * Sending (___send()) IS also exercised here, against the real Mailgun API
 * using this module's configured production credentials, but every send
 * test forces Mailgun's "test mode" on via setTestMode(true) regardless of
 * the module's configured default. In test mode Mailgun authenticates and
 * validates the request as normal (so a genuine HTTP 200 + accepted message
 * id is returned) but guarantees the message is discarded and never
 * actually delivered/queued to any recipient. See:
 * https://documentation.mailgun.com/docs/mailgun/user-manual/sending-messages/#sending-in-test-mode
 *
 * validateEmail() network lookups against Mailgun's validation endpoint
 * are still avoided (that only fast-fails on a malformed address, so no
 * live call is exercised).
 *
 * The automatic retry/split logic used when a batch-mode send gets a 400
 * (recipient-variables/payload related) or 413 (payload too large) response
 * from Mailgun - shouldRetryRecipientVariableSplit() and
 * splitBatchAndSend() - is tested by invoking those private methods
 * directly via reflection rather than trying to force a genuine 400/413
 * over the network (which would depend on undocumented/variable Mailgun
 * size thresholds and would be slow/fragile to reproduce reliably). The
 * split-and-resend behavior of splitBatchAndSend() is still verified with
 * a real live call in forced test mode, confirming it performs 2 genuine
 * sub-sends and aggregates the result correctly.
 *
 * WireMailgun's `singular` module flag is false, so every call to
 * $this->wire('modules')->get('WireMailgun') returns a brand new instance -
 * this is used throughout to keep each group of assertions isolated from
 * state set by earlier ones (tags/cc/bcc/recipientVariables all accumulate
 * on an instance rather than being reset automatically).
 */
class WireTest_WireMailgun extends WireTest {

	public function init() {
		if(!$this->wire('modules')->isInstalled('WireMailgun')) {
			$this->fail('WireMailgun module is not installed');
		}
	}

	public function execute() {
		$this->testCc();
		$this->testBcc();
		$this->testAddData();
		$this->testAddInlineImage();
		$this->testAddRecipientVariables();
		$this->testAddTags();
		$this->testSetApiKeyDomainRegion();
		$this->testSetSender();
		$this->testSetBatchMode();
		$this->testSetCampaign();
		$this->testSetDeliveryTime();
		$this->testUseTemplate();
		$this->testAttachment();
		$this->testValidateEmail();
		$this->testGetHttpCode();
		$this->testSendTestMode();
		$this->testSendHtmlTestMode();
		$this->testSendBatchModeTestMode();
		$this->testSendInvalidApiKeyFails();
		$this->testShouldRetryRecipientVariableSplit();
		$this->testSplitBatchAndSend();
	}

	/**
	 * Get a fresh WireMailgun instance - module is non-singular so each
	 * get() call returns a new object with default/empty builder state.
	 *
	 * @return WireMailgun
	 */
	protected function newMailgun() {
		return $this->wire('modules')->get('WireMailgun');
	}

	/**
	 * Read a protected/private property value via reflection
	 */
	protected function getProtected($obj, $prop) {
		$ref = new \ReflectionObject($obj);
		$p = $ref->getProperty($prop);
		$p->setAccessible(true);
		return $p->getValue($obj);
	}

	/**
	 * Invoke a protected/private method via reflection
	 */
	protected function invokeMethod($obj, $method, array $args = array()) {
		$ref = new \ReflectionObject($obj);
		$m = $ref->getMethod($method);
		$m->setAccessible(true);
		return $m->invokeArgs($obj, $args);
	}

	protected function testCc() {
		$mg = $this->newMailgun();
		$mg->cc('foo@example.com');
		$mail = $this->getProtected($mg, 'mail');
		$this->check('cc() sets single email', true, isset($mail['cc']['foo@example.com']));

		$mg = $this->newMailgun();
		$mg->cc('Foo Bar <foo@example.com>');
		$mail = $this->getProtected($mg, 'mail');
		$this->check('cc() parses email from "Name <email>" format', true, isset($mail['cc']['foo@example.com']));
		$this->check('cc() parses name from "Name <email>" format', 'Foo Bar', $mail['ccName']['foo@example.com']);

		$mg = $this->newMailgun();
		$mg->cc(array('foo@example.com' => 'Foo Bar', 'baz@example.com' => ''));
		$mail = $this->getProtected($mg, 'mail');
		$this->check('cc() accepts associative array - first email set', true, isset($mail['cc']['foo@example.com']));
		$this->check('cc() accepts associative array - second email set', true, isset($mail['cc']['baz@example.com']));
		$this->check('cc() accepts associative array - name set', 'Foo Bar', $mail['ccName']['foo@example.com']);

		$mg = $this->newMailgun();
		$mg->cc('foo@example.com');
		$mg->cc(null);
		$mail = $this->getProtected($mg, 'mail');
		$this->check('cc(null) clears previously set cc addresses', true, empty($mail['cc']));
	}

	protected function testBcc() {
		$mg = $this->newMailgun();
		$mg->bcc('foo@example.com');
		$mail = $this->getProtected($mg, 'mail');
		$this->check('bcc() sets single email', true, isset($mail['bcc']['foo@example.com']));
	}

	protected function testAddData() {
		// addData($key, $value) requires both arguments; both are passed
		// through sanitize() (entity-encoded + truncated to 128 chars).
		$mg = $this->newMailgun();
		$mg->addData('o:testmode', 'yes');
		$data = $this->getProtected($mg, 'customData');
		$this->check('addData() stores value under given key', 'yes', $data['o:testmode']);
	}

	protected function testAddInlineImage() {
		$mg = $this->newMailgun();
		$tmp = tempnam(sys_get_temp_dir(), 'wmgtest') . '.png';
		$this->wire('files')->filePutContents($tmp, 'not-a-real-png-but-thats-ok');
		$mg->addInlineImage($tmp, 'logo.png');
		$inline = $this->getProtected($mg, 'inline');
		$this->check('addInlineImage() registers file under given filename', $tmp, $inline['logo.png']);
		@unlink($tmp);
	}

	protected function testAddRecipientVariables() {
		// addRecipientVariables() merges per-recipient data across calls;
		// $override only decides which side wins when the SAME email key
		// collides between calls - it does not clear data for other emails.

		// override=true (default): new data wins on key collision
		$mg = $this->newMailgun();
		$mg->addRecipientVariables(array('foo@example.com' => array('id' => 1, 'name' => 'Original')), true);
		$mg->addRecipientVariables(array('foo@example.com' => array('id' => 2)), true);
		$vars = $this->getProtected($mg, 'recipientVariables');
		$this->check('addRecipientVariables() override=true replaces colliding key', 2, $vars['foo@example.com']['id']);
		$this->check('addRecipientVariables() override=true keeps non-colliding key from earlier call', 'Original', $vars['foo@example.com']['name']);

		// override=false: existing data wins on key collision
		$mg = $this->newMailgun();
		$mg->addRecipientVariables(array('bar@example.com' => array('id' => 1)), true);
		$mg->addRecipientVariables(array('bar@example.com' => array('id' => 99)), false);
		$vars = $this->getProtected($mg, 'recipientVariables');
		$this->check('addRecipientVariables() override=false keeps original value on collision', 1, $vars['bar@example.com']['id']);

		// distinct recipients accumulate regardless of override flag
		$mg = $this->newMailgun();
		$mg->addRecipientVariables(array('a@example.com' => array('id' => 1)), true);
		$mg->addRecipientVariables(array('b@example.com' => array('id' => 2)), true);
		$vars = $this->getProtected($mg, 'recipientVariables');
		$this->check('addRecipientVariables() accumulates distinct recipients - first present', true, isset($vars['a@example.com']));
		$this->check('addRecipientVariables() accumulates distinct recipients - second present', true, isset($vars['b@example.com']));
	}

	protected function testAddTags() {
		$mg = $this->newMailgun();
		$mg->addTags(array('a', 'b', 'c', 'd', 'e'));
		$tags = $this->getProtected($mg, 'tags');
		$this->check('addTags() caps at 3 tags (Mailgun hard limit)', 3, count($tags));
		$this->check('addTags() keeps first tags in call order', 'a', $tags[0]);

		$mg = $this->newMailgun();
		$mg->addTag('a');
		$mg->addTag('b');
		$mg->addTag('c');
		$mg->addTag('d'); // beyond cap: no-op that logs a warning, does not throw
		$tags = $this->getProtected($mg, 'tags');
		$this->check('addTag() calls beyond cap are silently ignored', 3, count($tags));
	}

	protected function testSetApiKeyDomainRegion() {
		$mg = $this->newMailgun();
		$mg->setApiKey('key-test123');
		$this->check('setApiKey() sets apiKey property', 'key-test123', $mg->apiKey);

		$mg->setDomainName('example.org');
		$this->check('setDomainName() sets domain property', 'example.org', $mg->domain);

		// Regression check: setRegion() must update the public 'region'
		// property itself, not just the internal API URL (see CHANGELOG).
		$mg->setRegion('eu');
		$this->check('setRegion() updates region property', 'eu', $mg->region);

		// Invalid region is a silent no-op (does not throw, does not change region)
		$mg->setRegion('xx');
		$this->check('setRegion() silently ignores invalid region value', 'eu', $mg->region);
	}

	protected function testSetSender() {
		// setSender($domain, $key, $region) only sets domain/apiKey/region -
		// it does not touch fromEmail/fromEmailName (set via WireMail::from()).
		$mg = $this->newMailgun();
		$mg->setSender('sender-domain.example', 'key-abc123', 'eu');
		$this->check('setSender() sets domain', 'sender-domain.example', $mg->domain);
		$this->check('setSender() sets apiKey', 'key-abc123', $mg->apiKey);
		$this->check('setSender() sets region', 'eu', $mg->region);
	}

	protected function testSetBatchMode() {
		$mg = $this->newMailgun();
		$mg->setBatchMode(true);
		$this->check('setBatchMode(true) sets batchMode truthy', true, (bool) $mg->batchMode);
		$mg->setBatchMode(false);
		$this->check('setBatchMode(false) sets batchMode falsy', false, (bool) $mg->batchMode);
	}

	protected function testSetCampaign() {
		$mg = $this->newMailgun();
		$mg->setCampaign(true, 'spring-sale');
		$tags = $this->getProtected($mg, 'tags');
		$this->check('setCampaign() adds tag argument via addTags()', true, in_array('spring-sale', $tags));
		$this->check('setCampaign(true, ...tags) enables click tracking', true, (bool) $mg->trackClicks);
		$this->check('setCampaign(true, ...tags) enables open tracking', true, (bool) $mg->trackOpens);
	}

	protected function testSetDeliveryTime() {
		// setDeliveryTime() converts a unix timestamp to Mailgun's required
		// RFC 2822 GMT date string (gmdate('r', $time)) and stores it on
		// $deliveryTime - it is not added to customData until ___send().
		$mg = $this->newMailgun();
		$ts = time() + 3600;
		$mg->setDeliveryTime($ts);
		$this->check('setDeliveryTime() stores RFC 2822 GMT date string', gmdate('r', $ts), $mg->deliveryTime);
	}

	protected function testUseTemplate() {
		// useTemplate($template, array $variables) requires both arguments.
		// $variables are merged (unsanitised) into customData.
		$mg = $this->newMailgun();
		$mg->useTemplate('my-template', array('firstName' => 'Alex'));
		$this->check('useTemplate() sets template name', 'my-template', $this->getProtected($mg, 'template'));
		$data = $this->getProtected($mg, 'customData');
		$this->check('useTemplate() merges variables into customData', 'Alex', $data['firstName']);
	}

	protected function testAttachment() {
		$mg = $this->newMailgun();
		$tmp = tempnam(sys_get_temp_dir(), 'wmgtest') . '.txt';
		$this->wire('files')->filePutContents($tmp, 'hello world');
		if(function_exists('curl_file_create')) {
			$mg->attachment($tmp);
			$this->ok('attachment() accepted a valid file without throwing');
		} else {
			$this->li('curl_file_create() unavailable in this environment - skipped attachment() call');
		}
		@unlink($tmp);
	}

	protected function testValidateEmail() {
		// Malformed emails fail $sanitizer->email() and return false
		// immediately, with no network call made to Mailgun.
		$mg = $this->newMailgun();
		$result = $mg->validateEmail('not-an-email');
		$this->check('validateEmail() fast-fails malformed address without an API call', false, $result);
	}

	protected function testGetHttpCode() {
		$mg = $this->newMailgun();
		$code = $mg->getHttpCode();
		$this->check('getHttpCode() returns 0 before any request has been attempted', 0, $code);
	}

	/**
	 * A plain-text send in forced Mailgun test mode
	 *
	 * Test mode messages are authenticated/validated by Mailgun exactly like
	 * a real send (so httpCode 200 and a returned "sent" count of 1 confirm
	 * the API credentials, domain and request payload are all valid) but
	 * Mailgun discards them - nothing is actually delivered.
	 */
	protected function testSendTestMode() {
		$mg = $this->newMailgun();
		$mg->setTestMode(true); // force on regardless of module's configured default
		$mg->to('agent-test@example.com');
		$mg->subject('WireMailgun automated test (plain text, test mode)');
		$mg->body('This message was sent in Mailgun test mode and is not delivered.');
		$sent = $mg->send();
		$this->check('send() plain text in test mode reports 1 sent', 1, $sent);
		$this->check('send() plain text in test mode gets HTTP 200 from Mailgun', 200, $mg->getHttpCode());
	}

	/**
	 * An HTML send (with tracking + a tag) in forced Mailgun test mode
	 */
	protected function testSendHtmlTestMode() {
		$mg = $this->newMailgun();
		$mg->setTestMode(true);
		$mg->setTrackOpens(true);
		$mg->setTrackClicks(true);
		$mg->addTag('agent-test');
		$mg->to('agent-test@example.com');
		$mg->subject('WireMailgun automated test (HTML, test mode)');
		$mg->bodyHTML('<p>This message was sent in Mailgun <b>test mode</b> and is not delivered.</p>');
		$sent = $mg->send();
		$this->check('send() HTML in test mode reports 1 sent', 1, $sent);
		$this->check('send() HTML in test mode gets HTTP 200 from Mailgun', 200, $mg->getHttpCode());
	}

	/**
	 * A batch mode send (recipient variables) in forced Mailgun test mode
	 *
	 * Confirms send() reports a sent count matching the number of distinct
	 * recipients, and that recipient variables are correctly wired through
	 * to the batch request.
	 */
	protected function testSendBatchModeTestMode() {
		$mg = $this->newMailgun();
		$mg->setTestMode(true);
		$mg->setBatchMode(true);
		$mg->to(array(
			'agent-test1@example.com' => 'Test One',
			'agent-test2@example.com' => 'Test Two',
		));
		$mg->subject('WireMailgun automated test (batch mode, test mode)');
		$mg->body('Hello %recipient.name% - this message was sent in Mailgun test mode and is not delivered.');
		$sent = $mg->send();
		$this->check('send() batch mode in test mode reports sent count matching recipients', 2, $sent);
		$this->check('send() batch mode in test mode gets HTTP 200 from Mailgun', 200, $mg->getHttpCode());
	}

	/**
	 * Sanity check that an invalid API key is rejected by Mailgun (401) and
	 * send() reports 0 sent - confirms failure handling works, without
	 * depending on (or risking) the real configured production API key.
	 */
	protected function testSendInvalidApiKeyFails() {
		$mg = $this->newMailgun();
		$mg->setTestMode(true);
		$mg->setApiKey('key-0000000000000000000000000000000000');
		$mg->to('agent-test@example.com');
		$mg->subject('WireMailgun automated test (invalid api key)');
		$mg->body('This send should fail authentication before reaching test mode handling.');
		$sent = $mg->send();
		$this->check('send() with invalid API key reports 0 sent', 0, $sent);
		$this->check('send() with invalid API key gets HTTP 401 from Mailgun', 401, $mg->getHttpCode());
	}

	/**
	 * shouldRetryRecipientVariableSplit() decides whether apiSend() should
	 * attempt splitBatchAndSend() after a 400/413 response. It requires
	 * batchMode to be on, and looks for wording in Mailgun's error message
	 * consistent with a recipient-variables/payload size problem.
	 *
	 * Tested directly via reflection rather than by forcing a genuine 400/413
	 * from the live API, since reliably reproducing those over the network
	 * would depend on undocumented/variable Mailgun size thresholds.
	 */
	protected function testShouldRetryRecipientVariableSplit() {
		// Guard clause: always false when batchMode is off, even with a matching message
		$mg = $this->newMailgun();
		$this->check(
			'shouldRetryRecipientVariableSplit() is false when batchMode is off',
			false,
			$this->invokeMethod($mg, 'shouldRetryRecipientVariableSplit', array('Recipient variables are too large'))
		);

		$mg = $this->newMailgun();
		$mg->setBatchMode(true);

		$this->check(
			'shouldRetryRecipientVariableSplit() is false for an empty message',
			false,
			$this->invokeMethod($mg, 'shouldRetryRecipientVariableSplit', array(''))
		);

		$this->check(
			'shouldRetryRecipientVariableSplit() matches "recipient variables" wording (typical 400)',
			true,
			$this->invokeMethod($mg, 'shouldRetryRecipientVariableSplit', array('Recipient variables are too large'))
		);

		$this->check(
			'shouldRetryRecipientVariableSplit() matches "payload" wording (typical 413)',
			true,
			$this->invokeMethod($mg, 'shouldRetryRecipientVariableSplit', array('Payload too large'))
		);

		$this->check(
			'shouldRetryRecipientVariableSplit() matches "too big/too large" wording',
			true,
			$this->invokeMethod($mg, 'shouldRetryRecipientVariableSplit', array('Message too big'))
		);

		$this->check(
			'shouldRetryRecipientVariableSplit() matches "size"/"limit" wording',
			true,
			$this->invokeMethod($mg, 'shouldRetryRecipientVariableSplit', array('Request exceeds size limit'))
		);

		$this->check(
			'shouldRetryRecipientVariableSplit() is false for an unrelated 400 message (no retry)',
			false,
			$this->invokeMethod($mg, 'shouldRetryRecipientVariableSplit', array('Invalid email address provided'))
		);
	}

	/**
	 * splitBatchAndSend() halves a batch-mode recipient-variables payload
	 * into 2 chunks and sends each via apiSend(), summing the results. This
	 * is what apiSend() calls when a 400/413 response passes the check
	 * above (400) or unconditionally (413).
	 *
	 * Guard clauses are tested directly via reflection (no network call).
	 * The functional split-and-resend behavior is then verified with a real
	 * live call against the Mailgun API, forced into test mode so nothing is
	 * actually delivered - this confirms the method truly performs 2 real
	 * sub-sends and correctly aggregates the sent count, which is the part
	 * of the retry mechanism most worth verifying end-to-end.
	 */
	protected function testSplitBatchAndSend() {

		// Guard: batchMode off -> 0, regardless of payload shape
		$mg = $this->newMailgun();
		$data = array('recipient-variables' => json_encode(array(
			'a@example.com' => array('name' => 'A'),
			'b@example.com' => array('name' => 'B'),
		)));
		$this->check(
			'splitBatchAndSend() returns 0 when batchMode is off',
			0,
			$this->invokeMethod($mg, 'splitBatchAndSend', array($data))
		);

		// Guard: batchMode on but no recipient-variables key -> 0
		$mg = $this->newMailgun();
		$mg->setBatchMode(true);
		$this->check(
			'splitBatchAndSend() returns 0 when data has no recipient-variables key',
			0,
			$this->invokeMethod($mg, 'splitBatchAndSend', array(array('from' => 'x')))
		);

		// Guard: batchMode on, only 1 recipient -> 0 (nothing left to split)
		$mg = $this->newMailgun();
		$mg->setBatchMode(true);
		$data = array('recipient-variables' => json_encode(array(
			'a@example.com' => array('name' => 'A'),
		)));
		$this->check(
			'splitBatchAndSend() returns 0 when there is only 1 recipient to split',
			0,
			$this->invokeMethod($mg, 'splitBatchAndSend', array($data))
		);

		// Functional: real split-and-resend of a 4-recipient batch, forced test mode
		$mg = $this->newMailgun();
		$mg->setBatchMode(true);
		$mg->setTestMode(true);

		$recipientVariables = array(
			'agent-test1@example.com' => array('name' => 'Test One'),
			'agent-test2@example.com' => array('name' => 'Test Two'),
			'agent-test3@example.com' => array('name' => 'Test Three'),
			'agent-test4@example.com' => array('name' => 'Test Four'),
		);

		$data = array(
			'from' => "ProcessWire <processwire@{$mg->domain}>",
			'subject' => 'WireMailgun automated test (splitBatchAndSend, test mode)',
			'text' => 'Hello %recipient.name% - this message was sent in Mailgun test mode and is not delivered.',
			'o:testmode' => true,
			'to' => implode(',', array_keys($recipientVariables)),
			'recipient-variables' => json_encode($recipientVariables),
		);

		$sent = $this->invokeMethod($mg, 'splitBatchAndSend', array($data));
		$this->check('splitBatchAndSend() sums sent counts across both chunks to the full recipient total', 4, $sent);
		$this->check('splitBatchAndSend() final chunk gets HTTP 200 from Mailgun', 200, $mg->getHttpCode());
	}

}
