<?php

namespace App\Models\Contracts;

use Filament\Models\Contracts\FilamentUser;

// Extends Filament's FilamentUser when Filament is installed, so User keeps working without it.
if (interface_exists(FilamentUser::class)) {
    interface PanelUser extends FilamentUser {}
} else {
    interface PanelUser {}
}
