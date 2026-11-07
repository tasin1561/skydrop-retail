<?php

namespace App\Models\Contracts;

// Extends Filament's FilamentUser when Filament is installed, so User keeps working without it.
if (interface_exists(\Filament\Models\Contracts\FilamentUser::class)) {
    interface PanelUser extends \Filament\Models\Contracts\FilamentUser {}
} else {
    interface PanelUser {}
}
