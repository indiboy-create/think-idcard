<?php

declare(strict_types=1);

namespace Youvision\ThinkIdcard;

use DateTimeImmutable;
use Youvision\ThinkIdcard\Support\RegionRepository;

class Idcard
{
    protected const WEIGHTS = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];

    protected const CHECKSUM_MAP = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];

    protected array $config;

    protected RegionRepository $regions;

    public function __construct(array $config = [], ?RegionRepository $regions = null)
    {
        $this->config = array_merge(self::defaultConfig(), $config);
        $this->regions = $regions ?? new RegionRepository((string) $this->config['data_path']);
    }

    public static function defaultConfig(): array
    {
        return [
            'unknown_value' => '未知',
            'return_units_directly_under' => true,
            'strict_validation' => false,
            'support_15' => true,
            'data_path' => '',
        ];
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getRegions(): RegionRepository
    {
        return $this->regions;
    }

    /**
     * 综合解析身份证号码
     *
     * @param string $idNumber 身份证号码，支持 18 位与 15 位（需开启 support_15）
     *
     * @return array{
     *     id_number:string,
     *     valid:bool,
     *     checksum_valid:bool,
     *     birthday_valid:bool,
     *     region_valid:bool,
     *     region_code:string,
     *     province:string,
     *     city:string,
     *     area:string,
     *     birthday:string,
     *     year:string,
     *     month:string,
     *     day:string,
     *     age:int|null,
     *     gender:string,
     *     gender_code:int|null
     * }
     */
    public function parse(string $idNumber): array
    {
        $normalized = $this->normalize($idNumber);
        $unknown = $this->unknownValue();
        $is15 = $this->support15() && preg_match('/^\d{15}$/', preg_replace('/\s+/', '', trim($idNumber)) ?? '') === 1;
        $formatValid = preg_match('/^\d{17}[\dX]$/', $normalized) === 1;

        $checksumValid = $formatValid && $this->checkChecksum($normalized);
        $birthday = $formatValid ? $this->getBirthday($normalized) : ['year' => '', 'month' => '', 'day' => ''];
        $birthdayValid = $formatValid && $this->isRealBirthday($birthday);
        $regionCode = $formatValid ? substr($normalized, 0, 6) : '';
        $regionValid = $formatValid && $this->regions->has($regionCode);
        $address = $formatValid
            ? $this->regions->findAddress($regionCode, $this->returnUnitsDirectlyUnder(), $unknown)
            : ['province' => $unknown, 'city' => $unknown, 'area' => $unknown];

        $valid = $is15
            ? $formatValid && $birthdayValid
            : $formatValid && $checksumValid;

        if ($this->strictValidation()) {
            $valid = $valid && $birthdayValid && $regionValid;
        }

        $gender = $formatValid ? $this->getGender($normalized) : $unknown;

        return [
            'id_number' => $normalized,
            'valid' => $valid,
            'checksum_valid' => $is15 ? $formatValid : $checksumValid,
            'birthday_valid' => $birthdayValid,
            'region_valid' => $regionValid,
            'region_code' => $regionCode,
            'province' => $address['province'],
            'city' => $address['city'],
            'area' => $address['area'],
            'birthday' => $birthdayValid
                ? $birthday['year'] . '-' . $birthday['month'] . '-' . $birthday['day']
                : '',
            'year' => $birthday['year'],
            'month' => $birthday['month'],
            'day' => $birthday['day'],
            'age' => $birthdayValid ? $this->getAge($normalized) : null,
            'gender' => $gender,
            'gender_code' => $formatValid
                ? ((int) $normalized[16] % 2)
                : null,
        ];
    }

    /**
     * 获取身份证号码对应的省、市、区县信息
     */
    public function getAddressInfo(
        string $idNumber,
        ?bool $returnUnitsDirectlyUnder = null,
        ?string $unknown = null
    ): array {
        $returnUnitsDirectlyUnder ??= $this->returnUnitsDirectlyUnder();
        $unknown ??= $this->unknownValue();

        $normalized = $this->normalize($idNumber);

        if (!preg_match('/^\d{17}[\dX]$/', $normalized)) {
            return [
                'province' => $unknown,
                'city' => $unknown,
                'area' => $unknown,
            ];
        }

        return $this->regions->findAddress(substr($normalized, 0, 6), $returnUnitsDirectlyUnder, $unknown);
    }

    /**
     * 获取身份证号码对应的性别
     */
    public function getGender(string $idNumber): string
    {
        $normalized = $this->normalize($idNumber);

        if (!preg_match('/^\d{17}[\dX]$/', $normalized)) {
            return $this->unknownValue();
        }

        return (int) $normalized[16] % 2 === 1 ? '男' : '女';
    }

    /**
     * 获取身份证号码对应的出生日期
     *
     * @return array{year:string,month:string,day:string}
     */
    public function getBirthday(string $idNumber): array
    {
        $normalized = $this->normalize($idNumber);

        if (!preg_match('/^\d{17}[\dX]$/', $normalized)) {
            return [
                'year' => '',
                'month' => '',
                'day' => '',
            ];
        }

        return [
            'year' => substr($normalized, 6, 4),
            'month' => substr($normalized, 10, 2),
            'day' => substr($normalized, 12, 2),
        ];
    }

    /**
     * 计算身份证持有人的年龄
     *
     * @param string $date 计算基准日期（Y-m-d），留空使用当天
     */
    public function getAge(string $idNumber, ?string $date = null): ?int
    {
        $birthday = $this->getBirthday($idNumber);

        if (!$this->isRealBirthday($birthday)) {
            return null;
        }

        $birth = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $birthday['year'] . '-' . $birthday['month'] . '-' . $birthday['day']
        );

        if ($birth === false) {
            return null;
        }

        $today = $date === null || $date === ''
            ? new DateTimeImmutable('today')
            : DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($today === false || $birth > $today) {
            return null;
        }

        return $birth->diff($today)->y;
    }

    /**
     * 校验身份证号码是否有效
     *
     * 默认只校验格式与校验位，开启 strict_validation 后额外校验出生日期与行政区划。
     */
    public function isValid(string $idNumber, ?bool $strict = null): bool
    {
        $normalized = $this->normalize($idNumber);
        $is15 = $this->support15() && preg_match('/^\d{15}$/', preg_replace('/\s+/', '', trim($idNumber)) ?? '') === 1;
        $formatValid = preg_match('/^\d{17}[\dX]$/', $normalized) === 1;

        if (!$formatValid) {
            return false;
        }

        if (!$is15 && !$this->checkChecksum($normalized)) {
            return false;
        }

        if ($is15 && !$this->isRealBirthday($this->getBirthday($normalized))) {
            return false;
        }

        $strict ??= $this->strictValidation();

        if (!$strict) {
            return true;
        }

        return $this->isRealBirthday($this->getBirthday($normalized))
            && $this->regions->has(substr($normalized, 0, 6));
    }

    /**
     * 规范化身份证号码
     *
     * 去除首尾空白与内部空格、校验位转大写，15 位号码补全为 18 位。
     */
    public function normalize(string $idNumber): string
    {
        $idNumber = strtoupper(preg_replace('/\s+/', '', trim($idNumber)) ?? '');

        if (!$this->support15() || !preg_match('/^\d{15}$/', $idNumber)) {
            return $idNumber;
        }

        $body = substr($idNumber, 0, 6) . '19' . substr($idNumber, 6);

        return $body . $this->checksum($body);
    }

    protected function checkChecksum(string $idNumber): bool
    {
        if (!preg_match('/^\d{17}[\dX]$/', $idNumber)) {
            return false;
        }

        return $idNumber[17] === $this->checksum(substr($idNumber, 0, 17));
    }

    protected function checksum(string $body): string
    {
        $sum = 0;

        for ($i = 0; $i < 17; $i++) {
            $sum += (int) $body[$i] * self::WEIGHTS[$i];
        }

        return self::CHECKSUM_MAP[$sum % 11];
    }

    protected function isRealBirthday(array $birthday): bool
    {
        $year = (int) ($birthday['year'] ?? 0);
        $month = (int) ($birthday['month'] ?? 0);
        $day = (int) ($birthday['day'] ?? 0);

        if ($year < 1900 || !checkdate($month, $day, $year)) {
            return false;
        }

        return sprintf('%04d%02d%02d', $year, $month, $day) <= date('Ymd');
    }

    protected function unknownValue(): string
    {
        return (string) $this->config['unknown_value'];
    }

    protected function returnUnitsDirectlyUnder(): bool
    {
        return (bool) $this->config['return_units_directly_under'];
    }

    protected function strictValidation(): bool
    {
        return (bool) $this->config['strict_validation'];
    }

    protected function support15(): bool
    {
        return (bool) $this->config['support_15'];
    }
}