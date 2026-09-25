<?php

namespace App\Models;

use Database\Factories\DocumentCounterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['scope', 'year', 'value'])]
class DocumentCounter extends Model
{
    /** @use HasFactory<DocumentCounterFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'value' => 'integer',
        ];
    }
}
