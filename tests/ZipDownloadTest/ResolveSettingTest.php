<?php declare(strict_types=1);

namespace ZipDownloadTest;

use PHPUnit\Framework\TestCase;
use ZipDownload\Module;

class ResolveSettingTest extends TestCase
{
    public function testEmptySiteValuesUseMainValue(): void
    {
        $this->assertSame('main', Module::resolveSetting(null, 'main'));
        $this->assertSame('main', Module::resolveSetting('', 'main'));
        $this->assertSame(['original'], Module::resolveSetting([], ['original']));
    }

    public function testSiteBooleansOverrideMainValue(): void
    {
        $this->assertTrue(Module::resolveSetting('yes', false));
        $this->assertFalse(Module::resolveSetting('no', true));
    }

    public function testSiteValuesOverrideMainValue(): void
    {
        $this->assertSame('primary', Module::resolveSetting('primary', 'all'));
        $this->assertSame('0', Module::resolveSetting('0', '25'));
        $this->assertSame(['large'], Module::resolveSetting(['large'], ['original']));
    }

    public function testExcludedSettings(): void
    {
        $this->assertTrue(Module::isExcludedSetting('zipdownload_text', ['text']));
        $this->assertTrue(Module::isExcludedSetting('zipdownload_asset', ['text', 'asset']));
        $this->assertFalse(Module::isExcludedSetting('zipdownload_asset', ['text']));
        $this->assertFalse(Module::isExcludedSetting('zipdownload_text', null));
        $this->assertFalse(Module::isExcludedSetting('zipdownload_tag', ['text', 'asset']));
    }
}
