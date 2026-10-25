{{-- Shared filter bar for the attendance reports.
     Expects: $action, $from, $to, $startTime, $grace, $lateAfter, $branches,
              $departments, $branchId, $departmentId, $search and optionally
              $sort, $status.

     Filters and the late rule answer different questions, so they are two
     separate forms. Each one carries the other's applied values as hidden
     fields, which keeps its own Apply/Reset scoped to what the panel owns. --}}
@php
$linkWith = function (array $params) use ($action) {
return $params === [] ? $action : $action . '?' . http_build_query($params);
};

// Clearing the filters keeps the applied rule and the sort order.
$filtersResetUrl = $linkWith(request()->only('start_time', 'grace', 'sort'));

// Clearing the rule returns to the defaults and keeps the visible scope.
$ruleResetUrl = $linkWith(request()->except('start_time', 'grace'));
@endphp

{{-- Panel 1: which records to list. --}}
<form action="{{ $action }}" method="GET" id="attFilterForm" class="att-filter-form">
    @isset($sort)
    <input type="hidden" name="sort" value="{{ $sort }}">
    @endisset
    @if (($status ?? 'all') !== 'all')
    <input type="hidden" name="status" value="{{ $status }}">
    @endif
    {{-- Applying filters must not quietly change the late rule. --}}
    @if (request()->filled('start_time'))
    <input type="hidden" name="start_time" value="{{ $startTime }}">
    @endif
    @if (request()->filled('grace'))
    <input type="hidden" name="grace" value="{{ $grace }}">
    @endif

    {{-- Header: names the block, carries the quick ranges and the actions. --}}
    <div class="att-filter-head">
        <span class="att-section-title"><i class="fa fa-filter"></i> Filters</span>
        <span class="att-section-hint">narrows the list</span>

        <div class="btn-group btn-group-sm att-quick" role="group" aria-label="Quick date range">
            <button type="button" class="btn btn-light" data-range="month">This month</button>
            <button type="button" class="btn btn-light" data-range="last-month">Last month</button>
            <button type="button" class="btn btn-light" data-range="last-30">Last 30 days</button>
        </div>

        <div class="ms-auto d-flex gap-2">
            <a href="{{ $filtersResetUrl }}" class="btn btn-sm btn-light"
                title="Clear branch, department, employee, dates and the status card; the late rule stays">
                Reset
            </a>
            <button type="submit" class="btn btn-sm btn-brand">
                <i class="fa fa-check"></i> Apply filters
            </button>
        </div>
    </div>

    {{-- Scope filters: who / where / when. The dates sit last on purpose. --}}
    <div class="row g-2 att-filter-fields">
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label small text-muted mb-1" for="attBranch">Branch</label>
            <select name="branch_id" id="attBranch" class="form-select form-select-sm att-select">
                <option value="">All branches</option>
                @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) $branchId===(string) $branch->id)>
                    {{ $branch->name }}
                </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label small text-muted mb-1" for="attDepartment">Department</label>
            <select name="department_id" id="attDepartment" class="form-select form-select-sm att-select"
                title="Isolated department">
                <option value="">All departments</option>
                @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) $departmentId===(string) $department->id)>
                    {{ $department->department_name }}
                </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label small text-muted mb-1" for="attSearch">Employee</label>
            <input type="text" name="q" id="attSearch" value="{{ $search }}" class="form-control form-control-sm"
                placeholder="name contains">
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label small text-muted mb-1" for="attFrom">From</label>
            <input type="date" name="from" id="attFrom" value="{{ $from->toDateString() }}"
                class="form-control form-control-sm">
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label small text-muted mb-1" for="attTo">To</label>
            <input type="date" name="to" id="attTo" value="{{ $to->toDateString() }}"
                class="form-control form-control-sm">
        </div>
    </div>
</form>

{{-- Panel 2: how "late" is judged. It has its own Apply/Reset so a rule change
     is a separate decision from a scope change. --}}
