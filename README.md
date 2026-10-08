# omnisign/yousign

**Yousign** - Youtrust since the company's new name - for
[glitchr/omnisign](https://github.com/glitchr-studio/omnisign): simple, advanced and qualified
electronic signatures (eIDAS) through its public API v3, by e-mail or in the application's page.

```php
use Omnisign\Yousign\YousignGatewayFactory;

$gateway = (new YousignGatewayFactory($httpClient))->create(['api_key' => getenv('YOUSIGN_API_KEY'), 'sandbox' => true]);

$envelope = $gateway->send($gateway->create($lease));     // a signature request, its documents, signers and fields, activated
$gateway->signingUrl($envelope, 'tenant')->url;           // the signer's signature_link (embedded)
$gateway->download($gateway->fetch($envelope));           // the signed PDF and the audit trail, once done
```

Written from Yousign's public API v3 OpenAPI, read on 2026-10-08. **Not verified in real: no
account.**

[Documentation](docs/index.md): the options, how an envelope becomes a signature request, levels
and authentication, the webhooks, what was verified.

License: LGPL-3.0-or-later.
