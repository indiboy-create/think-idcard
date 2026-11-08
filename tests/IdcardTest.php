<?php

declare(strict_types=1);

namespace Youvision\ThinkIdcard\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkIdcard\Idcard;

class IdcardTest extends TestCase
{
    protected Idcard $idcard;

    protected function setUp(): void
    {
        $this->idcard = new Idcard(require dirname(__DIR__) . '/config/idcard.php');
    }

    public function testParseReturnsFullProfile(): void
    {
        $info = $this->idcard->parse('110101199601249935');

        $this->assertSame('110101199601249935', $info['id_number']);
        $this->assertTrue($info['valid']);
        $this->assertTrue($info['checksum_valid']);
        $this->assertTrue($info['birthday_valid']);
        $this->assertTrue($info['region_valid']);
        $this->assertSame('110101', $info['region_code']);
        $this->assertSame('北京市', $info['province']);
        $this->assertSame('市辖区', $info['city']);
        $this->assertSame('东城区', $info['area']);
        $this->assertSame('1996-01-24', $info['birthday']);
        $this->assertSame('1996', $info['year']);
        $this->assertSame('01', $info['month']);
        $this->assertSame('24', $info['day']);
        $this->assertSame('男', $info['gender']);
        $this->assertSame(1, $info['gender_code']);
        $this->assertSame($this->idcard->getAge('110101199601249935'), $info['age']);
    }

    public function testParseInvalidNumberReturnsUnknownProfile(): void
    {
        $info = $this->idcard->parse('11010119960124993');

        $this->assertFalse($info['valid']);
        $this->assertFalse($info['checksum_valid']);
        $this->assertFalse($info['birthday_valid']);
        $this->assertFalse($info['region_valid']);
        $this->assertSame('', $info['birthday']);
        $this->assertNull($info['age']);
        $this->assertNull($info['gender_code']);
        $this->assertSame('未知', $info['gender']);
        $this->assertSame('未知', $info['province']);
    }

    public function testGetAddressInfoMatchesSourceBehaviour(): void
    {
        $this->assertSame([
            'province' => '北京市',
            'city' => '市辖区',
            'area' => '东城区',
        ], $this->idcard->getAddressInfo('110101199601249935'));

        $this->assertSame('', $this->idcard->getAddressInfo('110101199601249935', false)['city']);

        $config = new Idcard(['return_units_directly_under' => false, 'data_path' => '']);
        $this->assertSame('', $config->parse('110101199601249935')['city']);

        $this->assertSame([
            'province' => '未知',
            'city' => '未知',
            'area' => '未知',
        ], $this->idcard->getAddressInfo('11010119960124993'));
    }

    public function testGenderAndBirthdayHelpers(): void
    {
        $this->assertSame('男', $this->idcard->getGender('110101199601249935'));
        $this->assertSame('未知', $this->idcard->getGender('11010119960124993'));
        $this->assertSame([
            'year' => '1996',
            'month' => '01',
            'day' => '24',
        ], $this->idcard->getBirthday('110101199601249935'));
    }

    public function testAgeUsesGivenBaseDate(): void
    {
        $this->assertSame(28, $this->idcard->getAge('110101199601249935', '2025-01-23'));
        $this->assertSame(30, $this->idcard->getAge('110101199601249935', '2026-01-24'));
        $this->assertNull($this->idcard->getAge('110101199601249935', '1995-01-01'));
    }

    public function testFifteenDigitNumberIsNormalized(): void
    {
        $this->assertSame('110101199601249935', $this->idcard->normalize('110101960124993'));

        $info = $this->idcard->parse('110101960124993');

        $this->assertSame('110101199601249935', $info['id_number']);
        $this->assertTrue($info['valid']);
        $this->assertSame('男', $info['gender']);
        $this->assertSame('北京市', $info['province']);
    }

    public function testFifteenDigitCanBeDisabled(): void
    {
        $idcard = new Idcard(['support_15' => false, 'data_path' => '']);

        $this->assertSame('110101960124993', $idcard->normalize('110101960124993'));
        $this->assertFalse($idcard->isValid('110101960124993'));
        $this->assertFalse($idcard->parse('110101960124993')['valid']);
    }

