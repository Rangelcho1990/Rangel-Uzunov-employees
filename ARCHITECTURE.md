# Project architecture

This document describes the implementation in this repository as of 22 September 2026. It is intended for developers installing, reviewing, testing, or extending the application.

## 1. Purpose and boundaries

The application imports employee project assignments from CSV into the `sirma` database. It identifies the pair of employees with the greatest combined number of overlapping days on shared projects, and displays that pair with a per-project breakdown.

An assignment describes time on a project, not an employee's entire tenure at the company. Two equal assignment durations do not imply that the employees worked together: their date ranges must intersect.

The application provides a server-rendered page and file upload. It does not currently provide authentication, a public JSON API, background jobs, or an upload-history table. Uploaded CSV files are read from PHP's temporary upload location; application code does not retain the files.

## 2. Technology stack

| Component | Responsibility |
| --- | --- |
| Symfony 7.4 | HTTP handling, routing, dependency injection, forms, validation, sessions and console commands |
| Twig | Server-rendered HTML and escaped output |
| Doctrine ORM and DoctrineBundle | Entity mapping, repository integration and dependency injection |
| Doctrine DBAL | Parameterized SQL, bulk upserts and transactions |
| Doctrine Migrations | Versioned table creation |
| MySQL/MariaDB | Persistent assignments; SQL uses `ON DUPLICATE KEY UPDATE` |
| Symfony Mime | Uploaded-file MIME detection |
| Symfony Security CSRF | Upload form CSRF protection |
| Plain CSS and JavaScript | Page styling and showing/hiding the upload form |
| PHPUnit, BrowserKit and CSS Selector | Unit and HTTP/database integration tests |
| PHPStan with Doctrine extension | Static analysis at level 10 |
| PHP CS Fixer | Symfony coding style and strict types |

`composer.json` declares PHP >=8.2. The installed development toolchain has been tested with PHP 8.4; PHPUnit 13 requires PHP 8.4. Use the locked dependencies and run `composer check-platform-reqs` to check the actual environment. PDO MySQL and the extensions required by Composer must be available.

## 3. Directory structure

```text
src/
├── Controller/EmployeeCollaboration/
│   ├── Create/CreateCollaborationController.php
│   └── List/ListCollaborationController.php
├── Entity/Employees/EmployeesCollaboration.php
├── Exception/InvalidCsv.php
├── Form/EmployeeCollaboration/Create/
│   ├── FormCreateEmployeeCollaborationInterface.php
│   └── Csv/EmployeeCollaborationCsvUploadType.php
├── Infrastructure/
│   ├── DateParser.php
│   ├── SystemTodayProvider.php
│   └── FileUpload/
│       ├── AssignmentReaderInterface.php
│       └── CsvAssignmentReader.php
├── Repository/EmployeesCollaboration/
│   ├── Create/
│   │   ├── CreateEmployeesCollaborationInterface.php
│   │   └── CreateEmployeesCollaborationRepository.php
│   └── List/
│       ├── ListEmployeesCollaborationInterface.php
│       └── ListEmployeesCollaborationRepository.php
├── Service/EmployeesCollaboration/
│   ├── Create/
│   │   ├── CreateCollaborationServiceInterface.php
│   │   ├── CreateCollaborationService.php
│   │   └── DTO/FileParsedDataDTO.php
│   └── List/
│       ├── ListCollaborationServiceInterface.php
│       ├── ListCollaborationService.php
│       ├── LongestTeamCalculator.php
│       ├── PeriodMerger.php
│       └── OverlapCalculator.php
├── Validator/AssignmentRecordValidator.php
└── Kernel.php

config/       Symfony, Doctrine and service configuration
migrations/   Single consolidated creation migration
public/       HTTP entry point, CSS and JavaScript
templates/   Base layout and collaboration page
tests/       Unit tests, integration tests and bootstrap
 examples/    Example employee CSV
 bin/         Console and test entry points
 var/         Generated cache and logs
 vendor/      Composer dependencies
```

