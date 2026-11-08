<?php

declare(strict_types=1);

namespace Youvision\ThinkIdcard\Facade;

use think\Facade;

/**
 * @see \Youvision\ThinkIdcard\Idcard
 * @method static array parse(string $idNumber)
 * @method static array getAddressInfo(string $idNumber, ?bool $returnUnitsDirectlyUnder = null, ?string $unknown = null)
 * @method static string getGender(string $idNumber)
 * @method static array getBirthday(string $idNumber)
 * @method static int|null getAge(string $idNumber, ?string $date = null)
 * @method static bool isValid(string $idNumber, ?bool $strict = null)
 * @method static string normalize(string $idNumber)
 * @method static array getConfig()
 * @method static \Youvision\ThinkIdcard\Support\RegionRepository getRegions()
 */
class Idcard extends Facade
{
    protected static function getFacadeClass()
    {
        return 'idcard';
    }
}