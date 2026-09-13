<?php

namespace App\Http\Services;

use App\Http\Resources\KopokopoTransferResource;
use App\Models\KopokopoTransfer;
use Carbon\Carbon;
use Kopokopo\SDK\K2;

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
                    'amount' => $amount,
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
            return [true, 'Transfer initiated', $response];
        }

        return [false, 'Kopokopo transfer failed', $response];
    }

    /**
     * Kopokopo/M-Pesa expects "254XXXXXXXXX" (no "+", no leading 0).
     */
    private function normalizePhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '254'.substr($digits, 1);
        }

        return $digits;
    }
}
