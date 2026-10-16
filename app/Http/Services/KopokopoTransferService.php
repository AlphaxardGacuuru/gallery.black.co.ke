<?php

namespace App\Http\Services;

use App\Events\KopokopoTransferRecordedEvent;
use App\Http\Resources\KopokopoTransferResource;
use App\Models\KopokopoTransfer;
use App\Models\PhotoCompetitionWinner;
use App\Models\Referral;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Kopokopo\SDK\K2;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $kopokopoTransfer = $this->saveTransferPayload($request->input('data'));

        KopokopoTransferRecordedEvent::dispatch($kopokopoTransfer);

        return [true, "Payment Saved", $kopokopoTransfer];
    }

    /**
     * Reconcile unfinished transfers with Kopokopo and re-run settlement
     * handling for completed transfers already saved by a webhook.
     *
     * @return array{checked: int, polled: int, confirmed: int, errors: int}
     */
    public function reconcileTransfers(): array
    {
        $stats = ['checked' => 0, 'polled' => 0, 'confirmed' => 0, 'errors' => 0];
        $K2 = null;
        $accessToken = null;
        $authenticationFailed = false;

        KopokopoTransfer::query()
            ->whereNotNull('kopokopo_id')
            ->chunkById(100, function ($transfers) use (&$stats, &$K2, &$accessToken, &$authenticationFailed): void {
                foreach ($transfers as $transfer) {
                    if ($authenticationFailed) {
                        return;
                    }

                    $stats['checked']++;

                    if ($transfer->isConfirmedSuccess()) {
                        $this->normalizeTransferredStatus($transfer);
                        KopokopoTransferRecordedEvent::dispatch($transfer);
                        $stats['confirmed']++;

                        continue;
                    }

                    if ($transfer->isConfirmedFailure()) {
                        KopokopoTransferRecordedEvent::dispatch($transfer);

                        continue;
                    }

                    if ($K2 === null) {
                        $K2 = new K2(MPESATransactionService::config());
                        $tokenResponse = $K2->TokenService()->getToken();

                        if (($tokenResponse['status'] ?? null) !== 'success') {
                            Log::error('Could not authenticate with Kopokopo while reconciling transfers', $tokenResponse);
                            $stats['errors']++;
                            $authenticationFailed = true;

                            return;
                        }

                        $accessToken = $tokenResponse['data']['accessToken'];
                    }

                    $location = $transfer->kopokopo_location
                        ?: rtrim($K2->baseUrl, '/') . '/api/v2/send_money/' . rawurlencode($transfer->kopokopo_id);
                    $response = $K2->SendMoneyService()->getStatus([
                        'location' => $location,
                        'accessToken' => $accessToken,
                    ]);
                    $stats['polled']++;

                    if (($response['status'] ?? null) !== 'success' || ! is_array($response['data'] ?? null)) {
                        Log::warning('Kopokopo transfer status check failed', [
                            'kopokopo_id' => $transfer->kopokopo_id,
                            'response' => $response,
                        ]);
                        $stats['errors']++;

                        continue;
                    }

                    $statusData = $response['data'];
                    $attributes = $this->snakeCaseKeys($statusData);
                    $attributes['created_at'] ??= $transfer->kopokopo_created_at;
                    $attributes['metadata'] ??= $transfer->metadata ?? [];
                    $attributes['status'] ??= $transfer->status;
                    $attributes['transfer_batches'] ??= $transfer->transfer_batches ?? [];

                    $updatedTransfer = $this->saveTransferPayload(
                        ['id' => $statusData['id'] ?? $transfer->kopokopo_id, 'attributes' => $attributes],
                        $statusData['linkSelf'] ?? $location,
                    );

                    if ($updatedTransfer->isConfirmedSuccess()) {
                        KopokopoTransferRecordedEvent::dispatch($updatedTransfer);
                        $stats['confirmed']++;
                    } elseif ($updatedTransfer->isConfirmedFailure()) {
                        KopokopoTransferRecordedEvent::dispatch($updatedTransfer);
                    }
                }
            });

        return $stats;
    }

    /**
     * Persist the original Kopokopo status URL at initiation time so webhook
     * outages don't prevent the scheduled reconciler from checking it.
     */
    private function recordInitiatedTransfer(Request $request, string $location): void
    {
        $kopokopoId = $this->locationId($location);

        if ($kopokopoId === null) {
            Log::error('Kopokopo returned a transfer location without an ID', ['location' => $location]);

            return;
        }

        $transfer = KopokopoTransfer::firstOrNew(['kopokopo_id' => $kopokopoId]);
        $transfer->kopokopo_location = $location;

        if (! $transfer->exists) {
            $transfer->user_id = $this->id;
            $transfer->kopokopo_created_at = Carbon::now()->toIso8601String();
            $transfer->amount = $request->input('amount');
            $transfer->recipient_name = $request->input('recipientName');
            $transfer->recipient_phone = $this->normalizePhoneNumber($request->input('destinationReference'));
            $transfer->currency = 'KES';
            $transfer->status = 'Scheduled';
            $transfer->metadata = [
                'userId' => $this->id,
                'notes' => 'Transfer at ' . Carbon::now(),
            ];
        }

        $transfer->save();
    }

    /**
     * Save a common shape for webhook and polling payloads.
     *
     * @param  array{id: string, attributes: array<string, mixed>}  $payload
     */
    private function saveTransferPayload(array $payload, ?string $location = null): KopokopoTransfer
    {
        $attributes = $payload['attributes'];
        $destination = $attributes['destinations'][0] ?? [];
        $disbursement = $attributes['transfer_batches'][0]['disbursements'][0] ?? [];
        $values = [
            'user_id' => $attributes['metadata']['user_id'] ?? $attributes['metadata']['userId'] ?? null,
            'kopokopo_created_at' => $attributes['created_at'] ?? null,
            'amount' => $destination['amount'] ?? $disbursement['amount'] ?? null,
            'recipient_name' => $destination['nickname'] ?? $disbursement['nickname'] ?? null,
            'recipient_phone' => $destination['phone_number'] ?? $disbursement['phone_number'] ?? null,
            'currency' => $attributes['currency'] ?? null,
            'status' => $attributes['status'] ?? null,
            'errors' => $attributes['errors'] ?? null,
            'transfer_batches' => $attributes['transfer_batches'] ?? null,
            'metadata' => $attributes['metadata'] ?? null,
        ];

        if ($location !== null) {
            $values['kopokopo_location'] = $location;
        }

        $transfer = KopokopoTransfer::updateOrCreate(
            ['kopokopo_id' => $payload['id']],
            $values,
        );

        $this->normalizeTransferredStatus($transfer);

        return $transfer;
    }

    private function normalizeTransferredStatus(KopokopoTransfer $transfer): void
    {
        if ($transfer->hasOnlyTransferredDisbursements() && $transfer->status !== 'Transferred') {
            $transfer->status = 'Transferred';
            $transfer->save();
        }
    }

    /**
     * Convert SDK camelCase response keys into the webhook's snake_case shape.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function snakeCaseKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $result[Str::snake((string) $key)] = is_array($value)
                ? (array_is_list($value) ? array_map(fn ($item) => is_array($item) ? $this->snakeCaseKeys($item) : $item, $value) : $this->snakeCaseKeys($value))
                : $value;
        }

        return $result;
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
            return [
                false,
                $this->kopokopoErrorMessage($tokenResponse, 'Could not authenticate with Kopokopo'),
                $tokenResponse,
            ];
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
            'callbackUrl' => url('/api/kopokopo-transfers'),
            'accessToken' => $accessToken,
        ]);

        if (($response['status'] ?? null) === 'success') {
            $this->recordInitiatedTransfer($request, $response['location']);

            return [true, 'Transfer initiated, awaiting confirmation from Kopokopo', $response];
        }

        Log::error('Kopokopo transfer failed', $response);

        return [false, $this->kopokopoErrorMessage($response, 'Kopokopo Transfer Failed'), $response];
    }

    /**
     * Kopokopo's K2 SDK normalizes every failure (a thrown SDK validation
     * exception, a rejected HTTP response, or a token-request failure) into
     * `['status' => 'error', 'data' => ...]`, but `data` itself is shaped
     * differently depending on which of those it was: a plain string for a
     * client-side validation exception, `['errorMessage' => ...]` for a
     * rejected send-money request, or `['errorDescription' => ...]` for a
     * rejected token request. This picks out whichever one is actually
     * present instead of a message that can't say what Kopokopo reported.
     */
    private function kopokopoErrorMessage(array $response, string $fallback): string
    {
        $data = $response['data'] ?? null;

        if (is_string($data)) {
            return $data;
        }

        if (is_array($data)) {
            return $data['errorMessage'] ?? $data['errorDescription'] ?? $fallback;
        }

        return $fallback;
    }

    /**
     * Pay one winning position's prize to its submitter via Kopokopo. A
     * successful initiation only means Kopokopo accepted the request, not
     * that the money has arrived, so this records kopokopo_reference
     * (an in-flight marker) rather than prize_paid_at; that only gets set
     * once their webhook confirms the transfer actually completed (see
     * KopokopoTransferRecordedListener). One row = one recipient = one transfer.
     *
     * @return array{0: bool, 1: string, 2: mixed}
     */
    public function payWinner(PhotoCompetitionWinner $winner): array
    {
        if ($winner->prize_paid_at) {
            return [false, 'This prize has already been paid', null];
        }

        if ($winner->kopokopo_reference) {
            return [false, 'A payout for this winner is already in progress', null];
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
            $winner->update(['kopokopo_reference' => $this->locationId($data['location'] ?? '')]);
        }

        return [$status === true, $message, $data];
    }

    /**
     * Pay a referrer for however many complete referral-reward batches
     * they've accumulated (see Referral::eligiblePayout()). As with
     * payWinner(), a successful initiation only means Kopokopo accepted the
     * request, so this records kopokopo_reference (and the per-referral
     * amount they'll be paid) rather than marking the referrals paid
     * outright; paid_at only gets set once the webhook confirms the
     * transfer actually completed (see KopokopoTransferRecordedListener).
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
                    'amount_paid' => $perReferralAmount,
                    'kopokopo_reference' => $this->locationId($data['location'] ?? ''),
                ]);
        }

        return [$status === true, $message, $data];
    }

    /**
     * The trailing UUID segment of a Kopokopo Location URL, e.g.
     * ".../send_money/{this}", the same id that later shows up as the
     * webhook payload's top-level "data.id".
     */
    private function locationId(string $location): ?string
    {
        if ($location === '') {
            return null;
        }

        $segments = explode('/', rtrim($location, '/'));

        return end($segments) ?: null;
    }
}
