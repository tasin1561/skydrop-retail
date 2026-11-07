<?php

namespace App\Services\Catalog;

/** Maps store breadcrumbs or titles onto our five pricing categories. */
class CategoryGuesser
{
    private const PATH = [
        'electronics' => '/electronic|computer|mobile|phone|camera|headphone|audio|video game|wearable|smart/i',
        'fashion' => '/cloth|fashion|shoe|footwear|jewel|watch|bag|luggage|apparel|eyewear/i',
        'beauty' => '/beauty|personal care|make.?up|skin|hair|fragrance|health/i',
        'grocery' => '/grocery|gourmet|food|beverage|snack|tea|coffee|staples|fruits|drinks/i',
        'home' => '/home|kitchen|furniture|garden|tool|appliance|household|cleaning|decor|baby|toy|pet/i',
    ];

    private const TITLE = [
        'electronics' => '/\b(earbuds?|headphones?|earphones?|speaker|charger|cable|power ?bank|smart ?watch|phone|mobile|laptop|tablet|mouse|keyboard|tws|bluetooth|usb|ssd|camera)\b/i',
        'fashion' => '/\b(shirt|t-shirt|jeans|kurta|saree|dress|shoes?|sneakers?|sandals?|slippers?|trousers?|jacket|watch|wallet|handbag|socks)\b/i',
        'beauty' => '/\b(lipstick|serum|cream|lotion|shampoo|conditioner|face ?wash|sunscreen|perfume|deo|moisturi[sz]er|kajal|foundation|nail)\b/i',
        'grocery' => '/\b(juice|tea|coffee|rice|atta|dal|masala|spice|oil|ghee|biscuits?|chips|snacks?|chocolate|honey|noodles|makhana|nuts)\b/i',
        'home' => '/\b(bottle|cookware|pan|kadai|bedsheet|pillow|curtain|lamp|storage|container|mop|detergent|cleaner)\b/i',
    ];

    /** @param list<string> $path */
    public function guess(string $market, string $title, array $path = []): string
    {
        if ($path) {
            $root = implode(' ', array_slice($path, 0, 2));
            foreach (self::PATH as $cat => $re) {
                if (preg_match($re, $root)) {
                    return $cat;
                }
            }
        }
        foreach (self::TITLE as $cat => $re) {
            if (preg_match($re, $title)) {
                return $cat;
            }
        }

        return $market === 'bigbasket' ? 'grocery' : ($market === 'flipkart' ? 'fashion' : 'electronics');
    }
}
