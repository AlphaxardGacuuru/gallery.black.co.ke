<?php

namespace App\Http\Services;

use App\Http\Resources\KopokopoTransferResource;
use App\Models\KopokopoTransfer;
use App\Models\PhotoCompetitionWinner;
use App\Models\Referral;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Kopokopo\SDK\K2;
use Illuminate\Support\Facades\Log;

class KopokopoTransferService extends Service
{
    /*
     * Show All Transfers
     */
    public function index()
    {
        $kopokopoTransfers = KopokopoTransfer::with('user')
            ->latest('created_at')
            ->paginate(20);

        return KopokopoTransferResource::collection($kopokopoTransfers);
    }

    /*
     * Store a completed transfer from Kopokopo's send_money callback
     */
    public function store(Request $request)
    {
        // Get Data
        $data = $request->input("data");
        $attributes = $data["attributes"];
        $destination = $attributes["destinations"][0] ?? [];

        $kopokopoTransfer = new KopokopoTransfer;
        $kopokopoTransfer->user_id = $attributes["metadata"]["userId"] ?? null;
        $kopokopoTransfer->kopokopo_id = $data["id"];
        $kopokopoTransfer->kopokopo_created_at = $attributes["created_at"];
        $kopokopoTransfer->amount = $destination["amount"] ?? null;
        $kopokopoTransfer->currency = $attributes["currency"] ?? null;
        $kopokopoTransfer->transfer_batches = $attributes["transfer_batches"];
        $kopokopoTransfer->metadata = $attributes["metadata"];
        $saved = $kopokopoTransfer->save();

        return [$saved, "Payment Saved", $kopokopoTransfer];
    }

    /**
     * Send money (M-Pesa mobile wallet) via Kopokopo's SendMoneyService.
     * The recipient's phone number is normalized to Kopokopo's expected
     * "254XXXXXXXXX" format before the request is built.
     */
    public function initiateTransfer(Request $request)
    {
        $amount = $request->input('amount');
        $phoneNumber = $this->normalizePhoneNumber($request->input('destinationReference'));

        $K2 = new K2(MPESATransactionService::config());

        $tokenResponse = $K2->TokenService()->getToken();

        if (($tokenResponse['status'] ?? null) !== 'success') {
            return ['error', 'Could not authenticate with Kopokopo', $tokenResponse];
        }

        $accessToken = $tokenResponse['data']['accessToken'];

        $response = $K2->SendMoneyService()->sendMoney([
            'destinations' => [
                [
                    'type' => 'mobile_wallet',
                    'nickname' => $request->input('recipientName'),
                    'phoneNumber' => $phoneNumber,
                    'network' => 'Safaricom',
                    'amount' => $amount,
                    'description' => $request->input('description', 'Black Gallery challenge prize'),
                ],
            ],
            'currency' => 'KES',
            'metadata' => [
                'userId' => $this->id,
                'notes' => 'Transfer at ' . Carbon::now(),
            ],
            'callbackUrl' => rtrim(env('APP_URL'), '/') . '/api/kopokopo-transfers',
            'accessToken' => $accessToken,
        ]);

        if (($response['status'] ?? null) === 'success') {
            return [true, 'Transfer Initiated', $response];
        }

        Log::error('Kopokopo transfer failed', $response);

        return [false, 'Kopokopo Transfer Failed', $response];
    }

    /**
     * Pay one winning position's prize to its submitter via Kopokopo, then
     * mark that specific position as paid on success. One row = one
     * recipient = one transfer.
     *
     * @return array{0: bool, 1: string, 2: mixed}
     */
    public function payWinner(PhotoCompetitionWinner $winner): array
    {
        if ($winner->prize_paid_at) {
            return [false, 'This prize has already been paid', null];
        }

        $user = $winner->user;

        if (! $user) {
            return [false, 'This winner has no associated user', null];
        }

        if (! $user->phone) {
            return [false, $user->name . ' has no M-Pesa phone number on file', null];
        }

        $request = Request::create('/', 'POST', [
            'destinationReference' => $user->phone,
            'amount' => $winner->prize_amount,
            'recipientName' => $user->name,
            'description' => 'Black Gallery weekly challenge prize (#' . $winner->position . ')',
        ]);

        [$status, $message, $data] = $this->initiateTransfer($request);

        if ($status === true) {
            $winner->update(['prize_paid_at' => now()]);
        }

        return [$status === true, $message, $data];
    }

    /**
     * Pay a referrer for however many complete referral-reward batches
     * they've accumulated (see Referral::eligiblePayout()), in a single
     * transfer, then mark exactly those referrals paid with their even
     * split of the reward on success.
     *
     * @param  array<int, string>  $referralIds  The specific unpaid referrals this payout covers.
     * @return array{0: bool, 1: string, 2: mixed}
     */
    public function payReferrer(
        User $referrer,
        float $totalAmount,
        array $referralIds,
        float $perReferralAmount
    ): array {
        if ($referralIds === []) {
            return [false, $referrer->name . ' hasn\'t reached the referral threshold yet', null];
        }

        if (! $referrer->phone) {
            return [false, $referrer->name . ' has no M-Pesa phone number on file', null];
        }

        $request = Request::create('/', 'POST', [
            'destinationReference' => $referrer->phone,
            'amount' => $totalAmount,
            'recipientName' => $referrer->name,
            'description' => 'Black Gallery referral reward',
        ]);

        [$status, $message, $data] = $this->initiateTransfer($request);

        if ($status === true) {
            Referral::query()
                ->whereIn('id', $referralIds)
                ->update([
                    'paid_at' => now(),
                    'amount_paid' => $perReferralAmount,
                ]);
        }

        return [$status === true, $message, $data];
    }
}
