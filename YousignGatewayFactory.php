<?php

namespace Omnisign\Yousign;

use Omnisign\Config;
use Omnisign\GatewayFactory;
use Omnisign\Model\Capabilities;
use Omnisign\Model\Level;
use Omnisign\Yousign\Action\CancelAction;
use Omnisign\Yousign\Action\CreateAction;
use Omnisign\Yousign\Action\DownloadAction;
use Omnisign\Yousign\Action\FetchAction;
use Omnisign\Yousign\Action\NotifyAction;
use Omnisign\Yousign\Action\RemindAction;
use Omnisign\Yousign\Action\SendAction;
use Omnisign\Yousign\Action\SigningUrlAction;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Yousign - Youtrust since its new name: simple, advanced and qualified
 * electronic signatures, by e-mail or in the application's page.
 *
 *   options:
 *     api_key: '%env(YOUSIGN_API_KEY)%'          # required
 *     sandbox: false                             # true: api-sandbox.yousign.app - signatures without legal value
 *     webhook_secret: '%env(default::YOUSIGN_WEBHOOK_SECRET)%'   # the subscription's secret: notify() checks X-Yousign-Signature-256 with it
 *     url: ~                                     # another API root
 */
final class YousignGatewayFactory extends GatewayFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnisign.factory_name' => 'yousign',
            'omnisign.factory_title' => 'Yousign',
            'omnisign.required_options' => ['api_key'],
            'sandbox' => false,
            'webhook_secret' => null,
            'url' => null,
            'omnisign.capabilities' => new Capabilities([Level::SIMPLE, Level::ADVANCED, Level::QUALIFIED], embedded: true, ordered: true, identityCheck: true, thirdParty: true),
            'omnisign.api' => fn (Config $c) => new Api(
                $this->http ?? HttpClient::create(),
                (string) $c['api_key'],
                rtrim((string) ($c->get('url') ?: ($c->bool('sandbox') ? Api::SANDBOX_URL : Api::URL)), '/'),
                $c->get('webhook_secret') ?: null,
            ),
            'omnisign.action.create' => new CreateAction(),
            'omnisign.action.send' => new SendAction(),
            'omnisign.action.fetch' => new FetchAction(),
            'omnisign.action.remind' => new RemindAction(),
            'omnisign.action.cancel' => new CancelAction(),
            'omnisign.action.download' => new DownloadAction(),
            'omnisign.action.signing_url' => new SigningUrlAction(),
            'omnisign.action.notify' => static fn (Config $c) => $c->get('webhook_secret') ? new NotifyAction() : null,
        ]);
    }
}
