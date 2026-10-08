<?php

namespace Omnisign\Yousign\Action;

use Omnisign\Model\SignerStatus;
use Omnisign\Request\Remind;
use Omnisign\Request\Request;

/** POST /signature_requests/{id}/signers/{signerId}/send_reminder, for each signer asked who has not signed. */
final class RemindAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof Remind;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Remind);
        $envelope = $request->envelope;
        foreach ($envelope->states as $key => $state) {
            if ((null === $request->signer || $request->signer === $key) && null !== $state->reference && SignerStatus::NOTIFIED === $state->status) {
                $this->api->json('POST', '/signature_requests/'.$envelope->reference().'/signers/'.$state->reference.'/send_reminder');
            }
        }
        $request->setResult($envelope);
    }
}
