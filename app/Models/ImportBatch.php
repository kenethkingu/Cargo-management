<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = ['file_name','total_rows','processed_rows','status','errors','
    progress','error_count','validation_errors','message','started_at'];
}