<?php

require('vendor/autoload.php');

use PKPass\PKPass;
use Picqer\Barcode\BarcodeGeneratorPNG; // Include Barcode Generator

// Handle form submission
if (isset($_POST['name']) && isset($_POST['accountNumber'])) {
    setlocale(LC_MONETARY, 'en_US');

    $name = stripslashes($_POST['name']);
    $accountNumber = stripslashes($_POST['accountNumber']);
    $isaMember = isset($_POST['isaMember']); // Check if user is a member

    $barcodeGenerator = new BarcodeGeneratorPNG();
    $barcode = $barcodeGenerator->getBarcode($accountNumber, $barcodeGenerator::TYPE_CODABAR);

    $baseName = preg_replace('/[^a-z0-9]/', '', strtolower($name));
    $baseAccountNumber = preg_replace('/[^0-9]/', '', $accountNumber); 

    $folderName = !empty($baseName) && !empty($baseAccountNumber) ? 
                  strtolower(preg_replace('/[^a-z0-9_]/', '', "{$baseName}_{$baseAccountNumber}")) : 
                  'default_folder'; 

    $passFolder = __DIR__ . "/temp/{$folderName}.pass";

    // Ensure folder is created
    // if (!file_exists($passFolder)) {
    //     mkdir($passFolder, 0777, true);

    //     $barcodeImage = imagecreatefromstring($barcode);

    //     // Rescale barcode
    //     $scaleFactor = 0.5; // Adjust as needed
    //     $scaledWidth = imagesx($barcodeImage) * $scaleFactor;
    //     $scaledHeight = imagesy($barcodeImage) * $scaleFactor;

    //     $padding = 18; // Padding size in pixels
    //     $margin = 7; // Margin size in pixels

    //     $width = $scaledWidth + (2 * $margin) + (2 * $padding);
    //     $height = $scaledHeight + (2 * $margin) + (2 * $padding);

    //     $finalImage = imagecreatetruecolor($width, $height);
    //     $white = imagecolorallocate($finalImage, 255, 255, 255);
    //     imagefill($finalImage, 0, 0, $white);

    //     $barcodeX = $margin + $padding; 
    //     $barcodeY = $margin + $padding; 
    //     imagecopyresampled($finalImage, $barcodeImage, $barcodeX, $barcodeY, 0, 0, $scaledWidth, $scaledHeight, imagesx($barcodeImage), imagesy($barcodeImage));

    //     // Save the final image
    //     $barcodePath = "{$passFolder}/strip.png";
    //     imagepng($finalImage, $barcodePath);
    //     imagedestroy($finalImage);
    //     imagedestroy($barcodeImage);

    if (!file_exists($passFolder)) {
        mkdir($passFolder, 0777, true);
    
        $barcodeImage = imagecreatefromstring($barcode);
    
        // Get original dimensions
        $originalWidth = imagesx($barcodeImage);
        $originalHeight = imagesy($barcodeImage);
    
        // Container dimensions
        $containerWidth = 1125;
        $containerHeight = 432;
    
        $padding = 60; // Padding size in pixels
        $margin = 20;   // Margin size in pixels
    
        // Calculate the scale factor to fit within the container while maintaining the aspect ratio
        $availableWidth = $containerWidth - 2 * $margin - 2 * $padding;
        $availableHeight = $containerHeight - 2 * $margin - 2 * $padding;
    
        $scaleFactor = min($availableWidth / $originalWidth, $availableHeight / $originalHeight);
        $scaledWidth = $originalWidth * $scaleFactor;
        $scaledHeight = $originalHeight * $scaleFactor;
    
        // Create the final image
        $finalImage = imagecreatetruecolor($containerWidth, $containerHeight);
        $white = imagecolorallocate($finalImage, 255, 255, 255);
        imagefill($finalImage, 0, 0, $white);
    
        // Calculate the position to center the barcode with added padding and margin
        $barcodeX = $margin + $padding + ($availableWidth - $scaledWidth) / 2;
        $barcodeY = $margin + $padding + ($availableHeight - $scaledHeight) / 2;
    
        // Resample and draw the barcode on the final image
        imagecopyresampled($finalImage, $barcodeImage, $barcodeX, $barcodeY, 0, 0, $scaledWidth, $scaledHeight, $originalWidth, $originalHeight);
    
        // Save the final image
        $barcodePath = "{$passFolder}/strip.png";
        imagepng($finalImage, $barcodePath);
        imagedestroy($finalImage);
        imagedestroy($barcodeImage);
    
    
    
    

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

          
            // array_map('unlink', glob("{$passFolder}/*.*"));
            // rmdir($passFolder);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            exit;
        }
    }
} else {
    echo "Invalid submission.";
}
