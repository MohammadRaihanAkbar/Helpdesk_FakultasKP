<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabel 4: category_audiences — jenis pelapor yang boleh melihat sebuah Kelompok Layanan.
 *
 * #[WithoutTimestamps]: migration-nya tidak punya created_at/updated_at sama sekali,
 * jadi Eloquent jangan mencoba mengisinya (kalau tidak, insert akan error "unknown column").
 */
#[Fillable(['category_id', 'user_type'])]
#[WithoutTimestamps]
class CategoryAudience extends Model
{
    protected function casts(): array
    {
        return [
            'user_type' => UserType::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
