<?php
namespace App\Models;

use App\Models\EventTeamAction;
use Illuminate\Database\Eloquent\Model;

class TeamActionType extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'short_name',
    ];

    public function teamActions()
    {
        return $this->hasMany(EventTeamAction::class);
    }
}
