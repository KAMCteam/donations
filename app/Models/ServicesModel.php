<?php

namespace App\Models;

use Config\Trak;
use DateTime;
use Exception;

/**
 * Reads patient demographics from the TrakCare (InterSystems IRIS) ODBC source.
 *
 * Migrated from the CodeIgniter 3 Services_model. It never touched the CI
 * database, so it is a plain class rather than a CodeIgniter\Model; the
 * connection details moved out of the source and into Config\Trak / .env.
 */
class ServicesModel
{
    private Trak $config;

    public function __construct()
    {
        $this->config = config(Trak::class);
    }

    /**
     * @return resource|false
     */
    public function connect()
    {
        if (! function_exists('odbc_connect')) {
            return false;
        }

        return @odbc_connect(
            $this->config->connectionString(),
            $this->config->username,
            $this->config->password
        );
    }

    /**
     * @param resource $connection
     */
    public function disconnect($connection): void
    {
        odbc_close($connection);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_patient_info(int|string|null $mrn): ?array
    {
        $conn = $this->connect();

        if (! $conn) {
            return ['error' => 'ODBC connection failed'];
        }

        // MUST use odbc_prepare for safety
        $sql  = 'SELECT TOP 1 * FROM Custom_MEMS_Query.Patient_Files WHERE URN = ?';
        $stmt = odbc_prepare($conn, $sql);

        if (! $stmt) {
            $this->disconnect($conn);

            return ['error' => 'Failed preparing statement'];
        }

        if (! odbc_execute($stmt, [$mrn])) {
            $this->disconnect($conn);

            return ['error' => 'Failed executing statement'];
        }

        // Fetch row as associative array
        $row = odbc_fetch_array($stmt);

        $this->disconnect($conn);

        // If no row found
        if (! $row) {
            return null;
        }

        $city = $row['City'] ?? '';

        if (empty($city)) {
            $city = 'N/A';
        }

        return [
            'name'         => trim(($row['FIRST_NAME'] ?? '') . ' ' . ($row['SECOND_NAME'] ?? '') . ' ' . ($row['FOURTH_NAME'] ?? '')),
            'city'         => $city,
            'phone_number' => $row['MOBILE_NO'] ?? '',
            'gender'       => substr((string) ($row['GENDER'] ?? ''), 0, 1),
            'age'          => $this->calculate_age($row['DOB'] ?? null),
        ];
    }

    /**
     * Full years between the given date and today; 0 for anything unusable.
     */
    public function calculate_age(?string $date): int
    {
        if (empty($date)) {
            return 0;
        }

        try {
            $birthDate = new DateTime($date);
            $today     = new DateTime();

            // Prevent negative years if future birth date
            if ($birthDate > $today) {
                return 0;
            }

            return $birthDate->diff($today)->y; // number of full years
        } catch (Exception) {
            return 0; // invalid date format
        }
    }
}
