# WireMailgun - Agent API Reference

Machine-oriented reference for the `WireMailgun` module. For human-oriented
setup/usage docs and narrative examples, see `README.md` in this same
directory.

This file documents the *actual code-level API surface* an agent needs to
generate correct template/module code against this module: methods,
property names, option keys, defaults, return types, and known gotchas.

## Module identity

- Class: `WireMailgun` (file `WireMailgun.module`), extends `WireMail`, implements `Module`, `ConfigurableModule`
- Config class: `WireMailgunConfig` (file `WireMailgunConfig.php`, extends `ModuleConfig`)
- `autoload`: `false`
- `singular`: `false` (a new instance is created each time, standard `WireMail` behaviour via `$mail = wireMail()` / `wire('mail')->new()`)
- Requires: ProcessWire >= 3.0.123, PHP >= 5.6
- `#pw-var $mg` - conventional variable name used in docblocks/examples

## Getting an instance

```php
$mg = wireMail(); // or wire('mail')->new(), returns a WireMailgun instance
                   // if WireMailgun is the configured WireMail module.
```

## Constants

| Constant | Value | Description |
| --- | --- | --- |
| `WireMailgun::batchLimit` | `1000` | Mailgun's hard limit on recipients per batch-mode API request. Recipients beyond this are automatically deferred and sent in further chunked requests within the same `send()` call (see `___send()` behaviour below). |
| `WireMailgun::regions` | `['us' => 'US', 'eu' => 'EU']` | Valid Mailgun regions, used to populate the config screen and validate `setRegion()`/`setSender()` input. |
| `WireMailgun::version` | `3` | Mailgun API version used for the messages/sending endpoint. |
| `WireMailgun::versionValidation` | `4` | Mailgun API version used for `validateEmail()` (always against the `us` endpoint regardless of configured region - this is a Mailgun API constraint, not configurable). |

## Module config properties

Set via Modules > Configure > WireMailgun, or read/written directly off the
module instance, e.g. `$mg->domain`, `$mg->trackOpens`.

| Property | Type | Default | Notes |
| --- | --- | --- | --- |
| `apiKey` | string | `''` (required) | Mailgun API key. Override per-instance with `setApiKey()`. |
| `domain` | string | `''` (required) | Verified sending domain. Override per-instance with `setDomainName()`. |
| `region` | string | `'us'` | `'us'` or `'eu'`, see `WireMailgun::regions`. Override per-instance with `setRegion()`. |
| `fromEmail` | string | `''` | Falls back to `processwire@{domain}` if empty and not overridden via `WireMail::from()`. |
| `fromEmailName` | string | `''` | Falls back to `'ProcessWire'` if empty and not overridden via `WireMail::from()`. |
| `batchMode` | bool | `1` if `ProMailer` is installed, else `0` | See `setBatchMode()` below. |
| `trackOpens` | bool | `1` | Only has effect on HTML emails (Mailgun/email client constraint). Override per-send with `setTrackOpens()`. |
| `trackClicks` | bool | `1` | Only has effect on HTML emails. Override per-send with `setTrackClicks()`. |
| `testMode` | bool | `0` | When enabled, Mailgun accepts but does not actually deliver messages. Override per-send with `setTestMode()`. Automatically forced `false` for ProcessWire's core password-reset email (see gotcha #4 below). |
| `disableSslCheck` | bool | `0` | Disables cURL peer SSL verification. Not recommended in production. |

## Sending an email

Standard `WireMail` chainable API (`to()`, `from()`, `subject()`, `body()`,
`bodyHTML()`, `attachment()`, etc.) plus the module-specific methods below.
Call `send()` last; it returns an `int` (number of emails actually sent, `0`
on failure) - it never throws for delivery-level failures, only logs them
(see `getHttpCode()` below to inspect the last HTTP response code).

```php
$mg = wireMail();
$sent = $mg->to('user@example.com')
	->from('you@yourdomain.com', 'Your Name')
	->subject('Hello')
	->body('Plain text body')
	->bodyHTML('<p>HTML body</p>')
	->send();
```

### `attachment($value, $filename = '')`

Overrides `WireMail::attachment()`. Throws `WireException` immediately if
PHP's `curl_file_create()` is not available (requires PHP >= 5.5 with cURL)
- attachments/inline images cannot work at all without it.

