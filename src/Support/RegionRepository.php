<?php

declare(strict_types=1);

namespace Youvision\ThinkIdcard\Support;

use RuntimeException;

class RegionRepository
{
    protected string $path;

    protected ?array $data = null;

    protected ?array $history = null;

    protected ?array $diff = null;

    public function __construct(?string $path = null)
    {
        $this->path = $path !== null && $path !== ''
            ? rtrim($path, '/\\')
            : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data';
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getData(): array
    {
        if ($this->data === null) {
            $this->data = $this->load('data.json');
        }

        return $this->data;
    }

    public function getHistory(): array
    {
        if ($this->history === null) {
            $this->history = $this->load('history.json');
        }

        return $this->history;
    }

    public function getDiff(): array
    {
        if ($this->diff === null) {
            $this->diff = $this->load('diff.json');
        }

        return $this->diff;
    }

    public function has(string $code): bool
    {
        return $this->resolve($code, true) !== null;
    }

    public function findAddress(string $code, bool $returnUnitsDirectlyUnder, string $unknown): array
    {
        $address = $this->resolve($code, $returnUnitsDirectlyUnder);

        if ($address === null) {
            return [
                'province' => $unknown,
                'city' => $unknown,
                'area' => $unknown,
            ];
        }

        return [
            'province' => $address['province'] !== '' ? $address['province'] : $unknown,
            'city' => $address['city'],
            'area' => $address['area'] !== '' ? $address['area'] : $unknown,
        ];
    }

    protected function resolve(string $code, bool $returnUnitsDirectlyUnder, array $visited = []): ?array
    {
        if (!preg_match('/^\d{6}$/', $code) || substr($code, 4, 2) === '00' || isset($visited[$code])) {
            return null;
        }

        $visited[$code] = true;

        $data = $this->getData();

        if (isset($data[$code])) {
            return $this->build($data, $code, $returnUnitsDirectlyUnder);
        }

        $diff = $this->getDiff();

        if (isset($diff[$code]) && count($diff[$code]) === 1) {
            return $this->resolve((string) $diff[$code][0], $returnUnitsDirectlyUnder, $visited);
        }

        $history = $this->getHistory();

        if (isset($history[$code])) {
            return $this->build($history, $code, $returnUnitsDirectlyUnder);
        }

        return null;
    }

    protected function build(array $source, string $code, bool $returnUnitsDirectlyUnder): array
    {
        return [
            'province' => $source[substr($code, 0, 2) . '0000'] ?? '',
            'city' => $source[substr($code, 0, 4) . '00']
                ?? ($returnUnitsDirectlyUnder ? '市辖区' : ''),
            'area' => $source[$code] ?? '',
        ];
    }

    protected function load(string $file): array
    {
        $path = $this->path . DIRECTORY_SEPARATOR . $file;

        if (!is_file($path)) {
            throw new RuntimeException('身份证行政区划数据文件不存在：' . $path);
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('身份证行政区划数据文件读取失败：' . $path);
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new RuntimeException('身份证行政区划数据格式错误：' . $path);
        }

        return $data;
    }
}