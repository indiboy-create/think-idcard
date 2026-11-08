# youvision/think-idcard

ThinkPHP 8 身份证号码解析扩展。

内置全国省市区行政区划数据，可根据身份证号码解析户籍所在省、市、区县，以及性别、出生日期、年龄，并支持号码格式与校验位验证。适用于实名认证、会员资料补全、订单收货信息校验等场景。

## 特性

- 身份证解析：一次返回省、市、区县、性别、生日、年龄等结构化信息
- 号码校验：格式校验、GB 11643 校验位校验、出生日期与行政区划独立校验
- 历史区划兼容：内置现行数据、历史数据与区划变更映射，可解析已撤销或合并的区划代码
- 15 位兼容：15 位老身份证自动补全为 18 位后解析
- ThinkPHP 8 集成：服务自动注册、门面调用、依赖注入、配置自动合并与发布
- 自包含数据：区划数据随包分发，不依赖数据库，安装即用
- 灵活配置：未知值、严格校验模式、市辖区返回策略、自定义数据目录均可配置

## 环境要求

- PHP >= 8.0
- ThinkPHP 8

## 安装

```bash
composer require youvision/think-idcard
```

服务通过 Composer 的 `extra.think.services` 声明。ThinkPHP 8 项目通常在 `composer.json` 的 `post-autoload-dump` 中执行 `service:discover` 与 `vendor:publish`，安装后会自动完成服务注册与配置发布；若项目未配置该脚本，手动执行：

```bash
php think service:discover
php think vendor:publish
```

## 配置

包内默认配置会在服务注册时自动合并，无需发布即可使用。需要修改默认值时执行：

```bash
php think vendor:publish
```

该命令会将 `config/idcard.php` 发布到项目 `config` 目录；目标文件已存在时不会覆盖，可追加 `-f` 强制覆盖。也可以手动复制包内配置文件。

| 配置项 | 类型 | 默认值 | 说明 |
| :--- | :--- | :--- | :--- |
| `unknown_value` | `string` | `'未知'` | 号码无效或信息无法解析时的填充值，可在调用方法时临时覆盖 |
| `return_units_directly_under` | `bool` | `true` | 直辖市市级代码不存在时是否将 `city` 填充为“市辖区”，关闭后返回空字符串 |
| `strict_validation` | `bool` | `false` | 是否启用严格校验，开启后 `valid` / `isValid()` 额外要求出生日期真实存在且行政区划可匹配 |
| `support_15` | `bool` | `true` | 是否支持 15 位身份证，开启后自动补全为 18 位再解析 |
| `data_path` | `string` | `''` | 自定义行政区划数据目录，留空使用包内 `src/data`；目录需包含 `data.json`、`history.json`、`diff.json` |

> 所有配置项均在服务注册时合并，并已通过解析、性别、生日、年龄、严格校验与自定义数据目录等场景验证生效。

## 使用

### 门面调用

```php
use Youvision\ThinkIdcard\Facade\Idcard;

// 综合解析
$info = Idcard::parse('110101199601249935');

// 仅获取省市区
$address = Idcard::getAddressInfo('110101199601249935');

// 其他快捷方法
$gender = Idcard::getGender('110101199601249935');   // 男
$birthday = Idcard::getBirthday('110101199601249935'); // ['year' => '1996', 'month' => '01', 'day' => '24']
$age = Idcard::getAge('110101199601249935');          // 按当天计算的周岁
$valid = Idcard::isValid('110101199601249935');       // true
$idNumber = Idcard::normalize('110101960124993');     // 110101199601249935
```

### 依赖注入

```php
use Youvision\ThinkIdcard\Idcard;

class Member
{
    public function __construct(protected Idcard $idcard)
    {
    }

    public function profile(string $idNumber): array
    {
        return $this->idcard->parse($idNumber);
    }
}
```

### 解析结果

`parse()` 返回的字段如下：

```php
[
    'id_number'      => '110101199601249935', // 规范化后的 18 位号码
    'valid'          => true,                  // 综合校验结果
    'checksum_valid' => true,                  // 校验位是否正确
    'birthday_valid' => true,                  // 出生日期是否真实存在
    'region_valid'   => true,                  // 前 6 位行政区划是否可匹配
    'region_code'    => '110101',
    'province'       => '北京市',
    'city'           => '市辖区',
    'area'           => '东城区',
    'birthday'       => '1996-01-24',
    'year'           => '1996',
    'month'          => '01',
    'day'            => '24',
    'age'            => 30,                    // 按当天计算的周岁，生日非法时为 null
    'gender'         => '男',
    'gender_code'    => 1,                     // 1 男，0 女，号码无效时为 null
]
```

号码格式无效时不会抛出异常，`province`、`city`、`area`、`gender` 使用配置的未知值填充，`birthday` 为空字符串，`age` 与 `gender_code` 为 `null`。

### 校验说明

`isValid()` 默认只校验号码格式与校验位；15 位号码没有校验位，因此校验格式与出生日期。开启 `strict_validation` 或在调用时传入 `$strict = true` 后，还会校验出生日期是否真实存在、前 6 位行政区划是否能在现行或历史数据中匹配：

```php
Idcard::isValid('110101199601249935');        // 默认校验
Idcard::isValid('110101199601249935', true);  // 严格校验
```

`parse()` 返回的 `birthday_valid`、`region_valid` 始终独立计算，便于自行决定业务校验规则。

### 自定义行政区划数据

`data_path` 指向的目录需包含 `data.json` 文件，格式与包内数据一致：

- `data.json`：现行区划，键为 6 位行政区划代码，值为名称
