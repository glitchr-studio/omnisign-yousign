<?php

namespace Omnisign\Yousign\Action;

use Omnisign\Model\Download;
use Omnisign\Model\File;
use Omnisign\Request\Download as DownloadRequest;
use Omnisign\Request\Request;

/**
 * The signed documents (GET .../documents/download?version=completed: a
 * PDF, a ZIP when several) and the audit trail (GET .../audit_trails/download?merge=true).
 */
final class DownloadAction extends AbstractAction
{
    public function supports(Request $request): bool
    {
        return $request instanceof DownloadRequest;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof DownloadRequest);
        $id = $request->envelope->reference();
        $several = \count($request->envelope->documents) > 1;
        $signed = $this->api->download("/signature_requests/$id/documents/download", ['version' => 'completed', 'archive' => $several ? 'true' : 'false']);
        $trail = $this->api->download("/signature_requests/$id/audit_trails/download", ['merge' => 'true']);
        $type = static fn ($answer, string $default) => strtok((string) ($answer->header('content-type') ?? $default), ';');
        $request->setResult(new Download(
            [new File($signed->body, $several ? 'signed-documents.zip' : ($request->envelope->documents[0]->file->filename ?? 'signed.pdf'), $type($signed, $several ? 'application/zip' : 'application/pdf'))],
            new File($trail->body, 'audit-trail.pdf', $type($trail, 'application/pdf')),
        ));
    }
}
