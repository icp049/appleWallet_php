<?php

require('vendor/autoload.php');

use PKPass\PKPass;

// Handle form submission
if (isset($_POST['name']) && isset($_POST['accountNumber']) && isset($_POST['barcodeImage'])) {
    setlocale(LC_MONETARY, 'en_US');

    $name = stripslashes($_POST['name']);
    $accountNumber = stripslashes($_POST['accountNumber']);
    $isaMember = isset($_POST['isaMember']); // Check if user is a member
    $barcodeImage = $_POST['barcodeImage'];  // Base64-encoded barcode image

    $baseName = preg_replace('/[^a-z0-9]/', '', strtolower($name));
    $baseAccountNumber = preg_replace('/[^0-9]/', '', $accountNumber); 

    $folderName = !empty($baseName) && !empty($baseAccountNumber) ? 
                  strtolower(preg_replace('/[^a-z0-9_]/', '', "{$baseName}_{$baseAccountNumber}")) : 
                  'default_folder'; 

    $passFolder = __DIR__ . "/temp/{$folderName}.pass";

    // Ensure folder is created
    if (!file_exists($passFolder)) {
        mkdir($passFolder, 0777, true);

        // Decode the base64 barcode image
        $barcodeImage = str_replace('data:image/png;base64,', '', $barcodeImage);
        $barcodeImage = str_replace(' ', '+', $barcodeImage);
        $decodedBarcodeImage = base64_decode($barcodeImage);

        // Save the decoded image
        $barcodePath = "{$passFolder}/strip.png";
        file_put_contents($barcodePath, $decodedBarcodeImage);

        // Copy logo as logo.png regardless of membership status
        if ($isaMember) {
            copy(__DIR__ . "/images/logo.png", "{$passFolder}/logo.png"); // Member logo
        } else {
            copy(__DIR__ . "/images/logo2.png", "{$passFolder}/logo.png"); // Non-member logo, saved as logo.png
        }

        $filesToCopy = ['icon.png', 'icon@2x.png'];
        foreach ($filesToCopy as $file) {
            copy(__DIR__ . "/images/{$file}", "{$passFolder}/{$file}");
        }

        // Create pass.json file
        $serialNumber = rand(100000, 999999) . '-' . rand(100, 999) . '-' . rand(100, 999); // Unique serialNumber
        $passJson = [
            "formatVersion" => 1,
            "passTypeIdentifier" => "pass.applewallet2",
            "serialNumber" => $serialNumber,
            "teamIdentifier" => "CLGF4TH7AG",
            "organizationName" => "Regina Public Library",
            "description" => "RPL Library Card",
            "backgroundColor" => "rgb(255, 255, 255)",
            "foregroundColor" => "rgb(110, 58, 207)",
            "storeCard" => [
                "secondaryFields" => [
                    [
                        "key" => "name",
                        "label" => "Name",
                        "value" => $name
                    ],
                    [
                        "key" => "accountNumber",
                        "label" => "Account Number",
                        "value" => $accountNumber
                    ],
                ],
            ],
        ];

        file_put_contents("{$passFolder}/pass.json", json_encode($passJson, JSON_PRETTY_PRINT));

        // Generate the pass using PKPass
        try {
            $pass = new PKPass('key_new.p12', 'applewallet2');
            $pass->setData(json_encode($passJson));

            $pass->addFile("{$passFolder}/icon.png");
            $pass->addFile("{$passFolder}/icon@2x.png");
            $pass->addFile("{$passFolder}/logo.png"); // Always logo.png
            $pass->addFile("{$passFolder}/strip.png");

            $pass->create(true);

        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            exit;
        }
    }
} else {
    echo "Invalid submission.";
}
