<?php
declare(strict_types=1);

final class Branding
{
    public const DEFAULTS = [
        'shop_name' => 'Sports Galaxy', 'shop_logo' => null,
        'accent_color' => '#c7f548', 'background_color' => '#f6f5ef', 'text_color' => '#0b1821',
        'secondary_color' => '#8f7aff', 'heading_font' => 'classic', 'card_style' => 'rounded', 'catalogue_columns' => '4',
        'announcement_text' => 'Free express delivery above ₹999 · Use GALAXY10 for 10% off',
        'hero_title' => 'Find your game. Own your orbit.',
        'hero_description' => 'Thoughtfully selected sport essentials, delivered with the kind of speed that keeps your momentum moving.',
        'hero_button_text' => 'Shop the collection',
        'footer_description' => 'Curated gear for the way you play. Built for every first serve, final sprint, and weekend win.',
        'support_email' => '', 'support_phone' => '',
        'show_benefits' => '1', 'show_story' => '1', 'show_quote' => '1',
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
        foreach (['accent_color', 'background_color', 'text_color', 'secondary_color'] as $key) {
            if (!is_string($settings[$key] ?? null) || preg_match('/^#[a-fA-F0-9]{6}$/D', $settings[$key]) !== 1) {
                throw new RuntimeException('Choose valid colours using the colour fields.');
            }
            $result[$key] = strtolower($settings[$key]);
        }
        if (self::contrast($result['text_color'], $result['background_color']) < 4.5 || self::contrast($result['text_color'], '#ffffff') < 4.5) {
            throw new RuntimeException('Choose a darker text colour and a lighter page background so text stays readable.');
        }
        foreach (['announcement_text' => [0, 160], 'hero_title' => [2, 120], 'hero_description' => [2, 300], 'hero_button_text' => [2, 40], 'footer_description' => [0, 300], 'support_email' => [0, 190], 'support_phone' => [0, 30]] as $key => [$min, $max]) {
            $value = trim((string) ($settings[$key] ?? self::DEFAULTS[$key]));
            if (mb_strlen($value) < $min || mb_strlen($value) > $max) throw new RuntimeException('Check the length of ' . str_replace('_', ' ', $key) . '.');
            $result[$key] = $value;
        }
        if ($result['support_email'] !== '' && !filter_var($result['support_email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid support email.');
        if ($result['support_phone'] !== '' && preg_match('/^\+?[0-9 ()-]{7,30}$/D', $result['support_phone']) !== 1) throw new RuntimeException('Enter a valid support phone number.');
        foreach (['heading_font' => ['classic', 'modern', 'rounded'], 'card_style' => ['rounded', 'square'], 'catalogue_columns' => ['3', '4'], 'show_benefits' => ['0', '1'], 'show_story' => ['0', '1'], 'show_quote' => ['0', '1']] as $key => $allowed) {
            $value = (string) ($settings[$key] ?? self::DEFAULTS[$key]);
            if (!in_array($value, $allowed, true)) throw new RuntimeException('Choose a valid ' . str_replace('_', ' ', $key) . '.');
            $result[$key] = $value;
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
            '--on-accent' => $onAccent, '--violet' => $settings['secondary_color'] ?? self::DEFAULTS['secondary_color'],
            '--display' => match ($settings['heading_font'] ?? 'classic') { 'modern' => 'Arial, Helvetica, sans-serif', 'rounded' => 'Verdana, Geneva, sans-serif', default => 'Georgia, serif' }];
    }
}
