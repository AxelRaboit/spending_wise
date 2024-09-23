<?php

declare(strict_types=1);

namespace App\Manager;

use App\Dto\TransactionInformationDto;
use App\Entity\Transaction;
use App\Entity\Wallet;
use App\Enum\TransactionTypeEnum;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

use function Symfony\Component\String\u;

final readonly class TransactionManager
{
    private const string TRANSACTIONS = 'transactions';

    private const string INCOMES_CATEGORY = 'Incomes';

    private const string EXPENSES_CATEGORY = 'Expenses';

    private const string BILLS_CATEGORY = 'Bills';

    private const string DEBTS_CATEGORY = 'Debts';

    public function __construct(
        private TransactionRepository $transactionRepository,
        private EntityManagerInterface $entityManager
    ) {}

    public function getAllTransactionInformationByUser(Wallet $wallet): TransactionInformationDto
    {
        $transactions = $this->transactionRepository->findTransactionsByWalletWithRelations($wallet);

        $groupedTransactions = [
            'Incomes' => ['type' => TransactionTypeEnum::INCOMES->getString(), self::TRANSACTIONS => [], 'total' => 0, 'totalBudget' => 0],
            'Bills' => ['type' => TransactionTypeEnum::BILLS->getString(), self::TRANSACTIONS => [], 'total' => 0, 'totalBudget' => 0],
            'Expenses' => ['type' => TransactionTypeEnum::EXPENSES->getString(), self::TRANSACTIONS => [], 'total' => 0, 'totalBudget' => 0],
            'Debts' => ['type' => TransactionTypeEnum::DEBTS->getString(), self::TRANSACTIONS => [], 'total' => 0, 'totalBudget' => 0],
        ];

        foreach ($transactions as $transaction) {
            if ($transaction instanceof Transaction) {
                $transactionCategory = $transaction->getTransactionCategory();
                $category = u($transactionCategory->getName())->lower()->title(true)->toString();

                $budgetInfo = $this->calculateBudgetVsActual($transaction);

                // Add transaction to the correct category
                $groupedTransactions[$category][self::TRANSACTIONS][] = [
                    'transaction' => $transaction,
                    'budgetInfo' => $budgetInfo,
                ];

                // Accumulate category total
                $groupedTransactions[$category]['total'] += $transaction->getAmount();

                // Accumulate category budget total
                if (null !== $transaction->getBudget()) {
                    $groupedTransactions[$category]['totalBudget'] += (float) $transaction->getBudget();
                }
            }
        }

        $totalIncomes = $groupedTransactions[self::INCOMES_CATEGORY]['total'];
        $totalBills = $groupedTransactions[self::BILLS_CATEGORY]['total'];
        $totalExpenses = $groupedTransactions[self::EXPENSES_CATEGORY]['total'];
        $totalDebts = $groupedTransactions[self::DEBTS_CATEGORY]['total'];

        // Get total spending and other calculations
        $totalSpending = $totalExpenses + $totalBills + $totalDebts;
        $totalIncomesAndStartingBalance = $totalIncomes + $wallet->getStartBalance();
        $totalLeftToSpend = $totalIncomesAndStartingBalance - $totalSpending;

        // Calculate total budgets
        $budgets = $this->calculateTotalBudget($groupedTransactions);
        $totalBudget = $totalIncomesAndStartingBalance - $budgets;

        // Include the total budgets for each category (Bills, Expenses, Debts)
        return new TransactionInformationDto(
            $groupedTransactions,
            $totalIncomesAndStartingBalance,
            $totalIncomes,
            $totalBills,
            $totalExpenses,
            $totalDebts,
            $totalLeftToSpend,
            $totalSpending,
            $totalBudget,
            $groupedTransactions[self::BILLS_CATEGORY]['totalBudget'],
            $groupedTransactions[self::EXPENSES_CATEGORY]['totalBudget'],
            $groupedTransactions[self::DEBTS_CATEGORY]['totalBudget']
        );
    }

    /**
     * Calculate the total budget for transactions.
     *
     * @param array<string, array{type: string, transactions: array<array{transaction: Transaction, budgetInfo: array<string, mixed>}>, total: float}> $transactions
     */
    public function calculateTotalBudget(array $transactions): float
    {
        $totalBudget = 0.0;
        foreach ($transactions as $categoryData) {
            foreach ($categoryData[self::TRANSACTIONS] as $transactionData) {
                /** @var Transaction $transaction */
                $transaction = $transactionData['transaction'];
                if ($transaction->getBudget()) {
                    $totalBudget += (float) $transaction->getBudget();
                }
            }
        }

        return $totalBudget;
    }

    public function findAndDeleteTransactionsByWallet(Wallet $wallet): void
    {
        $transactions = $this->transactionRepository->findTransactionsByWallet($wallet);
        foreach ($transactions as $transaction) {
            $this->entityManager->remove($transaction);
        }

        $this->entityManager->flush();
    }

    public function copyTransactionsFromPreviousMonth(Wallet $currentWallet, float $totalLeftToSpend): void
    {
        $currentWallet->setStartBalance($totalLeftToSpend);
        $this->entityManager->persist($currentWallet);
        $this->entityManager->flush();
    }

    public function handleTransactionTags(Transaction $transaction): void
    {
        foreach ($transaction->getTag() as $tag) {
            $transaction->addTag($tag);
        }

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();
    }

    /**
     * @return array{budget: float|null, remaining: float|null, overBudget: bool|null, percentageUsed: float|null}
     */
    public function calculateBudgetVsActual(Transaction $transaction): array
    {
        $budget = $transaction->getBudget();
        $actual = (float) $transaction->getAmount();

        if (null === $budget) {
            return [
                'budget' => null,
                'remaining' => null,
                'overBudget' => null,
                'percentageUsed' => null,
            ];
        }

        $budget = (float) $budget;

        $remaining = $budget - $actual;
        $overBudget = $actual > $budget;
        $percentageUsed = ($actual / $budget) * 100;

        return [
            'budget' => $budget,
            'remaining' => $remaining,
            'overBudget' => $overBudget,
            'percentageUsed' => $percentageUsed,
        ];
    }

    /**
     * Delete all transactions of a specific category for a given wallet.
     */
    public function deleteTransactionsByCategory(Wallet $wallet, string $category): bool
    {
        $transactions = $this->transactionRepository->findTransactionsByCategory($wallet, $category);
        if ([] === $transactions) {
            return false;
        }

        foreach ($transactions as $transaction) {
            $this->entityManager->remove($transaction);
        }

        $this->entityManager->flush();

        return true;
    }
}
