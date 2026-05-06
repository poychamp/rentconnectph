<?php

namespace App\Http\Controllers\Vapor;

use Laravel\Vapor\Http\Controllers\SignedStorageUrlController as BaseSignedStorageUrlController;
use Symfony\Component\Mime\MimeTypes;

class SignedStorageUrlController extends BaseSignedStorageUrlController
{
    protected function getKey(string $uuid)
    {
        $extension = $this->extensionFromContentType(request('content_type'));

        return 'tmp/' . $uuid . ($extension ? '.' . $extension : '');
    }

    protected function extensionFromContentType(?string $contentType): ?string
    {
        if (empty($contentType)) {
            return null;
        }

        return MimeTypes::getDefault()->getExtensions($contentType)[0] ?? null;
    }
}
