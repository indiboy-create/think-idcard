<?php

declare(strict_types=1);

namespace Youvision\ThinkIdcard\Tests;

use PHPUnit\Framework\TestCase;
use think\App;
use Youvision\ThinkIdcard\Idcard;
use Youvision\ThinkIdcard\Service;

class ServiceTest extends TestCase
{
    protected function createApp(array $userConfig = []): App
    {
        $app = new App();

        if ($userConfig !== []) {
            $app->config->set($userConfig, 'idcard');
        }

        $app->register(Service::class);

        return $app;
    }

    public function testServiceBindsIdcardInstance(): void
    {
        $app = $this->createApp();

        $idcard = $app->make('idcard');

        $this->assertInstanceOf(Idcard::class, $idcard);
        $this->assertSame('未知', $idcard->getConfig()['unknown_value']);
        $this->assertTrue($idcard->getConfig()['support_15']);
    }

    public function testServiceMergesUserConfigWithPackageDefaults(): void
    {
        $app = $this->createApp([
            'unknown_value' => '未收录',
            'strict_validation' => true,
        ]);

        $idcard = $app->make(Idcard::class);

        $this->assertSame('未收录', $idcard->getConfig()['unknown_value']);
        $this->assertTrue($idcard->getConfig()['strict_validation']);
        $this->assertTrue($idcard->getConfig()['return_units_directly_under']);
        $this->assertSame('未收录', $idcard->parse('11010119960124993')['province']);
    }

    public function testFacadeResolvesFromContainer(): void
    {
        $this->createApp();

        $info = \Youvision\ThinkIdcard\Facade\Idcard::parse('110101199601249935');

        $this->assertTrue($info['valid']);
        $this->assertSame('东城区', $info['area']);
        $this->assertSame('男', \Youvision\ThinkIdcard\Facade\Idcard::getGender('110101199601249935'));
    }
}