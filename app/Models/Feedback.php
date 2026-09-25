<?php

namespace App\Models;

class Feedback extends TenantModel
{
    protected $table = 'feedbacks';

    protected $fillable = ['username', 'feedback'];
}
