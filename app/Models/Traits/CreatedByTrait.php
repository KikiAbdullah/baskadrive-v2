<?php

namespace App\Models\Traits;

trait CreatedByTrait
{
    public static function bootCreatedByTrait()
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });
    }
}
