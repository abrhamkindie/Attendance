@php
$editing = isset($location) && $location;
$lat = (float) old('latitude', $editing ? $location->latitude : 9.0133571);
$lng = (float) old('longitude', $editing ? $location->longitude : 38.8371474);
$radius = (int) old('radius_m', $editing ? $location->radius_m : 100);
$isActive = (bool) old('is_active', $editing ? $location->is_active : true);
$mapProvider = config('services.maps.provider', 'leaflet');
@endphp

<style>
    .geofence-map {
        height: 430px;
        width: 100%;
    }

    .geofence-map-wrap {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
    }

    .geofence-hint {
        position: absolute;
        left: 12px;
        bottom: 12px;
        z-index: 1000;
        background: rgba(255, 255, 255, .96);
        border: 1px solid #e5e7eb;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 12px;
        color: #4b5563;
        max-width: calc(100% - 24px);
    }

    .geofence-locate {
        position: absolute;
        top: 12px;
        right: 12px;
        z-index: 1000;
    }

    .geofence-card {
        border: 0;
        box-shadow: 0 1px 3px rgba(16, 24, 40, .08);
        border-radius: 8px;
    }

    .geofence-card .card-header {
        background: #395750;
        color: #fff;
        border-radius: 8px 8px 0 0;
        font-weight: 600;
        letter-spacing: .2px;
    }

    .coordinate-box {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 10px 12px;
        font-family: monospace;
        font-size: 13px;
        color: #334155;
    }

    .btn-brand {
        background-color: #395750;
        border-color: #395750;
        color: #fff;
        font-weight: 600;
    }

    .btn-brand:hover {
        background-color: #2c443f;
        border-color: #2c443f;
        color: #fff;
    }

    .radius-badge {
        background: #eef4f3;
        color: #395750;
        font-weight: 600;
    }

    .geofence-map-error {
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 24px;
        text-align: center;
        background: #fff7ed;
        color: #9a3412;
        font-size: 14px;
    }

    .geofence-map-error i {
        font-size: 28px;
    }

    .geofence-google {
        border: 0;
        display: block;
    }

    .geofence-tiles-failed::after {
        content: 'Map tiles could not be loaded. Check your internet connection - you can still type the coordinates.';
        position: absolute;
        left: 0;
        right: 0;
        top: 0;
        padding: 8px 12px;
        background: #fff7ed;
        color: #9a3412;
        font-size: 12px;
        text-align: center;
        z-index: 1001;
    }
</style>

