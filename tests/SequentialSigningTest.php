<?php

declare(strict_types=1);

namespace PdfLib\Laravel\Tests;

use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase;
use PdfLib\Document\PdfDocument;
use PdfLib\Laravel\Facades\PDF;
use PdfLib\Laravel\PdfManager;
use PdfLib\Laravel\PdfServiceProvider;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;
use PdfLib\Security\Signature\SignatureField;

/**
 * Test sequential digital signature workflow:
 * 1. User 1 signs and downloads
 * 2. User 2 signs doc from flow 1 and downloads
 * 3. User 3 signs doc from flow 2 and downloads
 */
class SequentialSigningTest extends TestCase
{
    protected string $targetDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->targetDir = sys_get_temp_dir() . '/laravel-pdf-tests';
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0755, true);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [PdfServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'PDF' => PDF::class,
        ];
    }

    /**
     * Test sequential signing workflow using Laravel Facade.
     */
    public function testSequentialSigningWithFacade(): void
    {
        // Create certificates for three users
        $user1Cert = $this->createTestCertificate('User 1 - Approver', 'user1@test.com');
        $user2Cert = $this->createTestCertificate('User 2 - Reviewer', 'user2@test.com');
        $user3Cert = $this->createTestCertificate('User 3 - Manager', 'user3@test.com');

        // Save certificates to files (for multiSign compatibility)
        $this->saveCertificateFiles('user1', $user1Cert);
        $this->saveCertificateFiles('user2', $user2Cert);
        $this->saveCertificateFiles('user3', $user3Cert);

        // Create original document
        $pdf = PDF::create(['title' => 'Sequential Signature Test']);
        $page = new Page(PageSize::a4());
        $page->addText('Sequential Signature Document', 100, 750, ['fontSize' => 24]);
        $page->addText('This document requires 3 signatures.', 100, 700, ['fontSize' => 12]);
        $pdf->addPageObject($page);

        $originalContent = $pdf->render();

        // ========== Flow 1: User 1 signs ==========
        $signedByUser1 = PDF::sign(
            $originalContent,
            $this->targetDir . '/user1.pem',
            '',
            [
                'key' => $this->targetDir . '/user1_key.pem',
                'reason' => 'First Approval',
                'location' => 'Office A',
            ]
        );

        // Verify User 1's signature
        $this->assertNotEmpty($signedByUser1);
        $this->assertEquals(1, preg_match_all('/\/Type\s*\/Sig\b/', $signedByUser1));

        // Save for User 2
        $user1OutputPath = $this->targetDir . '/signed_user1.pdf';
        file_put_contents($user1OutputPath, $signedByUser1);
        $this->assertFileExists($user1OutputPath);

        // ========== Flow 2: User 2 signs doc from User 1 ==========
        $signedByUser1And2 = PDF::sign(
            $signedByUser1,
            $this->targetDir . '/user2.pem',
            '',
            [
                'key' => $this->targetDir . '/user2_key.pem',
                'reason' => 'Second Approval',
                'location' => 'Office B',
            ]
        );

        // Verify both signatures
        $this->assertEquals(2, preg_match_all('/\/Type\s*\/Sig\b/', $signedByUser1And2));

        // Save for User 3
        $user2OutputPath = $this->targetDir . '/signed_user1_user2.pdf';
        file_put_contents($user2OutputPath, $signedByUser1And2);
        $this->assertFileExists($user2OutputPath);

        // ========== Flow 3: User 3 signs doc from User 2 ==========
        $fullySignedPdf = PDF::sign(
            $signedByUser1And2,
            $this->targetDir . '/user3.pem',
            '',
            [
                'key' => $this->targetDir . '/user3_key.pem',
                'reason' => 'Final Approval',
                'location' => 'Office C',
                'contact' => 'user3@test.com',
            ]
        );

        // Verify all three signatures
        $this->assertEquals(3, preg_match_all('/\/Type\s*\/Sig\b/', $fullySignedPdf));
        $this->assertEquals(3, preg_match_all('/\/ByteRange\s*\[/', $fullySignedPdf));

        // Save final document
        $finalOutputPath = $this->targetDir . '/fully_signed.pdf';
        file_put_contents($finalOutputPath, $fullySignedPdf);
        $this->assertFileExists($finalOutputPath);

        // Verify file sizes increase (due to incremental updates)
        $this->assertGreaterThan(filesize($user1OutputPath), filesize($user2OutputPath));
        $this->assertGreaterThan(filesize($user2OutputPath), filesize($finalOutputPath));
    }

    /**
     * Test multiSign method for batch signing.
     */
    public function testMultiSignMethod(): void
    {
        // Create certificates
        $certs = [
            $this->createTestCertificate('Signer A', 'a@test.com'),
            $this->createTestCertificate('Signer B', 'b@test.com'),
            $this->createTestCertificate('Signer C', 'c@test.com'),
        ];

        foreach (['a', 'b', 'c'] as $i => $name) {
            $this->saveCertificateFiles("signer_{$name}", $certs[$i]);
        }

        // Create document
        $pdf = PDF::create(['title' => 'Multi-Sign Test']);
        $page = new Page(PageSize::a4());
        $page->addText('Multi-Sign Document', 100, 750, ['fontSize' => 24]);
        $pdf->addPageObject($page);

        // Apply all signatures using multiSign
        $signed = PDF::multiSign($pdf, [
            [
                'cert' => $this->targetDir . '/signer_a.pem',
                'password' => '',
                'key' => $this->targetDir . '/signer_a_key.pem',
                'reason' => 'Signer A Approval',
            ],
            [
                'cert' => $this->targetDir . '/signer_b.pem',
                'password' => '',
                'key' => $this->targetDir . '/signer_b_key.pem',
                'reason' => 'Signer B Review',
            ],
            [
                'cert' => $this->targetDir . '/signer_c.pem',
                'password' => '',
                'key' => $this->targetDir . '/signer_c_key.pem',
                'reason' => 'Signer C Final',
            ],
        ]);

        // Verify all three signatures
        $this->assertEquals(3, preg_match_all('/\/Type\s*\/Sig\b/', $signed));

        $outputPath = $this->targetDir . '/multi_signed.pdf';
        file_put_contents($outputPath, $signed);
        $this->assertFileExists($outputPath);
    }

    /**
     * Test downloading signed PDFs with Response helper.
     */
    public function testSignAndDownload(): void
    {
        $cert = $this->createTestCertificate('Download Test', 'download@test.com');
        $this->saveCertificateFiles('download_test', $cert);

        $pdf = PDF::create();
        $page = new Page(PageSize::a4());
        $page->addText('Download Test', 100, 750);
        $pdf->addPageObject($page);

        $signed = PDF::sign(
            $pdf,
            $this->targetDir . '/download_test.pem',
            '',
            ['key' => $this->targetDir . '/download_test_key.pem']
        );

        // Test download response
        $response = PDF::download($signed, 'test-signed.pdf');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Test PdfManager directly (dependency injection).
     */
    public function testSequentialSigningWithManager(): void
    {
        $manager = app(PdfManager::class);

        $cert1 = $this->createTestCertificate('Manager Test 1', 'manager1@test.com');
        $cert2 = $this->createTestCertificate('Manager Test 2', 'manager2@test.com');

        $this->saveCertificateFiles('manager_test1', $cert1);
        $this->saveCertificateFiles('manager_test2', $cert2);

        // Create document using manager
        $pdf = $manager->create(['title' => 'Manager Test']);
        $page = new Page(PageSize::a4());
        $page->addText('Manager Direct Test', 100, 750);
        $pdf->addPageObject($page);

        // First signature
        $signed1 = $manager->sign(
            $pdf,
            $this->targetDir . '/manager_test1.pem',
            '',
            ['key' => $this->targetDir . '/manager_test1_key.pem']
        );

        $this->assertEquals(1, preg_match_all('/\/Type\s*\/Sig\b/', $signed1));

        // Second signature
        $signed2 = $manager->sign(
            $signed1,
            $this->targetDir . '/manager_test2.pem',
            '',
            ['key' => $this->targetDir . '/manager_test2_key.pem']
        );

        $this->assertEquals(2, preg_match_all('/\/Type\s*\/Sig\b/', $signed2));
    }

    /**
     * Create a self-signed test certificate.
     */
    private function createTestCertificate(string $commonName, string $email): array
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);

        $dn = [
            'countryName' => 'US',
            'stateOrProvinceName' => 'Test State',
            'localityName' => 'Test City',
            'organizationName' => 'Test Org',
            'commonName' => $commonName,
            'emailAddress' => $email,
        ];

        $csr = openssl_csr_new($dn, $privateKey, $config);
        $cert = openssl_csr_sign($csr, null, $privateKey, 365, $config);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($privateKey, $keyPem);

        return ['cert' => $certPem, 'key' => $keyPem];
    }

    /**
     * Save certificate and key to files.
     */
    private function saveCertificateFiles(string $name, array $cert): void
    {
        file_put_contents($this->targetDir . "/{$name}.pem", $cert['cert']);
        file_put_contents($this->targetDir . "/{$name}_key.pem", $cert['key']);
    }
}
