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
    ];

    public function actions()
    {
        return $this->hasMany(EventAction::class);
    }
}
