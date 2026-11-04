<?php

namespace App\Tests\Entity;

use App\Entity\Dish;
use PHPUnit\Framework\TestCase;

class DishTest extends TestCase
{
    public function testDefaults(): void
    {
        $dish = new Dish();

        $this->assertSame(0, $dish->getPosition());
        $this->assertInstanceOf(\DateTimeImmutable::class, $dish->getCreatedAt());
    }

    public function testNullPositionFallsBackToZero(): void
    {
        $dish = (new Dish())->setPosition(null);

        $this->assertSame(0, $dish->getPosition());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideFiles')]
    public function testVideoDetection(string $filename, bool $isVideo, string $mime): void
    {
        $dish = (new Dish())->setFilename($filename);

        $this->assertSame($isVideo, $dish->isVideo());
        if ($isVideo) {
            $this->assertSame($mime, $dish->getVideoMimeType());
        }
    }

    public static function provideFiles(): \Generator
    {
        yield 'jpg' => ['manti-123.jpg', false, ''];
        yield 'webp' => ['baklava-123.WEBP', false, ''];
        yield 'mp4' => ['recette-123.mp4', true, 'video/mp4'];
        yield 'webm' => ['recette-123.webm', true, 'video/webm'];
        yield 'ogg' => ['recette-123.ogg', true, 'video/ogg'];
        yield 'mov as mp4' => ['iphone-123.MOV', true, 'video/mp4'];
    }
}
