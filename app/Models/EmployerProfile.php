<?php

namespace App\Models;

use App\Enums\CompanySize;
use Database\Factories\EmployerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Employer-specific data for users with the employer role (1:1 with User).
 */
#[Fillable(['company_name', 'company_size', 'industry', 'website', 'open_to_beginners'])]
class EmployerProfile extends Model
{
    /** @use HasFactory<EmployerProfileFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_size' => CompanySize::class,
            'open_to_beginners' => 'boolean',
            'free_projects_used' => 'integer',
            'first_free_project_at' => 'datetime',
            'second_free_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