The application uses feature-based Create/List folders. There are no separate Application or Domain directories. Interfaces define the boundaries used by the controllers and services; helper calculators are injected as concrete classes.

## 4. HTTP request flow

| Method and URL | Route name | Controller | Result |
| --- | --- | --- | --- |
| `GET /` | `employee_collaboration` | `ListCollaborationController` | Page with saved results and a hidden upload form |
| `POST /` | `employee_collaboration_create` | `CreateCollaborationController` | Import, then redirect on success; render form errors on failure |

```mermaid
flowchart TD
    GET[GET /] --> LC[ListCollaborationController]
    LC --> LS[ListCollaborationService]
    LS --> LR[ListEmployeesCollaborationRepository]
    LR --> DB[(sirma)]
    LS --> CALC[LongestTeamCalculator]
    CALC --> MERGE[PeriodMerger]
    CALC --> OVERLAP[OverlapCalculator]
    LC --> VIEW[Twig collaboration page]
    POST[POST /] --> CC[CreateCollaborationController]
    CC --> FORM[Symfony upload form]
    CC --> CS[CreateCollaborationService]
    CS --> READER[CsvAssignmentReader]
    CS --> VALIDATOR[AssignmentRecordValidator]
    CS --> CR[CreateEmployeesCollaborationRepository]
    CR --> DB
    CC -->|303 success| GET
    CC -->|422 validation error| VIEW
```

The GET controller creates the form, calls `getList()` and `hasAssignments()`, and renders Twig. These are separate reads: the result query retrieves assignments, while the existence check currently performs a count.

The POST controller binds the request to the form and invokes the import service. A successful import adds a flash message and returns HTTP 303 to `/`, preventing refresh from resubmitting the form. An `InvalidCsv` exception is attached to the file field; the same page renders with HTTP 422, saved results, and the upload panel open. Database and unexpected programming exceptions are not converted into CSV validation errors.

## 5. CSV parsing and DTO

### Input format

```csv
EmpID,ProjectID,DateFrom,DateTo
143,10,2024-01-01,2024-03-31
218,10,2024-02-01,NULL
```

`CsvAssignmentReader::read()` uses `fgetcsv()` and returns **one** `FileParsedDataDTO`. The DTO's public readonly `$data` property contains a list of record arrays:

```php
[
    [
        'employeeId' => '143',
        'projectId' => '10',
        'dateFrom' => '2024-01-01',
        'dateTo' => '2024-03-31',
    ],
    [
        'employeeId' => '218',
        'projectId' => '10',
        'dateFrom' => '2024-02-01',
        'dateTo' => null,
    ],
]
```

Reader behavior:

- Recognizes an optional first nonblank header with the exact names shown above, allowing surrounding whitespace and a UTF-8 BOM on the first header field.
- Skips blank CSV rows and handles quoted values, embedded commas and embedded newlines using PHP's CSV parser.
- Requires four columns; structural errors identify the CSV record number.
- Converts case-insensitive `NULL` in DateTo, after trimming for comparison, into PHP `null`.
- Preserves other values for validation, including raw ID strings. It does not cast `143abc` into `143`.
- Closes the file even if reading fails. Empty files and header-only files produce an empty DTO; the import service rejects that empty input.

The DTO also permits programmatically supplied integer IDs and mixed date values. Parsing is not entity creation, business validation, or persistence. **The reader returns an array-backed DTO, not a generator:** all parsed records remain in memory until the import finishes.

## 6. Validation and normalization

### Upload-level validation

`EmployeeCollaborationCsvUploadType` defines an unmapped `FileType` field. Its constraints require a file and validate the `.csv` extension and accepted CSV/text MIME types. There is no application `maxSize` constraint. The browser's `accept` attribute is a file-picker hint; server-side validation remains authoritative.

