<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityBanner extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['path'];
    protected function casts(): array { return ['removed_at' => 'datetime', 'position_x' => 'float', 'position_y' => 'float', 'zoom' => 'float']; }
    public function community() { return $this->belongsTo(Community::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function reports() { return $this->morphMany(Report::class, 'reportable'); }
}
