<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookAd extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id', 'name', 'status', 'effective_status', 'adset_id', 'campaign_id', 'account_id', 
        'creative', 'created_time', 'updated_time',
        // Creative link fields
        'creative_link_url', 'creative_link_message', 'creative_link_name',
        'creative_image_hash', 'creative_call_to_action_type', 'creative_page_welcome_message',
        // Post fields
        'post_id', 'page_id', 'post_message', 'post_type', 'post_status_type',
        'post_attachments', 'post_permalink_url', 'post_created_time', 'post_updated_time',
        // Post insights fields
        'post_impressions', 'post_reach', 'post_clicks', 'post_unique_clicks',
        'post_likes', 'post_shares', 'post_comments', 'post_reactions',
        'post_saves', 'post_hides', 'post_hide_all_clicks', 'post_unlikes',
        'post_negative_feedback', 'post_video_views', 'post_video_view_time',
        'post_video_avg_time_watched', 'post_video_p25_watched_actions',
        'post_video_p50_watched_actions', 'post_video_p75_watched_actions',
        'post_video_p95_watched_actions', 'post_video_p100_watched_actions',
        'post_engagement_rate', 'post_ctr', 'post_cpm', 'post_cpc',
        'post_spend', 'post_frequency', 'post_actions', 'post_action_values',
        'post_cost_per_action_type', 'post_cost_per_unique_action_type', 'post_breakdowns',
        // Ad insights fields
        'ad_spend', 'ad_reach', 'ad_impressions', 'ad_clicks', 'ad_ctr',
        'ad_cpc', 'ad_cpm', 'ad_frequency', 'ad_unique_clicks', 'ad_actions',
        'ad_action_values', 'ad_purchase_roas',
        // Metadata fields
        'post_metadata', 'insights_metadata', 'last_insights_sync'
    ];
    protected $casts = [
        'creative' => 'array',
        'created_time' => 'datetime',
        'updated_time' => 'datetime',
        'post_attachments' => 'array',
        'post_created_time' => 'datetime',
        'post_updated_time' => 'datetime',
        'post_actions' => 'array',
        'post_action_values' => 'array',
        'post_cost_per_action_type' => 'array',
        'post_cost_per_unique_action_type' => 'array',
        'post_breakdowns' => 'array',
        'ad_actions' => 'array',
        'ad_action_values' => 'array',
        'post_metadata' => 'array',
        'insights_metadata' => 'array',
        'last_insights_sync' => 'datetime',
    ];

    /**
     * Relationship với Ad Account
     */
    public function adAccount()
    {
        return $this->belongsTo(FacebookAdAccount::class, 'account_id', 'id');
    }

    /**
     * Relationship với Ad Set
     */
    public function adSet()
    {
        return $this->belongsTo(FacebookAdSet::class, 'adset_id', 'id');
    }

    /**
     * Relationship với Campaign
     */
    public function campaign()
    {
        return $this->belongsTo(FacebookCampaign::class, 'campaign_id', 'id');
    }

    // Scopes
    /**
     * Scope: Lấy ads có post
     */
    public function scopeWithPost($query)
    {
        return $query->whereNotNull('post_id');
    }

    /**
     * Scope: Lấy ads có insights
     */
    public function scopeWithInsights($query)
    {
        return $query->whereNotNull('last_insights_sync');
    }

    /**
     * Scope: Lọc theo loại post
     */
    public function scopeByPostType($query, $type)
    {
        return $query->where('post_type', $type);
    }

    /**
     * Scope: Lọc theo page
     */
    public function scopeByPage($query, $pageId)
    {
        return $query->where('page_id', $pageId);
    }
}



