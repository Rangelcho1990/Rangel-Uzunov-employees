<?php

declare(strict_types=1);

namespace App\Repository\EmployeesCollaboration\Create;

interface CreateEmployeesCollaborationInterface
{
    /**
     * @param list<int|string> $assignments Flat SQL parameters in repeating groups of five:
     *                                      employee ID (int), project ID (int), days worked (int),
     *                                      start date (Y-m-d string), end date (Y-m-d string).
     *                                      The number of values must be a multiple of five.
     *                                      An empty list is allowed and performs no insert.
     */
    public function insertBatch(array $assignments): void;
}
