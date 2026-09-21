# Employee collaboration

Symfony application backed by the local MySQL/MariaDB database `sirma`. The home page displays the longest-working employee pair and their common projects from saved assignments, or a relevant empty state. **Upload assignments** reveals the CSV form. A successful upload redirects to the home page so refreshing does not resubmit it.

## Database setup

Doctrine ORM, DBAL, DoctrineBundle and DoctrineMigrationsBundle are installed. Configure your local credentials in the untracked `.env.local`:

```dotenv
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/sirma?serverVersion=YOUR_SERVER_VERSION&charset=utf8mb4"
```

Use your actual server version (for example `8.0.32` for MySQL or `10.11.14-MariaDB` for MariaDB); URL-encode special characters in credentials. SQL window functions require MySQL 8+ or MariaDB 10.2+.

```sh
composer install
composer database:setup
```

The setup script runs `doctrine:database:create --if-not-exists` followed by `doctrine:migrations:migrate --no-interaction`. Database creation must precede migrations because Doctrine stores migration history inside the selected database. The table migration uses `CREATE TABLE IF NOT EXISTS`. It does not alter or erase an existing table; `php bin/console doctrine:schema:validate` checks whether an existing schema matches. Rollback intentionally refuses to drop a potentially pre-existing table.

`employees_collaboration` contains:

| Column | Meaning |
| --- | --- |
| id | Auto-increment primary key |
| empoyee_id | Employee ID; spelling follows the requested schema |
| project_id | Project ID |
| days_worked | Inclusive duration of this assignment |
| date_from | Start date |
| date_to | End date |

A unique index on `(empoyee_id, project_id, date_from)` defines assignment identity. Re-uploading that combination updates `date_to` and `days_worked`; a different start date creates a separate period. If the same key appears multiple times in a file, the last record wins. Records absent from an upload are retained. A project/employee/date index supports the reporting query.

## Uploads and calculations

CSV columns: `EmpID,ProjectID,DateFrom,DateTo`, with an optional header. Quoted fields, whitespace, blank lines and a UTF-8 BOM are supported. IDs are positive signed SQL INT values. Supported dates: `YYYY-MM-DD`, `YYYY/MM/DD`, `DD/MM/YYYY`, `DD.MM.YYYY`, `DD-MM-YYYY`. Year-last dates are day-first. `NULL` DateTo is resolved to today's UTC date when imported and stored as that date; it does not automatically advance afterward.

There is no application file-size or row-count cap. PHP's `upload_max_filesize`, `post_max_size`, execution time and web-server limits still apply and should be configured for the intended workload. CSV input streams through a generator into 500-row database batches, not a file-sized PHP array or ORM unit of work. All batches share one transaction: invalid later records roll back earlier inserts and updates. SQL query logging/profiling is disabled to avoid accumulating import statements in memory.

Reporting merges overlapping periods per employee/project using window functions, joins overlapping periods of distinct employees on the same project, and ranks pairs by their combined inclusive overlap days. Only the winning pair's project rows return to PHP. Nested and overlapping periods cannot inflate the total. Simultaneous work on multiple projects contributes to each project. Ties select the numerically lowest pair.

A database join still has workload-dependent cost: many employees overlapping on one project can produce many pairs. The 10,000-row test uses 5,000 projects with two employees each; it verifies streaming import and reporting, not a constant-time guarantee for every possible distribution.

## Structure

- `Entity/EmployeesCollaboration`: Doctrine mapping and assignment invariants.
- `Repository/EmployeesCollaborationRepository`: batch upserts and SQL reporting.
- `Service/AssignmentImporter`: transactional import orchestration.
- `Infrastructure/Csv`: streaming CSV and date parsing.
- `Infrastructure/Time`: UTC clock.
- `Form` and `Controller`: upload validation and HTTP flow.
- `migrations`: versioned schema.

There are no Application or Domain folders.

## Tests and checks

Tests use the configured connection with a `_test` database suffix (`sirma_test`). Use `.env.test.local` to configure a separate test server/account if needed. Integration tests clear only the test table and assert the database suffix before doing so.

```sh
APP_ENV=test php bin/console doctrine:database:create --if-not-exists
APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
composer test
composer phpstan
vendor/bin/php-cs-fixer fix --dry-run --diff
php bin/console doctrine:schema:validate
```

Tests cover date parsing, entity invariants, upload validation and CSRF, empty states, saved results after redirect, upserts, entity hydration, rollback after a bad late row, overlapping/nested periods, project totals, ties and a 10,000-row import.
