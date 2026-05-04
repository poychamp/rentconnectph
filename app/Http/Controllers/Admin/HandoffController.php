<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminHandoffLockResource;
use App\Models\HandoffLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HandoffController extends Controller
{
    public function index(): View
    {
        $rows = HandoffLock::query()
            ->with(['inquiry.renter', 'listing', 'createdBy'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(10);

        return view('admin.handoffs.index', [
            'handoffs' => AdminHandoffLockResource::collection($rows)->response()->getData(true),
        ]);
    }

    public function destroy(HandoffLock $handoffLock): RedirectResponse
    {
        $handoffLock->delete();

        return redirect()
            ->route('admin.handoffs.index')
            ->with('success', 'Handoff released — listing returned to the queue.');
    }
}
