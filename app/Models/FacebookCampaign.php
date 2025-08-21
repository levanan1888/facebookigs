<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookCampaign extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id','name','status','objective','start_time','stop_time','effective_status','configured_status','updated_time','ad_account_id'
    ];
    protected $casts = [
        'start_time' => 'datetime',
        'stop_time' => 'datetime',
        'updated_time' => 'datetime',
    ];

    /**
     * Quan hệ: Campaign thuộc về Ad Account
     */
    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAdAccount::class, 'ad_account_id', 'id');
    }

    /**
     * Quan hệ: Campaign có nhiều Ad Set
     */
    public function adSets(): HasMany
    {
        return $this->hasMany(FacebookAdSet::class, 'campaign_id', 'id');
    }
}



