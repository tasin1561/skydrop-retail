<?php

namespace App\Models;

use App\Services\Money\MembershipService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    protected $guarded = [];

    public const PERIODS = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];

    public const MONTHS = ['monthly' => 1, 'quarterly' => 3, 'yearly' => 12];

    protected function casts(): array
    {
        return ['price_bdt' => 'float', 'active' => 'boolean', 'highlight' => 'boolean', 'benefits' => 'array', 'sort' => 'integer'];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'plan_id');
    }

    public function months(): int
    {
        return self::MONTHS[$this->period] ?? 1;
    }

    public function toApi(): array
    {
        $b = MembershipService::normalize($this->benefits ?? []);

        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'priceBdt' => (int) round($this->price_bdt),
            'period' => $this->period, 'months' => $this->months(), 'monthlyBdt' => (int) round($this->price_bdt / $this->months()),
            'highlight' => $this->highlight, 'tagline' => $this->tagline, 'benefits' => MembershipService::benefitsApi($b),
            'benefitList' => MembershipService::describe($b),
        ];
    }
}
