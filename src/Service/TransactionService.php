<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\TransactionInformationDto;
use App\Entity\Transaction;
use App\Entity\Wallet;
use App\Enum\TransactionTypeEnum;
use App\Exception\NoPreviousTransactionsException;
use App\Exception\NoPreviousWalletException;
use App\Exception\WalletNotFoundWithinLimitException;
use App\Manager\TransactionManager;
use App\Repository\TransactionCategoryRepository;
use App\Repository\TransactionRepository;
use App\Repository\WalletRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final readonly class TransactionService
{
    public function __construct(private TransactionManager $transactionManager, private EntityManagerInterface $entityManager, private WalletService $walletService, private TransactionRepository $transactionRepository, private WalletRepository $walletRepository, private TransactionCategoryRepository $transactionCategoryRepository) {}

    /**
     * @return TransactionInformationDto Returns a data transfer object with transaction information by user for a given wallet
     */
    public function getAllTransactionInformationByUser(Wallet $wallet): TransactionInformationDto
    {
        return $this->transactionManager->getAllTransactionInformationByUser($wallet);
    }

    /**
     * @throws WalletNotFoundWithinLimitException
     */
    public function copyTransactionsFromPreviousMonth(Wallet $currentWallet, TransactionTypeEnum $transactionCategoryEnum): void
    {
        $previousWallet = $this->walletRepository->findPreviousWallet($currentWallet->getIndividual(), $currentWallet->getYear(), $currentWallet->getMonth());

        if (!$previousWallet instanceof Wallet) {
            throw new NoPreviousWalletException();
        }

        $transactionCategoryId = $this->transactionCategoryRepository->findIdByCategoryName($transactionCategoryEnum->getString());
        if (null === $transactionCategoryId) {
            throw new InvalidArgumentException(sprintf('No category found for %s', $transactionCategoryEnum->getString()));
        }

        $previousTransactions = $this->transactionRepository->findTransactionsByWalletAndCategory($previousWallet, $transactionCategoryId);

        if ([] === $previousTransactions) {
            throw new NoPreviousTransactionsException();
        }

        foreach ($previousTransactions as $transaction) {
            $newTransaction = new Transaction();
            $newTransaction->setAmount($transaction->getAmount());
            $newTransaction->setBudget($transaction->getBudget());
            $newTransaction->setDate(new DateTimeImmutable());
            $newTransaction->setWallet($currentWallet);
            $newTransaction->setTransactionCategory($transaction->getTransactionCategory());
            $newTransaction->setNature($transaction->getNature());

            foreach ($transaction->getTag() as $tag) {
                $newTransaction->addTag($tag);
            }

            $this->entityManager->persist($newTransaction);
        }

        $this->entityManager->flush();
    }

    /**
     * Copies the remaining budget from the previous month's wallet to the current wallet.
     *
     * @param Wallet $currentWallet the wallet for the current month
     *
     * @throws NoPreviousWalletException          if no wallet is found for the previous month
     * @throws WalletNotFoundWithinLimitException
     */
    public function copyLeftToSpendFromPreviousMonth(Wallet $currentWallet): void
    {
        $previousWallet = $this->walletService->findPreviousWallet($currentWallet->getIndividual(), $currentWallet->getYear(), $currentWallet->getMonth());

        if (!$previousWallet instanceof Wallet) {
            throw new NoPreviousWalletException();
        }

        $transactionInfoDto = $this->getAllTransactionInformationByUser($previousWallet);
        $totalLeftToSpend = $transactionInfoDto->getTotalLeftToSpend();

        $this->transactionManager->copyTransactionsFromPreviousMonth($currentWallet, $totalLeftToSpend);
    }

    /**
     * Handle tags associated with a given transaction.
     *
     * @param Transaction $transaction the transaction whose tags are to be handled
     */
    public function handleTransactionTags(Transaction $transaction): void
    {
        $this->transactionManager->handleTransactionTags($transaction);
    }

    public function calculateTotalBudget(Wallet $wallet): float
    {
        $transactions = $this->transactionRepository->findTransactionsByWallet($wallet);
        $totalBudget = 0.0;

        foreach ($transactions as $transaction) {
            if ($transaction->getBudget()) {
                $totalBudget += $transaction->getBudget();
            }
        }

        return $totalBudget;
    }

    /**
     * @return float the amount left after subtracting the budget
     */
    public function calculateLeftMinusBudget(Wallet $wallet): float
    {
        $transactionsDto = $this->transactionManager->getAllTransactionInformationByUser($wallet);

        return $transactionsDto->getLeftMinusBudget();
    }

    /**
     * Delete all transactions of a specific category for a given wallet.
     */
    public function deleteTransactionsByCategory(Wallet $wallet, string $category): bool
    {
        return $this->transactionManager->deleteTransactionsByCategory($wallet, $category);
    }
}
