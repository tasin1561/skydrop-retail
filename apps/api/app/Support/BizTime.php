<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Business calendar: the shop runs on Bangladesh time while the app stores UTC. Reports and exports group by and print
 * Dhaka dates, so an order placed at 01:30 in Dhaka (19:30 UTC the day before) lands on the right day.
 */
class BizTime
{
    public const TZ = 'Asia/Dhaka';

    /** UTC instant where the Dhaka calendar day of $day starts. */
    public static function dayStart(CarbonInterface|string $day): Carbon
    {
        return Carbon::parse(self::dateString($day), self::TZ)->startOfDay()->utc();
    }

    /** UTC instant where the Dhaka calendar day of $day ends. */
    public static function dayEnd(CarbonInterface|string $day): Carbon
    {
        return Carbon::parse(self::dateString($day), self::TZ)->endOfDay()->utc();
    }

    /** Y-m-d of a stored (UTC) timestamp in Dhaka. */
    public static function date(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(self::TZ)->toDateString();
    }

    /** Y-m-d H:i:s of a stored (UTC) timestamp in Dhaka. */
    public static function dateTime(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(self::TZ)->toDateTimeString();
    }

    private static function dateString(CarbonInterface|string $day): string
    {
        return $day instanceof CarbonInterface ? $day->toDateString() : Carbon::parse($day)->toDateString();
    }
}
