<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Check-In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f0f4f3;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .checkin-card {
            background: white;
            border-radius: 16px;
            padding: 40px 28px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .checkin-icon {
            font-size: 64px;
            color: #395750;
            margin-bottom: 16px;
        }

        .checkin-title {
            color: #395750;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .checkin-message {
            color: #6c757d;
            margin-bottom: 24px;
            font-size: 15px;
        }

        .btn-start {
            background: #395750;
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }

        .btn-start:hover {
            background: #2c443f;
            color: white;
        }

        .btn-back {
            background: #395750;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-back:hover {
            background: #2c443f;
            color: white;
        }
    </style>
</head>

<body>

    <div class="checkin-card" id="checkinCard" data-validate-url="{{ route('attendance.validateQr', [], false) }}"
        data-csrf="{{ csrf_token() }}" data-user-id="{{ $qrUserId ?? '' }}" data-type="{{ $type ?? 'in' }}">
        <div class="checkin-icon">
            <i class="fas fa-map-marker-alt"></i>
        </div>

        <h4 id="statusTitle" class="checkin-title">Ready to Check {{ $type === 'out' ? 'Out' : 'In' }}</h4>
        <p id="status" class="checkin-message">
            Tap the button below to start. Your browser will ask for location permission.
        </p>

        <div id="spinner" style="display: none; font-size: 32px; color: #395750; margin: 20px 0;">
            <i class="fas fa-spinner fa-spin"></i>
        </div>

        <button id="startBtn" class="btn-start">
            <i class="fas fa-location-arrow"></i> Start Check-{{ $type === 'out' ? 'Out' : 'In' }}
        </button>

        {{-- Shown only when the server says you are at another office: HR needs a reason. --}}
        <div id="dutyBox"
            style="display: none; text-align: left; margin-top: 16px; padding: 14px; border: 1px solid #e3e8e6; border-radius: 12px; background: #f7f9f8;">
            <label for="dutyReason"
                style="font-size: 13px; font-weight: 600; color: #395750; display: block; margin-bottom: 6px;">
                You are not at your assigned site. Why are you punching here?
            </label>
            <select id="dutyReason" class="form-select form-select-sm mb-2">
                <option value="">-- select a reason --</option>
                <option value="Branch duty">Branch duty</option>
                <option value="Field duty">Field duty</option>
                <option value="Client visit">Client visit</option>
                <option value="Other">Other</option>
            </select>
            <input id="dutyText" class="form-control form-control-sm mb-2" maxlength="180"
                placeholder="Short note for HR (optional)">
            <button id="dutySubmitBtn" class="btn-start" style="padding: 10px 20px; font-size: 15px;">
                <i class="fas fa-check"></i> Send reason &amp; punch
            </button>
        </div>

        <div id="homeBtnWrapper" style="display: none; margin-top: 16px;">
            <a href="/" class="btn-back">
                <i class="fas fa-home"></i> Back
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const statusEl = document.getElementById('status');
            const titleEl = document.getElementById('statusTitle');
            const spinnerEl = document.getElementById('spinner');
            const homeBtnWrap = document.getElementById('homeBtnWrapper');
            const startBtn = document.getElementById('startBtn');
            const dutyBox = document.getElementById('dutyBox');
            const dutyReasonEl = document.getElementById('dutyReason');
            const dutyTextEl = document.getElementById('dutyText');
            const dutySubmitBtn = document.getElementById('dutySubmitBtn');

            // Kept so a missing reason can be retried without asking for GPS again.
            let lastCoords = null;

            // Server values come in through data-* attributes: a Blade echo
            // written directly inside <script> gets reformatted into invalid
            // JavaScript and the whole block stops running.
            const card = document.getElementById('checkinCard');
            const validateUrl = card.dataset.validateUrl;
            const csrfToken = card.dataset.csrf;
            const qrUserId = card.dataset.userId;
            const checkType = card.dataset.type;

            function showResult(title, message, isSuccess) {
                titleEl.innerText = title;
                statusEl.innerText = message;
                spinnerEl.style.display = 'none';
                startBtn.style.display = 'none';
                dutyBox.style.display = 'none';
                homeBtnWrap.style.display = 'block';
                titleEl.style.color = isSuccess ? '#28a745' : '#dc3545';
            }

            function showError(title, message) {
                titleEl.innerText = title;
                statusEl.innerText = message;
                spinnerEl.style.display = 'none';
                startBtn.style.display = 'block';
                titleEl.style.color = '#dc3545';
            }

            // Either the dropdown or the free text is enough. The server joins
            // them into one short line HR can read in the report.
            function buildDutyNote() {
                const reason = (dutyReasonEl.value || '').trim();
                const text = (dutyTextEl.value || '').trim();

                if (reason && text) return reason + ' - ' + text;

                return reason || text;
            }

            // A phone's GPS chip needs a clear view of the sky, so the first
            // pass regularly dies at the timeout indoors. The second pass asks
            // the browser for a network (Wi-Fi / cell) fix instead: fast even
            // indoors, and the server accepts coarse readings up to
            // config('attendance.max_accuracy_m'). A fix up to 30 s old is
            // reused first, which is what Android usually already has.
            const geoAttempts = [{
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 30000
            }, {
                enableHighAccuracy: false,
                timeout: 20000,
                maximumAge: 120000
            }];

            function locate(onPosition, onError) {
                let attempt = 0;

                const tryNext = function(lastError) {
                    if (attempt >= geoAttempts.length) {
                        onError(lastError);
                        return;
                    }

                    navigator.geolocation.getCurrentPosition(onPosition, function(error) {
                        // A denied permission is final: retrying only re-prompts.
                        if (error.code === 1) {
                            onError(error);
                            return;
                        }

                        tryNext(error);
                    }, geoAttempts[attempt++]);
                };

                tryNext(null);
            }

            function sendPunch(coords) {
                fetch(validateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            latitude: coords.latitude,
                            longitude: coords.longitude,
                            accuracy: coords.accuracy,
                            duty_note: buildDutyNote(),
                            user_id: qrUserId,
                            type: checkType
                        })
                    })
                    .then(function(res) {
                        return res.json().then(function(data) {
                            return {
                                ok: res.ok,
                                data: data
                            };
                        });
                    })
                    .then(function(result) {
                        const data = result.data || {};
                        const isOut = checkType === 'out';

                        const successTitle = isOut ? 'Checked Out!' : 'Checked In!';
                        const failTitle = isOut ? 'Check-Out Failed' : 'Check-In Failed';

                        // The server is the only source of truth: it decides pass/fail and reports
                        // the measured distance, so this page never invents a different verdict.
                        const isSuccess = result.ok && data.success === true;

                        // Punched at another office: ask for a reason and re-send the
                        // same GPS reading instead of making the user locate again.
                        if (!isSuccess && data.duty_required) {
                            lastCoords = coords;
                            titleEl.innerText = 'Reason Needed';
                            titleEl.style.color = '#b8860b';
                            statusEl.innerText = data.message ||
                                'Add a short reason so HR can see why you are punching here.';
                            spinnerEl.style.display = 'none';
                            startBtn.style.display = 'none';
                            dutyBox.style.display = 'block';
                            return;
                        }

                        let message = data.message || 'Something went wrong.';

                        if (isSuccess && data.match === 'on_duty') {
                            message = (isOut ? 'Checked out' : 'Checked in') + ' - on duty at ' +
                                (data.location_name || 'another site') + ' (' +
                                Math.round(data.expected_distance_m || 0) +
                                ' m from your assigned site' +
                                (data.expected_location_name ? ': ' + data.expected_location_name : '') +
                                ').';
                        } else if (isSuccess && data.distance_m !== undefined) {
                            message += ' You were ' + Math.round(data.distance_m) + ' m from ' +
                                (data.location_name || 'your assigned location') +
                                ' (allowed ' + (data.allowed_radius_m || 0) + ' m).';
                        } else if (!isSuccess && data.accuracy_m !== undefined && data.accuracy_m !== null) {
                            // Keep the reading quality on the failure screen: a coarse
                            // fix (GPS +/- hundreds of m) explains a distance a precise
                            // one would not.
                            message += ' (your GPS reading was \u00b1' + Math.round(data.accuracy_m) + ' m)';
                        }

                        showResult(isSuccess ? successTitle : failTitle, message, isSuccess);
                    })
                    .catch(function() {
                        showError('Error', 'Could not reach the server. Please try again.');
                    });
            }

            startBtn.addEventListener('click', function() {

                startBtn.style.display = 'none';
                dutyBox.style.display = 'none';
                spinnerEl.style.display = 'block';
                titleEl.innerText = 'Getting your location...';
                titleEl.style.color = '#395750';
                statusEl.innerText = 'Please allow location access when your browser asks.';

                if (!navigator.geolocation) {
                    showError('Error', 'Geolocation is not supported by your browser.');
                    return;
                }

                // Offline there is no network fix and no punch. Say it straight
                // away instead of burning 30+ seconds in the GPS timeouts.
                if (navigator.onLine === false) {
                    showError('No Internet',
                        'This phone is offline. Turn on mobile data or Wi-Fi, then tap again.');
                    return;
                }

                let settled = false;
                const progress = setTimeout(function() {
                    if (!settled) {
                        statusEl.innerText = 'Still searching for a fix. Indoors? Step near a window.';
                    }
                }, 8000);

                locate(function(position) {
                    settled = true;
                    clearTimeout(progress);

                    const accuracy = Math.round(position.coords.accuracy || 0);
                    statusEl.innerText = accuracy > 0 ?
                        'Sending your location (GPS \u00b1' + accuracy + ' m)...' :
                        'Sending your location...';

                    sendPunch({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    });
                }, function(error) {
                    settled = true;
                    clearTimeout(progress);

                    var errors = {
                        1: 'Location permission denied. Please allow access in your browser settings and try again.',
                        2: 'Unable to detect your position. Make sure Location is ON in your phone settings.',
                        3: 'Your phone could not get a location fix. Turn Location ON, then step outside or near a window and tap again.'
                    };
                    showError('Location Error', errors[error && error.code] || 'Location detection failed.');
                });
            });

            // Re-send the same reading with the reason attached.
            dutySubmitBtn.addEventListener('click', function() {
                if (!lastCoords) {
                    showError('Error', 'Location lost. Please tap start again.');
                    return;
                }

                if (!buildDutyNote()) {
                    statusEl.innerText = 'Pick a reason or type a short note. HR needs to see why.';
                    statusEl.style.color = '#dc3545';
                    return;
                }

                dutyBox.style.display = 'none';
                spinnerEl.style.display = 'block';
                statusEl.innerText = 'Sending your reason...';

                sendPunch(lastCoords);
            });

        });
    </script>

</body>

</html>