<form action="{{ $action }}" method="GET" id="attRuleForm" class="att-rule">
    <input type="hidden" name="branch_id" value="{{ $branchId }}">
    <input type="hidden" name="department_id" value="{{ $departmentId }}">
    <input type="hidden" name="q" value="{{ $search }}">
    <input type="hidden" name="from" value="{{ $from->toDateString() }}">
    <input type="hidden" name="to" value="{{ $to->toDateString() }}">
    @isset($sort)
    <input type="hidden" name="sort" value="{{ $sort }}">
    @endisset
    @if (($status ?? 'all') !== 'all')
    <input type="hidden" name="status" value="{{ $status }}">
    @endif

    <div class="att-rule-copy">
        <span class="att-rule-title"><i class="fa fa-stopwatch"></i> Late rule</span>
        <p class="att-rule-text">
            Not a filter. Currently late = clock-in after
            <strong>{{ substr($lateAfter, 0, 5) }}</strong>
            ({{ $startTime }} + {{ $grace }} min).
        </p>
    </div>

    <div class="att-rule-fields">
        <div>
            <label class="form-label small text-muted mb-1" for="attStart">Shift start</label>
            <input type="time" name="start_time" id="attStart" value="{{ $startTime }}"
                class="form-control form-control-sm" title="Clock-ins after this time count as late">
        </div>
        <div>
            <label class="form-label small text-muted mb-1" for="attGrace">Grace (min)</label>
            <input type="number" name="grace" id="attGrace" value="{{ $grace }}" min="0" max="120"
                class="form-control form-control-sm" title="Minutes of grace after the shift start">
        </div>
        <div class="att-rule-actions">
            <a href="{{ $ruleResetUrl }}" class="btn btn-sm btn-light"
                title="Back to the default shift start and grace; the filters stay">
                Defaults
            </a>
            <button type="submit" class="btn btn-sm btn-rule">
                <i class="fa fa-stopwatch"></i> Apply rule
            </button>
        </div>
    </div>
</form>

<script>
    (function() {
        var form = document.getElementById('attFilterForm');
        var ruleForm = document.getElementById('attRuleForm');
        if (!form) return;

        var from = document.getElementById('attFrom');
        var to = document.getElementById('attTo');

        function iso(date) {
            var pad = function(n) {
                return String(n).padStart(2, '0');
            };
            return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
        }

        form.querySelectorAll('[data-range]').forEach(function(button) {
            button.addEventListener('click', function() {
                var today = new Date();
                var start;

                if (button.dataset.range === 'month') {
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                } else if (button.dataset.range === 'last-month') {
                    start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    today = new Date(today.getFullYear(), today.getMonth(), 0);
                } else {
                    start = new Date(today.getTime() - 29 * 86400000);
                }

                from.value = iso(start);
                to.value = iso(today);

                form.submit();
            });
        });

        // Changing the rule should not throw away the filters on screen, so the
        // current scope is copied into the rule form right before it submits.
        if (ruleForm) {
            ruleForm.addEventListener('submit', function() {
                ['branch_id', 'department_id', 'q', 'from', 'to'].forEach(function(name) {
                    var field = form.querySelector('[name="' + name + '"]');
                    var hidden = ruleForm.querySelector('input[type="hidden"][name="' + name + '"]');

                    if (field && hidden) hidden.value = field.value;
                });
            });
        }

        // Branch/Department lists are long, so they open as type-to-search.
        // jQuery and select2 are parsed after this block (bottom of the layout),
        // which is why the init waits for DOMContentLoaded.
        document.addEventListener('DOMContentLoaded', function() {
            if (!window.jQuery || !window.jQuery.fn.select2) return;

            window.jQuery('.att-select').each(function() {
                var $select = window.jQuery(this);

                $select.select2({
                    width: '100%',
                    placeholder: $select.find('option[value=""]').first().text(),
                    allowClear: true
                });
            });
        });
    })();
</script>