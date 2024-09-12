<?php

require('vendor/autoload.php');

use PKPass\PKPass;
use Picqer\Barcode\BarcodeGeneratorPNG; // Include Barcode Generator


// Handle form submission
if (isset($_POST['name'])) {
    setlocale(LC_MONETARY, 'en_US');

    $name = stripslashes($_POST['name']);
    $accountNumber = '29085006805780'; // You can get this dynamically





    // Generate Codabar barcode for account number
    $barcodeGenerator = new BarcodeGeneratorPNG();
    $barcode = $barcodeGenerator->getBarcode($accountNumber, $barcodeGenerator::TYPE_CODABAR);

    // Create a folder to save barcode
    $folderName = strtolower(preg_replace('/[^a-z0-9]/', '_', $name));
    $passFolder = __DIR__ . "/temp/{$folderName}.pass";

    // Ensure folder is created
    if (!file_exists($passFolder)) {
        mkdir($passFolder, 0777, true);

        $barcodeImage = imagecreatefromstring($barcode);

// Get original width and height of the barcode
$originalWidth = imagesx($barcodeImage);
$originalHeight = imagesy($barcodeImage);

// Adjust scale factor to fit barcode and include padding
$scaleFactor = 0.5; // Adjust as needed
$scaledWidth = $originalWidth * $scaleFactor;
$scaledHeight = $originalHeight * $scaleFactor;

// Add extra padding to the scaled dimensions
$padding = 18; // Padding size in pixels

// Set margins
$margin = 7; // Margin size in pixels

// Create a new blank image with margins and padding for the final result
$width = $scaledWidth + (2 * $margin) + (2 * $padding);
$height = $scaledHeight + (2 * $margin) + (2 * $padding);

$finalImage = imagecreatetruecolor($width, $height);

// Set background color to white
$white = imagecolorallocate($finalImage, 255, 255, 255);
imagefill($finalImage, 0, 0, $white);

// Rescale and copy the barcode onto the new image with padding
$barcodeX = $margin + $padding; // X position with margin and padding
$barcodeY = $margin + $padding; // Y position with margin and padding
imagecopyresampled($finalImage, $barcodeImage, $barcodeX, $barcodeY, 0, 0, $scaledWidth, $scaledHeight, $originalWidth, $originalHeight);

// Save the final image as strip.png in the pass folder
$barcodePath = "{$passFolder}/strip.png";
imagepng($finalImage, $barcodePath);

// Clean up and free memory
imagedestroy($finalImage);
imagedestroy($barcodeImage);

// Copy static files to the new folder
$filesToCopy = ['icon.png', 'icon@2x.png', 'logo.png'];
foreach ($filesToCopy as $file) {
    copy(__DIR__ . "/images/{$file}", "{$passFolder}/{$file}");
}

        

        // Create pass.json file
        $serialNumber = rand(100000, 999999) . '-' . rand(100, 999) . '-' . rand(100, 999); // Unique serialNumber
        $passJson = [
            "formatVersion" => 1,
            "passTypeIdentifier" => "pass.",
            "serialNumber" => $serialNumber,
            "teamIdentifier" => "",
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

            // Add files to the PKPass package
            $pass->addFile("{$passFolder}/icon.png");
            $pass->addFile("{$passFolder}/icon@2x.png");
            $pass->addFile("{$passFolder}/logo.png");
            $pass->addFile("{$passFolder}/strip.png");

          //create pkpass
            $pass->create(true);

          //delete
            array_map('unlink', glob("{$passFolder}/*.*"));
            rmdir($passFolder);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            exit;
        }
    }
} else {
   
?>
<html>
<head>
    <title>Pass Creator</title>
</head>
<body>
    <form action="" method="post">
        <label for="name">Name:</label>
        <input type="text" name="name" id="name" required />
        <button type="submit">Create Pass</button>
    </form>
</body>
</html>
<?php
}
?>
