<?php

namespace App\DataFixtures;

use App\Entity\Image;
use App\Entity\Offer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Finder\Finder;

class ImageFixtures extends Fixture implements DependentFixtureInterface
{
    public const REFERENCE_IDENTIFIER = 'image_';

    // Images URL pour les téléchargements
    private const IMAGE_URLS = [
        // Légumes et fruits
        'https://images.unsplash.com/photo-1540148426945-6cf22a6b2383?w=600&auto=format', // Légumes variés
        'https://images.unsplash.com/photo-1488459716781-31db52582fe9?w=600&auto=format', // Fruits colorés
        'https://images.unsplash.com/photo-1518843875459-f738682238a6?w=600&auto=format', // Panier légumes bio
        'https://images.unsplash.com/photo-1610832958506-aa56368176cf?w=600&auto=format', // Fraises
        
        // Produits laitiers
        'https://images.unsplash.com/photo-1628088062854-d1870b4553da?w=600&auto=format', // Fromages
        'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=600&auto=format', // Yaourts
        'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=600&auto=format', // Lait
        'https://images.unsplash.com/photo-1559598467-f8b76c8155d0?w=600&auto=format', // Beurre
        
        // Viandes et poissons
        'https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?w=600&auto=format', // Steaks
        'https://images.unsplash.com/photo-1595504591284-8041c2be7df8?w=600&auto=format', // Poisson
        'https://images.unsplash.com/photo-1599161146603-4f48f1aa8712?w=600&auto=format', // Poulet
        
        // Boulangerie et pâtisserie
        'https://images.unsplash.com/photo-1586444248902-2f64eddc13df?w=600&auto=format', // Pain artisanal
        'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=600&auto=format', // Croissants
        'https://images.unsplash.com/photo-1563729784474-d77dbb933a9e?w=600&auto=format', // Gâteau
        'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?w=600&auto=format', // Cookies
        
        // Boissons
        'https://images.unsplash.com/photo-1534353473418-4cfa6c56fd38?w=600&auto=format', // Café
        'https://images.unsplash.com/photo-1547825407-2d060104b7f8?w=600&auto=format', // Thé
        'https://images.unsplash.com/photo-1595981267035-7b04ca84a82d?w=600&auto=format', // Jus de fruits
        
        // Épicerie
        'https://images.unsplash.com/photo-1532635241-17e820acc59f?w=600&auto=format', // Épices
        'https://images.unsplash.com/photo-1620574387735-3624d75b2dbc?w=600&auto=format', // Miel
        'https://images.unsplash.com/photo-1471943311424-646960669fbc?w=600&auto=format', // Confiture
        'https://images.unsplash.com/photo-1614273867161-53d13db11ec8?w=600&auto=format', // Huile d'olive
        
        // Plats préparés
        'https://images.unsplash.com/photo-1594834749740-74b3f6764be4?w=600&auto=format', // Soupe
        'https://images.unsplash.com/photo-1547592180-85f173990554?w=600&auto=format', // Pasta
        'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format', // Salade
        'https://images.unsplash.com/photo-1455619452474-d2be8b1e70cd?w=600&auto=format', // Sandwich
        
        // Alimentation spéciale
        'https://images.unsplash.com/photo-1625944230945-1b7dd3b949ab?w=600&auto=format', // Sans gluten
        'https://images.unsplash.com/photo-1593854823310-7c29b3cb45bf?w=600&auto=format', // Vegan
        'https://images.unsplash.com/photo-1609525313344-4332a0a567e9?w=600&auto=format', // Légumineuses
        'https://images.unsplash.com/photo-1508061235804-9436948fcd43?w=600&auto=format', // Noix
    ];

    // Configuration du nombre d'images par offre
    private const IMAGES_CONFIG = [
        // Maintenir la compatibilité avec les offres existantes
        '1' => [0, 6], // index des images pour l'offre 1
        '2' => [8],    // index des images pour l'offre 2 
        '3' => [11],   // index des images pour l'offre 3
    ];

