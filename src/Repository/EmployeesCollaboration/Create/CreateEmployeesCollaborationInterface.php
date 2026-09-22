<?php

declare(strict_types=1);

namespace App\Repository\EmployeesCollaboration\Create;

interface CreateEmployeesCollaborationInterface
{
    /**
     * @param list<int|string|null> $assignments Flat SQL parameters in repeating groups of four:
     *                                           employee ID (int), project ID (int),
     *                                           start date (Y-m-d string), end date (Y-m-d string or null).
     *                                           The number of values must be a multiple of four.
     *                                           An empty list is allowed and performs no insert.
     */
    public function insertBatch(array $assignments): void;
}
