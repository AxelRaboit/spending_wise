<?php

declare(strict_types=1);

namespace App\Security\Voter\Transaction;

use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\Transaction\TransactionRepository;
use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Transaction|null>
 */
class TransactionVoter extends Voter
{
    public const string ACCESS_TRANSACTION = 'ACCESS_TRANSACTION';

    public function __construct(private readonly TransactionRepository $transactionRepository) {}

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ACCESS_TRANSACTION === $attribute && $subject instanceof Transaction;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Transaction|null $transaction */
        $transaction = $subject;

        return match ($attribute) {
            self::ACCESS_TRANSACTION => $transaction instanceof Transaction && $this->canAccessTransaction($user, $transaction),
            default => false,
        };
    }

    private function canAccessTransaction(User $user, Transaction $transaction): bool
    {
        /** @var int $transactionId */
        $transactionId = $transaction->getId();

        $userTransaction = $this->transactionRepository->findSpecificTransactionByUser($user, $transactionId);

        return $userTransaction instanceof Transaction;
    }
}
