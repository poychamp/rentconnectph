<?php

namespace App\Support;

class MapboxStaticUrl
{
    public static function forCoords(?float $lat, ?float $lng, int $width = 800, int $height = 300): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        $token = config('services.mapbox.token');

        if (empty($token)) {
            return "https://placehold.co/{$width}x{$height}/e5e7eb/6b7280?text=Map";
        }

        return sprintf(
            'https://api.mapbox.com/styles/v1/mapbox/streets-v12/static/pin-s+f97316(%.7F,%.7F)/%.7F,%.7F,14/%dx%d@2x?access_token=%s',
            $lng,
            $lat,
            $lng,
            $lat,
            $width,
            $height,
            $token
        );
    }
}
