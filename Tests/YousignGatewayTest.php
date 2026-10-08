<?php

namespace Omnisign\Yousign\Tests;

use Omnisign\Exception\InvalidConfigException;
use Omnisign\Exception\InvalidKeyException;
use Omnisign\Exception\InvalidNotificationException;
use Omnisign\Exception\ProviderException;
use Omnisign\GatewayInterface;
use Omnisign\Model\Document;
use Omnisign\Model\Envelope;
use Omnisign\Model\Event;
use Omnisign\Model\Field;
use Omnisign\Model\FieldType;
use Omnisign\Model\File;
use Omnisign\Model\Level;
use Omnisign\Model\Signer;
use Omnisign\Model\SignerStatus;
use Omnisign\Model\Status;
use Omnisign\Request\Notify;
use Omnisign\Yousign\YousignGatewayFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Answers written from Yousign's public API v3 (Youtrust Public API v3),
 * its OpenAPI as the reference pages carry it, read on 2026-10-08: no
 * account was used.
 */
final class YousignGatewayTest extends TestCase
{
    private const ID = '9a2b1c3d-0e4f-4a5b-8c6d-7e8f9a0b1c2d';

    /** @var list<array{string, string, array<string, mixed>}> */
    private array $calls = [];

    /** @param list<string|MockResponse> $answers */
    private function gateway(array $answers, array $options = []): GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$answers): MockResponse {
            $this->calls[] = [$method, $url, $options];
            $answer = array_shift($answers) ?? throw new \LogicException('No answer left for '.$method.' '.$url);
            if ($answer instanceof MockResponse) {
                return $answer;
            }
            [$file, $status] = explode(':', $answer.':200');

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$file.'.json'), ['http_code' => (int) $status, 'response_headers' => ['content-type' => 'application/json']]);
        });

        return (new YousignGatewayFactory($http))->create($options + ['api_key' => 'ys-api-key', 'sandbox' => true]);
    }

    private static function lease(Level $level = Level::SIMPLE): Envelope
    {
        return new Envelope(
            'Bail - Maison Érable',
            [new Document('lease', new File('%PDF-1.7 lease', 'bail.pdf'))],
            [new Signer('landlord', 'Marco Meyer', 'marco@meyers.example', order: 1), new Signer('tenant', 'Camille Érable', 'camille@erable.example', order: 2)],
            [new Field('landlord', 'lease', page: 3, x: 77, y: 581), new Field('tenant', 'lease', page: 3, x: 330, y: 581), new Field('tenant', 'lease', FieldType::MENTION, 3, 330, 650)],
            $level,
            ordered: true,
            embedded: true,
            expiresAt: new \DateTimeImmutable('2026-11-07 23:59', new \DateTimeZone('Europe/Paris')),
            key: 'lease-42',
        );
    }

    /** @return array<string, mixed> */
    private function body(int $call): array
    {
        return json_decode((string) $this->calls[$call][2]['body'], true);
    }

    public function testASignatureRequestIsBuiltInFourCallsThenActivated(): void
    {
        $gateway = $this->gateway(['signature-request-draft:201', 'document:201', 'signer-landlord:201', 'signer-tenant:201', 'activate:201']);
        $created = $gateway->create(self::lease());

        self::assertSame([self::ID, Status::DRAFT], [$created->reference, $created->status]);
        self::assertSame(['landlord' => '5e1a0b2c-3d4e-4f5a-8b6c-7d8e9f0a1b2c', 'tenant' => '6f2b1c3d-4e5f-4a6b-9c7d-8e9f0a1b2c3d'], array_map(static fn ($s) => $s->reference, $created->states));
        self::assertSame(['POST', 'https://api-sandbox.yousign.app/v3/signature_requests'], [$this->calls[0][0], $this->calls[0][1]]);
        self::assertSame(['name' => 'Bail - Maison Érable', 'delivery_mode' => 'none', 'ordered_signers' => true, 'expiration_date' => '2026-11-07', 'timezone' => 'Europe/Paris', 'external_id' => 'lease-42'], $this->body(0));
        self::assertStringContainsString('Authorization: Bearer ys-api-key', implode("\n", $this->calls[0][2]['headers']));

        self::assertSame('https://api-sandbox.yousign.app/v3/signature_requests/'.self::ID.'/documents', $this->calls[1][1]);
        self::assertStringContainsString("name=\"nature\"\r\n\r\nsignable_document\r\n", (string) $this->calls[1][2]['body']);
        self::assertStringContainsString("filename=\"bail.pdf\"\r\nContent-Type: application/pdf\r\n\r\n%PDF-1.7 lease\r\n", (string) $this->calls[1][2]['body']);

        $tenant = $this->body(3);
        self::assertSame(['first_name' => 'Camille', 'last_name' => 'Érable', 'email' => 'camille@erable.example', 'locale' => 'fr'], $tenant['info']);
        self::assertSame(['electronic_signature', 'otp_email'], [$tenant['signature_level'], $tenant['signature_authentication_mode']]);
        self::assertSame([
            ['document_id' => 'd0c1a2b3-c4d5-4e6f-8a7b-9c0d1e2f3a4b', 'page' => 3, 'x' => 330, 'y' => 581, 'type' => 'signature'],
            ['document_id' => 'd0c1a2b3-c4d5-4e6f-8a7b-9c0d1e2f3a4b', 'page' => 3, 'x' => 330, 'y' => 650, 'type' => 'mention', 'mention' => 'Lu et approuvé'],
        ], $tenant['fields']);

        $sent = $gateway->send($created);
        self::assertSame([Status::SENT, SignerStatus::NOTIFIED, 'https://yousign.app/signatures/9a2b1c3d?s=landlord-token'], [$sent->status, $sent->state('landlord')->status, $sent->state('landlord')->link]);
        self::assertSame('https://api-sandbox.yousign.app/v3/signature_requests/'.self::ID.'/activate', $this->calls[4][1]);
    }

    public function testLevelsAndAuthentications(): void
    {
        $this->gateway(['signature-request-draft:201', 'document:201', 'signer-landlord:201', 'signer-tenant:201'])->create(self::lease(Level::QUALIFIED));
        self::assertSame('qualified_electronic_signature', $this->body(2)['signature_level']);
        self::assertArrayNotHasKey('signature_authentication_mode', $this->body(2), 'Yousign\'s own default above the simple level');

        $this->calls = [];
        $envelope = new Envelope('x', [new Document('d', new File('%PDF', 'd.pdf'))], [new Signer('s', 'Solo', 's@example.org', phone: '+33612345678', authentication: 'sms')], [new Field('s', 'd', FieldType::INITIALS)]);
        try {
            $this->gateway(['signature-request-draft:201', 'document:201'])->create($envelope);
            self::fail();
        } catch (InvalidConfigException $e) {
            self::assertStringContainsString('Yousign places no "initials" field', $e->getMessage());
        }
    }

    public function testFollowRemindSignInThePageCancel(): void
    {
        $gateway = $this->gateway(['signature-request-draft:201', 'document:201', 'signer-landlord:201', 'signer-tenant:201', 'activate:201', 'signers', new MockResponse('', ['http_code' => 201]), 'signature-request-done', 'canceled:201']);
        $sent = $gateway->send($gateway->create(self::lease()));

        self::assertSame('https://yousign.app/signatures/9a2b1c3d?s=landlord-token-2', $gateway->signingUrl($sent, 'landlord')->url, 'asked again, a fresh link');
        $gateway->remind($sent);
        self::assertSame('https://api-sandbox.yousign.app/v3/signature_requests/'.self::ID.'/signers/5e1a0b2c-3d4e-4f5a-8b6c-7d8e9f0a1b2c/send_reminder', $this->calls[6][1], 'the one asked who has not signed - not the one waiting for their turn');

        $done = $gateway->fetch($sent);
        self::assertSame([Status::COMPLETED, SignerStatus::SIGNED, '2026-10-09T17:30:00+00:00'], [$done->status, $done->state('tenant')->status, $done->completedAt?->format(\DATE_ATOM)]);
        $canceled = $gateway->cancel($sent, 'Changed our minds');
        self::assertSame([Status::CANCELED, SignerStatus::FAILED], [$canceled->status, $canceled->state('tenant')->status]);
        self::assertSame(['reason' => 'other', 'custom_note' => 'Changed our minds'], json_decode((string) $this->calls[8][2]['body'], true));
    }

    public function testTheSignedDocumentAndTheAuditTrail(): void
    {
        $gateway = $this->gateway([new MockResponse('%PDF signed', ['response_headers' => ['content-type' => 'application/pdf']]), new MockResponse('%PDF trail', ['response_headers' => ['content-type' => 'application/pdf']])]);
        $download = $gateway->download(self::lease()->with(reference: self::ID));

        self::assertSame(['%PDF signed', 'bail.pdf', 'application/pdf'], [$download->documents[0]->content, $download->documents[0]->filename, $download->documents[0]->mimeType]);
        self::assertSame(['%PDF trail', 'audit-trail.pdf'], [$download->evidence?->content, $download->evidence?->filename]);
        self::assertSame(['version' => 'completed', 'archive' => 'false'], $this->calls[0][2]['query']);
        self::assertSame(['merge' => 'true'], $this->calls[1][2]['query']);
    }

    public function testAWebhooksSignatureIsChecked(): void
    {
        $body = (string) file_get_contents(__DIR__.'/Fixtures/webhook-signer-done.json');
        $gateway = $this->gateway([], ['webhook_secret' => 'whk-secret']);

        $notification = $gateway->notify($body, ['x-yousign-signature-256' => 'sha256='.hash_hmac('sha256', $body, 'whk-secret')]);
        self::assertSame([Event::SIGNED, 'signer.done', self::ID, '6f2b1c3d-4e5f-4a6b-9c7d-8e9f0a1b2c3d', 'b6c63685-c556-4a30-8fe9-b6f2b187d936'], [$notification->event, $notification->type, $notification->reference, $notification->signer, $notification->id]);

        $this->expectException(InvalidNotificationException::class);
        $gateway->notify($body, ['X-Yousign-Signature-256' => 'sha256='.hash_hmac('sha256', $body, 'another')]);
    }

    public function testErrorsAndWhatIsNotConfigured(): void
    {
        self::assertFalse($this->gateway([])->supports(Notify::class), 'no secret, no webhook');
        self::assertSame(['simple', 'advanced', 'qualified'], $this->gateway([])->capabilities()->toArray()['levels']);
        try {
            $this->gateway(['error-400:400'])->create(self::lease());
            self::fail();
        } catch (ProviderException $e) {
            self::assertSame('[yousign] POST /signature_requests: HTTP 400, Invalid request body (info.email: This value is not a valid email address.)', $e->getMessage());
        }
        $this->expectException(InvalidKeyException::class);
        $this->gateway([new MockResponse('{"detail":"Unauthorized"}', ['http_code' => 401])])->fetch(self::lease()->with(reference: self::ID));
    }
}
