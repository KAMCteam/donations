<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Services_model extends CI_Model {
    public function connect() {
        $connect_string = "Driver={InterSystems IRIS ODBC35};Server=10.11.94.27;PORT=56772;Database=TRAK" ;
		$user="KAMC_SQL_WEB";
		$pass="p@D55rTMC";
		
        return odbc_connect($connect_string, $user, $pass);
    }

    public function disconnect($connection) {
        odbc_close($connection);
    }

    public function get_patient_info($mrn) {
        $conn = $this->connect();
        if (!$conn) {
            return ['error' => 'ODBC connection failed'];
        }

        // MUST use odbc_prepare for safety
        $sql = "SELECT TOP 1 * FROM Custom_MEMS_Query.Patient_Files WHERE URN = ?";
        $stmt = odbc_prepare($conn, $sql);

        if (!$stmt) {
            return ['error' => 'Failed preparing statement'];
        }

        $exec = odbc_execute($stmt, [$mrn]);

        if (!$exec) {
            return ['error' => 'Failed executing statement'];
        }

        // Fetch row as associative array
        $row = odbc_fetch_array($stmt);

        $this->disconnect($conn);

        // If no row found
        if (!$row) {
            return null;
        }

        $city = $row['City'];
        if (empty($city)) {
            $city = 'N/A';
        }
        
        $result = [
            'name' => $row['FIRST_NAME'] . ' ' . $row['SECOND_NAME'] . ' ' . $row['FOURTH_NAME'],
            'city' => $city,
            'phone_number' => $row['MOBILE_NO'],
            'gender' => substr($row['GENDER'], 0, 1),
            'age' => $this->calculate_age($row['DOB']),
        ];

        return $result;
    }

    public function calculate_age($date) {
        if (empty($date)) return 0;

        try {
            $hireDate = new DateTime($date);
            $today = new DateTime();

            // Prevent negative years if future birth date
            if ($hireDate > $today) return 0;

            $diff = $hireDate->diff($today);

            return $diff->y; // number of full years
        } catch (Exception $e) {
            return 0; // invalid date format
        }
    }
}