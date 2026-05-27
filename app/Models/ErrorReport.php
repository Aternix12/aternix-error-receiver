<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorReport extends Model
{
    protected $fillable = [
        'project',
        'app_version',
        'platform',
        'hostname',
        'report_type',
        'summary',
        'user_note',
        'frontend_report',
        'log_tail',
        'client_ip',
    ];
}
