<?php

namespace Omnisign\Yousign\Action;

use Omnisign\Action\ActionInterface;
use Omnisign\Action\ApiAwareInterface;
use Omnisign\Action\ApiAwareTrait;
use Omnisign\Model\Envelope;
use Omnisign\Model\SignerState;
use Omnisign\Model\Status;
use Omnisign\Yousign\Api;

/** An action on Yousign's API v3. */
abstract class AbstractAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    /**
     * The envelope as a signature request answered it: its status, and its
     * signers' - found back by the order they were added in.
     *
     * @param array<string, mixed> $request
     */
    protected static function read(Envelope $envelope, array $request): Envelope
    {
        $states = $envelope->states;
        $byReference = [];
        foreach ($states as $key => $state) {
            if (null !== $state->reference) {
                $byReference[$state->reference] = $key;
            }
        }
        foreach ((array) ($request['signers'] ?? []) as $signer) {
            $key = $byReference[$signer['id'] ?? ''] ?? null;
            if (null === $key) {
                continue;
            }
            $old = $states[$key];
            $status = Api::SIGNER_STATUSES[$signer['status'] ?? ''] ?? $old->status;
            $states[$key] = new SignerState($key, $old->reference, $status, $old->signedAt, $signer['signature_link'] ?? $old->link, isset($signer['signature_link_expiration_date']) ? new \DateTimeImmutable($signer['signature_link_expiration_date']) : $old->linkExpiresAt, $old->reason);
        }

        return $envelope->with(
            reference: (string) ($request['id'] ?? $envelope->reference),
            status: Api::STATUSES[$request['status'] ?? ''] ?? Status::UNKNOWN,
            states: $states,
            completedAt: isset($request['completed_at']) ? new \DateTimeImmutable($request['completed_at']) : null,
            data: $request,
        );
    }
}
