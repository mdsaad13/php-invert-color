<?php

declare(strict_types=1);

namespace InvertColor;

use InvertColor\Exceptions\InvalidColorFormatException;
use InvertColor\Exceptions\InvalidRGBException;

use function count;
use function dechex;
use function is_int;
use function strlen;
use function str_pad;
use function preg_match;

use const STR_PAD_LEFT;

class Color
{
    /**
     * Threshold used to determinate if a color is bright or dark.
     *
     * The value comes from the following formula:
     * sqrt(1.05 * 0.05) - 0.05 = 0.17912878474779
     */
    public const LUMINANCE_THRESHOLD = 0.17912878474779;

    private const REGEX_BY_LENGTH = [
        3 => '/^([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/',
        4 => '/^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/',
        6 => '/^([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/',
        7 => '/^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/',
    ];

    /**
     * @var int[]
     */
    private $rgb;

    /**
     * @static
     *
     * @param string $hex
     *
     * @return self
     *
     * @throws InvalidColorFormatException
     */
    public static function fromHex(string $hex): self
    {
        return new self(self::hexToRGB($hex));
    }

    /**
     * @static
     *
     * @param int[] $rgb
     *
     * @return self
     *
     * @throws InvalidRGBException
     */
    public static function fromRGB(array $rgb): self
    {
        self::checkRGB($rgb);

        return new self($rgb);
    }

    /**
     * @param int[] $rgb
     */
    private function __construct(array $rgb)
    {
        $this->rgb = $rgb;
    }

    /**
     * @static
     *
     * @param string $hex
     *
     * @return int[]
     *
     * @throws InvalidColorFormatException
     */
    private static function hexToRGB(string $hex): array
    {
        $hexLength = strlen($hex);
        $isValid = ((bool)$regex = self::REGEX_BY_LENGTH[$hexLength] ?? '') && (bool)preg_match($regex, $hex, $match);
        if (!$isValid) {
            throw new InvalidColorFormatException($hex);
        }

        return $hexLength > 4 ? [
            (int)hexdec($match[1]),
            (int)hexdec($match[2]),
            (int)hexdec($match[3]),
        ] : [
            (int)hexdec($match[1].$match[1]),
            (int)hexdec($match[2].$match[2]),
            (int)hexdec($match[3].$match[3]),
        ];
    }

    /**
     * @static
     *
     * @param int[] $rgb
     *
     * @throws InvalidRGBException
     */
    private static function checkRGB(array $rgb): void
    {
        if (count($rgb) !== 3) {
            throw new InvalidRGBException('must contain 3 values exactly', $rgb);
        } elseif (!isset($rgb[0], $rgb[1], $rgb[2])) {
            throw new InvalidRGBException('indexes must be integers and start at 0', $rgb);
        } elseif (!is_int($rgb[0]) || !is_int($rgb[1]) || !is_int($rgb[2])) {
            throw new InvalidRGBException('values must be integers', $rgb);
        } elseif ($rgb[0] < 0 || $rgb[1] < 0 || $rgb[2] < 0) {
            throw new InvalidRGBException('values must be greater or equal to 0', $rgb);
        } elseif ($rgb[0] > 255 || $rgb[1] > 255 || $rgb[2] > 255) {
            throw new InvalidRGBException('values must be lesser or equal to 255', $rgb);
        }
    }

    /**
     * @return int[]
     */
    public function getRGB(): array
    {
        return $this->rgb;
    }

    /**
     * @return string
     */
    public function getHex(): string
    {
        return '#'.
            str_pad(dechex($this->rgb[0]), 2, '0', STR_PAD_LEFT).
            str_pad(dechex($this->rgb[1]), 2, '0', STR_PAD_LEFT).
            str_pad(dechex($this->rgb[2]), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param bool $bw
     *
     * @return string
     */
    public function invert(bool $bw = false): string
    {
        if ($bw) {
            return $this->isBright() ? '#000000' : '#ffffff';
        }

        return '#'.
            self::inv($this->rgb[0]).
            self::inv($this->rgb[1]).
            self::inv($this->rgb[2]);
    }

    /**
     * @param bool $bw
     *
     * @return array
     */
    public function invertAsRGB(bool $bw = false): array
    {
        if ($bw) {
            return $this->isBright() ? [0, 0, 0] : [255, 255, 255];
        }

        return [
            255 - $this->rgb[0],
            255 - $this->rgb[1],
            255 - $this->rgb[2],
        ];
    }

    /**
     * @param bool $bw
     *
     * @return self
     */
    public function invertAsObj(bool $bw = false): self
    {
        return new self($this->invertAsRGB($bw));
    }

    /**
     * @param int $channel
     *
     * @return string
     */
    private static function inv(int $channel): string
    {
        $inverted = dechex(255 - $channel);

        return str_pad($inverted, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return float
     *
     * @see https://www.w3.org/TR/WCAG20/relative-luminance.xml
     */
    public function getLuminance(): float
    {
        $levels = [];
        foreach ($this->rgb as $i => $channel) {
            $coef = $channel / 255;
            $levels[$i] = $coef <= 0.03928 ? $coef / 12.92 : (($coef + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $levels[0] + 0.7152 * $levels[1] + 0.0722 * $levels[2];
    }

    /**
     * @return bool
     */
    public function isBright(): bool
    {
        return $this->getLuminance() > self::LUMINANCE_THRESHOLD;
    }

    /**
     * @return bool
     */
    public function isDark(): bool
    {
        return $this->getLuminance() <= self::LUMINANCE_THRESHOLD;
    }
}