    public function testChecksumFailureIsInvalid(): void
    {
        $this->assertFalse($this->idcard->isValid('110101199601249931'));
        $this->assertFalse($this->idcard->parse('110101199601249931')['checksum_valid']);
    }

    public function testInvalidBirthdayIsReportedButAllowedByDefault(): void
    {
        $idNumber = $this->makeIdNumber('11010119960230', '993');

        $info = $this->idcard->parse($idNumber);

        $this->assertTrue($info['checksum_valid']);
        $this->assertFalse($info['birthday_valid']);
        $this->assertTrue($info['valid']);
        $this->assertTrue($this->idcard->isValid($idNumber));

        $strict = new Idcard(['strict_validation' => true, 'data_path' => '']);

        $this->assertFalse($strict->isValid($idNumber));
        $this->assertFalse($strict->parse($idNumber)['valid']);
    }

    public function testUnknownRegionIsReportedAndAllowedByDefault(): void
    {
        $idNumber = $this->makeIdNumber('99999919960124', '993');

        $info = $this->idcard->parse($idNumber);

        $this->assertTrue($info['checksum_valid']);
        $this->assertTrue($info['birthday_valid']);
        $this->assertFalse($info['region_valid']);
        $this->assertTrue($info['valid']);
        $this->assertSame('未知', $info['area']);

        $strict = new Idcard(['strict_validation' => true, 'data_path' => '']);

        $this->assertFalse($strict->isValid($idNumber));
    }

    public function testMergedRegionFallsBackToDiffData(): void
    {
        $idNumber = $this->makeIdNumber('11010319960124', '993');

        $this->assertSame([
            'province' => '北京市',
            'city' => '市辖区',
            'area' => '东城区',
        ], $this->idcard->getAddressInfo($idNumber));
    }

    public function testCustomUnknownValue(): void
    {
        $idcard = new Idcard(['unknown_value' => '未收录', 'data_path' => '']);
        $info = $idcard->parse('11010119960124993');

        $this->assertSame('未收录', $info['province']);
        $this->assertSame('未收录', $info['gender']);
        $this->assertSame('未收录', $idcard->getAddressInfo('11010119960124993')['area']);
    }

    public function testCustomDataPathIsUsed(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think-idcard-' . uniqid('', true);
        mkdir($dir);

        file_put_contents($dir . '/data.json', json_encode([
            '110000' => '测试省',
            '110100' => '测试市',
            '110101' => '测试区',
        ]));
        file_put_contents($dir . '/history.json', '{}');
        file_put_contents($dir . '/diff.json', '{}');

        try {
            $idcard = new Idcard(['data_path' => $dir]);
            $info = $idcard->parse('110101199601249935');

            $this->assertSame('测试省', $info['province']);
            $this->assertSame('测试市', $info['city']);
            $this->assertSame('测试区', $info['area']);
            $this->assertTrue($info['region_valid']);
            $this->assertSame('未知', $idcard->getAddressInfo('11010519960124993X')['area']);
        } finally {
            @unlink($dir . '/data.json');
            @unlink($dir . '/history.json');
            @unlink($dir . '/diff.json');
            @rmdir($dir);
        }
    }

    public function testWhitespaceAndLowercaseAreNormalized(): void
    {
        $this->assertTrue($this->idcard->isValid(' 110101 19960124005x '));
        $this->assertSame('11010119960124005X', $this->idcard->normalize(' 110101 19960124005x '));
        $this->assertSame('男', $this->idcard->getGender(' 110101 19960124005x '));
    }

    protected function makeIdNumber(string $first14, string $sequence): string
    {
        $body = $first14 . $sequence;
        $weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $checksumMap = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $sum = 0;

        for ($i = 0; $i < 17; $i++) {
            $sum += (int) $body[$i] * $weights[$i];
        }

        return $body . $checksumMap[$sum % 11];
    }
}