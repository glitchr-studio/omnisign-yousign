<?php

namespace Omnisign\Yousign\Action;

use Omnisign\Request\Cancel;
use Omnisign\Request\Request;

/** POST /signature_requests/{id}/cancel: reason "other", the application's words as the note. */
final class CancelAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof Cancel;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Cancel);
        $request->setResult(self::read($request->envelope, $this->api->json('POST', '/signature_requests/'.$request->envelope->reference().'/cancel', array_filter(['reason' => 'other', 'custom_note' => null !== $request->reason ? mb_substr($request->reason, 0, 500) : null]))));
    }
}
