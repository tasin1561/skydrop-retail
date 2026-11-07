<?php

namespace App\Services\Commerce;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Address book with exactly one default address per user.
class AddressService
{
    /** Validation rules for the web Address shape (camelCase). */
    public static function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:80'],
            'phone' => [$req, 'string', 'max:20', function ($attr, $value, $fail) {
                if (! Phone::normalize($value)) {
                    $fail('Enter a valid Bangladeshi mobile number.');
                }
            }],
            'division' => [$req, 'string', function ($attr, $value, $fail) {
                if (! BdGeo::validDivision($value)) {
                    $fail('Choose a valid division.');
                }
            }],
            'district' => [$req, 'string', 'max:40'],
            'area' => [$req, 'string', 'max:80'],
            'line' => [$req, 'string', 'max:255'],
            'isDefault' => ['sometimes', 'boolean'],
        ];
    }

    /** Validates a full address payload (division/district pair included). */
    public function validate(array $data, ?Address $current = null): array
    {
        $v = Validator::make($data, self::rules($current !== null));
        $v->after(function ($v) use ($data, $current) {
            $division = $data['division'] ?? $current?->division;
            $district = $data['district'] ?? $current?->district;
            if ((isset($data['division']) || isset($data['district'])) && BdGeo::validDivision($division) && ! BdGeo::validDistrict($division, $district)) {
                $v->errors()->add('district', "Choose a district in {$division}.");
            }
        });

        return $v->validate();
    }

    public function create(User $user, array $data, bool $enforceLimit = true): Address
    {
        $data = $this->validate($data);
        $max = \App\Support\Limits::int('accounts.max_addresses'); // System → Limits
        if ($enforceLimit && $max > 0 && $user->addresses()->count() >= $max) {
            throw CommerceException::field('address', "You can save up to {$max} addresses. Delete one you no longer use first.");
        }

        return DB::transaction(function () use ($user, $data) {
            $first = ! $user->addresses()->exists();
            $address = $user->addresses()->create($this->columns($data) + ['is_default' => false]);
            if ($first || ! empty($data['isDefault'])) {
                $this->makeDefault($user, $address);
            }

            return $address->refresh();
        });
    }

    public function update(Address $address, array $data): Address
    {
        $data = $this->validate($data, $address);

        return DB::transaction(function () use ($address, $data) {
            $address->update($this->columns($data));
            if (array_key_exists('isDefault', $data)) {
                if ($data['isDefault']) {
                    $this->makeDefault($address->user, $address);
                } elseif ($address->is_default) {
                    // Hand the default to another address; the only address stays default.
                    $other = $address->user->addresses()->whereKeyNot($address->id)->latest('id')->first();
                    if ($other) {
                        $this->makeDefault($address->user, $other);
                    }
                }
            }

            return $address->refresh();
        });
    }

    public function delete(Address $address): void
    {
        DB::transaction(function () use ($address) {
            $user = $address->user;
            $wasDefault = $address->is_default;
            $address->delete();
            if ($wasDefault && ($next = $user->addresses()->latest('id')->first())) {
                $this->makeDefault($user, $next);
            }
        });
    }

    /** Finds an identical saved address or saves a new one (used for inline addresses at checkout). */
    public function findOrCreate(User $user, array $data): Address
    {
        $data = $this->validate($data);
        $cols = $this->columns($data);
        $existing = $user->addresses()->where($cols)->first();

        return $existing ?? $this->create($user, $data, false); // checkout must not fail on the saved-address limit
    }

    public function makeDefault(User $user, Address $address): void
    {
        $user->addresses()->whereKeyNot($address->id)->where('is_default', true)->update(['is_default' => false]);
        $address->forceFill(['is_default' => true])->save();
    }

    private function columns(array $data): array
    {
        $out = [];
        foreach (['name', 'division', 'district', 'area', 'line'] as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = trim((string) $data[$k]);
            }
        }
        if (array_key_exists('phone', $data)) {
            $out['phone'] = Phone::normalize($data['phone']);
        }

        return $out;
    }
}
