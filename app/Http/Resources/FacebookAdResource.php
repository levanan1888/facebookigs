<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacebookAdResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'effective_status' => $this->effective_status,
            'account_id' => $this->account_id,
            'campaign_id' => $this->campaign_id,
            'adset_id' => $this->adset_id,
            
            // Post information
            'post_id' => $this->post_id,
            'page_id' => $this->page_id,
            'post_message' => $this->post_message,
            'post_type' => $this->post_type,
            'post_status_type' => $this->post_status_type,
            'post_permalink_url' => $this->post_permalink_url,
            'post_created_time' => $this->post_created_time?->toISOString(),
            
            // Ad insights
            'ad_spend' => (float) $this->ad_spend,
            'ad_reach' => (int) $this->ad_reach,
            'ad_impressions' => (int) $this->ad_impressions,
            'ad_clicks' => (int) $this->ad_clicks,
            'ad_ctr' => (float) $this->ad_ctr,
            'ad_cpc' => (float) $this->ad_cpc,
            'ad_cpm' => (float) $this->ad_cpm,
            'ad_frequency' => (float) $this->ad_frequency,
            
            // Post insights
            'post_spend' => (float) $this->post_spend,
            'post_reach' => (int) $this->post_reach,
            'post_impressions' => (int) $this->post_impressions,
            'post_clicks' => (int) $this->post_clicks,
            'post_likes' => (int) $this->post_likes,
            'post_shares' => (int) $this->post_shares,
            'post_comments' => (int) $this->post_comments,
            'post_engagement_rate' => (float) $this->post_engagement_rate,
            
            // Timestamps
            'created_time' => $this->created_time?->toISOString(),
            'updated_time' => $this->updated_time?->toISOString(),
            'last_insights_sync' => $this->last_insights_sync?->toISOString(),
            
            // Relationships
            'ad_account' => new FacebookAdAccountResource($this->whenLoaded('adAccount')),
            'campaign' => new FacebookCampaignResource($this->whenLoaded('campaign')),
            'ad_set' => new FacebookAdSetResource($this->whenLoaded('adSet')),
        ];
    }
}
