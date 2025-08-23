<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookAdInsight extends Model
{
    protected $fillable = [
        'ad_id', 'date', 'spend', 'reach', 'impressions', 'clicks',
        'unique_clicks', 'unique_ctr', 'unique_link_clicks_ctr', 'unique_impressions',
        'ctr', 'cpc', 'cpm', 'frequency',
        'conversions', 'conversion_values', 'cost_per_conversion',
        'purchase_roas', 'outbound_clicks', 'unique_outbound_clicks',
        'inline_link_clicks', 'unique_inline_link_clicks', 'website_clicks',
        'actions', 'action_values', 'cost_per_action_type', 'cost_per_unique_action_type', 'breakdowns',
        // Video metrics fields - đầy đủ theo docs Facebook
        'video_plays', 'video_plays_at_25_percent', 'video_plays_at_50_percent', 'video_plays_at_75_percent', 'video_plays_at_100_percent',
        'video_avg_time_watched_actions', 'video_p25_watched_actions', 'video_p50_watched_actions', 'video_p75_watched_actions', 'video_p95_watched_actions', 'video_p100_watched_actions',
        'thruplays', 'video_avg_time_watched', 'video_view_time', 'video_30_sec_watched',
        'post_video_views', 'post_video_views_unique', 'post_video_avg_time_watched', 'post_video_complete_views_30s', 'post_video_views_10s',
        'post_video_retention_graph', 'post_video_views_paid', 'post_video_views_organic'
    ];

    protected $casts = [
        'date' => 'date',
        'actions' => 'array',
        'action_values' => 'array',
        'cost_per_action_type' => 'array',
        'cost_per_unique_action_type' => 'array',
        'breakdowns' => 'array',
        'post_video_retention_graph' => 'array',
    ];

    /**
     * Relationship với Ad
     */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(FacebookAd::class, 'ad_id', 'id');
    }

    /**
     * Scope: Lọc theo ngày
     */
    public function scopeByDate($query, $date)
    {
        return $query->where('date', $date);
    }

    /**
     * Scope: Lọc theo khoảng thời gian
     */
    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    /**
     * Scope: Có dữ liệu insights
     */
    public function scopeHasData($query)
    {
        return $query->where(function ($q) {
            $q->where('impressions', '>', 0)
              ->orWhere('reach', '>', 0)
              ->orWhere('clicks', '>', 0)
              ->orWhere('spend', '>', 0);
        });
    }

    /**
     * Scope: Có conversions
     */
    public function scopeHasConversions($query)
    {
        return $query->where('conversions', '>', 0);
    }
}
