# WireMailMailgun
Extends WireMail to use the Mailgun API for sending emails.

# How To Install
1. Download the [zip file](https://github.com/chriswthomson/WireMailMailgun/archive/master.zip) at Github or clone directly the repo into your `site/modules`
2. If you dowloaded the `zip file`, extract it in your `sites/modules` directory
3. Goto the modules admin page, click on refresh and install it.

# API
Prior to using this module, you must set up a domain in your [Mailgun account](https://app.mailgun.com/app/domains) to create an API key. Add the API key and domain to the module's settings.

## Usage
Usage is similar to the basic WireMail implementation, although a few extra options are available. Please refer to the [WireMail documentation](https://processwire.com/api/ref/wire-mail/) for full instructions on using WireMail, and to the examples below.

## Extra Methods
The following are extra methods implemented by this module:

### Chainable
The following methods can be used in a chained statement:

**cc(**_string|array|null_ **$email)** - Set a "cc" email address. 
- Only used when `$batchMode` is set to `false`. 
- Please refer to [WireMail::to()](https://processwire.com/api/ref/wire-mail/to/) for more information on how to use this method.

**bcc(**_string|array|null_ **$email)** - Set a "bcc" email address. 
- Only used when `$batchMode` is set to `false`. 
- Please refer to [WireMail::to()](https://processwire.com/api/ref/wire-mail/to/) for more information on how to use this method.

**addData(**_string_ **$key**, _string_ **$value)** - Add custom data to the email.
- See https://documentation.mailgun.com/en/latest/user_manual.html#attaching-data-to-messages for more information.

**addInlineImage(**_string_ **$file**, _string_ **$filename)** - Add an inline image for referencing in HTML. 
Images should be referenced like so: `<img src="cid:$filename">`
- See https://documentation.mailgun.com/en/latest/user_manual.html#sending-inline-images for more information.

**addTag(**_string_ **$tag)** - Add a tag to the email. 
- Only ASCII allowed
- Maximum length of 128 characters
- There is a maximum number of 3 tags allowed per email.

**addTags(**_array_ **$tags)** - Add tags in a batch.

**setApiKey(**_string_ **$apiKey)** - Override the Mailgun API Key module setting.

**setBatchMode(**_bool_ **$batchMode)** - Enables or disables batch mode.
- This is on by default, meaning that one email is sent per "to" recipient, and the recipients won't see the other recipients
- If this is on, any email addresses set by `cc()` and `bcc()` will be ignored
-  Mailgun has a maximum hard limit of recipients allowed per batch of 1,000. [Read more about batch sending](https://documentation.mailgun.com/user_manual.html#batch-sending).

**setDeliveryTime(**_int_ **$time)** - The (unix)time the email should be scheduled for.

**setDomainName(**_string_ **$domainName)** - Override the "Domain Name" module setting.

**setTestMode(**_bool_ **$testMode)** - Override the "Test Mode" module setting.

**setTrackOpens(**_bool_ **$trackOpens)** - Override "Track Message Opens" module setting on a per-email basis.
- Open tracking only works for emails with `bodyHTML()` set

**setTrackClicks(**_bool_ **$trackClicks)** - Override "Track Message Clicks" module setting on a per-email basis.
- Click tracking only works for emails with `bodyHTML()` set

### Other

**send()** - Send the email.
- Returns a positive number (indicating number of emails sent) or 0 on failure.

**validateEmail(**_string_ **$email)** - Validates a single address using Mailgun's address validation service.
- Returns an associative array. To return the response as an object, set the second argument to false
- For more information on what this method returns, see [Mailgun's documentation](https://documentation.mailgun.com/api-email-validation.html#email-validation).

**getHttpCode()** - Get the API HTTP response code.
- A response code of `200` indicates a successful response

**getDomainName()** - Get the API domain name.
- Returns the config setting, or dynamic value for the mail instance if this is enabled

## Examples

### Basic Example
Send an email:

```php
$mg = $mail->new();
$sent = $mg->to("user@domain.com")
	->from("you@company.com")
	->subject("Message Subject")
	->body("Message Body")
	->send();
```

### Advanced Example
Send an email using all supported WireMail methods and extra methods implemented by WireMailMailgun:
```php
$mg = $mail->new();

// WireMail methods
$mg->to([
		"user@domain.com" => "A User", 
		"user2@domain.com" => "Another User",
	])
	->from("you@company.com", "Company Name")
	->replyTo("reply@company.com", "Company Name")
	->subject("Message Subject")
	->bodyHTML("<p>Message Body</p>") // A text version will be automatically created
	->header("key1", "value1")
	->headers(["key2" => "value2"])
	->attachment("/path/to/file.ext", "filename.ext");

// WireMailMailgun methods
$mg->cc("cc@domain.com")
	->bcc(["bcc@domain.com", "bcc2@domain.com"])
	->addData("key", "value") // Custom X-Mailgun-Variables data
	->addInlineImage($pageimage->filename) // Remember to add <img src="cid:$pageimage->basename"> to the HTML
	->addTag("tag1") // Add a single tag
	->addTags(["tag2", "tag3"]) // Add tags in a batch
	->setBatchMode(false) // A single email will be sent, both "to" recipients shown
	->setDeliveryTime(time() + 3600) // The email will be delivered in an hour
	->setTestMode(true) // Mailgun won't actually send the email
	->setTrackOpens(false) // Disable tracking opens
	->setTrackClicks(false); // Disable tracking clicks

// Batch mode is set to false, so 1 returned if successful
$numSent = $mg->send();

echo "The email was " . ($numSent ? "" : "not ") . "sent.";
```

### Validate an Email Address
```php
$mg = $mail->new();
$response = $mg->validateEmail("user@domain.com", false);

if($mg->getHttpCode() == 200) {
	echo $response->is_valid ? "Valid" : "Not valid: $response->reason";
} else {
	echo "Could not validate";
}
```
Please note: To validate an email address, your Mailgun Public API Key must be added to the module's configuration.

## Other Versions and Backward Compatibility
This module was initally developed by [plauclair](https://github.com/plauclair/), with further development from [gebeer](https://github.com/gebeer) and [outflux3](https://github.com/outflux3/). This module is a rewrite of outflux3's version, bringing the module more in line with ProcessWire conventions and [coding style guide](https://processwire.com/docs/more/coding-style-guide/) and adding some more features. This module is not compatible with these other versions, so please only use it if beginning a new project. That said, a simple existing implementation would not require much work to upgrade and test.

This module requires Processwire 3.0.123 and above. Should you wish to use WireMailMailgun on older ProcessWire versions not supported by this version of the module, you should install one of the following:
- https://github.com/plauclair/WireMailMailgun
- https://github.com/gebeer/WireMailMailgun
- https://github.com/outflux3/WireMailMailgun
