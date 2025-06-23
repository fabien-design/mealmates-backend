<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\User;
use App\Enums\TransactionStatus;
use App\Repository\TransactionRepository;
use App\Service\Notification\TransactionNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class QrCodeService
{
    private const DEFAULT_QR_CODE_EXPIRY_MINUTES = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $parameterBag,
        private TokenStorageInterface $tokenStorage,
        private TransactionNotificationService $notificationService,
        private readonly TransactionRepository $transactionRepository
    ) {
    }

    public function generateQrCode(Transaction $transaction): string
    {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        if ($user->getId() !== $transaction->getBuyer()->getId()) {
            throw new \Exception('Seul l\'acheteur peut générer un QR code pour cette transaction.');
        }

        if ($transaction->isFree()) {
            if (!$transaction->isConfirmed()) {
                throw new \Exception('La réservation doit être confirmée par le vendeur avant de générer le QR code.');
            }
        } else {
            if (!$transaction->isPending()) {
                if ($transaction->isConfirmed()) {
                    throw new \Exception('Le paiement doit être effectué avant de générer le QR code.');
                } else {
                    throw new \Exception('La réservation doit être confirmée et payée avant de générer le QR code.');
                }
            }
        }

        if ($transaction->getQrCodeToken()) {
            $this->revokeQrCode($transaction);
        }

        $token = $this->generateSecureToken();

        $expiryMinutes = $this->parameterBag->get('app.qr_code_expiry_minutes') ?? self::DEFAULT_QR_CODE_EXPIRY_MINUTES;
        $expiryDate = new \DateTimeImmutable("+{$expiryMinutes} minutes");

        $transaction->setQrCodeToken($token);
        $transaction->setQrCodeExpiresAt($expiryDate);
        
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $token;
    }

    public function verifyQrCode(string $token): Transaction
    {
        /** @var Transaction|null $transaction */
        $transaction = $this->transactionRepository->findOneBy(['qrCodeToken' => $token]);
        
        if (!$transaction) {
            throw new \Exception('QR code invalide.');
        }
        
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        if ($user->getId() !== $transaction->getSeller()->getId()) {
            throw new \Exception('Seul le vendeur peut scanner ce QR code.');
        }

        if ($transaction->isQrCodeExpired()) {
            throw new \Exception('Ce QR code a expiré. Demandez à l\'acheteur de générer un nouveau QR code.');
        }

        if ($transaction->isFree()) {
            if (!$transaction->isConfirmed()) {
                throw new \Exception('Cette transaction n\'est pas dans un état valide pour être finalisée.');
            }
        } else {
            if (!$transaction->isPending()) {
                throw new \Exception('Le paiement n\'a pas été effectué pour cette transaction.');
            }
        }

        $this->notificationService->notifyQrCodeValidation($transaction);
        
        return $transaction;
    }

    public function completeTransactionByQrCode(Transaction $transaction): Transaction
    {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        if ($user->getId() !== $transaction->getSeller()->getId()) {
            throw new \Exception('Seul le vendeur peut finaliser cette transaction.');
        }

        $offer = $transaction->getOffer();
        if ($transaction->isFree()) {
            if (!$transaction->isConfirmed()) {
                throw new \Exception('Cette transaction gratuite ne peut pas être finalisée.');
            }
        } else {
            if (!$transaction->isPending()) {
                throw new \Exception('Cette transaction payante ne peut pas être finalisée car le paiement n\'a pas été effectué.');
            }
        }

        if ($transaction->isQrCodeExpired()) {
            throw new \Exception('Le QR code a expiré.');
        }

        $transaction->setStatus(TransactionStatus::COMPLETED);
        $transaction->setTransferredAt(new \DateTimeImmutable());

        $transaction->setQrCodeToken(null);
        $transaction->setQrCodeExpiresAt(null);

        $offer->setSoldAt(new \DateTime());
        
        $this->entityManager->persist($transaction);
        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        $this->notificationService->notifyTransactionCompleted($transaction);

        return $transaction;
    }

    public function canGenerateQrCode(Transaction $transaction): array
    {
        $canGenerate = true;
        $reason = '';
        
        if ($transaction->isFree()) {
            if (!$transaction->isConfirmed()) {
                $canGenerate = false;
                $reason = 'La réservation doit être confirmée par le vendeur.';
            }
        } else {
            if ($transaction->isConfirmed() && !$transaction->isPending()) {
                $canGenerate = false;
                $reason = 'Le paiement doit être effectué avant de générer le QR code.';
            } elseif (!$transaction->isConfirmed()) {
                $canGenerate = false;
                $reason = 'La réservation doit être confirmée par le vendeur.';
            }
        }

        if ($transaction->isCompleted()) {
            $canGenerate = false;
            $reason = 'Cette transaction est déjà finalisée.';
        }

        return [
            'canGenerate' => $canGenerate,
            'reason' => $reason
        ];
    }

    /**
     * Révoque un QR code existant -- claude.ai
     */
    public function revokeQrCode(Transaction $transaction): void
    {
        $transaction->setQrCodeToken(null);
        $transaction->setQrCodeExpiresAt(null);
        
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();
    }

    /**
     * Génère un token sécurisé pour le QR code -- claude.ai
     */
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32)) . '-' . time();
    }

    /**
     * Nettoie les QR codes expirés (méthode utilitaire) -- claude.ai
     */
    public function cleanupExpiredQrCodes(): int
    {        
        $expiredTransactions = $this->transactionRepository->findExpiredQrCodes();

        $count = 0;
        foreach ($expiredTransactions as $transaction) {
            $transaction->setQrCodeToken(null);
            $transaction->setQrCodeExpiresAt(null);
            $this->entityManager->persist($transaction);
            $count++;
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }
}
