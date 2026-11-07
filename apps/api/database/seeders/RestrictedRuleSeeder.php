<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Default "can this ship to Bangladesh?" rules. Idempotent (matched on pattern).
 * Patterns: comma-separated words/phrases matched on word boundaries, or a /regex/i.
 * Keep apps/web/src/lib/restrictions.ts (demo mode) in sync.
 */
class RestrictedRuleSeeder extends Seeder
{
    public const DEFAULTS = [
        ['keyword', '/\b(power ?banks?|spare (li-?ion |lithium )?batter(y|ies)|lithium(-ion)? batter(y|ies)( pack)?|li-?ion batter(y|ies)|18650|rechargeable batter(y|ies) pack)\b/i', 'warn',
            'Standalone lithium batteries and power banks have air-cargo limits. We may need to send this by road, which can add a few days.'],
        ['keyword', '/\b(aerosols?|spray paint|body spray|deo(dorant)? spray|lighter fluid|butane|camping gas|kerosene|petrol|paint thinner|nail polish remover|flammable)\b/i', 'block',
            'Aerosols and flammable items cannot be carried across the border, so we cannot order this one.'],
        ['keyword', '/\b(?:[2-9]|[1-9]\d+|1\.\d*[1-9])\s*(?:l|ltr|litre|liter|litres|liters)\b|\b(?:[2-9]\d{3}|1\d{2}[1-9]|1[1-9]\d{2})\s*ml\b/i', 'warn',
            'Liquids over 1 litre are heavy and need extra packing. Shipping is charged by weight and delivery can take a little longer.'],
        ['keyword', '/\b(medicines?|supplements?|multivitamins?|vitamin (tablets|capsules|gummies)|whey protein|protein powder|ayurvedic medicine|homeopathic|melatonin|prescription)\b/i', 'warn',
            'Medicines and supplements may need a prescription or DGDA clearance in Bangladesh. Customs can hold the parcel until it is cleared.'],
        ['keyword', '/\b(knife|knives|daggers?|swords?|machetes?|pepper spray|stun guns?|tasers?|air guns?|air rifles?|airsoft|bb guns?|crossbows?|ammunition|nunchaku|knuckle ?dusters?)\b/i', 'block',
            'Weapons, knives and self-defence items cannot be imported through Skydrop.'],
        ['keyword', '/\b(drones?|quadcopters?)\b/i', 'warn',
            'Drones need BTRC permission to import into Bangladesh. Customs may hold the parcel until you show the permission.'],
        ['keyword', '/\b(seeds for (planting|sowing)|(vegetable|flower|garden|gardening|fruit|herb) seeds|seed kit|live plants?|saplings?|plant bulbs|flower bulbs|seedlings?)\b/i', 'block',
            'Seeds and live plants need phytosanitary certificates, so we cannot bring them in.'],
        ['keyword', '/\b(raw meat|fresh meat|frozen meat|chicken breast|mutton|beef|pork|sausages?|salami|bacon)\b/i', 'block',
            'Meat products cannot be imported through Skydrop.'],
        ['keyword', '/\b(whisk(e)?y|vodka|rum|tequila|brandy|liquor|beer|wine|alcoholic)\b(?!\s*(glass|glasses|opener|rack|decanter|mug|cooler|chiller|stopper|aerator|cork))/i', 'block',
            'Alcohol cannot be imported through Skydrop.'],
        ['keyword', '/\b(cigarettes?(?!\s*lighter)|cigars?|tobacco|vapes?|vaping|e-?cigarettes?|e-?liquid|nicotine|shisha|hookah flavou?r)\b/i', 'block',
            'Tobacco, vapes and nicotine products cannot be imported through Skydrop.'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $i => [$type, $pattern, $action, $message]) {
            if (DB::table('restricted_rules')->where('pattern', $pattern)->exists()) {
                continue;
            }
            DB::table('restricted_rules')->insert([
                'type' => $type, 'pattern' => $pattern, 'action' => $action, 'message' => $message, 'surcharge_bdt' => 0,
                'active' => true, 'sort' => $i, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
