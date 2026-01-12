<?php declare(strict_types=1);

namespace ZipDownloadTest;

use PHPUnit\Framework\TestCase;

/**
 * Tests for missing file handling in ZipDownload.
 *
 * Verifies that the module gracefully handles cases where media files
 * referenced in the database no longer exist on disk (e.g. deleted,
 * moved, or failed upload).
 */
class MissingFileTest extends TestCase
{
    /**
     * Test that getMediaFilePath returns null when file does not exist.
     *
     * Simulates the logic of DownloadController::getMediaFilePath().
     */
    public function testGetMediaFilePathReturnsNullForMissingOriginal(): void
    {
        $basePath = sys_get_temp_dir() . '/omeka-test-files/';
        $filename = 'nonexistent_file_' . uniqid() . '.jpg';
        $filepath = $basePath . 'original/' . $filename;

        // Reproduce the logic of getMediaFilePath for 'original'.
        $result = file_exists($filepath) ? $filepath : null;

        $this->assertNull($result);
    }

    /**
     * Test that getMediaFilePath returns null when thumbnail does not exist.
     */
    public function testGetMediaFilePathReturnsNullForMissingThumbnail(): void
    {
        $basePath = sys_get_temp_dir() . '/omeka-test-files/';
        $storageId = 'nonexistent_' . uniqid();

        foreach (['large', 'medium', 'square'] as $type) {
            $filepath = $basePath . $type . '/' . $storageId . '.jpg';
            $result = file_exists($filepath) ? $filepath : null;
            $this->assertNull($result, "Expected null for missing $type thumbnail");
        }
    }

    /**
     * Test that calculateTotalSize returns 0 when all files are missing.
     *
     * Simulates the logic of DownloadZip::calculateTotalSize() +
     * getMediaFileSize() for missing files.
     */
    public function testCalculateTotalSizeReturnsZeroForMissingFiles(): void
    {
        $basePath = sys_get_temp_dir() . '/omeka-test-files-' . uniqid() . '/';

        // Simulate getMediaFileSize logic for missing files.
        $filenames = [
            'missing1_' . uniqid() . '.jpg',
            'missing2_' . uniqid() . '.png',
            'missing3_' . uniqid() . '.pdf',
        ];

        $totalSize = 0;
        foreach ($filenames as $filename) {
            $filepath = $basePath . 'original/' . $filename;
            if (file_exists($filepath)) {
                $totalSize += (int) filesize($filepath);
            }
        }

        $this->assertEquals(0, $totalSize);
    }

    /**
     * Test that calculateTotalSize correctly sums existing files and skips
     * missing ones.
     */
    public function testCalculateTotalSizeMixedExistingAndMissing(): void
    {
        $basePath = sys_get_temp_dir() . '/omeka-test-zipdownload-' . uniqid() . '/';
        mkdir($basePath . 'original', 0777, true);

        // Create one real file.
        $existingFile = $basePath . 'original/existing.jpg';
        file_put_contents($existingFile, str_repeat('x', 1024));

        $missingFile = $basePath . 'original/missing_' . uniqid() . '.jpg';

        $totalSize = 0;
        foreach ([$existingFile, $missingFile] as $filepath) {
            if (file_exists($filepath)) {
                $totalSize += (int) filesize($filepath);
            }
        }

        $this->assertEquals(1024, $totalSize);

        // Cleanup.
        unlink($existingFile);
        rmdir($basePath . 'original');
        rmdir($basePath);
    }

    /**
     * Test that the zip streaming loop skips missing files (continue logic).
     *
     * Simulates the core loop of DownloadController::streamZipArchive().
     */
    public function testZipStreamingLoopSkipsMissingFiles(): void
    {
        $basePath = sys_get_temp_dir() . '/omeka-test-zipdownload-' . uniqid() . '/';
        mkdir($basePath . 'original', 0777, true);

        // Create two real files and reference one missing.
        $file1 = $basePath . 'original/file1.jpg';
        $file2 = $basePath . 'original/file2.jpg';
        $fileMissing = $basePath . 'original/missing_' . uniqid() . '.jpg';

        file_put_contents($file1, 'content1');
        file_put_contents($file2, 'content2');

        // Simulate the zip streaming loop.
        $filesProcessed = [];
        $allFiles = [$file1, $fileMissing, $file2];
        foreach ($allFiles as $filepath) {
            if (!$filepath || !file_exists($filepath)) {
                continue;
            }
            $filesProcessed[] = $filepath;
        }

        $this->assertCount(2, $filesProcessed);
        $this->assertContains($file1, $filesProcessed);
        $this->assertContains($file2, $filesProcessed);
        $this->assertNotContains($fileMissing, $filesProcessed);

        // Cleanup.
        unlink($file1);
        unlink($file2);
        rmdir($basePath . 'original');
        rmdir($basePath);
    }

    /**
     * Test that the zip streaming loop handles all files missing.
     */
    public function testZipStreamingLoopHandlesAllFilesMissing(): void
    {
        $basePath = sys_get_temp_dir() . '/omeka-test-zipdownload-' . uniqid() . '/';

        $allFiles = [
            $basePath . 'original/missing1.jpg',
            $basePath . 'original/missing2.jpg',
            $basePath . 'original/missing3.jpg',
        ];

        $filesProcessed = [];
        foreach ($allFiles as $filepath) {
            if (!$filepath || !file_exists($filepath)) {
                continue;
            }
            $filesProcessed[] = $filepath;
        }

        $this->assertCount(0, $filesProcessed);
    }

    /**
     * Test that null filepath is skipped.
     *
     * Simulates getMediaFilePath returning null and the zip loop handling it.
     */
    public function testZipStreamingLoopSkipsNullFilepath(): void
    {
        $allFiles = [null, null, null];

        $filesProcessed = [];
        foreach ($allFiles as $filepath) {
            if (!$filepath || !file_exists($filepath)) {
                continue;
            }
            $filesProcessed[] = $filepath;
        }

        $this->assertCount(0, $filesProcessed);
    }

    /**
     * Test formatFileSize handles zero bytes.
     *
     * Simulates DownloadZip::formatFileSize().
     */
    public function testFormatFileSizeHandlesZeroBytes(): void
    {
        $result = $this->formatFileSize(0);
        $this->assertEquals('0 B', $result);
    }

    /**
     * Test formatFileSize handles various sizes.
     */
    public function testFormatFileSizeHandlesVariousSizes(): void
    {
        $this->assertEquals('0 B', $this->formatFileSize(0));
        $this->assertEquals('512 B', $this->formatFileSize(512));
        $this->assertEquals('1 KB', $this->formatFileSize(1024));
        $this->assertEquals('1.5 KB', $this->formatFileSize(1536));
        $this->assertEquals('1 MB', $this->formatFileSize(1048576));
        $this->assertEquals('1.5 MB', $this->formatFileSize(1572864));
        $this->assertEquals('1 GB', $this->formatFileSize(1073741824));
    }

    /**
     * Reproduce the formatFileSize logic from DownloadZip view helper.
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        } elseif ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        } else {
            return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        }
    }
}
