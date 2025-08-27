<?php

require_once '../class/Customer.php';

$customer = new Customer();

$firstName = $_POST['first_name'] ?? "";
$middleName = $_POST['middle_name'] ?? "";
$lastName = $_POST['last_name'] ?? "";
$email = $_POST['email'] ?? "";
$password = $_POST['password'] ?? "";
$birthdate = $_POST['birthdate'] ?? "0000-00-00";
$phoneNumber = $_POST['phone_number'] ?? "";
$createdBy = 1;
$createdAt = date('Y-m-d H:i:s');
$emergencyContactName = $_POST['emergency_contact_name'] ?? "";
$emergencyContactNumber = $_POST['emergency_contact_number'] ?? "";
$img = $_POST['img'] ?? "";



$data = [
    'firstName' => $firstName,
    'middleName' => $middleName,
    'lastName' => $lastName,
    'email' => $email,
    'password' => $password,
    'birthdate' => $birthdate,
    'phoneNumber' => $phoneNumber,
    'createdBy' => $createdBy,
    'createdAt' => $createdAt,
    'emergencyContactName' => $emergencyContactName,
    'emergencyContactNumber' => $emergencyContactNumber,
    'img' => $img
   
   
];

$result = $customer->store($data);

echo json_encode($result);
?>