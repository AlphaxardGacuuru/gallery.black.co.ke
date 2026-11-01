<?php

namespace App\Http\Controllers;

use App\Http\Services\TemporaryUploadService;
use App\Models\Photo;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class FilePondController extends Controller
{
    public function __construct(
        protected TemporaryUploadService $temporaryUploadService,
    ) {
        //
    }

    /*
     * Handle Material Upload */
    public function storeMaterial(Request $request): string
    {
        $this->validate($request, [
            'filepond-material' => 'required|file',
        ]);

        // Store material
        $material = $request->file('filepond-material')->store('public/materials');

        $material = substr($material, 7);

        return $material;
    }

    /*
     * Handle Material Delete */
    public function destoryMaterial(int|string $id): Response
    {
        Storage::delete('public/materials/' . $id);

        return response("Material deleted", 200);
    }

    /*
     * Discussion Forum
     */

    /*
     * Handle Attachment Upload */
    public function storeAttachment(Request $request): string
    {
        $this->validate($request, [
            'filepond-attachment' => 'required|file',
        ]);

        // Store Attachment
        $attachment = $request->file('filepond-attachment')->store('public/attachments');

        $attachment = substr($attachment, 7);

        return $attachment;
    }

    /*
     * Handle Attachment Delete */
    public function destoryAttachment(int|string $id): Response
    {
        Storage::delete('public/attachments/' . $id);

        return response("Attachment deleted", 200);
    }

    /*
     * Store Submissions */
    public function storeSubmission(Request $request, int|string $sessionId, int|string $unitId, int|string $week, int|string $userId, string $type): Response
    {
        $this->validate($request, [
            "filepond-file" => "required|file",
        ]);

        $attachment = $request
            ->file('filepond-file')
            ->store('public/submissions');

        $attachment = substr($attachment, 7);

        $submissionQuery = Submission::where("academic_session_id", $sessionId)
            ->where("unit_id", $unitId)
            ->where("week", $week)
            ->where("user_id", $userId)
            ->where("type", $type);

        $submissionDoesntExist = $submissionQuery->doesntExist();

        if ($submissionDoesntExist) {
            // Add New Submission
            $submission = new Submission;
            $submission->academic_session_id = $sessionId;
            $submission->unit_id = $unitId;
            $submission->week = $week;
            $submission->user_id = $userId;
            $submission->type = $type;
            $submission->attachment = $attachment;
            $submission->save();

            $message = $type . " saved";
        } else {
            $submission = $submissionQuery->first();

            // Get old attachment and delete it
            $oldAttachment = substr($submission->attachment, 8);

            Storage::disk("public")->delete($oldAttachment);

            // Update Submission
            $submission->attachment = $attachment;
            $submission->save();

            $message = $type . " updated";
        }

        return response($message, 200);
    }

    /*
     * Support Ticket Attachments
     */

    public function storeSupportTicketAttachment(Request $request): Response
    {
        $this->validate($request, [
            'filepond-support-ticket-attachments' => 'required|file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx',
        ]);

        $temporaryUpload = $this->temporaryUploadService->store(
            $request->file('filepond-support-ticket-attachments'),
            $request->user(),
            'temporary-uploads/support-tickets'
        );

        // FilePond expects a plain server id string.
        return response((string) $temporaryUpload->id, 200);
    }

    public function destroySupportTicketAttachment(Request $request, int|string $id): Response
    {
        if (! $this->temporaryUploadService->destroy($request->user(), $id)) {
            return response('Attachment already removed', 200);
        }

        return response('Attachment deleted', 200);
    }

    /*
     * Photo Competition Submissions
     */

    public function storePhoto(Request $request): Response
    {
        $this->validate($request, [
            'filepond-photo' => 'required|image|max:' . Photo::MAX_UPLOAD_KB,
        ]);

        $temporaryUpload = $this->temporaryUploadService->store(
            $request->file('filepond-photo'),
            $request->user(),
            'temporary-uploads/photos'
        );

        return response((string) $temporaryUpload->id, 200);
    }

    public function destroyPhoto(Request $request, int|string $id): Response
    {
        if (! $this->temporaryUploadService->destroy($request->user(), $id)) {
            return response('Upload already removed', 200);
        }

        return response('Upload deleted', 200);
    }
}
