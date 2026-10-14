<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyAccountsPlugin\Member;

use Sylius\Component\Core\Model\OrderInterface;

final readonly class CompositeCompanyOrderEligibilityChecker implements CompanyOrderEligibilityCheckerInterface
{
    /** @param iterable<CompanyOrderEligibilityCheckerInterface> $checkers */
    public function __construct(private iterable $checkers)
    {
    }

    public function isEligible(OrderInterface $order): bool
    {
        foreach ($this->checkers as $checker) {
            if ($checker->isEligible($order)) {
                return true;
            }
        }

        return false;
    }
}
