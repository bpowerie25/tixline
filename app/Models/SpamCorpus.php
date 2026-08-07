<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpamCorpus extends Model
{
    protected $table = 'spam_corpus';

    protected $fillable = ['type', 'value', 'hits'];
}
