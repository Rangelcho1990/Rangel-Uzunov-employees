<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class EmployeeUploadTest extends WebTestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        self::assertStringEndsWith('_test', (string) $connection->getDatabase());
        $connection->executeStatement('DELETE FROM employees_collaboration');
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->paths as $path) {
            unlink($path);
        }
    }

    public function testRootShowsUploadForm(): void
    {
        static::createClient()->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/"][method="post"][enctype="multipart/form-data"]');
        self::assertSelectorExists('input[type="file"]');
        self::assertSelectorExists('#upload-panel[hidden]');
        self::assertSelectorTextContains('#results-heading', 'No assignments yet');
        self::assertSelectorTextContains('#toggle-upload', 'Upload assignments');
        self::assertSelectorExists('input[name="employee_collaboration_csv_upload[_token]"]');
    }

    public function testUploadDisplaysWinningPairAndProjectBreakdown(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $form = $crawler->selectButton('Save assignments')->form();
        $client->request('POST', '/', $form->getPhpValues(), ['employee_collaboration_csv_upload' => ['file' => $this->upload("EmpID,ProjectID,DateFrom,DateTo\n143,10,2023-01-01,2023-06-30\n218,10,2023-03-01,2023-08-31\n143,12,2024-01-01,2024-04-30\n218,12,2024-02-01,2024-04-30\n")]]);
        self::assertResponseRedirects('/', 303);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#results-heading', '143 & 218');
        self::assertSelectorTextContains('.total', '212');
        self::assertSelectorCount(2, 'tbody tr');
        self::assertSelectorTextContains('tbody tr:first-child', '122');
        self::assertSelectorTextContains('tbody tr:last-child', '90');
    }

    public function testNoOverlapShowsEmptyResult(): void
    {
        $this->submitCsv("1,10,2024-01-01,2024-01-02\n2,10,2024-01-03,2024-01-04");
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#results-heading', 'No overlapping assignments');
    }

    public function testMalformedCsvShowsRowError(): void
    {
        $this->submitCsv('1,10,2024-02-30,2024-03-01');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Record 1: Invalid date');
        self::assertSelectorExists('#upload-panel:not([hidden])');
    }

    public function testMissingFileIsRejected(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $client->submit($crawler->selectButton('Save assignments')->form());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Choose a CSV file');
    }

    public function testWrongExtensionIsRejected(): void
    {
        $this->submitCsv('1,10,2024-01-01,2024-01-02', 'employees.txt');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Upload a valid CSV file');
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/', ['employee_collaboration_csv_upload' => ['_token' => 'invalid']], ['employee_collaboration_csv_upload' => ['file' => $this->upload('1,10,2024-01-01,2024-01-02')]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'CSRF token is invalid');
    }

    private function submitCsv(string $csv, string $name = 'employees.csv'): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');
        $client->request('POST', '/', $crawler->selectButton('Save assignments')->form()->getPhpValues(), ['employee_collaboration_csv_upload' => ['file' => $this->upload($csv, $name)]]);
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }
    }

    private function upload(string $csv, string $name = 'employees.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'employee-upload-');
        self::assertNotFalse($path);
        $this->paths[] = $path;
        file_put_contents($path, $csv);

        return new UploadedFile($path, $name, 'text/csv', test: true);
    }
}
