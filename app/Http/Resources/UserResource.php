<?php

namespace App\Http\Resources;

use App\Models\User;
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
            "verificationReason" => $this->when(
                (bool) $request->user()?->isAdmin(),
                $this->verification_reason?->label()
            ),
            "flaggedAt" => $this->flagged_at,
            "flagRefutedAt" => $this->flag_refuted_at,
            "flaggedUntilPhone" => $this->isFlaggedUntilPhone(),
            // Shown to admins and to the flagged user themselves.
            "flagReason" => $this->when(
                (bool) $request->user()?->isAdmin() || $request->user()?->is($this->resource),
                $this->flag_reason
            ),
            "flaggedUntilPhoneVerified" => $this->isFlaggedUntilPhoneVerified(),
            // What confirming a changed number costs, shown before changing it.
            "phoneVerificationFee" => $this->when(
                (bool) $request->user()?->is($this->resource),
                fn () => User::phoneVerificationFee()
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
