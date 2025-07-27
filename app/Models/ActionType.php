<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionType extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'color',
        'group',
        'short_name',
        'short_name_table',
        'points',
    ];

    public function actions()
    {
        return $this->hasMany(EventAction::class);
    }
}
