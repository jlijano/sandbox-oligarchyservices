<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/prospects.php';

function expect_same($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $label . ' failed. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function expect_float_same(float $expected, $actual, string $label): void
{
    if (abs($expected - (float) $actual) > 0.001) {
        fwrite(STDERR, $label . ' failed. Expected ' . $expected . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$fixture = file_get_contents(__DIR__ . '/fixtures/prospects-import-template-sample.csv');
if ($fixture === false) {
    fwrite(STDERR, 'Could not read prospects import fixture.' . PHP_EOL);
    exit(1);
}

$rows = prospect_parse_import_rows($fixture);
expect_same(1, count($rows), 'official template row count');
$row = $rows[0];

expect_same('Acme Clinics', $row['company'], 'company');
expect_same('New Lead', $row['status'], 'status');
expect_same('https://acme-clinics.example', $row['website'], 'website');
expect_same('Healthcare', $row['industry_category'], 'industry_category');
expect_same('IT asset disposition, secure data destruction', $row['recommended_services'], 'recommended_services');
expect_float_same(88.0, $row['conversion_percentage'], 'conversion_percentage');
expect_same("First note line\nSecond note line", $row['notes'], 'multiline notes');
expect_same('Jane Doe', $row['contact'], 'contact');
expect_same('jane.doe@acme-clinics.example', $row['email'], 'email');
expect_same('+1 555 0100', $row['phone'], 'phone');
expect_same('Denver, CO', $row['location'], 'location');
expect_same('High', $row['priority'], 'priority');
expect_same('2026-06-20', $row['last_contact'], 'last_contact');
expect_same('2026-06-21', $row['last_verified'], 'last_verified');
expect_same('Verify phone before outreach', $row['data_gaps_validation_notes'], 'data_gaps_validation_notes');

$shortNewTemplateRow = "Short Tail Inc,New Lead,https://short-tail.example,IT Services,Managed IT support,80%\n";
$shortRows = prospect_parse_import_rows($shortNewTemplateRow);
expect_same(1, count($shortRows), 'short new-template row count');
expect_same('Managed IT support', $shortRows[0]['recommended_services'], 'short new-template recommended_services');
expect_float_same(80.0, $shortRows[0]['conversion_percentage'], 'short new-template conversion_percentage');

$oldTemplateRow = "Legacy Co,New Lead,https://legacy.example,Manufacturing,72%,Legacy notes,Leo Lead,leo@legacy.example\n";
$oldRows = prospect_parse_import_rows($oldTemplateRow);
expect_same(1, count($oldRows), 'old template row count');
expect_same('', $oldRows[0]['recommended_services'], 'old template recommended_services');
expect_float_same(72.0, $oldRows[0]['conversion_percentage'], 'old template conversion_percentage');
expect_same('Legacy notes', $oldRows[0]['notes'], 'old template notes');

echo 'Prospect import template mapping OK' . PHP_EOL;
