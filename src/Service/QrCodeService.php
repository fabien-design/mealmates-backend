<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Enums\TransactionStatus;
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
        private TransactionNotificationService $notificationService
    ) {
    }

    public function generateQrCode(Transaction $transaction): string
    {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        if ($user->getId() !== $transaction->getBuyer()->getId()) {
            throw new \Exception('Vous n\'êtes pas autorisé à générer un QR code pour cette transaction.');
        }

        if (!$transaction->isReserved() && !$transaction->isPending()) {
            throw new \Exception('Cette transaction ne peut pas avoir de QR code généré.');
        }
        // Générer un token aléatoire pour le QR code
        $token = bin2hex(random_bytes(32));

        $expiryMinutes = $this->parameterBag->get('app.qr_code_expiry_minutes') ?? self::DEFAULT_QR_CODE_EXPIRY_MINUTES;
        $expiryDate = new \DateTimeImmutable("+{$expiryMinutes} minutes");

        $transaction->setQrCodeToken($token);
        $transaction->setQrCodeExpiresAt($expiryDate);
        
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $token;
    }

    public function verifyQrCode(string $token): ?Transaction
    {
        $repository = $this->entityManager->getRepository(Transaction::class);
        $transaction = $repository->findOneBy(['qrCodeToken' => $token]);
        
        if (!$transaction) {
            throw new \Exception('QR code invalide.');
        }
        
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        if ($user->getId() !== $transaction->getSeller()->getId()) {
            throw new \Exception('Vous n\'êtes pas autorisé à valider ce QR code.');
        }

        if ($transaction->isQrCodeExpired()) {
            throw new \Exception('Ce QR code a expiré.');
        }

        $this->notificationService->notifyQrCodeValidation($transaction);
        
        return $transaction;
    }

    public function completeTransactionByQrCode(Transaction $transaction): Transaction
    {
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUser();
        if ($user->getId() !== $transaction->getSeller()->getId()) {
            throw new \Exception('Vous n\'êtes pas autorisé à compléter cette transaction.');
        }

        if (!$transaction->isReserved() && !$transaction->isPending()) {
            throw new \Exception('Cette transaction ne peut pas être complétée.');
        }

        $transaction->setStatus(TransactionStatus::COMPLETED);
        $transaction->setTransferredAt(new \DateTimeImmutable());

        $transaction->setQrCodeToken(null);
        $transaction->setQrCodeExpiresAt(null);

        $offer = $transaction->getOffer();
        $offer->setSoldAt(new \DateTime());
        
        $this->entityManager->persist($transaction);
        $this->entityManager->persist($offer);
        $this->entityManager->flush();

        $this->notificationService->notifyTransactionCompleted($transaction);

        return $transaction;
    }
}