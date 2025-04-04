<?php

namespace App\DataFixtures;

use App\Entity\Image;
use App\Entity\Offer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageFixtures extends Fixture implements DependentFixtureInterface
{
    public const REFERENCE_IDENTIFIER = 'image_';

    private const IMAGES = [
        [
            'identifier' => '1',
            'image' => 'pain.jpg',
            'offer' => '3',
        ],
        [
            'identifier' => '2',
            'image' => 'brique-lait-vegetal.jpg',
            'offer' => '1',
        ],
        [
            'identifier' => '3',
            'image' => 'steak.jpg',
            'offer' => '2',
        ],
        [
            'identifier' => '4',
            'image' => 'brique-lait-2.png',
            'offer' => '1',
        ]
    ];


    public function load(ObjectManager $manager): void
    {
        $filesystem = new Filesystem();

        for ($i = 0; $i < count(self::IMAGES); $i++) {
            $newImage = new Image();

            $src = "public/images/fixtures/" . self::IMAGES[$i]['image'];

            $copyPath = __DIR__ . "/../../public/images/uploads/";
            $copyImgName =  uniqid() . '_' . 'image.jpg';
            $copyPath .= $copyImgName;
            $filesystem->copy($src, $copyPath);

            $newImage->setFile(new UploadedFile(
                $copyPath,
                $copyImgName,
                null,
                null,
                true
            ));
            $newImage->setOffer($this->getReference(OfferFixtures::REFERENCE_IDENTIFIER . self::IMAGES[$i]['offer'], Offer::class));
            $newImage->setCreatedAt(new \DateTimeImmutable());
            $manager->persist($newImage);
            $this->addReference(self::REFERENCE_IDENTIFIER . self::IMAGES[$i]['identifier'], $newImage);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            OfferFixtures::class,
        ];
    }
}
