<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AttendanceQrCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'signed_url',
        'qr_base64',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Total lifetime of a QR (signed URL + DB row).
     */
    public const TTL_MINUTES = 30;

    /**
     * Minimum remaining life before fresh() reuses an existing row.
     * Prevents handing out a QR that expires mid-scan.
     */
    public const REUSE_BUFFER_MINUTES = 1;

    // ============================================================
    // Factory helpers
    // ============================================================

    /**
     * Get-or-create a QR row for this user/type.
     * Reuses an existing one only if it will still be valid for
     * at least REUSE_BUFFER_MINUTES. Otherwise regenerates.
     */
    public static function freshFor(int $userId, string $type): self
    {
        $row = static::where('user_id', $userId)
            ->where('type', $type)
            ->first();

        if (
            $row
            && $row->qr_base64
            && $row->expires_at
            && $row->expires_at->gt(now()->addMinutes(self::REUSE_BUFFER_MINUTES))
            && $row->matchesCurrentHost()
        ) {
            return $row;
        }

        return static::regenerateFor($userId, $type);
    }

    /**
     * A QR embeds an absolute URL, so one minted on a different host or
     * scheme (localhost, a LAN IP, another domain, or the same app reached
     * over a tunnel) would send the phone somewhere it cannot reach - or
     * make the signature fail. Only reuse a stored QR while it still points
     * at the host serving this request.
     */
    public function matchesCurrentHost(): bool
    {
        if (! is_string($this->signed_url) || $this->signed_url === '') {
            return false;
        }

        return str_starts_with($this->signed_url, rtrim(url('/'), '/') . '/');
    }

    /**
     * Force a brand-new signed URL + QR for this user/type.
     * Uses updateOrCreate() so the unique (user_id, type) key is honoured.
     */
    public static function regenerateFor(int $userId, string $type): self
    {
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        // Pass a copy so temporarySignedRoute() can't mutate our stored value.
        $url = URL::temporarySignedRoute(
            'attendance.qrCheckin',
            $expiresAt->copy(),
            ['user_id' => $userId, 'type' => $type]
        );

        $record = static::updateOrCreate(
            ['user_id' => $userId, 'type' => $type],
            [
                'signed_url' => $url,
                'expires_at' => $expiresAt,
                'qr_base64'  => null,   // force regeneration below
            ]
        );

        $record->generateQrCode();

        return $record->refresh();
    }

    // ============================================================
    // QR generation — API → GD → HTML fallback
    // ============================================================

    /**
     * Generate the QR payload and persist it to qr_base64.
     *
     * Returns the strategy used: 'api' | 'gd' | 'html'.
     */
    public function generateQrCode(): string
    {
        [$strategy, $base64] = $this->buildQrPayload();

        $this->qr_base64 = $base64;
        $this->save();

        Log::info('Attendance QR generated', [
            'id'       => $this->id,
            'user_id'  => $this->user_id,
            'type'     => $this->type,
            'strategy' => $strategy,
        ]);

        return $strategy;
    }

    /**
     * @return array{0: string, 1: string}  [strategy, base64Payload]
     */
    protected function buildQrPayload(): array
    {
        // 1) External QR API — returns raw PNG bytes.
        try {
            $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='
                . urlencode($this->signed_url);

            $response = Http::timeout(5)
                ->retry(1, 200)
                ->get($apiUrl);

            if (
                $response->successful()
                && str_starts_with($response->body(), "\x89PNG")
            ) {
                return ['api', base64_encode($response->body())];
            }

            Log::warning('QR API returned non-PNG response', [
                'status' => $response->status(),
                'bytes'  => strlen($response->body()),
            ]);
        } catch (\Throwable $e) {
            Log::warning('QR API failed: ' . $e->getMessage());
        }

        // 2) GD fallback via milon/barcode (DNS2D returns base64 of PNG).
        try {
            if (class_exists(\Milon\Barcode\Facades\DNS2DFacade::class)) {
                $barcode = \Milon\Barcode\Facades\DNS2DFacade::getBarcodePNG(
                    $this->signed_url,
                    'QRCODE',
                    10,
                    10
                );

                if (is_string($barcode) && $barcode !== '') {
                    $decoded = base64_decode($barcode, true);

                    if ($decoded !== false && str_starts_with($decoded, "\x89PNG")) {
                        return ['gd', base64_encode($decoded)];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('QR GD fallback failed: ' . $e->getMessage());
        }

        // 3) Final fallback — HTML page with the signed URL.
        return ['html', base64_encode($this->buildHtmlFallback())];
    }

    protected function buildHtmlFallback(): string
    {
        $safeUrl = htmlspecialchars($this->signed_url, ENT_QUOTES, 'UTF-8');
        $type    = strtoupper($this->type);

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance QR</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .url  { word-break: break-all; background: #e9ecef; padding: 10px; border-radius: 5px; }
        .btn  { display: inline-block; margin-top: 20px; padding: 10px 20px;
                background: #395750; color: #fff; text-decoration: none; border-radius: 6px; }
    </style>
</head>
<body>
    <h2>Attendance Check-' . $type . '</h2>
    <p>Open this link on your phone to punch:</p>
    <div class="url">' . $safeUrl . '</div>
    <a class="btn" href="' . $safeUrl . '">Open Check-' . $type . '</a>
</body>
</html>';
    }

    // ============================================================
    // Accessors used by the view
    // ============================================================

    /**
     * True when qr_base64 holds a PNG.
     */
    public function isImage(): bool
    {
        if (!$this->qr_base64) {
            return false;
        }

        $decoded = base64_decode($this->qr_base64, true);

        return $decoded !== false && str_starts_with($decoded, "\x89PNG");
    }

    /**
     * True when qr_base64 holds the HTML fallback.
     */
    public function isHtml(): bool
    {
        if (!$this->qr_base64) {
            return false;
        }

        $decoded = base64_decode($this->qr_base64, true);

        if ($decoded === false) {
            return false;
        }

        return str_starts_with($decoded, '<!DOCTYPE')
            || str_starts_with($decoded, '<!doctype')
            || str_starts_with($decoded, '<html');
    }

    /**
     * Ready-to-use data URL for <img src="...">, or null if not a PNG.
     */
    public function getQrDataUrl(): ?string
    {
        if (!$this->isImage()) {
            return null;
        }

        return 'data:image/png;base64,' . $this->qr_base64;
    }

    /**
     * Decoded HTML fallback, or null when the payload is a PNG.
     */
    public function getQrHtml(): ?string
    {
        if (!$this->isHtml()) {
            return null;
        }

        return base64_decode($this->qr_base64);
    }

    // ============================================================
    // Relations
    // ============================================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
