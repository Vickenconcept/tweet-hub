<?php

namespace App\Services\WhatsApp;

class WhatsAppOutboundMessage
{
    /**
     * @param  array<int, array{url: string, caption?: string}>  $images
     */
    public function __construct(
        public string $text = '',
        public array $images = [],
    ) {}

    public static function text(string $text): self
    {
        return new self(text: $text);
    }

    /**
     * @param  array<int, array{url: string, caption?: string}>  $images
     */
    public static function withImages(string $text, array $images): self
    {
        return new self(text: $text, images: $images);
    }

    public static function wrap(string|self $message): self
    {
        return is_string($message) ? new self(text: $message) : $message;
    }

    public function append(string|self $message): self
    {
        $other = self::wrap($message);

        return new self(
            text: trim($this->text."\n\n".$other->text),
            images: array_merge($this->images, $other->images),
        );
    }

    /**
     * @return array{text: string, images: array<int, array{url: string, caption?: string}>}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'images' => $this->images,
        ];
    }

    /**
     * @param  array{text?: string, images?: array<int, array{url: string, caption?: string}>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            text: (string) ($data['text'] ?? ''),
            images: is_array($data['images'] ?? null) ? $data['images'] : [],
        );
    }

    /**
     * @param  mixed  $cached
     */
    public static function fromCached(mixed $cached): ?self
    {
        if ($cached instanceof self) {
            return $cached;
        }

        if (is_string($cached) && $cached !== '') {
            return new self(text: $cached);
        }

        if (is_array($cached)) {
            return self::fromArray($cached);
        }

        return null;
    }
}
