<?php

namespace App\Http\Controllers\Api\V1\Field\Vapor;

use App\Enums\AppPermission;
use App\Http\Controllers\Vapor\SignedStorageUrlController as BaseSignedStorageUrlController;
use Illuminate\Http\Request;

class SignedStorageUrlController extends BaseSignedStorageUrlController
{
    public function store(Request $request)
    {
        // Defense-in-depth for users whose role was revoked after token issuance:
        // route middleware (`auth:sanctum + abilities:field`) doesn't see Spatie
        // permissions, so the explicit guard-scoped check belongs here.
        abort_unless(
            $request->user()->hasPermissionTo(AppPermission::listingsFieldWork()->value, 'admin'),
            403,
        );

        return parent::store($request);
    }
}
