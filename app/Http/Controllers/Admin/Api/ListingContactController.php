<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\ListingContact;
use App\Support\PhMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingContactController extends Controller
{
    public function findByPhone(Request $request): JsonResponse
    {
        $phone = $request->query('phone');
        $normalized = $phone !== null ? PhMobile::normalize($phone) : null;

        $contact = $normalized
            ? ListingContact::where('phone', $normalized)->first()
            : null;

        return response()->json([
            'contact' => $contact ? [
                'uuid'  => $contact->uuid,
                'name'  => $contact->name,
                'phone' => $contact->phone,
                'notes' => $contact->notes,
            ] : null,
        ]);
    }
}
