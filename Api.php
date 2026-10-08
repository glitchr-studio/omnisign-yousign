<?php

namespace Omnisign\Yousign;

use Omnisign\Exception\InvalidKeyException;
use Omnisign\Exception\InvalidNotificationException;
use Omnisign\Exception\ProviderException;
use Omnisign\Http\Answer;
use Omnisign\Http\Multipart;
use Omnisign\Model\SignerStatus;
use Omnisign\Model\Status;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Yousign's public API v3 - "Youtrust Public API v3" since the company's
 * new name; the hosts and headers keep Yousign's: a bearer API key,
 * JSON, a multipart upload for documents.
 */
final class Api
{
    public const PROVIDER = 'yousign';
    public const URL = 'https://api.yousign.app/v3';
    public const SANDBOX_URL = 'https://api-sandbox.yousign.app/v3';

    /** A signature request's status, as the family's. */
    public const STATUSES = [
        'draft' => Status::DRAFT, 'ongoing' => Status::SENT, 'approval' => Status::SENT, 'paused' => Status::SENT,
        'done' => Status::COMPLETED, 'declined' => Status::DECLINED, 'rejected' => Status::DECLINED,
        'expired' => Status::EXPIRED, 'canceled' => Status::CANCELED, 'deleted' => Status::CANCELED,
    ];

    /** A signer's status, as the family's. */
    public const SIGNER_STATUSES = [
        'initiated' => SignerStatus::WAITING, 'notified' => SignerStatus::NOTIFIED,
        'verified' => SignerStatus::OPENED, 'processing' => SignerStatus::OPENED, 'consent_given' => SignerStatus::OPENED,
        'signed' => SignerStatus::SIGNED, 'declined' => SignerStatus::DECLINED, 'aborted' => SignerStatus::FAILED, 'error' => SignerStatus::FAILED,
    ];

    public function __construct(
        private readonly HttpClientInterface $http,
        #[\SensitiveParameter] private readonly string $apiKey,
        public readonly string $url = self::URL,
        #[\SensitiveParameter] private readonly ?string $webhookSecret = null,
    ) {
    }

    /**
     * @param array<string, mixed> $json
     *
     * @return array<string, mixed>
     */
    public function json(string $method, string $path, array $json = []): array
    {
        $answer = $this->call($method, $path, $json ? ['json' => $json] : []);

        return '' === trim($answer->body) ? [] : $answer->json();
    }

    /** @return array<string, mixed> the document uploaded */
    public function upload(string $path, string $content, string $filename, string $type, bool $signable): array
    {
        $multipart = Multipart::build(['nature' => $signable ? 'signable_document' : 'attachment'], ['file' => ['content' => $content, 'filename' => $filename, 'type' => $type]]);

        return $this->call('POST', $path, ['headers' => ['Content-Type' => $multipart['contentType']], 'body' => $multipart['body']])->json();
    }

    /** A file: a PDF, or a ZIP when several. */
    public function download(string $path, array $query = []): Answer
    {
        return $this->call('GET', $path, ['query' => $query, 'headers' => ['Accept' => 'application/pdf, application/zip']]);
    }

    /**
     * A webhook's call, its X-Yousign-Signature-256 checked: "sha256=" and
     * the HMAC-SHA-256 of the raw body under the subscription's secret.
     *
     * @return array<string, mixed>
     */
    public function verify(string $body, ?string $signature): array
    {
        if (null === $this->webhookSecret) {
            throw new InvalidNotificationException(self::PROVIDER, 'No webhook secret is configured.');
        }
        if (null === $signature || !hash_equals('sha256='.hash_hmac('sha256', $body, $this->webhookSecret), $signature)) {
            throw new InvalidNotificationException(self::PROVIDER, 'The signature does not hold.');
        }
        $data = json_decode($body, true);

        return \is_array($data) ? $data : throw new InvalidNotificationException(self::PROVIDER, 'The callback is not JSON.');
    }

    /** @param array<string, mixed> $options */
    private function call(string $method, string $path, array $options): Answer
    {
        $options['headers'] = ['Authorization' => 'Bearer '.$this->apiKey] + ($options['headers'] ?? []);
        $answer = Answer::send($this->http, self::PROVIDER, $method, $this->url.$path, $options);
        if (\in_array($answer->status, [401, 403], true)) {
            throw new InvalidKeyException(self::PROVIDER, \sprintf('The API key was refused (HTTP %d): %s', $answer->status, self::error($answer)), (string) $answer->status);
        }
        if ($answer->status >= 400) {
            throw new ProviderException(self::PROVIDER, \sprintf('%s %s: HTTP %d, %s', $method, $path, $answer->status, self::error($answer)), (string) $answer->status);
        }

        return $answer;
    }

    private static function error(Answer $answer): string
    {
        $error = json_decode($answer->body, true);
        if (!\is_array($error)) {
            return mb_substr(trim($answer->body), 0, 200) ?: 'no detail';
        }
        $invalid = array_map(static fn ($p) => \is_array($p) ? ($p['name'] ?? '?').': '.($p['reason'] ?? '') : (string) $p, (array) ($error['invalid_params'] ?? []));

        return trim(($error['detail'] ?? $error['title'] ?? $error['type'] ?? 'an error').($invalid ? ' ('.implode('; ', $invalid).')' : ''));
    }
}
