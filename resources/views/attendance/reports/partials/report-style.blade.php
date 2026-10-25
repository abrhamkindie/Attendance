{{--
    Shared styling for the attendance report pages (by branch, by department).
    Included by both so the two screens stay pixel-identical. Brand palette:
    #395750 primary, #2c443f dark, #7A9F95 accent.
--}}
<style>
    .att-report {
        --brand: #395750;
        --brand-dark: #2c443f;
        --brand-accent: #7A9F95;
        --brand-tint: #f4f8f7;
        --brand-line: #e3ebe8;
    }

    .att-report .btn-brand {
        background-color: #395750;
        border-color: #395750;
        color: #fff;
        font-weight: 600;
    }

    .att-report .btn-brand:hover,
    .att-report .btn-brand:focus {
        background-color: #2c443f;
        border-color: #2c443f;
        color: #fff;
    }

    .att-report .btn-outline-secondary {
        color: #395750;
        border-color: #395750;
    }

    .att-report .btn-outline-secondary:hover,
    .att-report .btn-outline-secondary.active,
    .att-report .btn-outline-secondary:active {
        background-color: #395750;
        border-color: #395750;
        color: #fff;
    }

    .att-report .form-control:focus,
    .att-report .form-select:focus {
        border-color: #7A9F95;
        box-shadow: 0 0 0 .2rem rgba(122, 159, 149, .25);
    }

    /* ---- Card headers carry the brand tint ---- */
    .att-report .att-card-header {
        background-color: var(--brand-tint);
        border-bottom: 1px solid var(--brand-line);
        color: var(--brand);
    }

    .att-report .att-card-header strong {
        color: var(--brand);
    }

    .att-report .att-card-header .badge {
        background-color: #e5edea !important;
        color: #395750 !important;
        font-weight: 600;
    }

    /* ---- Filter card: scope filters vs the late rule ---- */
    /* Two panels, two owners: pale green = what is listed, pale amber = how
       "late" is judged. Each panel carries its own Apply/Reset. */
    .att-filter-form {
        padding: 12px 14px;
        background: #f2f7f5;
        border: 1px solid #dbe7e3;
        border-left: 3px solid var(--brand);
        border-radius: 10px;
    }

    .att-filter-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
        margin-bottom: .75rem;
    }

    .att-section-title {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #395750;
    }

    .att-section-title i {
        color: #7A9F95;
        margin-right: 4px;
    }

    .att-section-hint {
        font-size: 11px;
        color: #7d8f8a;
    }

    .att-quick .btn {
        background: #f8faf9;
        border: 1px solid var(--brand-line);
        color: #5f7a74;
        font-size: 12px;
    }

    .att-quick .btn:hover {
        background: #eef4f2;
        border-color: #cfe0db;
        color: #395750;
    }

    /* Pale amber on purpose: the rule must not read as another filter field. */
    .att-rule {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: .75rem;
        margin-top: 1rem;
        padding: 12px 14px;
        background: #fff8ec;
        border: 1px solid #f1d9a8;
        border-left: 3px solid #ef6c00;
        border-radius: 10px;
    }

    .att-rule-copy {
        min-width: 0;
        margin-right: auto;
    }

    .att-rule-title {
        display: block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #b26a00;
    }

    .att-rule-title i {
        margin-right: 4px;
    }

    .att-rule-text {
        margin: 2px 0 0;
        font-size: 13px;
        color: #6c757d;
    }

    .att-rule-text strong {
        color: #b26a00;
        font-weight: 700;
    }

    .att-rule-fields {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: .5rem;
        margin-left: auto;
    }

    .att-rule-fields>div {
        min-width: 130px;
    }

    .att-rule-actions {
        display: flex;
        gap: .5rem;
    }

    /* Amber, like the panel it belongs to: the action is rule-only. */
    .att-report .btn-rule {
        background-color: #ef6c00;
        border-color: #ef6c00;
        color: #fff;
        font-weight: 600;
    }

    .att-report .btn-rule:hover,
    .att-report .btn-rule:focus {
        background-color: #d95f00;
        border-color: #d95f00;
        color: #fff;
    }

    /* select2 replaces a .form-select-sm, so it has to match its height. */
    .att-report .att-filter-form .select2-container {
        width: 100% !important;
    }

    .att-report .att-filter-form .select2-container--default .select2-selection--single {
        height: 31px;
        padding: 4px 0;
        border: 1px solid #dee2e6;
        border-radius: .25rem;
        background: #fff;
    }

    .att-report .att-filter-form .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding-left: .5rem;
        padding-right: 1.25rem;
        line-height: 1.5;
        font-size: .875rem;
        color: #212529;
    }

    .att-report .att-filter-form .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #6c757d;
    }

    .att-report .att-filter-form .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 29px;
        right: 2px;
    }

    .att-report .att-filter-form .select2-container--default .select2-selection--single .select2-selection__clear {
        height: 29px;
        margin-right: 1.25rem;
        line-height: 1.6;
    }

    .att-report .att-filter-form .select2-container--default.select2-container--focus .select2-selection--single,
    .att-report .att-filter-form .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #7A9F95;
        box-shadow: 0 0 0 .2rem rgba(122, 159, 149, .25);
    }

    /* ---- KPI tiles ---- */
    .att-tile {
        display: flex;
        flex-direction: column;
        gap: 2px;
        height: 100%;
        padding: 14px 16px;
        background: #fff;
        border: 1px solid #eef1f0;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
        border-left: 3px solid #7A9F95;
    }

    .att-tile-label {
        font-size: 12px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: .03em;
        white-space: nowrap;
    }

    .att-tile-label i {
        color: #7A9F95;
        margin-right: 4px;
    }

    .att-tile-value {
        font-size: 22px;
        font-weight: 700;
        line-height: 1.15;
        color: #395750;
    }

    .att-tile-sub {
        font-size: 12px;
        color: #98a2a0;
    }

    .att-tile-sub.is-alert {
        color: #c62828;
        font-weight: 600;
    }

    .att-tile-alert {
        border-color: #f3d6d4;
        border-left-color: #c62828;
        background: #fffbfb;
    }

    .att-tile-alert .att-tile-value {
        color: #c62828;
    }

    /* ---- Status cards double as filters ---- */
    .att-tile-link {
        display: block;
        height: 100%;
        color: inherit;
        text-decoration: none;
        border-radius: 10px;
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .att-tile-link:hover {
        transform: translateY(-2px);
    }

    .att-tile-link:hover .att-tile {
        border-color: #cfe0db;
        box-shadow: 0 .5rem 1rem rgba(16, 24, 40, .10);
    }

    .att-tile-link:focus-visible {
        outline: 2px solid #395750;
        outline-offset: 2px;
    }

    .att-tile.is-active {
        border-color: #395750;
        border-left-color: #395750;
        background: #f2f7f5;
        box-shadow: 0 0 0 3px rgba(57, 87, 80, .14);
    }

    .att-tile.is-active .att-tile-label {
        color: #2c443f;
        font-weight: 600;
    }

    .att-tile.is-active .att-tile-sub {
        color: #395750;
        font-weight: 600;
    }

    /* ---- Grouped tables (branches / departments) ---- */
    .att-report #attGroups {
        scroll-margin-top: 90px;
    }

    .att-report .att-table>thead>tr>th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #5f7a74;
        font-weight: 600;
        white-space: nowrap;
        background-color: #f7faf9;
        border-bottom: 2px solid var(--brand-line);
    }

    .dept-row,
    .br-row {
        cursor: pointer;
    }

    .dept-row:hover>td,
    .br-row:hover>td {
        background: #f7f9f8;
    }

    .dept-row:focus-visible,
    .br-row:focus-visible {
        outline: 2px solid #7A9F95;
        outline-offset: -2px;
    }

    .dept-chevron,
    .br-chevron {
        color: #7A9F95;
        transition: transform .15s ease;
    }

    .dept-chevron.open,
    .br-chevron.open {
        transform: rotate(90deg);
    }

    .dept-detail>td,
    .br-detail>td {
        border-top: 0;
    }

    /* 15 employee rows before the list scrolls (a row is ~40px because of the
       Full story button) plus the sticky header and padding. The 72vh guard
       keeps the next group row in sight on short laptop screens. */
    .dept-detail-body,
    .br-detail-body {
        --att-rows: 15;
        max-height: min(calc(var(--att-rows) * 40px + 44px), 72vh);
        overflow-y: auto;
        background: #fbfcfc;
        border-top: 1px solid #eef1f0;
        border-bottom: 1px solid #eef1f0;
        padding: 6px 12px 10px 54px;
    }

    /* Opaque and sticky: the column header has to stay legible over the rows
       sliding underneath it. The inset shadow replaces the border, which
       collapsed-border tables drop on a sticky cell. */
    .att-inner>thead>tr>th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #fbfcfc;
        box-shadow: inset 0 -1px 0 #eef1f0;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #98a2a0;
        font-weight: 600;
        border-bottom: 1px solid #eef1f0;
    }

    .att-inner>tbody>tr>td {
        background: transparent;
    }

    /* ---- Status chips ---- */
    .chip {
        display: inline-block;
        padding: 1px 7px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.6;
        white-space: nowrap;
    }

    .chip-none {
        background: #fdecea;
        color: #c62828;
    }

    .chip-warn {
        background: #fff4e5;
        color: #b26a00;
    }

    .chip-good {
        background: #e7f4e8;
        color: #2e7d32;
    }

    .chip-info {
        background: #e7f0fb;
        color: #1565c0;
    }

    .row-none>td {
        background: #fffdfa;
    }

    /* ---- Numbering column ---- */
    .att-num {
        width: 54px;
        text-align: center;
        color: #98a2a0;
        font-variant-numeric: tabular-nums;
        font-size: 12px;
    }

    /* ---- Tabs ---- */
    .att-report .att-tabs {
        border-bottom: 1px solid #e3ebe8;
    }

    .att-report .att-tabs .nav-link {
        color: #5f7a74;
        font-weight: 600;
        border: 0;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        padding: 10px 14px;
    }

    .att-report .att-tabs .nav-link:hover {
        color: #395750;
        border-bottom-color: #7A9F95;
    }

    .att-report .att-tabs .nav-link.active {
        color: #395750;
        background: transparent;
        border-bottom: 2px solid #395750;
    }

    .att-report .att-tabs .nav-link .badge {
        background-color: #e5edea;
        color: #395750;
        font-weight: 600;
    }

    .att-report .att-tabs .nav-link.active .badge {
        background-color: #395750;
        color: #fff;
    }

    /* ---- Data-check blocks ---- */
    .att-check+.att-check {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #eef1f0;
    }

    .att-check-title {
        font-weight: 700;
        color: #395750;
        margin-bottom: 4px;
    }

    .att-link {
        color: #395750;
        font-weight: 600;
        text-decoration: none;
    }

    .att-link:hover {
        text-decoration: underline;
        color: #2c443f;
    }

    /* ---- Attendance rate ---- */
    .rate {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 3px;
        min-width: 92px;
    }

    .rate-value {
        font-size: 12px;
        font-weight: 700;
    }

    .rate-bar {
        display: block;
        width: 100%;
        height: 4px;
        border-radius: 999px;
        background: #eef1f0;
        overflow: hidden;
    }

    .rate-bar>i {
        display: block;
        width: 0;
        height: 100%;
        border-radius: 999px;
        background: #395750;
        transition: width .25s ease;
    }

    .rate-good .rate-value {
        color: #2e7d32;
    }

    .rate-good .rate-bar>i {
        background: #2e7d32;
    }

    .rate-warn .rate-value {
        color: #ef6c00;
    }

    .rate-warn .rate-bar>i {
        background: #ef6c00;
    }

    .rate-bad .rate-value {
        color: #c62828;
    }

    .rate-bad .rate-bar>i {
        background: #c62828;
    }

    /* ---- Empty states ---- */
    .att-empty {
        padding: 40px 20px;
        text-align: center;
        color: #6c757d;
    }

    .att-empty>i {
        display: block;
        margin-bottom: 10px;
        font-size: 34px;
        color: #b7c9c4;
    }

    .att-empty h6 {
        margin-bottom: 6px;
        color: #395750;
        font-weight: 700;
    }

    .att-empty p {
        max-width: 520px;
        margin: 0 auto 14px;
        font-size: 13px;
    }

    @media (max-width: 575.98px) {
        .att-rule-fields {
            width: 100%;
            margin-left: 0;
        }

        .att-rule-fields>div {
            flex: 1 1 0;
            min-width: 0;
        }

        .att-rule-fields>.att-rule-actions {
            flex: 1 1 100%;
            justify-content: flex-end;
        }

        .att-filter-head .ms-auto {
            width: 100%;
            justify-content: flex-end;
        }
    }

    @media (max-width: 1199.98px) {
        .att-tile {
            padding: 10px 12px;
        }

        .att-tile-value {
            font-size: 18px;
        }

        .dept-detail-body,
        .br-detail-body {
            padding-left: 16px;
        }
    }

    @media print {

        .att-report .page-header .col-auto,
        .att-filter-form,
        .att-rule-fields,
        .att-card-header .btn-group,
        .att-card-header .d-flex {
            display: none !important;
        }

        /* The rule sentence is the only explanatory line left, so paper keeps it. */
        .att-rule {
            margin-top: 0;
        }

        .dept-detail[hidden],
        .br-detail[hidden] {
            display: table-row !important;
        }

        /* Paper keeps every employee, so the scroll cap comes off. */
        .dept-detail-body,
        .br-detail-body {
            max-height: none;
            overflow: visible;
        }

        .att-inner>thead>tr>th {
            position: static;
        }

        .att-tile {
            box-shadow: none;
        }

        .att-report .card {
            break-inside: avoid;
        }
    }
</style>