<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MessageReaction extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'emoji'];
}
