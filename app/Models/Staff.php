<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'personal_email',
        'staff_number',
        'job_title',
        'department',
        'supervisor_name',
        'work_station',
        'employment_date',
        'employment_type',
    ];

    protected function casts(): array
    {
        return [
            'employment_date' => 'date',
        ];
    }

    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
