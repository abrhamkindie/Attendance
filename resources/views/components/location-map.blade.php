@props([
'lat' => null,
'lng' => null,
'radius' => 100,
'latField' => 'latitude',
'lngField' => 'longitude',
'radiusField' => 'radius_m',
'height' => '360px',
])

@php
$lat = $lat !== null ? (float) $lat : 9.03; // neutral default centre
$lng = $lng !== null ? (float) $lng : 38.74;
$radius = (int) ($radius ?: 100);
$mapId = 'location-map-'.\Illuminate\Support\Str::random(6);
@endphp

{{--
    Reusable location picker for the Location / Branch forms.

    • Leaflet 1.9.4 map (unpkg) — click or drag the marker to set lat/lng,
      the circle visualises the geofence radius.
    • Google Maps embed iframe — a no-API-key static preview of the pin.

    It writes back into the sibling hidden inputs (latitude/longitude/radius)
    so the surrounding <form> submits normalised coordinates.
--}}
<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    <div
        id="{{ $mapId }}"
        class="rounded-lg overflow-hidden ring-1 ring-gray-200 z-0"
        style="height: {{ $height }};"
        data-lat="{{ $lat }}"
        data-lng="{{ $lng }}"
        data-radius="{{ $radius }}"
        data-lat-field="{{ $latField }}"
        data-lng-field="{{ $lngField }}"
        data-radius-field="{{ $radiusField }}"></div>

    {{-- Google Maps embed iframe (no key required for a basic pin preview) --}}
    <iframe
        id="{{ $mapId }}-gmaps"
        class="w-full rounded-lg ring-1 ring-gray-200 z-0"
        style="height: 180px; border:0;"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        src="https://www.google.com/maps?q={{ $lat }},{{ $lng }}&z=16&output=embed">
    </iframe>

    <p class="text-xs text-gray-500">
        Click the map to drop a pin, or drag it. Coordinates sync to the form fields automatically.
    </p>
</div>

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    (function() {
        if (typeof L === 'undefined') {
            return;
        }

        document.querySelectorAll('[data-lat-field][id^="location-map-"]:not(.map-initialised)').forEach(function(el) {
            el.classList.add('map-initialised');

            const lat = parseFloat(el.dataset.lat);
            const lng = parseFloat(el.dataset.lng);
            const radius = parseInt(el.dataset.radius, 10) || 100;
            const iframe = document.getElementById(el.id + '-gmaps');

            const setField = (name, value) => {
                const input = document.querySelector('[name="' + name + '"]');
                if (input) {
                    input.value = value;
                }
            };

            const map = L.map(el.id).setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const marker = L.marker([lat, lng], {
                draggable: true
            }).addTo(map);
            const circle = L.circle([lat, lng], {
                radius: radius,
                color: '#395750',
                fillOpacity: 0.1
            }).addTo(map);

            function sync(latlng) {
                const la = latlng.lat.toFixed(6);
                const ln = latlng.lng.toFixed(6);
                setField(el.dataset.latField, la);
                setField(el.dataset.lngField, ln);
                marker.setLatLng(latlng);
                circle.setLatLng(latlng);
                if (iframe) {
                    iframe.src = 'https://www.google.com/maps?q=' + la + ',' + ln + '&z=16&output=embed';
                }
            }

            map.on('click', (e) => sync(e.latlng));
            marker.on('dragend', (e) => sync(e.target.getLatLng()));

            const radiusInput = document.querySelector('[name="' + el.dataset.radiusField + '"]');
            if (radiusInput) {
                radiusInput.addEventListener('input', () => {
                    circle.setRadius(parseInt(radiusInput.value, 10) || 0);
                });
            }
        });
    })();
</script>
@endpush