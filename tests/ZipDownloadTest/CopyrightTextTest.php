<?php declare(strict_types=1);

namespace ZipDownloadTest;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for copyright text placeholder replacement.
 *
 * These tests verify the regex and placeholder logic without requiring
 * the full Omeka application context.
 */
class CopyrightTextTest extends TestCase
{
    /**
     * Test regex matches property placeholders.
     */
    public function testRegexMatchesPropertyPlaceholders(): void
    {
        $template = <<<'TXT'
            Source: {main_title}
            Author: {dcterms:creator}
            Date: {dcterms:date}
            Subject: {dcterms:subject}
            TXT;

        $matches = [];
        $result = preg_match_all('/\{([a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+)\}/', $template, $matches);

        $this->assertEquals(3, $result);
        $this->assertContains('dcterms:creator', $matches[1]);
        $this->assertContains('dcterms:date', $matches[1]);
        $this->assertContains('dcterms:subject', $matches[1]);
        // Should not match {main_title} (no colon).
        $this->assertNotContains('main_title', $matches[1]);
    }

    /**
     * Test regex matches various property formats.
     */
    public function testRegexMatchesVariousPropertyFormats(): void
    {
        $patterns = [
            '{dcterms:creator}' => 'dcterms:creator',
            '{foaf:name}' => 'foaf:name',
            '{bibo:doi}' => 'bibo:doi',
            '{schema:author}' => 'schema:author',
            '{custom-vocab:my_term}' => 'custom-vocab:my_term',
            '{ns123:term456}' => 'ns123:term456',
        ];

        foreach ($patterns as $placeholder => $expected) {
            $matches = [];
            preg_match_all('/\{([a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+)\}/', $placeholder, $matches);
            $this->assertEquals($expected, $matches[1][0] ?? null, "Failed for: $placeholder");
        }
    }

    /**
     * Test placeholder replacement with values.
     */
    public function testPlaceholderReplacementWithValues(): void
    {
        $template = <<<'TXT'
            Author: {dcterms:creator}
            Date: {dcterms:date}
            TXT;

        $placeholders = [
            '{dcterms:creator}' => 'John Doe, Jane Smith',
            '{dcterms:date}' => '2024-05-15',
        ];

        $result = strtr($template, $placeholders);

        $this->assertStringContainsString('Author: John Doe, Jane Smith', $result);
        $this->assertStringContainsString('Date: 2024-05-15', $result);
    }

    /**
     * Test placeholder replacement with empty values removes placeholder.
     */
    public function testPlaceholderReplacementWithEmptyValues(): void
    {
        $template = <<<'TXT'
            Author: {dcterms:creator}
            Date: {dcterms:date}
            Subject: {dcterms:subject}
            TXT;

        $placeholders = [
            '{dcterms:creator}' => 'John Doe',
            '{dcterms:date}' => '', // Empty value.
            '{dcterms:subject}' => '', // Empty value.
        ];

        $result = strtr($template, $placeholders);

        $this->assertStringContainsString('Author: John Doe', $result);
        $this->assertStringContainsString('Date: ', $result);
        $this->assertStringNotContainsString('{dcterms:date}', $result);
        $this->assertStringNotContainsString('{dcterms:subject}', $result);
    }

    /**
     * Test simulated value representation casting.
     *
     * This simulates how ValueRepresentation objects should be cast to string.
     */
    public function testValueRepresentationCasting(): void
    {
        // Simulate ValueRepresentation objects (they have __toString).
        $mockValues = [
            new class {
                public function __toString(): string
                {
                    return '<span>John Doe</span>';
                }
            },
            new class {
                public function __toString(): string
                {
                    return 'Jane Smith';
                }
            },
        ];

        // This is the fixed logic from the controller.
        $values = array_map(fn($v) => strip_tags((string) $v), $mockValues);

        $this->assertEquals(['John Doe', 'Jane Smith'], $values);
        $this->assertEquals('John Doe, Jane Smith', implode(', ', $values));
    }

    /**
     * Test empty array produces empty string.
     */
    public function testEmptyArrayProducesEmptyString(): void
    {
        $values = [];
        $result = implode(', ', $values);

        $this->assertEquals('', $result);
    }