The form uses CSRF token ID `employee_upload`. This overrides the framework's default `submit` token ID. `employee_upload` is not in the configured stateless token list, so this form uses session-backed CSRF protection.

### Record-level validation

`AssignmentRecordValidator::validateAndNormalize()` validates a record using Symfony constraints before producing database-ready values.

| Rule | Implementation |
| --- | --- |
| Required record keys; no unexpected keys | `Collection` |
| IDs must be supplied | `NotBlank` |
| IDs must be integer values or strings | `Type` inside `Sequentially` |
| Reject partial numbers, fractions and signs | `Regex` |
| IDs must fit positive signed SQL INT | `Callback` and `FILTER_VALIDATE_INT`, range 1–2,147,483,647 |
| Start date is required | `NotBlank` |
| Dates must be supported strings or DateTime objects | `Callback` using `DateParser` |
| End date may be null | Date callback accepts null; the key is still required |
| End date must not precede start date | Cross-field `Callback`; null is compared with today |

Supported string formats are `YYYY-MM-DD`, `YYYY/MM/DD`, `DD/MM/YYYY`, `DD.MM.YYYY`, and `DD-MM-YYYY`. Month and day have two digits. Year-last formats are day-first. Impossible calendar dates are rejected. A blank end date is not equivalent to `NULL` and is rejected.

`DateTimeInterface` values are normalized to their calendar date. Valid date strings are trimmed, parsed at UTC midnight, and formatted as `Y-m-d`. IDs are converted to integers only after validation. The normalized shape is:

```php
array{
    employeeId: int,
    projectId: int,
    dateFrom: string,
    dateTo: ?string
}
```

Errors identify the record and field, for example `Record 4: dateTo must be on or after dateFrom (NULL means today).` Validation record numbers refer to DTO records after header/blank-row removal, not necessarily physical file lines. Validation stops at the first invalid record, but can report multiple invalid fields on that record.

`SystemTodayProvider` exists as a UTC clock helper but is currently not injected into this flow. The import service captures today once per import and passes it to the validator. The team calculator separately captures today once per calculation.

## 7. Import and persistence

`CreateCollaborationService::validateFormData()` checks submission state, form validity, the uploaded-file object and nonempty parsed data. `insertBatch()` validates and normalizes each DTO record, prepares flat SQL parameters, and sends them through `CreateEmployeesCollaborationInterface`.

Each assignment contributes exactly four parameters:

```php
[$employeeId, $projectId, $dateFrom, $dateTo]
```

A batch contains up to 500 records, therefore up to 2,000 parameters. The repository verifies that the parameter count is divisible by four and generates one `(?, ?, ?, ?)` group per record. Bound parameters keep input values out of the SQL text.

The upsert uses:

```sql
INSERT INTO employees_collaboration
    (empoyee_id, project_id, date_from, date_to)
VALUES (?, ?, ?, ?)
ON DUPLICATE KEY UPDATE date_to = VALUES(date_to)
```

Existing assignments are matched by employee, project and start date. Matching rows update only `date_to`; a different start date creates another assignment. Within one upload, the last occurrence of a matching key wins. Rows absent from the upload are retained. The returned count is the number of processed input records, not the database's affected-row count.

All batches share a DBAL transaction. If a later record fails validation or an SQL statement fails, previous inserts and updates in that upload roll back. The service creates no temporary entities and does not use ORM `persist()`/`flush()` for imports. This avoids retaining thousands of entities in Doctrine's unit of work.

## 8. Database and entity

The configured database name is `sirma`. `Version20260921120000` is the single consolidated migration and creates `employees_collaboration` if it does not exist.

| Column | SQL type | Meaning |
| --- | --- | --- |
| `id` | INT, primary key, auto increment | Stored assignment identity |
| `empoyee_id` | INT, NOT NULL | Employee ID; spelling intentionally matches the schema |
| `project_id` | INT, NOT NULL | Project ID |
| `date_from` | DATE, NOT NULL | Inclusive start date |
| `date_to` | DATE, DEFAULT NULL | Inclusive end date; null means ongoing |

