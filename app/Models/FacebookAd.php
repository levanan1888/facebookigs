<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookAd extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id','name','status','effective_status','adset_id','campaign_id','account_id','creative','created_time','updated_time'
    ];
    protected $casts = [
        'creative' => 'array',
        'created_time' => 'datetime',
        'updated_time' => 'datetime',
    ];

    /**
     * Quan hệ: Ad thuộc về Ad Set
     */
    public function adSet(): BelongsTo
    {
        return $this->belongsTo(FacebookAdSet::class, 'adset_id', 'id');
    }

    /**
     * Quan hệ: Ad thuộc về Campaign
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FacebookCampaign::class, 'campaign_id', 'id');
    }

    /**
     * Quan hệ: Ad thuộc về Ad Account (thông qua account_id)
     */
    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(FacebookAdAccount::class, 'account_id', 'id');
    }
}



