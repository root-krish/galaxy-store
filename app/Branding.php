<?php
declare(strict_types=1);

final class Branding
{
    public const DEFAULTS = [
        'shop_name' => 'Sports Galaxy', 'shop_logo' => null,
        'accent_color' => '#c7f548', 'background_color' => '#f6f5ef', 'text_color' => '#0b1821',
    ];

    public static function validate(array $settings): array
    {
        $name = trim((string) ($settings['shop_name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            throw new RuntimeException('Enter a shop name between 2 and 60 characters.');
        }
        $result = ['shop_name' => $name, 'shop_logo' => $settings['shop_logo'] ?? null];
        if ($result['shop_logo'] !== null && (!is_string($result['shop_logo']) || !ImageUploads::isValidName($result['shop_logo']))) {
            throw new RuntimeException('Choose a valid uploaded shop logo.');
        }
        foreach (['accent_color', 'background_color', 'text_color'] as $key) {
            if (!is_string($settings[$key] ?? null) || preg_match('/^#[a-fA-F0-9]{6}$/D', $settings[$key]) !== 1) {
                throw new RuntimeException('Choose valid colours using the colour fields.');
            }
            $result[$key] = strtolower($settings[$key]);
        }
        if (self::contrast($result['text_color'], $result['background_color']) < 4.5 || self::contrast($result['text_color'], '#ffffff') < 4.5) {
            throw new RuntimeException('Choose a darker text colour and a lighter page background so text stays readable.');
        }
        return $result;
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(static function (string $channel): float {
            $value = hexdec($channel) / 255;
            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));
        return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
    }

    private static function contrast(string $a, string $b): float
    {
        $first = self::luminance($a); $second = self::luminance($b);
        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    private static function mix(string $hex, string $target, float $weight): string
    {
        $first = str_split(substr($hex, 1), 2); $second = str_split(substr($target, 1), 2);
        $result = '#';
        foreach ($first as $index => $channel) {
            $result .= str_pad(dechex((int) round(hexdec($channel) * (1 - $weight) + hexdec($second[$index]) * $weight)), 2, '0', STR_PAD_LEFT);
        }
        return $result;
    }

    public static function cssVariables(array $settings): array
    {
        $accent = $settings['accent_color']; $background = $settings['background_color']; $ink = $settings['text_color'];
        $link = $accent;
        for ($step = 1; self::contrast($link, $background) < 4.5 && $step <= 20; $step++) {
            $link = self::mix($accent, $ink, $step / 20);
        }
        $onAccent = self::contrast($accent, '#ffffff') > self::contrast($accent, '#0b1821') ? '#ffffff' : '#0b1821';
        return ['--lime' => $accent, '--lime-deep' => $link, '--paper' => $background, '--ink' => $ink,
            '--cream' => self::mix($background, $ink, 0.04), '--ink-soft' => self::mix($ink, '#ffffff', 0.15),
            '--on-accent' => $onAccent];
    }
}
