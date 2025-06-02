<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ParseTableContent extends Model
{
    use HasFactory;

    protected $table = 'parse_table_contents';

    protected $fillable = [
        'table_id',
        'field1', 'field2', 'field3', 'field4', 'field5',
        'field6', 'field7', 'field8', 'field9', 'field10',
        'field11', 'field12', 'field13', 'field14', 'field15',
        'field16', 'field17', 'field18', 'field19', 'field20'
    ];

    public function table()
    {
        return $this->belongsTo(ParseTable::class, 'table_id');
    }
}
