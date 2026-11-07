<?php

// AI shopping assistant (Claude API). Runtime overrides live in the settings table under the same
// dotted key prefixed with "assistant." (see App\Services\Assistant\AssistantSettings and Admin → AI assistant).
return [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    'version' => '2023-06-01',
    'timeout' => (int) env('ASSISTANT_TIMEOUT', 90),

    // Shared secret the Next.js proxy sends as X-Internal-Key so the API trusts its X-Client-IP header.
    'internal_key' => env('INTERNAL_API_KEY'),

    'model' => env('ASSISTANT_MODEL', 'claude-sonnet-5-5'),
    'fallback_model' => env('ASSISTANT_FALLBACK_MODEL', 'claude-haiku-5-5'),
    // Switch to the fallback model once this share of the monthly budget is spent (0 = never).
    'fallback_at_pct' => 80,
    'max_tokens' => 1024,
    'max_tool_rounds' => 5,
    'history_turns' => 10,

    'monthly_budget_usd' => (float) env('ASSISTANT_MONTHLY_BUDGET_USD', 50),

    // Messages per day (UTC) per signed-in user, or per IP for guests.
    'limits' => ['guest' => 10, 'user' => 40, 'plus' => 120],

    // USD per million tokens. Cache writes cost 1.25x input, cache reads 0.1x input.
    'prices' => [
        'claude-sonnet-5-5' => ['in' => 3.0, 'out' => 15.0],
        'claude-haiku-5-5' => ['in' => 1.0, 'out' => 5.0],
        '*' => ['in' => 3.0, 'out' => 15.0],
    ],

    'models' => [
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (best answers)',
        'claude-haiku-5-5' => 'Claude Haiku 5.5 (cheaper, faster)',
    ],

    'greeting' => [
        'en' => "Hi! I'm Skydrop AI. Tell me what you're looking for and your budget in taka, and I'll find it on amazon.in, Flipkart or BigBasket.",
        'bn' => 'হ্যালো! আমি Skydrop AI। কী খুঁজছেন আর বাজেট কত টাকা বলুন, আমি amazon.in, Flipkart বা BigBasket থেকে খুঁজে দেব।',
    ],

    'prompts' => [
        'en' => ['Best earbuds under ৳3,000', 'Compare two phones for me', 'Gift ideas for mom', 'Where is my order?'],
        'bn' => ['৩০০০ টাকার মধ্যে ভালো ইয়ারবাড', 'ঈদের আগে ডেলিভারি পাব?', 'মায়ের জন্য গিফট আইডিয়া', 'আমার অর্ডার কোথায়?'],
    ],

    // Upcoming holidays that affect delivery: [{ name, date: Y-m-d, note? }]. Order-by = date - 12 days.
    'holidays' => [],
];
