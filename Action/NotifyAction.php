<?php

namespace Omnisign\Yousign\Action;

use Omnisign\Model\Event;
use Omnisign\Model\Notification;
use Omnisign\Request\Notify;
use Omnisign\Request\Request;

/** A webhook's call: X-Yousign-Signature-256 checked, event_name read. */
final class NotifyAction extends AbstractAction
{
    private const EVENTS = [
        'signature_request.activated' => Event::SENT,
        'signature_request.done' => Event::COMPLETED,
        'signature_request.declined' => Event::DECLINED,
        'signature_request.rejected' => Event::DECLINED,
        'signature_request.expired' => Event::EXPIRED,
        'signature_request.canceled' => Event::CANCELED,
        'signer.link_opened' => Event::OPENED,
        'signer.done' => Event::SIGNED,
        'signer.declined' => Event::DECLINED,
    ];

    public function supports(Request $request): bool
    {
        return $request instanceof Notify;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Notify);
        $payload = $this->api->verify($request->body, $request->header('X-Yousign-Signature-256'));
        $type = (string) ($payload['event_name'] ?? '');
        $data = (array) ($payload['data'] ?? []);
        $request->setResult(new Notification(
            self::EVENTS[$type] ?? Event::OTHER,
            $type,
            $data['signature_request']['id'] ?? null,
            $data['signer']['id'] ?? null,
            isset($payload['event_time']) && ctype_digit((string) $payload['event_time']) ? new \DateTimeImmutable('@'.$payload['event_time']) : null,
            $payload['event_id'] ?? null,
            $payload,
        ));
    }
}
