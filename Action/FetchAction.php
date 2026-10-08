<?php

namespace Omnisign\Yousign\Action;

use Omnisign\Request\Fetch;
use Omnisign\Request\Request;

/** GET /signature_requests/{id}: its status and its signers'. */
final class FetchAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof Fetch;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Fetch);
        $request->setResult(self::read($request->envelope, $this->api->json('GET', '/signature_requests/'.$request->envelope->reference())));
    }
}
