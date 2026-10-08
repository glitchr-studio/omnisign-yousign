<?php

namespace Omnisign\Yousign\Action;

use Omnisign\Request\Request;
use Omnisign\Request\Send;

/** POST /signature_requests/{id}/activate: ongoing; embedded, each signer's signature_link comes back. */
final class SendAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof Send;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Send);
        $request->setResult(self::read($request->envelope, $this->api->json('POST', '/signature_requests/'.$request->envelope->reference().'/activate')));
    }
}
