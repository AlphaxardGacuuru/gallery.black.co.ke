<?php

namespace App\Http\Services;

use App\Http\Resources\KopokopoTransferResource;
use App\Models\KopokopoTransfer;
use App\Models\PhotoCompetition;
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
    public function store($request)
    {
        // Get Data
        $data = $request->input("data");
        $attributes = $data["attributes"];

        $kopokopoTransfer = new KopokopoTransfer;
        $kopokopoTransfer->user_id = $attributes["metadata"]["userId"] ?? null;
        $kopokopoTransfer->kopokopo_id = $data["id"];
        $kopokopoTransfer->kopokopo_created_at = $attributes["created_at"];
        $kopokopoTransfer->amount = $attributes["amount"]["value"];
        $kopokopoTransfer->currency = $attributes["amount"]["currency"];
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
    public function initiateTransfer($request)
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
                    // 'amount' => $amount,
                    'amount' => 20,
                    'description' => $request->input('description', 'Black Gallery challenge prize'),
                ],
            ],
            'currency' => 'KES',
            'metadata' => [
                'userId' => $this->id,
                'notes' => 'Transfer at '.Carbon::now(),
            ],
            'callbackUrl' => rtrim(env('APP_URL'), '/').'/api/kopokopo-transfers',
            'accessToken' => $accessToken,
        ]);

        if (($response['status'] ?? null) === 'success') {
            return [true, 'Transfer Initiated', $response];
        }

        Log::error('Kopokopo transfer failed', $response);

        return [false, 'Kopokopo transfer failed', $response];
    }

    /**
     * Pay a competition's prize to its winning photo's submitter via
     * Kopokopo, then mark the competition as paid on success.
     *
     * @return array{0: bool, 1: string, 2: mixed}
     */
    public function payWinner(PhotoCompetition $competition): array
    {
        if ($competition->prize_paid_at) {
            return [false, 'This week\'s prize has already been paid', null];
        }

        $winner = $competition->winnerPhoto?->user;

        if (! $winner) {
            return [false, 'This competition has no winner yet', null];
        }

        if (! $winner->phone) {
            return [false, $winner->name.' has no M-Pesa phone number on file', null];
        }

        $request = Request::create('/', 'POST', [
            'destinationReference' => $winner->phone,
            'amount' => $competition->prize_amount,
            'recipientName' => $winner->name,
            'description' => 'Black Gallery weekly challenge prize',
        ]);

        [$status, $message, $data] = $this->initiateTransfer($request);

        if ($status === true) {
            $competition->update(['prize_paid_at' => now()]);
        }

        return [$status === true, $message, $data];
    }
}
