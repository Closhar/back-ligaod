<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAction extends Model
{
    protected $fillable = [
        'event_id',
        'club_id',
        'person_id',
        'player_name',
        'action_type_id',
        'minute',
        'is_overtime',
        'value',
        'related_action_id',
        'extra_info',
        'sort_order',
        'score',
    ];

    protected $appends = [
        'full_name'
    ];

    public function getFullNameAttribute()
    {
        return $this->person ? $this->person->full_name : $this->player_name;
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function actionType()
    {
        return $this->belongsTo(ActionType::class);
    }

    public function relatedAction()
    {
        return $this->belongsTo(EventAction::class, 'related_action_id');
    }

    public function relatedEvents()
    {
        return $this->hasMany(EventAction::class, 'related_action_id');
    }
}
