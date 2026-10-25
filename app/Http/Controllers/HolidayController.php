<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HolidayController extends Controller
{
    /** HR / admins: management list with edit + delete actions. */
    public function index(Request $request)
    {
        $year = $request->filled('year') ? (int) $request->input('year') : null;

        $holidays = $this->ordered($year);

        $years = Holiday::all()
            ->map(fn(Holiday $h) => $h->holidayDate()?->year)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return view('holidays.index', compact('holidays', 'years', 'year'));
    }

    /** Everyone: read-only holiday calendar grouped by year. */
    public function browse()
    {
        $grouped = collect($this->ordered())
            ->groupBy(fn(Holiday $h) => $h->holidayDate()?->year ?? 'Unknown')
            ->sortKeysDesc();

        $today = Carbon::today();

        return view('holidays.browse', compact('grouped', 'today'));
    }

    public function create()
    {
        return view('holidays.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($validated['date']);

        Holiday::create([
            'date_holiday' => $date->toDateString(),
            'name'         => $validated['name'],
            'year'         => $date->year,
        ]);

        Toastr::success('Holiday added successfully.', 'Success');

        return redirect()->route('holidays.index');
    }

    public function edit(Holiday $holiday)
    {
        return view('holidays.edit', [
            'holiday'   => $holiday,
            'dateValue' => $holiday->holidayDate()?->toDateString(),
        ]);
    }

    public function update(Request $request, Holiday $holiday)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($validated['date']);

        $holiday->update([
            'date_holiday' => $date->toDateString(),
            'name'         => $validated['name'],
            'year'         => $date->year,
        ]);

        Toastr::success('Holiday updated successfully.', 'Success');

        return redirect()->route('holidays.index');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        Toastr::success('Holiday deleted successfully.', 'Success');

        return redirect()->route('holidays.index');
    }

    /**
     * All holidays ordered by their parsed date (future first). Free-text
     * rows that cannot be parsed sort last.
     *
     * @return array<int, Holiday>
     */
    private function ordered(?int $year = null): array
    {
        $all = Holiday::all();

        if ($year !== null) {
            $all = $all->filter(fn(Holiday $h) => $h->holidayDate()?->year === $year);
        }

        return $all
            ->sortByDesc(fn(Holiday $h) => $h->holidayDate()?->timestamp ?? PHP_INT_MIN)
            ->values()
            ->all();
    }
}
