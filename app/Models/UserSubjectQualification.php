<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubjectQualification extends Model
{
    use HasFactory;

    // 1. MASS ASSIGNMENT PROTECTION
    // This tells Laravel exactly which columns are safe to save via forms
    protected $fillable = [
        'user_id',
        'subject_name',
        'proof_file_path',
        'status',
        'rejection_reason',
    ];

    // 2. THE RELATIONSHIP
    // This connects the Qualification back to the Student who submitted it
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