There is no `days_worked` or `days_in_company` column in the consolidated schema. Durations are derived from dates in PHP. The table uses InnoDB, utf8mb4 and utf8mb4_unicode_ci.

| Index | Columns | Purpose |
| --- | --- | --- |
| `PRIMARY` | `id` | Unique row identity and efficient ID lookup |
| `uniq_employee_project_start_date` | `empoyee_id, project_id, date_from` | Prevents duplicate assignments and drives upsert matching |
| `idx_project_employee_date_range` | `project_id, empoyee_id, date_from, date_to` | Supports lookups beginning with project and then employee/date |

Composite index column order matters: the last index is suitable for queries starting with project, not generally for filtering only by end date. The current unfiltered list query still reads the complete table; the index does not eliminate that work.

`EmployeesCollaboration` provides Doctrine mapping and typed getters. Its constructor rejects a non-null end date before the start date. The upload path does not rely on that constructor for validation because it writes through DBAL. The entity's configured ORM repository is `ListEmployeesCollaborationRepository`.

Migration rollback refuses to drop the table because `CREATE TABLE IF NOT EXISTS` may adopt a table that existed before the migration. Editing an applied migration does not update an existing database. Use new migrations for subsequent schema changes; older installations may retain legacy columns or migration-history entries from before consolidation.

## 9. Longest-team calculation

### Responsibilities

| Class/method | Purpose |
| --- | --- |
| `ListEmployeesCollaborationRepository::findLongestTeam()` | Returns raw database rows using a single SELECT; despite its name, performs no team calculation |
| `ListCollaborationService::getList()` | Retrieves rows and delegates to the calculator |
| `LongestTeamCalculator::findLongestTeam()` | Groups assignments, compares employee pairs, accumulates totals and selects a winner |
| `PeriodMerger::merge()` | Sorts and combines duplicate, nested and overlapping ranges for one employee/project |
| `OverlapCalculator::calculate()` | Counts inclusive shared days between two sorted, non-overlapping range lists |

### Algorithm

1. Group records by project and employee, retaining every assignment range. Treat null end dates as today's UTC date without changing stored data.
2. Convert dates to integer day numbers in UTC. This avoids time-of-day and daylight-saving effects in interval arithmetic.
3. Merge each employee's overlapping ranges. Duplicate or nested assignments must not count the same project day twice.
4. Sort employee IDs numerically. Compare each unordered pair once per project, excluding self-pairs.
5. Use two pointers to compare their merged period lists. Each intersection contributes `max(0, min(endA, endB) - max(startA, startB) + 1)`. Advance whichever current period ends first.
6. Accumulate positive overlap by pair and project, and sum across projects.
7. Select the greatest total. Equal totals are resolved by the lowest first employee ID, then lowest second employee ID. Sort the winning project's rows numerically.

A shared boundary day counts as one day. Concurrent work on two different projects contributes separately to each project's total. Employees on the same project but on disjoint dates contribute zero. If no pair overlaps, the result is null.

### Result contract

```php
[
    'firstEmployeeId' => 143,
    'secondEmployeeId' => 218,
    'totalDays' => 7,
    'projectDays' => [10 => 6, 12 => 1],
]
```

For example, on project 10, January 1–10 and January 5–12 overlap for six inclusive days. If that pair shares one day on project 12, the total is seven.

### Cost and limitations

The repository loads all rows into PHP; grouping and pair totals require additional memory. Merging a list of `r` ranges sorts it in roughly `O(r log r)` time. Comparing two merged lists of lengths `a` and `b` takes `O(a + b)` time. A project with `e` employees has `e(e-1)/2` candidate pairs, so the overall calculation can still be quadratic in employees per project.

