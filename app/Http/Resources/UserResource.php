<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $userRoles = [
            "userId" => $this->id,
            "roles" => $this->roles,
        ];

        $userRoleNames = [
            "userId" => $this->id,
            "roleNames" => $this->getRoleNames(),
        ];

        return [
            "id" => $this->id,
            "name" => $this->name,
            "email" => $this->email,
            "phone" => $this->phone,
            "gender" => $this->gender,
            "avatar" => $this->avatar,
            "verified" => (bool) $this->verified,
            "flaggedAt" => $this->flagged_at,
            "flagRefutedAt" => $this->flag_refuted_at,
            // An admin-only note, never shown to the flagged user.
            "flagReason" => $this->when(
                (bool) $request->user()?->isAdmin(),
                $this->flag_reason
            ),
            "phoneRequestsCount" => $this->when(
                (bool) $request->user()?->isAdmin(),
                (int) $this->phone_requests_count
            ),
            "phoneRequestedAt" => $this->when(
                (bool) $request->user()?->isAdmin(),
                $this->phone_requested_at
            ),
            "isAdmin" => $this->isAdmin(),
            "accountType" => $this->account_type,
            "emailVerifiedAt" => $this->email_verified_at,
            "settings" => $this->settings,
            "roles" => [$userRoles],
            "roleNames" => [$userRoleNames],
            "permissions" => $this->getAllPermissions()->pluck('name')->unique()->values(),
            "twoFactorEnabled" => $this->hasTwoFactorEnabled(),
            "referralsCount" => $this->referrals_made_count ?? $this->referralsMade()->count(),
            "referredBy" => $this->whenLoaded('referral', fn() => $this->referral?->referrer
                ? ["id" => $this->referral->referrer->id, "name" => $this->referral->referrer->name]
                : null),
            "pushSubscriptionsCount" => $this->push_subscriptions_count ?? $this->pushSubscriptions()->count(),
            "can_view_ai_usage" => Gate::forUser($this->resource)
                ->allows("viewAiUsage"),
            "createdAt" => $this->created_at,
        ];
    }
}