    /**
     * Test full template replacement simulation.
     */
    public function testFullTemplateReplacement(): void
    {
        $template = <<<'TXT'
            Source: {main_title}
            Document: {resource_title}
            Author: {dcterms:creator}
            Date: {dcterms:date}
            Number of files: {file_count}
            Downloaded on: {date}

            Citation: {citation}

            URL: {resource_url}
            TXT;

        // Build placeholders (simulating controller logic).
        $placeholders = [
            '{file_count}' => '5',
            '{resource_id}' => '123',
            '{resource_title}' => 'Test Document',
            '{resource_url}' => 'https://example.org/s/site/item/123',
            '{file_type}' => 'original',
            '{site_title}' => 'My Site',
            '{site_url}' => 'https://example.org/s/site',
            '{main_title}' => 'Omeka S Installation',
            '{date}' => '2025-12-21',
            '{datetime}' => '2025-12-21 15:30:00',
            '{citation}' => 'John Doe, "Test Document", 2024. Accessed on 2025-12-21',
        ];

        // Simulate property extraction.
        $matches = [];
        if (preg_match_all('/\{([a-zA-Z0-9_-]+:[a-zA-Z0-9_-]+)\}/', $template, $matches)) {
            foreach ($matches[1] as $term) {
                // Simulate resource values.
                if ($term === 'dcterms:creator') {
                    $values = ['John Doe', 'Jane Smith'];
                } elseif ($term === 'dcterms:date') {
                    $values = ['2024-05-15'];
                } else {
                    $values = []; // No value.
                }
                $placeholders['{' . $term . '}'] = implode(', ', $values);
            }
        }

        $result = strtr($template, $placeholders);

        $this->assertStringContainsString('Source: Omeka S Installation', $result);
        $this->assertStringContainsString('Document: Test Document', $result);
        $this->assertStringContainsString('Author: John Doe, Jane Smith', $result);
        $this->assertStringContainsString('Date: 2024-05-15', $result);
        $this->assertStringContainsString('Number of files: 5', $result);
        $this->assertStringContainsString('Downloaded on: 2025-12-21', $result);
        $this->assertStringContainsString('URL: https://example.org/s/site/item/123', $result);
        // No remaining placeholders.
        $this->assertStringNotContainsString('{dcterms:', $result);
    }

    /**
     * Test placeholder with special characters in values.
     */
    public function testPlaceholderWithSpecialCharacters(): void
    {
        $template = 'Author: {dcterms:creator}';

        // Value with HTML that should be stripped.
        $rawValue = '<a href="http://example.org">John <b>Doe</b></a>';
        $cleanValue = strip_tags($rawValue);

        $placeholders = [
            '{dcterms:creator}' => $cleanValue,
        ];

        $result = strtr($template, $placeholders);

        $this->assertEquals('Author: John Doe', $result);
    }

    /**
     * Test multiple values are joined with comma.
     */
    public function testMultipleValuesJoinedWithComma(): void
    {
        $values = ['Author One', 'Author Two', 'Author Three'];
        $result = implode(', ', $values);

        $this->assertEquals('Author One, Author Two, Author Three', $result);
    }

    /**
     * Test citation building with multiple creators.
     */
    public function testCitationWithMultipleCreators(): void
    {
        $testCases = [
            [['John Doe'], 'John Doe'],
            [['John Doe', 'Jane Smith'], 'John Doe and Jane Smith'],
            [['John Doe', 'Jane Smith', 'Bob Wilson'], 'John Doe, Jane Smith, and Bob Wilson'],
            [['John Doe', 'Jane Smith', 'Bob Wilson', 'Alice Brown'], 'John Doe et al.'],
        ];

        foreach ($testCases as [$creators, $expected]) {
            $result = $this->formatCreators($creators);
            $this->assertEquals($expected, $result, 'Failed for: ' . implode(', ', $creators));
        }
    }

    /**
     * Format creators list (simulates controller logic).
     */
    private function formatCreators(array $creators): string
    {
        switch (count($creators)) {
            case 0:
                return '';
            case 1:
                return $creators[0];
            case 2:
                return sprintf('%s and %s', $creators[0], $creators[1]);
            case 3:
                return sprintf('%s, %s, and %s', $creators[0], $creators[1], $creators[2]);
            default:
                return sprintf('%s et al.', $creators[0]);
        }
    }
}