The 10,000-row integration fixture has 5,000 projects with two employees each. It verifies large input handling, not performance for 10,000 employees on one project. Splitting the code into classes improves responsibilities and testability; it does not remove this pair-count cost. There is no background processing, pagination, or cached winning result.

## 10. Presentation

`templates/base.html.twig` provides the document layout. `templates/employee/collaboration.html.twig` receives `form`, `result`, `hasAssignments`, and `showUpload`.

The page displays the winning pair, total days and a table with Employee ID #1, Employee ID #2, Project ID and Days worked. Empty states distinguish no saved assignments from saved assignments with no shared days.

`public/scripts/employees.js` toggles the upload panel's `hidden` attribute, updates `aria-expanded`, and focuses the file input when opened. `public/styles/employees.css` provides layout, form/table styling, responsive sizing and focus styling. No frontend build pipeline is required.

## 11. Dependency injection and SOLID

`config/services.yaml` discovers `App\` classes under `src/`, enables autowiring and autoconfiguration, and allows Symfony to resolve the single implementations of reader, service and repository interfaces. Class names, namespaces and file paths must agree for PSR-4 loading.

The main responsibility boundaries are:

- Controllers coordinate HTTP input/output.
- Form constraints validate the uploaded file.
- The reader parses CSV structure.
- The record validator validates and normalizes field values.
- The create service owns transaction and batch orchestration.
- Repositories own SQL and persistence access.
- Calculation classes own period merging, overlap arithmetic and winner selection.
- Twig owns presentation.

Controllers and services depend on narrow interfaces at storage/input boundaries. A new input reader can implement `AssignmentReaderInterface`; repository implementations can change behind their interfaces. Calculator helpers remain concrete, focused classes rather than interfaces with only one implementation. The design does not use an inheritance hierarchy for business behavior.

## 12. Configuration and installation

The repository ignores `.env`, `.env.dev`, `.env.test`, and local environment overrides. A new checkout therefore needs environment configuration before Composer's installation hooks run.

1. Install PHP with required extensions, Composer, and a running MySQL/MariaDB server.
2. Copy `.env.example` to `.env`. Set `APP_SECRET` and configure `DATABASE_URL`, either there or in `.env.local`.
3. Supply the real database server version, and credentials permitted to create `sirma` and its tables. URL-encode special characters in credentials.
4. Run `composer install`.

```dotenv
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/sirma?serverVersion=10.11.14-MariaDB&charset=utf8mb4"
```

The version above is an example matching the local MariaDB setup, not a universal value. Do not put real credentials in committed documentation.

The `post-install-cmd` hook invokes `database:setup` before cache clearing and asset setup. Database setup runs database creation with `--if-not-exists`, followed by pending migrations. It does not install/start the database server or create a database user. `composer update` currently runs Symfony auto-scripts only; it does not automatically run `database:setup`.

Start the local web server with `symfony server:start`, or use `php -S 127.0.0.1:8000 -t public` for HTTP. HTTPS at `https://127.0.0.1:8000/` depends on local Symfony TLS configuration.

| Configuration | Purpose |
| --- | --- |
| `config/routes.yaml` | Loads controller route attributes |
| `config/services.yaml` | Registers application services |
| `config/packages/doctrine.yaml` | Database connection, entity mappings, test suffix and ORM caches |
| `config/packages/doctrine_migrations.yaml` | Migration namespace and path |
| `config/packages/framework.yaml` | Secret, sessions and test session storage |
| `config/packages/csrf.yaml` | Framework CSRF defaults |
| `config/packages/validator.yaml` | Validator configuration |
| `config/packages/twig.yaml` | Twig configuration |
| `config/packages/cache.yaml`, `routing.yaml`, `property_info.yaml` | Supporting Symfony configuration |
| `config/bundles.php` | Enabled Symfony/Doctrine bundles |
| `config/reference.php` | Generated configuration reference, excluded from PHPStan |
| `config/preload.php` | Optional preload entry point |
| `public/index.php`, `src/Kernel.php` | HTTP runtime and application kernel |