### `cc($email = null, $name = null)` / `bcc($email = null, $name = null)`

Same accepted argument forms as core `WireMail::to()`. Passing `null` clears
all previously set CC/BCC addresses for this instance. **Ignored entirely
when batch mode is active** - see gotcha #1 below.

### `replyTo($email, $name = null)`

Thin override of `WireMail::replyTo()` (present mainly for docblock/API
clarity - behaviour is unchanged from core).

### `addData($key, $value)`

Attaches arbitrary custom data to the message
(`v:{key}` form field per Mailgun's "attaching data" feature). Both `$key`
and `$value` are passed through `sanitize()` (HTML-entities-encoded and
truncated to 128 characters - see "Sanitisation" below).

### `addInlineImage($file, $filename = null)`

Registers a file to be sent as `inline[n]` (referenceable in HTML body via
`cid:{filename}`). `$filename` defaults to the basename of `$file` if not
given.

### `addRecipientVariables(array $recipients, $override = true)`

Only meaningful in batch mode. `$recipients` is an associative array of
`email => data`, where `data` is either a string (shorthand for
`['name' => data]`) or an array of arbitrary per-recipient template
variables. Repeated calls for the same email address merge data together;
`$override` controls whether new data takes precedence (`true`, default) or
existing data does (`false`) on key collisions. Invalid emails are silently
skipped (not thrown).

### `addTag($tag)` / `addTags(array $tags)`

Adds up to 3 tags total (Mailgun hard limit) - each tag is passed through
`sanitize()`. A 4th+ `addTag()` call is a no-op that logs a warning rather
than throwing. `addTags()` is just a loop over `addTag()`.

### `setApiKey($key)` / `setDomainName($domain)` / `setRegion($region)`

Per-instance overrides of the corresponding module config values. `setRegion()`
lower-cases its input and silently no-ops (does not throw) if not a valid key
in `WireMailgun::regions`. As of the current version, a successful call also
updates `$this->region` itself (not just the internal API URL) - see
CHANGELOG for the historical bug where this was not the case.

### `setSender($domain, $key, $region = '')`

Convenience wrapper calling `setDomainName()`, `setApiKey()`, and
`setRegion()` together in one call.

### `setBatchMode(bool $batchMode)`

Enables/disables batch mode for this send only. In batch mode:

- Each recipient in `to()` only sees themselves (no other recipients
  exposed), unlike default `WireMail`/SMTP behaviour.
- `to()` names supplied via the normal `to()` call are automatically merged
  into `recipientVariables` (as `name`) if not already present.
- **CC/BCC addresses set via `cc()`/`bcc()` are ignored** - a warning is
  logged listing the ignored addresses, but they are never sent.
- If more than `WireMailgun::batchLimit` (1000) recipients are set, `send()`
  automatically splits them into multiple sequential API requests and sums
  the returned count - this is transparent to the caller.

### `setCampaign($track = true, ...$tags)`

Variadic: any additional string arguments passed after `$track` are added as
tags via `addTags()`. If `$track` is truthy (default) and at least one tag
string was passed, both `setTrackClicks(true)` and `setTrackOpens(true)` are
also called. Passing only `setCampaign(false)` neither adds tags nor forces
tracking.

### `setDeliveryTime($time)`

`$time` is a Unix timestamp; internally converted to Mailgun's required RFC
2822 GMT date string format via `gmdate('r', $time)`.

### `setTestMode(bool)` / `setTrackOpens(bool)` / `setTrackClicks(bool)`

Per-send overrides of the corresponding module config values.

### `useTemplate($template, array $variables)`

Switches the send to use a pre-defined Mailgun template (`template` form
field) instead of the `html`/`text` body - `$variables` become `v:{key}`
custom data (each value passed through raw, **not** sanitised/truncated
unlike `addData()`). When a template is set, any `bodyHTML()` content is
ignored for the purposes of the `html` field (the template takes over
rendering), though `text` (plain-text fallback) is still sent from `body()`.

### `validateEmail($email, $assoc = true)`

Calls Mailgun's separate address-validation API (**always** against the
`us` region endpoint, per Mailgun's own API constraint - regardless of the
module's/instance's configured region). Returns `false` immediately
(without an API call) if `$email` fails `$sanitizer->email()`. Returns
`json_decode()` of the raw response - an `array` if `$assoc` is `true`
(default), otherwise a `stdClass` object; returns `null` if the response
body itself was not valid JSON, or `false` if the underlying cURL request
itself failed (see `getHttpCode()`).

