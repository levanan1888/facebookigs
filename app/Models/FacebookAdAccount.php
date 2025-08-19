<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookAdAccount extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','account_id','name','account_status','business_id'];

    /**
     * Quan hệ: Ad Account thuộc về Business Manager
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(FacebookBusiness::class, 'business_id', 'id');
    }

    /**
     * Quan hệ: Ad Account có nhiều Campaign
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(FacebookCampaign::class, 'ad_account_id', 'id');
    }

    /**
     * Quan hệ: Account có nhiều insights ở cấp account
     */
    public function insights(): HasMany
    {
        return $this->hasMany(FacebookInsight::class, 'ref_id', 'id')
            ->where('level', 'account');
    }
}