    public function load(ObjectManager $manager): void
    {
        $filesystem = new Filesystem();
        $imageCounter = 1;

        // Créer le dossier fixtures s'il n'existe pas
        $fixturesDir = __DIR__ . "/../../public/images/fixtures/";
        if (!is_dir($fixturesDir)) {
            $filesystem->mkdir($fixturesDir, 0755);
        }

        // Créer le dossier uploads s'il n'existe pas
        $uploadsDir = __DIR__ . "/../../public/images/uploads/";
        if (!is_dir($uploadsDir)) {
            $filesystem->mkdir($uploadsDir, 0755);
        }

        // Télécharger toutes les images
        $imageFiles = $this->downloadAllImages($filesystem, $fixturesDir);

        // Traiter les images pour les offres spécifiques d'abord
        foreach (self::IMAGES_CONFIG as $offerId => $imageIndexes) {
            foreach ($imageIndexes as $index) {
                if (isset($imageFiles[$index])) {
                    $this->createImage($manager, $filesystem, $offerId, $imageFiles[$index], $imageCounter, $fixturesDir, $uploadsDir);
                    $imageCounter++;
                }
            }
        }

        // Ensuite, pour chaque offre de 1 à 50, ajouter des images aléatoires s'il n'y en a pas déjà
        for ($offerId = 1; $offerId <= 50; $offerId++) {
            // Vérifier si cette offre existe
            try {
                $offer = $this->getReference(OfferFixtures::REFERENCE_IDENTIFIER . $offerId, Offer::class);
            } catch (\Exception $e) {
                // Si l'offre n'existe pas, passer à la suivante
                continue;
            }

            // Si l'offre n'a pas déjà des images spécifiées, en ajouter
            if (!isset(self::IMAGES_CONFIG[$offerId])) {
                // Décider aléatoirement du nombre d'images (1 à 3)
                $numImages = rand(1, 3);
                
                // Sélectionner des images aléatoires
                $selectedImages = $this->getRandomImages($imageFiles, $numImages);
                
                foreach ($selectedImages as $image) {
                    $this->createImage($manager, $filesystem, $offerId, $image, $imageCounter, $fixturesDir, $uploadsDir);
                    $imageCounter++;
                }
            }
        }

        $manager->flush();
    }

    /**
     * Télécharge toutes les images depuis les URLs
     */
    private function downloadAllImages(Filesystem $filesystem, string $fixturesDir): array
    {
        $imageFiles = [];
        foreach (self::IMAGE_URLS as $index => $url) {
            $filename = 'food_image_' . ($index + 1) . '.jpg';
            $filePath = $fixturesDir . $filename;
            
            // Vérifier si l'image existe déjà
            if (!file_exists($filePath)) {
                try {
                    // Télécharger l'image
                    $imageContent = @file_get_contents($url);
                    if ($imageContent !== false) {
                        file_put_contents($filePath, $imageContent);
                        echo "Image téléchargée: $filename\n";
                    } else {
                        echo "Échec du téléchargement: $url\n";
                        continue;
                    }
                } catch (\Exception $e) {
                    echo "Erreur lors du téléchargement de $url: " . $e->getMessage() . "\n";
                    continue;
                }
            }
            
            $imageFiles[$index] = $filename;
        }
        
        return $imageFiles;
    }

    /**
     * Crée une image et l'associe à une offre
     */
    private function createImage(ObjectManager $manager, Filesystem $filesystem, string $offerId, string $imageName, int &$imageCounter, string $fixturesDir, string $uploadsDir): void
    {
        $newImage = new Image();

        $src = $fixturesDir . $imageName;
        if (!file_exists($src)) {
            echo "Image non trouvée: $src\n";
            return;
        }

        $copyImgName = uniqid() . '_' . 'image.jpg';
        $copyPath = $uploadsDir . $copyImgName;
        
        try {
            $filesystem->copy($src, $copyPath, true);

            $newImage->setFile(new UploadedFile(
                $copyPath,
                $copyImgName,
                null,
                null,
                true
            ));
            
            $newImage->setOffer($this->getReference(OfferFixtures::REFERENCE_IDENTIFIER . $offerId, Offer::class));
            $newImage->setCreatedAt(new \DateTimeImmutable());
            
            $manager->persist($newImage);
            $this->addReference(self::REFERENCE_IDENTIFIER . $imageCounter, $newImage);
            
            echo "Image créée pour l'offre $offerId: $imageName\n";
        } catch (\Exception $e) {
            echo "Erreur lors de la copie de l'image {$imageName}: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Récupère un nombre aléatoire d'images parmi celles disponibles
     */
    private function getRandomImages(array $availableImages, int $count): array
    {
        $selectedImages = [];
        $keys = array_rand($availableImages, min($count, count($availableImages)));
        
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        
        foreach ($keys as $key) {
            $selectedImages[] = $availableImages[$key];
        }
        
        return $selectedImages;
    }

    public function getDependencies(): array
    {
        return [
            OfferFixtures::class,
        ];
    }
}
