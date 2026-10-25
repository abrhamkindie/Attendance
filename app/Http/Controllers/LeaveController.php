<?php

namespace App\Http\Controllers;

use App\Models\Leave;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    /** The signed-in employee's own requests plus this year's balance. */
    public function index(Request $request)
    {
        $user = $request->user();
        $year = (int) $request->input('year', Carbon::today()->year);

        $leaves = Leave::where('user_id', $user->id)
            ->orderByDesc('from_date')
            ->paginate(15)
            ->withQueryString();

        $balances = Leave::balances($user->id, $year);
        $years    = $this->availableYears($user->id);

        return view('leaves.index', compact('leaves', 'balances', 'year', 'years'));
    }

    public function create()
    {
        return view('leaves.create', [
            'types'    => Leave::TYPES,
            'balances' => Leave::balances(auth()->id(), Carbon::today()->year),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'leave_type'     => ['required', Rule::in(Leave::TYPES)],
            'from_date'      => 'required|date',
            'to_date'        => 'required|date|after_or_equal:from_date',
            'is_half_day'    => 'nullable|boolean',
            'half_day_type'  => 'nullable|in:morning,afternoon',
            'reason'         => 'nullable|string|max:2000',
        ], [], ['leave_type' => 'leave type']);

        $from    = Carbon::parse($validated['from_date']);
        $to      = Carbon::parse($validated['to_date']);
        $isHalf  = (bool) ($validated['is_half_day'] ?? false);
        $details = null;

        if ($isHalf) {
            // A half day is always a single day.
            $to      = $from->copy();
            $details = [[
                'date'          => $from->toDateString(),
                'half_day_type' => $validated['half_day_type'] ?? Leave::HALF_MORNING,
            ]];
        }

        if ($this->hasOverlap($user->id, $from, $to)) {
            Toastr::error('These dates overlap an existing leave request.', 'Blocked');

            return back()->withInput();
        }

        Leave::create([
            'user_id'      => $user->id,
            'leave_type'   => $validated['leave_type'],
            'from_date'    => $from->toDateString(),
            'to_date'      => $to->toDateString(),
            'is_half_day'  => $isHalf,
            'date_details' => $details,
            'status'       => Leave::STATUS_PENDING,
            'reason'       => $validated['reason'] ?? null,
        ]);

        Toastr::success('Leave request submitted for approval.', 'Success');

        return redirect()->route('leaves.index');
    }

    /** Employees may only open their own request. */
    public function show(Request $request, Leave $leave)
    {
        abort_unless($leave->user_id === $request->user()->id, 403);

        return view('leaves.show', [
            'leave' => $leave->load('approver'),
            'days'  => $leave->workingDays(),
        ]);
    }

    /** An employee can withdraw a still-pending request. */
    public function cancel(Request $request, Leave $leave)
    {
        abort_unless($leave->user_id === $request->user()->id, 403);

        if ($leave->status !== Leave::STATUS_PENDING) {
            Toastr::error('Only pending requests can be cancelled.', 'Blocked');

            return back();
        }

        $leave->delete();

        Toastr::success('Leave request cancelled.', 'Success');

        return redirect()->route('leaves.index');
    }

    /** Any non-rejected request whose span touches [from, to]. */
    private function hasOverlap(int $userId, Carbon $from, Carbon $to): bool
    {
        return Leave::where('user_id', $userId)
            ->where('status', '!=', Leave::STATUS_REJECTED)
            ->whereNotNull('from_date')
            ->where('from_date', '<=', $to->toDateString())
            ->where('to_date', '>=', $from->toDateString())
            ->exists();
    }

    /**
     * Years that have leave activity, always including the current year,
     * newest first.
     *
     * @return array<int, int>
     */
    private function availableYears(int $userId): array
    {
        $years = Leave::where('user_id', $userId)
            ->whereNotNull('from_date')
            ->selectRaw('DISTINCT YEAR(from_date) as y')
            ->pluck('y')
            ->map(fn($y) => (int) $y);

        $years->push((int) Carbon::today()->year);

        return $years->filter()->unique()->sortDesc()->values()->all();
    }
}
