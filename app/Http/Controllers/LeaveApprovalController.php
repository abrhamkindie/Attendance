<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class LeaveApprovalController extends Controller
{
    /** HR / admins: every request with status filter + search. */
    public function index(Request $request)
    {
        $status = (string) $request->input('status', Leave::STATUS_PENDING);

        $query = Leave::with(['user', 'approver'])->latest('created_at');

        if (in_array($status, [Leave::STATUS_PENDING, Leave::STATUS_APPROVED, Leave::STATUS_REJECTED], true)) {
            $query->where('status', $status);
        } else {
            $status = '';
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->whereHas('user', function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $leaves = $query->paginate(15)->withQueryString();

        $counts = [
            Leave::STATUS_PENDING  => Leave::where('status', Leave::STATUS_PENDING)->count(),
            Leave::STATUS_APPROVED => Leave::where('status', Leave::STATUS_APPROVED)->count(),
            Leave::STATUS_REJECTED => Leave::where('status', Leave::STATUS_REJECTED)->count(),
        ];

        return view('leave-approvals.index', compact('leaves', 'status', 'counts'));
    }

    public function show(Leave $leave)
    {
        return view('leave-approvals.show', [
            'leave' => $leave->load(['user', 'approver']),
            'days'  => $leave->workingDays(),
        ]);
    }

    public function approve(Request $request, Leave $leave)
    {
        $leave->update([
            'status'           => Leave::STATUS_APPROVED,
            'approved_by'      => $request->user()->id,
            'approved_at'      => now(),
            'rejection_reason' => null,
        ]);

        Toastr::success('Leave approved.', 'Success');

        return back();
    }

    public function reject(Request $request, Leave $leave)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ], [], ['rejection_reason' => 'reason']);

        $leave->update([
            'status'           => Leave::STATUS_REJECTED,
            'approved_by'      => $request->user()->id,
            'approved_at'      => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        Toastr::success('Leave rejected.', 'Success');

        return back();
    }
}
