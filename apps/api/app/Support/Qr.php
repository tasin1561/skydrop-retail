<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** QR codes as inline SVG (chillerlan/php-qrcode, already a Filament 2FA dependency). */
class Qr
{
    public static function svg(string $text, int $scale = 5): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'scale' => $scale,
            'eccLevel' => EccLevel::M,
            'connectPaths' => true,
        ]);

        return (new QRCode($options))->render($text);
    }

    public static function dataUri(string $text): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($text));
    }

    /** Only our own site URLs may be encoded by the public endpoint (no free QR service for third parties). */
    public static function allowed(string $text): bool
    {
        $host = parse_url($text, PHP_URL_HOST);
        if (! $host || ! in_array(parse_url($text, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return false;
        }
        $own = array_filter([parse_url((string) config('retail.web_url'), PHP_URL_HOST), parse_url((string) config('app.url'), PHP_URL_HOST),
            ...array_map('trim', explode(',', (string) env('QR_ALLOWED_HOSTS', '')))]);

        return in_array(strtolower($host), array_map('strtolower', $own), true);
    }
}
