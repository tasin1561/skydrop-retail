<?php

namespace App\Models\Fulfilment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

/** A customer upload kept on the private disk; served through a signed URL (uploads.show). */
class Upload extends Model
{
    protected $table = 'uploads';

    protected $guarded = [];

    public function signedUrl(int $minutes = 60): string
    {
        return URL::temporarySignedRoute('uploads.show', now()->addMinutes($minutes), ['upload' => $this->id]);
    }

    public function toApi(): array
    {
        return ['id' => $this->id, 'url' => $this->signedUrl(), 'mime' => $this->mime, 'size' => (int) $this->size];
    }
}
