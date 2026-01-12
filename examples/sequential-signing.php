<?php

/**
 * Sequential Digital Signature Workflow Example
 *
 * This demonstrates a real-world signing flow:
 * 1. User 1 signs the original document and downloads
 * 2. User 2 receives the signed doc, adds their signature, downloads
 * 3. User 3 receives the doc with 2 signatures, adds theirs, downloads
 *
 * Each signature is preserved using PDF incremental updates.
 */

use PdfLib\Laravel\Facades\PDF;

// ============================================================================
// Example 1: Using Laravel Facade (in a Controller)
// ============================================================================

class DocumentSigningController extends Controller
{
    /**
     * Flow 1: User 1 signs the original document
     */
    public function user1Signs(Request $request)
    {
        // Load the original unsigned document
        $originalPdf = storage_path('documents/contract.pdf');

        // User 1 signs with their certificate
        $signedByUser1 = PDF::sign(
            file_get_contents($originalPdf),
            storage_path('certificates/user1.p12'),
            $request->input('password'),
            [
                'reason' => 'Approved by Department Head',
                'location' => 'New York Office',
                'contact' => 'user1@company.com',
            ]
        );

        // Store for User 2 to pick up (optional)
        PDF::store($signedByUser1, 'documents/contract_signed_user1.pdf');

        // Download to User 1
        return PDF::download($signedByUser1, 'contract_signed_by_me.pdf');
    }

    /**
     * Flow 2: User 2 signs the document that User 1 already signed
     */
    public function user2Signs(Request $request)
    {
        // Load document that User 1 signed
        $signedByUser1 = PDF::parseFromDisk('documents/contract_signed_user1.pdf');
        $content = Storage::get('documents/contract_signed_user1.pdf');

        // User 2 adds their signature (preserves User 1's signature)
        $signedByUser1And2 = PDF::sign(
            $content,
            storage_path('certificates/user2.p12'),
            $request->input('password'),
            [
                'reason' => 'Reviewed and Approved',
                'location' => 'Chicago Office',
                'contact' => 'user2@company.com',
            ]
        );

        // Store for User 3 to pick up
        PDF::store($signedByUser1And2, 'documents/contract_signed_user1_user2.pdf');

        // Download to User 2
        return PDF::download($signedByUser1And2, 'contract_signed_by_me.pdf');
    }

    /**
     * Flow 3: User 3 signs the document that User 1 and User 2 already signed
     */
    public function user3Signs(Request $request)
    {
        // Load document that User 1 and User 2 signed
        $content = Storage::get('documents/contract_signed_user1_user2.pdf');

        // User 3 adds their signature (preserves User 1 and User 2's signatures)
        $fullySignedPdf = PDF::sign(
            $content,
            storage_path('certificates/user3.p12'),
            $request->input('password'),
            [
                'reason' => 'Final Approval - CEO',
                'location' => 'Los Angeles HQ',
                'contact' => 'user3@company.com',
            ]
        );

        // Store the fully signed document
        PDF::store($fullySignedPdf, 'documents/contract_fully_signed.pdf');

        // Download to User 3
        return PDF::download($fullySignedPdf, 'contract_fully_signed.pdf');
    }
}


// ============================================================================
// Example 2: Using Signer directly (more control)
// ============================================================================

use PdfLib\Security\Signature\Signer;

class AdvancedSigningController extends Controller
{
    /**
     * User 1 signs with visible signature
     */
    public function user1SignsWithVisibleSignature(Request $request)
    {
        $signer = PDF::signer();
        $signer->loadFile(storage_path('documents/contract.pdf'));
        $signer->loadCertificate(
            storage_path('certificates/user1.p12'),
            $request->input('password')
        );

        $signer->setReason('Department Head Approval');
        $signer->setLocation('New York');
        $signer->setContactInfo('user1@company.com');

        // Add visible signature on page 1
        $signer->setSignatureAppearance([
            'page' => 1,
            'x' => 50,
            'y' => 100,
            'width' => 200,
            'height' => 50,
        ]);

        $signedContent = $signer->sign();

        // Store for next user
        file_put_contents(
            storage_path('documents/contract_signed_user1.pdf'),
            $signedContent
        );

        return PDF::download($signedContent, 'contract_signed.pdf');
    }

    /**
     * User 2 signs - signature appears in different position
     */
    public function user2SignsWithVisibleSignature(Request $request)
    {
        // Load the PDF that User 1 already signed
        $signer = PDF::signer();
        $signer->loadFile(storage_path('documents/contract_signed_user1.pdf'));
        $signer->loadCertificate(
            storage_path('certificates/user2.p12'),
            $request->input('password')
        );

        $signer->setReason('Legal Review Complete');
        $signer->setLocation('Chicago');

        // User 2's signature appears in a different position
        $signer->setSignatureAppearance([
            'page' => 1,
            'x' => 300,  // Different X position
            'y' => 100,
            'width' => 200,
            'height' => 50,
        ]);

        $signedContent = $signer->sign();

        file_put_contents(
            storage_path('documents/contract_signed_user1_user2.pdf'),
            $signedContent
        );

        return PDF::download($signedContent, 'contract_signed.pdf');
    }

