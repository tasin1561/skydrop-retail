<?php

namespace App\Services\Commerce;

// PHP copy of apps/web/src/lib/bd.ts. Keep both in sync.
final class BdGeo
{
    public const DIVISIONS = [
        'Dhaka' => ['Dhaka', 'Gazipur', 'Narayanganj', 'Narsingdi', 'Manikganj', 'Munshiganj', 'Tangail', 'Faridpur', 'Gopalganj', 'Kishoreganj', 'Madaripur', 'Rajbari', 'Shariatpur'],
        'Chattogram' => ['Chattogram', "Cox's Bazar", 'Cumilla', 'Feni', 'Noakhali', 'Lakshmipur', 'Chandpur', 'Brahmanbaria', 'Rangamati', 'Khagrachhari', 'Bandarban'],
        'Rajshahi' => ['Rajshahi', 'Bogura', 'Pabna', 'Sirajganj', 'Natore', 'Naogaon', 'Chapainawabganj', 'Joypurhat'],
        'Khulna' => ['Khulna', 'Jashore', 'Satkhira', 'Bagerhat', 'Kushtia', 'Jhenaidah', 'Magura', 'Narail', 'Chuadanga', 'Meherpur'],
        'Barishal' => ['Barishal', 'Patuakhali', 'Bhola', 'Pirojpur', 'Jhalokati', 'Barguna'],
        'Sylhet' => ['Sylhet', 'Moulvibazar', 'Habiganj', 'Sunamganj'],
        'Rangpur' => ['Rangpur', 'Dinajpur', 'Kurigram', 'Gaibandha', 'Nilphamari', 'Lalmonirhat', 'Thakurgaon', 'Panchagarh'],
        'Mymensingh' => ['Mymensingh', 'Jamalpur', 'Netrokona', 'Sherpur'],
    ];

    public static function validDivision(?string $division): bool
    {
        return is_string($division) && array_key_exists($division, self::DIVISIONS);
    }

    public static function validDistrict(?string $division, ?string $district): bool
    {
        return self::validDivision($division) && in_array($district, self::DIVISIONS[$division], true);
    }
}
