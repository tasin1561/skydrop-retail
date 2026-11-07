<?php

namespace App\Services\Audit;

use Illuminate\Database\Eloquent\Model;

/** Logs create/update/delete with a field diff when a staff member is signed in (admin panel). */
class ActivityObserver
{
    private const IGNORE = ['updated_at', 'created_at'];

    public function created(Model $model): void
    {
        if (ActivityLogger::isStaff()) {
            ActivityLogger::log('created', $model, collect($model->getAttributes())->except(self::IGNORE)->map(fn ($v) => [null, $v])->all());
        }
    }

    public function updated(Model $model): void
    {
        if (! ActivityLogger::isStaff()) {
            return;
        }
        $diff = [];
        foreach (array_keys($model->getChanges()) as $field) {
            if (in_array($field, self::IGNORE, true)) {
                continue;
            }
            $diff[$field] = [$model->getOriginal($field), $model->getAttribute($field)];
        }
        if ($diff) {
            ActivityLogger::log('updated', $model, self::normalise($diff));
        }
    }

    public function deleted(Model $model): void
    {
        if (ActivityLogger::isStaff()) {
            ActivityLogger::log('deleted', $model, collect($model->getAttributes())->except(self::IGNORE)->map(fn ($v) => [$v, null])->all());
        }
    }

    /** Dates, enums and casts → JSON-friendly scalars. */
    private static function normalise(array $diff): array
    {
        return array_map(fn ($pair) => array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format(DATE_ATOM)
            : ($v instanceof \BackedEnum ? $v->value : $v), $pair), $diff);
    }
}
