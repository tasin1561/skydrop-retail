// "Can this ship to Bangladesh?" rules for demo mode. The API is the source of truth in production
// (restricted_rules table, product JSON `restriction`). Keep in sync with apps/api/database/seeders/RestrictedRuleSeeder.php.
import type { Product, Restriction } from "./types";

type Rule = { re: RegExp; action: Restriction["action"]; message: string };

const RULES: Rule[] = [
  {
    re: /\b(power ?banks?|spare (li-?ion |lithium )?batter(y|ies)|lithium(-ion)? batter(y|ies)( pack)?|li-?ion batter(y|ies)|18650|rechargeable batter(y|ies) pack)\b/i,
    action: "warn",
    message: "Standalone lithium batteries and power banks have air-cargo limits. We may need to send this by road, which can add a few days.",
  },
  {
    re: /\b(aerosols?|spray paint|body spray|deo(dorant)? spray|lighter fluid|butane|camping gas|kerosene|petrol|paint thinner|nail polish remover|flammable)\b/i,
    action: "block",
    message: "Aerosols and flammable items cannot be carried across the border, so we cannot order this one.",
  },
  {
    re: /\b(?:[2-9]|[1-9]\d+|1\.\d*[1-9])\s*(?:l|ltr|litre|liter|litres|liters)\b|\b(?:[2-9]\d{3}|1\d{2}[1-9]|1[1-9]\d{2})\s*ml\b/i,
    action: "warn",
    message: "Liquids over 1 litre are heavy and need extra packing. Shipping is charged by weight and delivery can take a little longer.",
  },
  {
    re: /\b(medicines?|supplements?|multivitamins?|vitamin (tablets|capsules|gummies)|whey protein|protein powder|ayurvedic medicine|homeopathic|melatonin|prescription)\b/i,
    action: "warn",
    message: "Medicines and supplements may need a prescription or DGDA clearance in Bangladesh. Customs can hold the parcel until it is cleared.",
  },
  {
    re: /\b(knife|knives|daggers?|swords?|machetes?|pepper spray|stun guns?|tasers?|air guns?|air rifles?|airsoft|bb guns?|crossbows?|ammunition|nunchaku|knuckle ?dusters?)\b/i,
    action: "block",
    message: "Weapons, knives and self-defence items cannot be imported through Skydrop.",
  },
  { re: /\b(drones?|quadcopters?)\b/i, action: "warn", message: "Drones need BTRC permission to import into Bangladesh. Customs may hold the parcel until you show the permission." },
  {
    re: /\b(seeds for (planting|sowing)|(vegetable|flower|garden|gardening|fruit|herb) seeds|seed kit|live plants?|saplings?|plant bulbs|flower bulbs|seedlings?)\b/i,
    action: "block",
    message: "Seeds and live plants need phytosanitary certificates, so we cannot bring them in.",
  },
  { re: /\b(raw meat|fresh meat|frozen meat|chicken breast|mutton|beef|pork|sausages?|salami|bacon)\b/i, action: "block", message: "Meat products cannot be imported through Skydrop." },
  {
    re: /\b(whisk(e)?y|vodka|rum|tequila|brandy|liquor|beer|wine|alcoholic)\b(?!\s*(glass|glasses|opener|rack|decanter|mug|cooler|chiller|stopper|aerator|cork))/i,
    action: "block",
    message: "Alcohol cannot be imported through Skydrop.",
  },
  {
    re: /\b(cigarettes?(?!\s*lighter)|cigars?|tobacco|vapes?|vaping|e-?cigarettes?|e-?liquid|nicotine|shisha|hookah flavou?r)\b/i,
    action: "block",
    message: "Tobacco, vapes and nicotine products cannot be imported through Skydrop.",
  },
];

const RANK = { block: 3, surcharge: 2, warn: 1 } as const;

export function checkRestriction(title: string): Restriction | null {
  let best: Restriction | null = null;
  for (const r of RULES) {
    if (r.re.test(title) && (!best || RANK[r.action] > RANK[best.action])) best = { action: r.action, message: r.message };
  }
  return best;
}

/** The product's restriction: from the API when present, otherwise the demo rules on its title. */
export function restrictionOf(p: Pick<Product, "title" | "restriction">): Restriction | null {
  return p.restriction !== undefined ? p.restriction : checkRestriction(p.title);
}
