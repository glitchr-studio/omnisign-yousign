<?php

namespace Omnisign\Yousign\Action;

use Omnisign\Exception\InvalidConfigException;
use Omnisign\Exception\ProviderException;
use Omnisign\Model\SigningUrl;
use Omnisign\Request\Request;
use Omnisign\Request\SigningUrl as SigningUrlRequest;

/**
 * A signer's signature_link, as GET .../signers lists it - given for an
 * envelope sent embedded (delivery_mode none), once activated.
 */
final class SigningUrlAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof SigningUrlRequest;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof SigningUrlRequest);
        $envelope = $request->envelope;
        $reference = $envelope->state($request->signer)?->reference ?? throw new InvalidConfigException(\sprintf('The signer "%s" was not created on Yousign with this envelope.', $request->signer));
        foreach ($this->api->json('GET', '/signature_requests/'.$envelope->reference().'/signers') as $signer) {
            if (\is_array($signer) && ($signer['id'] ?? null) === $reference && !empty($signer['signature_link'])) {
                $request->setResult(new SigningUrl((string) $signer['signature_link'], isset($signer['signature_link_expiration_date']) ? new \DateTimeImmutable($signer['signature_link_expiration_date']) : null));

                return;
            }
        }

        throw new ProviderException('yousign', \sprintf('Yousign gives no signature link for the signer "%s": an envelope sent embedded, and activated, has one.', $request->signer));
    }
}
