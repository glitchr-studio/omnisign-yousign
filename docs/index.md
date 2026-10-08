---
title: omnisign/yousign
order: 1
---

# omnisign/yousign

## Installation

```sh
composer require omnisign/yousign
```

PHP 8.2 or later, `glitchr/omnisign` and `symfony/http-client`. An API key from Yousign's app
(sandbox: free, without legal value).

## The sources

Yousign's developer site, now `developers.youtrust.com` ("Youtrust Public API v3"), read on
2026-10-08: the OpenAPI its reference pages carry (signature requests, documents, signers, activate,
cancel, send_reminder, documents/download, audit_trails/download), and the guides "Webhooks" and
"Handling Webhooks". The API keeps Yousign's hosts (`api.yousign.app/v3`,
`api-sandbox.yousign.app/v3`) and headers (`X-Yousign-Signature-256`).

## Options

| Option | Default | |
|---|---|---|
| `api_key` | required | a bearer key |
| `sandbox` | `false` | `api-sandbox.yousign.app` |
| `webhook_secret` | | the subscription's secret; without it, `notify()` is not supported |
| `url` | | another API root |

## From an envelope to a signature request

| | |
|---|---|
| `create()` | `POST /signature_requests` (`name`, `delivery_mode`: `none` when embedded, `email` otherwise; `ordered_signers`, `expiration_date`, `timezone`, `external_id`: the envelope's key) - each document `POST .../documents` (multipart: `file`, `nature`: `signable_document` or `attachment`) - each signer `POST .../signers` (`info`: first and last name, e-mail, phone, locale; `signature_level`; `signature_authentication_mode`; `fields`; `redirect_urls.success`). A `DRAFT` |
| `send()` | `POST .../activate`: `ongoing`, each signer's `signature_link` |
| `fetch()` | `GET /signature_requests/{id}` |
| `remind()` | `POST .../signers/{id}/send_reminder` for each signer notified who has not signed |
| `cancel()` | `POST .../cancel`, reason `other`, the application's words as the note |
| `download()` | `GET .../documents/download?version=completed` (a ZIP with `archive=true` when several documents) and `GET .../audit_trails/download?merge=true` |
| `signingUrl()` | the signer's `signature_link`, from `GET .../signers` |
| `notify()` | `X-Yousign-Signature-256`: `sha256=` and the HMAC-SHA-256 of the raw body under the secret; `event_name` read |

| Level | `signature_level` |
|---|---|
| `SIMPLE` | `electronic_signature` - a code by e-mail unless the signer says otherwise |
| `ADVANCED` | `advanced_electronic_signature` - Yousign verifies the signer's identity |
| `QUALIFIED` | `qualified_electronic_signature` |

`Signer::$authentication`: `email` → `otp_email`, `sms` → `otp_sms` (a phone needed), `none` →
`no_otp`. Fields: `SIGNATURE`, `MENTION` (`mention`: "Lu et approuvé" unless the field's label),
`TEXT`; Yousign places no `INITIALS` nor `DATE` field with a signer - such an envelope is refused.

| Yousign | Status | | Signer | SignerStatus |
|---|---|---|---|---|
| `draft` | `DRAFT` | | `initiated` | `WAITING` |
| `ongoing`, `approval`, `paused` | `SENT` | | `notified` | `NOTIFIED` |
| `done` | `COMPLETED` | | `verified`, `processing`, `consent_given` | `OPENED` |
| `declined`, `rejected` | `DECLINED` | | `signed` | `SIGNED` |
| `expired` | `EXPIRED` | | `declined` | `DECLINED` |
| `canceled`, `deleted` | `CANCELED` | | `aborted`, `error` | `FAILED` |

## Verified, and not

| | |
|---|---|
| Against Yousign | **not verified in real: no account** (the sandbox needs one). The answers in `Tests/Fixtures` are written from the OpenAPI's schemas |
| The calls, their bodies, the multipart upload, the webhook's signature | by the tests, against the OpenAPI and the webhook guide |
| Advanced and qualified signatures | the level is sent; the identity checks happen on Yousign's side, not observed |
