<?php

namespace App\Domain\Notifications\Data;

final readonly class PushPayload
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url = '/',
        public ?string $tag = null,
    ) {}

    public function toJson(): string
    {
        $data = [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
        if ($this->tag !== null && $this->tag !== '') {
            $data['tag'] = $this->tag;
        }

        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
