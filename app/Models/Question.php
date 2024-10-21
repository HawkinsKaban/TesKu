<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = ['test_id', 'question_text', 'question_type', 'options', 'points'];

    protected $casts = [
        'options' => 'array',
    ];

    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function responses()
    {
        return $this->hasMany(Response::class);
    }

    // Tambahkan relasi options
    public function options()
    {
        return $this->hasMany(Option::class);
    }
}
