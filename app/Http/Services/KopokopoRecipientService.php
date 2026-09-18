<?php

namespace App\Http\Services;

use App\Http\Resources\KopokopoRecipientResource;
use App\Models\KopokopoRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Kopokopo\SDK\K2;
use Illuminate\Support\Facades\Log;

class KopokopoRecipientService extends Service
{
    /*
     * Get All Kopokopo Recipients
     */
    public function index(): AnonymousResourceCollection
    {
        $kopokopoRecipients = KopokopoRecipient::latest('created_at')->get();

        return KopokopoRecipientResource::collection($kopokopoRecipients);
    }

    /*
     * Get Recipients Added By a Given User
     */
    public function show(int|string $id): AnonymousResourceCollection
    {
        $kopokopoRecipients = KopokopoRecipient::where('user_id', $id)->get();

        return KopokopoRecipientResource::collection($kopokopoRecipients);
    }

    /**
     * Register the recipient with Kopokopo, then store it locally.
     *
     * @return array{0: bool, 1: string, 2: mixed}
     */
    public function store(Request $request): array
    {
        $K2 = new K2(MPESATransactionService::config());

        $tokenResponse = $K2->TokenService()->getToken();

        if (($tokenResponse['status'] ?? null) !== 'success') {
            return [false, 'Could not authenticate with Kopokopo', $tokenResponse];
        }

        $accessToken = $tokenResponse['data']['accessToken'];

        $details = $this->recipientDetails($request, $accessToken);

        $response = $K2->ExternalRecipientService()->addExternalRecipient($details);

        if (($response['status'] ?? null) !== 'success') {

            Log::error('Kopokopo recipient registration failed', $response);

            return [
                false,
                $response['data']['errorMessage'] ?? 'Kopokopo Rejected the Recipient',
                $response,
            ];
        }

        $kopokopoRecipient = new KopokopoRecipient;
        $kopokopoRecipient->user_id = $this->id;
        $kopokopoRecipient->destination_reference = $this->locationId($response['location'] ?? '');
        $kopokopoRecipient->type = $request->type;
        $kopokopoRecipient->first_name = $request->firstName;
        $kopokopoRecipient->last_name = $request->lastName;
        $kopokopoRecipient->email = $request->email;
        $kopokopoRecipient->phone_number = $request->phoneNumber
            ? $this->normalizePhoneNumber($request->phoneNumber)
            : null;
        $kopokopoRecipient->account_name = $request->accountName;
        $kopokopoRecipient->account_number = $request->accountNumber;
        $kopokopoRecipient->till_name = $request->tillName;
        $kopokopoRecipient->till_number = $request->tillNumber;
        $kopokopoRecipient->paybill_name = $request->paybillName;
        $kopokopoRecipient->paybill_number = $request->paybillNumber;
        $kopokopoRecipient->paybill_account_number = $request->paybillAccountNumber;
        $kopokopoRecipient->description = $request->description;
        $saved = $kopokopoRecipient->save();

        return [$saved, 'Recipient added', $kopokopoRecipient];
    }

    /**
     * The trailing segment of Kopokopo's Location header — its ID for the
     * newly created recipient, kept for reference (it isn't reusable in a
     * later sendMoney() call, which always needs full destination details).
     */
    private function locationId(string $location): ?string
    {
        if ($location === '') {
            return null;
        }

        $segments = explode('/', rtrim($location, '/'));

        return end($segments) ?: null;
    }

    /*
     * Get relevant details for the recipient type being added
     */
    public function recipientDetails(Request $request, string $accessToken): array
    {
        return match ($request->type) {
            'mobile_wallet' => $this->mobileWalletDetails($request, $accessToken),
            'bank_account' => $this->bankAccountDetails($request, $accessToken),
            'till' => $this->tillDetails($request, $accessToken),
            'paybill' => $this->payBillDetails($request, $accessToken),
            default => throw new \InvalidArgumentException('Invalid recipient type'),
        };
    }

    /*
     * Mobile Wallet Details
     */
    public function mobileWalletDetails(Request $request, string $accessToken): array
    {
        return [
            'type' => 'mobile_wallet',
            'firstName' => $request->firstName,
            'lastName' => $request->lastName,
            'email' => $request->email,
            'phoneNumber' => $this->normalizePhoneNumber($request->phoneNumber),
            'network' => 'Safaricom',
            'accessToken' => $accessToken,
        ];
    }

    /*
     * Bank Account Details
     */
    public function bankAccountDetails(Request $request, string $accessToken): array
    {
        return [
            'type' => 'bank_account',
            'accountName' => $request->accountName,
            'accountNumber' => $request->accountNumber,
            'bankBranchRef' => $request->bankBranchRef,
            'settlementMethod' => 'RTS',
            'accessToken' => $accessToken,
        ];
    }

    /*
     * Till Details
     */
    public function tillDetails(Request $request, string $accessToken): array
    {
        return [
            'type' => 'till',
            'tillName' => $request->tillName,
            'tillNumber' => $request->tillNumber,
            'accessToken' => $accessToken,
        ];
    }

    /*
     * Paybill Details
     */
    public function payBillDetails(Request $request, string $accessToken): array
    {
        return [
            'type' => 'paybill',
            'paybillName' => $request->paybillName,
            'paybillNumber' => $request->paybillNumber,
            'paybillAccountNumber' => $request->paybillAccountNumber,
            'accessToken' => $accessToken,
        ];
    }
}
