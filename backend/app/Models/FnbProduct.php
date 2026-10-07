<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['category', 'name', 'price', 'hpp', 'stock', 'is_available'])]
class FnbProduct extends Model
{
    use HasUuidKey;

    protected function casts(): array
    {
        return ['is_available' => 'boolean'];
    }

    /** `stock` null = tidak dilacak (API.md §8), jadi selalu tersedia. */
    public function isOrderable(): bool
    {
        return $this->is_available && ($this->stock === null || $this->stock > 0);
    }
}