    /**
     * User 3 final signature
     */
    public function user3FinalSignature(Request $request)
    {
        $signer = PDF::signer();
        $signer->loadFile(storage_path('documents/contract_signed_user1_user2.pdf'));
        $signer->loadCertificate(
            storage_path('certificates/user3.p12'),
            $request->input('password')
        );

        $signer->setReason('CEO Final Approval');
        $signer->setLocation('Los Angeles');

        // User 3's signature at the bottom
        $signer->setSignatureAppearance([
            'page' => 1,
            'x' => 50,
            'y' => 50,  // Lower position
            'width' => 450,
            'height' => 40,
        ]);

        $signedContent = $signer->sign();

        file_put_contents(
            storage_path('documents/contract_fully_signed.pdf'),
            $signedContent
        );

        return PDF::download($signedContent, 'contract_fully_signed.pdf');
    }
}


// ============================================================================
// Example 3: Service class for reusable signing workflow
// ============================================================================

class DocumentSigningService
{
    /**
     * Sign a document and return the signed content
     *
     * @param string $pdfContent The PDF content (can be already signed by others)
     * @param string $certificatePath Path to signer's certificate
     * @param string $password Certificate password
     * @param array $metadata Signing metadata (reason, location, contact)
     * @return string Signed PDF content
     */
    public function signDocument(
        string $pdfContent,
        string $certificatePath,
        string $password,
        array $metadata = []
    ): string {
        return PDF::sign($pdfContent, $certificatePath, $password, [
            'reason' => $metadata['reason'] ?? 'Document Signed',
            'location' => $metadata['location'] ?? null,
            'contact' => $metadata['contact'] ?? null,
        ]);
    }

    /**
     * Complete workflow: Each user signs and downloads
     */
    public function processSigningWorkflow(string $documentPath, array $signers): array
    {
        $results = [];
        $currentContent = file_get_contents($documentPath);

        foreach ($signers as $index => $signer) {
            // Sign the document
            $signedContent = $this->signDocument(
                $currentContent,
                $signer['certificate'],
                $signer['password'],
                [
                    'reason' => $signer['reason'] ?? "Signed by User " . ($index + 1),
                    'location' => $signer['location'] ?? null,
                    'contact' => $signer['contact'] ?? null,
                ]
            );

            // Store intermediate result
            $outputPath = storage_path(
                "documents/contract_signed_" . ($index + 1) . "_users.pdf"
            );
            file_put_contents($outputPath, $signedContent);

            $results[] = [
                'user' => $index + 1,
                'signed_by' => $signer['name'] ?? "User " . ($index + 1),
                'output_path' => $outputPath,
                'content' => $signedContent,
            ];

            // Next iteration uses this signed content
            $currentContent = $signedContent;
        }

        return $results;
    }
}

// Usage:
// $service = new DocumentSigningService();
// $results = $service->processSigningWorkflow('contract.pdf', [
//     ['name' => 'John', 'certificate' => 'john.p12', 'password' => 'pass1', 'reason' => 'Approved'],
//     ['name' => 'Jane', 'certificate' => 'jane.p12', 'password' => 'pass2', 'reason' => 'Reviewed'],
//     ['name' => 'CEO', 'certificate' => 'ceo.p12', 'password' => 'pass3', 'reason' => 'Final Approval'],
// ]);


// ============================================================================
// Example 4: API Routes for the signing workflow
// ============================================================================

/*
// routes/api.php

Route::prefix('documents/{document}')->group(function () {
    // User 1 initiates signing
    Route::post('/sign/user1', [DocumentSigningController::class, 'user1Signs']);

    // User 2 adds their signature
    Route::post('/sign/user2', [DocumentSigningController::class, 'user2Signs']);

    // User 3 completes the signing
    Route::post('/sign/user3', [DocumentSigningController::class, 'user3Signs']);

    // Download current state of document
    Route::get('/download', [DocumentSigningController::class, 'download']);

    // Get signature status
    Route::get('/signatures', [DocumentSigningController::class, 'getSignatures']);
});
*/


// ============================================================================
// Example 5: Database-backed workflow with status tracking
// ============================================================================

/*
// Migration: create_document_signatures_table.php

Schema::create('document_signatures', function (Blueprint $table) {
    $table->id();
    $table->foreignId('document_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->integer('order')->default(1);  // Signing order
    $table->string('reason')->nullable();
    $table->string('location')->nullable();
    $table->timestamp('signed_at')->nullable();
    $table->string('status')->default('pending'); // pending, signed, rejected
    $table->timestamps();
});

// Controller with database tracking
class WorkflowSigningController extends Controller
{
    public function sign(Request $request, Document $document)
    {
        $user = auth()->user();

        // Get this user's signature record
        $signatureRecord = $document->signatures()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->firstOrFail();

        // Check if previous signers have signed
        $previousPending = $document->signatures()
            ->where('order', '<', $signatureRecord->order)
            ->where('status', '!=', 'signed')
            ->exists();

        if ($previousPending) {
            return response()->json([
                'error' => 'Previous signers have not completed signing'
            ], 422);
        }

        // Get the latest signed version
        $pdfContent = Storage::get($document->current_file_path);

        // Sign the document
        $signedContent = PDF::sign(
            $pdfContent,
            $user->certificate_path,
            $request->input('certificate_password'),
            [
                'reason' => $signatureRecord->reason ?? 'Signed',
                'location' => $signatureRecord->location,
                'contact' => $user->email,
            ]
        );

        // Save the new version
        $newPath = "documents/{$document->id}/signed_{$signatureRecord->order}.pdf";
        Storage::put($newPath, $signedContent);

        // Update records
        $document->update(['current_file_path' => $newPath]);
        $signatureRecord->update([
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        // Return download response
        return PDF::download($signedContent, "{$document->name}_signed.pdf");
    }
}
*/
