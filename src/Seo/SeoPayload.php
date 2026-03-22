<?php

namespace App\Seo;

final class SeoPayload
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonical,
        public readonly string $robots,
        /** @var array{title:string,description:string,url:string,type:string,image:?string} */
        public readonly array $og,
        /** @var array<int, array<string,mixed>> */
        public readonly array $jsonLd,
    ) {}
}
