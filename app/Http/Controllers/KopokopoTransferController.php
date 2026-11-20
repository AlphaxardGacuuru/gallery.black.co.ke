<?php

namespace App\Http\Controllers;

use App\Events\KopokopoTransferInitiated;
use App\Http\Services\KopokopoTransferService;
use App\Http\Services\Service;
use App\Models\KopokopoTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class KopokopoTransferController extends Controller
{
    public function __construct(protected KopokopoTransferService $service)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'type' => ['nullable', Rule::in(KopokopoTransfer::TYPES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->service
            ->index($request->input('type'), $request->integer('per_page', 20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        [$saved, $message, $kopokopoTransfer] = $this->service->store($request);

        return response([
            "status" => $saved,
            "message" => $message,
            "data" => $kopokopoTransfer,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): void
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, KopokopoTransfer $kopokopoTransfer): void
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(KopokopoTransfer $kopokopoTransfer): void
    {
        //
    }

    /*
     * Initiate Transfer (send money to a phone number via M-Pesa)
     */
    public function initiateTransfer(Request $request): Response
    {
        $this->validate($request, [
            "destinationReference" => "required|string",
            "amount" => "required|numeric|min:1",
            "recipientName" => "nullable|string|max:255",
            "description" => "nullable|string|max:255",
        ]);

        [$status, $message, $data] = $this->service->initiateTransfer($request);

        $phone = Service::normalizePhoneNumber($request->string('destinationReference')->toString());

        KopokopoTransferInitiated::dispatchIf(
            $status,
            $phone,
            (float) $request->input('amount'),
            $request->input('recipientName'),
            $request->input('description'),
        );

        return response([
            "status" => $status,
            "message" => $message,
            "data" => $data,
        ], 200);
    }
}