<form action="{{ $action }}" method="POST">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
    @method($method)
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card geofence-card h-100">
                <div class="card-body p-0">
                    <div class="geofence-map-wrap" id="geofenceMapWrap" data-lat="{{ $lat }}"
                        data-lng="{{ $lng }}" data-radius="{{ $radius }}"
                        data-resolve-url="{{ route('attendance.locations.resolve-link', [], false) }}"
                        data-csrf="{{ csrf_token() }}">
                        @if ($mapProvider === 'google_embed')
                        <iframe id="geofenceMapGoogle" class="geofence-map geofence-google"
                            src="https://maps.google.com/maps?q={{ $lat }},{{ $lng }}&amp;z=17&amp;output=embed"
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Google Maps"></iframe>
                        @else
                        <div id="geofenceMap" class="geofence-map"></div>
                        @endif
                        <button type="button" id="useMyLocation" class="btn btn-light btn-sm geofence-locate">
                            <i class="fa fa-location-arrow"></i> Use my current location
                        </button>
                        <div class="geofence-hint">
                            @if ($mapProvider === 'google_embed')
                            <i class="fa fa-map-marker"></i>
                            Google Maps preview. Move the point with "Use my current location", a pasted
                            Google Maps link, or the coordinate fields. The allowed radius is not drawn on
                            Google Maps - it is shown in the details panel.
                            @else
                            <i class="fa fa-hand-pointer-o"></i>
                            Click the map or drag the pin to place the office entrance, then set the radius.
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <label for="mapsLink" class="form-label small mb-1">Paste a Google Maps link or coordinates</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fa fa-link"></i></span>
                        <input type="text" id="mapsLink" class="form-control"
                            placeholder="https://maps.app.goo.gl/... or 9.0133571, 38.8371474">
                        <button type="button" class="btn btn-brand" id="applyMapsLink">Use</button>
                    </div>
                    <small id="mapsLinkHint" class="form-text text-muted">
                        In Google Maps: open a place, then Share &gt; Copy link. Short links are expanded on the
                        server, so no Google API key is needed.
                    </small>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card geofence-card h-100">
                <div class="card-header py-3">Location details</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Location name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $editing ? $location->name : '') }}"
                            placeholder="e.g. Head Office" required>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" name="address" id="address"
                            class="form-control @error('address') is-invalid @enderror"
                            value="{{ old('address', $editing ? $location->address : '') }}"
                            placeholder="Optional, e.g. Bole, Addis Ababa">
                        @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label for="radius_m" class="form-label mb-0">Allowed radius</label>
                            <span class="badge radius-badge" id="radiusLabel">{{ $radius }} m</span>
                        </div>
                        <input type="range" id="radiusRange" class="form-range mt-2"
                            min="10" max="1000" step="5" value="{{ min($radius, 1000) }}">
                        <div class="input-group input-group-sm mt-1">
                            <input type="number" name="radius_m" id="radius_m"
                                class="form-control @error('radius_m') is-invalid @enderror"
                                min="10" max="5000" step="5" value="{{ $radius }}" required>
                            <span class="input-group-text">meters</span>
                            @error('radius_m')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <small class="text-muted">GPS tolerance of up to 30 m is applied inside this radius.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Coordinates</label>
                        <div class="coordinate-box">
                            <div>Latitude: <input type="text" name="latitude" id="latitude"
                                    class="border-0 bg-transparent p-0 @error('latitude') is-invalid @enderror"
                                    value="{{ old('latitude', $editing ? $location->latitude : '') }}"
                                    readonly required></div>
                            <div>Longitude: <input type="text" name="longitude" id="longitude"
                                    class="border-0 bg-transparent p-0 @error('longitude') is-invalid @enderror"
                                    value="{{ old('longitude', $editing ? $location->longitude : '') }}"
                                    readonly required></div>
                        </div>
                        @error('latitude')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        @error('longitude')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                            value="1" @checked($isActive)>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button type="submit" class="btn btn-brand">
                        {{ $editing ? 'Update Location' : 'Save Location' }}
                    </button>
                    <a href="{{ route('attendance.locations.index') }}" class="btn btn-light">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    (function() {
        var wrap = document.getElementById('geofenceMapWrap');

        if (!wrap) {
            return;
        }

        var initialLat = parseFloat(wrap.dataset.lat);
        var initialLng = parseFloat(wrap.dataset.lng);
        var initialRadius = parseInt(wrap.dataset.radius, 10) || 100;

        if (!isFinite(initialLat) || !isFinite(initialLng)) {
            initialLat = 9.0133571;
            initialLng = 38.8371474;
        }

        var latInput = document.getElementById('latitude');
        var lngInput = document.getElementById('longitude');
        var radiusInput = document.getElementById('radius_m');
        var radiusRange = document.getElementById('radiusRange');
        var radiusLabel = document.getElementById('radiusLabel');

        var linkInput = document.getElementById('mapsLink');
        var applyLink = document.getElementById('applyMapsLink');
        var linkHint = document.getElementById('mapsLinkHint');
        var resolveUrl = wrap.dataset.resolveUrl;
        var tokenInput = document.querySelector('input[name="_token"]');
        var csrfToken = (tokenInput && tokenInput.value) || wrap.dataset.csrf;

        // Filled in by whichever map provider is active.
        var render = {
            point: function() {},
            radius: function() {}
        };

        function applyRadius(value) {
            var radius = parseInt(value, 10) || 100;
            radiusLabel.textContent = radius + ' m';
            render.radius(radius);
        }

        function applyPoint(lat, lng, recenter) {
            latInput.value = lat.toFixed(7);
            lngInput.value = lng.toFixed(7);
            render.point(lat, lng, recenter);
        }

        function initLeaflet() {
            var mapEl = document.getElementById('geofenceMap');

            if (!mapEl || typeof L === 'undefined') {
                return false;
            }

            var map = L.map('geofenceMap').setView([initialLat, initialLng], 16);

            var tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            });

            tiles.on('tileerror', function() {
                wrap.classList.add('geofence-tiles-failed');
            });

            tiles.addTo(map);

            var marker = L.marker([initialLat, initialLng], {
                draggable: true
            }).addTo(map);

            var circle = L.circle([initialLat, initialLng], {
                radius: initialRadius,
                color: '#395750',
                weight: 2,
                fillColor: '#395750',
                fillOpacity: 0.12
            }).addTo(map);

            render.point = function(lat, lng, recenter) {
                marker.setLatLng([lat, lng]);
                circle.setLatLng([lat, lng]);

                if (recenter) {
                    map.setView([lat, lng], map.getZoom());
                }
            };

            render.radius = function(radius) {
                circle.setRadius(radius);
            };

            map.on('click', function(event) {
                applyPoint(event.latlng.lat, event.latlng.lng, false);
            });

            marker.on('drag', function(event) {
                var position = event.target.getLatLng();
                applyPoint(position.lat, position.lng, false);
            });

            window.setTimeout(function() {
                map.invalidateSize();
            }, 250);

            return true;
        }

        // Google Maps embed: no API key, but the iframe cannot report clicks.
        function initGoogleEmbed() {
            var frame = document.getElementById('geofenceMapGoogle');

            if (!frame) {
                return false;
            }

            var last = initialLat.toFixed(7) + ',' + initialLng.toFixed(7);

            render.point = function(lat, lng) {
                var key = lat.toFixed(7) + ',' + lng.toFixed(7);

                if (key === last) {
                    return;
                }

                last = key;
                frame.src = 'https://maps.google.com/maps?q=' + key + '&z=17&output=embed';
            };

            return true;
        }

        // No map at all: explain why and let the coordinates be typed.
        function showMapFailure() {
            var mapEl = document.getElementById('geofenceMap');

            if (mapEl) {
                mapEl.innerHTML =
                    '<div class="geofence-map-error">' +
                    '<i class="fa fa-exclamation-triangle"></i>' +
                    '<strong>The map library could not be loaded.</strong>' +
                    '<span>Check your internet connection and reload the page, ' +
                    'or type the latitude and longitude below.</span>' +
                    '</div>';
            }

            latInput.removeAttribute('readonly');
            lngInput.removeAttribute('readonly');
            latInput.placeholder = 'e.g. 9.0133571';
            lngInput.placeholder = 'e.g. 38.8371474';
        }

        if (!initGoogleEmbed() && !initLeaflet()) {
            showMapFailure();
        }

        // Seed the form so it is valid even if the pin is never moved.
        applyPoint(initialLat, initialLng, false);
        applyRadius(initialRadius);

        radiusInput.addEventListener('input', function() {
            radiusRange.value = Math.min(parseInt(this.value, 10) || 100, 1000);
            applyRadius(this.value);
        });

        radiusRange.addEventListener('input', function() {
            radiusInput.value = this.value;
            applyRadius(this.value);
        });

        document.getElementById('useMyLocation').addEventListener('click', function() {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by this browser.');
                return;
            }

            var button = this;
            button.disabled = true;

            navigator.geolocation.getCurrentPosition(function(position) {
                applyPoint(position.coords.latitude, position.coords.longitude, true);
                button.disabled = false;
            }, function() {
                alert('Could not read your location. Allow location access and try again.');
                button.disabled = false;
            }, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            });
        });

        // Accepts "lat, lng" and every Google Maps link shape. Order matters:
        //   ...!3d9.0133571!4d38.8371474   the actual place pin
        //   ...?q=9.0133571,38.8371474     explicit query coordinates
        //   .../place/9.0133571,38.8371474
        //   .../@9.0133571,38.8371474,17z  viewport center, used only as a fallback
        function parseCoordinates(text) {
            if (!text) {
                return null;
            }

            text = text.trim();

            var patterns = [
                /^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/,
                /!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/,
                /[?&](?:q|ll|daddr|destination|center)=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/,
                /\/place\/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/,
                /@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/
            ];

            for (var i = 0; i < patterns.length; i++) {
                var match = text.match(patterns[i]);

                if (match) {
                    return {
                        lat: parseFloat(match[1]),
                        lng: parseFloat(match[2])
                    };
                }
            }

            return null;
        }

        function showLinkHint(message, isError) {
            if (!linkHint) {
                return;
            }

            linkHint.textContent = message;
            linkHint.className = isError ? 'form-text text-danger' : 'form-text text-muted';
        }

        // Short maps.app.goo.gl links are HTTP redirects that a browser cannot
        // follow cross-origin, so the server expands them and returns the pin.
        function resolveLinkOnServer(text) {
            return fetch(resolveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    link: text
                })
            }).then(function(response) {
                return response.json().catch(function() {
                    return {};
                }).then(function(payload) {
                    return {
                        ok: response.ok,
                        payload: payload || {}
                    };
                });
            });
        }

        function useResolvedPoint(latitude, longitude) {
            var lat = parseFloat(latitude);
            var lng = parseFloat(longitude);

            if (!isFinite(lat) || !isFinite(lng)) {
                showLinkHint('That link did not contain a usable point.', true);
                return;
            }

            applyPoint(lat, lng, true);
            linkInput.value = lat.toFixed(7) + ', ' + lng.toFixed(7);
            showLinkHint('Point updated from the link.', false);
        }

        applyLink.addEventListener('click', function() {
            var text = linkInput.value.trim();
            var point = parseCoordinates(text);

            // Anything readable in the browser wins: instant, no request.
            if (point && Math.abs(point.lat) <= 90 && Math.abs(point.lng) <= 180) {
                applyPoint(point.lat, point.lng, true);
                linkInput.value = point.lat.toFixed(7) + ', ' + point.lng.toFixed(7);
                showLinkHint('Point updated from the link.', false);
                return;
            }

            if (!/^https?:\/\//i.test(text)) {
                showLinkHint('No coordinates found. Paste a Google Maps link or "latitude, longitude".', true);
                return;
            }

            var button = this;
            button.disabled = true;
            showLinkHint('Reading the link...', false);

            resolveLinkOnServer(text).then(function(result) {
                button.disabled = false;

                if (!result.ok || !result.payload.success) {
                    showLinkHint(result.payload.message || 'Could not read that link.', true);
                    return;
                }

                useResolvedPoint(result.payload.latitude, result.payload.longitude);
            }).catch(function() {
                button.disabled = false;
                showLinkHint('Could not reach the server. Check your connection and try again.', true);
            });
        });

        linkInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyLink.click();
            }
        });
    })();
</script>