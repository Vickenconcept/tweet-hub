<?php

namespace App\Services\WhatsApp;

class WhatsAppActionHints
{
    /**
     * Stack action commands vertically with icons (WhatsApp-friendly).
     *
     * @param  array<int, array{icon: string, cmd: string}|string>  $actions
     */
    public static function actions(string $title, array $actions): string
    {
        $lines = ['', $title];

        foreach ($actions as $action) {
            if (is_string($action)) {
                $lines[] = '▸ *'.$action.'*';

                continue;
            }

            $icon = $action['icon'] ?? '▸';
            $cmd = trim($action['cmd'] ?? '');
            if ($cmd === '') {
                continue;
            }

            $lines[] = $icon.' *'.$cmd.'*';
        }

        return implode("\n", $lines);
    }

    public static function afterIdeas(): string
    {
        return self::actions('➡️ *Next:*', [
            ['icon' => '📝', 'cmd' => 'post idea 1'],
            ['icon' => '🕐', 'cmd' => 'schedule idea 1 at 10pm'],
        ]);
    }

    public static function startMenu(): string
    {
        return self::actions('➡️ *Try:*', [
            ['icon' => '📬', 'cmd' => 'show my mentions'],
            ['icon' => '✍️', 'cmd' => 'post: Hello world!'],
            ['icon' => '📊', 'cmd' => 'status'],
            ['icon' => '❓', 'cmd' => 'help'],
        ]);
    }

    public static function greetingMenu(): string
    {
        return self::actions('➡️ *Try:*', [
            ['icon' => '📬', 'cmd' => 'show my mentions'],
            ['icon' => '📋', 'cmd' => 'show my queue'],
            ['icon' => '❓', 'cmd' => 'help'],
        ]);
    }

    public static function engageFromList(): string
    {
        return self::actions('➡️ *Actions:*', [
            ['icon' => '💬', 'cmd' => 'reply 1: your text'],
            ['icon' => '❤️', 'cmd' => 'like 1'],
            ['icon' => '🔁', 'cmd' => 'retweet 1'],
        ]);
    }

    public static function queueActions(): string
    {
        return self::actions('➡️ *Actions:*', [
            ['icon' => '🗑️', 'cmd' => 'delete queue 1'],
        ]);
    }

    public static function emptyQueue(): string
    {
        return self::actions('➡️ *Try:*', [
            ['icon' => '🕐', 'cmd' => 'schedule tomorrow 9am | your text'],
            ['icon' => '❓', 'cmd' => 'help'],
        ]);
    }

    public static function didntUnderstand(): string
    {
        return implode("\n", [
            '',
            "Sorry, I didn't get that.",
            '',
            'Type one of these words:',
            '❓ *help* — see what you can ask me',
            '⚡ *shortcuts* — see short phrases to use',
        ]);
    }

    public static function confirm(): string
    {
        return self::actions('➡️ *Next:*', [
            ['icon' => '✅', 'cmd' => 'confirm'],
        ]);
    }

    public static function help(): string
    {
        return self::actions('➡️ *Stuck?*', [
            ['icon' => '❓', 'cmd' => 'help'],
        ]);
    }

    public static function keywordActions(): string
    {
        return self::actions('➡️ *Actions:*', [
            ['icon' => '➕', 'cmd' => 'add keyword: yourbrand'],
            ['icon' => '➖', 'cmd' => 'remove keyword: oldterm'],
            ['icon' => '🔍', 'cmd' => 'search: keyword'],
        ]);
    }

    public static function autoPostsActions(): string
    {
        return self::actions('➡️ *Actions:*', [
            ['icon' => '▶️', 'cmd' => 'auto posts 1 on'],
            ['icon' => '⏸️', 'cmd' => 'auto posts 1 off'],
        ]);
    }

    public static function assetActions(): string
    {
        return self::actions('➡️ *Actions:*', [
            ['icon' => '✍️', 'cmd' => 'post: Hello [img:code]'],
            ['icon' => '🎨', 'cmd' => 'image: your description'],
        ]);
    }

    public static function imageActions(string $code): string
    {
        return self::actions('➡️ *Actions:*', [
            ['icon' => '✍️', 'cmd' => 'post: Hello [img:'.$code.']'],
            ['icon' => '🖼️', 'cmd' => 'assets'],
        ]);
    }
}
