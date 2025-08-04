<?php
namespace App\Models;

use App\Models\Event;
use App\Models\TeamActionType;
use Illuminate\Database\Eloquent\Model;

class EventTeamAction extends Model
{
    protected $fillable = [
        'event_id',
        'team_action_type_id',
        'value_home',
        'value_away',
        'sort_order',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function teamActionType()
    {
        return $this->belongsTo(TeamActionType::class);
    }
}
