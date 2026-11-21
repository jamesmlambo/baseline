<?php

declare(strict_types=1);

namespace App\Services\Staff\Attendance;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * ATT-01: punches from a CSV file, e.g. an export from the terminal's software.
 *
 * Needs a header row. Recognised columns (case-insensitive):
 * - person: "person_id", "employee_id", "employee id", "employee no", "person id", "id"
 * - time:   "timestamp", "time", "date time", "datetime" — or separate "date" and "time"
 * - direction (optional): "direction", "attendance status", "status" — values like in / out /
 *   check-in / check-out
 * - device (optional): "device", "device serial", "device name"
 * Times without a timezone are read as South African time. The exact Hikvision export layout is
 * still to be confirmed on the real device (VERIFY, docs/HIKVISION_POC.md).
 */
class CsvAttendanceSource implements AttendanceSource
{
    private const PERSON = ['person_id', 'employee_id', 'employee id', 'employee no', 'employee no.', 'person id', 'id'];

    private const TIMESTAMP = ['timestamp', 'time', 'date time', 'datetime', 'date and time'];

    private const DIRECTION = ['direction', 'attendance status', 'status', 'type'];

    private const DEVICE = ['device', 'device serial', 'device name', 'serial'];

    public function __construct(private readonly string $path) {}

    public function source(): PunchSource
    {
        return PunchSource::Csv;
    }

    /**
     * @return iterable<int, PunchData|SourceError>
     */
    public function read(?CarbonImmutable $from = null, ?CarbonImmutable $until = null): iterable
    {
        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            yield new SourceError(0, 'The file could not be opened.');

            return;
        }

        try {
            $header = fgetcsv($handle, escape: '\\');

            if ($header === false || $header === [null]) {
                yield new SourceError(1, 'The file is empty.');

                return;
            }

            $columns = array_map(fn (?string $name): string => mb_strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $name))), $header);
            $person = $this->find($columns, self::PERSON);
            $timestamp = $this->find($columns, self::TIMESTAMP);
            $date = array_search('date', $columns, true);
            $direction = $this->find($columns, self::DIRECTION);
            $device = $this->find($columns, self::DEVICE);

            if ($person === null || ($timestamp === null && $date === false)) {
                yield new SourceError(1, 'The header needs a person ID column and a time column (see the import help).');

                return;
            }

            $row = 1;

            while (($values = fgetcsv($handle, escape: '\\')) !== false) {
                $row++;

                if ($values === [null]) {
                    continue;
                }

                $personId = trim((string) ($values[$person] ?? ''));
                $when = $timestamp !== null && $date !== false && $columns[$timestamp] === 'time'
                    ? trim(($values[$date] ?? '').' '.($values[$timestamp] ?? ''))
                    : trim((string) ($values[$timestamp ?? $date] ?? ''));

                if ($personId === '' || $when === '') {
                    yield new SourceError($row, 'Missing person ID or time.');

                    continue;
                }

                try {
                    $punchedAt = CarbonImmutable::parse($when, 'Africa/Johannesburg');
                } catch (Throwable) {
                    yield new SourceError($row, "Unreadable time “{$when}”.");

                    continue;
                }

                if (($from !== null && $punchedAt->lessThan($from)) || ($until !== null && $punchedAt->greaterThan($until))) {
                    continue;
                }

                yield new PunchData(
                    PunchSource::Csv,
                    $personId,
                    $punchedAt,
                    $this->direction($direction !== null ? (string) ($values[$direction] ?? '') : ''),
                    $device !== null ? (trim((string) ($values[$device] ?? '')) ?: null) : null,
                    raw: array_combine(array_slice($columns, 0, count($values)), array_slice($values, 0, count($columns))) ?: [],
                );
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $names
     */
    private function find(array $columns, array $names): ?int
    {
        foreach ($names as $name) {
            $index = array_search($name, $columns, true);

            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    private function direction(string $value): PunchDirection
    {
        $value = mb_strtolower(trim($value));

        return match (true) {
            in_array($value, ['in', 'check-in', 'check in', 'checkin', 'clock in', 'clock-in', 'entry'], true) => PunchDirection::In,
            in_array($value, ['out', 'check-out', 'check out', 'checkout', 'clock out', 'clock-out', 'exit'], true) => PunchDirection::Out,
            default => PunchDirection::Unknown,
        };
    }
}
