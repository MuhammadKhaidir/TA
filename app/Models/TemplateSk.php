<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateSk extends Model
{
    protected $fillable = ['nama', 'isi', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /** Hanya satu template yang aktif pada satu waktu. */
    protected static function booted(): void
    {
        static::saved(function (self $template) {
            if ($template->aktif) {
                static::query()->where('id', '!=', $template->id)->update(['aktif' => false]);
            }
        });
    }

    public static function aktif(): ?self
    {
        return static::query()->where('aktif', true)->latest('id')->first();
    }
}