### `getHttpCode()`

Returns the `int` HTTP response code (e.g. `200`, `400`, `401`) from the
most recent Mailgun API request made by this instance (send or validate).
`0` if no request has been attempted yet, or if the cURL request itself
failed before a response was received.

## `___send()` behaviour notes

- Hookable (`___send`), matching core `WireMail::send()` convention -
  hook `before`/`after WireMailgun::send` to inspect/modify behaviour.
- Automatically sets the `sender` header when the `from` address' domain
  differs from the configured `domain` (helps deliverability/SPF-adjacent
  concerns for cross-domain sends).
- Automatically forces `testMode = false`, `trackOpens = false`,
  `trackClicks = false` when the outgoing subject exactly matches
  ProcessWire's core `ProcessForgotPassword` module's configured email
  subject - this is a safety measure so password-reset emails are never
  accidentally suppressed by a test-mode module setting.
- Non-batch mode: if `recipientVariables` were set anyway (e.g. leftover
  from a previous call chain), they are ignored and a warning is logged
  rather than silently sent or thrown.
- Attachments/inline images require `curl_file_create()` (see `attachment()`
  above) - `send()` throws `WireException` at send time if attachments/inline
  images are queued but the function is unavailable (rather than at the
  point they were added).
- Returns `0` (not an exception) for any Mailgun API/HTTP-level failure;
  check `getHttpCode()` and the ProcessWire log (module logs under its own
  name) for the reason.

## Sanitisation

`sanitize()` (used by `addData()` values/keys and `addTag()`) HTML-entity
encodes and truncates the value to 128 characters (Mailgun's practical limit
for these fields) - this is lossy for already-long strings; do not rely on
round-tripping the exact original value back out of Mailgun's webhooks/API
for values you pass through this method.

## Known gotchas for agents generating code against this module

1. **CC/BCC are silently ignored in batch mode.** If `setBatchMode(true)` is
   active, any `cc()`/`bcc()` addresses set on the instance will not be
   delivered - only a log warning is produced. Don't rely on CC/BCC + batch
   mode together; restructure as individual non-batch sends if both are
   required.
2. **`recipientVariables` are meaningless outside batch mode** - they are
   collected but never sent, and a warning is logged. Only call
   `addRecipientVariables()` when batch mode is (or will be) enabled.
3. **`validateEmail()` always uses the `us` endpoint**, even if the module
   or instance region is set to `eu` - this is a Mailgun platform constraint,
   not a bug, and is not configurable.
4. **Password-reset emails bypass `testMode`/tracking overrides.** If your
   application relies on `testMode` being enabled to prevent real email
   during development/staging, be aware ProcessWire's own forgot-password
   flow will still send for real (by design, to avoid locking out testers).
5. **`useTemplate()`'s `$variables` are not sanitised/truncated** the way
   `addData()` values are - pass already-safe values if the template engine
   on Mailgun's side is sensitive to HTML-entity-encoded input.
6. **Batch mode has a hard 1000-recipient-per-request Mailgun limit**
   (`WireMailgun::batchLimit`) - `send()` handles chunking this
   automatically and transparently sums the sent count, but each chunk is a
   *separate* Mailgun API call; a failure partway through a large batch will
   still return a partial `int > 0` count for the chunks that succeeded
   before the failure, not `0` or an exception.
7. **`getMimeType()` fallback chain**: uses `mime_content_type()`, then
   `finfo_open()`, then a large built-in extension-to-mime-type lookup table
   as a last resort - if none of these determine a type for an attachment,
   that specific attachment/inline image is silently skipped (a log warning
   is written, but `send()` still proceeds without throwing).
8. **`getHttpCode()` reflects the *last* API request only.** In batch mode
   with deferred (>1000 recipient) chunking, or across multiple `send()`
   calls on the same instance, only the most recent request's code is
   available - it is overwritten on every `apiRequest()` call, including
   `validateEmail()`'s.