DBAL SQL logging and profiling are disabled to avoid accumulating import statements in memory. Production ORM query/result cache pools are configured; this does not cache the custom DBAL list calculation.

## 13. Tests and quality checks

`tests/bootstrap.php` loads Composer and environment variables. PHPUnit forces `APP_ENV=test`. Doctrine adds `_test` plus an optional `TEST_TOKEN` to the database name. Integration tests assert a `_test` suffix before clearing their table; the current guard is intended for ordinary serial execution, not arbitrary nonempty parallel test tokens.

For a new checkout, create an ignored `.env.test` (or `.env.test.local`) with a test secret and kernel class, and configure separate database credentials if required:

```dotenv
KERNEL_CLASS='App\Kernel'
APP_SECRET='local-test-secret'
```

`composer test` creates the test database if absent, runs migrations, then executes PHPUnit. Integration tests delete rows from `employees_collaboration` in the test database, so use a dedicated test database without valuable records.

| Test class | Coverage |
| --- | --- |
| `AssignmentRecordValidatorTest` | Normalization, ID types/ranges, missing fields, invalid dates, date objects and date order |
| `DateParserTest` | Supported formats, leap dates, impossible dates and day-first parsing |
| `EmployeesCollaborationTest` | Entity date storage and date-order invariant |
| `PeriodMergerTest` | Unsorted, duplicate, nested, overlapping and disjoint ranges |
| `OverlapCalculatorTest` | Inclusive intersections, empty lists and disjoint periods |
| `ListCollaborationServiceTest` | No result, project totals and avoiding duplicate-day counting |
| `CsvAssignmentReaderTest` | DTO shape, headers, quoting, null values, empty input and read errors |
| `DatabaseImportTest` | Persistence, upsert, rollback, ongoing assignments, team results and 10,000 rows |
| `EmployeeUploadTest` | GET form/results, successful upload/redirect, validation errors and CSRF rejection |

At the latest full verification before this document was written: 57 tests and 188 assertions passed, and PHPStan reported no errors. This is a recorded result, not a guarantee about future changes.

```sh
composer test
composer phpstan
composer cs-fix
composer code-fix
php bin/console lint:container
php bin/console lint:twig templates
php bin/console doctrine:schema:validate
```

`composer cs-fix` changes files. For a read-only style check, use `vendor/bin/php-cs-fixer fix --dry-run --diff`. In restricted environments that cannot open worker sockets, use `--sequential` for PHP CS Fixer and `--debug` for PHPStan.

Additional commands: `composer database:setup` prepares the application database, `composer test:setup` prepares the test database, `composer db-migrate:generate` scaffolds a migration, and `composer db-migrate:next` runs the next migration.

## 14. Operational boundaries and extension points

There is no application row/file-size cap, but PHP/web-server upload limits, execution time, memory and database transaction limits still apply. Inputs are fully buffered in the DTO; database writes alone are batched. A very large import can hold locks for the duration of its transaction.

The reader and validator have different contracts: readers preserve raw IDs; only validated records contain integer IDs. Keep that distinction when adding input formats. Preserve parameter order if changing the import schema, and update the repository's placeholder count with it.

A nullable end date is stored as null and advances with today during reporting. Do not replace it permanently with an import-time date unless the intended behavior changes. A future-start ongoing assignment currently fails validation because today would precede its start.

The date parser validates formats and calendar dates but does not explicitly validate MySQL's complete DATE year range. Such out-of-range dates can still produce a database error. There is no user-facing retry policy for database failures or deadlocks.

To change overlap behavior, update `PeriodMerger`/`OverlapCalculator` and their tests. To change ranking, update `LongestTeamCalculator`. To add access control, introduce it at HTTP/security boundaries; CSRF protection alone is not authentication. To change the schema of an existing installation, add a new migration rather than modifying an already-recorded migration.
